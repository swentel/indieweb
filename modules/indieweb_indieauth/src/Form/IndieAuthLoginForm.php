<?php

namespace Drupal\indieweb_indieauth\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\externalauth\AuthmapInterface;
use IndieAuth\Client;
use p3k\HTTP;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class IndieAuthLoginForm.
 *
 * @package Drupal\indieweb_indieauth\Form
 */
class IndieAuthLoginForm extends FormBase {

  /**
   * The external auth authmap service.
   *
   * Note: this may be NULL when the service is not available.
   *
   * @var \Drupal\externalauth\AuthmapInterface
   */
  protected $externalAuthMap;

  /**
   * Set the authmap service.
   *
   * @param \Drupal\externalauth\AuthmapInterface $externalAuthMap
   *   The authmap service.
   */
  public function setExternalAuthMap(AuthmapInterface $externalAuthMap) {
    $this->externalAuthMap = $externalAuthMap;
  }

  /**
   * Get the configuration for the indieauth module.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The module's configuration.
   */
  protected function configuration() {
    return $this->configFactory()->get('indieweb_indieauth.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieauth_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    if ($this->configuration()->get('login_enable') && !empty($this->externalAuthMap)) {

      if ($this->currentUser()->isAuthenticated()) {
        if ($this->externalAuthMap->get($this->currentUser()->id(), 'indieweb')) {
          return [];
        }
        else {
          $form['map'] = [
            '#markup' => '<p>' . $this->t('Map your domain with your current user.') . '</p>',
          ];
        }
      }

      $form['domain'] = [
        '#title' => $this->t('Web Address'),
        '#type' => 'textfield',
        '#placeholder' => $this->t('yourdomain.com'),
        '#required' => TRUE,
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->currentUser()->isAnonymous() ? $this->t('Sign in') : $this->t('Map account'),
      ];
    }
    else {
      $form['#markup'] = '<p>' . $this->t('Web Sign-In is not enabled.') . '</p>';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!UrlHelper::isValid($form_state->getValue('domain'))) {
      $form_state->setErrorByName('domain', $this->t('This URL is not valid'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domain = $form_state->getValue('domain');

    // Add trailing slash if necessary.
    if (substr($domain, -1, 1) != '/') {
      $domain .= '/';
    }

    // Get the authorization endpoint for the domain. The IndieAuth client
    // does an HTTP request and has no idea it might be in a simpletest
    // environment, so check whether we are in a test or not. If so, set the
    // User-Agent.
    $client = new Client();
    if ($test_prefix = drupal_valid_test_ua()) {
      $httpClient = new HTTP();
      $httpClient->set_user_agent(drupal_generate_test_ua($test_prefix));
      $client::$http = $httpClient;
    }
    $authorization_endpoint = $client::discoverAuthorizationEndpoint($domain);

    if (!empty($authorization_endpoint)) {

      $_SESSION['indieauth_domain'] = $domain;
      $_SESSION['indieauth_authorization_endpoint'] = $authorization_endpoint;

      $url = $authorization_endpoint;
      $url .= '?redirect_uri=' . Url::fromroute('indieweb.indieauth.login.redirect', [], ['absolute' => TRUE])->toString();
      $url .= '&client_id=' . \Drupal::request()->getSchemeAndHttpHost();
      $url .= '&me=' . $domain;
      $url .= '&state=' . session_id();

      // Redirect to auth provider.
      $response = new TrustedRedirectResponse($url);
      $form_state->setResponse($response);
    }
    else {
      $this->messenger()->addMessage($this->t('No authorization endpoint found.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\indieweb_indieauth\Form\IndieAuthLoginForm $form */
    $form = parent::create($container);

    $authMapId = 'externalauth.authmap';
    if ($container->has($authMapId)) {
      $externalAuthMap = $container->get($authMapId);
      $form->setExternalAuthMap($externalAuthMap);
    }

    $form->setConfigFactory($container->get('config.factory'));

    return $form;
  }

}

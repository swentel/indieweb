<?php

namespace Drupal\indieweb\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

class IndieAuthLoginForm extends FormBase {

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

    if (\Drupal::config('indieweb.indieauth')->get('login_enable')) {
      $form['domain'] = [
        '#title' => $this->t('Web Address'),
        '#type' => 'textfield',
        '#placeholder' => $this->t('yourdomain.com'),
        '#required' => TRUE,
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Sign in'),
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

    // Start a session.
    $_SESSION['started'] = TRUE;
    $_SESSION['domain'] = $domain;

    $url = \Drupal::config('indieweb.indieauth')->get('login_endpoint');
    $url .= '?redirect_uri=' . Url::fromroute('indieweb.indieauth.login.redirect', [], ['absolute' => TRUE])->toString();
    $url .= '&client_id=' . \Drupal::request()->getSchemeAndHttpHost();
    $url .= '&me=' . $domain;
    $url .= '&state=' . session_id();

    // Redirect to login provider.
    $response = new TrustedRedirectResponse($url);
    $form_state->setResponse($response);
  }

}

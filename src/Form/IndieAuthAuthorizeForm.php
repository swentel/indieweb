<?php

namespace Drupal\indieweb\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\indieweb\Controller\IndieAuthController;
use Jose\Component\Core\JWT;

class IndieAuthAuthorizeForm extends FormBase {

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

    $params = [];
    $reason = '';
    $valid_request = TRUE;
    IndieAuthController::checkRequiredAuthorizeParameters(\Drupal::request(),  $reason, $valid_request, TRUE, $params );

    if (!$valid_request) {
      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authorize on form: @reason', ['@reason' => $reason]);
      return ['#markup' => 'Invalid request, missing parameters.', '#cache' => ['max-age' => 0]];
    }

    $form['intro'] = [
      '#markup' => '<p>' . $this->t('The app <strong>@app</strong> would like to access your site, using the credentials of <strong>@user</strong>.', [
          '@app' => $params['client_id'],
          '@user' => $this->currentUser()->getAccountName(),
        ]
      ) . '</p>',
    ];

    $scopes = [];
    foreach (explode(' ', $params['scope']) as $s) {
      $scopes[$s] = $s;
    }
    $form['scope'] = [
      '#title' => $this->t('The app is requesting the following <a href="https://indieweb.org/scope" target="_blank">scopes</a>'),
      '#access' => !empty($scopes),
      '#type' => 'checkboxes',
      '#options' => $scopes,
      '#default_value' => $scopes,
    ];

    $form['redirect'] = [
      '#markup' => '<p>' . $this->t('You will be redirected to <code>@redirect</code> after authorizing this application.', ['@redirect' => $params['redirect_uri']]) . '</p>'
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];

    $form['actions']['authorize'] = [
      '#type' => 'submit',
      '#value' => $this->t('Authorize'),
      '#submit' => ['::authorize']
    ];

    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancel']
    ];

    $form['params'] = [
      '#type' => 'value',
      '#value' => $params,
    ];

    return $form;
  }

  /**
   * Authorize submit callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function authorize(array &$form, FormStateInterface $form_state) {
    unset($_SESSION['indieauth']);
    $params = $form_state->getValue('params');

    $scopes = [];
    $scope = $form_state->getValue('scope');
    foreach ($scope as $key => $value) {
      if ($key === $value) {
        $scopes[] = $key;
      }
    }

    // Generate code.
    $random = new Random();
    // TODO encrypt the code ?
    $code = $random->name(120);
    $values = [
      'code' => $code,
      // TODO does it expire anyway ?
      'expire' => \Drupal::time()->getRequestTime() + 3600,
      'uid' => $this->currentUser()->id(),
      'status' => 1,
      'client' => $params['client_id'],
      'scope' => implode(' ', $scopes),
    ];
    $authorization_code = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_code')->create($values);
    $authorization_code->save();

    $query = '?state=' . $params['state'] . '&me=' . $params['me'] . '&code=' . $code;
    $response = new TrustedRedirectResponse($params['redirect_uri'] . $query);
    $form_state->setResponse($response);
  }

  /**
   * Cancel submit callback.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    unset($_SESSION['indieauth']);
    $params = $form_state->getValue('params');
    $response = new TrustedRedirectResponse($params['redirect_uri']);
    $form_state->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Intentionally empty
  }

}
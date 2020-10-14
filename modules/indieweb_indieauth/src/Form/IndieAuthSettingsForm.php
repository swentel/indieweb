<?php

namespace Drupal\indieweb_indieauth\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class IndieAuthSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_indieauth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_indieauth_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb_indieauth.settings');

    $externalauth_module_enabled = \Drupal::moduleHandler()->moduleExists('externalauth');

    $form['login'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Login'),
      '#description' => $this->t('Allow users to login into this site by using their domain. A "Web Sign-In" block is available where users can enter their domain to login.<br />After authentication a new user account will be created if this domain does not exist yet. The account will automatically be verified.<br />Authenticated users can use the same block to map an existing domain with their account.<br />Users who login with their domain will not be able to change the username or password.')
    ];

    $form['login']['login_enable'] = [
      '#title' => $this->t('Enable login'),
      '#type' => 'checkbox',
      '#disabled' => !$externalauth_module_enabled,
      '#default_value' => $config->get('login_enable'),
    ];

    if (!$externalauth_module_enabled) {
      $form['login']['login_enable']['#description'] = $this->t('You need to install the <a href="https://www.drupal.org/project/externalauth" target="_blank">External Authentication</a> module for this feature to work.');
    }

    $form['auth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication API'),
      '#description' => $this->t('If you use apps like Quill (https://quill.p3k.io - web) or Indigenous (iOS, Android) or other clients which can post via micropub or read via microsub, the easiest way to let those clients log you in with your domain is by using indieauth.com and exchange access tokens for further requests. Only expose those links if you want to use micropub or microsub. <br /><strong>Important: </strong> if you add the token endpoint manually, and the endpoint is an external service, you still need to enter the URL here because it is used by the micropub and/or microsub endpoint.')
    ];

    $form['auth']['auth_internal'] = [
      '#title' => $this->t('Use built-in authentication endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('auth_internal'),
      '#description' => $this->t("Use the internal authorize and token endpoints to authenticate with a Drupal user. The user needs the 'Authorize with IndieAuth' permission.<br />The endpoints are available at <strong>https://@domain/indieauth/auth</strong> and <strong>https://@domain/indieauth/token</strong>", ['@domain' => \Drupal::request()->getHttpHost()])
    ];

    $form['auth']['expose_link_tag'] = [
      '#title' => $this->t('Expose authentication API link tag'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('expose_link_tag'),
      '#description' => $this->t('The link tag will be added on the front page. You can also add them yourself to html.html.twig e.g.:<br /><div class="indieweb-highlight-code">&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth" /&gt;</div><br /><div class="indieweb-highlight-code">&lt;link rel="token_endpoint" href="https://tokens.indieauth.com/token" /&gt;</div>'),
    ];

    $form['auth']['expose_link_header'] = [
      '#title' => $this->t('Expose authentication API header link'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('expose_link_header'),
      '#description' => $this->t('The link tag will be added on in response headers of the front page.'),
    ];

    $form['auth']['authorization_endpoint'] = [
      '#title' => $this->t('External authorization endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('authorization_endpoint'),
      '#states' => array(
        'visible' => array(
          ':input[name="auth_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['auth']['token_endpoint'] = [
      '#title' => $this->t('External token endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('token_endpoint'),
      '#states' => array(
        'visible' => array(
          ':input[name="auth_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['keys'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Keys'),
      '#description' => $this->t('Configure the paths to the public and private keys which are used for encrypting the access tokens.<br />If you choose to generate keys, the default path where these keys are stored is set to public://indieauth. You can override this via settings.php. Check the README for more information.'),
      '#states' => array(
        'visible' => array(
          ':input[name="auth_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['keys']['public_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public key'),
      '#default_value' => $config->get('public_key'),
      '#description' => $this->t('The path to the public key file.'),
    ];

    if ($this->config('auth_internal') && \Drupal::request()->getMethod() == 'GET' && ($file = $config->get('public_key')) && !file_exists($file)) {
      $this->messenger()->addError($this->t('The public key file does not exist.'));
    }

    $form['keys']['private_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Private key'),
      '#default_value' => $config->get('private_key'),
      '#description' => $this->t('The path to the private key file.'),
    ];

    if ($this->config('auth_internal') && \Drupal::request()->getMethod() == 'GET' && ($file = $config->get('private_key')) && !file_exists($file)) {
      $this->messenger()->addError($this->t('The private key file does not exist.'));
    }

    $form['keys']['generate_keys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate keys on save'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $public_key = $form_state->getValue('public_key');
    $private_key = $form_state->getValue('private_key');

    // Generate keys if the checkbox is toggled.
    if ($form_state->getValue('generate_keys')) {
      $paths = \Drupal::service('indieweb.indieauth.client')->generateKeys();
      if (!$paths) {
        $this->messenger()->addMessage($this->t('Something went wrong generating the keys, please check your logs.'));
      }
      else {
        $public_key = $paths['public_key'];
        $private_key = $paths['private_key'];
      }
    }

    $this->config('indieweb_indieauth.settings')
      ->set('auth_internal', $form_state->getValue('auth_internal'))
      ->set('expose_link_tag', $form_state->getValue('expose_link_tag'))
      ->set('expose_link_header', $form_state->getValue('expose_link_header'))
      ->set('authorization_endpoint', $form_state->getValue('authorization_endpoint'))
      ->set('token_endpoint', $form_state->getValue('token_endpoint'))
      ->set('login_enable', $form_state->getValue('login_enable'))
      ->set('public_key', $public_key)
      ->set('private_key', $private_key)
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

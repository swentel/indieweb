<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class IndieAuthSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.indieauth'];
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

    $config = $this->config('indieweb.indieauth');

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
      '#description' => $this->t('If you use apps like Quill (https://quill.p3k.io - web) or Indigenous (iOS, Android) or other clients which can post via micropub or read via microsub, the easiest way to let those clients log you in with your domain is by using indieauth.com and exchange access tokens for further requests. Only expose those links if you want to use micropub or microsub. <br /><strong>Important: </strong> if you add the token endpoint manually, and the endpoint is an external service, you still need to enter the URL here because it is used by the micropub and/or microsub endpoint.')];

    $form['auth']['auth_internal'] = [
      '#title' => $this->t('Use built-in authentication endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('auth_internal'),
      '#description' => $this->t("Use the internal authorize and token endpoints to authenticate with a Drupal user. The user needs the 'Authorize with IndieAuth' permission.<br />The endpoints are available at <strong>https://@domain/indieauth/auth</strong> and <strong>https://@domain/indieauth/token</strong>", ['@domain' => \Drupal::request()->getHttpHost()])
    ];

    $form['auth']['expose_endpoint_link'] = [
      '#title' => $this->t('Expose authentication API header links'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('expose_endpoint_link'),
      '#description' => $this->t('The links will be added on the front page. You can also add them yourself to html.html.twig e.g.:<br /><div class="indieweb-highlight-code">&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth" /&gt;</div><br /><div class="indieweb-highlight-code">&lt;link rel="token_endpoint" href="https://tokens.indieauth.com/token" /&gt;</div>'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable"]' => array('checked' => TRUE),
        ),
      ),
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.indieauth')
      ->set('auth_internal', $form_state->getValue('auth_internal'))
      ->set('expose_endpoint_link', $form_state->getValue('expose_endpoint_link'))
      ->set('authorization_endpoint', $form_state->getValue('authorization_endpoint'))
      ->set('token_endpoint', $form_state->getValue('token_endpoint'))
      ->set('login_enable', $form_state->getValue('login_enable'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\indieweb\Form;

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
    return 'indieauth_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb.indieauth');

    $form['info'] = [
      '#markup' => '<p>' . $this->t('IndieAuth is a way to use your own domain name to sign in to websites. It works by linking your website to one or more authentication providers such as Twitter or Google, then entering your domain name in the login form on websites that support IndieAuth. Indieauth.com is a hosted service that does this for you and also adds Authentication API. Indieauth.com is open source so you can also host the service yourself.<br /><br />The easy way is to add rel="me" links on your homepage which point to your social media accounts and on each of those services adding a link back to your home page. They can even be hidden. e.g.<div class="indieweb-highlight-code">&lt;a href="https://twitter.com/swentel" target="_blank" title="Twitter" rel="me"&gt;&lt;/a&gt;</div><br /><br /> You can also use a PGP key if you don\'t want to use a third party service. See <a href="https://indieauth.com/setup" target="_blank">https://indieauth.com/setup</a> for full details. This module does not expose any of these links or help you with the PGP setup, you will have to manage this yourself.') . '</p>'];

    $form['indieauth'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('IndieAuth authentication API'),
      '#description' => $this->t('If you use apps like Quill (https://quill.p3k.io - web) or Indigenous (Beta iOS, Alpha Android) or other clients which can post via micropub or read via microsub, the easiest way to let those clients log you in with your domain is by using indieauth.com too and exchange access tokens for further requests. Only expose those links if you want to use micropub or microsub.')];

    $form['indieauth']['enable'] = [
      '#title' => $this->t('Expose authentication API head links'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('enable'),
    ];

    $form['indieauth']['authorization_endpoint'] = [
      '#title' => $this->t('Authorization endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('authorization_endpoint'),
      '#description' => $this->t('This link will be added on the front page. You can also add it yourself to html.html.twig:<br /><div class="indieweb-highlight-code">&lt;link rel="authorization_endpoint" href="https://indieauth.com/auth" /&gt;</div>'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['indieauth']['token_endpoint'] = [
      '#title' => $this->t('Token endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('token_endpoint'),
      '#description' => $this->t('This link will be added on the front page. You can also add it yourself to html.html.twig:<br /><div class="indieweb-highlight-code">&lt;link rel="token_endpoint" href="https://tokens.indieauth.com/token" /&gt;</div>'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable"]' => array('checked' => TRUE),
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
      ->set('enable', $form_state->getValue('enable'))
      ->set('authorization_endpoint', $form_state->getValue('authorization_endpoint'))
      ->set('token_endpoint', $form_state->getValue('token_endpoint'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

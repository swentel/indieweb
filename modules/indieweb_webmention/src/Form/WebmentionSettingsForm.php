<?php

namespace Drupal\indieweb_webmention\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class WebmentionSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_webmention.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_webmention_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('indieweb_webmention.settings');

    $form['#attached']['library'][] = 'indieweb/admin';

    $form['webmention'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webmention'),
    ];

    $form['webmention']['webmention_internal'] = [
      '#title' => $this->t('Use built-in webmention endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('webmention_internal'),
      '#description' => $this->t("Use the internal webmention endpoint to receive webmentions. The endpoint is available at <strong>https://@domain/webmention/receive</strong>", ['@domain' => \Drupal::request()->getHttpHost()])
    ];

    $form['webmention']['webmention_internal_handler'] = [
      '#title' => $this->t('Process webmentions'),
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('webmention_internal_handler'),
      '#description' => $this->t('Received webmentions on built-in endpoint are not processed immediately, but stored with property received and status 0.<br />The drush command is <strong>indieweb-process-webmentions</strong>'),
      '#states' => array(
        'visible' => array(
          ':input[name="webmention_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['webmention']['webmention_log_processing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log messages in watchdog when the webmention is processed.'),
      '#default_value' => $config->get('webmention_log_processing'),
      '#states' => array(
        'visible' => array(
          ':input[name="webmention_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['webmention']['webmention_detect_identical'] = [
      '#title' => $this->t('Detect identical webmentions'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('webmention_detect_identical'),
      '#description' => $this->t('On some occasions it might be possible multiple webmentions are send with the same source, target and property. Enable to detect duplicates and not store those.'),
    ];

    $form['webmention']['webmention_expose_link_tag'] = [
      '#title' => $this->t('Expose webmention link tag'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('webmention_expose_link_tag'),
      '#description' => $this->t('The link tag will be added on all non admin pages. Exposing a link tag is necessary so you can start receiving webmentions. You can also manually add to html.html.twig. e.g.<br /><div class="indieweb-highlight-code">&lt;link rel="webmention" href="https://webmention.io/@domain/webmention" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
    ];

    $form['webmention']['webmention_endpoint'] = [
      '#title' => $this->t('External webmention endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('webmention_endpoint'),
      '#description' => $this->t('If you use webmention.io, the endpoint will look like <strong>https://webmention.io/@domain/webmention</strong><br />If you already have an account on webmention.io, you can use that URL (the domain is your username and does not matter).'),
      '#states' => array(
        'visible' => array(
          ':input[name="webmention_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['webmention']['webmention_notify'] = [
      '#title' => $this->t('Enable notification endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('webmention_notify'),
      '#description' => $this->t('When webmention.io receives a webmention, it can notify your site to send a post request to <strong>@domain/webmention/notify</strong></br >You can configure the notification endpoint and secret on webmention.io once you have received the first webmention there.', ['@domain' => \Drupal::request()->getSchemeAndHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="webmention_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['webmention']['webmention_secret'] = [
      '#title' => $this->t('Webmention.io secret'),
      '#type' => 'textfield',
      '#default_value' => $config->get('webmention_secret'),
      '#states' => array(
        'visible' => array(
          ':input[name="webmention_internal"]' => array('checked' => FALSE),
          ':input[name="webmention_notify"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['pingback'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Pingback'),
    ];

    $form['pingback']['pingback_internal'] = [
      '#title' => $this->t('Use built-in pingback endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('pingback_internal'),
      '#description' => $this->t("Use the internal pingback endpoint to receive pingbacks. The endpoint is available at <strong>https://@domain/pingback/receive</strong>", ['@domain' => \Drupal::request()->getHttpHost()])
    ];

    $form['pingback']['pingback_expose_link_tag'] = [
      '#title' => $this->t('Expose webmention link tag'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('pingback_expose_link_tag'),
      '#description' => $this->t('The link tag will be added on all non admin pages. Exposing a link tag is necessary so you can start receiving webmentions. You can also manually add to html.html.twig. e.g.<br /><div class="indieweb-highlight-code">&lt;link rel="pingback" href="https://@domain/pingback/receive" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
    ];

    $form['pingback']['pingback_notify'] = [
      '#title' => $this->t('Enable notification endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('pingback_notify'),
      '#description' => $this->t('When webmention.io receives a pingback, it can notify your site to send a post request to <strong>https://webmention.io/webmention?forward=@domain/pingback/notify</strong>', ['@domain' => \Drupal::request()->getSchemeAndHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="pingback_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['pingback']['pingback_endpoint'] = [
      '#title' => $this->t('External pingback endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('pingback_endpoint'),
      '#description' => $this->t('If you use webmention.io, the endpoint will look like <strong>https://webmention.io/webmention?forward=@domain/pingback/notify</strong>', ['@domain' => \Drupal::request()->getSchemeAndHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="pingback_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['other'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other'),
    ];

    $form['other']['blocked_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Block domains'),
      '#description' => $this->t('Block domains from sending webmentions or pingbacks. Enter domains line per line.'),
      '#default_value' => $config->get('blocked_domains'),
    ];

    $form['other']['webmention_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the collected webmentions'),
      '#default_value' => $config->get('webmention_uid'),
    ];

    $form['other']['webmention_log_payload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the payload in watchdog on the webmention notification endpoint.'),
      '#default_value' => $config->get('webmention_log_payload'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $webmention_notify = $form_state->getValue('webmention_notify');
    $webmention_internal = $form_state->getValue('webmention_internal');
    if ($webmention_internal && $webmention_notify) {
      $form_state->setErrorByName('webmention_notify', $this->t('You can not enable the webmention notification and internal endpoint together'));
    }

    $pingback_notify = $form_state->getValue('pingback_notify');
    $pingback_internal = $form_state->getValue('pingback_internal');
    if ($pingback_internal && $pingback_notify) {
      $form_state->setErrorByName('webmention_notify', $this->t('You can not enable the pingback notification and internal endpoint together'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_webmention.settings')
      ->set('webmention_uid', $form_state->getValue('webmention_uid'))
      ->set('webmention_internal', $form_state->getValue('webmention_internal'))
      ->set('webmention_internal_handler', $form_state->getValue('webmention_internal_handler'))
      ->set('webmention_log_processing', $form_state->getValue('webmention_log_processing'))
      ->set('webmention_notify', $form_state->getValue('webmention_notify'))
      ->set('webmention_log_payload', $form_state->getValue('webmention_log_payload'))
      ->set('webmention_detect_identical', $form_state->getValue('webmention_detect_identical'))
      ->set('webmention_expose_link_tag', $form_state->getValue('webmention_expose_link_tag'))
      ->set('webmention_endpoint', $form_state->getValue('webmention_endpoint'))
      ->set('webmention_secret', $form_state->getValue('webmention_secret'))
      ->set('pingback_internal', $form_state->getValue('pingback_internal'))
      ->set('pingback_expose_link_tag', $form_state->getValue('pingback_expose_link_tag'))
      ->set('pingback_notify', $form_state->getValue('pingback_notify'))
      ->set('pingback_endpoint', $form_state->getValue('pingback_endpoint'))
      ->set('blocked_domains', $form_state->getValue('blocked_domains'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

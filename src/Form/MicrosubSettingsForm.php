<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.microsub'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_microsub_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb.microsub');

    $form['microsub'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Microsub'),
      '#title_display' => 'hidden'
    ];

    $form['microsub']['microsub_internal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use built-in microsub endpoint'),
      '#default_value' => $config->get('microsub_internal'),
      '#description' => $this->t('The endpoint is available at <strong>https://@domain/indieweb/microsub</strong>', ['@domain' => \Drupal::request()->getHttpHost()]) . ' - not fully ready yet!',
      //'#disabled' => TRUE,
    ];

    $form['microsub']['microsub_internal_handler'] = [
      '#title' => $this->t('Fetch items'),
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('microsub_internal_handler'),
      '#description' => $this->t('Fetch items either by cron or drush.<br />The drush command is <strong>indieweb-microsub-fetch-items</strong>'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['microsub']['microsub_add_header_link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose microsub endpoint header link'),
      '#default_value' => $config->get('microsub_add_header_link'),
      '#description' => $this->t('This link will be added on the front page. You can also add this manually to html.html.twig.<br /><div class="indieweb-highlight-code">&lt;link rel="micropub" href="https://@domain/indieweb/microsub" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
    ];

    $form['microsub']['microsub_endpoint'] = [
      '#title' => $this->t('External microsub endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('microsub_endpoint'),
      '#description' => $this->t('Enter a custom microsub endpoint URL in case you do not use the built-in endpoint.'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['aperture'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Aperture'),
      '#description' => $this->t('If you use <a href="https://aperture.p3k.io" target="_blank">Aperture</a> as your Microsub server, you can send a micropub post to one channel when a webmention is received by this site.<br />The canonical example is to label that channel name as "Notifications" so you can view incoming webmentions on readers like Monocle or Indigenous.<br />Following webmentions are send: likes, reposts, bookmarks, mentions and replies.</a>'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => FALSE),
        ),
      ),
    ];

    $form['aperture']['aperture_enable_micropub'] = [
      '#title' => $this->t('Send micropub request to Aperture'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('aperture_enable_micropub'),
    ];

    $form['aperture']['aperture_api_key'] = [
      '#title' => $this->t('Channel API key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('aperture_api_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.microsub')
      ->set('microsub_internal', $form_state->getValue('microsub_internal'))
      ->set('microsub_internal_handler', $form_state->getValue('microsub_internal_handler'))
      ->set('microsub_endpoint', $form_state->getValue('microsub_endpoint'))
      ->set('microsub_add_header_link', $form_state->getValue('microsub_add_header_link'))
      ->set('aperture_enable_micropub', $form_state->getValue('aperture_enable_micropub'))
      ->set('aperture_api_key', $form_state->getValue('aperture_api_key'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

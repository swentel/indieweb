<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_microsub.settings'];
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

    $config = $this->config('indieweb_microsub.settings');

    $form['microsub'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Microsub'),
      '#title_display' => 'hidden'
    ];

    $form['microsub']['microsub_internal'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use built-in microsub endpoint'),
      '#default_value' => $config->get('microsub_internal'),
      '#description' => $this->t('The endpoint is available at <strong>https://@domain/indieweb/microsub</strong>', ['@domain' => \Drupal::request()->getHttpHost()]),
    ];

    $form['microsub']['microsub_internal_cleanup_items'] = [
      '#title' => $this->t('Cleanup feed items'),
      '#type' => 'checkbox',
      '#description' => $this->t('You can configure the number of items to keep per feed.'),
      '#default_value' => $config->get('microsub_internal_cleanup_items'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['microsub']['microsub_internal_mark_unread_on_first_import'] = [
      '#title' => $this->t('Mark items unread on first import'),
      '#type' => 'checkbox',
      '#description' => $this->t('On a first import of a feed, items are marked as read. Toggle this setting to still mark them as unread.'),
      '#default_value' => $config->get('microsub_internal_mark_unread_on_first_import'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['microsub']['microsub_allow_vimeo_youtube'] = [
      '#title' => $this->t('Allow Vimeo and Video in feeds'),
      '#type' => 'checkbox',
      '#description' => $this->t('By default videos embedded with an iframe in content are stripped. Toggle this setting to allow YouTube and Vimeo in content.'),
      '#default_value' => $config->get('microsub_allow_vimeo_youtube'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
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
      '#description' => $this->t('Fetch and cleanup items either by cron or drush.<br />The drush command is <strong>indieweb-microsub-fetch-items</strong>'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['microsub']['microsub_expose_link_tag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose microsub endpoint link tag'),
      '#default_value' => $config->get('microsub_expose_link_tag'),
      '#description' => $this->t('This link will be added on the front page. You can also add this manually to html.html.twig.<br /><div class="indieweb-highlight-code">&lt;link rel="microsub" href="https://@domain/indieweb/microsub" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
    ];

    $form['microsub']['microsub_expose_link_header'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose microsub endpoint header link'),
      '#default_value' => $config->get('microsub_expose_link_header'),
      '#description' => $this->t('The link tag will be added on in response headers of the front page.'),
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

    $form['microsub']['microsub_aggregated_feeds'] = [
      '#title' => $this->t('Aggregated feeds'),
      '#type' => 'textarea',
      '#default_value' => $config->get('microsub_aggregated_feeds'),
      '#description' => $this->t('Some readers support viewing feeds per author (source), but this will not work in case of aggregated feeds.<br />Enter the base url\'s line by line which, in case they match will trigger a search instead internally on the author name so the response will work.'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
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

    $form['indigenous'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Indigenous'),
      '#description' => $this->t('If you use <a href="https://indigenous.realize.be" target="_blank">Indigenous for Android</a>, you can send a push notification when a webmention is added to the notifications channel.<br />You need an account and a registered device, see <a href="https://indigenous.realize.be/push-notifications" target="_blank">https://indigenous.realize.be/push-notifications</a>.<br />This feature only works if you use the built-in webmention and microsub endpoint.'),
      '#states' => array(
        'visible' => array(
          ':input[name="microsub_internal"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['indigenous']['push_notification_indigenous'] = [
      '#title' => $this->t('Send push notification to Indigenous'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('push_notification_indigenous'),
    ];

    $form['indigenous']['push_notification_indigenous_key'] = [
      '#title' => $this->t('Push notification API key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('push_notification_indigenous_key'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_microsub.settings')
      ->set('microsub_internal', $form_state->getValue('microsub_internal'))
      ->set('microsub_internal_handler', $form_state->getValue('microsub_internal_handler'))
      ->set('microsub_internal_cleanup_items', $form_state->getValue('microsub_internal_cleanup_items'))
      ->set('microsub_internal_mark_unread_on_first_import', $form_state->getValue('microsub_internal_mark_unread_on_first_import'))
      ->set('microsub_allow_vimeo_youtube', $form_state->getValue('microsub_allow_vimeo_youtube'))
      ->set('microsub_endpoint', $form_state->getValue('microsub_endpoint'))
      ->set('microsub_aggregated_feeds', $form_state->getValue('microsub_aggregated_feeds'))
      ->set('microsub_expose_link_tag', $form_state->getValue('microsub_expose_link_tag'))
      ->set('microsub_expose_link_header', $form_state->getValue('microsub_expose_link_header'))
      ->set('aperture_enable_micropub', $form_state->getValue('aperture_enable_micropub'))
      ->set('aperture_api_key', $form_state->getValue('aperture_api_key'))
      ->set('push_notification_indigenous', $form_state->getValue('push_notification_indigenous'))
      ->set('push_notification_indigenous_key', $form_state->getValue('push_notification_indigenous_key'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

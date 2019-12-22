<?php

namespace Drupal\indieweb_websub\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class WebSubSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_websub.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_websub_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb_websub.settings');

    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General'),
    ];

    $form['general']['hub_endpoint'] = [
      '#title' => $this->t('Hub endpoint'),
      '#type' => 'textfield',
      '#default_value' => $config->get('hub_endpoint'),
      '#description' => $this->t('Configure the hub used to publish and where people can subscribe.'),
      '#required' => TRUE,
    ];

    $form['general']['pages'] = [
      '#title' => $this->t('Discovery'),
      '#type' => 'textarea',
      '#default_value' => $config->get('pages'),
      '#description' => $this->t('Specify pages by using their paths to which people can subscribe to. Enter one path per line and do not use wildcards. / is the frontpage.<br />You can also include RSS pages managed by Views using the "RSS Feed with WebSub discovery" format and disabling the views cache.'),
      '#required' => TRUE,
    ];

    if (indieweb_is_multi_user()) {
      $form['general']['pages']['#description'] .= '<br />' . $this->t('The site is set to multiple users, so if you have a feed that is configured for multiple users, the path will be appended to the user page. e.g "/timeline" automatically becomes "/user/x/timeline" and will be exposed on the user page.');
    }

    $form['general']['expose_link_tag'] = [
      '#title' => $this->t('Expose WebSub link tags'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('expose_link_tag'),
      '#description' => indieweb_is_multi_user() ? $this->t('Depending on the feed setting, the link tag will either be added on the exact path or the user page.') : $this->t('The link tags will be added on the front page. You can also add them yourself to html.html.twig e.g.:<br /><div class="indieweb-highlight-code">&lt;link rel="hub" href="@hub" /&gt;</div><br /><div class="indieweb-highlight-code">&lt;link rel="self" href="@self" /&gt;</div>', ['@hub' => $config->get('hub_endpoint'), '@self' => \Drupal::request()->getSchemeAndHttpHost()]),
    ];

    $form['general']['expose_link_header'] = [
      '#title' => $this->t('Expose WebSub header links'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('expose_link_header'),
      '#description' => indieweb_is_multi_user() ? $this->t('Depending on the feed setting, the link tag will be added to the response of the exact tag or on the user page.') : $this->t('The link tag will be added on in response headers of the page that can be discovered.'),
    ];

    $form['general']['node_element'] = [
      '#title' => $this->t('WebSub element on node edit forms'),
      '#type' => 'select',
      '#options' => [
        'none' => $this->t('No element'),
        'expose' => $this->t('Expose'),
        'expose_default' => $this->t('Expose and set checked')
      ],
      '#default_value' => $config->get('node_element'),
      '#description' => $this->t('This allows you tell to toggle a checkbox to send a publication to the hub.<br />This is also controlled by the "Publish to hub" permission.'),
    ];

    $form['general']['send_pub_handler'] = [
      '#title' => $this->t('How to send the publication to the hub'),
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('send_pub_handler'),
      '#description' => $this->t('Publications are not send immediately, but are stored in a queue when the content is published.<br />The drush command is <strong>indieweb-websub-publish</strong>')
    ];

    $form['general']['resubscribe_handler'] = [
      '#title' => $this->t('How to resubscribe to subscriptions'),
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('resubscribe_handler'),
      '#description' => $this->t('Subscriptions are active for a limited time, usually not more than two weeks.<br />This allows you to automatically resubscribe, leave disabled if you do not have any WebSub subscriptions.<br />The drush command is <strong>indieweb-websub-resubscribe</strong>')
    ];

    $form['general']['micropub_publish_to_hub'] = [
      '#title' => $this->t('Publish to the hub when you create a post with Micropub.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_publish_to_hub'),
    ];

    $form['general']['microsub_api_subscribe'] = [
      '#title' => $this->t('Send a subscribe or unsubscribe request when managing feeds through the Microsub API.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('microsub_api_subscribe'),
    ];

    $form['general']['log_payload'] = [
      '#title' => $this->t('Log the payload and responses in watchdog on the subscribe and notification endpoints and publish requests.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('log_payload'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_websub.settings')
      ->set('log_payload', $form_state->getValue('log_payload'))
      ->set('expose_link_tag', $form_state->getValue('expose_link_tag'))
      ->set('expose_link_header', $form_state->getValue('expose_link_header'))
      ->set('node_element', $form_state->getValue('node_element'))
      ->set('hub_endpoint', $form_state->getValue('hub_endpoint'))
      ->set('pages', $form_state->getValue('pages'))
      ->set('send_pub_handler', $form_state->getValue('send_pub_handler'))
      ->set('resubscribe_handler', $form_state->getValue('resubscribe_handler'))
      ->set('micropub_publish_to_hub', $form_state->getValue('micropub_publish_to_hub'))
      ->set('microsub_api_subscribe', $form_state->getValue('microsub_api_subscribe'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

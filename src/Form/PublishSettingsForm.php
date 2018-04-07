<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class PublishSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.publish'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_publish_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb.publish');

    $form['info'] = [
      '#markup' => '<p>' . $this->t('The easiest way to start pulling back content or publish content on social networks is by using <a href="https://brid.gy/" target="_blank">https://brid.gy</a>. <br />You have to create an account by signing in with your preferred social network. Bridgy is open source so you can also host the service yourself.<br /><br />Publishing, which is nothing more than sending a webmention, can be done per node in the "Publish to" fieldset, which is protected with the "send webmentions" permission.<br />If no channels are configured, there is nothing to do. There is a syndication field on every node type available to render your <a href=":syndication_link">syndications</a> for <a href="https://indieweb.org/posse-post-discovery" target="_blank">POSSE-Post-Discovery</a>.', [':syndication_link' => Url::fromRoute('indieweb.syndications_list')->toString()]) . '</p>',
    ];

    $form['channels_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Publishing channels')
    ];

    $form['channels_wrapper']['channels'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Publishing channels'),
      '#title_display' => 'invisible',
      '#default_value' => $config->get('channels'),
      '#description' => $this->t('Enter every channel line by line if you want to publish content, in following format:<br /><br />Name|webmention_url<br />Twitter (bridgy)|https://brid.gy/publish/twitter<br /><br />When you add or remove channels, extra fields will be enabled on the manage display screens of every node type (you will have to clear cache to see them showing up).<br />These need to be added on the page (usually on the "full" view mode) because bridgy will check for the url in the markup, along with the proper microformat classes.<br />The field will print them hidden in your markup, even if you do not publish to that channel, that will be altered later.<br />You can also add them yourself:<br /><div class="indieweb-highlight-code">&lt;a href="https://brid.gy/publish/twitter"&gt;&lt;/a&gt;</div><br />These channels are also used for the syndicate-to request if you are using micropub.')
    ];

    $form['channels_wrapper']['back_link'] = [
      '#title' => $this->t('Bridgy back link'),
      '#type' => 'radios',
      '#default_value' => $config->get('back_link'),
      '#options' => [
        'always' => $this->t('Always'),
        'never' => $this->t('Never'),
        'maybe' => $this->t('Add when the content is ellipsized (truncated) to fit the post'),
      ],
      '#description' => $this->t('By default, Bridgy includes a link back to your post, configure the behavior here.<br /><strong>Important</strong>: make sure that the syndications are printed in your posts if you select "never" or "maybe".'),
    ];

    $form['custom_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom publishing'),
      '#description' => $this->t('The "Publish to" fieldset can contain two additional ways to publish content: a textfield to enter a custom URL or a select which listens to a "link" field on your content.'),
    ];

    // Collect fields.
    $link_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['link'])) {
        $link_fields[$key] = $field->getName();
      }
    }

    $form['custom_wrapper']['publish_custom_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose textfield'),
      '#description' => $this->t('Add a textfield to enter a custom URL to send a webmention.'),
      '#default_value' => $config->get('publish_custom_url'),
    ];

    $form['custom_wrapper']['publish_link_fields'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Link fields'),
      '#options' => $link_fields,
      '#default_value' => explode('|', $config->get('publish_link_fields')),
      '#description' => $this->t('Do not select a field if you do not want to use this feature.'),
    ];

    $form['send_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sending publications')
    ];

    $form['send_wrapper']['publish_send_webmention_by'] = [
      '#title' => $this->t('Send publication'),
      '#title_display' => 'invisible',
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('publish_send_webmention_by'),
      '#description' => $this->t('Publications are not send immediately, but are stored in a queue when the content is published and when you toggled one or more channels to publish to.<br />The drush command is <strong>indieweb-send-webmentions</strong>')
    ];

    $form['send_wrapper']['publish_log_response'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the response in watchdog when the publication is send.'),
      '#default_value' => $config->get('publish_log_response'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Store publish_dynamic_fields as a simple string.
    $link_fields_string = '';
    $link_fields_to_implode = [];
    $link_fields = $form_state->getValue('publish_link_fields');
    foreach ($link_fields as $key => $value) {
      if ($key === $value) {
        $link_fields_to_implode[] = $key;
      }
    }
    if (!empty($link_fields_to_implode)) {
      $link_fields_string = implode("|", $link_fields_to_implode);
    }

    $this->config('indieweb.publish')
      ->set('channels', $form_state->getValue('channels'))
      ->set('back_link', $form_state->getValue('back_link'))
      ->set('publish_send_webmention_by', $form_state->getValue('publish_send_webmention_by'))
      ->set('publish_log_response', $form_state->getValue('publish_log_response'))
      ->set('publish_custom_url', $form_state->getValue('publish_custom_url'))
      ->set('publish_link_fields', $link_fields_string)
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

<?php

namespace Drupal\indieweb_webmention\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class WebmentionSendSettingsForm extends ConfigFormBase {

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
    return 'indieweb_webmention_send_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb_webmention.settings');

    $form['syndication_targets_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Syndication targets')
    ];

    $form['syndication_targets_wrapper']['syndication_targets'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Syndication targets'),
      '#title_display' => 'invisible',
      '#default_value' => $config->get('syndication_targets'),
      '#description' => $this->t('Enter every target line by line if you want to publish content, in following format:<br /><br />Name|webmention_url|selected<br />selected is optional. Set to 1 to set as selected on the form.<br />Twitter (bridgy)|https://brid.gy/publish/twitter|1<br /><br />When you add or remove channels, extra fields will be enabled on the manage display screens of every node type (you will have to clear cache to see them showing up).<br />These need to be added on the page (usually on the "full" view mode) because bridgy will check for the url in the markup, along with the proper microformat classes.<br />The field will print them hidden in your markup, even if you do not publish to that channel, that will be altered later.<br />You can also add them yourself:<br /><div class="indieweb-highlight-code">&lt;a href="https://brid.gy/publish/twitter"&gt;&lt;/a&gt;</div><br />These channels are also used for the syndicate-to request if you are using micropub.<br />Consult the README file that comes with this module if you want to integrate with the Fediverse.')
    ];

    $form['syndication_targets_wrapper']['bridgy_back_link'] = [
      '#title' => $this->t('Bridgy back link'),
      '#type' => 'radios',
      '#default_value' => $config->get('bridgy_back_link'),
      '#options' => [
        'always' => $this->t('Always'),
        'never' => $this->t('Never'),
        'maybe' => $this->t('Add when the content is ellipsized (truncated) to fit the post'),
      ],
      '#description' => $this->t('By default, Bridgy includes a link back to your post, configure the behavior here.<br /><strong>Important</strong>: make sure that the syndications are printed in your posts if you select "never" or "maybe".'),
    ];

    $form['custom_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom URL\'s for content'),
      '#description' => $this->t('The "Publish to" fieldset on content can contain two additional ways to send webmentions to other sites: a textfield to enter a custom URL or a select which listens to a "link" field on node types.'),
    ];

    $comment_enabled = \Drupal::moduleHandler()->moduleExists('comment');

    // Collect fields.
    $link_fields = $reference_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    if ($comment_enabled) {
      $fields += \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('comment');
    }
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['link'])) {
        $link_fields[$key] = $field->getName() . ' (' . $field->getTargetEntityTypeId() . ')';
      }
    }

    if ($comment_enabled) {
      $comment_fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('comment');
      /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
      foreach ($comment_fields as $key => $field) {
        if (in_array($field->getType(), ['entity_reference'])) {
          $settings = $field->getSettings();
          if (isset($settings['target_type']) && $settings['target_type'] == 'indieweb_webmention') {
            $reference_fields[$key] = $field->getName();
          }
        }
      }
    }

    $form['custom_wrapper']['send_custom_url'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose textfield'),
      '#description' => $this->t('Add a textfield to enter a custom URL to send a webmention.'),
      '#default_value' => $config->get('send_custom_url'),
    ];

    $form['custom_wrapper']['send_link_fields'] = [
      '#type' => 'select',
      '#multiple' => TRUE,
      '#title' => $this->t('Link fields on content'),
      '#options' => $link_fields,
      '#default_value' => explode('|', $config->get('send_link_fields')),
      '#description' => $this->t('When you have a "Reply" post type, or reply on a comment, add a link field to which you are replying to. This URL will be used then to send the webmention to.<br />You can also just use the custom field above of course. Do not select a field if you do not want to use this feature.'),
    ];

    $form['custom_wrapper']['send_comment_webmention_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Webmention entity reference field'),
      '#options' => ['' => $this->t('Do not check')] + $reference_fields,
      '#description' => $this->t('Select the comment webmention reference field. When replying on a comment, the value of the webmention of the parent comment will be used to populate link fields on the comment.'),
      '#access' => $comment_enabled,
      '#default_value' => $config->get('send_comment_webmention_field'),
    ];

    $form['custom_wrapper']['send_comment_permission_fields'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Block access to link and webmention fields on comments'),
      '#description' => $this->t('Webmention reference field and link fields will be hidden for users who do not have the "Send webmention" permission.'),
      '#access' => $comment_enabled,
      '#default_value' => $config->get('send_comment_permission_fields'),
    ];

    $form['send_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Sending webmentions')
    ];

    $form['send_wrapper']['send_webmention_handler'] = [
      '#title' => $this->t('Send webmention'),
      '#title_display' => 'invisible',
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('send_webmention_handler'),
      '#description' => $this->t('Webmentions are not send immediately, but are stored in a queue when the content is published.<br />The drush command is <strong>indieweb-send-webmentions</strong>')
    ];

    $form['send_wrapper']['send_log_response'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the response in watchdog when the webmention is send.'),
      '#default_value' => $config->get('send_log_response'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Store link fields as a simple string.
    $link_fields_string = '';
    $link_fields_to_implode = [];
    $link_fields = $form_state->getValue('send_link_fields');
    foreach ($link_fields as $key => $value) {
      if ($key === $value) {
        $link_fields_to_implode[] = $key;
      }
    }
    if (!empty($link_fields_to_implode)) {
      $link_fields_string = implode("|", $link_fields_to_implode);
    }

    $this->config('indieweb_webmention.settings')
      ->set('syndication_targets', $form_state->getValue('syndication_targets'))
      ->set('bridgy_back_link', $form_state->getValue('bridgy_back_link'))
      ->set('send_webmention_handler', $form_state->getValue('send_webmention_handler'))
      ->set('send_log_response', $form_state->getValue('send_log_response'))
      ->set('send_custom_url', $form_state->getValue('send_custom_url'))
      ->set('send_link_fields', $link_fields_string)
      ->set('send_comment_webmention_field', $form_state->getValue('send_comment_webmention_field'))
      ->set('send_comment_permission_fields', $form_state->getValue('send_comment_permission_fields'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

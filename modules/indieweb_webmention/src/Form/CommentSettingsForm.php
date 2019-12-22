<?php

namespace Drupal\indieweb_webmention\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CommentSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_webmention.comment'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_comment_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Check if comment module is enabled.
    if (!\Drupal::moduleHandler()->moduleExists('comment')) {
      $form['disabled'] = ['#markup' => '<p>' . $this->t('The comment module is not enabled.') . '</p>'];
      return $form;
    }

    $config = $this->config('indieweb_webmention.comment');

    $form['comment_create'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a comment'),
    ];

    $form['comment_create']['comment_create_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('comment_create_enable'),
    ];

    $form['comment_create']['comment_create_default_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status of the new comment'),
      '#options' => [
        0 => $this->t('Moderated'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('comment_create_default_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="comment_create_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Collect fields.
    $reference_fields = $node_comment_fields = [];
    $node_fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($node_fields as $key => $field) {
      if (in_array($field->getType(), ['comment'])) {
        $node_comment_fields[$key] = $field->getName();
      }
    }
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

    // Comment type.
    $form['comment_create']['comment_create_comment_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Comment type'),
      '#description' => $this->t('Select the comment type to use for saving a new comment.'),
      '#options' => $this->getCommentTypes(),
      '#default_value' => $config->get('comment_create_comment_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="comment_create_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Comment webmention entity reference field.
    $form['comment_create']['comment_create_webmention_reference_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Webmention entity reference field'),
      '#description' => $this->t('Select the field which will store the reference to the webmention.'),
      '#options' => $reference_fields,
      '#default_value' => $config->get('comment_create_webmention_reference_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="comment_create_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Node comment field.
    $form['comment_create']['comment_create_node_comment_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Node comment field'),
      '#description' => $this->t('Select the comment field on a node. This is usually just "comment".'),
      '#options' => $node_comment_fields,
      '#default_value' => $config->get('comment_create_node_comment_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="comment_create_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['comment_create']['comment_create_mail_notification'] = [
      '#type' => 'email',
      '#title' => $this->t('Mail notification'),
      '#description' => $this->t('Send a notification to the email address entered here. Leave empty to disable mail notifications'),
      '#default_value' => $config->get('comment_create_mail_notification'),
      '#states' => array(
        'visible' => array(
          ':input[name="comment_create_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['comment_create']['comment_create_whitelist_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Trusted domains'),
      '#description' => $this->t('Automatically approve webmention comments from these domains. Enter domains line per line.') . '<br />' . $this->t('In case of multi-user site, users can override this setting.'),
      '#default_value' => $config->get('comment_create_whitelist_domains'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_webmention.comment')
      ->set('comment_create_enable', $form_state->getValue('comment_create_enable'))
      ->set('comment_create_default_status', $form_state->getValue('comment_create_default_status'))
      ->set('comment_create_comment_type', $form_state->getValue('comment_create_comment_type'))
      ->set('comment_create_webmention_reference_field', $form_state->getValue('comment_create_webmention_reference_field'))
      ->set('comment_create_node_comment_field', $form_state->getValue('comment_create_node_comment_field'))
      ->set('comment_create_mail_notification', $form_state->getValue('comment_create_mail_notification'))
      ->set('comment_create_whitelist_domains', $form_state->getValue('comment_create_whitelist_domains'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Get all comment types.
   *
   * @return array
   */
  protected function getCommentTypes() {
    return array_map(function ($bundle_info) {
      return $bundle_info['label'];
    }, \Drupal::service('entity_type.bundle.info')->getBundleInfo('comment'));
  }

}

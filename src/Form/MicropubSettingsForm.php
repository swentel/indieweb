<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicropubSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.micropub'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_micropub_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb.micropub');

    $form['info'] = [
      '#markup' => '<p>' . $this->t("Allow posting to your site. Before you can post, you need to authenticate and enable the IndieAuth Authentication API.<br />See <a href=':link_indieauth'>IndieAuth</a> to configure. More information about micropub: see <a href='https://indieweb.org/Micropub' target='_blank'>https://indieweb.org/Micropub</a>.",
          [
            ':link_indieauth' => Url::fromRoute('indieweb.admin.indieauth_settings')->toString(),
          ]) .
        '</p><p>' . $this->t("A very good client to test is <a href='https://quill.p3k.io' target='_blank'>https://quill.p3k.io</a>. A full list is available at <a href='https://indieweb.org/Micropub/Clients'>https://indieweb.org/Micropub/Clients</a>.<br />Indigenous (iOS and Android) are also microsub readers.") . '</p>',
    ];

    $form['micropub'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Micropub'),
    ];

    $form['micropub']['micropub_enable'] = [
      '#title' => $this->t('Enable micropub'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable'),
      '#description' => $this->t('This will allow the endpoint to receive requests.')
    ];

    $form['micropub']['micropub_add_header_link'] = [
      '#title' => $this->t('Add micropub endpoint to header'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_add_header_link'),
      '#description' => $this->t('The endpoint will look like <strong>https://@domain/indieweb/micropub</strong><br />This link will be added on the front page. You can also add this manually to html.html.twig.<br /><div class="indieweb-highlight-code">&lt;link rel="micropub" href="https://@domain/indieweb/micropub" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['micropub']['micropub_me'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Me'),
      '#default_value' => $config->get('micropub_me'),
      '#description' => $this->t('Every request will contain an access token which will be verified to make sure it is really you who is posting.<br />The response of the access token check request contains the "me" value which should match with your domain.<br />This is the value of your domain. Make sure there is a trailing slash, e.g. <strong>@domain/</strong>', ['@domain' => \Drupal::request()->getSchemeAndHttpHost()]),
    ];

    $form['micropub']['micropub_log_payload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the payload in watchdog on the micropub endpoint.'),
      '#default_value' => $config->get('micropub_log_payload'),
    ];

    // Collect fields.
    $text_fields = $upload_fields = $link_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['text_with_summary', 'text_long'])) {
        $text_fields[$key] = $field->getName();
      }
      if (in_array($field->getType(), ['file', 'image'])) {
        $upload_fields[$key] = $field->getName();
      }
      if (in_array($field->getType(), ['link'])) {
        $link_fields[$key] = $field->getName();
      }
    }

    $form['article'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub article is posted'),
      '#description' => $this->t("An article request contains 'content', 'name' and the 'h' value is 'entry'. Think of it as a blog post. The article can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_publish'>Publish section</a> to configure this.<br /><strong>Important:</strong> might not work with clients who send json requests (e.g. quill), will be fixed when https://github.com/swentel/indieweb/issues/44 is done", [':link_publish' => Url::fromRoute('indieweb.admin.publish_settings')->toString(),]),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['article']['article_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('article_create_node'),
    ];

    $form['article']['article_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('article_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['article']['article_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('article_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['article']['article_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('article_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['article']['article_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field'),
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => $text_fields,
      '#default_value' => $config->get('article_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Upload field.
    $form['article']['article_upload_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Upload field'),
      '#description' => $this->t('Select the field which will be used to store files. Make sure the field exists on the node type.<br />Currently only supports saving 1 file in the "image" section of a micropub request.'),
      '#options' => ['' => $this->t('Do not allow uploads')] + $upload_fields,
      '#default_value' => $config->get('article_upload_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['note'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub note is posted'),
      '#description' => $this->t("A note request contains 'content', but no 'name' and the 'h' value is 'entry'. Think of it as a Tweet. The note can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_publish'>Publish section</a> to configure this.", [':link_publish' => Url::fromRoute('indieweb.admin.publish_settings')->toString(),]),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['note']['note_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('note_create_node'),
    ];

    $form['note']['note_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('note_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['note']['note_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('note_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['note']['note_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('note_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['note']['note_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field'),
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => $text_fields,
      '#default_value' => $config->get('note_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Upload field.
    $form['note']['note_upload_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Upload field'),
      '#description' => $this->t('Select the field which will be used to store files. Make sure the field exists on the node type.<br />Currently only supports saving 1 file in the "image" section of a micropub request.'),
      '#options' => ['' => $this->t('Do not allow uploads')] + $upload_fields,
      '#default_value' => $config->get('note_upload_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['like'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub like is posted'),
      '#description' => $this->t("A like request contains a URL in 'like-of' and 'h' value is 'entry'. The like can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_publish'>Publish section</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['like']['like_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('like_create_node'),
    ];

    $form['like']['like_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('like_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['like']['like_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('like_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['like']['like_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('like_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Link field.
    $form['like']['like_link_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#description' => $this->t('Select the field which will be used to store the link. Make sure the field exists on the node type.'),
      '#options' => $link_fields,
      '#default_value' => $config->get('like_link_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['like']['like_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field') . ' (' . $this->t('optional') .')',
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => ['' => $this->t('Do not store content')] + $text_fields,
      '#default_value' => $config->get('like_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['reply'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub reply is posted'),
      '#description' => $this->t("A reply request contains a URL in 'in-reply-to', has content and 'h' value is 'entry'. The reply can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_publish'>Publish section</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['reply']['reply_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('reply_create_node'),
    ];

    $form['reply']['reply_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('reply_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['reply']['reply_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('reply_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['reply']['reply_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('reply_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Link field.
    $form['reply']['reply_link_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#description' => $this->t('Select the field which will be used to store the reply link. Make sure the field exists on the node type.'),
      '#options' => $link_fields,
      '#default_value' => $config->get('reply_link_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['reply']['reply_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field'),
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => $text_fields,
      '#default_value' => $config->get('reply_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.micropub')
      ->set('micropub_enable', $form_state->getValue('micropub_enable'))
      ->set('micropub_add_header_link', $form_state->getValue('micropub_add_header_link'))
      ->set('micropub_me', $form_state->getValue('micropub_me'))
      ->set('micropub_log_payload', $form_state->getValue('micropub_log_payload'))
      ->set('note_create_node', $form_state->getValue('note_create_node'))
      ->set('note_status', $form_state->getValue('note_status'))
      ->set('note_uid', $form_state->getValue('note_uid'))
      ->set('note_node_type', $form_state->getValue('note_node_type'))
      ->set('note_content_field', $form_state->getValue('note_content_field'))
      ->set('note_upload_field', $form_state->getValue('note_upload_field'))
      ->set('article_create_node', $form_state->getValue('article_create_node'))
      ->set('article_uid', $form_state->getValue('article_uid'))
      ->set('article_status', $form_state->getValue('article_status'))
      ->set('article_node_type', $form_state->getValue('article_node_type'))
      ->set('article_content_field', $form_state->getValue('article_content_field'))
      ->set('article_upload_field', $form_state->getValue('article_upload_field'))
      ->set('like_create_node', $form_state->getValue('like_create_node'))
      ->set('like_uid', $form_state->getValue('like_uid'))
      ->set('like_status', $form_state->getValue('like_status'))
      ->set('like_node_type', $form_state->getValue('like_node_type'))
      ->set('like_content_field', $form_state->getValue('like_content_field'))
      ->set('like_link_field', $form_state->getValue('like_link_field'))
      ->set('reply_create_node', $form_state->getValue('reply_create_node'))
      ->set('reply_uid', $form_state->getValue('reply_uid'))
      ->set('reply_status', $form_state->getValue('reply_status'))
      ->set('reply_node_type', $form_state->getValue('reply_node_type'))
      ->set('reply_content_field', $form_state->getValue('reply_content_field'))
      ->set('reply_link_field', $form_state->getValue('reply_link_field'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

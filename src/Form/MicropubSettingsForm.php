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
        '</p><p>' . $this->t("A very good client to test is <a href='https://quill.p3k.io' target='_blank'>https://quill.p3k.io</a>. A full list is available at <a href='https://indieweb.org/Micropub/Clients'>https://indieweb.org/Micropub/Clients</a>.<br />Indigenous (iOS and Android) are also microsub readers.") . '</p><p>Even if you do not decide to use the micropub endpoint, this screen gives you a good overview what kind of content types and fields you can create which can be used for sending webmentions or read by microformat parsers.</p>',
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
    $text_fields = $upload_fields = $link_fields = $date_range_fields = $option_fields = $tag_fields = [];
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
      if (in_array($field->getType(), ['daterange'])) {
        $date_range_fields[$key] = $field->getName();
      }
      if (in_array($field->getType(), ['list_string'])) {
        $option_fields[$key] = $field->getName();
      }
      if (in_array($field->getType(), ['entity_reference'])) {
        $settings = $field->getSettings();
        if (isset($settings['target_type']) && $settings['target_type'] == 'taxonomy_term') {
          $tag_fields[$key] = $field->getName();
        }
      }

    }

    $form['article'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub article is posted'),
      '#description' => $this->t("An article request contains 'content', 'name' and the 'h' value is 'entry'. Think of it as a blog post. The article can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this.<br /><strong>Important:</strong> might not work with clients who send json requests (e.g. quill), will be fixed when https://github.com/swentel/indieweb/issues/44 is done", [':link_send' => Url::fromRoute('indieweb.admin.publish_settings')->toString(),]),
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

    // Tags field.
    $form['article']['article_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('article_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="article_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['note'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub note is posted'),
      '#description' => $this->t("A note request contains 'content', but no 'name' and the 'h' value is 'entry'. Think of it as a Tweet. The note can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this.", [':link_send' => Url::fromRoute('indieweb.admin.publish_settings')->toString(),]),
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

    // Upload field.
    $form['note']['note_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('note_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="note_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['like'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub like is posted'),
      '#description' => $this->t("A like request contains a URL in 'like-of' and 'h' value is 'entry'. The like can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
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

    $form['like']['like_auto_send_webmention'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send webmention'),
      '#default_value' => $config->get('like_auto_send_webmention'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
      '#description' => $this->t('Automatically send a webmention to the URL that is found in the link field.'),
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

    // Tags field.
    $form['like']['like_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('like_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="like_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['reply'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub reply is posted'),
      '#description' => $this->t("A reply request contains a URL in 'in-reply-to', has content and 'h' value is 'entry'. The reply can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
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

    // Tags field.
    $form['reply']['reply_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('reply_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="reply_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['repost'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub repost is posted'),
      '#description' => $this->t("A repost request contains a URL in 'repost-of' and 'h' value is 'entry'. The repost can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['repost']['repost_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('repost_create_node'),
    ];

    $form['repost']['repost_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('repost_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['repost']['repost_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('repost_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['repost']['repost_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('repost_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Link field.
    $form['repost']['repost_link_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#description' => $this->t('Select the field which will be used to store the link. Make sure the field exists on the node type.'),
      '#options' => $link_fields,
      '#default_value' => $config->get('repost_link_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['repost']['repost_auto_send_webmention'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send webmention'),
      '#default_value' => $config->get('repost_auto_send_webmention'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
      '#description' => $this->t('Automatically send a webmention to the URL that is found in the link field.'),
    ];

    // Content field.
    $form['repost']['repost_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field') . ' (' . $this->t('optional') .')',
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => ['' => $this->t('Do not store content')] + $text_fields,
      '#default_value' => $config->get('repost_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Tags field.
    $form['repost']['repost_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('repost_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="repost_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['bookmark'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub bookmark is posted'),
      '#description' => $this->t("A bookmark request contains a URL in 'bookmark-of' and 'h' value is 'entry'. The bookmark can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['bookmark']['bookmark_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('bookmark_create_node'),
    ];

    $form['bookmark']['bookmark_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('bookmark_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['bookmark']['bookmark_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('bookmark_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['bookmark']['bookmark_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('bookmark_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Link field.
    $form['bookmark']['bookmark_link_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#description' => $this->t('Select the field which will be used to store the link. Make sure the field exists on the node type.'),
      '#options' => $link_fields,
      '#default_value' => $config->get('bookmark_link_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['bookmark']['bookmark_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field') . ' (' . $this->t('optional') .')',
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => ['' => $this->t('Do not store content')] + $text_fields,
      '#default_value' => $config->get('bookmark_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['bookmark']['bookmark_auto_send_webmention'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Send webmention'),
      '#default_value' => $config->get('bookmark_auto_send_webmention'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
      '#description' => $this->t('Automatically send a webmention to the URL that is found in the link field.'),
    ];

    // Tags field.
    $form['bookmark']['bookmark_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('bookmark_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="bookmark_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['event'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub event is posted'),
      '#description' => $this->t("An event request contains a start and end date and the 'h' value is 'event'. The event can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['event']['event_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('event_create_node'),
    ];

    $form['event']['event_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('event_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['event']['event_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('event_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['event']['event_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('event_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Date field.
    $form['event']['event_date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Date field'),
      '#description' => $this->t('Select the field which will be used to store the date. Make sure the field exists on the node type.<br />This can only be a date range field.'),
      '#options' => $date_range_fields,
      '#default_value' => $config->get('event_date_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['event']['event_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field') . ' (' . $this->t('optional') .')',
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => ['' => $this->t('Do not store content')] + $text_fields,
      '#default_value' => $config->get('event_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Tags field.
    $form['event']['event_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('event_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="event_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['rsvp'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Create a node when a micropub rsvp is posted'),
      '#description' => $this->t("An rsvp request contains an rsvp field. The rsvp can also contain a 'mp-syndicate-to' value which will contain the channel you want to publish to, see the <a href=':link_send'>Send webmention screen</a> to configure this."),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['rsvp']['rsvp_create_node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#default_value' => $config->get('rsvp_create_node'),
    ];

    $form['rsvp']['rsvp_status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Status'),
      '#options' => [
        0 => $this->t('Unpublished'),
        1 => $this->t('Published'),
      ],
      '#default_value' => $config->get('rsvp_status'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['rsvp']['rsvp_uid'] = [
      '#type' => 'number',
      '#title' => $this->t('The user id which will own the created node'),
      '#default_value' => $config->get('rsvp_uid'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['rsvp']['rsvp_node_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Node type'),
      '#description' => $this->t('Select the node type to use for creating a node'),
      '#options' => node_type_get_names(),
      '#default_value' => $config->get('rsvp_node_type'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // RSVP field.
    $form['rsvp']['rsvp_rsvp_field'] = [
      '#type' => 'select',
      '#title' => $this->t('RSVP field'),
      '#description' => $this->t('Select the field which will be used to store the RSVP value. Make sure the field exists on the node type.<br />This can only be a list option field with following values:<br />yes|I am going!<br />no|I am not going<br />maybe|I might attend<br />interested|I am interested'),
      '#options' => $option_fields,
      '#default_value' => $config->get('rsvp_rsvp_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Link field.
    $form['rsvp']['rsvp_link_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Link field'),
      '#description' => $this->t('Select the field which will be used to store the event link. Make sure the field exists on the node type.'),
      '#options' => $link_fields,
      '#default_value' => $config->get('rsvp_link_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Content field.
    $form['rsvp']['rsvp_content_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Content field') . ' (' . $this->t('optional') .')',
      '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
      '#options' => ['' => $this->t('Do not store content')] + $text_fields,
      '#default_value' => $config->get('rsvp_content_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Tags field.
    $form['rsvp']['rsvp_tags_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Tags field'),
      '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
      '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
      '#default_value' => $config->get('rsvp_tags_field'),
      '#states' => array(
        'visible' => array(
          ':input[name="rsvp_create_node"]' => array('checked' => TRUE),
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
      ->set('note_tags_field', $form_state->getValue('note_tags_field'))
      ->set('article_create_node', $form_state->getValue('article_create_node'))
      ->set('article_uid', $form_state->getValue('article_uid'))
      ->set('article_status', $form_state->getValue('article_status'))
      ->set('article_node_type', $form_state->getValue('article_node_type'))
      ->set('article_content_field', $form_state->getValue('article_content_field'))
      ->set('article_upload_field', $form_state->getValue('article_upload_field'))
      ->set('article_tags_field', $form_state->getValue('article_tags_field'))
      ->set('like_create_node', $form_state->getValue('like_create_node'))
      ->set('like_uid', $form_state->getValue('like_uid'))
      ->set('like_status', $form_state->getValue('like_status'))
      ->set('like_node_type', $form_state->getValue('like_node_type'))
      ->set('like_content_field', $form_state->getValue('like_content_field'))
      ->set('like_link_field', $form_state->getValue('like_link_field'))
      ->set('like_auto_send_webmention', $form_state->getValue('like_auto_send_webmention'))
      ->set('like_tags_field', $form_state->getValue('like_tags_field'))
      ->set('reply_create_node', $form_state->getValue('reply_create_node'))
      ->set('reply_uid', $form_state->getValue('reply_uid'))
      ->set('reply_status', $form_state->getValue('reply_status'))
      ->set('reply_node_type', $form_state->getValue('reply_node_type'))
      ->set('reply_content_field', $form_state->getValue('reply_content_field'))
      ->set('reply_link_field', $form_state->getValue('reply_link_field'))
      ->set('reply_tags_field', $form_state->getValue('reply_tags_field'))
      ->set('repost_create_node', $form_state->getValue('repost_create_node'))
      ->set('repost_uid', $form_state->getValue('repost_uid'))
      ->set('repost_status', $form_state->getValue('repost_status'))
      ->set('repost_node_type', $form_state->getValue('repost_node_type'))
      ->set('repost_content_field', $form_state->getValue('repost_content_field'))
      ->set('repost_link_field', $form_state->getValue('repost_link_field'))
      ->set('repost_auto_send_webmention', $form_state->getValue('repost_auto_send_webmention'))
      ->set('repost_tags_field', $form_state->getValue('repost_tags_field'))
      ->set('bookmark_create_node', $form_state->getValue('bookmark_create_node'))
      ->set('bookmark_uid', $form_state->getValue('bookmark_uid'))
      ->set('bookmark_status', $form_state->getValue('bookmark_status'))
      ->set('bookmark_node_type', $form_state->getValue('bookmark_node_type'))
      ->set('bookmark_content_field', $form_state->getValue('bookmark_content_field'))
      ->set('bookmark_link_field', $form_state->getValue('bookmark_link_field'))
      ->set('bookmark_auto_send_webmention', $form_state->getValue('bookmark_auto_send_webmention'))
      ->set('bookmark_tags_field', $form_state->getValue('bookmark_tags_field'))
      ->set('event_create_node', $form_state->getValue('event_create_node'))
      ->set('event_uid', $form_state->getValue('event_uid'))
      ->set('event_status', $form_state->getValue('event_status'))
      ->set('event_node_type', $form_state->getValue('event_node_type'))
      ->set('event_content_field', $form_state->getValue('event_content_field'))
      ->set('event_date_field', $form_state->getValue('event_date_field'))
      ->set('event_tags_field', $form_state->getValue('event_tags_field'))
      ->set('rsvp_create_node', $form_state->getValue('rsvp_create_node'))
      ->set('rsvp_uid', $form_state->getValue('rsvp_uid'))
      ->set('rsvp_status', $form_state->getValue('rsvp_status'))
      ->set('rsvp_node_type', $form_state->getValue('rsvp_node_type'))
      ->set('rsvp_content_field', $form_state->getValue('rsvp_content_field'))
      ->set('rsvp_link_field', $form_state->getValue('rsvp_link_field'))
      ->set('rsvp_rsvp_field', $form_state->getValue('rsvp_rsvp_field'))
      ->set('rsvp_tags_field', $form_state->getValue('rsvp_tags_field'))

      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

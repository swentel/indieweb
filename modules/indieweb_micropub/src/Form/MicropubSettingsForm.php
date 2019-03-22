<?php

namespace Drupal\indieweb_micropub\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicropubSettingsForm extends ConfigFormBase {

  /**
   * Returns supported post types.
   *
   * @return array
   */
  protected function getPostTypes() {
    $post_types = [
      'article' => [
        'geo_field' => TRUE,
        'description' => $this->t("An article request contains 'content', 'name' and the 'h' value is 'entry'. Think of it as a blog post."),
      ],
      'note' => [
        'geo_field' => TRUE,
        'description' => $this->t("A note request contains 'content', but no 'name' and the 'h' value is 'entry'. Think of it as a Tweet."),
      ],
      'like' => [
        'description' => $this->t("A like request contains a URL in 'like-of' and 'h' value is 'entry'."),
        'optional_body' => TRUE,
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'reply' => [
        'description' => $this->t("A reply request contains a URL in 'in-reply-to', has content and 'h' value is 'entry'."),
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'repost' => [
        'description' => $this->t("A repost request contains a URL in 'repost-of' and 'h' value is 'entry'. When content is found, this will be stored in the title of the link which will make microformat parsers handle this as a quotation. It does not make sense to have a body field for this content type."),
        'no_body' => TRUE,
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'bookmark' => [
        'description' => $this->t("A bookmark request contains a URL in 'bookmark-of' and 'h' value is 'entry'."),
        'optional_body' => TRUE,
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'event' => [
        'description' => $this->t("An event request contains a start and end date and the 'h' value is 'event'."),
        'date_field' => TRUE,
        'link_field' => TRUE,
        'optional_body' => TRUE,
        'geo_field' => TRUE,
      ],
      'rsvp' => [
        'description' => $this->t("A RSVP request contains an RSVP field."),
        'rsvp_field' => TRUE,
        'optional_body' => TRUE,
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'issue' => [
        'description' => $this->t("An issue request contains 'content', 'name', a URL in 'in-reply-to' (which is the URL of a repository) and the 'h' value is 'entry'."),
        'link_field' => TRUE,
        'send_webmention' => TRUE,
      ],
      'checkin' => [
        'description' => $this->t("A checkin request contains 'checkin' which is an URL and optionally a name or an h-card which contains url, name, latitude and longitude. 'Content' and 'name' are optional and the 'h' value is 'entry'.") . '<br /><strong>Note: experimental, will not work with some clients.</strong>',
        'link_field' => TRUE,
        'optional_body' => TRUE,
        'geo_field' => TRUE,
      ],
      'geocache' => [
        'description' => $this->t("A geocache request contains 'p-geocache-log-type', 'checkin' which is an URL and optionally a name or an h-card which contains url, name, latitude and longitude. 'Content' and 'name' are optional and the 'h' value is 'entry'.") . '<br /><strong>Note: experimental, will not work with some clients.</strong>',
        'link_field' => TRUE,
        'geocache_field' => TRUE,
        'optional_body' => TRUE,
        'geo_field' => TRUE,
      ],
    ];

    return $post_types;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_micropub.settings'];
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

    $config = $this->config('indieweb_micropub.settings');

    $form['micropub'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['general'] = [
      '#type' => 'details',
      '#group' => 'micropub',
      '#title' => $this->t('General'),
    ];

    $form['general']['micropub_enable'] = [
      '#title' => $this->t('Enable micropub'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable'),
      '#description' => $this->t('This will allow the endpoint to receive requests.')
    ];

    $form['general']['micropub_expose_link_tag'] = [
      '#title' => $this->t('Expose micropub endpoint link tag'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_expose_link_tag'),
      '#description' => $this->t('The endpoint is available at <strong>https://@domain/indieweb/micropub</strong><br />This link will be added on the front page. You can also add this manually to html.html.twig.<br /><div class="indieweb-highlight-code">&lt;link rel="micropub" href="https://@domain/indieweb/micropub" /&gt;</div>', ['@domain' => \Drupal::request()->getHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_enable_update'] = [
      '#title' => $this->t('Enable post updates'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable_update'),
      '#description' => $this->t('Allow sending update requests to update any node or comment. Updating posts is currently limited to title, body and published status.'),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_enable_delete'] = [
      '#title' => $this->t('Enable post deletes'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable_delete'),
      '#description' => $this->t('Allow sending delete requests to delete any node, comment or webmention.'),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_enable_source'] = [
      '#title' => $this->t('Enable post queries'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable_source'),
      '#description' => $this->t('Allow clients to query for posts via q=source. This is experimental, leave disabled if your client does not support it.'),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_media_enable'] = [
      '#title' => $this->t('Enable media endpoint'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_media_enable'),
      '#description' => $this->t('This will enable the micropub media endpoint to receive files, limited to images (jpg, png, gif). <br />The endpoint will look like <strong>https://@domain/indieweb/micropub/media</strong><br />', ['@domain' => \Drupal::request()->getHttpHost()]),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $vocabularies = [];
    if ($taxonomy_module_enabled = \Drupal::moduleHandler()->moduleExists('taxonomy')) {
      $all_vocabularies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
      foreach ($all_vocabularies as $vocabulary) {
        $vocabularies[$vocabulary->id()] = $vocabulary->label();
      }
    }
    $form['general']['micropub_enable_category'] = [
      '#title' => $this->t('Enable terms request'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('micropub_enable_category'),
      '#description' => $this->t('Allow sending a request to return a list of terms.'),
      '#disabled' => !$taxonomy_module_enabled || empty($vocabularies),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_category_vocabulary'] = [
      '#title' => $this->t('Vocabulary'),
      '#type' => 'select',
      '#options' => $vocabularies,
      '#default_value' => $config->get('micropub_category_vocabulary'),
      '#description' => $this->t('Select the vocabulary to return the terms from.'),
      '#disabled' => !$taxonomy_module_enabled || empty($vocabularies),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
          ':input[name="micropub_enable_category"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_enable_geo'] = [
      '#title' => $this->t('Enable geo lookup'),
      '#type' => 'checkbox',
      '#access' => \Drupal::moduleHandler()->moduleExists('geocoder'),
      '#default_value' => $config->get('micropub_enable_geo'),
      '#description' => $this->t('This will allow requests on q=geo for location information using the <a href="https://www.drupal.org/project/geocoder" target="_blank">Geocoder module</a>.<br />Configuration of the providers to use is currently done via settings.php: e.g.<br /> <div class="indieweb-highlight-code">$settings[\'indieweb_micropub_geo_plugins\'] = [\'arcgisonline\'];</div>'),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['general']['micropub_log_payload'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log the payload in watchdog on the micropub endpoint.'),
      '#default_value' => $config->get('micropub_log_payload'),
      '#states' => array(
        'visible' => array(
          ':input[name="micropub_enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    // Collect fields.
    $text_fields = $upload_fields = $link_fields = $date_range_fields = $option_fields = $tag_fields = $geo_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    $field_types = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['text_with_summary', 'text_long'])) {
        $text_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['file', 'image'])) {
        $upload_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['link'])) {
        $link_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['daterange'])) {
        $date_range_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['list_string'])) {
        $option_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['geofield'])) {
        $geo_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
      if (in_array($field->getType(), ['entity_reference'])) {
        $settings = $field->getSettings();
        if (isset($settings['target_type']) && $settings['target_type'] == 'taxonomy_term') {
          $tag_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
        }
      }
    }

    foreach ($this->getPostTypes() as $post_type => $configuration) {

      $form[$post_type] = [
        '#type' => 'details',
        '#group' => 'micropub',
        '#title' => $this->t('@micropub_post_type', ['@micropub_post_type' => ucfirst($post_type)]),
        '#description' => $configuration['description'],
      ];

      $form[$post_type][$post_type . '_create_node'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#default_value' => $config->get($post_type . '_create_node'),
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
          ),
        ),
      ];

      // Create comments if available on the reply post type.
      if ($post_type == 'reply' && \Drupal::moduleHandler()->moduleExists('indieweb_webmention') && \Drupal::moduleHandler()->moduleExists('comment')) {
        $form[$post_type]['reply_create_comment'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable comment creation'),
          '#description' => $this->t('If a reply post comes in and the reply-to-url is a node or comment on this site, or a webmention which target is a node or comment and property "in-reply-to", create a (child) comment from this reply.<br /><a href=":url">Comment creation</a> needs to be enabled and configured for this to work. In case the target is a webmention, the original url can be stored if a <a href=":url2">link field is configured</a> on the comment type.', [':url' => Url::fromRoute('indieweb.admin.comment_settings')->toString(), ':url2' => Url::fromRoute('indieweb.admin.webmention_send_settings')->toString()]),
          '#default_value' => $config->get('reply_create_comment'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      $form[$post_type][$post_type . '_status'] = [
        '#type' => 'radios',
        '#title' => $this->t('Status'),
        '#options' => [
          0 => $this->t('Unpublished'),
          1 => $this->t('Published'),
        ],
        '#default_value' => $config->get($post_type . '_status'),
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
            ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
          ),
        ),
        '#description' => $this->t('When the payload contains the "post-status" property, its value will take precedence over this one. See <a href="https://indieweb.org/Micropub-extensions#Post_Status" target="_blank">https://indieweb.org/Micropub-extensions#Post_Status</a>'),
      ];

      $author = NULL;
      if ($uid = $config->get($post_type . '_uid')) {
        $author = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
      }
      $form[$post_type][$post_type . '_uid'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        '#title' => $this->t('Author of the node'),
        '#default_value' => $author,
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
            ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
          ),
        ),
        '#description' => $this->t('Default user id when a node is created. If you are using the internal IndieAuth endpoint, the author of the node will be the owner of the token used in the micropub request.')
      ];

      $form[$post_type][$post_type . '_node_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Node type'),
        '#description' => $this->t('Select the node type to use for creating a node'),
        '#options' => ['' => $this->t('Select a node type')] + node_type_get_names(),
        '#default_value' => $config->get($post_type . '_node_type'),
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
            ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
          ),
        ),
      ];

      // Date field.
      if (isset($configuration['date_field'])) {
        $form[$post_type][$post_type . '_date_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Date field'),
          '#description' => $this->t('Select the field which will be used to store the date. Make sure the field exists on the node type.<br />This can only be a date range field.'),
          '#options' => $date_range_fields,
          '#default_value' => $config->get($post_type . '_date_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="'. $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      // RSVP field.
      if (isset($configuration['rsvp_field'])) {
        $form[$post_type][$post_type . '_rsvp_field'] = [
          '#type' => 'select',
          '#title' => $this->t('RSVP field'),
          '#description' => $this->t('Select the field which will be used to store the RSVP value. Make sure the field exists on the node type.<br />This can only be a list option field with following values:<br />yes|I am going!<br />no|I am not going<br />maybe|I might attend<br />interested|I am interested<br />This module comes with a rsvp storage field with those settings, so it is easy to add.'),
          '#options' => $option_fields,
          '#default_value' => $config->get($post_type . '_rsvp_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      // Geocache field.
      if (isset($configuration['geocache_field'])) {
        $form[$post_type][$post_type . '_geocache_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Geocache field'),
          '#description' => $this->t('Select the field which will be used to store the log type value. Make sure the field exists on the node type.<br />This can only be a list option field with following values:<br />found|Found<br />not-found|Did not find<br />This module comes with a geocache storage field with those settings, so it is easy to add.'),
          '#options' => $option_fields,
          '#default_value' => $config->get($post_type . '_geocache_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      // Link field.
      if (isset($configuration['link_field'])) {
        $form[$post_type][$post_type . '_link_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Link field'),
          '#description' => $this->t('Select the field which will be used to store the link. Make sure the field exists on the node type.'),
          '#options' => $link_fields,
          '#default_value' => $config->get($post_type . '_link_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      // Send webmention.
      if (isset($configuration['send_webmention'])) {
        $form[$post_type][$post_type . '_auto_send_webmention'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Send webmention'),
          '#default_value' => $config->get($post_type . '_auto_send_webmention'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
          '#description' => $this->t('Automatically send a webmention to the URL that is found in the link field.'),
        ];
      }

      // Content field.
      if (!isset($configuration['no_body'])) {
        $optional_body = [];
        if (isset($configuration['optional_body'])) {
          $optional_body = ['' => $this->t('Do not store content')];
        }
        $form[$post_type][$post_type . '_content_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Content field'),
          '#description' => $this->t('Select the field which will be used to store the content. Make sure the field exists on the node type.'),
          '#options' => $optional_body + $text_fields,
          '#default_value' => $config->get($post_type . '_content_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

      // Upload field.
      $form[$post_type][$post_type . '_upload_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Image field'),
        '#description' => $this->t('Select the field which will be used to store files. Make sure the field exists on the node type.<br />Only images are allowed, multiple if the field has a cardinality other than 1.'),
        '#options' => ['' => $this->t('Do not allow uploads')] + $upload_fields,
        '#default_value' => $config->get($post_type . '_upload_field'),
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
            ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
          ),
        ),
      ];

      // Tags field.
      $form[$post_type][$post_type . '_tags_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Tags field'),
        '#description' => $this->t('Select the field which will be used to store tags. Make sure the field exists on the node type.<br />Field can only be a reference field targeting a taxonomy vocabulary and should only have one target bundle.'),
        '#options' => ['' => $this->t('Do not store tags')] + $tag_fields,
        '#default_value' => $config->get($post_type . '_tags_field'),
        '#states' => array(
          'visible' => array(
            ':input[name="micropub_enable"]' => array('checked' => TRUE),
            ':input[name="' . $post_type . '_create_node"]' => array('checked' => TRUE),
          ),
        ),
      ];

      // Location field.
      if (isset($configuration['geo_field'])) {
        $optional_location = ['' => $this->t('Do not store location')];
        $form[$post_type][$post_type . '_geo_field'] = [
          '#type' => 'select',
          '#title' => $this->t('Geo field'),
          '#description' => $this->t('Select the field which will be used to store the geo location. Make sure the field exists on the node type.<br />This can only be a geofield field from the <a href="https://www.drupal.org/project/geofield" target="_blank">Geofield module</a>.'),
          '#options' => $optional_location + $geo_fields,
          '#default_value' => $config->get($post_type . '_geo_field'),
          '#states' => array(
            'visible' => array(
              ':input[name="micropub_enable"]' => array('checked' => TRUE),
              ':input[name="'. $post_type . '_create_node"]' => array('checked' => TRUE),
            ),
          ),
        ];
      }

    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->config('indieweb_micropub.settings');

    $config
      ->set('micropub_enable', $form_state->getValue('micropub_enable'))
      ->set('micropub_enable_update', $form_state->getValue('micropub_enable_update'))
      ->set('micropub_enable_delete', $form_state->getValue('micropub_enable_delete'))
      ->set('micropub_enable_source', $form_state->getValue('micropub_enable_source'))
      ->set('micropub_enable_geo', $form_state->getValue('micropub_enable_geo'))
      ->set('micropub_expose_link_tag', $form_state->getValue('micropub_expose_link_tag'))
      ->set('micropub_media_enable', $form_state->getValue('micropub_media_enable'))
      ->set('micropub_enable_category', $form_state->getValue('micropub_enable_category'))
      ->set('micropub_category_vocabulary', $form_state->getValue('micropub_category_vocabulary'))
      ->set('micropub_log_payload', $form_state->getValue('micropub_log_payload'));


    // Reply create comment.
    $config->set('reply_create_comment', $form_state->getValue('reply_create_comment'));

    // Loop over post types.
    foreach ($this->getPostTypes() as $post_type => $configuration) {

     $config->set($post_type . '_create_node', $form_state->getValue($post_type . '_create_node'))
        ->set($post_type . '_status', $form_state->getValue($post_type . '_status'))
        ->set($post_type . '_uid', $form_state->getValue($post_type . '_uid'))
        ->set($post_type . '_node_type', $form_state->getValue($post_type . '_node_type'))
        ->set($post_type . '_upload_field', $form_state->getValue($post_type . '_upload_field'))
        ->set($post_type . '_tags_field', $form_state->getValue($post_type . '_tags_field'));

      if (!isset($configuration['no_body'])) {
        $config->set($post_type . '_content_field', $form_state->getValue($post_type . '_content_field'));
      }

      if (isset($configuration['link_field'])) {
        $config->set($post_type . '_link_field', $form_state->getValue($post_type . '_link_field'));
      }

      if (isset($configuration['geo_field'])) {
        $config->set($post_type . '_geo_field', $form_state->getValue($post_type . '_geo_field'));
      }

      if (isset($configuration['date_field'])) {
        $config->set($post_type . '_date_field', $form_state->getValue($post_type . '_date_field'));
      }

      if (isset($configuration['rsvp_field'])) {
        $config->set($post_type . '_rsvp_field', $form_state->getValue($post_type . '_rsvp_field'));
      }

      if (isset($configuration['geocache_field'])) {
        $config->set($post_type . '_geocache_field', $form_state->getValue($post_type . '_geocache_field'));
      }

      if (isset($configuration['send_webmention'])) {
        $config->set($post_type . '_auto_send_webmention', $form_state->getValue($post_type . '_auto_send_webmention'));
      }

    }

    $config->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

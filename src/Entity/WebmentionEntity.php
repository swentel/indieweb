<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Webmention entity.
 *
 * @ingroup webmention
 *
 * @ContentEntityType(
 *   id = "webmention_entity",
 *   label = @Translation("Webmention"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\indieweb\WebmentionEntityListBuilder",
 *     "views_data" = "Drupal\indieweb\Entity\WebmentionEntityViewsData",
 *
 *     "form" = {
 *       "default" = "Drupal\indieweb\Form\WebmentionEntityForm",
 *       "add" = "Drupal\indieweb\Form\WebmentionEntityForm",
 *       "edit" = "Drupal\indieweb\Form\WebmentionEntityForm",
 *       "delete" = "Drupal\indieweb\Form\WebmentionEntityDeleteForm",
 *     },
 *     "access" = "Drupal\indieweb\WebmentionEntityAccessControlHandler",
 *     "route_provider" = {
 *      "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *
 *   },
 *   base_table = "webmention_entity",
 *   admin_permission = "administer webmention entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "target",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/webmention/{webmention_entity}",
 *     "add-form" = "/admin/content/webmention/add",
 *     "edit-form" = "/admin/content/webmention/{webmention_entity}/edit",
 *     "delete-form" = "/admin/content/webmention/{webmention_entity}/delete",
 *     "collection" = "/admin/content/webmention",
 *   },
 *   field_ui_base_route = "indieweb.admin.dashboard"
 * )
 */
class WebmentionEntity extends ContentEntityBase implements WebmentionEntityInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    return (bool) $this->getEntityKey('status');
  }

  /**
   * {@inheritdoc}
   */
  public function setPublished($published) {
    $this->set('status', $published ? TRUE : FALSE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Webmention entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 20,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $additional_fields = [
      'source' => 'Source',
      'target' => 'Target',
      'type' => 'Type',
      'property' => 'Property',
      'author_name' => 'Author name',
      'author_photo' => 'Author avatar',
      'author_url' => 'Author URL',
    ];

    $weight = 0;
    foreach ($additional_fields as $key => $label) {

      $required = $weight < 4;

      $fields[$key] = BaseFieldDefinition::create('string')
        ->setLabel(t($label))
        ->setSettings([
          'max_length' => 255,
          'text_processing' => 0,
        ])
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'string',
          'weight' => $weight,
        ])
        ->setDisplayOptions('form', [
          'type' => 'string_textfield',
          'weight' => $weight,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE)
        ->setDefaultValue('')
        ->setRequired($required);

      $weight++;
    }

    // Long text fields.
    foreach (['content_html' => 'HTML content', 'content_text' => 'Text content'] as $key => $label) {

      $fields[$key] = BaseFieldDefinition::create('string_long')
        ->setLabel(t($label))
        ->setDisplayOptions('view', [
          'label' => 'above',
          'type' => 'basic_string',
          'weight' => $weight,
        ])
        ->setDisplayOptions('form', [
          'type' => 'string_textarea',
          'weight' => $weight,
        ])
        ->setDisplayConfigurable('form', TRUE)
        ->setDisplayConfigurable('view', TRUE)
        ->setDefaultValue('')
        ->setRequired(FALSE);

      $weight++;

    }

    $fields['private'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Private'))
      ->setDescription(t('A boolean indicating whether the Webmention was private or not.'))
      ->setDefaultValue(FALSE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 19,
      ]);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Webmention is published.'))
      ->setDefaultValue(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'weight' => 20,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

}

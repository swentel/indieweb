<?php

namespace Drupal\indieweb_contact\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Contact entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_contact",
 *   label = @Translation("Contact"),
 *   label_collection = @Translation("Contacts"),
 *   label_singular = @Translation("Contact"),
 *   label_plural = @Translation("Contacts"),
 *   label_count = @PluralTranslation(
 *     singular = "@count contact",
 *     plural = "@count contacts",
 *   ),
 *   handlers = {
 *     "storage_schema" = "Drupal\indieweb_contact\Entity\Storage\ContactStorageSchema",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\indieweb_contact\Entity\ContactListBuilder",
 *     "views_data" = "Drupal\indieweb_contact\Entity\ContactViewsData",
 *     "form" = {
 *       "default" = "Drupal\indieweb_contact\Form\ContactForm",
 *       "add" = "Drupal\indieweb_contact\Form\ContactForm",
 *       "edit" = "Drupal\indieweb_contact\Form\ContactForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\indieweb_contact\Entity\ContactAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     },
 *   },
 *   base_table = "indieweb_contact",
 *   admin_permission = "administer indieweb contact entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "langcode" = "langcode",
 *     "status" = "status",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/contacts/{indieweb_contact}",
 *     "add-form" = "/admin/content/contacts/add",
 *     "edit-form" = "/admin/content/contacts/{indieweb_contact}/edit",
 *     "delete-form" = "/admin/content/contacts/{indieweb_contact}/delete",
 *     "collection" = "/admin/content/contacts"
 *   }
 * )
 */
class Contact extends ContentEntityBase implements ContactInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getNickname() {
    return !empty($this->get('nickname')->value) ? $this->get('nickname')->value : "";
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsite() {
    return !empty($this->get('url')->value) ? $this->get('url')->value : "";
  }

  /**
   * {@inheritdoc}
   */
  public function getPhoto() {
    return !empty($this->get('photo')->value) ? $this->get('photo')->value : "";
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Contact entity.'))
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
      'name' => 'Name',
      'nickname' => 'Nickname',
      'url' => 'Website',
      'photo' => 'Photo',
    ];

    $weight = 0;
    foreach ($additional_fields as $key => $label) {
      $max_length = 255;
      $required = $key == 'name';

      $cardinality = $key == 'url' ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1;

      $fields[$key] = BaseFieldDefinition::create('string')
        ->setLabel(t($label))
        ->setSettings([
          'max_length' => $max_length,
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
        ->setCardinality($cardinality)
        ->setRequired($required);

      $weight++;
    }

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Publishing status'))
      ->setDescription(t('A boolean indicating whether the Contact is published.'))
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

<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb webmention send entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_webmention_send",
 *   label = @Translation("Webmention send"),
 *   label_collection = @Translation("Webmention send items"),
 *   label_singular = @Translation("Webmention send item"),
 *   label_plural = @Translation("Webmention send items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count webmention send item",
 *     plural = "@count webmention send items",
 *   ),
 *   persistent_cache = FALSE,
 *   admin_permission = "administer webmention entities",
 *   handlers = {
 *     "list_builder" = "Drupal\indieweb_webmention\Entity\SendListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *      "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "webmention_send",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   links = {
 *     "delete-form" = "/admin/content/webmention/send-list/{indieweb_webmention_send}/delete",
 *     "collection" = "/admin/content/webmention/send-list",
 *   }
 * )
 */
class Send extends ContentEntityBase implements SendInterface {

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->get('source')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTarget() {
    return $this->get('target')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityTypeId() {
    return $this->get('entity_type_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->get('entity_id')->value;
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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('Webmention send id'))
      ->setDescription(t('The id of the item.'));

    $fields['source'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['target'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('The entity id'))
      ->setDefaultValue(1);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('entity_type_id'))
      ->setDescription(t('The entity type id.'))
      ->setSetting('max_length', 128);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    return $fields;
  }

}

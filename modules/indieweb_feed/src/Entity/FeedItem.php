<?php

namespace Drupal\indieweb_feed\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb feed item entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_feed_item",
 *   label = @Translation("Feed item"),
 *   label_collection = @Translation("Feed items"),
 *   label_singular = @Translation("Feed item"),
 *   label_plural = @Translation("Feed items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count feed item",
 *     plural = "@count feed items",
 *   ),
 *   persistent_cache = FALSE,
 *   handlers = {
 *     "storage" = "Drupal\indieweb_feed\Entity\Storage\FeedItemStorage",
 *     "storage_schema" = "Drupal\indieweb_feed\Entity\Storage\FeedItemStorageSchema",
 *   },
 *   base_table = "indieweb_feed_item",
 *   entity_keys = {
 *     "id" = "id",
 *   }
 * )
 */
class FeedItem extends ContentEntityBase implements FeedItemInterface {

    /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id']->setLabel('Feed item id')
      ->setDescription(t('The id of the item.'));

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel('The id of the entity');

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel('The entity type id')
      ->setDescription('The entity type id of the entity')
      ->setSetting('max_length', 40);

    $fields['published'] = BaseFieldDefinition::create('integer')
      ->setLabel('The published status of the entity');

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDefaultValue(0)
      ->setDescription(t('Posted date of the entity item, as a Unix timestamp. Used for sorting'));

    $fields['feed_id'] = BaseFieldDefinition::create('string')
      ->setLabel('Feed id')
      ->setDescription(t('The id of the feed'))
      ->setSetting('max_length', 128);

    return $fields;
  }
}

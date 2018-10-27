<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb microsub item entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_microsub_item",
 *   label = @Translation("Microsub source item"),
 *   label_collection = @Translation("Microsub source items"),
 *   label_singular = @Translation("Microsub source item"),
 *   label_plural = @Translation("aggregator source items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count microsub source item",
 *     plural = "@count microsub source items",
 *   ),
 *   persistent_cache = FALSE,
 *   handlers = {
 *     "storage" = "Drupal\indieweb\Entity\Storage\MicrosubItemStorage",
 *     "storage_schema" = "Drupal\indieweb\Entity\Storage\MicrosubItemStorageSchema",
 *   },
 *   base_table = "microsub_item",
 *   entity_keys = {
 *     "id" = "mid",
 *     "langcode" = "langcode",
 *   }
 * )
 */
class MicrosubItem extends ContentEntityBase implements MicrosubItemInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceId() {
    return $this->get('source_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return $this->get('data')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isRead() {
    return (bool) $this->get('is_read')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['mid']->setLabel(t('Microsub item ID'))
      ->setDescription(t('The ID of the item.'));

    $fields['langcode']->setLabel(t('Language code'))
      ->setDescription(t('The microsub item language code.'));

    $fields['source_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Source'))
      ->setRequired(TRUE)
      ->setDescription(t('The microsub source entity associated with this item.'))
      ->setSetting('target_type', 'indieweb_microsub_source');

    $fields['channel_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Channel'))
      ->setRequired(TRUE)
      ->setDescription(t('The microsub channel entity associated with this item.'))
      ->setSetting('target_type', 'indieweb_microsub_channel');

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('data'))
      ->setDescription(t('The data of the item.'));

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Posted on'))
      ->setDescription(t('Posted date of the feed item, as a Unix timestamp.'));

    $fields['guid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('GUID'))
      ->setDescription(t('Unique identifier for the microsub item.'))
      ->setSetting('max_length', 255);

    $fields['is_read'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Read'))
      ->setDefaultValue(0)
      ->setDescription(t('Whether the entry was read or not'));

    return $fields;
  }

}
<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb microsub item entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_microsub_item",
 *   label = @Translation("Microsub item"),
 *   label_collection = @Translation("Microsub source items"),
 *   label_singular = @Translation("Microsub source item"),
 *   label_plural = @Translation("Microsub source items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count microsub source item",
 *     plural = "@count microsub source items",
 *   ),
 *   persistent_cache = FALSE,
 *   handlers = {
 *     "storage" = "Drupal\indieweb_microsub\Entity\Storage\MicrosubItemStorage",
 *     "storage_schema" = "Drupal\indieweb_microsub\Entity\Storage\MicrosubItemStorageSchema",
 *   },
 *   base_table = "microsub_item",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *   }
 * )
 */
class MicrosubItem extends ContentEntityBase implements MicrosubItemInterface {

  /**
   * {@inheritdoc}
   */
  public function getChannelId() {
    return $this->get('channel_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceId() {
    return $this->get('source_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceIdForTimeline($author, $urls = []) {
    $return = $this->getSourceId();

    if ($this->getChannelId() > 0 && $return > 0) {
      $url = $this->getSource()->label();
      foreach ($urls as $u) {
        if (strpos($url, $u) !== FALSE) {
          $return = $this->getSourceId() . ':' . $author;
          break;
        }
      }
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getChannel() {
    return $this->get('channel_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->get('source_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $return = NULL;
    $value = $this->get('data')->value;
    if (!empty($value)) {
      $return = json_decode($value);
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    $return = NULL;
    $value = $this->get('post_context')->value;
    if (!empty($value)) {
      $temp = json_decode($value);
      if (isset($temp->url)) {
        $return = new \stdClass();
        $object = new \stdClass();
        $content = '';
        if (!empty($temp->content->text)) {
          $content = $temp->content->text;
        }
        elseif (!empty($temp->summary)) {
          $content = $temp->summary;
        }
        $object->content = $content;
        $object->name = isset($temp->name) ? $temp->name : '';
        $return->{$temp->url} = $object;
      }
    }
    return $return;
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

    $fields['id']->setLabel(t('Microsub item ID'))
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

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status of the item'))
      ->setDefaultValue(1);

    $fields['data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Data'))
      ->setDescription(t('The data of the item.'));

    $fields['post_context'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Post context'))
      ->setDescription(t('The post context for this item.'));

    $fields['post_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Post type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 40);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Posted on'))
      ->setDefaultValue(0)
      ->setDescription(t('Posted date of the feed item, as a Unix timestamp.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDefaultValue(0)
      ->setDescription(t('Creation date of the feed item, as a Unix timestamp.'));

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

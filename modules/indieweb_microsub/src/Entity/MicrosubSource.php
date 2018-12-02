<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Microsub source entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_microsub_source",
 *   label = @Translation("Microsub source"),
 *   label_collection = @Translation("Sources"),
 *   handlers = {
 *     "storage" = "Drupal\indieweb_microsub\Entity\Storage\MicrosubSourceStorage",
 *     "storage_schema" = "Drupal\indieweb_microsub\Entity\Storage\MicrosubSourceStorageSchema",
 *     "list_builder" = "Drupal\indieweb_microsub\Entity\MicrosubSourceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\indieweb_microsub\Form\MicrosubSourceForm",
 *       "edit" = "Drupal\indieweb_microsub\Form\MicrosubSourceForm",
 *       "delete" = "Drupal\indieweb_microsub\Form\MicrosubSourceDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\indieweb_microsub\Routing\MicrosubSourceHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "microsub_source",
 *   admin_permission = "administer indieweb",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "url",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/indieweb/microsub/sources/add-source",
 *     "edit-form" = "/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/edit",
 *     "delete-form" = "/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/delete",
 *     "reset-next-fetch" = "/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/reset-next-fetch",
 *     "delete-items" = "/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/delete-items",
 *   }
 * )
 */
class MicrosubSource extends ContentEntityBase implements MicrosubSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChannelId() {
    return $this->get('channel_id')->target_id;
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
  public function getHash() {
    return $this->get('hash')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setHash($hash) {
    return $this->set('hash', $hash);
  }

  /**
   * {@inheritdoc}
   */
  public function getInterval() {
    return $this->get('fetch_interval')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setNextFetch($next_fetch = NULL) {
    if (!isset($next_fetch)) {
      $next_fetch = \Drupal::time()->getRequestTime() + $this->getInterval();
    }
    $this->set('fetch_next', $next_fetch);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setItemsInFeed($total) {
    return $this->set('items_in_feed', $total);
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsInFeed() {
    return (int) $this->get('items_in_feed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setKeepItemsInFeed($total) {
    return $this->set('items_to_keep', $total);
  }

  /**
   * {@inheritdoc}
   */
  public function getKeepItemsInFeed() {
    return (int) $this->get('items_to_keep')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextFetch() {
    return $this->get('fetch_next')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTries() {
    return $this->get('fetch_tries')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTries($value) {
    $this->set('fetch_tries', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getPostContext() {
    $return = [];
    $context = $this->get('post_context')->value;
    if (!empty($context)) {
      $values = @unserialize($context);
      if (is_array($values)) {
        foreach ($values as $key => $value) {
          if ($key === $value) {
            $return[] = $key;
          }
        }
      }
    }
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount() {
    return \Drupal::entityTypeManager()->getStorage('indieweb_microsub_source')->getItemCount($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function disableImageCache() {
    return (bool) $this->get('cache_image_disable')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    if (isset($this->original) && $this->original->getChannelId() != $this->getChannelId()) {
      $storage->updateItemsToNewChannel($this->id(), $this->getChannelId());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->removeAllItemsBySource($this->id());
    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('URL'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The username of the source.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\indieweb_microsub\Entity\MicrosubChannel::getCurrentUserId');

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status of the source'))
      ->setDefaultValue(1);

    $fields['channel_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Channel'))
      ->setDescription(t('The channel id of this source.'))
      ->setSetting('target_type', 'indieweb_microsub_channel');

    $fields['post_context'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Post context'))
      ->setSetting('max_length', 255);

    $fields['hash'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Hash'))
      ->setSetting('max_length', 32);

    $intervals = [900, 1800, 3600, 7200, 10800, 21600, 32400, 43200, 64800, 86400, 172800, 259200, 604800, 1209600, 2419200];
    $period = array_map([\Drupal::service('date.formatter'), 'formatInterval'], array_combine($intervals, $intervals));

    $fields['fetch_interval'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Update interval'))
      ->setDescription(t('The length of time between feed updates. Requires a correctly configured cron maintenance task.'))
      ->setDefaultValue(3600)
      ->setSetting('unsigned', TRUE)
      ->setSetting('allowed_values', $period)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);


    $fields['fetch_next'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('The next time the source will be fetched'))
      ->setDefaultValue(0);

    $fields['fetch_tries'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('How many times a fetch was performed without changes'))
      ->setDefaultValue(0);

    $fields['cache_image_disable'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Whether to disable the image cache or not for this source'))
      ->setDefaultValue(0);

    $fields['items_in_feed'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('How many items a feed contains'))
      ->setDefaultValue(0);

    $keep_values = [0, 20, 30, 40, 50, 60, 70, 80, 90, 100, 200, 250, 500, 1000];
    $keep = array_combine($keep_values, $keep_values);
    $fields['items_to_keep'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Items to keep'))
      ->setDescription(t('The number of items to keep when cleaning up feeds. Set to 0 to keep all.'))
      ->setDefaultValue(0)
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('allowed_values', $keep)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}

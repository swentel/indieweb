<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Microsub channel entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_microsub_channel",
 *   label = @Translation("Microsub channel"),
 *   label_collection = @Translation("Channels"),
 *   handlers = {
 *     "list_builder" = "Drupal\indieweb_microsub\Entity\MicrosubChannelListBuilder",
 *     "form" = {
 *       "add" = "Drupal\indieweb_microsub\Form\MicrosubChannelForm",
 *       "edit" = "Drupal\indieweb_microsub\Form\MicrosubChannelForm",
 *       "delete" = "Drupal\indieweb_microsub\Form\MicrosubChannelDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "microsub_channel",
 *   admin_permission = "administer indieweb",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "title",
 *     "weight" = "weight",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "uid" = "uid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/indieweb/microsub/channels/add-channel",
 *     "edit-form" = "/admin/config/services/indieweb/microsub/channels/{indieweb_microsub_channel}/edit",
 *     "delete-form" = "/admin/config/services/indieweb/microsub/channels/{indieweb_microsub_channel}/delete"
 *   }
 * )
 */
class MicrosubChannel extends ContentEntityBase implements MicrosubChannelInterface {

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSources() {
    $sources = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_source')
      ->getQuery()
      ->condition('channel_id', $this->id())
      ->execute();

    return $sources;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadCount() {
    return \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->getUnreadCountByChannel($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount() {
    return \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->getItemCountByChannel($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getPostTypesToExclude() {
    $return = [];
    $post_types = $this->get('exclude_post_type')->value;
    if (!empty($post_types)) {
      $values = @unserialize($post_types);
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
  public function getReadIndicator() {
    $value = $this->get('read_indicator')->value;

    // Default to count.
    if (empty($value)) {
      $value = self::readIndicatorCount;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $ids = $this->getSources();
    if ($ids) {
      $sources = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_source')->loadMultiple($ids);
      foreach ($sources as $source) {
        $source->delete();
      }
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Created by'))
      ->setDescription(t('The username of the channel.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback('Drupal\indieweb_microsub\Entity\MicrosubChannel::getCurrentUserId');

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status of the channel'))
      ->setDefaultValue(1);

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Weight of the channel'))
      ->setDefaultValue(0);

    $fields['exclude_post_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Exclude post type'))
      ->setSetting('max_length', 255);

    $fields['exclude_keyword'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Exclude keyword'))
      ->setSetting('max_length', 255);

    $fields['include_post_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Include post type'))
      ->setSetting('max_length', 255);

    $fields['include_keyword'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Include keyword'))
      ->setSetting('max_length', 255);

    $fields['read_indicator'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Read indicator'))
      ->setDefaultValue(0);

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}

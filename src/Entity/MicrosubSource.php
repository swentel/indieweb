<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the IndieWeb Microsub source entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_microsub_source",
 *   label = @Translation("Source"),
 *   label_collection = @Translation("Sources"),
 *   handlers = {
 *     "storage" = "Drupal\indieweb\Entity\Storage\MicrosubSourceStorage",
 *     "storage_schema" = "Drupal\indieweb\Entity\Storage\MicrosubSourceStorageSchema",
 *     "list_builder" = "Drupal\indieweb\MicrosubSourceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\indieweb\Form\MicrosubSourceForm",
 *       "edit" = "Drupal\indieweb\Form\MicrosubSourceForm",
 *       "delete" = "Drupal\indieweb\Form\MicrosubSourceDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
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
 *     "delete-form" = "/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/delete"
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
  public function getChannel() {
    return $this->get('channel_id')->target_id;
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
  public function setNextFetch() {
    $fetch_next = \Drupal::time()->getRequestTime() + $this->getInterval();
    $this->set('fetch_next', $fetch_next);
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
  public function delete() {

    \Drupal::database()
      ->delete('microsub_item')
      ->condition('source_id', $this->id())
      ->execute();

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
      ->setDefaultValueCallback('Drupal\indieweb\Entity\MicrosubChannel::getCurrentUserId');

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

    return $fields;
  }

}

<?php

namespace Drupal\indieweb_feed\Entity\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb_feed\Entity\FeedInterface;

/**
 * SQL Storage class for feed items.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for feed item entities.
 */
class FeedItemStorage extends SqlContentEntityStorage implements FeedItemStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function insertItemIntoFeed(EntityInterface $entity, FeedInterface $feed) {
    // Assume yes if isPublished does not exist.
    $published = method_exists($entity, 'isPublished') ? $entity->isPublished() : 1;

    $this->database
      ->merge('indieweb_feed_item')
      ->key('entity_id', $entity->id())
      ->key('entity_type_id', $entity->getEntityTypeId())
      ->key('feed_id', $feed->id())
      ->fields([
        'published' => (int) $published,
        'timestamp' => $entity->getCreatedTime(),
      ])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount($feed_id) {
    $query = $this->database->select('indieweb_feed_item', 't');
    $query->condition('feed_id', $feed_id);
    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function loadItemsByFeed($feed_id, $limit = 20) {
    $entities = [];

    $query = $this->database
      ->select('indieweb_feed_item', 'ifi')
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('ifi');
    $query->condition('feed_id', $feed_id);
    $query->condition('published', 1);
    $records = $query
      ->limit($limit)
      ->orderBy('timestamp', 'DESC')
      ->execute();

    foreach ($records as $record) {
      $entity = \Drupal::entityTypeManager()->getStorage($record->entity_type_id)->load($record->entity_id);
      if ($entity) {
        $entities[] = $entity;
      }
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItemsInFeed($feed_id) {
    $this->database
      ->delete('indieweb_feed_item')
      ->condition('feed_id', $feed_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function removeItemByEntity($entity_id, $entity_type_id) {
    $this->database->delete('indieweb_feed_item')
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();
  }

}

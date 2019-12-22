<?php

namespace Drupal\indieweb_feed\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\indieweb_feed\Entity\FeedInterface;

/**
 * Defines an interface for feed item entity storage classes.
 */
interface FeedItemStorageInterface extends ContentEntityStorageInterface {

  /**
   * Inserts an entity into a feed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\indieweb_feed\Entity\FeedInterface $feed
   */
  public function insertItemIntoFeed(EntityInterface $entity, FeedInterface $feed);

  /**
   * Returns the count of items in a feed.
   *
   * @param $feed_id
   *   The id of the feed
   *
   * @return int
   *   The count of items associated with a feed.
   */
  public function getItemCount($feed_id);

  /**
   * Loads entities filtered by a feed.
   *
   * @param int $feed_id
   *   The feed ID to filter by.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 20.
   * @param int $uid
   *   (optional) The user id.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of the entities.
   */
  public function loadItemsByFeed($feed_id, $limit = 20, $uid = 0);

  /**
   * Removes all items for a feed.
   *
   * @param $feed_id
   */
  public function deleteItemsInFeed($feed_id);

  /**
   * Remove an item by entity id and type.
   *
   * @param $entity_id
   *   The entity id.
   * @param $entity_type_id
   *   The entity type id.
   */
  public function removeItemByEntity($entity_id, $entity_type_id);

}

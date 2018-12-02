<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb_microsub\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for microsub item entity storage classes.
 */
interface MicrosubItemStorageInterface extends ContentEntityStorageInterface {

  /**
   * Returns the count of the items in a source.
   *
   * @param \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source
   *   The feed entity.
   *
   * @return int
   *   The count of items associated with a source.
   */
  public function getItemCount(MicrosubSourceInterface $source);

  /**
   * Loads microsub items filtered by a channel.
   *
   * @param int $channel_id
   *   The channel ID to filter by.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 20.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubItemInterface[]
   *   An array of the items.
   */
  public function loadByChannel($channel_id, $limit = 20);

  /**
   * Mark items as read.
   *
   * @param $channel_id
   *   The channel id
   * @param $id
   *   The item id
   */
  public function markItemsRead($channel_id, $id = NULL);

  /**
   * Remove an item.
   *
   * This does not delete the item from the storage, but sets the status to 0 so
   * that when on the next fetch of the feed the entry does not show up again.
   *
   * Items are effectively deleted later when the item drops out of the feed
   * limit.
   *
   * @param $id
   *   The item id.
   */
  public function removeItem($id);

  /**
   * Removes all items by source.
   *
   * @param $source_id
   *   The source id
   */
  public function removeAllItemsBySource($source_id);

  /**
   * Check if an item exists.
   *
   * @param $source_id
   *   The source id.
   * @param $guid
   *   The guid.
   *
   * @return integer
   */
  public function itemExists($source_id, $guid);

}

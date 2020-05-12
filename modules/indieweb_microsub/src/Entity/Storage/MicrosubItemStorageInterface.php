<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for microsub item entity storage classes.
 */
interface MicrosubItemStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get the unread items for a channel.
   *
   * @param int $channel_id
   *   The channel id.
   *
   * @return integer
   */
  public function getUnreadCountByChannel($channel_id);

  /**
   * Get the unread items for a source.
   *
   * @param int $source_id
   *   The source id.
   *
   * @return integer
   */
  public function getUnreadCountBySource($source_id);

  /**
   * Returns the count of the items in a source.
   *
   * @param $channel_id
   *   The channel id.
   *
   * @return int
   *   The count of items associated with a channel.
   */
  public function getItemCountByChannel($channel_id);

  /**
   * Returns the count of the items in a source.
   *
   * @param $source_id
   *   The source id.
   *
   * @return int
   *   The count of items associated with a source.
   */
  public function getItemCountBySource($source_id);

  /**
   * Loads microsub items filtered by a channel.
   *
   * @param int $channel_id
   *   The channel ID to filter by.
   * @param $is_read
   *   is_read or not.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 20.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubItemInterface[]
   *   An array of the items.
   */
  public function loadByChannel($channel_id, $is_read = NULL, $limit = 20);

  /**
   * Loads microsub items filtered by a source.
   *
   * @param int $source_id
   *   The source ID to filter by.
   * @param $is_read
   *   is_read or not.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 20.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubItemInterface[]
   *   An array of the items.
   */
  public function loadBySource($source_id, $is_read = NULL, $limit = 20);

  /**
   * Searches microsub items optionally filtered by a channel.
   *
   * @param string $search
   *   The search query.
   * @param int $channel_id
   *   The channel ID to filter by.
   * @param $is_read
   *   is_read or not.
   * @param int $limit
   *   (optional) The number of items to return. Defaults to 20.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubItemInterface[]
   *   An array of the items.
   */
  public function searchItems($search, $channel_id = NULL, $is_read = NULL, $limit = 20);

  /**
   * Change read status of one or more items.
   *
   * @param $channel_id
   *   The channel id
   * @param $status
   *   The status
   * @param $entries
   *   Array of single items.
   */
  public function changeReadStatus($channel_id, $status, $entries = NULL);

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
   * Purge the deleted items.
   */
  public function purgeDeletedItems();

  /**
   * Removes all items by source.
   *
   * @param $source_id
   *   The source id
   */
  public function removeAllItemsBySource($source_id);

  /**
   * Moves items from one channel to another.
   *
   * @param $entries
   * @param $channel_id
   */
  public function moveItem($entries, $channel_id);

    /**
   * Updates the items of this source to the new channel.
   *
   * @param $source_id
   * @param $channel_id
   */
  public function updateItemsToNewChannel($source_id, $channel_id);

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

  /**
   * Removes items by source and older than created time and is unread.
   *
   * @param $timestamp
   *   The microsub item created time
   * @param $channel_id
   *   The channel id
   * @param $source_id
   *   The source id
   * @param $guids
   *   The ids to not remove.
   */
  public function removeItemsBySourceAndChannelOlderThanTimestamp($timestamp, $channel_id, $source_id, $guids);

  /**
   * Get an id by range and source.
   *
   * @param $start
   * @param $channel_id
   * @param $source_id
   *
   * @return int $id|FALSE
   */
  public function getTimestampByRangeSourceAndChannel($start, $channel_id, $source_id);

}

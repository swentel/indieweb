<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for microsub item entity storage classes.
 */
interface MicrosubItemStorageInterface extends ContentEntityStorageInterface {

  /**
   * Returns the count of the items in a source.
   *
   * @param \Drupal\indieweb\Entity\MicrosubSourceInterface $source
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
   *   (optional) The number of items to return. Defaults to unlimited.
   *
   * @return \Drupal\indieweb\Entity\MicrosubItemInterface[]
   *   An array of the items.
   */
  public function loadByChannel($channel_id, $limit = NULL);

  /**
   * Mark items as read.
   *
   * @param $channel_id
   *   The channel id
   * @param $entry_id
   *   The entry id
   */
  public function markItemsRead($channel_id, $entry_id = NULL);

  /**
   * Remove an item.
   *
   * @param $entry_id
   */
  public function removeItem($entry_id);

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

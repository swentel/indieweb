<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines an interface for source entity storage classes.
 */
interface MicrosubSourceStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get the sources to refresh.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface[]
   *   An array of the sources.
   */
  public function getSourcesToRefresh();

  /**
   * Get the number of items.
   *
   * @param $source_id
   *
   * @return int
   */
  public function getItemCount($source_id);

  /**
   * Updates the items of this source to the new channel.
   *
   * @param $source_id
   * @param $channel_id
   *
   * @return mixed
   */
  public function updateItemsToNewChannel($source_id, $channel_id);

  /**
   * Delete all items for a source.
   *
   * @param $source_id
   *
   * @return int
   */
  public function deleteItems($source_id);

}

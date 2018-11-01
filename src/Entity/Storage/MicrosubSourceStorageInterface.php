<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for source entity storage classes.
 */
interface MicrosubSourceStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get the sources to refresh.
   *
   * @return \Drupal\indieweb\Entity\MicrosubSourceInterface[]
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
   * Delete all items for a source.
   *
   * @param $source_id
   *
   * @return int
   */
  public function deleteItems($source_id);

}

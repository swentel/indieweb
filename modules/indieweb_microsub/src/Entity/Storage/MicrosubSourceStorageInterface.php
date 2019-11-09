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
   * Get the sources which need to resubscribe to their WebSub hub.
   *
   * @return array $urls
   *   A collection of urls that need to resubscribe for WebSub.
   */
  public function getSourcesToResubscribe();
}

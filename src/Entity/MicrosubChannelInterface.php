<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining IndieWeb Microsub channel entities.
 */
interface MicrosubChannelInterface extends ContentEntityInterface {

  /**
   * Returns the status.
   *
   * @return integer
   */
  public function getStatus();

  /**
   * Returns the channel weight.
   *
   * @return integer
   */
  public function getWeight();

  /**
   * Get the sources.
   *
   * @return \Drupal\indieweb\Entity\MicrosubSourceInterface[]
   */
  public function getSources();

  /**
   * Get the post types to exclude.
   *
   * @return array
   */
  public function getPostTypesToExclude();

  /**
   * Get the count of unread items.
   *
   * @return int $count
   */
  public function getUnreadCount();


}

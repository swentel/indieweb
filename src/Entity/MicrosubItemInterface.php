<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an microsub item entity.
 */
interface MicrosubItemInterface extends ContentEntityInterface {

  /**
   * Returns the source id of microsub item.
   *
   * @return int
   *   The source id.
   */
  public function getSourceId();

  /**
   * Get the content for this item.
   *
   * @return string
   */
  public function getData();

  /**
   * Get the context content for this item.
   *
   * @return string
   */
  public function getContext();

  /**
   * Returns whether the item was read or not.
   *
   * @return bool
   */
  public function isRead();

}

<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface defining an microsub item entity.
 */
interface MicrosubItemInterface extends ContentEntityInterface {

  /**
   * Returns the channel id of microsub item.
   *
   * @return int
   *   The channel id.
   */
  public function getChannelId();

  /**
   * Returns the source id of microsub item.
   *
   * @return int
   *   The source id.
   */
  public function getSourceId();

  /**
   * Returns the value for the source id for a timeline.
   *
   * @param string $author
   * @param array $urls
   *
   * @return mixed
   */
  public function getSourceIdForTimeline($author, $urls = []);

  /**
   * Returns the source of microsub item.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface
   *   The source id.
   */
  public function getSource();

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

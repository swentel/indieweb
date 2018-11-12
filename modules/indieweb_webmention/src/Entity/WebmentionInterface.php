<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Webmention entities.
 *
 * @ingroup webmention
 */
interface WebmentionInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Gets the Webmention creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Webmention.
   */
  public function getCreatedTime();

  /**
   * Sets the Webmention creation timestamp.
   *
   * @param int $timestamp
   *   The Webmention creation timestamp.
   *
   * @return \Drupal\indieweb_webmention\Entity\WebmentionInterface
   *   The called Webmention entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the Webmention published status indicator.
   *
   * Unpublished Webmention are only visible to restricted users.
   *
   * @return bool
   *   TRUE if the Webmention is published.
   */
  public function isPublished();

  /**
   * Returns the source.
   *
   * @return string
   */
  public function getSource();

  /**
   * Returns the target.
   *
   * @return string
   */
  public function getTarget();

}

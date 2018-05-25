<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining IndieWeb Feed entities.
 */
interface FeedInterface extends ConfigEntityInterface {

  /**
   * Whether to expose an Atom feed.
   *
   * @return bool
   */
  public function exposeAtomFeed();

  /**
   * Whether to expose a jf2 feed.
   *
   * @return bool
   */
  public function exposeJf2Feed();

  /**
   * Whether to expose the rel="feed" header link.
   *
   * @return bool
   */
  public function exposeRelHeaderLink();

  /**
   * Whether to expose the application/atom+xml header link.
   *
   * @return bool
   */
  public function exposeAtomHeaderLink();

  /**
   * Whether to expose the application/jf2feed+json header link.
   *
   * @return bool
   */
  public function exposeJf2HeaderLink();

  /**
   * The feed path.
   *
   * @return string
   */
  public function getPath();

  /**
   * Set the feed path.
   *
   * @param string $path
   *
   * @return string
   */
  public function setPath($path);

  /**
   * The number of items per feed.
   *
   * @return int
   */
  public function getLimit();

  /**
   * The author information.
   *
   * @return string
   */
  public function getAuthor();

  /**
   * The bundles for this feed.
   *
   * @return array
   */
  public function getBundles();

  /**
   * The owner id.
   *
   * @return int
   */
  public function getOwnerId();

  /**
   * Returns the feed title.
   *
   * @return string
   */
  public function getFeedTitle();

}

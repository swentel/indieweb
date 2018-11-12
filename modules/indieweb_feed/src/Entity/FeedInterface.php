<?php

namespace Drupal\indieweb_feed\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining IndieWeb feed entities.
 */
interface FeedInterface extends ConfigEntityInterface {

  /**
   * Whether to exclude from indexing.
   *
   * @return bool
   */
  public function excludeIndexing();

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
  public function exposeRelLinkTag();

  /**
   * Whether to expose the application/atom+xml header link.
   *
   * @return bool
   */
  public function exposeAtomLinkTag();

  /**
   * Whether to use a hub.
   *
   * @return bool
   */
  public function useHub();

  /**
   * Returns the hub URL.
   *
   * @return string
   */
  public function getHubUrl();

  /**
   * Whether to expose the application/jf2feed+json header link.
   *
   * @return bool
   */
  public function exposeJf2LinkTag();

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

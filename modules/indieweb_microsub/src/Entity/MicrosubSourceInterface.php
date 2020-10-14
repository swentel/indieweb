<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\aggregator\FeedInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining IndieWeb Microsub source entities.
 */
interface MicrosubSourceInterface extends ContentEntityInterface {

  /**
   * Returns the feed name.
   *
   * @return string
   */
  public function getName();

  /**
   * Returns the status.
   *
   * @return integer
   */
  public function getStatus();

  /**
   * Returns the channel id.
   *
   * @return string
   */
  public function getChannelId();

  /**
   * Returns the channel.
   *
   * @return \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface
   */
  public function getChannel();

  /**
   * Get the number of unread items.
   *
   * @return int $count
   */
  public function getUnreadCount();

  /**
   * Returns the hash of the source.
   *
   * @return string
   */
  public function getHash();

  /**
   * Sets the hash of the source.
   *
   * @param string $hash
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setHash($hash);

  /**
   * Set number of items in feed.
   *
   * @param $total
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setItemsInFeed($total);

  /**
   * Return items in feed.
   *
   * @return integer
   */
  public function getItemsInFeed();

  /**
   * Set number of items to keep in feed.
   *
   * @param $total
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setKeepItemsInFeed($total);

  /**
   * Get the number of items in the feed to keep.
   *
   * @return integer
   */
  public function getKeepItemsInFeed();

  /**
   * Gets the interval.
   *
   * @return integer
   */
  public function getInterval();

  /**
   * Set time for next fetch.
   *
   * @param $next_fetch
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setNextFetch($next_fetch = NULL);

  /**
   * Get next fetch.
   *
   * @return int
   */
  public function getNextFetch();

  /**
   * Returns the changed timestamp.
   *
   * @return integer
   */
  public function getChanged();

  /**
   * Sets the changed timestamp.
   *
   * @param $changed
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setChanged($changed);

  /**
   * Get number of tries
   *
   * @return integer
   */
  public function getTries();

  /**
   * Set the value of tries.
   *
   * @param $value
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setTries($value);

  /**
   * Get the number of items.
   *
   * @return integer
   */
  public function getItemCount();

  /**
   * Get context.
   *
   * @return array().
   */
  public function getPostContext();

  /**
   * Whether to disable image cache or not.
   *
   * @return bool
   */
  public function disableImageCache();

  /**
   * Whether this feed uses WebSub or not.
   *
   * @return bool
   */
  public function usesWebSub();

  /**
   * Returns the entity tag HTTP response header, used for validating cache.
   *
   * @return string
   *   The entity tag HTTP response header.
   */
  public function getEtag();

  /**
   * Sets the entity tag HTTP response header, used for validating cache.
   *
   * @param string $etag
   *   A string containing the entity tag HTTP response header.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setEtag($etag);

  /**
   * Return when the feed was modified last time.
   *
   * @return int
   *   The timestamp of the last time the feed was modified.
   */
  public function getLastModified();

  /**
   * Sets the last modification of the feed.
   *
   * @param int $modified
   *   The timestamp when the feed was modified.
   *
   * @return $this
   *   The class instance that this method is called on.
   */
  public function setLastModified($modified);

}

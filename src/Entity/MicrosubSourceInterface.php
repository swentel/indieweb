<?php

namespace Drupal\indieweb\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for defining IndieWeb Microsub source entities.
 */
interface MicrosubSourceInterface extends ContentEntityInterface {

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
  public function getChannel();

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
   */
  public function setHash($hash);

  /**
   * Gets the interval.
   *
   * @return integer
   */
  public function getInterval();

  /**
   * Set time for next fetch.
   */
  public function setNextFetch();

  /**
   * Get next fetch.
   *
   * @return int
   */
  public function getNextFetch();

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

}

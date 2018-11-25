<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface SendInterface extends ContentEntityInterface {

  /**
   * Get the source.
   *
   * @return string
   */
  public function getSource();

  /**
   * Get the target.
   *
   * @return string
   */
  public function getTarget();

  /**
   * Get the source entity type id.
   *
   * @return string
   */
  public function getSourceEntityTypeId();

  /**
   * Get the source entity id.
   *
   * @return string
   */
  public function getSourceEntityId();

  /**
   * Get the created time.
   *
   * @return integer
   */
  public function getCreatedTime();

}
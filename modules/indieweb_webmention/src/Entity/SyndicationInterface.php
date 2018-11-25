<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface SyndicationInterface extends ContentEntityInterface {

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
   * Get the url.
   *
   * @return string
   */
  public function getUrl();

}
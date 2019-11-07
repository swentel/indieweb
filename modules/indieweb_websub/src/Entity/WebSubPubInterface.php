<?php

namespace Drupal\indieweb_websub\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface WebSubPubInterface extends ContentEntityInterface {

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

}

<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for IndieAuth token entity storage classes.
 */
interface IndieAuthAuthorizationCodeStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get an IndieAuth authorization code
   *
   * @param $code
   *
   * @return \Drupal\indieweb\Entity\IndieAuthAuthorizationCodeInterface
   */
  public function getIndieAuthAuthorizationCode($code);

}

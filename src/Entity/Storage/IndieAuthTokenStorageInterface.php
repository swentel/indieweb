<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for IndieAuth token entity storage classes.
 */
interface IndieAuthTokenStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get an IndieAuth token by access token.
   *
   * @param $access_token
   *
   * @return \Drupal\indieweb\Entity\IndieAuthToken
   */
  public function getIndieAuthTokenByAccessToken($access_token);

}

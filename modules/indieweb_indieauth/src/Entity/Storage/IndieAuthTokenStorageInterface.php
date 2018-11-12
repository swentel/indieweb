<?php

namespace Drupal\indieweb_indieauth\Entity\Storage;

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
   * @return \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface
   */
  public function getIndieAuthTokenByAccessToken($access_token);

}

<?php

namespace Drupal\indieweb_indieauth\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for IndieAuth tokens.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for IndieAuth token entities.
 */
class IndieAuthTokenStorage extends SqlContentEntityStorage implements IndieAuthTokenStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getIndieAuthTokenByAccessToken($access_token) {
    $tokens = $this->loadByProperties(['access_token' => $access_token]);
    return count($tokens) == 1 ? array_shift($tokens) : NULL;
  }

}

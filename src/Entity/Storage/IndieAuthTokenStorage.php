<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

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
    $indieAuthToken = count($tokens) == 1 ? array_shift($tokens) : NULL;
    return $indieAuthToken;
  }

}

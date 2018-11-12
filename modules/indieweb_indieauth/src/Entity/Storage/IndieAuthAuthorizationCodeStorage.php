<?php

namespace Drupal\indieweb_indieauth\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Controller class for IndieAuth tokens.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for IndieAuth token entities.
 */
class IndieAuthAuthorizationCodeStorage extends SqlContentEntityStorage implements IndieAuthAuthorizationCodeStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getIndieAuthAuthorizationCode($code) {
    $codes = $this->loadByProperties(['code' => $code]);
    $indieAuthAuthorizationCode = count($codes) == 1 ? array_shift($codes) : FALSE;
    return $indieAuthAuthorizationCode;
  }

}

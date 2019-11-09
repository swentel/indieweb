<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for microsub sources.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for microsub source entities.
 */
class MicrosubSourceStorage extends SqlContentEntityStorage implements MicrosubSourceStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourcesToRefresh() {

    $query = \Drupal::entityQuery('indieweb_microsub_source')
      ->condition('status', 1)
      ->condition('websub', 0)
      ->condition('fetch_next', \Drupal::time()->getRequestTime(), '<');

    return $this->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function getSourcesToResubscribe() {
    $query = $this->database->select('microsub_source', 'ms')
      ->fields('ms', ['url'])
      ->condition('status', 1)
      ->condition('websub', 1)
      ->condition('fetch_next', \Drupal::time()->getRequestTime(), '<');

    return $query->execute()->fetchAllKeyed(0, 0);
  }

}

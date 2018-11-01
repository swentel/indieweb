<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Controller class for microsub sources.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for microsub item entities.
 */
class MicrosubSourceStorage extends SqlContentEntityStorage implements MicrosubSourceStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourcesToRefresh() {

    $query = \Drupal::entityQuery('indieweb_microsub_source')
      ->condition('status', 1)
      ->condition('fetch_next', \Drupal::time()->getRequestTime(), '<');

    return $this->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount($source_id) {

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('source_id', $source_id)
      ->count();

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems($source_id) {
    \Drupal::database()->delete('microsub_item')->condition('source_id', $source_id)->execute();
  }

}

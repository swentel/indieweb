<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Controller class for microsub channels.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for microsub channel entities.
 */
class MicrosubChannelStorage extends SqlContentEntityStorage implements MicrosubChannelStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getUnreadCount($channel_id) {

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id)
      ->condition('is_read', 0)
      ->count();

    return $query->execute();
  }

}

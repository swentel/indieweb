<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

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

    $exclude = [];
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->load($channel_id);
    if ($channel) {
      $exclude = $channel->getPostTypesToExclude();
    }

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id)
      ->condition('status', 1)
      ->condition('is_read', 0);

    if ($exclude) {
      $query->condition('post_type', $exclude, 'NOT IN');
    }

    $query->count();

    return $query->execute();
  }

    /**
   * {@inheritdoc}
   */
  public function getItemCount($channel_id) {

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id)
      ->count();

    return $query->execute();
  }

}

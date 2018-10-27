<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

/**
 * Defines an interface for channel entity storage classes.
 */
interface MicrosubChannelStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get the unread items for a channel.
   *
   * @param int $channel_id
   *   The channel id.
   *
   * @return integer
   */
  public function getUnreadCount($channel_id);

}

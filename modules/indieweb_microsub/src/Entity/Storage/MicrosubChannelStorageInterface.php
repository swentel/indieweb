<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

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

  /**
   * Get the number of items for a channel.
   *
   * @param $channel_id
   *
   * @return integer
   */
  public function getItemCount($channel_id);

}

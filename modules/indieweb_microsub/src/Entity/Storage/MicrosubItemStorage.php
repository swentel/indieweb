<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for microsub items.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for microsub item entities.
 */
class MicrosubItemStorage extends SqlContentEntityStorage implements MicrosubItemStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getUnreadCountByChannel($channel_id) {
    $exclude = [];

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    if ($channel_id > 0) {
      $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->load($channel_id);
      if ($channel) {
        $exclude = $channel->getPostTypesToExclude();
      }
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
  public function getItemCountByChannel($channel_id) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id)
      ->count();

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCountBySource($source_id) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('source_id', $source_id)
      ->count();

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function changeReadStatus($channel_id, $status, $entries = NULL) {
    $is_read_condition = $status ? 0 : 1;
    $query = $this->database->update('microsub_item')
      ->fields(['is_read' => $status])
      ->condition('channel_id', $channel_id)
      ->condition('is_read', $is_read_condition);

    if (!empty($entries)) {
      $operator = '=';
      if (is_array($entries)) {
        $operator = 'IN';
      }
      $query->condition('id', $entries, $operator);
    }

    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem($id) {
    $this->database->update('microsub_item')
      ->fields(['status' => 0])
      ->condition('id', $id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function removeAllItemsBySource($source_id) {
    $this->database
      ->delete('microsub_item')
      ->condition('source_id', $source_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function updateItemsToNewChannel($source_id, $channel_id) {
    $this->database->update('microsub_item')
      ->fields(['channel_id' => $channel_id])
      ->condition('source_id', $source_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadByChannel($channel_id, $limit = 20) {
    $exclude = [];

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    if ($channel_id > 0) {
      $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->load($channel_id);
      if ($channel) {
        $exclude = $channel->getPostTypesToExclude();
      }
    }

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('status', 1)
      ->condition('channel_id', $channel_id);

    if ($exclude) {
      $query->condition('post_type', $exclude, 'NOT IN');
    }

    $query
      ->sort('created', 'DESC')
      ->sort('id', 'ASC');

    if (!empty($limit)) {
      $query->pager($limit);
    }

    return $this->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function loadBySource($source_id, $limit = 20) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('status', 1)
      ->condition('source_id', $source_id);

    $query
      ->sort('created', 'DESC')
      ->sort('id', 'ASC');

    if (!empty($limit)) {
      $query->pager($limit);
    }

    return $this->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function itemExists($source_id, $guid) {

    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('source_id', $source_id)
      ->condition('guid', $guid)
      ->count();

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestampByRangeAndSource($start, $source_id) {
    return $this->database
      ->select('microsub_item', 'm')
      ->fields('m', ['timestamp'])
      ->range(($start - 1), 1)
      ->condition('source_id', $source_id)
      ->orderBy('timestamp', 'DESC')
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function removeItemsBySourceOlderThanTimestamp($timestamp, $source_id, $guids) {
    $this->database
      ->delete('microsub_item')
      ->condition('timestamp', $timestamp, '<')
      ->condition('source_id', $source_id)
      ->condition('is_read', 1)
      ->condition('guid', $guids, 'NOT IN')
      ->execute();
  }

}

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
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id)
      ->condition('status', 1)
      ->condition('is_read', 0);
    $query->count();
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getUnreadCountBySource($source_id) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('source_id', $source_id)
      ->condition('status', 1)
      ->condition('is_read', 0);
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

    // Do not execute in case the channel id is global and there are no entries.
    if (empty($entries) && $channel_id === 'global') {
      return;
    }

    $is_read_condition = $status ? 0 : 1;
    $query = $this->database->update('microsub_item')
      ->fields(['is_read' => $status])
      ->condition('is_read', $is_read_condition);

    if (is_numeric($channel_id)) {
      $query->condition('channel_id', $channel_id);
    }

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
  public function purgeDeletedItems() {
    $this->database
      ->delete('microsub_item')
      ->condition('status', 0)
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
  public function moveItem($entries, $channel_id) {

    $operator = '=';
    if (is_array($entries)) {
      $operator = 'IN';
    }

    $this->database->update('microsub_item')
      ->fields(['channel_id' => $channel_id])
      ->condition('id', $entries, $operator)
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
  public function loadByChannel($channel_id, $is_read = NULL, $limit = 20) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('status', 1);

    // Filter on channel id.
    if (is_numeric($channel_id)) {
      $query->condition('channel_id', $channel_id);
    }

    // Is read or not.
    if (isset($is_read)) {
      $query->condition('is_read', (int) $is_read);
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
  public function loadBySource($source_id, $is_read = NULL, $limit = 20) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('status', 1)
      ->condition('source_id', $source_id);

    if (isset($is_read)) {
      $query->condition('is_read', (int) $is_read);
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
  public function searchItems($search, $channel_id = NULL, $is_read = NULL, $limit = 20) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('status', 1);

    if (is_numeric($channel_id)) {
      $query->condition('channel_id', $channel_id);
    }

    // Is read or not.
    if (isset($is_read)) {
      $query->condition('is_read', (int) $is_read);
    }

    // We're a bit limited by our current storage which stores json. Search in
    // guid and data, but we should always optimize this later.
    $group = $query
      ->orConditionGroup()
      ->condition('guid', '%' . $this->database->escapeLike($search) . '%', 'LIKE')
      ->condition('data', '%' . $this->database->escapeLike($search) . '%', 'LIKE');
    $query->condition($group);

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
  public function getTimestampByRangeSourceAndChannel($start, $channel_id, $source_id) {
    return $this->database
      ->select('microsub_item', 'm')
      ->fields('m', ['timestamp'])
      ->range(($start - 1), 1)
      ->condition('channel_id', $channel_id)
      ->condition('source_id', $source_id)
      ->orderBy('timestamp', 'DESC')
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function removeItemsBySourceAndChannelOlderThanTimestamp($timestamp, $channel_id, $source_id, $guids) {
    $this->database
      ->delete('microsub_item')
      ->condition('timestamp', $timestamp, '<')
      ->condition('channel_id', $channel_id)
      ->condition('source_id', $source_id)
      ->condition('is_read', 1)
      ->condition('guid', $guids, 'NOT IN')
      ->execute();
  }

}

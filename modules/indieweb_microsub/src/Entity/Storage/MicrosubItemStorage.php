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
  public function markItemsRead($channel_id, $id = NULL) {
    $this->database->update('microsub_item')
      ->fields(['is_read' => 1])
      ->condition('channel_id', $channel_id)
      ->condition('is_read', 0)
      ->execute();
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
  public function removeItemsBySourceOlderThanId($id, $source_id) {
    $this->database
      ->delete('microsub_item')
      ->condition('id', $id, '<')
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

    return $this->executeFeedItemQuery($query, $limit);
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
  public function getIdByRangeAndSource($start, $source_id) {
    return $this->database
      ->select('microsub_item', 'm')
      ->fields('m', ['id'])
      ->range($start, 1)
      ->condition('source_id', $source_id)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchField();
  }

  /**
   * Helper method to execute an item query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute.
   * @param int $limit
   *   (optional) The number of items to return.
   *
   * @return \Drupal\aggregator\ItemInterface[]
   *   An array of the microsub items.
   */
  protected function executeFeedItemQuery(QueryInterface $query, $limit) {
    $query
      ->sort('created', 'DESC')
      ->sort('id', 'DESC');
    if (!empty($limit)) {
      $query->pager($limit);
    }

    return $this->loadMultiple($query->execute());
  }

}

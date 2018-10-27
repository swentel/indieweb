<?php

namespace Drupal\indieweb\Entity\Storage;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\indieweb\Entity\MicrosubSourceInterface;

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
  public function markItemsRead($channel_id, $entry_id = NULL) {
    $this->database->update('microsub_item')
      ->fields(['is_read' => 1])
      ->condition('channel_id', $channel_id)
      ->condition('is_read', 0)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem($entry_id) {
    $this->database->delete('microsub_item')
      ->condition('mid', $entry_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemCount(MicrosubSourceInterface $source) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('source_id', $source->id())
      ->count();

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function loadByChannel($channel_id, $limit = 20) {
    $query = \Drupal::entityQuery('indieweb_microsub_item')
      ->condition('channel_id', $channel_id);
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
   * Helper method to execute an item query.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query to execute.
   * @param int $start
   *   Start and end.
   * @param int $limit
   *   (optional) The number of items to return.
   *
   * @return \Drupal\aggregator\ItemInterface[]
   *   An array of the microsub items.
   */
  protected function executeFeedItemQuery(QueryInterface $query, $limit) {
    $query
      ->sort('created', 'DESC')
      ->sort('mid', 'DESC');
    if (!empty($limit)) {
      $query->pager($limit);
    }

    return $this->loadMultiple($query->execute());
  }

}

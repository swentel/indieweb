<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for syndication.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for syndication entities.
 */
class SyndicationStorage extends SqlContentEntityStorage implements SyndicationStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getSyndicationUrls($entity_id, $entity_type_id) {
    $syndications = [];
    $records = $this->database->select('webmention_syndication', 's')
      ->fields('s', ['url'])
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();
    foreach ($records as $record) {
      $syndications[] = $record->url;
    }
    return $syndications;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteByIdAndType($entity_id, $entity_type_id) {
    $this->database
      ->delete('webmention_syndication')
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function checkIdenticalSyndication($like) {
    return $this->database->query("SELECT count(url) as count FROM {webmention_syndication} WHERE url LIKE :match_url", [':match_url' => '%/' . Database::getConnection()->escapeLike($like)])->fetchField();
  }

}

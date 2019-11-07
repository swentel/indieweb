<?php

namespace Drupal\indieweb_websub\Entity\Storage;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for WebSubPub.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for websubpub entities.
 */
class WebSubPubStorage extends SqlContentEntityStorage implements WebSubPubStorageInterface {

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

}

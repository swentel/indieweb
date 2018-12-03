<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for send webmention.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for send webmention entities.
 */
class SendStorage extends SqlContentEntityStorage implements SendStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function deleteByIdAndType($entity_id, $entity_type_id) {
    $this->database
      ->delete('webmention_send')
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();
  }


}

<?php

namespace Drupal\indieweb_websub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

interface WebSubPubStorageInterface extends ContentEntityStorageInterface {

  /**
   * Delete WebSubPub by id and type.
   *
   * @param $entity_id
   * @param $entity_type_id
   */
  public function deleteByIdAndType($entity_id, $entity_type_id);

}

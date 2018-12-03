<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

interface SendStorageInterface extends ContentEntityStorageInterface {

  /**
   * Delete syndication by id and type.
   *
   * @param $entity_id
   * @param $entity_type_id
   *
   * @return mixed
   */
  public function deleteByIdAndType($entity_id, $entity_type_id);

}
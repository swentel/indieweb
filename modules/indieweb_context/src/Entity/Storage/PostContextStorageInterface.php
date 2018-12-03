<?php

namespace Drupal\indieweb_context\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

interface PostContextStorageInterface extends ContentEntityStorageInterface {

  /**
   * Save content post context.
   *
   * @param $entity_id
   * @param $entity_type_id
   * @param $url
   * @param $reference
   */
  public function saveContentContext($entity_id, $entity_type_id, $url, $reference);

  /**
   * Save microsub post context.
   *
   * @param $id
   * @param $reference
   *
   * @return mixed
   */
  public function saveMicrosubContext($id, $reference);

  /**
   * Delete post context by id and type.
   *
   * @param $entity_id
   * @param $entity_type_id
   */
  public function deleteByIdAndType($entity_id, $entity_type_id);

  /**
   * Get content post contexts.
   *
   * @param $entity_id
   * @param $entity_type_id
   *
   * @return array $post_contexts.
   */
  public function getContentPostContexts($entity_id, $entity_type_id);

}
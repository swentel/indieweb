<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

use Drupal\Core\Entity\ContentEntityStorageInterface;

interface SyndicationStorageInterface extends ContentEntityStorageInterface {

  /**
   * Get syndication URL's.
   *
   * @param $entity_id
   * @param $entity_type_id
   *
   * @return array $syndications
   */
  public function getSyndicationUrls($entity_id, $entity_type_id);

  /**
   * Delete syndication by id and type.
   *
   * @param $entity_id
   * @param $entity_type_id
   */
  public function deleteByIdAndType($entity_id, $entity_type_id);

  /**
   * Check for an identical syndication.
   *
   * @param $like
   *
   * @return bool
   */
  public function checkIdenticalSyndication($like);

}
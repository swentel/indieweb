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
   * @return mixed
   */
  public function getSyndicationUrls($entity_id, $entity_type_id);

}
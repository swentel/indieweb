<?php

namespace Drupal\indieweb_microsub\Entity\Storage;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the microsub source schema handler.
 */
class MicrosubSourceStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['microsub_source']['indexes'] += array(
      'fetch_next' => array('fetch_next'),
    );

    return $schema;
  }

}

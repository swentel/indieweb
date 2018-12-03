<?php

namespace Drupal\indieweb_context\Entity\Storage;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for post context.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for post context entities.
 */
class PostContextStorage extends SqlContentEntityStorage implements PostContextStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function saveContentContext($entity_id, $entity_type_id, $url, $reference) {
    $this->database
      ->merge('indieweb_post_context')
      ->key('entity_id', $entity_id)
      ->key('entity_type_id', $entity_type_id)
      ->key('url', $url)
      ->fields(['content' => json_encode($reference)])
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function saveMicrosubContext($id, $reference) {
    $this->database
      ->update('microsub_item')
      ->condition('id', $id)
      ->fields(['post_context' => json_encode($reference)])
      ->execute();

  }

  /**
   * {@inheritdoc}
   */
  public function deleteByIdAndType($entity_id, $entity_type_id) {
    $this->database
      ->delete('indieweb_post_context')
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();

  }

  /**
   * {@inheritdoc}
   */
  public function getContentPostContexts($entity_id, $entity_type_id) {
    $contexts = [];

    $records = $this->database
      ->select('indieweb_post_context', 'pc')
      ->fields('pc', ['url', 'content'])
      ->condition('entity_id', $entity_id)
      ->condition('entity_type_id', $entity_type_id)
      ->execute();
    foreach ($records as $record) {
      $content = (array) json_decode($record->content);
      if (isset($content['post-type'])) {
        $contexts[] = [
          'url' => $record->url,
          'content' => $content,
        ];
      }
    }

    return $contexts;
  }

}
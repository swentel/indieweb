<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

use Drupal\Core\Database\Query\Condition;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Controller class for webmention.
 *
 * This extends the Drupal\Core\Entity\Sql\SqlContentEntityStorage class, adding
 * required special handling for webmention entities.
 */
class WebmentionStorage extends SqlContentEntityStorage implements WebmentionStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getWebmentions($types, $target, $number_of_posts = 0, $sort_field = 'id', $sort_direction = 'DESC') {
    $query = $this->database
      ->select('webmention_received', 'w')
      ->fields('w')
      ->condition('status', 1)
      ->condition('property', $types, 'IN');

    $or = new Condition('OR');
    $or->condition('target', $target);
    $or->condition('parent_target', $target);
    $query->condition($or);

    $query->orderBy($sort_field, $sort_direction);

    if ($number_of_posts) {
      $query->range(0, $number_of_posts);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldOptions($field) {
    return $this->database->select('webmention_received', 'w')
      ->fields('w', [$field])
      ->distinct()
      ->execute()
      ->fetchAllKeyed(0, 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getWebmentionByTargetPropertyAndUid($target, $property, $uid) {
    $args = [
      ':target' => $target,
      ':property' => $property,
      ':uid' => $uid,
    ];
    return $this->database->query('SELECT id, rsvp FROM {webmention_received} WHERE target = :target AND property = :property AND uid = :uid', $args)->fetchObject();
  }

  /**
   * {@inheritdoc}
   */
  public function checkIdenticalWebmention($source, $target, $property) {
    return $this->database->query("SELECT id FROM {webmention_received} WHERE source = :source AND target = :target AND property = :property ORDER by id DESC limit 1", [':source' => $source, ':target' => $target, ':property' => $property])->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function updateRSVP($value, $id) {
    $this->database->update('webmention_received')
      ->fields(['rsvp' => $value])
      ->condition('id', $id)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getCommentIdByWebmentionId($field_name, $id) {
    $cid = FALSE;

    $table_name = 'comment__' . $field_name;
    if ($this->database->schema()->tableExists($table_name)) {
      $cid = $this->database
        ->select($table_name, 'a')
        ->fields('a', ['entity_id'])
        ->condition($field_name . '_target_id', $id)
        ->execute()
        ->fetchField();
    }

    return $cid;
  }


}

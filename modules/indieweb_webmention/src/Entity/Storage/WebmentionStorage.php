<?php

namespace Drupal\indieweb_webmention\Entity\Storage;

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
  public function getWebmentions($types, $target, $number_of_posts = 0) {
    $query = $this->database
      ->select('webmention_received', 'w')
      ->fields('w')
      ->condition('status', 1)
      ->condition('target', $target)
      ->condition('property', $types, 'IN');

    $query->orderBy('id', 'DESC');

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

}

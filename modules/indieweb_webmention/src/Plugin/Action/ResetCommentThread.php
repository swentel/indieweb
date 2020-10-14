<?php

namespace Drupal\indieweb_webmention\Plugin\Action;

use Drupal\Component\Utility\Number;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Reset a comments thread for a node.
 *
 * @Action(
 *   id = "comment_reset_thread",
 *   label = @Translation("Reset comment thread"),
 *   type = "node"
 * )
 */
class ResetCommentThread extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {

    if (!\Drupal::moduleHandler()->moduleExists('comment')) {
      return;
    }

    $eq = \Drupal::entityQuery('comment');
    $eq->condition('entity_id', $object->id());
    $eq->condition('entity_type', 'node');
    $eq->sort('created', 'ASC');
    $res = $eq->execute();
    if (!empty($res)) {
      $this->resetThreading($res);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\node\NodeInterface $object */
    return $object->access('update', $account, $return_as_object);
  }

  /**
   * Reset threading.
   *
   * @param $res
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function resetThreading($res) {
    $storage = \Drupal::entityTypeManager()->getStorage('comment');
    $storage->resetCache($res);

    // Set all threads to empty.
    \Drupal::database()
      ->update('comment_field_data')
      ->fields(['thread' => ''])
      ->condition('cid', $res, 'IN')
      ->execute();

    foreach ($res as $cid) {

      /** @var \Drupal\comment\CommentInterface $comment */
      $comment = $storage->load($cid);

      if (!$comment->hasParentComment()) {
        // This is a comment with no parent comment (depth 0): we start
        // by retrieving the maximum thread level.
        $max = $storage->getMaxThread($comment);
        // Strip the "/" from the end of the thread.
        $max = rtrim($max, '/');
        // We need to get the value at the correct depth.
        $parts = explode('.', $max);
        $n = Number::alphadecimalToInt($parts[0]);
        $prefix = '';
      }
      else {
        // This is a comment with a parent comment, so increase the part of
        // the thread value at the proper depth.

        // Get the parent comment:
        $parent = $comment->getParentComment();
        // Strip the "/" from the end of the parent thread.
        $parent->setThread((string) rtrim((string) $parent->getThread(), '/'));
        $prefix = $parent->getThread() . '.';
        // Get the max value in *this* thread.
        $max = $storage->getMaxThreadPerThread($comment);

        if ($max == '') {
          // First child of this parent. As the other two cases do an
          // increment of the thread number before creating the thread
          // string set this to -1 so it requires an increment too.
          $n = -1;
        }
        else {
          // Strip the "/" at the end of the thread.
          $max = rtrim($max, '/');
          // Get the value at the correct depth.
          $parts = explode('.', $max);
          $parent_depth = count(explode('.', $parent->getThread()));
          $n = Number::alphadecimalToInt($parts[$parent_depth]);
        }
      }

      // Finally, build the thread field for this new comment. To avoid
      // race conditions, get a lock on the thread. If another process already
      // has the lock, just move to the next integer.
      $thread = $prefix . Number::intToAlphadecimal(++$n) . '/';

      $comment->setThread($thread);
      $comment->save();
    }
  }

}

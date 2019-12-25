<?php

namespace Drupal\indieweb_webmention\Controller;

use Drupal\comment\CommentInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the comment entity in indieweb context.
 *
 * @see \Drupal\comment\Entity\Comment.
 */
class CommentController extends ControllerBase {

  /**
   * Renders a comment on a dedicated page.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   A comment entity.
   *
   * @return array $build
   *   The comment build render array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function commentPermalink(CommentInterface $comment) {
    if ($entity = $comment->getCommentedEntity()) {

      // Check access permissions for the entity.
      if (!$entity->access('view')) {
        throw new AccessDeniedHttpException();
      }

      // Render in default when the owner is anonymous.
      $view_mode = 'indieweb_microformat';
      if (empty($comment->getOwnerId())) {
        $view_mode = 'full';
      }
      $build = $this->entityTypeManager()->getViewBuilder('comment')->view($comment, $view_mode);

      // Set canonical and shortlink to default comment permalink.
      $build['#attached']['html_head_link'][] = [
        [
          'rel' => 'shortlink',
          'href' => $comment->toUrl()->setOption('alias', TRUE)->toString(),
        ],
        TRUE,
      ];
      $build['#attached']['html_head_link'][] = [
        [
          'rel' => 'canonical',
          'href' => $comment->toUrl()->toString(),
        ],
        TRUE,
      ];

      return $build;

    }
    throw new NotFoundHttpException();
  }

  /**
   * The _title_callback for the page that renders the comment permalink.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *   The current comment.
   *
   * @return string
   *   The translated comment subject.
   */
  public function commentPermalinkTitle(CommentInterface $comment) {
    return \Drupal::service('entity.repository')->getTranslationFromContext($comment)->label();
  }

}

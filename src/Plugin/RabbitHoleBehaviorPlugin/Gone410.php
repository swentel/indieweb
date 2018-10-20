<?php

namespace Drupal\indieweb\Plugin\RabbitHoleBehaviorPlugin;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\Entity;
use Drupal\rabbit_hole\Plugin\RabbitHoleBehaviorPluginBase;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * Denies access to a page, by sending a 410 response.
 *
 * @RabbitHoleBehaviorPlugin(
 *   id = "indieweb_410_gone",
 *   label = @Translation("Gone")
 * )
 */
class Gone410 extends RabbitHoleBehaviorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function performAction(Entity $entity, Response $current_response = NULL) {
    throw new GoneHttpException('This content is gone');
  }

}

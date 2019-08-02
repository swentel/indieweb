<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Syndication entity.
 *
 * @see \Drupal\indieweb_webmention\Entity\SyndicationInterface.
 */
class SyndicationAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\indieweb_webmention\Entity\SyndicationInterface $entity */
    switch ($operation) {
      case 'view':
        return AccessResult::allowedIfHasPermission($account, 'view syndication entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer syndication entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

}

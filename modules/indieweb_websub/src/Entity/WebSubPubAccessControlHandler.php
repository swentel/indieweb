<?php

namespace Drupal\indieweb_websub\Entity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the WebSubPub entity.
 *
 * @see \Drupal\indieweb_webmention\Entity\SyndicationInterface.
 */
class WebSubPubAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\indieweb_websub\Entity\WebSubPubInterface $entity */
    switch ($operation) {
      case 'view':
      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'administer websubpub entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

}

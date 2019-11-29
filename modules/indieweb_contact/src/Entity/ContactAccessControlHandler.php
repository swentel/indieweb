<?php

namespace Drupal\indieweb_contact\Entity;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Contact entity.
 *
 * @see \Drupal\indieweb_contact\Entity\ContactInterface.
 */
class ContactAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\indieweb_contact\Entity\ContactInterface $entity */
    switch ($operation) {
      case 'view':
        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished indieweb contact entities');
        }
        return AccessResult::allowedIfHasPermission($account, 'view published indieweb contact entities');

      case 'update':
        return AccessResult::allowedIfHasPermission($account, 'edit indieweb contact entities');

      case 'delete':
        return AccessResult::allowedIfHasPermission($account, 'delete indieweb contact entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add indieweb contact entities');
  }

}

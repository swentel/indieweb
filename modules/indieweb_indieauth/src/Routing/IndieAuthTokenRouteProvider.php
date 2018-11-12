<?php

namespace Drupal\indieweb_indieauth\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for IndieAuth token entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 */
class IndieAuthTokenRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/admin/config/services/indieweb/indieauth/tokens/{indieweb_indieauth_token}/change-status'))
      ->addDefaults([
        '_controller' => '\Drupal\indieweb_indieauth\Controller\IndieAuthController::changeStatus',
        '_title' => 'Change token status',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_indieauth_token', '\S+');
    $collection->add('entity.indieweb_indieauth_token.change_status', $route);

    return $collection;
  }

}

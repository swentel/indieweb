<?php

namespace Drupal\indieweb_microsub\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Microsub entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 */
class MicrosubSourceHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/reset-next-fetch'))
      ->addDefaults([
        '_controller' => '\Drupal\indieweb_microsub\Controller\MicrosubController::resetNextFetch',
        '_title' => 'Reset next fetch',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.reset_next_fetch', $route);

    $route = (new Route('/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/delete-items'))
      ->addDefaults([
        '_form' => '\Drupal\indieweb_microsub\Form\MicrosubSourceItemsDeleteForm',
        '_title' => 'Delete items',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.delete_items', $route);

    $route = (new Route('/admin/config/services/indieweb/microsub/delete-notifications'))
      ->addDefaults([
        '_form' => '\Drupal\indieweb_microsub\Form\MicrosubNotificationsDeleteForm',
        '_title' => 'Delete notifications',
      ])
      ->setRequirement('_permission', 'administer indieweb');
    $collection->add('entity.indieweb_microsub.delete_notifications', $route);

    return $collection;
  }

}

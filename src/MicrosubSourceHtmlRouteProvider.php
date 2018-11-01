<?php

namespace Drupal\indieweb;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Microsub source entities.
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
        '_controller' => '\Drupal\indieweb\Controller\MicrosubController::resetNextFetch',
        '_title' => 'Reset next fetch',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.reset_next_fetch', $route);

    $route = (new Route('/admin/config/services/indieweb/microsub/sources/{indieweb_microsub_source}/delete-items'))
      ->addDefaults([
        '_form' => '\Drupal\indieweb\Form\MicrosubSourceItemsDeleteForm',
        '_title' => 'Delete items',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.delete_items', $route);

    return $collection;
  }

}

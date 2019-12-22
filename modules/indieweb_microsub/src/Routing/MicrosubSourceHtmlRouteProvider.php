<?php

namespace Drupal\indieweb_microsub\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Microsub entities.
 *
 * @see \Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider
 */
class MicrosubSourceHtmlRouteProvider extends DefaultHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/user/{user}/sources/{indieweb_microsub_source}/reset-next-fetch'))
      ->addDefaults([
        '_controller' => '\Drupal\indieweb_microsub\Controller\MicrosubController::resetNextFetch',
        '_title' => 'Reset next fetch',
      ])
      ->setRequirement('_permission', 'manage channels and sources')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.reset_next_fetch', $route);

    $route = (new Route('/user/{user}/microsub/sources/{indieweb_microsub_source}/delete-items'))
      ->addDefaults([
        '_form' => '\Drupal\indieweb_microsub\Form\MicrosubSourceItemsDeleteForm',
        '_title' => 'Delete items',
      ])
      ->setRequirement('_permission', 'manage channels and sources')
      ->setRequirement('indieweb_microsub_source', '\S+');
    $collection->add('entity.indieweb_microsub_source.delete_items', $route);

    $route = (new Route('/user/{user}/microsub/sources/delete-notifications'))
      ->addDefaults([
        '_form' => '\Drupal\indieweb_microsub\Form\MicrosubNotificationsDeleteForm',
        '_title' => 'Delete notifications',
      ])
      ->setRequirement('_permission', 'manage channels and sources');
    $collection->add('entity.indieweb_microsub.delete_notifications', $route);

    return $collection;
  }

}

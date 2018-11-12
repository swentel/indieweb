<?php

namespace Drupal\indieweb_feed\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Feed entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 */
class FeedHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/admin/config/services/indieweb/feeds/{indieweb_feed}/update-items'))
      ->addDefaults([
        '_controller' => '\Drupal\indieweb_feed\Controller\FeedController::updateItems',
        '_title' => 'Update items',
      ])
      ->setRequirement('_permission', 'administer indieweb')
      ->setRequirement('indieweb_feed', '\S+');
    $collection->add('entity.indieweb_feed.update_items', $route);

    return $collection;
  }

}

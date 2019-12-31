<?php

namespace Drupal\indieweb_webmention\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Symfony\Component\Routing\Route;

/**
 * Provides routes for Webmention entities.
 *
 * @see \Drupal\Core\Entity\Routing\AdminHtmlRouteProvider
 */
class WebmentionHtmlRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  public function getRoutes(EntityTypeInterface $entity_type) {
    $collection = parent::getRoutes($entity_type);

    $route = (new Route('/admin/content/webmention/{indieweb_webmention}/reprocess'))
      ->addDefaults([
        '_controller' => '\Drupal\indieweb_webmention\Controller\WebmentionController::reprocess',
        '_title' => 'Update items',
      ])
      ->setRequirement('_permission', 'reprocess webmention')
      ->setRequirement('indieweb_webmention', '\S+');
    $collection->add('entity.indieweb_webmention.reprocess', $route);

    return $collection;
  }

}

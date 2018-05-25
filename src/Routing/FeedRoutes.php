<?php

namespace Drupal\indieweb\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines a route subscriber to register a url for serving feed pages.
 */
class FeedRoutes implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new SearchApiRoutes object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }


  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function routes() {
    $routes = array();

    /** @var \Drupal\indieweb\Entity\FeedInterface $feed */
    foreach ($this->entityTypeManager->getStorage('indieweb_feed')->loadMultiple() as $feed) {

      $path = $feed->getPath();
      $args = [
        '_controller' => 'Drupal\indieweb\Controller\FeedController::feedMicroformat',
        '_title' => $feed->label(),
        'indieweb_feed' => $feed->id(),
      ];

      $routes['indieweb.feeds.microformat.' . $feed->id()] = new Route(
        $path,
        $args,
        array(
          '_permission' => 'access content',
        )
      );

      if ($feed->exposeAtomFeed()) {
        $atom_path = str_replace('/', '-', $path) . '.xml';
        $args['_controller'] = 'Drupal\indieweb\Controller\FeedController::feedAtom';
        $routes['indieweb.feeds.atom.' . $feed->id()] = new Route(
          $atom_path,
          $args,
          array(
            '_permission' => 'access content',
          )
        );
      }

      if ($feed->exposeJf2Feed()) {
        $jf2_path = str_replace('/', '-', $path) . '.jf2';
        $args['_controller'] = 'Drupal\indieweb\Controller\FeedController::feedJf2';
        $routes['indieweb.feeds.jf2.' . $feed->id()] = new Route(
          $jf2_path,
          $args,
          array(
            '_permission' => 'access content',
          )
        );
      }
    }

    return $routes;
  }

}

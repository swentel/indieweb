<?php

namespace Drupal\indieweb_microsub\Plugin\Menu\LocalAction;

use Drupal\Core\Menu\LocalActionDefault;
use Drupal\Core\Routing\RouteMatchInterface;

class SourceAddAction extends LocalActionDefault {

  /**
   * {@inheritdoc}
   */
  public function getOptions(RouteMatchInterface $route_match) {

    if ($route_match->getRouteName() == 'indieweb.admin.microsub_sources') {
      $channel = $route_match->getParameter('indieweb_microsub_channel');
      if ($channel) {
        return ['query' => ['channel' => $channel->id()]];
      }
    }

    return parent::getOptions($route_match);
  }

}

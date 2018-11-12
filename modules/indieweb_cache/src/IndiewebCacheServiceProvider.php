<?php

namespace Drupal\indieweb_cache;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class IndiewebCacheServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Swap out the Media cache service.
    if ($container->has('indieweb.media_cache.client')) {
      $definition = $container->getDefinition('indieweb.media_cache.client');
      $definition->setClass('Drupal\indieweb_cache\MediaCacheClient\MediaCacheClient');
    }
  }

}

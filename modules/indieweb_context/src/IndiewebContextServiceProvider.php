<?php

namespace Drupal\indieweb_context;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class IndiewebContextServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Swap out the Post context service.
    if ($container->has('indieweb.post_context.client')) {
      $definition = $container->getDefinition('indieweb.post_context.client');
      $definition->setClass('Drupal\indieweb_context\PostContextClient\PostContextClient');
    }
  }

}

<?php

namespace Drupal\indieweb_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class IndiewebTestServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    // Swap out the WebmentionClient service.
    if ($container->has('indieweb.webmention.client')) {
      $definition = $container->getDefinition('indieweb.webmention.client');
      $definition->setClass('Drupal\indieweb_test\WebmentionClient\WebmentionClientTest');
    }

    // Swap out the MicrosubClient service.
    if ($container->has('indieweb.microsub.client')) {
      $definition = $container->getDefinition('indieweb.microsub.client');
      $definition->setClass('Drupal\indieweb_test\MicrosubClient\MicrosubClientTest');
    }
  }

}

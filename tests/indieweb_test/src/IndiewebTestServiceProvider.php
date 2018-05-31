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
    $definition = $container->getDefinition('indieweb.webmention.client');
    $definition->setClass('Drupal\indieweb_test\WebmentionClient\WebmentionClientTest');
  }

}

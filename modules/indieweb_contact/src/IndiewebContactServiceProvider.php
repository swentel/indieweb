<?php

namespace Drupal\indieweb_contact;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

class IndiewebContactServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Swap out the Contacts service.
    if ($container->has('indieweb.contact.client')) {
      $definition = $container->getDefinition('indieweb.contact.client');
      $definition->setClass('Drupal\indieweb_contact\ContactClient\ContactClient');
    }
  }

}

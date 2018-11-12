<?php

namespace Drupal\indieweb_microsub\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb Microsub Drush commands file.
 */
class MicrosubCommands extends DrushCommands {

  /**
   * Fetch microsub items.
   *
   * @command indieweb:microsub-fetch-items
   * @aliases imfi,indieweb-microsub-fetch-items
   */
  public function microsubFetchItems() {
    if (\Drupal::config('indieweb_microsub.settings')->get('microsub_internal') &&
      \Drupal::config('indieweb_microsub.settings')->get('microsub_internal_handler') == 'drush') {
      \Drupal::service('indieweb.microsub.client')->fetchItems();
    }
  }

}

<?php

namespace Drupal\indieweb_websub\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb webmention Drush commands file.
 */
class WebSubCommands extends DrushCommands {

  /**
   * WebSub publish.
   *
   * @command indieweb:websub-publish
   * @aliases iwp,indieweb-websub-publish
   */
  public function webSubPublish() {
    if (\Drupal::config('indieweb_websub.settings')->get('send_pub_handler') == 'drush') {
      \Drupal::service('indieweb.websub.client')->handleQueue();
    }
  }

  /**
   * WebSub resubscribe
   *
   * @command indieweb:websub-resubscribe
   * @aliases iwrs,indieweb-websub-resubscribe
   */
  public function webSubResubscribe() {
    if (\Drupal::config('indieweb_websub.settings')->get('resubscribe_handler') == 'drush') {
      \Drupal::service('indieweb.websub.client')->resubscribe();
    }
  }

}

<?php

namespace Drupal\indieweb_webmention\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb webmention Drush commands file.
 */
class WebmentionCommands extends DrushCommands {

  /**
   * Send webmentions
   *
   * @command indieweb:send-webmentions
   * @aliases isw,indieweb-send-webmentions
   */
  public function sendWebmentions() {
    if (\Drupal::config('indieweb_webmention.settings')->get('send_webmention_handler') == 'drush') {
      indieweb_handle_webmention_queue();
    }
  }

  /**
   * Process webmentions
   *
   * @command indieweb:process-webmentions
   * @aliases ipw,indieweb-process-webmentions
   */
  public function processWebmentions() {
    if (\Drupal::config('indieweb_webmention.settings')->get('webmention_internal_handler') == 'drush') {
      \Drupal::service('indieweb.webmention.client')->processWebmentions();
    }
  }

}

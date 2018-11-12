<?php

namespace Drupal\indieweb_context\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb Context Drush commands file.
 */
class ContextCommands extends DrushCommands {

  /**
   * Fetch post contexts
   *
   * @command indieweb:fetch-post-contexts
   * @aliases ifpc,indieweb-fetch-post-contexts
   */
  public function fetchPostContexts() {
    if (\Drupal::config('indieweb_context.settings')->get('handler') == 'drush') {
      indieweb_handle_post_context_queue();
    }
  }


}

<?php

namespace Drupal\indieweb\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb Drush commands file.
 */
class IndiewebCommands extends DrushCommands {

  /**
   * Send webmentions
   *
   * @command indieweb:send-webmentions
   * @aliases isw,indieweb-send-webmentions
   */
  public function sendWebmentions() {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_send_webmentions();
  }

  /**
   * Process webmentions
   *
   * @command indieweb:process-webmentions
   * @aliases ipw,indieweb-process-webmentions
   */
  public function processWebmentions() {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_process_webmentions();
  }

  /**
   * Fetch post contexts
   *
   * @command indieweb:fetch-post-contexts
   * @aliases ifpc,indieweb-fetch-post-contexts
   */
  public function fetchPostContexts() {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_fetch_post_contexts();
  }

  /**
   * Fetch microsub items.
   *
   * @command indieweb:microsub-fetch-items
   * @aliases imfi,indieweb-microsub-fetch-items
   */
  public function microsubFetchItems() {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_microsub_fetch_items();
  }

  /**
   * Maps an existing account with a domain.
   *
   * @param $uid
   *   The uid of the account.
   * @param $domain
   *   The domain to map.
   *
   * @command indieweb:externalauth-map-account
   * @aliases iema,indieweb-externalauth-map-account
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function externalauthMapAccount($uid, $domain) {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_externalauth_map_account($uid, $domain);
  }

}

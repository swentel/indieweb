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
   * Gets webmentions from Webmention.io. Supports getting webmentions for a
   * certain url and optionally saving them. More features will be added in the
   * future.
   *
   * @param $target_url
   *   The url you want to get the webmentions for.
   * @param $base_url
   *   The base url of your site, so we can strip that from the target.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   * @option api-url
   *   The API url of webmention.io. Defaults to
   *    https://webmention.io/api/mentions
   *
   * @command indieweb:get-webmentions-from-webmention-io
   * @aliases iwio,indieweb-get-webmentions-from-webmention-io
   */
  public function getWebmentionsFromWebmentionIo($target_url, $base_url, array $options = ['api-url' => NULL]) {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_get_webmentions_from_webmention_io($target_url, $base_url, $options);

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
   */
  public function externalauthMapAccount($uid, $domain) {
    // See bottom of https://weitzman.github.io/blog/port-to-drush9 for details on what to change when porting a
    // legacy command.
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_externalauth_map_account($uid, $domain);
  }

}

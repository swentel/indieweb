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

<?php

namespace Drupal\indieweb_indieauth\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb Drush commands file.
 */
class IndieAuthCommands extends DrushCommands {

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
    \Drupal::service('indieweb.indieauth.client')->externalauthMapAccount($uid, $domain, TRUE);
  }

}

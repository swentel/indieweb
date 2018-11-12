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
    if (\Drupal::moduleHandler()->moduleExists('externalauth')) {

      /** @var \Drupal\user\UserInterface $account */
      $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
      if ($account) {
        /** @var \Drupal\externalauth\ExternalAuthInterface $external_auth */
        $external_auth = \Drupal::service('externalauth.externalauth');
        $authname = str_replace(['https://', 'http://'], '', $domain);
        $external_auth->linkExistingAccount($authname, 'indieweb', $account);
        drush_print(dt('Mapped uid @uid with @domain.', ['@uid' => $uid, '@domain' => $domain]));
      }
      else {
        drush_print(dt('Account with uid @uid not found.', ['@uid' => $uid]));
      }

    }
    else {
      drush_print('The External Authentication module is not enabled.');
    }
  }

}

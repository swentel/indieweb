<?php

/**
 * Implements hook_drush_command().
 *
 * @return array
 */
function indieweb_drush_command() {
  return [
    'indieweb-send-webmentions' => [
      'description' => 'Send webmentions',
      'aliases' => ['isw'],
    ],
    'indieweb-externalauth-map-account' => [
      'description' => 'Maps an existing account with a domain.',
      'aliases' => ['iema'],
      'required-arguments' => 2,
      'arguments' => [
        'uid' => 'The uid of the account.',
        'domain' => 'The domain to map.'
      ],
    ],
  ];
}

/**
 * Send webmentions in queue.
 */
function drush_indieweb_send_webmentions() {
  if (\Drupal::config('indieweb.webmention')->get('send_webmention_handler') == 'drush') {
    indieweb_handle_webmention_queue();
  }
}

/**
 * Maps a domain to an existing account.
 *
 * @param int $uid
 * @param string $domain
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function drush_indieweb_externalauth_map_account($uid = 0, $domain = '') {

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

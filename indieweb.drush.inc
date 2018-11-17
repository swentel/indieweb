<?php

/**
 * @file
 * Drush legacy commands.
 */

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
    'indieweb-process-webmentions' => [
      'description' => 'Process webmentions',
      'aliases' => ['ipw'],
    ],
    'indieweb-fetch-post-contexts' => [
      'description' => 'Fetch post contexts',
      'aliases' => ['ifpr'],
    ],
    'indieweb-microsub-fetch-items' => [
      'description' => 'Microsub fetch new items',
      'aliases' => ['imfi'],
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
  if (\Drupal::config('indieweb_webmention.settings')->get('send_webmention_handler') == 'drush') {
    \Drupal::service('indieweb.webmention.client')->handleQueue();
  }
}

/**
 * Process webmentions.
 */
function drush_indieweb_process_webmentions() {
  if (\Drupal::config('indieweb_webmention.settings')->get('webmention_internal_handler') == 'drush') {
    \Drupal::service('indieweb.webmention.client')->processWebmentions();
  }
}

/**
 * Fetch post contexts in queue.
 */
function drush_indieweb_fetch_post_contexts() {
  if (\Drupal::config('indieweb_context.settings')->get('handler') == 'drush') {
    \Drupal::service('indieweb.post_context.client')->handleQueue();
  }
}

/**
 * Microsub fetch items.
 */
function drush_indieweb_microsub_fetch_items() {
  if (\Drupal::config('indieweb_microsub.settings')->get('microsub_internal') &&
    \Drupal::config('indieweb_microsub.settings')->get('microsub_internal_handler') == 'drush') {
    \Drupal::service('indieweb.microsub.client')->fetchItems();
  }
}

/**
 * Maps a domain to an existing account.
 *
 * @param int $uid
 * @param string $domain
 */
function drush_indieweb_externalauth_map_account($uid = 0, $domain = '') {
  \Drupal::service('indieweb.indieauth.client')->externalauthMapAccount($uid, $domain, TRUE);
}

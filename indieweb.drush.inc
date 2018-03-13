<?php

/**
 * Implements hook_drush_commands().
 *
 * @return array
 */
function indieweb_drush_command() {
  return [
    'indieweb-send-webmentions' => [
      'description' => 'Send webmentions',
    ],
  ];
}

/**
 * Send webmentions in queue.
 */
function drush_indieweb_send_webmentions() {
  if (\Drupal::config('indieweb.publish')->get('publish_send_webmention_by') == 'drush') {
    indieweb_handle_publish_queue();
  }
}

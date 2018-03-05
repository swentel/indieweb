<?php

use IndieWeb\MentionClient;

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

  $query = 'SELECT * FROM queue WHERE name = :name';
  $results = \Drupal::database()->query($query, [':name' => 'publish_bridgy']);
  foreach ($results as $result) {

    $data = unserialize($result->data);
    if (!empty($data['url'])) {
      $sourceURL = $data['url'];
      $targetURL = 'https://brid.gy/publish/twitter';
      $client = new MentionClient();
      $client->sendWebmention($sourceURL, $targetURL);
    }

    \Drupal::database()->delete('queue')->condition('item_id', $result->item_id)->execute();
  }

}

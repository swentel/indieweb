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
      'aliases' => ['isw'],
    ],
    'indieweb-get-webmentions-from-webmention-io' => [
      'description' => 'Gets webmentions from Webmention.io. Supports getting webmentions for a certain url and optionally saving them. More features will be added in the future.',
      'aliases' => ['iwio'],
      'arguments' => [
        'target-url' => 'The url you want to get the webmentions for.',
        'base-url' => 'The base url of your site, so we can strip that from the target.'
      ],
      'required-arguments' => 2,
      'options' => [
        'api-url' => 'The API url of webmention.io. Defaults to https://webmention.io/api/mentions',
      ],
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

/**
 * Gets webmentions from webmention.io.
 *
 * @param $target_url
 */
function drush_indieweb_get_webmentions_from_webmention_io($target_url = '', $base_url = '') {
  $api_url = drush_get_option('api-url', 'https://webmention.io/api/mentions');

  $api_key = drush_prompt('Please enter your API key');
  if (!empty($api_key)) {

    $config = \Drupal::config('indieweb.webmention');

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($api_url . '?target=' . $target_url);
      $json = json_decode($response->getBody(), TRUE);
      if (!empty($json['links'])) {
        foreach ($json['links'] as $mention) {

          // This is a bit weird. The data is different than a webmention,
          // especially the type / property combo.
          $property = '';
          $type = 'entry';
          $mention_type = $mention['activity']['type'];
          if ($mention_type == 'like') {
            $property = 'like-of';
          }
          elseif ($mention_type == 'reply') {
            $property = 'in-reply-to';
          }
          elseif ($mention_type == 'repost') {
            $property = 'repost-of';
          }
          elseif ($mention_type == 'link') {
            $type = 'pingback';
            $property = 'pingback';
          }

          $values = [
            'user_id' => $config->get('webmention_uid'),
            // Remove the base url - TODO we should simply use parse_url() :/
            'target' => ['value' => str_replace($base_url, '', $target_url)],
            'source' => ['value' => $mention['source']],
            'type' => ['value' => $type],
            'property' => ['value' => $property]
          ];

          // Set created to published or wm-received if available.
          if (!empty($link['data']['wm-received'])) {
            $values['created'] = strtotime($mention['post']['wm-received']);
          }
          elseif (!empty($mention['data']['published_ts'])) {
            $values['created'] = $mention['data']['published_ts'];
          }

          // Author info.
          foreach (['name', 'photo', 'url'] as $key) {
            if (!empty($mention['data']['author'][$key])) {
              $values['author_' . $key] = ['value' => $mention['data']['author'][$key]];
            }
          }

          // Text content.
          foreach (['sentence_html' => 'html', 'sentence' => 'text'] as $mention_key => $key) {
            if (!empty($mention['activity'][$mention_key])) {
              $values['content_' . $key] = ['value' => $mention['activity'][$mention_key]];
            }
          }

          // Private or not.
          if (!empty($mention['private'])) {
            $values['private'] = ['value' => TRUE];
          }

          // Print collected values.
          print_r($values);

          // Save the entity.
          if (drush_confirm('Do you want to save this mention ?')) {
            // TODO we should probably try/catch this too to be sure.
            $webmention = \Drupal::entityTypeManager()->getStorage('webmention_entity')->create($values);
            $webmention->save();
          }

        }
      }

    }
    catch (Exception $e) {
      drush_print('An error occurred: ' . $e->getMessage());
    }
  }
}

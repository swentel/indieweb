<?php

namespace Drupal\indieweb_context\PostContextClient;

use Drupal\indieweb\PostContextClient\PostContextClientInterface;
use p3k\XRay;

class PostContextClient implements PostContextClientInterface {

  /**
   * {@inheritdoc}
   */
  public function createQueueItem($url, $entity_id, $entity_type_id) {
    $data = [
      'url' => $url,
      'entity_id' => $entity_id,
      'entity_type_id' => $entity_type_id,
    ];

    try {
      \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->createItem($data);
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_queue')->notice('Error creating queue item: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleQueue() {
    $end = time() + 15;
    $xray = new XRay();
    while (time() < $end && ($item = \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->claimItem())) {
      $data = $item->data;
      if (!empty($data['url']) && !empty($data['entity_id']) && !empty($data['entity_type_id'])) {

        // Get content.
        try {
          $response = \Drupal::httpClient()->get($data['url']);
          $body = $response->getBody()->getContents();

          $parsed = $xray->parse($data['url'], $body, ['expect'=>'feed']);
          if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
              $reference = $parsed['data']['items'][0];

              // Nodes.
              if ($data['entity_type_id'] == 'node') {
                \Drupal::database()
                  ->merge('indieweb_post_context')
                  ->key('entity_id', $data['entity_id'])
                  ->key('entity_type_id', $data['entity_type_id'])
                  ->key('url', $data['url'])
                  ->fields(['content' => json_encode($reference)])
                  ->execute();
              }

              // Microsub.
              if ($data['entity_type_id'] == 'microsub_item') {
                \Drupal::database()
                  ->merge('microsub_item')
                  ->key('id', $data['entity_id'])
                  ->fields(['post_context' => json_encode($reference)])
                  ->execute();

            }
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('indieweb_post_context')->notice('Error getting post context for @url: @message', ['@url' => $data['url'], '@message' => $e->getMessage()]);
        }
      }

      // Remove the item - always.
      \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->deleteItem($item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPostContexts($entity_id, $entity_type_id) {
    $contexts = [];

    $records = \Drupal::database()->query('SELECT url, content FROM {indieweb_post_context} WHERE entity_id = :entity_id AND entity_type_id = :entity_type_id', [':entity_id' => $entity_id, ':entity_type_id' => $entity_type_id]);
    foreach ($records as $record) {

      $content = (array) json_decode($record->content);
      if (isset($content['post-type'])) {
        $contexts[] = [
          'url' => $record->url,
          'content' => $content,
        ];
      }

    }

    return $contexts;
  }

}
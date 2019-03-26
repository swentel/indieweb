<?php

namespace Drupal\indieweb_context\PostContextClient;

use DOMDocument;
use DOMXPath;
use Drupal\Core\Cache\Cache;
use Drupal\indieweb\PostContextClient\PostContextClientInterface;
use p3k\XRay;

class PostContextClient implements PostContextClientInterface {

  /**
   * {@inheritdoc}
   */
  public function createQueueItem($url, $entity_id, $entity_type_id) {

    // mobile.twitter.com doesn't have the necessary tags.
    if (strpos($url, 'mobile.twitter.com') !== FALSE) {
      $url = str_replace('mobile.twitter.com', 'twitter.com', $url);
    }

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
        $reference = NULL;

        try {
          $response = \Drupal::httpClient()->get($data['url']);
          $body = $response->getBody()->getContents();

          // -----------------------------------------------------------------
          // Get silo content with our own parser. XRay has support for
          // external services, but usually with API keys. We add support
          // using simple techniques.
          // -----------------------------------------------------------------

          if (strpos($data['url'], 'twitter.com') !== FALSE) {
            $reference = $this->parseTwitter($body);
          }

          if (strpos($data['url'], 'coord.info') !== FALSE || strpos($data['url'], 'geocaching.com') !== FALSE) {
            $reference = $this->parseGeocaching($body);
          }

          // -----------------------------------------------------------------
          // Parse with XRay
          // -----------------------------------------------------------------
          if (!$reference) {
            $parsed = $xray->parse($data['url'], $body, ['expect'=>'feed']);
            if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
              $reference = $parsed['data']['items'][0];
            }
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('indieweb_post_context')->notice('Error getting post context for @url: @message', ['@url' => $data['url'], '@message' => $e->getMessage()]);
        }

        // ------------------------------------------------------------------
        // Save reference, if any.
        // ------------------------------------------------------------------

        if ($reference) {

          // Nodes.
          if ($data['entity_type_id'] == 'node') {
            \Drupal::entityTypeManager()
              ->getStorage('indieweb_post_context')
              ->saveContentContext($data['entity_id'], $data['entity_type_id'], $data['url'], $reference);
            Cache::invalidateTags(['node:' . $data['entity_id']]);
          }

          // Microsub.
          if ($data['entity_type_id'] == 'microsub_item') {

            if (!isset($reference['url'])) {
              $reference['url'] = $data['url'];
            }

            \Drupal::entityTypeManager()
              ->getStorage('indieweb_post_context')
              ->saveMicrosubContext($data['entity_id'], $reference);
          }
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
    return \Drupal::entityTypeManager()->getStorage('indieweb_post_context')->getContentPostContexts($entity_id, $entity_type_id);
  }

  /**
   * Create context from a twitter URL.
   *
   * @param $body
   *   The HTML
   *
   * @return bool|array
   */
  public function parseTwitter($body) {
    $text = '';

    $dom = new domDocument;
    libxml_use_internal_errors(TRUE);
    $dom->loadHTML($body);
    $dom->preserveWhiteSpace = FALSE;
    $meta_elements = $dom->getElementsByTagName('meta');
    foreach ($meta_elements as $element) {
      if ($element->getAttribute('property') == 'og:description') {
        $text = str_replace(['“', '”'], '', $element->getAttribute('content'));
      }
    }

    if ($text) {
      return [
        'type' => 'entry',
        'content' => [
          'text' => $text,
        ],
        'post-type' => 'note',
      ];
    }

    return FALSE;
  }

  /**
   * Create context from geocaching.
   *
   * @param $body
   *
   * @return array|bool
   */
  public function parseGeocaching($body) {
    $text = '';

    libxml_use_internal_errors(TRUE);
    $doc = new DOMDocument();
    $doc->loadHTML($body);
    $xpath = new DOMXPath($doc);
    // There are two description on the page ...
    $description = $xpath->evaluate('//meta[@name="description"]/@content')->item(1);
    if (!empty($description->value)) {
      $text .= $description->value;
    }

    if ($text) {
      return [
        'type' => 'entry',
        'content' => [
          'text' => $text,
        ],
        'post-type' => 'note',
      ];
    }

    return FALSE;
  }

}
<?php

/**
 * @file
 * Hooks specific to the Indieweb module.
 */

use Drupal\node\NodeInterface;

/**
 * Act on the values and input before a node is created with Node::create().
 *
 * When a micropub post request comes in and a node will be created, you can
 * alter the values just before. In this example, we're changing the node type.
 * Switching the node type during hook_node_presave() is too late for pathauto
 * for instance.
 *
 * @param array $values
 *   The values before used in Node::create();
 * @param array $payload
 *   The payload which was entered. You can also change this since some of the
 *   payload is still being used on fields that will be added later like body,
 *   image and others.
 */
function hook_indieweb_micropub_node_pre_create_alter(&$values, &$payload) {
  if (!empty($payload['category']) && in_array('to-gallery', $payload['category'])) {
    $values['type'] = 'another_node_type';
  }
}

/**
 * Act when a node has just been saved and we're about to return the response
 * back to the client that send the request.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The node that just has been saved. There is property on the node called
 *   'micropub_payload' which is the payload from the micropub request so you
 *   can inspect it here to see the original submitted values.
 * @param array $values
 *   The values that where submitted to Node::created(). These could have been
 *   altered in hook_indieweb_micropub_node_pre_create_alter().
 * @param $payload
 *   The payload entered in the micropub request. This could also have been
 *   altered by hook_indieweb_micropub_node_pre_create_alter().
 * @param $payload_original
 *   The original payload from the micropub request.
 */
function hook_indieweb_micropub_node_saved(NodeInterface $node, $values, $payload, $payload_original) {
  if (!empty($payload['category'])) {
    foreach ($payload['category'] as $category) {
      $category = trim($category);
      if ($category == 'to-twitter') {
        $source_url = $node->toUrl()->setAbsolute(TRUE)->toString();
        indieweb_webmention_create_queue_item($source_url, 'https://brid.gy/publish/twitter', $node->id(), 'node');
      }
    }
  }
}

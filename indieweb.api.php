<?php

/**
 * @file
 * Hooks specific to the Indieweb module.
 */

/**
 * Act on the values before a node is created with Node::create().
 *
 * When a micropub post request comes in and a node will be created, you can
 * alter the values just before. In this example, we're changing the node type.
 *
 * $values contains 'micropub_payload' which is the payload from the micropub
 * request so you can inspect it here, or even later during other entity hooks.
 *
 * @param array $values
 *   The values before used in Node::create();
 */
function hook_indieweb_micropub_node_pre_create_alter(&$values) {
  $payload = $values['micropub_payload'];
  if (!empty($payload['category']) && in_array('to-gallery', $payload['category'])) {
    $values['type'] = 'another_node_type';
  }
}

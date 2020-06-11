<?php

/**
 * @file
 * Hooks specific to the IndieWeb modules.
 */

use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

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
 *
 * @throws \Drupal\Core\Entity\EntityMalformedException
 */
function hook_indieweb_micropub_node_saved(NodeInterface $node, $values, $payload, $payload_original) {
  if (!empty($payload['category'])) {
    foreach ($payload['category'] as $category) {
      $category = trim($category);
      if ($category == 'to-twitter') {
        $source = $node->toUrl()->setAbsolute(TRUE)->toString();
        \Drupal::service('indieweb.webmention.client')->createQueueItem($source, 'https://brid.gy/publish/twitter', $node->id(), 'node');
      }
    }
  }
}

/**
 * Act when no post has been made, but has a valid token. If you return an
 * absolute URL, that will be used as the response.
 *
 * @param $payload
 *   The payload entered in the micropub request.
 *
 * @return string $url|NULL
 *   Absolute URL. Return NULL in case nothing happened.
 */
function hook_indieweb_micropub_no_post_made($payload) {
}

/**
 * Act on a geo response.
 *
 * Use this to change or add extra data to the response. The response might
 * already contain the 'geo' property. Another property which can be added is
 * 'places' which contains a list of suggested places.
 *
 * No context is given. You need to check for yourself if 'lat' and 'lon'
 * paramaters are available in the request.
 *
 * @param \stdClass $response
 *   The current response.
 */
function hook_micropub_geo_response_alter($response) {
  $response->places = [
    (object) [
      'label' => 'Place 1',
      'latitude' => '51.123',
      'longitude' => '-0.2323',
      'url' => 'https://example.com/place'
    ],
  ];
}

/**
 * Act when a user registers or logs in with IndieAuth.
 *
 * This is the perfect moment to save additional properties on the account.
 *
 * @param $account
 *   The Drupal user account.
 * @param $indieauth_response
 *   The IndieAuth response, which is a JSON object.
 */
function hook_indieweb_indieauth_login(UserInterface $account, $indieauth_response) {
}

/**
 * Act on a WebSub subscribe event.
 *
 * @param $url
 *   The url to subscribe.
 * @param $seconds
 *   The lease seconds, if available.
 *
 * @return mixed TRUE|NULL
 */
function hook_indieweb_websub_subscribe($url, $seconds) {
  return NULL;
}

/**
 * Act on a WebSub unsubscribe event.
 *
 * @param $url
 *   The url to unsubscribe.
 *
 * @return mixed TRUE|NULL
 */
function hook_indieweb_websub_unsubscribe($url) {
  return NULL;
}

/**
 * Act on incoming WebSub notification.
 *
 * @param $url
 *   The URL which was updated.
 * @param $content
 *   The content, if any.
 */
function hook_indieweb_websub_notification($url, $content) {
}

/**
 * Act on the resubscribe moment.
 *
 * @return array
 *   A collection urls to resubscribe.
 */
function hook_indieweb_websub_needs_resubscribe() {
  return ['url1', 'url2'];
}

/**
 * Alter the options for the Guzzle request just before a request is made to
 * get the contents of a feed.
 *
 * @param $options
 * @param $url
 */
function hook_microsub_pre_request_alter(&$options, $url) {

}

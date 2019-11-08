<?php

namespace Drupal\indieweb_websub\WebSubClient;

interface WebSubClientInterface {

  /**
   * Handles the queue.
   */
  public function handleQueue();

  /**
   * Generates a queue item.
   *
   * @param string $entity_id
   *   (optional) The entity id
   * @param string $entity_type_id
   *   (optional) The entity type id
   */
  public function createQueueItem($entity_id, $entity_type_id);

  /**
   * Resubscribe to WebSub subscriptions.
   */
  public function resubscribe();

  /**
   * Checks whether this entity has been published or not to the hub.
   *
   * @param string $entity_id
   *   (optional) The entity id
   * @param string $entity_type_id
   *   (optional) The entity type id
   *
   * @return bool
   */
  public function isPublishedToHub($entity_id, $entity_type_id);

  /**
   * Subscribe or unsubscribe to a URL.
   *
   * @param $url
   *   The URL you want to subscribe to.
   * @param $hub
   *   The hub endpoint.
   * @param $mode
   *   Either 'subscribe' or 'unsubscribe'
   *
   * @return int $status
   */
  public function subscribe($url, $hub, $mode);

  /**
   * Discover if a URL has a hub.
   *
   * @param $url
   *
   * @return mixed
   */
  public function discoverHub($url);

}

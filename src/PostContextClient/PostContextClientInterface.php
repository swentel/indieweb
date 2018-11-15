<?php

namespace Drupal\indieweb\PostContextClient;

interface PostContextClientInterface {

  /**
   * Creates a post context queue item.
   *
   * @param $url
   * @param $entity_id
   * @param $entity_type_id
   */
  public function createQueueItem($url, $entity_id, $entity_type_id);

  /**
   * Handles the queue.
   */
  public function handleQueue();

  /**
   * Get the post contexts for an entity.
   *
   * @param $entity_id
   * @param $entity_type_id
   *
   * @return array
   */
  public function getPostContexts($entity_id, $entity_type_id);

}
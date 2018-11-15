<?php

namespace Drupal\indieweb\PostContextClient;

class PostContextClient implements PostContextClientInterface {

  /**
   * {@inheritdoc}
   */
  public function createQueueItem($url, $entity_id, $entity_type_id) {}

  /**
   * {@inheritdoc}
   */
  public function handleQueue() {}

  /**
   * {@inheritdoc}
   */
  public function getPostContexts($entity_id, $entity_type_id) {
    return [];
  }

}
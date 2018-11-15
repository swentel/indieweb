<?php

namespace Drupal\indieweb_webmention\WebmentionClient;

use Drupal\indieweb_webmention\Entity\WebmentionInterface;

interface WebmentionClientInterface {

  /**
   * Generates a queue item.
   *
   * @param $source
   *   The source URL
   * @param $target
   *   The target URL
   * @param string $entity_id
   *   (optional) The entity id
   * @param string $entity_type_id
   *   (optional) The entity type id
   */
  public function createQueueItem($source, $target, $entity_id = '', $entity_type_id = '');

  /**
   * Handles the queue.
   */
  public function handleQueue();

  /**
   * Send a webmention.
   *
   * @param $sourceURL
   * @param $targetURL
   *
   * @return mixed
   */
  public function sendWebmention($sourceURL, $targetURL);

  /**
   * Processes received webmentions.
   */
  public function processWebmentions();

  /**
   * Create a comment.
   *
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   */
  public function createComment(WebmentionInterface $webmention);

  /**
   * Checks if a source url already exists as syndication.
   *
   * This can happen when you reply from your site to twitter. Brid.gy will then
   * send that reply on twitter back as a webmention.
   *
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   *
   * @return bool
   */
  public function sourceExistsAsSyndication(WebmentionInterface $webmention);

}

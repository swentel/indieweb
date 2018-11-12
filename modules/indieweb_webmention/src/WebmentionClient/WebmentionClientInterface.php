<?php

namespace Drupal\indieweb_webmention\WebmentionClient;

use Drupal\indieweb_webmention\Entity\WebmentionInterface;

interface WebmentionClientInterface {

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

}

<?php

namespace Drupal\indieweb\WebmentionClient;

use Drupal\indieweb\Entity\WebmentionInterface;

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
   * @param \Drupal\indieweb\Entity\WebmentionInterface $webmention
   *
   * @return mixed
   */
  public function createComment(WebmentionInterface $webmention);

}

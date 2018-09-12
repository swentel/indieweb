<?php

namespace Drupal\indieweb\ApertureClient;

use Drupal\indieweb\Entity\WebmentionInterface;

interface ApertureClientInterface {

  /**
   * Sends a post to Aperture on an incoming webmention.
   *
   * @param string $api_key
   *   The Aperture channel API key
   * @param \Drupal\indieweb\Entity\WebmentionInterface $webmention
   *   The incoming webmention.
   */
  public function sendPost($api_key, WebmentionInterface $webmention);

}
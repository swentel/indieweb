<?php

namespace Drupal\indieweb_microsub\ApertureClient;

interface ApertureClientInterface {

  /**
   * Sends a post to Aperture on an incoming webmention.
   *
   * @param string $api_key
   *   The Aperture channel API key
   * @param $post
   *   The mf2 post
   */
  public function sendPost($api_key, $post);

}
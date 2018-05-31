<?php

namespace Drupal\indieweb\WebmentionClient;

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

}

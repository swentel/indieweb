<?php

namespace Drupal\indieweb\WebmentionClient;

use IndieWeb\MentionClient;

class WebmentionClient implements WebmentionClientInterface {

  /**
   * {@inheritdoc}
   */
  public function sendWebmention($sourceURL, $targetURL) {
    $client = new MentionClient();
    return $client->sendWebmention($sourceURL, $targetURL);
  }

}

<?php

namespace Drupal\indieweb_test\MicrosubClient;

use Drupal\Component\Utility\Random;
use Drupal\indieweb_microsub\MicrosubClient\MicrosubClient;
use Drupal\indieweb_webmention\Entity\WebmentionInterface;

class MicrosubClientTest extends MicrosubClient {

  /**
   * {@inheritdoc}
   */
  public function sendNotification(WebmentionInterface $webmention, $parsed = NULL, $channel_id = 0) {

    $url = $webmention->get('url')->value;
    if (empty($url)) {
      $r = new Random();
      $url = $r->name();
    }

    $item = [
      'url' => $url,
      'content' => ['data']
    ];

    try {
      $this->saveItem($item);
    }
    catch (\Exception $ignored) {}
  }

}

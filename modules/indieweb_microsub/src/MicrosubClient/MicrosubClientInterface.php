<?php

namespace Drupal\indieweb_microsub\MicrosubClient;

use Drupal\indieweb_webmention\Entity\WebmentionInterface;

interface MicrosubClientInterface {

  /**
   * Fetch new items.
   */
  public function fetchItems();

  /**
   * Send notification from a webmention.
   *
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   * @param $parsed
   */
  public function sendNotification(WebmentionInterface $webmention, $parsed = NULL);

}
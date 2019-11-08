<?php

namespace Drupal\indieweb_microsub\MicrosubClient;

use Drupal\indieweb_webmention\Entity\WebmentionInterface;

interface MicrosubClientInterface {

  /**
   * Fetch new items.
   *
   * @param string $url
   *   Only fetch items for a specific URL.
   * @param string $content
   *   The body for the url.
   */
  public function fetchItems($url = '', $content = '');

  /**
   * Send notification from a webmention.
   *
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   * @param $parsed
   */
  public function sendNotification(WebmentionInterface $webmention, $parsed = NULL);

  /**
   * Send push notification.
   *
   * @param array $webmentions
   *   A collection of valid webmentions.
   */
  public function sendPushNotification($webmentions);

  /**
   * Search feeds on a url.
   *
   * XRay ignores rel="feed", so we use our own version that adds them.
   *
   * @param $url
   * @param $body
   *
   * @return array
   */
  public function searchFeeds($url, $body = NULL);

}

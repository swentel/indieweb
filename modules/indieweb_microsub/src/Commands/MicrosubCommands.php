<?php

namespace Drupal\indieweb_microsub\Commands;

use Drush\Commands\DrushCommands;
use p3k\XRay;

/**
 * IndieWeb Microsub Drush commands file.
 */
class MicrosubCommands extends DrushCommands {

  /**
   * Fetch microsub items.
   *
   * @command indieweb:microsub-fetch-items
   * @aliases imfi,indieweb-microsub-fetch-items
   */
  public function microsubFetchItems() {
    if (\Drupal::config('indieweb_microsub.settings')->get('microsub_internal') &&
      \Drupal::config('indieweb_microsub.settings')->get('microsub_internal_handler') == 'drush') {
      \Drupal::service('indieweb.microsub.client')->fetchItems();
    }
  }

  /**
   * Test Xray parser on a URL.
   *
   * @param $url
   *
   * @command indieweb:microsub-test-xray
   * @aliases imtx
   */
  public function testXray($url) {
    $xray = new XRay();
    $options = ['headers' => ['User-Agent' => indieweb_microsub_http_client_user_agent()]];
    $response = \Drupal::httpClient()->get($url, $options);
    $body = ltrim($response->getBody()->getContents());
    $parsed = $xray->parse($url, $body, ['expect' => 'feed']);
    print_r($parsed);
  }

  /**
   * Test Xray feed discovery on a URL.
   *
   * @param $url
   *
   * @command indieweb:microsub-test-xray-discovery
   * @aliases imtxd
   */
  public function testXrayFeedDiscovery($url) {
    $microsubClient = \Drupal::service('indieweb.microsub.client');
    $feeds = $microsubClient->searchFeeds($url);
    print_r($feeds);
  }

  /**
   * Test XRay application/activity+json parsing.
   *
   * @command indieweb:microsub-test-activity-parsing
   *
   * @param $activityId
   * @param int $send
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testActivityParsing($activityId, $send = 0) {
    /** @var \Drupal\activitypub\Entity\ActivityPubActivityInterface $activity */
    $activity = \Drupal::entityTypeManager()->getStorage('activitypub_activity')->load($activityId);
    if (!$activity) {
      echo "No activity found";
      return;
    }

    $xray = new XRay();
    /** @var \Drupal\indieweb_microsub\MicrosubClient\MicrosubClientInterface $microsubClient */
    $microsubClient = \Drupal::service('indieweb.microsub.client');

    $json = @json_decode($activity->getPayLoad(), TRUE);
    $parsed = $xray->parse($json['id'], $json);
    print_r($parsed);

    $target = 'https://realize.be';
    if (!empty($json['object']['inReplyTo'])) {
      $target = $json['object']['inReplyTo'];
    }
    $values = [
      'source' => $json['id'],
      'target' => $target,
    ];

    if ($send) {
      /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention */
      $webmention = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->create($values);
      $microsubClient->sendNotification($webmention, $parsed);
    }
  }

}

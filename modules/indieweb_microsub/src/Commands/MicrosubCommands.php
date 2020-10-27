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
   * Test Microsub ActivityPub plugin.
   *
   * @command indieweb:microsub-test-activity-plugin
   *
   * @param $activityId
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testActivityParsing($activityId) {
    /** @var \Drupal\activitypub\Entity\ActivityPubActivityInterface $activity */
    $activity = \Drupal::entityTypeManager()->getStorage('activitypub_activity')->load($activityId);
    if (!$activity) {
      echo "No activity found";
      return;
    }

    /** @var \Drupal\activitypub\Services\Type\TypePluginInterface $object */
    $object = $activity->getTypePluginManager()->createInstance('activitypub_microsub');
    $object->onActivitySave($activity, FALSE);
  }

}

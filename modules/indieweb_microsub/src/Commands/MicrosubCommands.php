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
    $response = \Drupal::httpClient()->get($url);
    $body = ltrim($response->getBody()->getContents());
    $parsed = $xray->parse($url, $body, ['expect' => 'feed']);
    print_r($parsed);
  }

}

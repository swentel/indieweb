<?php

namespace Drupal\indieweb_websub\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb webmention Drush commands file.
 */
class WebSubCommands extends DrushCommands {

  /**
   * WebSub publish.
   *
   * @command indieweb:websub-publish
   * @aliases iwp,indieweb-websub-publish
   */
  public function webSubPublish() {
    if (\Drupal::config('indieweb_websub.settings')->get('send_pub_handler') == 'drush') {
      \Drupal::service('indieweb.websub.client')->handleQueue();
    }
  }

  /**
   * WebSub resubscribe
   *
   * @command indieweb:websub-resubscribe
   * @aliases iwrs,indieweb-websub-resubscribe
   */
  public function webSubResubscribe() {
    if (\Drupal::config('indieweb_websub.settings')->get('resubscribe_handler') == 'drush') {
      \Drupal::service('indieweb.websub.client')->resubscribe();
    }
  }

  /**
   * Command to test hub discovery.
   *
   * @param $url
   *
   * @command indieweb:websub-discover-hub
   * @aliases indieweb-websub-discover-hub
   */
  public function webSubTestHubDiscovery($url) {
    /** @var \Drupal\indieweb_websub\WebSubClient\WebSubClientInterface $websub_service */
    $websub_service = \Drupal::service('indieweb.websub.client');
    $info = $websub_service->discoverHub($url, TRUE);
    print_r($info);
  }

}

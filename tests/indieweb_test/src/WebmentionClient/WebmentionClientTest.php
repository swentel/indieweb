<?php

namespace Drupal\indieweb_test\WebmentionClient;

use Drupal\Core\Url;
use Drupal\indieweb\WebmentionClient\WebmentionClientInterface;

class WebmentionClientTest implements WebmentionClientInterface {

  /**
   * {@inheritdoc}
   */
  public function sendWebmention($sourceURL, $targetURL) {
    // Call the test webmention endpoint hook.
    $uri = Url::fromRoute('indieweb_test.webmention_endpoint', [], ['absolute' => TRUE])->toString();
    $httpClient = \Drupal::httpClient();
    try {
      $response = $httpClient->get($uri);
      if ($response->getHeader('Location')) {
        $return = [];
        $return['code'] = 201;
        $return['headers']['Location'] = $response->getHeader('Location')[0];
        return $return;
      }
    }
    catch (\Exception $ignored) {}

    return FALSE;
  }

}

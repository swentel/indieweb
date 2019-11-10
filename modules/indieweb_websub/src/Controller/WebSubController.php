<?php

namespace Drupal\indieweb_websub\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebSubController extends ControllerBase {

  /**
   * General callback endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $websub_hash
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function callback(Request $request, $websub_hash) {
    $status = 404;
    $response = '';

    /** @var \Drupal\indieweb_websub\WebSubClient\WebSubClientInterface $websub_client */
    $websub_client = \Drupal::service('indieweb.websub.client');

    // Notification callback.
    if ($request->getMethod() == 'POST') {

      $url = $hub = '';

      // Check link header.
      try {
        $result = \IndieWeb\http_rels($request->headers);
        if (!empty($result['self'])) {
          $url = $result['self'][0];
        }
        if (!empty($result['hub'])) {
          $hub = $result['hub'][0];
        }
      }
      catch (\Exception $ignored) {}


      if (!empty($url) && !empty($hub) && Crypt::hashEquals($websub_hash, $websub_client->getHash($url))) {
        $status = 200;
        $content = $request->getContent();
        \Drupal::moduleHandler()->invokeAll('indieweb_websub_notification', [$url, $hub, $content]);
      }

      // Log payload.
      if (\Drupal::config('indieweb_websub.settings')->get('log_payload')) {
        \Drupal::logger('indieweb_websub')->notice('notification callback: @headers - @body', ['@headers' => print_r($request->headers->all(), 1), '@body' => print_r($request->getContent(), 1)]);
      }

    }
    // Subscribe or unsubscribe callback.
    elseif ($request->get('hub_mode') && $request->get('hub_topic') && $request->get('hub_challenge') && Crypt::hashEquals($websub_hash, $websub_client->getHash($request->get('hub_topic')))) {

      $method = $request->get('hub_mode');

      switch ($method) {
        case 'subscribe':
          $url = $request->get('hub_topic');
          $seconds = $request->get('hub_lease_seconds', 0);
          $result = \Drupal::moduleHandler()->invokeAll('indieweb_websub_subscribe', [$url, $seconds]);
          if ($result) {
            $status = 200;
            $response = $request->get('hub_challenge');
          }
          break;

        case 'unsubscribe':
          $url = $request->get('hub_topic');
          $result = \Drupal::moduleHandler()->invokeAll('indieweb_websub_unsubscribe', [$url]);
          if ($result) {
            $status = 200;
            $response = $request->get('hub_challenge');
          }
          break;
      }

      // Log payload.
      if (\Drupal::config('indieweb_websub.settings')->get('log_payload')) {
        \Drupal::logger('indieweb_websub')->notice('subscribe callback: @headers - @params', ['@headers' => print_r($request->headers->all(), 1), '@params' => print_r($request->query->all(), 1)]);
      }

    }

    return new Response($response, $status);
  }

}

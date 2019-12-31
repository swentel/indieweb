<?php

namespace Drupal\indieweb_websub\WebSubClient;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use p3k\WebSub\Client;

class WebSubClient implements WebSubClientInterface {

  /**
   * {@inheritdoc}
   */
  public function handleQueue() {
    $end = time() + 15;
    $config = \Drupal::config('indieweb_websub.settings');

    $pages = [];
    $wildcard = FALSE;
    $config_pages = explode("\n", $config->get('pages'));
    foreach ($config_pages as $page) {
      $page = trim($page);
      if (!empty($page)) {
        if (strpos($page, '*') !== FALSE) {
          $wildcard = TRUE;
        }
        $pages[] = \Drupal::request()->getSchemeAndHttpHost() . $page;
      }
    }

    while (time() < $end && ($item = \Drupal::queue(INDIEWEB_WEBSUB_QUEUE)->claimItem())) {
      $data = $item->data;

      if (!empty($data['entity_id']) && !empty($data['entity_type_id']) && !empty($data['uid']) && !empty($pages)) {

        $uid = $data['uid'];
        $entity_id = $data['entity_id'];
        $entity_type_id = $data['entity_type_id'];

        // We assume that a wildcard is for a uid.
        $pages_to_send = $pages;
        if ($wildcard) {
          foreach ($pages as $key => $page) {
            $pages_to_send[$key] = str_replace('*', $uid, $page);
          }
        }

        // Send request.
        $options = [
          'form_params' => [
            'hub.mode' => 'publish',
            'hub.url' => $pages_to_send,
          ]
        ];
        $client = \Drupal::httpClient();
        $response = $client->post($config->get('hub_endpoint'), $options);

        try {
          $websubpub = \Drupal::entityTypeManager()->getStorage('indieweb_websubpub')->create(['uid' => $uid, 'entity_id' => $entity_id, 'entity_type_id' => $entity_type_id]);
          $websubpub->save();
        }
        catch (\Exception $e) {
          \Drupal::logger('indieweb_websub')->notice('Error saving websubpub record: @message', ['@message' => $e->getMessage()]);
        }

        if ($config->get('log_payload')) {
          \Drupal::logger('indieweb_websub')->notice('publish response for @entity_id / @entity_type_id: @code - @response', ['@code' => $response->getStatusCode(), '@response' => print_r($response->getBody()->getContents(), 1), '@entity_id' => $entity_id, '@entity_type_id' => $entity_type_id]);
        }
      }

      // Always delete.
      \Drupal::queue(INDIEWEB_WEBSUB_QUEUE)->deleteItem($item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createQueueItem($entity_id, $entity_type_id, $uid) {
    $data = [
      'entity_id' => $entity_id,
      'entity_type_id' => $entity_type_id,
      'uid' => $uid,
    ];
    try {
      \Drupal::queue(INDIEWEB_WEBSUB_QUEUE)->createItem($data);
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_queue')->notice('Error creating queue item: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resubscribe($debug = FALSE) {
    $urls = \Drupal::moduleHandler()->invokeAll('indieweb_websub_needs_resubscribe');
    if ($debug) {
      print_r($urls);
      return;
    }
    foreach ($urls as $url) {
      if ($info = $this->discoverHub($url)) {
        $this->subscribe($info['self'], $info['hub'], 'subscribe');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isPublishedToHub($entity_id, $entity_type_id) {
    if ($entity_id && $entity_type_id) {
      $published = \Drupal::entityTypeManager()->getStorage('indieweb_websubpub')->loadByProperties(['entity_id' => $entity_id, 'entity_type_id' => $entity_type_id]);
      return empty($published) ? FALSE : TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function subscribe($url, $hub, $mode) {
    $status = 400;

    $options = [
      'form_params' => [
        'hub.mode' => $mode,
        'hub.topic' => $url,
        'hub.callback' => Url::fromRoute('indieweb.websub.callback', ['websub_hash' => $this->getHash($url)], ['absolute' => TRUE])->toString()
      ]
    ];

    try {
      $client = \Drupal::httpClient();
      $response = $client->post($hub, $options);
      $status = $response->getStatusCode();
      if (\Drupal::config('indieweb_websub.settings')->get('log_payload')) {
        \Drupal::logger('indieweb_websub')->notice('subscribe response for @url, @hub, @mode: @code - @response', ['@code' => $response->getStatusCode(), '@response' => print_r($response->getBody()->getContents(), 1), '@hub' => $hub, '@url' => $url, '@mode' => $mode]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_websub')->notice('Error sending subscribe request: @message', ['@message' => $e->getMessage()]);
    }

    return $status;
  }

  /**
   * {@inheritDoc}
   */
  public function discoverHub($url, $debug = FALSE) {
    $webSubClient = new Client();

    try {

      $response = $webSubClient->discover($url);
      if (!empty($response['hub']) && !empty($response['self'])) {
        if ($debug) {
          print_r($response);
        }
        return ['hub' => $response['hub'], 'self' => $response['self']];
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_websub')->notice('Error discovering hub: @message', ['@message' => $e->getMessage()]);
    }

    return FALSE;
  }

  /**
   *{@inheritdoc}
   */
  public function getHash($url) {
    return Crypt::hashBase64(Settings::getHashSalt() . $url);
  }

}

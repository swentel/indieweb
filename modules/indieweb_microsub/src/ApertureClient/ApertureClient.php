<?php

namespace Drupal\indieweb_microsub\ApertureClient;

class ApertureClient implements ApertureClientInterface {

  /**
   * {@inheritdoc}
   */
  public function sendPost($api_key, $post) {
    $this->sendMicropubRequest($api_key, $post);
  }

  /**
   * Send micropub request.
   *
   * @param $api_key
   *   The Aperture Channel API key.
   * @param $post
   *   The micropub post to send.
   */
  public function sendMicropubRequest($api_key, $post) {
    $auth = 'Bearer ' . $api_key;

    $client = \Drupal::httpClient();
    $headers = [
      'Accept' => 'application/json',
    ];

    // Access token is always in the headers when using Request from p3k.
    $headers['Authorization'] = $auth;

    try {
      $response = $client->post('https://aperture.p3k.io/micropub', ['json' => $post, 'headers' => $headers]);
      $status_code = $response->getStatusCode();
      $headersLocation = $response->getHeader('Location');
      if (empty($headersLocation[0]) || $status_code != 201) {
        \Drupal::logger('indieweb_aperture')->notice('Error sending micropub request: @code', ['@code' => $status_code]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_aperture')->notice('Error sending micropub request: @message', ['@message' => $e->getMessage()]);
    }

  }

}
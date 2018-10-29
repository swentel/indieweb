<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;

class MicroControllerBase extends ControllerBase {

  /**
   * @var  \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Gets the Authorization header from the request.
   *
   * @return null|string|string[]
   */
  protected function getAuthorizationHeader() {
    $auth = NULL;
    $auth_header = \Drupal::request()->headers->get('Authorization');
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
      $auth = $auth_header;
    }

    return $auth;
  }

  /**
   * Check if there's a valid access token in the request.
   *
   * @param $auth_header
   *   The input.
   * @param $scope_to_check
   *   The scope needed for this request, optional.
   *
   * @return bool
   */
  protected function isValidToken($auth_header, $scope_to_check = NULL) {
    $valid_token = FALSE;

    try {
      $client = \Drupal::httpClient();
      $headers = [
        'Accept' => 'application/json',
        'Authorization' => $auth_header,
      ];

      $response = $client->get(\Drupal::config('indieweb.indieauth')->get('token_endpoint'), ['headers' => $headers]);
      $json = json_decode($response->getBody());

      // Compare me with current domain. We don't support multi-user
      // authentication yet.
      $domain = rtrim(\Drupal::request()->getSchemeAndHttpHost(), '/');
      if (isset($json->me) && rtrim($json->me, '/') === $domain) {

        // The token is valid.
        $valid_token = TRUE;

        // Scope check.
        if (!empty($scope_to_check)) {
          $scopes = isset($json->scope) ? explode(' ', $json->scope) : [];
          if (empty($scopes) || !in_array($scope_to_check, $scopes)) {
            $valid_token = FALSE;
            $this->getLogger('indieweb_scope')->notice('Scope "@scope" insufficient', ['@scope' => $scope_to_check]);
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('indieweb_token')->notice('Error validating the access token: @message', ['@message' => $e->getMessage()]);
    }

    return $valid_token;
  }

}
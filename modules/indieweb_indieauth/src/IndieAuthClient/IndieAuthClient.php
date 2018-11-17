<?php

namespace Drupal\indieweb_indieauth\IndieAuthClient;

class IndieAuthClient implements IndieAuthClientInterface {

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationHeader() {
    $auth = NULL;
    $auth_header = \Drupal::request()->headers->get('Authorization');
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
      $auth = $auth_header;
    }

    return $auth;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidToken($auth_header, $scope_to_check = NULL) {
    $indieauth = \Drupal::config('indieweb_indieauth.settings');
    $internal = $indieauth->get('auth_internal');

    if ($internal) {
      return $this->validateTokenInternal($auth_header, $scope_to_check);
    }
    else {
      return $this->validateTokenOnExternalService($auth_header, $indieauth->get('token_endpoint'), $scope_to_check);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function externalauthMapAccount($uid, $domain, $is_drush = FALSE) {
    if (\Drupal::moduleHandler()->moduleExists('externalauth')) {

      /** @var \Drupal\user\UserInterface $account */
      $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
      if ($account) {
        /** @var \Drupal\externalauth\ExternalAuthInterface $external_auth */
        $external_auth = \Drupal::service('externalauth.externalauth');
        $authname = str_replace(['https://', 'http://'], '', $domain);
        $external_auth->linkExistingAccount($authname, 'indieweb', $account);

        if ($is_drush) {
          drush_print(dt('Mapped uid @uid with @domain.', ['@uid' => $uid, '@domain' => $domain]));
        }
      }
      else {
        if ($is_drush) {
          dt('Account with uid @uid not found.', ['@uid' => $uid]);
        }
      }

    }
    else {
      if ($is_drush) {
        drush_print('The External Authentication module is not enabled.');
      }
    }
  }

  /**
   * Internal IndieAuth token validation.
   *
   * @param $auth_header
   * @param $scope_to_check
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function validateTokenInternal($auth_header, $scope_to_check) {
    $valid_token = FALSE;

    $matches = [];
    $access_token = '';
    preg_match('/Bearer\s(\S+)/', $auth_header, $matches);
    if (isset($matches[1])) {
      $access_token = $matches[1];
    }

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface $indieAuthToken */
    $indieAuthToken = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_token')->getIndieAuthTokenByAccessToken($access_token);
    if ($indieAuthToken && $indieAuthToken->isValid()) {

      // The token is valid.
      $valid_token = TRUE;

      // Scope check.
      if (!empty($scope_to_check)) {
        $scopes = $indieAuthToken->getScopes();
        if (empty($scopes) || !in_array($scope_to_check, $scopes)) {
          $valid_token = FALSE;
          \Drupal::Logger('indieweb_scope')->notice('Scope "@scope" insufficient', ['@scope' => $scope_to_check]);
        }
      }

      // Update changed.
      $indieAuthToken->set('changed', \Drupal::time()->getRequestTime())->save();
    }

    return $valid_token;
  }

  /**
   * External IndieAuth token validation.
   *
   * @param $auth_header
   * @param $token_endpoint
   * @param $scope_to_check
   *
   * @return bool
   */
  private function validateTokenOnExternalService($auth_header, $token_endpoint, $scope_to_check) {
    $valid_token = FALSE;

    try {
      $client = \Drupal::httpClient();
      $headers = [
        'Accept' => 'application/json',
        'Authorization' => $auth_header,
      ];

      $response = $client->get($token_endpoint, ['headers' => $headers]);
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
            \Drupal::Logger('indieweb_scope')->notice('Scope "@scope" insufficient', ['@scope' => $scope_to_check]);
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::Logger('indieweb_token')->notice('Error validating the access token: @message', ['@message' => $e->getMessage()]);
    }

    return $valid_token;
  }
}
<?php

namespace Drupal\indieweb_indieauth\IndieAuthClient;

use Drupal\Core\Site\Settings;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Rsa\Sha512;

class IndieAuthClient implements IndieAuthClientInterface {

  const CERT_CONFIG = [
    "digest_alg" => "sha512",
    "private_key_bits" => 4096,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
  ];

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
  public function generateKeys() {
    $success = FALSE;

    if (!extension_loaded('openssl')) {
      \Drupal::logger('indieweb_indieauth')->notice('OpenSSL PHP extension is not loaded.');
      return $success;
    }

    try {

      // Generate Resource.
      $resource = openssl_pkey_new(self::CERT_CONFIG);

      // Get Private Key.
      openssl_pkey_export($resource, $pkey);

      // Get Public Key.
      $pubkey = openssl_pkey_get_details($resource);

      $keys = [
        'private' => $pkey,
        'public' => $pubkey['key'],
      ];

      $paths = [];
      $dir_path = Settings::get('indieauth_keys_path', 'public://indieauth');
      file_prepare_directory($dir_path, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

      foreach (['public', 'private'] as $name) {

        // Key uri.
        $key_uri = "$dir_path/$name.key";

        // Remove old key.
        if (file_exists($key_uri)) {
          drupal_unlink($key_uri);
        }

        // Write key content to key file.
        file_put_contents($key_uri, $keys[$name]);

        // Set correct permission to key file.
        drupal_chmod($key_uri, 0600);

        $paths[$name . '_key'] = $key_uri;
      }

      $success = $paths;
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_indieauth')->notice('Error generating keys: @message', ['@message' => $e->getMessage()]);
    }

    return $success;
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
    $bearer_token = '';
    preg_match('/Bearer\s(\S+)/', $auth_header, $matches);
    if (isset($matches[1])) {
      $bearer_token = $matches[1];
    }

    $config = \Drupal::config('indieweb_indieauth.settings');
    $signer = new Sha512();

    $access_token = '';
    try {
      $JWT = (new Parser())->parse((string) $bearer_token);
      $valid = $JWT->verify($signer, file_get_contents($config->get('public_key')));
      if ($valid) {
        $access_token = $JWT->getHeader('jti');
      }
    }
    catch (\Exception $e) {
      \Drupal::Logger('indieweb_token_verify')->notice('Error verifying token: @message', ['@message' => $e->getMessage()]);
    }

    // Return early already, no need to verify further.
    if (!$access_token) {
      return $valid_token;
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
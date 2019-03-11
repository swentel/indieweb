<?php

namespace Drupal\indieweb_indieauth\Controller;

use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Rsa\Sha512;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IndieAuthController extends ControllerBase {

  /**
   * The parameters needed for an authorize request.
   *
   * @var array
   */
  static $auth_parameters = [
    'response_type',
    'redirect_uri',
    'client_id',
    'me',
    'scope',
    'state'
  ];

  /**
   * The parameters needed for a authentication verification request.
   *
   * @var array
   */
  static $auth_verify_parameters = [
    'client_id',
    'code',
    'redirect_uri',
  ];

  /**
   * The parameters needed for a token request.
   *
   * @var array
   */
  static $token_parameters = [
    'code',
    'me',
    'redirect_uri',
    'client_id',
    'grant_type',
  ];

  /**
   * Routing callback: authorization screen.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function auth(Request $request) {

    $config = \Drupal::config('indieweb_indieauth.settings');
    $auth_enabled = $config->get('auth_internal');

    // Early return when internal server is not enabled.
    if (!$auth_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Get the method.
    $method = $request->getMethod();

    // ------------------------------------------------------------------------
    // POST request: verify a authentication request.
    // See https://indieauth.spec.indieweb.org/#authorization-code-verification
    // ------------------------------------------------------------------------

    if ($method == 'POST') {

      $reason = '';
      $params = [];
      $valid_request = TRUE;
      self::validateAuthenticationRequestParameters($request, $reason, $valid_request, $params);
      if (!$valid_request) {
        $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authentication request: @reason', ['@reason' => $reason]);
        return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing or invalid parameters'], 400);
      }

      // Get authorization code.
      /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface $authorization_code */
      $authorization_code = $this->entityTypeManager()->getStorage('indieweb_indieauth_code')->getIndieAuthAuthorizationCode($params['code']);

      if (!$authorization_code) {
        $this->getLogger('indieweb_indieauth')->notice('No Authorization code found for @code', ['@code' => $params['code']]);
        return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Authorization code not found'], 404);
      }

      if (!$authorization_code->isValid()) {
        $this->getLogger('indieweb_indieauth')->notice('Authorization expired for @code', ['@code' => $params['code']]);
        return new JsonResponse(['error' => 'access_denied', 'error_description' => 'Authorization code expired'], 403);
      }

      // Verify the data from the request matches with the stored data.
      $stored_data = [];
      foreach (self::$auth_verify_parameters as $parameter) {
        $stored = $authorization_code->get($parameter)->value;
        $stored_data[] = $stored;
        if ($stored != $params[$parameter]) {
          $valid_request = FALSE;
          break;
        }
      }

      if (!$valid_request) {
        $this->getLogger('indieweb_indieauth')->notice('Stored values do not match with request values: @stored_data -  @request', ['@stored_values' => $stored_data, '@request' => print_r($params, 1)]);
        return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Session and request values do not match'], 400);
      }

      // Good to go.
      $response = [
        'me' => $authorization_code->getMe(),
        'profile' => $this->getProfile($authorization_code->getOwnerId()),
      ];

      // Remove old code.
      $authorization_code->delete();

      return new JsonResponse($response, 200);
    }

    // ------------------------------------------------------------------------
    // GET request: redirect to the auth/form url. We work like this since
    // submitting the 'Authorize' form, we get into a POST request, which gets
    // into the this controller again and we want to verify authorization
    // requests here as well.
    // See https://indieauth.spec.indieweb.org/#authentication-request
    // ------------------------------------------------------------------------

    if ($method == 'GET') {
      $auth_form_path = Url::fromRoute('indieweb.indieauth.auth_form', [], ['query' => UrlHelper::filterQueryParameters($request->query->all())])->toString();
      return new RedirectResponse($auth_form_path);
    }
  }

  /**
   * Authorize form screen.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   */
  public function authForm(Request $request) {

    $config = \Drupal::config('indieweb_indieauth.settings');
    $auth_enabled = $config->get('auth_internal');

    // Early return when internal server is not enabled.
    if (!$auth_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    $reason = '';
    $params = [];
    $valid_request = TRUE;

    // ------------------------------------------------------------------------
    // Redirect to user login if this is an anonymous user. Start a session so
    // we don't expose the details of the request on the user login page.
    // ------------------------------------------------------------------------
    if ($this->currentUser()->isAnonymous()) {

      self::validateAuthorizeRequestParameters($request, $reason, $valid_request, FALSE, $params);

      if ($valid_request) {
        $_SESSION['indieauth'] = $params;
        $this->messenger()->addMessage($this->t('Login first with your account. You will be redirected to the authorize screen on success.'));
        return new RedirectResponse(Url::fromRoute('user.login', [], ['query' => ['destination' => Url::fromRoute('indieweb.indieauth.auth_form')->toString()]])->toString());
      }

      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authorize as anonymous: @reason', ['@reason' => $reason]);
      return ['#markup' => 'Invalid request, missing parameters.', '#cache' => ['max-age' => 0]];
    }

    // ------------------------------------------------------------------------
    // Authenticated user: Store in session in case the indieauth key does not
    // exist yet.
    // ------------------------------------------------------------------------

    elseif (!isset($_SESSION['indieauth'])) {
      self::validateAuthorizeRequestParameters($request, $reason, $valid_request, FALSE, $params);
      $_SESSION['indieauth'] = $params;
    }

    // ------------------------------------------------------------------------
    // Check permission and required parameters as authenticated user.
    // ------------------------------------------------------------------------

    if (!$this->currentUser()->hasPermission('authorize with indieauth')) {
      return ['#markup' => 'You do not have permission to authorize.', '#cache' => ['max-age' => 0]];
    }

    self::validateAuthorizeRequestParameters($request, $reason, $valid_request, TRUE);
    if (!$valid_request) {
      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authorize as user: @reason', ['@reason' => $reason]);
      return ['#markup' => 'Invalid request, missing parameters', '#cache' => ['max-age' => 0]];
    }

    // ------------------------------------------------------------------------
    // Good to go, show the authorize form.
    // ------------------------------------------------------------------------

    $build = [];
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\indieweb_indieauth\Form\IndieAuthAuthorizeForm');
    return $build;
  }

  /**
   * Validate authentication verification request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param $reason
   * @param $valid_request
   * @param $params
   */
  public static function validateAuthenticationRequestParameters(Request $request, &$reason, &$valid_request, &$params) {
    foreach (self::$auth_verify_parameters as $parameter) {
      $check = $request->request->get($parameter);
      if (empty($check)) {
        $reason = "$parameter is empty";
        $valid_request = FALSE;
        break;
      }

      // Store the params.
      if (is_array($params)) {
        $params[$parameter] = $check;
      }
    }
  }

  /**
   * Check request parameters for an IndieAuth authorize request.
   *
   * response_type and code are optional.
   *
   * @param $request
   * @param $reason
   * @param $valid_request
   * @param $in_session
   * @param $params
   */
  public static function validateAuthorizeRequestParameters(Request $request, &$reason, &$valid_request, $in_session = FALSE, &$params = NULL) {

    foreach (self::$auth_parameters as $parameter) {

      $value = $in_session ? (isset($_SESSION['indieauth'][$parameter]) ? $_SESSION['indieauth'][$parameter] : '') : $request->query->get($parameter);
      if (empty($value) && !in_array($parameter, ['response_type', 'scope'])) {
        $reason = "$parameter is empty";
        $valid_request = FALSE;
        break;
      }
      elseif ($parameter == 'response_type') {
        if (!empty($value) && ($value != 'code' && $value != 'id')) {
          $valid_request = FALSE;
          $reason = "response type is not code or id ($value)";
          break;
        }
        // Set default value in case it was empty.
        // See https://indieauth.spec.indieweb.org/#authentication-request
        $value = 'id';
      }

      // Store the params.
      if (is_array($params) && !empty($value)) {
        $params[$parameter] = $value;
      }
    }
  }

  /**
   * Check request parameters for an IndieAuth code request.
   *
   * @param $request
   * @param $reason
   * @param $valid_request
   * @param $params
   */
  public static function validateTokenRequestParameters(Request $request, &$reason, &$valid_request, &$params = NULL) {
    foreach (self::$token_parameters as $parameter) {

      $check = $request->request->get($parameter);

      if (empty($check)) {
        $reason = "$parameter is empty";
        $valid_request = FALSE;
        break;
      }
      elseif ($parameter == 'grant_type' && $check != 'authorization_code') {
        $reason = "grant_type is not authorization_code";
        $valid_request = FALSE;
        break;
      }

      // Store the params.
      if (is_array($params) && !empty($check)) {
        $params[$parameter] = $check;
      }
    }
  }

  /**
   * Routing callback: token endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function token(Request $request) {

    $config = \Drupal::config('indieweb_indieauth.settings');
    $auth_enabled = $config->get('auth_internal');

    // Early return when internal server is not enabled.
    if (!$auth_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Return early if this is not a POST request.
    if ($request->getMethod() != 'POST') {
      return new Response($this->t('Page not found'), 404);
    }

    // -----------------------------------------------------------------
    // Token revocation request.

    if ($request->request->has('action') && $request->request->get('action') == 'revoke' && ($token = $request->request->get('token'))) {
      /** @var \Drupal\indieweb_indieauth\IndieAuthClient\IndieAuthClientInterface $indieAuthClient */
      $indieAuthClient = \Drupal::service('indieweb.indieauth.client');
      $indieAuthClient->revokeToken($token);
      return new JsonResponse([], 200);
    }

    // -----------------------------------------------------------------
    // Access token request.

    $params = [];
    $valid_request = TRUE;
    self::validateTokenRequestParameters($request, $reason, $valid_request, $params);
    if (!$valid_request) {
      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to obtain code: @reason', ['@reason' => $reason]);
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Missing or invalid parameters'], 400);
    }

    // -----------------------------------------------------------------
    // Get authorization code.
    // -----------------------------------------------------------------

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface $authorization_code */
    $authorization_code = $this->entityTypeManager()->getStorage('indieweb_indieauth_code')->getIndieAuthAuthorizationCode($params['code']);

    if (!$authorization_code) {
      $this->getLogger('indieweb_indieauth')->notice('No Authorization code found for @code', ['@code' => $params['code']]);
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Authorization code not found'], 404);
    }

    if (!$authorization_code->isValid()) {
      $this->getLogger('indieweb_indieauth')->notice('Authorization expired for @code', ['@code' => $params['code']]);
      return new JsonResponse(['error' => 'access_denied', 'error_description' => 'Authorization code expired'], 403);
    }

    // -----------------------------------------------------------------
    // Validate redirect_uri, me and client_id, and scope is not empty.
    // -----------------------------------------------------------------

    if ($authorization_code->getClientId() != $params['client_id']) {
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Client ID does not match'], 400);
    }
    if ($authorization_code->getRedirectURI() != $params['redirect_uri']) {
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Redirect URI does not match'], 400);
    }
    if ($authorization_code->getMe() != $params['me']) {
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Me does not match'], 400);
    }
    if (empty($authorization_code->getScopes())) {
      return new JsonResponse(['error' => 'invalid_request', 'error_description' => 'Scope is empty, can not issue access token'], 400);
    }

    // -----------------------------------------------------------------
    // Good to go, create a token!
    // -----------------------------------------------------------------

    $created = time();
    $random = new Random();
    $access_token = $random->name(128);
    $signer = new Sha512();

    $JWT = (new Builder())
      ->setIssuer(\Drupal::request()->getSchemeAndHttpHost())
      ->setAudience($authorization_code->getClientId())
      ->setId($access_token, true)
      ->setIssuedAt($created)
      ->set('uid', $authorization_code->getOwnerId())
      ->sign($signer,  file_get_contents($config->get('private_key')))
      ->getToken();

    $values = [
      'expire' => 0,
      'changed' => 0,
      'created' => $created,
      'access_token' => $access_token,
      'client_id' => $authorization_code->getClientId(),
      'uid' => $authorization_code->getOwnerId(),
      'scope' => implode(' ', $authorization_code->getScopes()),
    ];

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface $token */
    $token = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_token')->create($values);
    $token->save();

    // Remove old code.
    $authorization_code->delete();

    $data = [
      'me' => $params['me'],
      'token_type' => 'Bearer',
      'scope' => $token->getScopesAsString(),
      'access_token' => (string) $JWT,
      'profile' => $this->getProfile($token->getOwnerId()),
    ];

    return new JsonResponse($data, 200);
  }

  /**
   * Routing callback: login redirect callback.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
   */
  public function loginRedirect(Request $request) {

    $config = \Drupal::config('indieweb_indieauth.settings');
    $login_enabled = $config->get('login_enable');

    // Early return when endpoint is not enabled.
    if (!$login_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Default message.
    $message = $this->t('Access denied');

    // Verify code.
    if (!empty($request->get('code')) && $request->get('state') == session_id()) {

      // Validate the code.
      $valid_code = FALSE;
      $domain = '';

      try {
        $client = \Drupal::httpClient();
        $body = [
          'code' => $request->get('code'),
          'client_id' => \Drupal::request()->getSchemeAndHttpHost(),
          'redirect_uri' => Url::fromroute('indieweb.indieauth.login.redirect', [], ['absolute' => TRUE])->toString(),
        ];

        $headers = ['Accept' => 'application/json'];
        $authorization_endpoint = $_SESSION['indieauth_authorization_endpoint'];
        $response = $client->post($authorization_endpoint, ['form_params' => $body, 'headers' => $headers]);
        $json = json_decode($response->getBody()->getContents());
        if (isset($json->me) && isset($_SESSION['indieauth_domain']) && $json->me == $_SESSION['indieauth_domain']) {
          $domain = $_SESSION['indieauth_domain'];
          unset($_SESSION['indieauth_domain']);
          unset($_SESSION['indieauth_start']);
          unset($_SESSION['indieauth_authorization_endpoint']);
          $valid_code = TRUE;
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_indieauth')->notice('Error validating the code: @message', ['@message' => $e->getMessage()]);
      }

      // We have a valid token.
      if ($valid_code && !empty($domain)) {

        // Create authname. Strip schemes.
        $authname = str_replace(['https://', 'http://', '/'], '', $domain);
        try {

          /** @var \Drupal\externalauth\ExternalAuthInterface $external_auth */
          $external_auth = \Drupal::service('externalauth.externalauth');

          // Map with existing account.
          if ($this->currentUser()->isAuthenticated()) {
            /** @var \Drupal\user\UserInterface $account */
            $account = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
            $external_auth->linkExistingAccount($authname, 'indieweb', $account);
          }
          // Login or register the user.
          // The username can only be 60 chars long. Provide it ourselves as
          // external auth prefixes it with the provider by default. Since
          // we try to login first, there's no possibility of clashing
          // usernames.
          else {
            $username = $authname;
            if (strlen($username) > 60) {
              $username = substr($username, 0, 60);
            }
            $account = $external_auth->loginRegister($authname, 'indieweb', ['name' => $username]);
          }
          if ($account) {
            return new RedirectResponse($account->toUrl()->toString(), 302);
          }
          else {
            $message = $this->t('Unknown user, please try again.');
          }
        }
        catch (\Exception $e) {
          $this->getLogger('indieweb_indieauth_login')->notice('Error on login: @message', ['@message' => $e->getMessage()]);
          $message = $this->t('Unknown user, please try again. : @message');
        }
      }
      else {
        $message = $this->t('Invalid code, please try again.');
      }

    }

    // Default message.
    $this->messenger()->addMessage($message);

    // Redirect.
    return new RedirectResponse(Url::fromRoute('user.login')->toString(), 302);
  }

  /**
   * Change status of an IndieAuth token.
   *
   * @param \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface $indieweb_indieauth_token
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function changeStatus(IndieAuthTokenInterface $indieweb_indieauth_token) {
    if ($indieweb_indieauth_token->isRevoked()) {
      $indieweb_indieauth_token->enable();
    }
    else {
      $indieweb_indieauth_token->revoke();
    }
    $indieweb_indieauth_token->save();
    $this->messenger()->addMessage($this->t('Changed status for %token', ['%token' => $indieweb_indieauth_token->label()]));
    return new RedirectResponse($indieweb_indieauth_token->toUrl('collection')->toString());
  }

  /**
   * Returns profile information.
   *
   * @param $uid
   *
   * @return \stdClass
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getProfile($uid) {
    $profile = ['type' => 'card'];

    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entityTypeManager()->getStorage('user')->load($uid);

    if ($account) {
      $profile['name'] = $account->getAccountName();
      $profile['url'] = \Drupal::request()->getSchemeAndHttpHost();

      // Avatar.
      if (!empty($account->user_picture)) {
        /** @var \Drupal\file\FileInterface $file */
        $file = $account->get('user_picture')->entity;
        if ($file) {
          $profile['photo'] = file_create_url($file->getFileUri());
        }
      }
    }

    return (object) $profile;
  }

}

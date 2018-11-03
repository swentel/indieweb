<?php

namespace Drupal\indieweb\Controller;

use Drupal\Component\Utility\Random;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indieweb\Entity\IndieAuthTokenInterface;
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
   * The parameters needed for a code request.
   *
   * @var array
   */
  static $code_parameters = [
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
   */
  public function auth(Request $request) {

    $config = \Drupal::config('indieweb.indieauth');
    $auth_enabled = $config->get('auth_internal');

    // Early return when internal server is not enabled.
    if (!$auth_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    $reason = '';
    $valid_request = TRUE;

    // ------------------------------------------------------------------------
    // Redirect to user login if this is an anonymous user. Start a session so
    // we don't expose the details of the request on the user login page.
    // ------------------------------------------------------------------------

    if ($this->currentUser()->isAnonymous()) {
      self::checkRequiredAuthorizeParameters($request, $reason, $valid_request);
      if ($valid_request) {

        $session_params = [];
        foreach (self::$auth_parameters as $parameter) {
          if ($parameter == 'response_type') {
            $session_params['response_type'] = 'code';
          }
          else {
            $session_params[$parameter] = $request->query->get($parameter);
          }
        }
        $_SESSION['indieauth'] = $session_params;
        $this->messenger()->addMessage($this->t('Login first with your account. You will be redirected to the authorize screen on success.'));
        return new RedirectResponse(Url::fromRoute('user.login', [], ['query' => ['destination' => Url::fromRoute('indieweb.indieauth.auth')->toString()]])->toString());
      }

      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authorize as anonymous: @reason', ['@reason' => $reason]);
      return ['#markup' => 'Invalid request, missing parameters.', '#cache' => ['max-age' => 0]];
    }
    // Store in session in case the indieauth key does not exist yet.
    elseif (!isset($_SESSION['indieauth'])) {
      $session_params = [];
      foreach (self::$auth_parameters as $parameter) {
        if ($parameter == 'response_type') {
          $session_params['response_type'] = 'code';
        }
        else {
          $session_params[$parameter] = $request->query->get($parameter);
        }
      }
      $_SESSION['indieauth'] = $session_params;
    }

    // ------------------------------------------------------------------------
    // Check permission and required parameters as authenticated user.
    // ------------------------------------------------------------------------

    if (!$this->currentUser()->hasPermission('authorize with indieauth')) {
      return ['#markup' => 'You do not have permission to authorize.', '#cache' => ['max-age' => 0]];
    }

    self::checkRequiredAuthorizeParameters($request, $reason, $valid_request, TRUE);
    if (!$valid_request) {
      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to authorize as user: @reason', ['@reason' => $reason]);
      return ['#markup' => 'Invalid request, missing parameters', '#cache' => ['max-age' => 0]];
    }

    // ------------------------------------------------------------------------
    // Good to go, show the authorize form.
    // ------------------------------------------------------------------------

    $build = [];
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\indieweb\Form\IndieAuthAuthorizeForm');
    return $build;
  }

  /**
   * Check required parameters for an IndieAuth authorize request.
   *
   * @param $request
   * @param $reason
   * @param $valid_request
   * @param $in_session
   * @param $params
   */
  public static function checkRequiredAuthorizeParameters(Request $request, &$reason, &$valid_request, $in_session = FALSE, &$params = NULL) {
    foreach (self::$auth_parameters as $parameter) {
      if ($parameter == 'response_type') {

        $check = $in_session ? (isset($_SESSION['indieauth']['response_type']) ? $_SESSION['indieauth']['response_type'] : '') : $request->query->get('response_type');
        if ($check != 'code') {
          $valid_request = FALSE;
          $reason = "response type is not code";
          break;
        }
        if (is_array($params)) {
          $params['response_type'] = 'code';
        }
      }
      else {
        $check = $in_session ? (isset($_SESSION['indieauth'][$parameter]) ? $_SESSION['indieauth'][$parameter] : '') : $request->query->get($parameter);
        if (empty($check)) {
          $reason = "$parameter is empty";
          $valid_request = FALSE;
          break;
        }
        if (is_array($params)) {
          $params[$parameter] = $check;
        }
      }
    }
  }

  /**
   * Check required parameters for an IndieAuth code request.
   *
   * @param $request
   * @param $reason
   * @param $valid_request
   * @param $params
   */
  public static function checkRequiredCodeParameters(Request $request, &$reason, &$valid_request, &$params = NULL) {
    foreach (self::$code_parameters as $parameter) {
      if ($parameter == 'grant_type') {

        $check = $request->request->get('grant_type');
        if ($check != 'authorization_code') {
          $valid_request = FALSE;
          $reason = "grant_type is not authorization_code";
          break;
        }
        if (is_array($params)) {
          $params['grant_type'] = 'authorization_code';
        }
      }
      else {
        $check = $request->request->get($parameter);
        if (empty($check)) {
          $reason = "$parameter is empty";
          $valid_request = FALSE;
          break;
        }
        if (is_array($params)) {
          $params[$parameter] = $check;
        }
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

    $config = \Drupal::config('indieweb.indieauth');
    $auth_enabled = $config->get('auth_internal');

    // Early return when internal server is not enabled.
    if (!$auth_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Return early if this is not a POST request.
    if ($request->getMethod() != 'POST') {
      return new Response($this->t('Page not found'), 404);
    }

    $params = [];
    $valid_request = TRUE;
    self::checkRequiredCodeParameters($request, $reason, $valid_request, $params);
    if (!$valid_request) {
      $this->getLogger('indieweb_indieauth')->notice('Missing or invalid parameters to obtain code: @reason', ['@reason' => $reason]);
      return new JsonResponse('', 400);
    }

    // Get authorization code.
    /** @var \Drupal\indieweb\Entity\IndieAuthAuthorizationCodeInterface $authorization_code */
    $authorization_code = $this->entityTypeManager()->getStorage('indieweb_indieauth_code')->getIndieAuthAuthorizationCode($params['code']);

    if (!$authorization_code) {
      $this->getLogger('indieweb_indieauth')->notice('No Authorization code found for @code', ['@code' => $params['code']]);
      return new JsonResponse('', 404);
    }

    if (!$authorization_code->isValid()) {
      $this->getLogger('indieweb_indieauth')->notice('Authorization expired for @code', ['@code' => $params['code']]);
      return new JsonResponse('', 403);
    }

    // Good to go, create a token!
    $random = new Random();
    $values = [
      'expire' => 0,
      'changed' => 0,
      'access_token' => $random->name(128),
      'client' => $authorization_code->get('client')->value,
      'uid' => $authorization_code->get('uid')->target_id,
      'scope' => implode(' ', $authorization_code->getScopes()),
    ];

    /** @var \Drupal\indieweb\Entity\IndieAuthTokenInterface $token */
    $token = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_token')->create($values);
    $token->save();

    // Remove old code.
    $authorization_code->delete();

    $data = [
      'me' => $params['me'],
      'scope' => $token->getScopesAsString(),
      'access_token' => $token->getAccessToken(),
    ];

    return new JsonResponse($data, 200);
  }

  /**
   * Routing callback: login redirect callback.
   */
  public function loginRedirect() {

    $config = \Drupal::config('indieweb.indieauth');
    $login_enabled = $config->get('login_enable');

    // Early return when endpoint is not enabled.
    if (!$login_enabled) {
      return new Response($this->t('Page not found'), 404);
    }

    // Default message.
    $message = $this->t('Access denied');

    // Verify code.
    if (!empty($_GET['code']) && !empty($_GET['state']) && $_GET['state'] == session_id()) {

      // Validate the code.
      $valid_code = FALSE;
      $domain = '';

      try {
        $client = \Drupal::httpClient();
        $body = [
          'code' => $_GET['code'],
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

          // Map or login/register
          if ($this->currentUser()->isAuthenticated()) {
            /** @var \Drupal\user\UserInterface $account */
            $account = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());
            $external_auth->linkExistingAccount($authname, 'indieweb', $account);
          }
          // Login or register the user.
          else {
            // The username can only be 60 chars long. Provide it ourselves as
            // external auth prefixes it with the provider by default. Since
            // we try to login first, there's no possibility of clashing
            // usernames.
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
   * @param \Drupal\indieweb\Entity\IndieAuthTokenInterface $indieweb_indieauth_token
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

}

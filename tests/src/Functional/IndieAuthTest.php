<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

/**
 * Tests integration of IndieAuth.
 *
 * @group indieweb
 */
class IndieAuthTest extends IndiewebBrowserTestBase {

  protected $link_tag_auth_endpoint = '<link rel="authorization_endpoint" href="https://indieauth.com/auth" />';
  protected $link_tag_token_endpoint = '<link rel="token_endpoint" href="https://tokens.indieauth.com/token" />';

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'block',
    'node',
    'indieweb',
    'indieweb_indieauth',
    'indieweb_test',
    'indieweb_micropub',
  ];

  /**
   * An indieweb authorized user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $indiewebAuthorizedUser;

  /**
   * Another indieweb authorized user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $indiewebAuthorizedUser2;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Page content type.
    $this->createContentType(['type' => 'page']);
  }

  /**
   * Tests indieauth functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testIndieAuth() {

    // ------------------------------------------------------------------------
    // Header link expose.
    // ------------------------------------------------------------------------

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->link_tag_auth_endpoint);
    $this->assertSession()->responseNotContains($this->link_tag_token_endpoint);

    $this->drupalGet('admin/config/services/indieweb/indieauth');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $edit = ['expose_link_tag' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->link_tag_auth_endpoint);
    $this->assertSession()->responseContains($this->link_tag_token_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->link_tag_auth_endpoint);
    $this->assertSession()->responseContains($this->link_tag_token_endpoint);

    // Do not expose.
    $this->drupalLogin($this->adminUser);
    $edit = ['expose_link_tag' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->link_tag_auth_endpoint);
    $this->assertSession()->responseNotContains($this->link_tag_token_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->link_tag_auth_endpoint);
    $this->assertSession()->responseNotContains($this->link_tag_token_endpoint);

    // ------------------------------------------------------------------------
    // Test the user sign in. We use a custom login endpoint for authentication.
    // ------------------------------------------------------------------------

    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseContains('Web Sign-In is not enabled');

    // Redirect endpoint should 404.
    $this->drupalGet('indieauth/login/redirect');
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalLogin($this->adminUser);
    $edit = ['login_enable' => '1'];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');
    $this->drupalLogout();

    // Redirect endpoint without params should redirect.
    $this->drupalGet('indieauth/login/redirect');
    $this->assertSession()->addressEquals('user/login');

    // Use test login page.
    $authorization_endpoint = Url::fromRoute('indieweb_test.indieauth.login.endpoint', [], ['absolute' => TRUE])->toString();
    $domain = Url::fromroute('indieweb_test.indieauth.discover_page_one', [], ['absolute' => TRUE])->toString();
    $this->drupalGet($domain);
    $this->assertSession()->responseContains($authorization_endpoint);
    $edit = ['domain' => $domain];
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseNotContains('Map your domain with your current user.');
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/3');

    // Login again, should be same user.
    $this->drupalLogout();
    $edit = ['domain' => $domain];
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/3');

    // No map text.
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseNotContains('Map your domain with your current user.');

    // Now let's check the edit form as the indieauth user, you should not be
    // able to set the password or change your username as that is used for
    // external auth.
    $this->drupalGet('user/3/edit');
    $this->assertSession()->responseNotContains('Required if you want to change the');
    $this->assertSession()->responseNotContains('To change the current user password, enter the new password in both fields.');
    $this->assertSession()->responseNotContains('Several special characters are allowed');

    /** @var \Drupal\user\UserInterface $account */
    $account = \Drupal::entityTypeManager()->getStorage('user')->load(3);
    self::assertTrue(empty($account->getEmail()));

    // Normal user can change it.
    $this->drupalLogout();
    $another = $this->drupalCreateUser(['access content', 'change own username'], 'anotheruser');
    $this->drupalLogin($another);
    $this->drupalGet('user/4/edit');
    $this->assertSession()->responseContains('Required if you want to change the');
    $this->assertSession()->responseContains('To change the current user password, enter the new password in both fields.');
    $this->assertSession()->responseContains('Several special characters are allowed');

    // Check fields as admin user that everything is there.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('user/2/edit');
    $this->assertSession()->responseContains('Required if you want to change the');
    $this->assertSession()->responseContains('To change the current user password, enter the new password in both fields.');
    $this->assertSession()->responseContains('Several special characters are allowed');
    $this->drupalGet('user/3/edit');
    $this->assertSession()->responseNotContains('Required if you want to change the');
    $this->assertSession()->responseContains('To change the current user password, enter the new password in both fields.');
    $this->assertSession()->responseContains('Several special characters are allowed');
    $this->drupalGet('user/4/edit');
    $this->assertSession()->responseNotContains('Required if you want to change the');
    $this->assertSession()->responseContains('To change the current user password, enter the new password in both fields.');
    $this->assertSession()->responseContains('Several special characters are allowed');
    $this->drupalLogout();

    // Change e-mail.
    $edit = ['domain' => $domain];
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $edit = ['mail' => 'indieweb@example.com'];
    $this->drupalPostForm('/user/3/edit', $edit, 'Save');
    /** @var \Drupal\user\UserInterface $account_updated */
    $account_updated = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged(3);
    self::assertEquals('indieweb@example.com', $account_updated->getEmail());
    $this->drupalLogout();

    // Map existing user with domain.
    $this->drupalLogin($another);
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseContains('Map your domain with your current user.');
    $map_domain = Url::fromroute('indieweb_test.indieauth.discover_page_two', [], ['absolute' => TRUE])->toString();
    $edit = ['domain' => $map_domain];
    $this->drupalPostForm('indieauth-test/login', $edit, '');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/4');
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseNotContains('Map your domain with your current user.');

  }

  /**
   * Tests the built-in IndieAuth functionality.
   *
   * The authorization is handled inline. When we want to get the access token
   * we'll do the request ourselves by fetching the code from the url and so on.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function testBuiltInServer() {

    $this->httpClient = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => $this->baseUrl]);

    // IndieWeb authorized users.
    $role = $this->drupalCreateRole(['authorize with indieauth']);
    $this->indiewebAuthorizedUser = $this->drupalCreateUser();
    $this->indiewebAuthorizedUser->addRole($role);
    $this->indiewebAuthorizedUser->save();
    $this->indiewebAuthorizedUser2 = $this->drupalCreateUser();
    $this->indiewebAuthorizedUser2->addRole($role);
    $this->indiewebAuthorizedUser2->save();

    // Normal user.
    $this->authUser = $this->drupalCreateUser();

    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_enable' => 1, 'note_create_node' => 1, 'note_node_type' => 'page', 'note_uid' => $this->adminUser->id(), 'issue_node_type' => 'page'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $auth_path = 'indieauth/auth';
    $token_path = Url::fromRoute('indieweb.indieauth.token', [], ['absolute' => TRUE])->toString();
    $me = Url::fromRoute('indieweb_test.indieauth.discover_page_one', [], ['absolute' => TRUE])->toString();
    $redirect_uri = Url::fromRoute('indieweb_test.auth_redirect', [], ['absolute' => TRUE])->toString();

    // -------------------------------------------------------
    // Not enabled
    // -------------------------------------------------------

    $this->drupalGet($auth_path);
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet($token_path);
    $this->assertSession()->statusCodeEquals(404);

    // Enable internal.
    $this->setIndieAuthInternal();

    // -------------------------------------------------------
    // Try to authorize as a normal user with no permission
    // -------------------------------------------------------

    $this->drupalGet($auth_path);
    $this->assertSession()->responseContains('Invalid request, missing parameters');

    $state = $this->randomGenerator->name(10);

    $options = [
      'query' => [
        'response_type' => 'code',
        'redirect_uri' => $redirect_uri,
        'client_id' => 'Indigenous Android',
        'me' => $me,
        'scope' => 'read create',
        'state' => $state,
      ]
    ];
    $this->drupalGet($auth_path, $options);
    $this->assertSession()->responseContains('Login first with your account. You will be redirected to the authorize screen on success.');

    $edit = ['name' => $this->authUser->getAccountName(), 'pass' => $this->authUser->pass_raw];
    $this->drupalPostForm(NULL, $edit, 'Log in');
    $this->assertSession()->responseContains('You do not have permission to authorize.');
    $this->drupalLogout();

    // -------------------------------------------------------
    // Authorize as a user with permission
    // -------------------------------------------------------

    $this->drupalGet($auth_path, $options);
    $this->assertSession()->responseContains('Login first with your account. You will be redirected to the authorize screen on success.');

    $edit = ['name' => $this->indiewebAuthorizedUser->getAccountName(), 'pass' => $this->indiewebAuthorizedUser->pass_raw];
    $this->drupalPostForm(NULL, $edit, 'Log in');
    $this->assertSession()->responseContains('would like to access your site');
    $this->drupalPostForm(NULL, [], 'Authorize');
    $url = parse_url($this->getUrl());
    $query = [];
    parse_str($url['query'],$query);
    self::assertEquals($query['state'], $state);
    $code = $query['code'];

    // Call token endpoint for an access token now.
    $params = [
      'code' => $code,
      'me' => $me,
      'redirect_uri' => $redirect_uri,
      'client_id' => 'Indigenous Android',
      'grant_type' => 'authorization_code',
    ];

    // some failing ones first.
    $post = $params;
    unset($post['me']);
    $response = $this->postToUrl($post, $token_path);
    self::assertEquals(400, $response->getStatusCode());

    $post = $params;
    $post['code'] = 'random';
    $response = $this->postToUrl($post, $token_path);
    self::assertEquals(404, $response->getStatusCode());

    // Now a good one.
    $response = $this->postToUrl($params, $token_path);
    $body_response = $response->getBody()->__toString();
    $body = @json_decode($body_response);
    self::assertTrue(isset($body->access_token));
    self::assertEquals(200, $response->getStatusCode());

    // Verify the authorization code is gone.
    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface $authorization_code */
    $authorization_code = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_code')->getIndieAuthAuthorizationCode($params['code']);
    self::assertFalse($authorization_code);

    // Now do a micropub request with the access token.
    $post = [
      'h' => 'entry',
      'content' => 'A note content',
    ];
    $code = $this->sendMicropubRequest($post, $body->access_token);
    self::assertEquals(201, $code);

    $code = $this->sendMicropubRequest($post, 'unknown');
    self::assertEquals(403, $code);

    $this->drupalGet($token_path);
    $this->assertSession()->statusCodeEquals(404);

    // -------------------------------------------------------
    // No 'response_type' or scope in this request since they
    // are optional as authenticated user.
    // -------------------------------------------------------

    $this->drupalLogout();
    $this->drupalLogin($this->indiewebAuthorizedUser2);
    unset($options['query']['response_type']);
    unset($options['query']['scope']);
    $this->drupalGet($auth_path, $options);
    // Simply seeing the authorize screen is good enough.
    $this->assertSession()->responseContains('would like to access your site');

    // -------------------------------------------------------
    // No 'response_type' or scope  in this request since they
    // are optional as anonymous user
    // -------------------------------------------------------

    $this->drupalLogout();
    $this->drupalGet($auth_path, $options);
    // Simply seeing the user login screen is good enough.
    $this->assertSession()->responseContains('Login first with your account. You will be redirected to the authorize screen on success.');

    // -------------------------------------------------------
    // Response type invalid
    // -------------------------------------------------------

    $options['query']['response_type'] = 'invalid_value_for_response_type';
    $this->drupalGet($auth_path, $options);
    $this->assertSession()->responseContains('Invalid request, missing parameters');

    // -------------------------------------------------------
    // Do the authentication dance, this flow asks for an
    // authorization code, and a post request is made back to
    // the auth endpoint to verify the user.
    // -------------------------------------------------------

    $options = [
      'query' => [
        'redirect_uri' => $redirect_uri,
        'client_id' => 'Indigenous Android',
        'me' => $me,
        'state' => $state,
      ]
    ];
    $this->drupalGet($auth_path, $options);

    $edit = ['name' => $this->indiewebAuthorizedUser->getAccountName(), 'pass' => $this->indiewebAuthorizedUser->pass_raw];
    $this->drupalPostForm(NULL, $edit, 'Log in');
    $this->assertSession()->responseContains('would like to access your site');
    $this->drupalPostForm(NULL, [], 'Authorize');
    $url = parse_url($this->getUrl());
    $query = [];
    parse_str($url['query'],$query);
    self::assertEquals($query['state'], $state);
    $code = $query['code'];

    // Call auth endpoint token now.
    $params = [
      'code' => $code,
      'redirect_uri' => $redirect_uri,
      'client_id' => 'Indigenous Android',
    ];

    // First with bad or missing client_id, code or redirect_uri
    $post = $params;
    $post['code'] = 'random';
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(404, $response->getStatusCode());

    $post = $params;
    $post['redirect_uri'] = 'not matching';
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(403, $response->getStatusCode());

    $post = $params;
    $post['client_id'] = 'another client';
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(403, $response->getStatusCode());

    $post = $params;
    unset($post['code']);
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(400, $response->getStatusCode());

    $post = $params;
    unset($post['client_id']);
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(400, $response->getStatusCode());

    $post = $params;
    unset($post['redirect_uri']);
    $response = $this->postToUrl($post, $auth_path);
    self::assertEquals(400, $response->getStatusCode());

    // Now a valid one.
    $response = $this->postToUrl($params, $auth_path);
    $body_response = $response->getBody()->__toString();
    $body = @json_decode($body_response);
    self::assertTrue(isset($body->me) && $body->me == $me);
    self::assertEquals(200, $response->getStatusCode());

    // Verify the authorization code is gone.
    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface $authorization_code */
    $authorization_code = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_code')->getIndieAuthAuthorizationCode($params['code']);
    self::assertFalse($authorization_code);
  }

  /**
   * Post to a URL.
   *
   * @param $params
   * @param $url
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The request response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function postToUrl($params, $url) {

    $headers = [
      'Accept' => 'application/json',
    ];

    try {
      $response = $this->httpClient->request('post', $url, ['form_params' => $params, 'headers' => $headers]);
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
    }
    catch (ServerException $e) {
      $response = $e->getResponse();
    }

    return $response;
  }

}

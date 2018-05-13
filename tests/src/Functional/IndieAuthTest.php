<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;

/**
 * Tests integration of indieauth.
 *
 * @group indieweb
 */
class IndieAuthTest extends IndiewebBrowserTestBase {

  protected $header_link_auth_endpoint = '<link rel="authorization_endpoint" href="https://indieauth.com/auth" />';
  protected $header_link_token_endpoint = '<link rel="token_endpoint" href="https://tokens.indieauth.com/token" />';

  /**
   * Tests indieauth functionality.
   */
  public function testIndieAuth() {

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_link_auth_endpoint);
    $this->assertSession()->responseNotContains($this->header_link_token_endpoint);

    $this->drupalGet('admin/config/services/indieweb/indieauth');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link_auth_endpoint);
    $this->assertSession()->responseContains($this->header_link_token_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link_auth_endpoint);
    $this->assertSession()->responseContains($this->header_link_token_endpoint);

    // Do not expose.
    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_link_auth_endpoint);
    $this->assertSession()->responseNotContains($this->header_link_token_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_link_auth_endpoint);
    $this->assertSession()->responseNotContains($this->header_link_token_endpoint);

    // Test the user sign in. We use a custom login endpoint for authentication.
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseContains('Web Sign-In is not enabled');

    // Redirect endpoint should 404.
    $this->drupalGet('indieauth/login/redirect');
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalLogin($this->adminUser);
    $edit = ['login_enable' => '1', 'login_endpoint' => Url::fromRoute('indieweb_test.indieauth.login.endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');
    $this->drupalLogout();

    // Redirect endpoint without params should redirect.
    $this->drupalGet('indieauth/login/redirect');
    $this->assertSession()->addressEquals('user/login');

    // Use test login page.
    $edit = ['domain' => 'https://example.com'];
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/3');

    // Login again, should be same user.
    $this->drupalLogout();
    $edit = ['domain' => 'https://example.com'];
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/3');
  }

}

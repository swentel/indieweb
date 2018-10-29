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
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseNotContains('Map your domain with your current user.');
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/3');
    $this->assertSession()->responseContains('indieweb_example.com');
    $this->assertSession()->responseNotContains('indieweb_example.com/');

    // Login again, should be same user.
    $this->drupalLogout();
    $edit = ['domain' => 'https://example.com'];
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
    $edit = ['domain' => 'https://example.com'];
    $this->drupalPostForm('indieauth-test/login', $edit, 'Sign in');
    $edit = ['mail' => 'indieweb@example.com'];
    $this->drupalPostForm('/user/3/edit', $edit, 'Save');
    /** @var \Drupal\user\UserInterface $account_updated */
    $account_updated = \Drupal::entityTypeManager()->getStorage('user')->loadUnchanged(3);
    self::assertEquals('indieweb@example.com', $account_updated->getEmail());

    // Make sure you can't login with user 3 since it has no password.
    $this->drupalLogout();
    $this->drupalPostForm('user/login', ['name' => 'indieweb_example.com', 'pass' => ''], 'Log in');
    $this->assertSession()->responseContains('Password field is required');

    // Map existing user with domain.
    $this->drupalLogin($another);
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseContains('Map your domain with your current user.');
    $edit = ['domain' => 'https://example-map.com/'];
    $this->drupalPostForm('indieauth-test/login', $edit, '');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('user/4');
    $this->drupalGet('indieauth-test/login');
    $this->assertSession()->responseNotContains('Map your domain with your current user.');

  }

}

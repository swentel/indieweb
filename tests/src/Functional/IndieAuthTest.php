<?php

namespace Drupal\Tests\indieweb\Functional;

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
  }

}

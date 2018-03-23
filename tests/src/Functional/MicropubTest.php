<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of micropub.
 *
 * @group indieweb
 */
class MicropubTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Tests micropub functionality.
   */
  public function testMicropub() {
    $this->drupalGet('<front>');

    // Request to micropub should be a 404.
    $this->drupalGet('indieweb/micropub');
    $this->assertSession()->statusCodeEquals(404);

    // Enable the endpoint.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/micropub', ['micropub_enable' => 1, 'micropub_add_header_link' => 1], 'Save configuration');
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');
    $this->drupalGet('indieweb/micropub');
    $this->assertSession()->statusCodeEquals(400);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');

  }

}

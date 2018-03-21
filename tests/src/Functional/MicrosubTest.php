<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of microsub.
 *
 * @group indieweb
 */
class MicrosubTest extends IndiewebBrowserTestBase {

  protected $header_link = '<link rel="microsub" href="https://example.com/microsub" />';

  /**
   * Tests microsub functionality.
   */
  public function testMicrosub() {

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_link);

    $this->drupalGet('admin/config/services/indieweb/microsub');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'microsub_endpoint' => 'https://example.com/microsub'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link);
  }

}

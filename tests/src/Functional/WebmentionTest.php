<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of webmentions.
 *
 * @group indieweb
 */
class WebmentionTest extends IndiewebBrowserTestBase {

  protected $webmention_endpoint = '<link rel="webmention" href="https://webmention.io/example.com/webmention" />';
  protected $pingback_endpoint = '<link rel="pingback" href="https://webmention.io/webmention?forward=http://example.com/webmention/notify" />';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'page']);
  }

  /**
   * Tests webmention functionality.
   */
  public function testWebmention() {

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $this->drupalGet('admin/config/services/indieweb/webmention');
    $this->assertSession()->statusCodeEquals(403);

    $code = $this->sendWebmentionRequest();
    self::assertEquals(404, $code);

    $this->drupalLogin($this->adminUser);
    $this->enableWebmention();

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->webmention_endpoint);
    $this->assertSession()->responseContains($this->pingback_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->webmention_endpoint);
    $this->assertSession()->responseContains($this->pingback_endpoint);

    // Do not expose.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'webmention_endpoint' => '',
      'pingback_endpoint' => '',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $node = $this->drupalCreateNode(['type' => 'page']);
    $node_2 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is not on']]);
    $node_3 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is on! ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString()]]);

    // Send a webmention request, try invalid one first, then a valid.
    $webmention = [
      'secret' => 'in_valid_secret',
      'source' => 'external.com',
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'post' => [
        'type' => 'entry',
        'wm-property' => 'like-of',
        'content' => [
          'text' => 'Webmention from external.com'
        ],
      ],
    ];
    $code = $this->sendWebmentionRequest($webmention);
    self::assertEquals(400, $code);
    $webmention['secret'] = 'valid_secret';
    $code = $this->sendWebmentionRequest($webmention);
    self::assertEquals(202, $code);

    // Test pingback.
    $pingback = [
      'source' => 'blahahaasd;flkjsf',
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionRequest($pingback, FALSE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_2->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionRequest($pingback, FALSE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionRequest($pingback, FALSE);
    $this->drupalGet('node/' . $node_3->id());
    self::assertEquals(202, $code);

  }


}

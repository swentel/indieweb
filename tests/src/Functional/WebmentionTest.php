<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;
use Drupal\indieweb_test\WebmentionClient\WebmentionClientTest;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

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
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['view published webmention entities']);
  }

  /**
   * Tests receiving webmention functionality.
   */
  public function testReceivingWebmention() {

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $this->drupalGet('admin/config/services/indieweb/webmention');
    $this->assertSession()->statusCodeEquals(403);

    $code = $this->sendWebmentionNotificationRequest();
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
    $webmention = $this->getWebmentionPayload($node);
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(400, $code);
    $webmention['secret'] = 'valid_secret';
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);

    // Test pingback.
    $pingback = [
      'source' => 'blahahaasd;flkjsf',
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionNotificationRequest($pingback, FALSE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_2->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionNotificationRequest($pingback, FALSE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendWebmentionNotificationRequest($pingback, FALSE);
    $this->drupalGet('node/' . $node_3->id());
    self::assertEquals(202, $code);

  }

  /**
   * Tests sending webmentions.
   */
  public function testSendingWebmention() {

    // Test that the test indieweb client is used. If not, we bail out because
    // otherwise we bleed into the live site, and possibly start pinging
    // webmention.io.
    $class = \Drupal::service('indieweb.webmention.client');
    self::assertTrue($class instanceof WebmentionClientTest);

    $test_endpoint = Url::fromRoute('indieweb_test.webmention_endpoint', [], ['absolute' => TRUE])->toString();
    $this->drupalLogin($this->adminUser);
    $this->enableWebmention(['webmention_endpoint' => $test_endpoint]);

    $edit = ['publish_custom_url' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/publish', $edit, 'Save configuration');

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Best article ever']);
    $node_1_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains($test_endpoint);

    $this->createPage($node_1_url, FALSE, TRUE);
    $this->assertQueueItems([$node_1_url], 2);

    // Handle queue, should not be send yet.
    $this->runWebmentionQueue();
    $this->assertQueueItems([$node_1_url]);

    // Send by cron.
    $edit = ['publish_send_webmention_by' => 'cron'];
    $this->drupalPostForm('admin/config/services/indieweb/publish', $edit, 'Save configuration');
    $this->runWebmentionQueue();
    $this->assertQueueItems();

    // Send by drush.
    $this->createPage($node_1_url, FALSE, TRUE);
    $this->assertQueueItems([$node_1_url]);
    $edit = ['publish_send_webmention_by' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/publish', $edit, 'Save configuration');
    $this->runWebmentionQueue();
    $this->assertQueueItems();

    // Put node 1 in channels, so it can be a publish URL, so we can test
    // syndications.
    $edit = ['channels' => 'Twitter (bridgy)|' . $node_1_url];
    $this->drupalPostForm('admin/config/services/indieweb/publish', $edit, 'Save configuration');

    $this->createPage($node_1_url, TRUE);
    $this->assertQueueItems([$node_1_url]);
    $this->runWebmentionQueue();
    $this->assertQueueItems();
    $this->assertSyndication(4, $node_1_url);

  }

  /**
   * Tests the Webmention Block
   */
  public function testWebmentionInteractionsBlock() {

    $this->drupalLogin($this->adminUser);
    $this->enableWebmention();

    $this->placeBlock('indieweb_webmention', ['region' => 'content', 'label' => 'Interactions', 'id' => 'webmention']);
    $this->createPage();
    $this->createPage();
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $node_2 = \Drupal::entityTypeManager()->getStorage('node')->load(2);
    $this->drupalLogout();

    $this->drupalGet('node/1');
    $this->assertSession()->responseNotContains('Interactions');

    $webmention = $this->getWebmentionPayload($node, 'valid_secret');
    $webmention['post']['author'] = ['name' => 'swentel'];
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['author'] = ['name' => 'Dries'];
    $webmention['post']['wm-property'] = 'mention-of';
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['wm-property'] = 'repost-of';
    $webmention['post']['author'] = ['name' => 'swentie'];
    $this->sendWebmentionNotificationRequest($webmention);

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseNotContains('Dries');

    $this->drupalGet('node/2');
    $this->assertSession()->responseNotContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseNotContains('Dries');

    $edit = ['settings[webmentions][show_reposts]' => TRUE];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');
    $this->assertSession()->responseNotContains('Dries');

    $webmention['target'] = $node_2->toUrl('canonical', ['absolute' => TRUE])->toString();
    $webmention['post']['wm-property'] = 'repost-of';
    $webmention['post']['author'] = ['name' => 'Dries'];
    $this->sendWebmentionNotificationRequest($webmention);

    $this->drupalGet('node/2');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseContains('Reposted by Dries');

    $edit = ['settings[webmentions][number_of_posts]' => 1];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');

    $edit = ['settings[webmentions][number_of_posts]' => 0];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');
  }

  /**
   * Change block configuration.
   *
   * @param $block_id
   *   The block id.
   * @param $edit
   *   The edit
   */
  protected function changeBlockConfiguration($block_id, $edit) {
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/structure/block/manage/' . $block_id, $edit, 'Save block');
    $this->drupalLogout();
  }

  /**
   * Creates a page and send webmention to url.
   *
   * @param $target_url
   * @param $publish
   * @param $custom
   */
  protected function createPage($target_url = '', $publish = FALSE, $custom = FALSE, $edit = []) {
    $edit += [
      'title[0][value]' => 'It sure it!',
      'body[0][value]' => 'And here is mine!',
    ];

    if ($publish) {
      $edit['indieweb_publish_channels[' . $target_url . ']'] = TRUE;
    }

    if ($custom) {
      $edit['indieweb_publish_custom_url'] = $target_url;
    }

    $this->drupalPostForm('node/add/page', $edit, 'Save');
  }
}

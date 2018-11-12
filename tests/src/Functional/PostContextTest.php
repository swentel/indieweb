<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;
use Drupal\indieweb_test\WebmentionClient\WebmentionClientTest;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests integration of post context.
 *
 * @group indieweb
 */
class PostContextTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'block',
    'node',
    'indieweb',
    'indieweb_context',
    'indieweb_microformat',
    'indieweb_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createNodeTypes(['reply']);

    // Configure microformats.
    $edit = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_name_exclude_node_type' => 'page',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');

    drupal_flush_all_caches();
  }

  /**
   * Tests post context.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testPostContext() {

    $this->drupalLogin($this->adminUser);

    $edit = ['post_context_link_field' => 'field_reply_link', 'post_context_post_type' => 'u-in-reply-to'];
    $this->drupalPostForm('admin/structure/types/manage/reply', $edit, 'Save content type');
    $node_type = NodeType::load('reply');
    self::assertEquals($node_type->getThirdPartySetting('indieweb_context', 'post_context_link_field'), 'field_reply_link');
    self::assertEquals($node_type->getThirdPartySetting('indieweb_context', 'post_context_post_type'), 'u-in-reply-to');

    // Create page for context.
    $page_settings = [
      'type' => 'page',
      'title' => 'A context page',
      'body' => ['value' => 'We will see this hopefully on that page as context']
    ];
    $page = $this->createNode($page_settings);

    $reply_settings = [
      'type' => 'reply',
      'title' => 'Wow, that works !',
      'body' => ['value' => 'Nicely done man!'],
      'field_reply_link' => ['uri' => $page->toUrl('canonical', ['absolute' => TRUE])->toString()]
    ];
    $reply = $this->createNode($reply_settings);

    // No queue items.
    $this->assertPostContextQueueItems();
    $reply->delete();

    // Enable drush and configure post context field.
    $edit = ['handler' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/post-context', $edit, 'Save configuration');
    $edit = ['fields[indieweb_post_context][region]' => 'content'];
    $this->drupalPostForm('admin/structure/types/manage/reply/display', $edit, 'Save');

    $reply = $this->createNode($reply_settings);
    $this->assertPostContextQueueItems([$page->toUrl('canonical', ['absolute' => TRUE])->toString()], $reply->id());

    $this->runPostContextQueue();
    $this->assertPostContextQueueItems();
    $this->assertPostContextItem('node', $reply->id());

    $this->drupalGet('node/' . $reply->id());
    $this->assertSession()->responseContains($page->get('body')->value);
    $this->assertSession()->responseContains('u-in-reply-to');

    // Test with cron handler.
    $edit = ['handler' => 'cron'];
    $this->drupalPostForm('admin/config/services/indieweb/post-context', $edit, 'Save configuration');

    $reply->delete();
    $reply = $this->createNode($reply_settings);
    $this->assertPostContextQueueItems([$page->toUrl('canonical', ['absolute' => TRUE])->toString()], $reply->id());

    $this->runPostContextQueue();
    $this->assertPostContextQueueItems();
    $this->assertPostContextItem('node', $reply->id());

    $this->drupalGet('node/' . $reply->id());
    $this->assertSession()->responseContains($page->get('body')->value);
    $this->assertSession()->responseContains('u-in-reply-to');
  }

}

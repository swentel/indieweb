<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests integration of Comment creation on webmention with 'in-reply-to'.
 *
 * @group indieweb
 */
class CommentsTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create node types.
    $this->createNodeTypes(['reply']);

    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['view published webmention entities', 'access comments']);
  }

  /**
   * Tests comments functionality.
   */
  public function testComments() {

    $this->drupalLogin($this->adminUser);
    $this->enableWebmention();
    $edit = ['existing_storage_name' => 'indieweb_webmention', 'existing_storage_label' => 'Webmention reference'];
    $this->drupalPostForm('admin/structure/comment/manage/comment/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm('admin/structure/comment/manage/comment/display', ['fields[indieweb_webmention][type]' => 'entity_reference_entity_view'], 'Save');

    // Create link field on comments.
    $edit = ['new_storage_type' => 'link', 'label' => 'Link', 'field_name' => 'comment_link'];
    $this->drupalPostForm('admin/structure/comment/manage/comment/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');
    drupal_flush_all_caches();

    // Configure microformats.
    $edit = [
      'h_entry_comment' => 1,
      'e_content_comment' => 1,
      'post_metadata_comment' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');


    // Create article.
    $edit = [
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
    ];
    $this->drupalPostForm('node/add/article', $edit, 'Save');

    // Logout now.
    $this->drupalLogout();

    // Send a webmention request, will not create a comment.
    $webmention = [
      'secret' => 'valid_secret',
      'source' => 'http://external.com',
      // Testbot uses subdir, use hardcoded path here.
      'target' => '/node/1',
      'post' => [
        'type' => 'entry',
        'wm-property' => 'in-reply-to',
        'content' => [
          'text' => 'Wow, this is a great module!'
        ],
      ],
    ];
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(0);

    // Enable comments creation and selection webmention field. The other fields
    // will be ok by default.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'comment_create_enable' => 1,
      'comment_create_webmention_reference_field' => 'indieweb_webmention',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/comments', $edit, 'Save configuration');

    // Set send fields.
    $edit = [];
    $edit['send_custom_url'] = 1;
    $edit['send_link_fields[]'] = ['field_comment_link'];
    $this->drupalPostForm('admin/config/services/indieweb/send', $edit, 'Save configuration');
    $this->assertSession()->responseNotContains('An illegal choice has been detected.');

    // Send again, we should have a comment now.
    $this->drupalLogout();
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(1);
    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data}')->fetchField();
    if ($cid) {
      $this->drupalGet('comment/' . $cid . '/edit');
      /** @var \Drupal\comment\CommentInterface $comment */
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      self::assertEquals(FALSE, $comment->isPublished());
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }

    // We should not have a notification.
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $this->assertFalse($captured_emails, 'No notification mail for comment.');

    // Publish the comment.
    $comment->setPublished();
    $comment->save();

    // Check the comment is visible.
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Wow, this is a great module!');

    // Body should be gone on edit as it's empty.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('comment/' . $cid . '/edit');
    $this->assertSession()->fieldNotExists('comment_body[0][value]');

    // Check that no microformats are rendered.
    $this->drupalGet('/comment/indieweb/1');
    $this->assertSession()->responseContains('Wow, this is a great module!');
    $this->assertSession()->responseNotContains('h-entry');
    $this->assertSession()->responseNotContains('dt-published');

    // Set mail notification.
    $edit = [
      'comment_create_mail_notification' => 'no-reply@example.com',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/comments', $edit, 'Save configuration');
    $this->drupalLogout();

    // Send a webmention now to the comment, this should create another comment
    // on node 1. The target is comment/indieweb/cid.
    $webmention['target'] = '/comment/indieweb/1';
    $webmention['post']['content']['text'] = 'This is awesome!';
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(2);

    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data} ORDER by cid DESC limit 1')->fetchField();
    if ($cid) {
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      self::assertEquals(FALSE, $comment->isPublished());
      self::assertEquals('1', $comment->getParentComment()->id());
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }

    // We should have a mail notification.
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $notification = end($captured_emails);
    $this->assertTrue($notification && $notification['id'] == 'indieweb_webmention_comment_created', 'Notification mail found for comment.');

    // Publish the comment.
    $comment->setPublished();
    $comment->save();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('This is awesome!');

    // Reply to the comment from the site.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'comment_body[0][value]' => "I know, isn't it!",
      'indieweb_send_custom_url' => 'https://brid-gy.appspot.com/comment/twitter/swentel/994117538731741185/994129251946418177',
    ];
    $this->drupalPostForm('comment/reply/node/1/comment/' . $comment->id(), $edit, 'Save');
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains("I know, isn't it!");
    $this->assertQueueItems(['https://brid-gy.appspot.com/comment/twitter/swentel/994117538731741185/994129251946418177' => 'https://brid-gy.appspot.com/comment/twitter/swentel/994117538731741185/994129251946418177'], 3);

    $this->drupalGet('/comment/indieweb/3');
    $this->assertSession()->responseContains("I know, isn't it!");
    $this->assertSession()->responseContains('h-entry');
    $this->assertSession()->responseContains('dt-published');

    // Save a syndication for this one, so we can detect that a webmention from
    // brid.gy will not create the same comment again as it already exists.
    $this->createSyndication('https://twitter.com/swentel/status/994133390680092672', 'node', 1);
    $webmention['target'] = '/comment/indieweb/2';
    $webmention['source'] = 'https://brid-gy.appspot.com/comment/twitter/swentel/994117538731741185/994133390680092672';
    $webmention['post']['content']['text'] = "I know, isn't it!";
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(3);

    // ------------------------------------------------------------------------
    // Test micropub reply post to create a comment.

    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_enable' => 1, 'reply_create_comment' => 1, 'reply_create_node' => 1, 'reply_node_type' => 'reply', 'reply_link_field' => 'field_reply_link', 'reply_content_field' => 'body', 'reply_auto_send_webmention' => 1, 'reply_uid' => $this->adminUser->id()];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Set IndieAuth token endpoint.
    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 1, 'token_endpoint' => Url::fromRoute('indieweb_test.token_endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    $this->drupalLogout();

    // Create an article.
    $article = $this->createNode(['type' => 'article', 'title' => 'Test generating comments via micropub', 'body' => ['value' => 'This will be awesome when it works!']]);
    $this->drupalGet('node/' . $article->id());
    $this->assertSession()->responseContains('This will be awesome when it works!');

    // Now send a webmention to this article (with different source).
    $webmention['target'] = '/node/' . $article->id();
    $webmention['source'] = 'https://brid-gy.appspot.com/comment/twitter/swentel/1039829498676563968/1039835297159282688';
    $webmention['post']['url'] = 'https://twitter.com/jgmac1106/status/1039835297159282688';
    $webmention['post']['content']['text'] = "This comes in via a webmention";
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(4);

    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data} ORDER by cid DESC limit 1')->fetchField();
    if ($cid) {
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      self::assertEquals(FALSE, $comment->isPublished());
      self::assertEquals($article->id(), $comment->getCommentedEntityId());
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }
    $comment->setPublished();
    $comment->save();
    $this->drupalGet('node/' . $article->id());
    $this->assertSession()->responseContains('This comes in via a webmention');
    $webmention_cid = $cid;

    $reply = [
      'h' => 'entry',
      'in-reply-to' => '/node/' . $article->id(),
      'content' => 'Reply with node target'
    ];

    // Micropub reply request with 'in-reply-to' set to node.
    $this->sendMicropubRequest($reply);
    $this->assertNodeCount(0, 'reply');
    $this->assertCommentCount(5);

    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data} ORDER by cid DESC limit 1')->fetchField();
    if ($cid) {
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      $comment->setPublished();
      $comment->save();
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }

    $this->drupalGet('node/' . $article->id());
    $this->assertSession()->responseContains('Reply with node target');

    // Micropub reply request with 'in-reply-to' set to previous comment.
    $previous_cid = $cid;
    $reply['content'] = 'Reply with comment target';
    $reply['in-reply-to'] = '/comment/indieweb/' . $previous_cid;
    $this->sendMicropubRequest($reply);
    $this->assertNodeCount(0, 'reply');
    $this->assertCommentCount(6);

    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data} ORDER by cid DESC limit 1')->fetchField();
    if ($cid) {
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      self::assertEquals($previous_cid, $comment->getParentComment()->id());
      $comment->setPublished();
      $comment->save();
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }

    $this->drupalGet('node/' . $article->id());
    $this->assertSession()->responseContains('Reply with comment target');

    // Micropub reply request with 'in-reply-to' set to previous webmention.
    $reply['content'] = 'Reply with webmention target';
    $reply['in-reply-to'] = '/admin/content/webmention/5';
    $this->sendMicropubRequest($reply);
    $this->assertNodeCount(0, 'reply');
    $this->assertCommentCount(7);

    $cid = \Drupal::database()->query('SELECT cid FROM {comment_field_data} ORDER by cid DESC limit 1')->fetchField();
    if ($cid) {
      $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($cid);
      self::assertEquals($webmention_cid, $comment->getParentComment()->id());

      $link_field_value = NULL;
      if (isset($comment->field_comment_link)) {
        $link_field_value = $comment->get('field_comment_link')->uri;
      }
      self::assertEquals('https://twitter.com/jgmac1106/status/1039835297159282688', $link_field_value);

      $comment->setPublished();
      $comment->save();
    }
    else {
      // Explicit failure.
      $this->assertTrue($cid, 'No comment found');
    }

    $this->drupalGet('node/' . $article->id());
    $this->assertSession()->responseContains('Reply with webmention target');
  }

  /**
   * Assert comment count.
   *
   * @param $count
   */
  protected function assertCommentCount($count) {
    $comment_count = \Drupal::database()->query('SELECT count(cid) FROM {comment_field_data}')->fetchField();
    self::assertEquals($count, $comment_count);
  }

}

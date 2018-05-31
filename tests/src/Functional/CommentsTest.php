<?php

namespace Drupal\Tests\indieweb\Functional;
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

    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['view published webmention entities']);
  }

  /**
   * Tests comments functionality.
   */
  public function testComments() {

    $this->drupalLogin($this->adminUser);
    $this->enableWebmention();
    $edit = ['new_storage_type' => 'entity_reference', 'label' => 'Webmention reference', 'field_name' => 'webmention'];
    $this->drupalPostForm('admin/structure/comment/manage/comment/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, ['settings[target_type]' => 'webmention_entity'], 'Save field settings');
    $this->drupalPostForm('admin/structure/comment/manage/comment/display', ['fields[field_webmention][type]' => 'entity_reference_entity_view'], 'Save');



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
      'comment_create_webmention_reference_field' => 'field_webmention',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/comments', $edit, 'Save configuration');

    // Set publish fields.
    $edit = [];
    $edit['publish_custom_url'] = 1;
    $this->drupalPostForm('admin/config/services/indieweb/publish', $edit, 'Save configuration');

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
    $comment->setPublished(TRUE);
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

    // We should a mail notification.
    $captured_emails = \Drupal::state()->get('system.test_mail_collector');
    $notification = end($captured_emails);
    $this->assertTrue($notification && $notification['id'] == 'indieweb_webmention_comment_created', 'Notification mail found for comment.');

    // Publish the comment.
    $comment->setPublished(TRUE);
    $comment->save();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('This is awesome!');

    // Reply to the comment from the site.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'comment_body[0][value]' => "I know, isn't it!",
      'indieweb_publish_custom_url' => 'https://brid-gy.appspot.com/comment/twitter/swentel/994117538731741185/994129251946418177',
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

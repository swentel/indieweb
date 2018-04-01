<?php

namespace Drupal\Tests\indieweb\Functional;

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
   * Tests comments functionality.
   */
  public function testComments() {

    $this->drupalLogin($this->adminUser);
    $this->enableWebmention();
    $edit = ['new_storage_type' => 'entity_reference', 'label' => 'Webmention reference', 'field_name' => 'webmention'];
    $this->drupalPostForm('admin/structure/comment/manage/comment/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, ['settings[target_type]' => 'webmention_entity'], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');

    // Create article.
    $edit = [
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
    ];
    $this->drupalPostForm('node/add/article', $edit, 'Save');

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
    $code = $this->sendWebmentionRequest($webmention);
    self::assertEquals(202, $code);
    $this->assertCommentCount(0);

    // Enable comments creation and selection webmention field. The other fields
    // will be ok by default.
    $edit = [
      'comment_create_enable' => 1,
      'comment_create_webmention_reference_field' => 'field_webmention',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/comments', $edit, 'Save configuration');

    // Send again, we should have a comment now.
    $code = $this->sendWebmentionRequest($webmention);
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

    // Body should be gone on edit as it's empty.
    $this->drupalGet('comment/' . $cid . '/edit');
    $this->assertSession()->fieldNotExists('comment_body[0][value]');
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

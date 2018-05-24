<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;

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
   * Default note $_POST content.
   *
   * @var array
   */
  protected $note = [
    'h' => 'entry',
    'content' => 'A note content',
  ];

  /**
   * Default article $_POST content.
   *
   * @var array
   */
  protected $article = [
    'h' => 'entry',
    'name' => 'An article',
    'content' => 'An article content',
    'category' => ['tag 1', 'tag 2'],
  ];

  /**
   * Default like $_POST content.
   *
   * @var array
   */
  protected $like = [
    'h' => 'entry',
    'like-of' => 'https://example.com/what-a-page'
  ];

  /**
   * Default bookmark $_POST content.
   *
   * @var array
   */
  protected $bookmark = [
    'h' => 'entry',
    'bookmark-of' => 'https://example.com/what-a-page'
  ];

  /**
   * Default like $_POST content.
   *
   * @var array
   */
  protected $like_silo = [
    'h' => 'entry',
    'like-of' => 'https://twitter.com/swentel/status/1'
  ];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    foreach (['like', 'bookmark'] as $type) {
      $edit = ['name' => $type, 'type' => $type];
      $this->drupalPostForm('admin/structure/types/add', $edit, 'Save and manage fields');
      $edit = ['new_storage_type' => 'link', 'label' => 'Link', 'field_name' => $type . '_link'];
      $this->drupalPostForm('admin/structure/types/manage/' . $type . '/fields/add-field', $edit, 'Save and continue');
      $this->drupalPostForm(NULL, [], 'Save field settings');
      $this->drupalPostForm(NULL, [], 'Save settings');
      $edit = ['fields[field_' . $type . '_link][type]' => 'link_microformat'];
      $this->drupalPostForm('admin/structure/types/manage/' . $type . '/display', $edit, 'Save');
    }

    drupal_flush_all_caches();
  }

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

    // Set IndieAuth token endpoint.
    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 1, 'token_endpoint' => Url::fromRoute('indieweb_test.token_endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    // Configure note, but set 'me' to invalid domain.
    $edit = ['note_create_node' => 1, 'note_node_type' => 'page', 'micropub_me' => 'https://indieweb.micropub.invalid.testdomain'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Send request to create a note, will fail because the 'me' is wrong.
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->note);
    self::assertEquals(400, $code);

    // Set me right.
    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_me' => 'https://indieweb.micropub.testdomain'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Send request, first with invalid token, then with valid, we should have
    // a note then.
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->note, 'invalid_token');
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'page');
    $this->assertNodeCount(0, 'like');
    $this->assertNodeCount(0, 'article');
    $this->assertNodeCount(0, 'bookmark');
    // With valid access token now.
    // TEST: url from 201 header
    $code = $this->sendMicropubRequest($this->note);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals($this->note['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No page node found');
    }

    // Try to send article, should be 400.
    $code = $this->sendMicropubRequest($this->article);
    self::assertEquals(400, $code);
    // Try to send like, should be 400.
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(400, $code);

    // Test articles.
    $this->drupalLogin($this->adminUser);
    $edit = ['article_create_node' => 1, 'article_node_type' => 'article', 'article_tags_field' => 'field_tags'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->article, 'this_is_a_valid_token', TRUE);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'article');
    $nid = $this->getLastNid('article');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals($this->article['name'], $node->getTitle());
      self::assertEquals($this->article['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No article node found');
    }

    // Test likes.
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'like');

    $this->drupalLogin($this->adminUser);
    $edit = ['like_create_node' => 1, 'like_node_type' => 'like', 'like_link_field' => 'field_like_link', 'like_content_field' => 'body', 'like_auto_send_webmention' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'like');
    $nid = $this->getLastNid('like');
    $this->assertQueueItems([$this->like['like-of']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('like', $node->bundle());
      self::assertEquals('Like of ' . $this->like['like-of'], $node->getTitle());
      self::assertEquals($this->like['like-of'], $node->get('field_like_link')->uri);

      // Check 'u-like-of' class.
      $this->drupalGet('node/' . $nid);
      $this->assertSession()->responseContains('href="' . $this->like['like-of'] . '" class="u-like-of"');
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No like node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Do not send when like url is from a silo.
    $code = $this->sendMicropubRequest($this->like_silo);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'like');
    $this->assertQueueItems();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['like_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'like');
    $this->assertQueueItems();

    // Test bookmarks.
    $code = $this->sendMicropubRequest($this->bookmark);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'bookmark');

    $this->drupalLogin($this->adminUser);
    $edit = ['bookmark_create_node' => 1, 'bookmark_node_type' => 'bookmark', 'bookmark_link_field' => 'field_bookmark_link', 'bookmark_content_field' => 'body', 'bookmark_auto_send_webmention' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->bookmark);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'bookmark');
    $nid = $this->getLastNid('bookmark');
    $this->assertQueueItems([$this->bookmark['bookmark-of']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('bookmark', $node->bundle());
      self::assertEquals('Bookmark of ' . $this->bookmark['bookmark-of'], $node->getTitle());
      self::assertEquals($this->bookmark['bookmark-of'], $node->get('field_bookmark_link')->uri);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No bookmark node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['bookmark_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->bookmark);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'bookmark');
    $this->assertQueueItems();

    // Set default status to unpublished for all post types.
    // Turn on auto webmentions too.
    $this->drupalLogin($this->adminUser);
    $edit = ['note_status' => 0, 'article_status' => 0, 'like_status' => 0, 'bookmark_status' => 0, 'bookmark_auto_send_webmention' => 1, 'like_auto_send_webmention' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $post = $this->note;
    $post['content'] = 'Unpublished note';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No second page node found');
    }

    $post = $this->article;
    $post['name'] = 'Unpublished article';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'article');
    $nid = $this->getLastNid('article');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['name'], $node->getTitle());
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No second article node found');
    }

    // Add content to the like too, use access token in post.
    $post = $this->like;
    $post['content'] = 'That is a nice site!';
    $code = $this->sendMicropubRequest($post, 'this_is_a_valid_token', FALSE, TRUE);
    self::assertEquals(201, $code);
    $this->assertNodeCount(4, 'like');
    $nid = $this->getLastNid('like');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No like node found');
    }
    $this->assertQueueItems();

    // Add content to the bookmark too.
    $post = $this->bookmark;
    $post['content'] = 'That is a nice bookmark!';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'bookmark');
    $nid = $this->getLastNid('bookmark');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No bookmark node found');
    }
    $this->assertQueueItems();
  }

}

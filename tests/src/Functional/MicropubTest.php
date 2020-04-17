<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests integration of micropub.
 *
 * @group indieweb
 */
class MicropubTest extends IndiewebBrowserTestBase {

  use TestFileCreationTrait;

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
    'node',
    'indieweb',
    'indieweb_test',
    'indieweb_microformat',
    'indieweb_micropub',
    'indieweb_webmention',
    'datetime_range',
  ];

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
   * Default event $_POST content.
   *
   * @var array
   */
  protected $event = [
    'h' => 'event',
    'name' => 'An event',
    'content' => 'Where it takes place',
    'start' => '2018-05-24 14:00',
    'end' => '2018-05-24 18:00',
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
   * Default repost $_POST content.
   *
   * @var array
   */
  protected $repost = [
    'h' => 'entry',
    'repost-of' => 'https://example.com/what-a-page'
  ];

  /**
   * Default reply $_POST content.
   *
   * @var array
   */
  protected $reply = [
    'h' => 'entry',
    'in-reply-to' => 'https://example.com/what-a-page',
    'content' => 'I am replying now'
  ];

  /**
   * Default RSVP $_POST content.
   *
   * @var array
   */
  protected $rsvp = [
    'h' => 'entry',
    'rsvp' => 'yes',
    'in-reply-to' => 'https://example.com/what-a-page',
    'content' => 'So excited!',
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

    // Create node types.
    $this->createNodeTypes();

    // Configure event microformats.
    $edit = [
      'h_event' => 'event',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');
  }

  /**
   * Tests micropub uploads.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testUpload() {
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/micropub', ['micropub_enable' => 1, 'micropub_expose_link_tag' => 1, 'micropub_media_enable' => 1], 'Save configuration');
    $this->setIndieAuthEndPoints();
    $edit = ['article_create_node' => 1, 'article_node_type' => 'article', 'article_uid' => 'admin (1)', 'article_upload_field' => 'field_image'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Single image.
    $image = current($this->getTestFiles('image'));
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post = $this->article;
    unset($post['category']);
    $post['photo'] = fopen($image_path, 'r');
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'article');
    $nid = $this->getLastNid('article');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    self::assertNotEmpty($node->field_image->target_id);

    // Set cardinality to 2.
    $edit = ['cardinality' => 'number', 'cardinality_number' => 2];
    $this->drupalPostForm('admin/structure/types/manage/article/fields/node.article.field_image/storage', $edit, 'Save field settings');
    drupal_flush_all_caches();

    $post = $this->article;
    $post['photo'] = [];
    unset($post['category']);

    $image = $this->getTestFiles('image')[0];
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post['photo'][] = fopen($image_path, 'r');

    $image = $this->getTestFiles('image')[1];
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post['photo'][] = fopen($image_path, 'r');

    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'article');
    $nid = $this->getLastNid('article');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    self::assertEquals(2, count($node->field_image->getValue()));
    /** @var \Drupal\file\FileInterface $file */
    $file = \Drupal::entityTypeManager()->getStorage('file')->load($node->field_image->target_id);
    self::assertEquals(1, $file->getOwnerId());

    // Test with 3 images.
    $post = $this->article;
    $post['photo'] = [];
    unset($post['category']);

    $image = $this->getTestFiles('image')[0];
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post['photo'][] = fopen($image_path, 'r');
    $post['mp-photo-alt'] = [];
    $post['mp-photo-alt'][] = 'Image alt 1';

    $image = $this->getTestFiles('image')[1];
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post['photo'][] = fopen($image_path, 'r');
    $post['mp-photo-alt'][] = 'Image alt 2';

    $image = $this->getTestFiles('image')[2];
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $post['photo'][] = fopen($image_path, 'r');
    $post['mp-photo-alt'][] = 'Image alt 3';

    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'article');
    $nid = $this->getLastNid('article');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    self::assertEquals(2, count($node->field_image->getValue()));
    foreach ($node->field_image->getValue() as $delta => $image) {
      $number = $delta + 1;
      self::assertEquals($image['alt'], 'Image alt ' . $number);
    }

    // Test with invalid file.
    $post = $this->article;
    $post['photo'] = [];
    unset($post['category']);

    $file = $this->getTestFiles('text')[0];
    $file_path = \Drupal::service('file_system')->realpath($file->uri);
    $post['photo'][] = fopen($file_path, 'r');

    $code = $this->sendMicropubRequest($post, 'is_valid');
    self::assertEquals(201, $code);
    $this->assertNodeCount(4, 'article');
    $nid = $this->getLastNid('article');
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    self::assertEquals(0, count($node->field_image->getValue()));

    // Check q=last
    $auth = 'Bearer is_valid';
    $headers = [
      'Accept' => 'application/json',
      'Authorization' => $auth,
    ];
    $this->drupalGet('indieweb/micropub/media', ['query' => ['q' => 'last']], $headers);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("url");
    $this->assertSession()->responseContains('image-test_3.png');
  }

  /**
   * Tests micropub functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testMicropub() {
    $this->drupalGet('<front>');

    // Request to micropub should be a 404.
    $this->drupalGet('indieweb/micropub');
    $this->drupalGet('indieweb/micropub/media');
    $this->assertSession()->statusCodeEquals(404);

    // Enable the main micropub endpoint.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/micropub', ['micropub_enable' => 1, 'micropub_expose_link_tag' => 1, 'micropub_media_enable' => 1], 'Save configuration');
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');
    $this->drupalGet('indieweb/micropub');
    $this->assertSession()->statusCodeEquals(400);
    $this->drupalGet('indieweb/micropub/media');
    $this->assertSession()->statusCodeEquals(401);
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'config']]);
    $this->assertSession()->statusCodeEquals(401);
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'source']]);
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'category']]);
    $this->assertSession()->statusCodeEquals(404);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');

    // Set IndieAuth token endpoint.
    $this->drupalLogin($this->adminUser);
    $this->setIndieAuthEndPoints();

    // Configure note and also enable issue. It doesn't have an explicit test
    // for properties, but just being enabled makes sure the order is ok.
    $edit = ['note_create_node' => 1, 'note_node_type' => 'page', 'note_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')', 'issue_node_type' => 'page'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    // Send request to create a note, will fail because the 'me' is wrong.
    $code = $this->sendMicropubRequest($this->note, 'return_wrong_me');
    self::assertEquals(403, $code);

    // Send note request with invalid token.
    $code = $this->sendMicropubRequest($this->note, 'invalid_token');
    self::assertEquals(403, $code);
    $this->assertNodeCount(0, 'page');
    $this->assertNodeCount(0, 'like');
    $this->assertNodeCount(0, 'article');
    $this->assertNodeCount(0, 'bookmark');

    // ----------------------------------------------------------------
    // q=config tests.
    // ----------------------------------------------------------------

    // Check q=config with invalid token, should be 403.
    $auth = 'Bearer invalid_token';
    $headers = [
      'Accept' => 'application/json',
      'Authorization' => $auth,
    ];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'config']], $headers);
    $this->assertSession()->statusCodeEquals(403);

    // Check q=config with valid token, should contain media endpoint.
    $auth = 'Bearer is_valid';
    $headers = [
      'Accept' => 'application/json',
      'Authorization' => $auth,
    ];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'config']], $headers);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains("media-endpoint");
    $this->assertSession()->responseContains("\/indieweb\/micropub\/media");

    // Create note with valid access token.
    $response_data = $this->sendMicropubRequest($this->note, 'is_valid', FALSE, 'form_params', TRUE);
    self::assertEquals(201, $response_data['code']);
    $this->assertNodeCount(1, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals($this->note['content'], $node->get('body')->value);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());
      self::assertEquals($node->toUrl('canonical', ['absolute' => TRUE])->toString(), $response_data['location']);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No page node found');
    }

    // ----------------------------------------------------------------
    // q=source tests.
    // ----------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_enable_source' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $auth = 'Bearer invalid_token';
    $headers = ['Accept' => 'application/json', 'Authorization' => $auth,];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'source']], $headers);
    $this->assertSession()->statusCodeEquals(403);

    $auth = 'Bearer is_valid';
    $headers = ['Accept' => 'application/json', 'Authorization' => $auth,];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'source']], $headers);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('items');

    // ----------------------------------------------------------------
    // Update tests.
    // ----------------------------------------------------------------

    $update = [
      'action' => 'update',
      'url' => '/node/' . $node->id(),
      'replace' => [
        'post-status' => ['draft'],
        'name' => ['An updated title'],
        'content' => ['Fixing content'],
      ],
    ];
    $code = $this->sendMicropubRequest($update, 'is_in_valid', FALSE, 'json');
    self::assertEquals(400, $code);

    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_enable_update' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $code = $this->sendMicropubRequest($update, 'is_in_valid', FALSE, 'json');
    self::assertEquals(403, $code);

    $code = $this->sendMicropubRequest($update, 'is_valid', FALSE, 'json');
    self::assertEquals(200, $code);
    $node_unpublished = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($nid);
    self::assertEmpty($node_unpublished->isPublished());
    self::assertEquals('An updated title', $node_unpublished->label());
    self::assertEquals('Fixing content', $node_unpublished->get('body')->value);

    $update['replace']['post-status'] = ['published'];
    $code = $this->sendMicropubRequest($update, 'is_valid', FALSE, 'json');
    self::assertEquals(200, $code);
    $node_published_again = \Drupal::entityTypeManager()->getStorage('node')->loadUnchanged($nid);
    self::assertNotEmpty($node_published_again->isPublished());

    // ----------------------------------------------------------------
    // all post types
    // ----------------------------------------------------------------

    // Try to send article, should be 400.
    $code = $this->sendMicropubRequest($this->article);
    self::assertEquals(400, $code);

    // Test articles.
    $this->drupalLogin($this->adminUser);
    $edit = ['article_create_node' => 1, 'article_node_type' => 'article', 'article_tags_field' => 'field_tags', 'article_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->article);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'article');
    $nid = $this->getLastNid('article');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals($this->article['name'], $node->getTitle());
      self::assertEquals($this->article['content'], $node->get('body')->value);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());
      $terms = \Drupal::database()->query('SELECT count(tid) FROM {taxonomy_term_field_data}')->fetchField();
      self::assertEquals(2, $terms);
      $tags = $node->get('field_tags')->getValue();
      self::assertEquals(2, count($tags));
      foreach ($tags as $tag) {
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tag['target_id']);
        self::assertNotEmpty(in_array($term->label(), $this->article['category']));
      }
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No article node found');
    }

    // Test event.
    $this->drupalLogin($this->adminUser);
    $edit = ['event_create_node' => 1, 'event_node_type' => 'event', 'event_content_field' => 'body', 'event_date_field' => 'field_date', 'event_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->event);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'event');
    $nid = $this->getLastNid('event');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals($this->event['name'], $node->getTitle());
      self::assertEquals($this->event['content'], $node->get('body')->value);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());

      // Check 'dt-start' and 'dt-end' classes.
      $this->drupalGet('node/' . $nid);
      $this->assertSession()->responseContains('datetime="2018-05-24T04:00:00Z" class="dt-start datetime"');
      $this->assertSession()->responseContains('datetime="2018-05-24T08:00:00Z" class="dt-end datetime"');

    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No event node found');
    }

    // Test likes.
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'like');

    $this->drupalLogin($this->adminUser);
    $edit = ['like_create_node' => 1, 'like_node_type' => 'like', 'like_link_field' => 'field_like_link', 'like_content_field' => 'body', 'like_auto_send_webmention' => 1, 'like_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'like');
    $nid = $this->getLastNid('like');
    $this->assertWebmentionQueueItems([$this->like['like-of']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('like', $node->bundle());
      self::assertEquals('Like of ' . $this->like['like-of'], $node->getTitle());
      self::assertEquals($this->like['like-of'], $node->get('field_like_link')->uri);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());

      // Check 'u-like-of' class.
      $this->drupalGet('node/' . $nid);
      $this->assertSession()->responseContains('href="' . $this->like['like-of'] . '" class="u-like-of"');
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No like node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Swap to brid.gy
    $code = $this->sendMicropubRequest($this->like_silo);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'like');
    $this->assertWebmentionQueueItems(['https://brid.gy/publish/twitter']);

    // Clear the queue.
    $this->clearQueue();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['like_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->like);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'like');
    $this->assertWebmentionQueueItems();

    // Test bookmarks.
    $code = $this->sendMicropubRequest($this->bookmark);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'bookmark');

    $this->drupalLogin($this->adminUser);
    $edit = ['bookmark_create_node' => 1, 'bookmark_node_type' => 'bookmark', 'bookmark_link_field' => 'field_bookmark_link', 'bookmark_content_field' => 'body', 'bookmark_auto_send_webmention' => 1, 'bookmark_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $b = $this->bookmark;
    $b['mp-syndicate-to'] = ['https://brid.gy/publish/twitter'];
    $code = $this->sendMicropubRequest($b);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'bookmark');
    $nid = $this->getLastNid('bookmark');
    $this->assertWebmentionQueueItems([$this->bookmark['bookmark-of'], 'https://brid.gy/publish/twitter'], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('bookmark', $node->bundle());
      self::assertEquals('Bookmark of ' . $this->bookmark['bookmark-of'], $node->getTitle());
      self::assertEquals($this->bookmark['bookmark-of'], $node->get('field_bookmark_link')->uri);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No bookmark node found');
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
    $this->assertWebmentionQueueItems();

    // Test reposts.
    $code = $this->sendMicropubRequest($this->repost);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'repost');

    $this->drupalLogin($this->adminUser);
    $edit = ['repost_create_node' => 1, 'repost_node_type' => 'repost', 'repost_link_field' => 'field_repost_link', 'repost_content_field' => 'body', 'repost_auto_send_webmention' => 1, 'repost_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->repost);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'repost');
    $nid = $this->getLastNid('repost');
    $this->assertWebmentionQueueItems([$this->repost['repost-of']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('repost', $node->bundle());
      self::assertEquals('Repost of ' . $this->repost['repost-of'], $node->getTitle());
      self::assertEquals($this->repost['repost-of'], $node->get('field_repost_link')->uri);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No repost node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['repost_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->repost);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'repost');
    $this->assertWebmentionQueueItems();

    // Test replies.
    $code = $this->sendMicropubRequest($this->reply);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'reply');

    $this->drupalLogin($this->adminUser);
    $edit = ['reply_create_node' => 1, 'reply_node_type' => 'reply', 'reply_link_field' => 'field_reply_link', 'reply_content_field' => 'body', 'reply_auto_send_webmention' => 1, 'reply_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->reply);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'reply');
    $nid = $this->getLastNid('reply');
    $this->assertWebmentionQueueItems([$this->reply['in-reply-to']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('reply', $node->bundle());
      self::assertEquals('In reply to ' . $this->reply['in-reply-to'], $node->getTitle());
      self::assertEquals($this->reply['in-reply-to'], $node->get('field_reply_link')->uri);
      self::assertEquals($this->reply['content'], $node->get('body')->value);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No reply node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['reply_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->reply);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'reply');
    $this->assertWebmentionQueueItems();

    // Test RSVP.
    $code = $this->sendMicropubRequest($this->rsvp);
    self::assertEquals(400, $code);
    $this->assertNodeCount(0, 'rsvp');

    $this->drupalLogin($this->adminUser);
    $edit = ['rsvp_create_node' => 1, 'rsvp_node_type' => 'rsvp', 'rsvp_link_field' => 'field_rsvp_link', 'rsvp_rsvp_field' => 'indieweb_rsvp', 'rsvp_content_field' => 'body', 'rsvp_auto_send_webmention' => 1, 'rsvp_uid' => $this->adminUser->getDisplayName() . ' (' . $this->adminUser->id() . ')'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->rsvp);
    self::assertEquals(201, $code);
    $this->assertNodeCount(1, 'rsvp');
    $nid = $this->getLastNid('rsvp');
    $this->assertWebmentionQueueItems([$this->rsvp['in-reply-to']], $nid);
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(TRUE, $node->isPublished());
      self::assertEquals('rsvp', $node->bundle());
      self::assertEquals('RSVP on ' . $this->rsvp['in-reply-to'], $node->getTitle());
      self::assertEquals($this->rsvp['in-reply-to'], $node->get('field_rsvp_link')->uri);
      self::assertEquals($this->rsvp['content'], $node->get('body')->value);
      self::assertEquals($this->rsvp['rsvp'], $node->get('indieweb_rsvp')->value);
      self::assertEquals($this->adminUser->id(), $node->getOwnerId());

      // Check 'rsvp' class.
      $this->drupalGet('node/' . $nid);
      $this->assertSession()->responseContains('class="p-rsvp" value="yes"');

    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No rsvp node found');
    }

    // Clear the queue.
    $this->clearQueue();

    // Configure to not auto send a webmention.
    $this->drupalLogin($this->adminUser);
    $edit = ['rsvp_auto_send_webmention' => 0];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->rsvp);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'rsvp');
    $this->assertWebmentionQueueItems();

    // Set default status to unpublished for all post types.
    // Turn on auto webmentions too.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'note_status' => 0,
      'article_status' => 0,
      'like_status' => 0,
      'bookmark_status' => 0,
      'repost_status' => 0,
      'reply_status' => 0,
      'rsvp_status' => 0,
      'event_status' => 0,
      'bookmark_auto_send_webmention' => 1,
      'like_auto_send_webmention' => 1,
      'repost_auto_send_webmention' => 1,
      'reply_auto_send_webmention' => 1,
      'rsvp_auto_send_webmention' => 1,
    ];
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
      $this->assertNotEmpty($nid, 'No page node found');
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
      $this->assertNotEmpty($nid, 'No article node found');
    }

    // Add content to the like too, use access token in post.
    $post = $this->like;
    $post['content'] = 'That is a nice site!';
    $code = $this->sendMicropubRequest($post);
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
      $this->assertNotEmpty($nid, 'No like node found');
    }
    $this->assertWebmentionQueueItems();

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
      $this->assertNotEmpty($nid, 'No bookmark node found');
    }
    $this->assertWebmentionQueueItems();

    // Add content to the repost too.
    $post = $this->repost;
    $post['content'] = 'That is a nice repost!';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'repost');
    $nid = $this->getLastNid('repost');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals('Repost of ' . $this->repost['repost-of'], $node->getTitle());
      self::assertEquals($this->repost['repost-of'], $node->get('field_repost_link')->uri);
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No repost node found');
    }
    $this->assertWebmentionQueueItems();

    // Unpublished reply
    $post = $this->reply;
    $post['content'] = 'This is not a published reply';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'reply');
    $nid = $this->getLastNid('reply');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No reply node found');
    }
    $this->assertWebmentionQueueItems();

    // Unpublished rsvp
    $post = $this->rsvp;
    $post['content'] = 'This is not a published rsvp';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(3, 'rsvp');
    $nid = $this->getLastNid('rsvp');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No rsvp node found');
    }
    $this->assertWebmentionQueueItems();

    // Unpublished event
    $post = $this->event;
    $post['name'] = 'Unpublished event';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'event');
    $nid = $this->getLastNid('event');
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
      self::assertEquals($post['name'], $node->getTitle());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No event node found');
    }

    // ----------------------------------------------------------------
    // test hooks
    // ----------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    $edit = [
      'note_status' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $post = $this->note;
    $post['act_on_hooks'] = TRUE;
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(4, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      $previous_nid = $nid;
      $previous_nid--;

      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($previous_nid);
      self::assertEquals('Title set from hook', $node->label());

      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals('duplicated node', $node->label());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No page node found');
    }

    // A json post.
    $post = ['type' => ['h-entry'], 'properties' => ['content' => ['json node']]];
    $code = $this->sendMicropubRequest($post, 'is_valid', FALSE, 'json');
    self::assertEquals(201, $code);
    $this->assertNodeCount(5, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals('json node', $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No page node found');
    }

    // A post with random properties.
    $post = ['no_post_made' => TRUE, 'h' => 'entry', 'random_content' => 'random content', 'random_title' => 'random_title'];
    $code = $this->sendMicropubRequest($post, 'is_valid', TRUE);
    self::assertEquals(201, $code);
    $this->assertNodeCount(6, 'page');
    $nid = $this->getLastNid('page');
    if ($nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals($post['random_title'], $node->label());
      self::assertEquals($post['random_content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No page node found');
    }

    // Test that nothing comes in the queue as the note is unpublished.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'note_status' => 0,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $this->clearQueue();
    $post = $this->note;
    $post['mp-syndicate-to'] = ['https://brid.gy/publish/twitter'];
    $this->sendMicropubRequest($post);
    $this->assertWebmentionQueueItems();

    // Set note default to published again.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'note_status' => 1,
      'article_status' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();
    $this->clearQueue();
    $post = $this->note;
    $post['mp-syndicate-to'] = ['https://brid.gy/publish/twitter'];
    $this->sendMicropubRequest($post);
    $this->assertWebmentionQueueItems($post['mp-syndicate-to']);

    // Test post-status
    $post = $this->note;
    $post['post-status'] = 'draft';
    $this->sendMicropubRequest($post);
    $nid = $this->getLastNid('page');
    if ($nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals(FALSE, $node->isPublished());
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No page node found');
    }

    // More tags tests.
    $post = $this->article;
    $post['category'][] = 'another tag';
    $this->sendMicropubRequest($post);
    $nid = $this->getLastNid('article');
    if ($nid) {
      $terms = \Drupal::database()->query('SELECT count(tid) FROM {taxonomy_term_field_data}')->fetchField();
      self::assertEquals(3, $terms);
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      $tags = $node->get('field_tags')->getValue();
      self::assertEquals(3, count($tags));
      foreach ($tags as $tag) {
        $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tag['target_id']);
        self::assertNotEmpty(in_array($term->label(), $post['category']));
      }
    }
    else {
      // Explicit failure.
      $this->assertNotEmpty($nid, 'No article node found');
    }

    // ----------------------------------------------------------------
    // Test with internal indieauth server.
    // ----------------------------------------------------------------

    $token = $this->setIndieAuthInternal(TRUE, 'create');
    $post = $this->note;
    $post['content'] = 'Using the internal indieauth functionality';
    $code = $this->sendMicropubRequest($post, $token);
    self::assertEquals(201, $code);
    $this->setIndieAuthExternal();

    // ----------------------------------------------------------------
    // q=category tests.
    // ----------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    // tags is the only vocabulary, so it will be selected automatically.
    $edit = ['micropub_enable_category' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $auth = 'Bearer invalid_token';
    $headers = ['Accept' => 'application/json', 'Authorization' => $auth,];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'category']], $headers);
    $this->assertSession()->statusCodeEquals(403);

    $auth = 'Bearer is_valid';
    $headers = ['Accept' => 'application/json', 'Authorization' => $auth,];
    $this->drupalGet('indieweb/micropub', ['query' => ['q' => 'category']], $headers);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('tag 1');
    $this->assertSession()->responseContains('tag 2');
    $this->assertSession()->responseContains('another tag');

    // ----------------------------------------------------------------
    // Delete tests.
    // ----------------------------------------------------------------

    $delete = [
      'action' => 'delete',
      'url' => '/node/' . $nid,
    ];
    $code = $this->sendMicropubRequest($delete, 'is_in_valid', FALSE, 'json');
    self::assertEquals(400, $code);

    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_enable_delete' => 1];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');
    $this->drupalLogout();

    $code = $this->sendMicropubRequest($delete, 'is_in_valid', FALSE, 'json');
    self::assertEquals(403, $code);

    $nid = \Drupal::database()->query("SELECT nid FROM {node_field_data} WHERE nid = :nid", [':nid' => $nid])->fetchField();
    self::assertNotEmpty($nid);
    $code = $this->sendMicropubRequest($delete, 'is_valid', FALSE, 'json');
    self::assertEquals(200, $code);
    $nid = \Drupal::database()->query("SELECT nid FROM {node_field_data} WHERE nid = :nid", [':nid' => $nid])->fetchField();
    self::assertEmpty($nid);

    // ----------------------------------------------------------------
    // Delete node type.
    // ----------------------------------------------------------------
    $article = \Drupal::entityTypeManager()->getStorage('node_type')->load('article');
    $article->delete();

    $node_type_name = \Drupal::config('indieweb_micropub.settings')->get('article_node_type');
    self::assertEmpty($node_type_name);
    $node_type_name = \Drupal::config('indieweb_micropub.settings')->get('note_node_type');
    self::assertNotEmpty($node_type_name == 'page');
    $node_type_name = \Drupal::config('indieweb_micropub.settings')->get('like_node_type');
    self::assertNotEmpty($node_type_name == 'like');

    // Test creating an article now, should not create a node.
    $code = $this->sendMicropubRequest($this->article);
    self::assertEquals(400, $code);
    self::assertNodeCount(30);

  }

}

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
  ];

  protected $like = [
    'h' => 'entry',
    'like-of' => 'https://example.com/what-a-page'
  ];

  /**
   * {@inheritdoc}
   */
  function setUp() {
    parent::setUp();

    $schema = [
      'description' => 'Stores items in queues.',
      'fields' => [
        'item_id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Primary Key: Unique item ID.',
        ],
        'name' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The queue name.',
        ],
        'data' => [
          'type' => 'blob',
          'not null' => FALSE,
          'size' => 'big',
          'serialize' => TRUE,
          'description' => 'The arbitrary data for the item.',
        ],
        'expire' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the claim lease expires on the item.',
        ],
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the item was created.',
        ],
      ],
      'primary key' => ['item_id'],
      'indexes' => [
        'name_created' => ['name', 'created'],
        'expire' => ['expire'],
      ],
    ];
    \Drupal::database()->schema()->createTable('queue', $schema);

    $this->drupalLogin($this->adminUser);
    $edit = ['name' => 'like', 'type' => 'like'];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save and manage fields');
    $edit = ['new_storage_type' => 'link', 'label' => 'Link', 'field_name' => 'link'];
    $this->drupalPostForm('admin/structure/types/manage/like/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');
    $edit = ['fields[field_link][type]' => 'link_microformat'];
    $this->drupalPostForm('admin/structure/types/manage/like/display', $edit, 'Save');
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
    $edit = ['article_create_node' => 1, 'article_node_type' => 'article'];
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
    $edit = ['like_create_node' => 1, 'like_node_type' => 'like', 'like_link_field' => 'field_link', 'like_content_field' => 'body'];
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
      self::assertEquals($this->like['like-of'], $node->get('field_link')->uri);

      // Check 'u-like-of' class.
      $this->drupalGet('node/' . $nid);
      $this->assertSession()->responseContains('href="' . $this->like['like-of'] . '" class="u-like-of"');
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No like node found');
    }

    // Set default status to unpublished for all post types.
    $this->drupalLogin($this->adminUser);
    $edit = ['note_status' => 0, 'article_status' => 0, 'like_status' => 0];
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

    // Add content to the like too.
    $post = $this->like;
    $post['content'] = 'That is a nice site!';
    $code = $this->sendMicropubRequest($post);
    self::assertEquals(201, $code);
    $this->assertNodeCount(2, 'like');
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

  }

}

<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of feeds.
 *
 * @group indieweb
 */
class FeedTest extends IndiewebBrowserTestBase {

  protected $defaultTheme = 'stark';

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'block',
    'node',
    'indieweb',
    'indieweb_feed',
    'indieweb_microformat',
    'indieweb_test',
  ];

  /**
   * Timeline path.
   *
   * @var string
   */
  protected $timeline_path = '/timeline/all';

  /**
   * Timeline 2 path.
   *
   * @var string
   */
  protected $timeline_path_2 = '/timeline/all/2';

  /**
   * Timeline jf2 path.
   *
   * @var string
   */
  protected $timeline_jf2_path = '/timeline-all.jf2';

  /**
   * The rel header link.
   *
   * @var string
   */
  protected $header_feed_link_tag = '<link rel="feed"';

  /**
   * The jf2 header link.
   *
   * @var string
   */
  protected $header_jfd_link_tag = '<link rel="alternate" type="application/jf2feed+json"';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'article']);
    $this->createContentType(['type' => 'page']);
    $this->placeBlock('page_title_block', ['region' => 'content']);
  }

  /**
   * Tests feeds functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testFeeds() {

    // Login and add feed.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'label' => 'Timeline',
      'id' => 'timeline',
      'path' => $this->timeline_path,
      'feedTitle' => 'Timeline',
      'ownerId' => 1,
      'excludeIndexing' => 1,
      'limit' => 10,
      'author' => '<a class="u-url p-name" href="/">Your name</a><img src="https://example.com/image/avatar.png" class="u-photo hidden" alt="Your name">',
      'bundles[]' => [
        'node|article' => 'node|article'
      ],
    ];
    $this->drupalPostForm('admin/config/services/indieweb/feeds/add', $edit, 'Save');

    // Set all microformats so that the JF2 feed can work.
    $microformats = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_name_exclude_node_type' => 'page',
      'p_bridgy_twitter_content' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $microformats, 'Save configuration');
    $this->drupalLogout();

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_feed_link_tag);
    $this->assertSession()->responseNotContains($this->header_jfd_link_tag);

    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains('<h1>Timeline</h1>');
    $this->assertSession()->responseContains($edit['author']);
    $this->assertSession()->responseContains('noindex, nofollow');

    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->statusCodeEquals(404);

    // Create an article.
    $settings = [
      'type' => 'article',
      'title' => 'An article',
      'body' => [
        'value' => 'This is an article on the timeline feed',
        'format' => filter_default_format()
      ],
      'uid' => 1,
    ];
    $article = $this->createNode($settings);
    $this->assertFeedItems(1, 1, $article->id());

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/services/indieweb/feeds');
    $this->clickLink('Update items');

    // Also expose headers and jf2.
    $edit = [
      'excludeIndexing' => FALSE,
      'jf2' => TRUE,
      'feedLinkTag' => TRUE,
      'jf2LinkTag' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/feeds/timeline/edit', $edit, 'Save');
    $this->assertFeedItems(1, 1, $article->id());

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_feed_link_tag);
    $this->assertSession()->responseContains($this->header_jfd_link_tag);
    $this->assertSession()->responseContains('/timeline/all');
    $this->assertSession()->responseContains('/timeline-all.jf2');

    // Check timelines.
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains($article->label());
    $this->assertSession()->responseNotContains('noindex, nofollow');

    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains($article->label());

    // Change title.
    $article->set('title', 'Updated title')->save();
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains('Updated title');
    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->responseContains('Updated title');
    $this->assertFeedItems(1, 1, $article->id());

    // Add new article, this will be added to the timeline by the hooks.
    $settings['title'] = 'Now this will be cool!';
    $article_2 = $this->createNode($settings);
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains($article_2->label());
    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->responseContains($article_2->label());

    // Edit article, should only be once in timeline.
    $article_2->set('title', 'Now this is updated');
    $article_2->save();
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains($article_2->label());
    $this->assertFeedItems(2, 1, $article_2->id());

    // Delete, should be gone from timeline.
    $article_2->delete();
    $this->assertFeedItems(1, 0, $article_2->id());
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseNotContains($settings['title']);
    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->responseNotContains($settings['title']);

    // Create an article with a different uid, should not show up in feed.
    $settings['uid'] = $this->adminUser->id();
    $settings['title'] = 'Should not show up';
    $article_3 = $this->createNode($settings);
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseNotContains($article_3->label());
    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->responseNotContains($article_3->label());

    // Add page node.
    $page = $this->createNode(['type' => 'page', 'title' => 'A nice page', 'uid' => 1]);

    // Create new feed, will be indexed immediately.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'label' => 'Timeline 2',
      'id' => 'timeline_2',
      'path' => $this->timeline_path_2,
      'feedTitle' => 'Timeline 2',
      'ownerId' => 1,
      'excludeIndexing' => 1,
      'limit' => 10,
      'bundles[]' => [
        'node|page' => 'node|page'
      ],
    ];
    $this->drupalPostForm('admin/config/services/indieweb/feeds/add', $edit, 'Save');
    $this->drupalLogout();

    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseNotContains($page->label());
    $this->drupalGet($this->timeline_path_2);
    $this->assertSession()->responseContains($page->label());
    $this->assertFeedItems(1, 1, $article->id());
    $this->assertFeedItems(1, 1, $page->id(), 'timeline_2');

    // Delete feed 2.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/feeds/timeline_2/delete', [], 'Delete');
    $this->assertFeedItems(1, 1, $article->id());
    $this->assertFeedItems(0, 0, $page->id(), 'timeline_2');
  }

  /**
   * Assert number of items in feed.
   *
   * @param $total_feed
   * @param $total_nid
   * @param $entity_id
   *
   * @param string $feed_id
   */
  protected function assertFeedItems($total_feed, $total_nid, $entity_id, $feed_id = 'timeline') {

    $total = \Drupal::database()->query('SELECT count(id) FROM {indieweb_feed_item} WHERE feed_id = :feed_id AND entity_id = :entity_id', [':feed_id' => $feed_id, ':entity_id' => $entity_id])->fetchField();
    $this->assertEquals($total_nid, $total);

    $total = \Drupal::database()->query('SELECT count(id) FROM {indieweb_feed_item} WHERE feed_id = :feed_id', [':feed_id' => $feed_id])->fetchField();
    $this->assertEquals($total_feed, $total);

  }

}

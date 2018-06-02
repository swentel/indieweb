<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of feeds.
 *
 * @group indieweb
 */
class FeedsTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Timeline path.
   *
   * @var string
   */
  protected $timeline_path = '/timeline/all';

  /**
   * Timeline atom path.
   *
   * @var string
   */
  protected $timeline_atom_path = '/timeline-all.xml';

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
  protected $header_link_rel_link = '<link rel="feed"';

  /**
   * The atom header link.
   *
   * @var string
   */
  protected $header_link_atom_link = '<link rel="alternate" type="application/atom+xml"';

  /**
   * The jf2 header link.
   *
   * @var string
   */
  protected $header_link_jf2_link = '<link rel="alternate" type="application/jf2feed+json"';

  /**
   * Tests feeds functionality.
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
    $this->assertSession()->responseNotContains('Created Timeline');

    // Set all microformats too.
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
    $this->assertSession()->responseNotContains($this->header_link_rel_link);
    $this->assertSession()->responseNotContains($this->header_link_atom_link);
    $this->assertSession()->responseNotContains($this->header_link_jf2_link);

    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseContains('<h1 class="title page-title">Timeline</h1>');
    $this->assertSession()->responseContains($edit['author']);
    $this->assertSession()->responseContains('noindex, nofollow');

    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->statusCodeEquals(404);
    $this->drupalGet($this->timeline_atom_path);
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
    // Also expose headers and jf2 and atom.
    $edit = [
      'excludeIndexing' => FALSE,
      'atom' => TRUE,
      'jf2' => TRUE,
      'relHeader' => TRUE,
      'relHeaderAtom' => TRUE,
      'relHeaderJf2' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/feeds/timeline/edit', $edit, 'Save');
    $this->assertFeedItems(1, 1, $article->id());

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link_rel_link);
    $this->assertSession()->responseContains($this->header_link_atom_link);
    $this->assertSession()->responseContains($this->header_link_jf2_link);
    $this->assertSession()->responseContains('/timeline/all');
    $this->assertSession()->responseContains('/timeline-all.jf2');
    $this->assertSession()->responseContains('/timeline-all.xml');

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
    $this->drupalGet($this->timeline_path);
    $this->assertSession()->responseNotContains($settings['title']);
    $this->drupalGet($this->timeline_jf2_path);
    $this->assertSession()->responseNotContains($settings['title']);
  }

  /**
   * Assert number of items in feed.
   *
   * @param $total_feed
   * @param $total_nid
   * @param $entity_id
   *
   * @param string $feed
   */
  protected function assertFeedItems($total_feed, $total_nid, $entity_id, $feed = 'timeline') {

    $total = \Drupal::database()->query('SELECT count(id) FROM {indieweb_feed_items} WHERE feed = :feed AND entity_id = :entity_id', [':feed' => $feed, ':entity_id' => $entity_id])->fetchField();
    $this->assertEquals($total_nid, $total);

    $total = \Drupal::database()->query('SELECT count(id) FROM {indieweb_feed_items} WHERE feed = :feed', [':feed' => $feed])->fetchField();
    $this->assertEquals($total_feed, $total);

  }

}

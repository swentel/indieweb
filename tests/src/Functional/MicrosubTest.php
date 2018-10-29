<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;

/**
 * Tests integration of microsub.
 *
 * @group indieweb
 */
class MicrosubTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * The external header link
   *
   * @var string
   */
  protected $header_link_external = '<link rel="microsub" href="https://example.com/microsub" />';

  /**
   * The external header link
   *
   * @var string
   */
  protected $header_link = '<link rel="microsub" href="https://example.com/microsub" />';

  /**
   * The internal path.
   *
   * @var string
   */
  protected $microsub_path = 'indieweb/microsub';

  /**
   * Timeline path.
   *
   * @var string
   */
  protected $timeline_path_1 = '/microsub-timeline/1';

  /**
   * Timeline path.
   *
   * @var string
   */
  protected $timeline_path_2 = '/microsub-timeline/2';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create feeds, channels and sources.
    $this->drupalLogin($this->adminUser);
    $this->createFeeds();
    $this->createChannels();
    $this->createSources();
    $this->drupalLogout();

    drupal_flush_all_caches();
  }

  /**
   * Tests microsub functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMicrosub() {

    // ----------------------------------------------------------------
    // basic tests.
    // ----------------------------------------------------------------

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->header_link);

    $this->drupalGet('admin/config/services/indieweb/microsub');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->adminUser);
    $edit = ['microsub_add_header_link' => TRUE, 'microsub_endpoint' => 'https://example.com/microsub', 'microsub_internal_handler' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link_external);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link_external);

    $this->drupalGet($this->microsub_path);
    $this->assertSession()->statusCodeEquals(404);

    // ----------------------------------------------------------------
    // Use internal built-in
    // ----------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    $edit = ['microsub_internal' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    // Set IndieAuth token endpoint.
    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 1, 'token_endpoint' => Url::fromRoute('indieweb_test.token_endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

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

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->microsub_path);
    $this->drupalLogout();

    $this->drupalGet('indieweb/microsub');
    $this->assertSession()->statusCodeEquals(401);

    // ----------------------------------------------------------------
    // Fetch items
    // ----------------------------------------------------------------

    // Create an article and page.
    $settings_article = [
      'type' => 'article',
      'title' => 'An article',
      'body' => [
        'value' => 'This is an article on the first timeline feed',
        'format' => filter_default_format()
      ],
      'uid' => 1,
    ];
    $this->createNode($settings_article);

    $settings_page = [
      'type' => 'page',
      'title' => 'A page',
      'body' => [
        'value' => 'This is a page on the second timeline feed',
        'format' => filter_default_format()
      ],
      'uid' => 1,
    ];
    $this->createNode($settings_page);
    $settings_page['title'] = 'Another page';
    $settings_page['body']['value'] = 'This is another page on the second timeline feed';
    $this->createNode($settings_page);

    $this->fetchItems();
    $this->assertItemCount('item', 3);

    // ----------------------------------------------------------------
    // channels and timeline
    // ----------------------------------------------------------------

    $query = [];
    $type = 'get';
    $response = $this->sendMicrosubRequest($query, $type, 'no_auth_header');
    self::assertEqual($response['code'], 401);

    $response = $this->sendMicrosubRequest($query, $type);
    self::assertEqual($response['code'], 400);

    $query = ['action' => 'timeline'];
    $response = $this->sendMicrosubRequest($query, $type, 'is_totally_invalid');
    self::assertEqual($response['code'], 403);

    $query = ['action' => 'channels'];
    $response = $this->sendMicrosubRequest($query);
    self::assertEqual($response['code'], 200);
    $body = json_decode($response['body']);
    self::assertEquals('Channel 1', $body->channels[1]->name);
    self::assertEquals('Channel 2', $body->channels[2]->name);
    self::assertEquals(0, $body->channels[1]->unread);
    self::assertEquals(0, $body->channels[2]->unread);

    $this->resetNextFetch(1);
    $this->resetNextFetch(2);

    $settings_page['title'] = 'Another page for second';
    $settings_page['body']['value'] = 'This is another page on the second timeline feed which will be marked as unread';
    $this->createNode($settings_page);
    $this->fetchItems();
    $this->assertItemCount('item', 4);

    $response = $this->sendMicrosubRequest($query);
    $body = json_decode($response['body']);
    self::assertEquals(0, $body->channels[1]->unread);
    self::assertEquals(1, $body->channels[2]->unread);

    // Delete an item.
    $query = ['action' => 'timeline', 'method' => 'remove', 'entry' => 4];
    $this->sendMicrosubRequest($query, 'POST');
    $this->assertItemCount('item', 3);
    $this->resetNextFetch(1);
    $this->resetNextFetch(2);
    $this->fetchItems();
    $this->assertItemCount('item', 3);

    // Delete source.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/1/delete', [], 'Delete');
    $this->assertItemCount('item', 2);
    $this->assertItemCount('channel', 2);
    $this->assertItemCount('source', 1);

    // Delete channel.
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/2/delete', [], 'Delete');
    $this->assertItemCount('channel', 1);
    $this->assertItemCount('source', 0);
    $this->assertItemCount('item', 0);
  }

  /**
   * Create feeds.
   */
  protected function createFeeds() {
    $edit = [
      'label' => 'Timeline 1',
      'id' => 'timeline',
      'path' => $this->timeline_path_1,
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
    $edit = [
      'label' => 'Timeline 2',
      'id' => 'timeline_2',
      'path' => $this->timeline_path_2,
      'feedTitle' => 'Timeline',
      'ownerId' => 1,
      'excludeIndexing' => 1,
      'limit' => 10,
      'author' => '<a class="u-url p-name" href="/">Your name</a><img src="https://example.com/image/avatar.png" class="u-photo hidden" alt="Your name">',
      'bundles[]' => [
        'node|page' => 'node|page'
      ],
    ];
    $this->drupalPostForm('admin/config/services/indieweb/feeds/add', $edit, 'Save');
  }

  /**
   * Create channels.
   */
  protected function createChannels() {
    $edit = ['title' => 'Channel 1'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/add-channel', $edit, 'Save');
    $edit = ['title' => 'Channel 2'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/add-channel', $edit, 'Save');
    // Order them too.
    $edit = [
      'entities[1][weight]' => -10,
      'entities[2][weight]' => -9,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels', $edit, 'Save');
  }

  /**
   * Create sources.
   */
  protected function createSources() {
    $edit = ['url' => 'internal:/microsub-timeline/1', 'channel_id' => '1'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/add-source', $edit, 'Save');
    $edit = ['url' => 'internal:/microsub-timeline/2', 'channel_id' => '2'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/add-source', $edit, 'Save');
  }

  /**
   * Assert count in a table.
   *
   * @param $type
   *   Either channel, source or item.
   * @param $expected_total
   *   The total to expect
   */
  protected function assertItemCount($type, $expected_total) {
    $table = 'microsub_' . $type;
    $query = 'SELECT count(id) FROM {' . $table . '}';
    $total = \Drupal::database()->query($query)->fetchField();
    self::assertEquals($expected_total, (int) $total);
  }

  /**
   * Clears a table.
   *
   * @param $type
   */
  protected function clear($type) {
    $table = 'microsub_' . $type;
    \Drupal::database()->truncate($table)->execute();
  }

  /**
   * Fetch items, use both cron and drush.
   */
  protected function fetchItems() {
    module_load_include('inc', 'indieweb', 'indieweb.drush');
    drush_indieweb_microsub_fetch_items();
    indieweb_cron();
  }

  /**
   * Reset next fetch.
   *
   * @param $source_id
   */
  protected function resetNextFetch($source_id) {
    \Drupal::database()
      ->update('microsub_source')
      ->fields(['fetch_next' => 0])
      ->condition('id', $source_id)
      ->execute();
  }

}

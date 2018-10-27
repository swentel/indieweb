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
  protected $timeline_path_1 = '/timeline/1';

  /**
   * Timeline path.
   *
   * @var string
   */
  protected $timeline_path_2 = '/timeline/2';

  /**
   * Tests microsub functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMicrosub() {

    // Create feeds, channels and sources.
    $this->drupalLogin($this->adminUser);
    $this->createFeeds();
    $this->createChannels();
    $this->createSources();
    $this->drupalLogout();

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

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->microsub_path);
    $this->drupalLogout();

    $this->drupalGet('indieweb/microsub');
    $this->assertSession()->statusCodeEquals(401);

    // ----------------------------------------------------------------
    // Fetch items
    // ----------------------------------------------------------------

    // Create an article
    $settings = [
      'type' => 'article',
      'title' => 'An article',
      'body' => [
        'value' => 'This is an article on the timeline feed',
        'format' => filter_default_format()
      ],
      'uid' => 1,
    ];
    $this->createNode($settings);

    // TODO fix this
    //$this->fetchItems();
    //$this->assertItemCount('item', 1);

    // ----------------------------------------------------------------
    // channels and timeline
    // ----------------------------------------------------------------

    /*$query = [];
    $type = 'get';
    $response = $this->sendMicrosubRequest($query, $type, 'is_totally_invalid');
    self::assertEqual($response['code'], 403);

    $query = ['action' => 'channels'];
    $response = $this->sendMicrosubRequest($query);
    self::assertEqual($response['code'], 200);
    $body = json_decode($response['body']);
    self::assertTrue($body[0]['name'] == 'Channel 1');
    self::assertTrue($body[1]['name'] == 'Channel 2');*/

    // TODO more tests
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
      'id' => 'timeline',
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
    $this->drupalLogin($this->adminUser);
    $edit = ['title' => 'Channel 1'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/add-channel', $edit, 'Save');
    $edit = ['title' => 'Channel 2'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/add-channel', $edit, 'Save');
  }

  /**
   * Create sources.
   */
  protected function createSources() {
    $this->drupalLogin($this->adminUser);
    $edit = ['url' => Url::fromUri('internal:/timeline/1', ['absolute' => TRUE])->toString(), 'channel_id' => '1'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/add-source', $edit, 'Save');
    $edit = ['url' => Url::fromUri('internal:/timeline/2', ['absolute' => TRUE])->toString(), 'channel_id' => '2'];
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
    $total = \Drupal::database()->query('SELECT count(mid) FROM {' . $table . '}')->fetchField();
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

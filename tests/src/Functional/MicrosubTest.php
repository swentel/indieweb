<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\indieweb_microsub\Entity\MicrosubSource;

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
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'node',
    'indieweb_test',
    'indieweb_microformat',
    'indieweb_microsub',
    'indieweb_feed',
    'indieweb_context',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createNodeTypes(['reply']);

    // Set microformat class to in-reply-to
    $display = entity_get_display('node', 'reply', 'default');
    $options = [
      'region' => 'content',
      'type' => 'link_microformat',
      'settings' => [
        'microformat_class' => 'u-in-reply-to',
        'trim_length' => 80,
        'url_only' => FALSE,
        'url_plain' => FALSE,
        'rel' => 0,
        'target' => 0,
      ]
    ];
    $display->setComponent('field_reply_link', $options);
    $display->save();

    // Create feeds, channels and sources.
    $this->drupalLogin($this->adminUser);
    $this->createFeeds();
    $this->createChannels();
    $this->createSources();

    // Post context handler.
    $edit = ['handler' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/post-context', $edit, 'Save configuration');

    $this->drupalLogout();

    drupal_flush_all_caches();
  }

  /**
   * Tests microsub functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
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
    $edit = ['microsub_expose_link_tag' => TRUE, 'microsub_endpoint' => 'https://example.com/microsub', 'microsub_internal_handler' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->header_link);

    $this->drupalGet($this->microsub_path);
    $this->assertSession()->statusCodeEquals(404);

    // ----------------------------------------------------------------
    // Use internal built-in
    // ----------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    $edit = ['microsub_internal' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    // Set IndieAuth token endpoints.
    $this->setIndieAuthEndPoints();

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

    $this->drupalGet($this->timeline_path_1);
    $this->drupalGet($this->timeline_path_2);

    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 3);

    // ----------------------------------------------------------------
    // channels, timeline and post contexts.
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
    $this->assertMicrosubItemCount('item', 4);

    $response = $this->sendMicrosubRequest($query);
    $body = json_decode($response['body']);
    self::assertEquals(0, $body->channels[1]->unread);
    self::assertEquals(1, $body->channels[2]->unread);

    // Delete an item.
    $query = ['action' => 'timeline', 'method' => 'remove', 'entry' => 4];
    $this->sendMicrosubRequest($query, 'POST');
    $this->assertMicrosubItemCount('item', 3, 1);
    $this->assertMicrosubItemCount('item', 1, 0);
    $this->assertMicrosubItemCount('item', 4);
    $this->resetNextFetch(1);
    $this->resetNextFetch(2);
    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 3, 1);
    $this->assertMicrosubItemCount('item', 1, 0);
    $this->assertMicrosubItemCount('item', 4);

    // Test post context.
    $page = $this->createNode(['type' => 'page', 'title' => 'microsub page', 'body' => ['value' => 'This should be a context for a microsub item']]);
    $reply_settings = [
      'type' => 'reply',
      'title' => 'Wow, that works !',
      'body' => ['value' => 'Nicely done man!'],
      // Use an external link so xray can see it as an 'in-reply-to'.
      'field_reply_link' => ['uri' => 'https://example.com/fetch-content']
    ];
    $this->createNode($reply_settings);
    $this->drupalGet($this->timeline_path_1);
    $this->resetNextFetch(1);
    $this->resetNextFetch(2);

    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 6);
    $this->assertMicrosubSourceItemsInFeed(1, 2);
    $this->assertMicrosubSourceItemsInFeed(2, 4);
    $id = \Drupal::database()->query('SELECT id FROM {microsub_item} where post_type = :reply', [':reply' => 'reply'])->fetchField();
    $item = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->loadUnchanged($id);
    $this->assertPostContextQueueItems(['https://example.com/fetch-content'], $item->id());

    // Change the item to the page url
    $record = \Drupal::database()->query('SELECT item_id, data FROM {queue}')->fetchObject();
    $data = unserialize($record->data);
    $data['url'] = $page->toUrl('canonical', ['absolute' => TRUE])->toString();
    \Drupal::database()
      ->update('queue')
      ->fields(['data' => serialize($data)])
      ->condition('item_id', $record->item_id)
      ->execute();

    $this->runPostContextQueue();
    $this->assertPostContextQueueItems();
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubItemInterface $item */
    $item = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->loadUnchanged($id);
    self::assertTrue(!empty($item->getContext()));
    $context_data = $item->getContext();
    $url = $page->toUrl('canonical', ['absolute' => TRUE])->toString();
    self::assertEqual($page->get('body')->value, $context_data->{$url}->content);

    $query = ['action' => 'timeline', 'channel' => 1];
    $response = $this->sendMicrosubRequest($query);
    $body = json_decode($response['body']);
    self::assertTrue(isset($body->items[0]->references));
    self::assertTrue(isset($body->items[0]->references->{$url}));
    self::assertTrue(!isset($body->items[1]->references));

    // Exclude replies from channel.
    $this->drupalLogin($this->adminUser);
    $edit = ['exclude_post_type[reply]' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/1/edit', $edit, 'Save');
    \Drupal::database()->update('microsub_item')->fields(['is_read' => 0])->condition('channel_id', 1)->execute();
    $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadUnchanged(1);
    $unread = (int) $channel->getUnreadCount();
    self::assertEquals(1, $unread);
    self::assertEquals(2, $channel->getItemCount());

    $query = ['action' => 'timeline', 'channel' => 1];
    $response = $this->sendMicrosubRequest($query);
    $body = json_decode($response['body']);
    self::assertTrue(count($body->items) == 1);
    $type = 'post-type';
    self::assertTrue($body->items[0]->{$type} == 'article');

    // Test disabled source.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/2/edit', ['status' => FALSE], 'Save');
    $this->microsubClear('item');
    $this->drupalLogout();
    $this->resetNextFetch(1);
    $this->resetNextFetch(2);
    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 2);

    // Test disabled channel.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/2/edit', ['status' => FALSE], 'Save');
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/2/edit', ['status' => TRUE], 'Save');
    $this->drupalLogout();

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadUnchanged(2);
    self::assertTrue($channel->getStatus() === FALSE);

    $this->microsubClear('item');
    $this->assertMicrosubItemCount('item', 0);
    $this->resetNextFetch(1);
    $this->resetNextFetch(2);
    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 2);
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/2/edit', ['status' => TRUE], 'Save');
    // Load so caches are cleared - weird testbot.
    $channel = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadUnchanged(2);
    self::assertTrue($channel->getStatus() === TRUE);

    $this->resetNextFetch(1);
    $this->resetNextFetch(2);
    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 6);

    // Move source to another channel.
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/2/edit', ['channel_id' => '1'], 'Save');
    $this->assertMicrosubItemCount('item', 2, NULL, 1, 1);
    $this->assertMicrosubItemCount('item', 4, NULL, 2, 1);
    $this->assertMicrosubItemCount('item', 6, NULL, NULL, 1);
    $this->assertMicrosubItemCount('item', 0, NULL, NULL, 2);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/2/edit', ['channel_id' => '2'], 'Save');
    $this->assertMicrosubItemCount('item', 2, NULL, 1, 1);
    $this->assertMicrosubItemCount('item', 4, NULL, 2, 2);
    $this->assertMicrosubItemCount('item', 2, NULL, NULL, 1);
    $this->assertMicrosubItemCount('item', 4, NULL, NULL, 2);

    // Delete source.
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/1/delete', [], 'Delete');
    $this->assertMicrosubItemCount('channel', 2);
    $this->assertMicrosubItemCount('source', 1);
    $this->assertMicrosubItemCount('item', 4);

    // Delete channel.
    $this->drupalPostForm('admin/config/services/indieweb/microsub/channels/2/delete', [], 'Delete');
    $this->assertMicrosubItemCount('channel', 1);
    $this->assertMicrosubItemCount('source', 0);
    $this->assertMicrosubItemCount('item', 0);
  }

  /**
   * Tests the cleanup functionality.
   */
  function testMicrosubCleanup() {
    $source = MicrosubSource::load(2);
    $source->delete();

    $this->createNodes(10, 'article');

    $this->drupalLogin($this->adminUser);
    $edit = ['microsub_internal' => TRUE, 'microsub_internal_handler' => 'drush', 'microsub_internal_cleanup_items' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/microsub', $edit, 'Save configuration');

    // Set all microformats too.
    $microformats = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_name_exclude_node_type' => 'page',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $microformats, 'Save configuration');

    $this->fetchItems();
    $this->assertMicrosubItemCount('item', 10);

    $this->createNodes(1, 'article', 11, time() - 1800);
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/1/edit', ['items_to_keep' => 5], 'Save');

    $this->resetNextFetch(1);
    $this->fetchItems();
    $this->assertItemTitles(range(6, 11));
    $this->assertMicrosubItemCount('item', 6);

    $this->createNodes(2, 'article', 12, time() - 900);
    $this->resetNextFetch(1);
    $this->fetchItems();
    $this->assertItemTitles(range(8, 13));
    $this->assertMicrosubItemCount('item', 6);
  }

  /**
   * Assert titles.
   *
   * @param $titles
   */
  protected function assertItemTitles($titles) {
    $total = 0;
    $total_to_find = count($titles);
    $records = \Drupal::database()->query('SELECT * FROM {microsub_item} order by timestamp DESC');
    foreach ($records as $record) {
      $data = json_decode($record->data);
      foreach ($titles as $nr) {
        if ($data->name == 'Number ' . $nr) {
          $total++;
        }
      }
    }

    self::assertEquals($total_to_find, $total);
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
      'limit' => 20,
      'author' => '<a class="u-url p-name" href="/">Your name</a><img src="https://example.com/image/avatar.png" class="u-photo hidden" alt="Your name">',
      'bundles[]' => [
        'node|article' => 'node|article',
        'node|reply' => 'node|reply',
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
        'node|page' => 'node|page',
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
    $edit = ['url' => 'internal:/microsub-timeline/1', 'channel_id' => '1', 'post_context[reply]' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/add-source', $edit, 'Save');
    $edit = ['url' => 'internal:/microsub-timeline/2', 'channel_id' => '2'];
    $this->drupalPostForm('admin/config/services/indieweb/microsub/sources/add-source', $edit, 'Save');
  }

  /**
   * Fetch items, use both cron and drush.
   */
  protected function fetchItems() {
    if (\Drupal::config('indieweb_microsub.settings')->get('microsub_internal') &&
      \Drupal::config('indieweb_microsub.settings')->get('microsub_internal_handler') == 'drush') {
      \Drupal::service('indieweb.microsub.client')->fetchItems();
    }
    indieweb_microsub_cron();
  }

  /**
   * Reset next fetch.
   *
   * @param $source_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function resetNextFetch($source_id) {
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source */
    $source = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_source')->loadUnchanged($source_id);
    $source->setNextFetch(0);
    $source->setHash("");
    $source->save();
  }

}

<?php

namespace Drupal\Tests\indieweb\Functional;
use Drupal\Core\Url;

/**
 * Tests integration of microsub.
 *
 * @group indieweb
 */
class PublishTest extends IndiewebBrowserTestBase {

  // Use standard because we need a lot of functionality.
  protected $profile = 'standard';

  // The channels used.
  protected $channels = "Twitter (bridgy)|https://brid.gy/publish/twitter\nFacebook (bridgy)|https://brid.gy/publish/facebook\nAnother channel|https://example.com/publish/test";

  // Body text.
  protected $body_text = 'A really nice article';

  // Authenticated user which can only create articles.
  protected $authUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // TODO remove this
    // @see https://github.com/swentel/indieweb/issues/77
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

    $this->authUser = $this->drupalCreateUser(['create article content']);
  }

  /**
   * Tests publish functionality.
   */
  public function testPublish() {

    // Login and configure channels.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/publish', ['channels' => $this->channels], 'Save configuration');

    // Verify channels.
    $channels = indieweb_get_publishing_channels();
    $posted_channels = explode("\n", $this->channels);
    foreach ($posted_channels as $line) {
      $line = trim($line);
      list($label, $url) = explode('|', $line);
      $this->assertTrue(isset($channels[$url]) && $channels[$url] == $label);
    }

    // Go to manage display and verify the fields are there.
    $this->drupalGet('admin/structure/types/manage/article/display');
    foreach ($channels as $url => $name) {
      $this->assertSession()->pageTextContains($name);
    }

    // Create a node and verify none of those fields
    //   - are available on the node form
    //   - are not rendered in the markup.
    // and no queue item has been created.
    $edit = [
      'title[0][value]' => 'Hello indieweb',
      'body[0][value]' => 'A really nice article',
    ];
    $this->drupalGet('node/add/article');
    $this->assertChannelFieldsOnNodeForm($channels);
    $this->drupalPostForm('node/add/article', $edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->body_text);
    $this->assertChannelFieldsOnNodeView($channels, FALSE);
    $this->assertQueueItems([]);

    // Configure manage display to display the fields.
    $edit = [];
    foreach ($channels as $url => $name) {
      $machine_name = 'fields[' . indieweb_get_machine_name_from_url($url) . '][region]';
      $edit[$machine_name] = 'content';
    }
    $this->drupalPostForm('admin/structure/types/manage/article/display', $edit, 'Save');

    // Verify the channels are now visible on the node view.
    $this->drupalGet('node/1');
    $this->assertChannelFieldsOnNodeView($channels);

    // Verify that an authenticated user does not see the publish section.
    $this->drupalLogout();
    $this->drupalLogin($this->authUser);
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('title[0][value]');
    $this->assertChannelFieldsOnNodeForm($channels, FALSE);

    // Go back to node and select two channels to publish.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $channels_queued = [];
    $channels_queued[] = 'https://brid.gy/publish/twitter';
    $channels_queued[] = 'https://example.com/publish/test';
    $edit = [];
    foreach ($channels_queued as $url) {
      $edit['indieweb_publish_channels[' . $url . ']'] = TRUE;
    }
    $this->drupalPostForm('node/1/edit', $edit, 'Save');
    $this->assertQueueItems($channels_queued, 1);
  }

  /**
   * Assert queue items.
   *
   * @param array $channels
   * @param $nid
   */
  protected function assertQueueItems($channels = [], $nid = NULL) {
    if ($channels) {
      $query = 'SELECT count(item_id) FROM {queue} WHERE name = :name';
      $count = \Drupal::database()->query($query, [':name' => $this->queue_name])->fetchField();
      $this->assertTrue($count == count($channels));

      $query = 'SELECT * FROM {queue} WHERE name = :name';
      $records = \Drupal::database()->query($query, [':name' => $this->queue_name]);
      foreach ($records as $record) {
        $data = unserialize($record->data);
        if (!empty($data['source_url']) && !empty($data['target_url'])) {
          $this->assertTrue(in_array($data['target_url'], $channels));
          $this->assertEquals($data['source_url'], Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString());
        }
      }
    }
    else {
      $query = 'SELECT count(item_id) FROM {queue} WHERE name = :name';
      $count = \Drupal::database()->query($query, [':name' => $this->queue_name])->fetchField();
      $this->assertFalse($count);
    }
  }

  /**
   * Verify that channel field are (not) available on the node form.
   *
   * @param $channels
   * @param bool $visible
   */
  protected function assertChannelFieldsOnNodeForm($channels, $visible = TRUE) {

    foreach ($channels as $url => $name) {
      if ($visible) {
        $this->assertSession()->responseContains($url);
        $this->assertSession()->pageTextContains($name);
      }
      else {
        $this->assertSession()->responseNotContains($url);
        $this->assertSession()->pageTextNotContains($name);
      }
    }
  }

  /**
   * Verify that channel field are (not) rendered in the node markup.
   *
   * @param $channels
   * @param bool $visible
   */
  protected function assertChannelFieldsOnNodeView($channels, $visible = TRUE) {

    foreach ($channels as $url => $name) {
      if ($visible) {
        $this->assertSession()->responseContains($url);
      }
      else {
        $this->assertSession()->responseNotContains($url);
      }
    }
  }

}

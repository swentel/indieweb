<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides the base class for web tests for Indieweb.
 */
abstract class IndiewebBrowserTestBase extends BrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'node',
    'indieweb',
    'indieweb_test',
  ];

  /**
   * An admin user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * A simple authenticated user.
   *
   * @var
   */
  protected $authUser;

  /**
   * Default title.
   *
   * @var string
   */
  protected $title_text = 'Hello indieweb';

  /**
   * Default body text.
   *
   * @var string
   */
  protected $body_text = 'Getting on the Indieweb is easy. Just install this module!';

  /**
   * Default summary text.
   *
   * @var string
   */
  protected $summary_text = 'A summary';

  /**
   * RSVP settings.
   *
   * @var string
   */
  protected $rsvp_settings = "yes|I am going!\nno|I can not go\nmaybe|I might go\ninterested|Interested, but will decide later!";

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Use administrator role, less hassle for browsing around.
    $this->adminUser = $this->drupalCreateUser([], NULL, TRUE);

    // Set front page to custom page instead of /user/login or /user/x
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('page.front', '/indieweb-test-front')
      ->save();
  }

  /**
   * Creates several node types that are useful for micropub, posting etc.
   */
  protected function createNodeTypes() {

    $this->drupalLogin($this->adminUser);

    foreach (['like', 'bookmark', 'repost', 'reply', 'rsvp', 'event'] as $type) {
      $edit = ['name' => $type, 'type' => $type];
      $this->drupalPostForm('admin/structure/types/add', $edit, 'Save and manage fields');

      if ($type != 'event') {
        $edit = ['new_storage_type' => 'link', 'label' => 'Link', 'field_name' => $type . '_link'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/fields/add-field', $edit, 'Save and continue');
        $this->drupalPostForm(NULL, [], 'Save field settings');
        $this->drupalPostForm(NULL, [], 'Save settings');
        $edit = ['fields[field_' . $type . '_link][type]' => 'link_microformat'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/display', $edit, 'Save');
      }

      if ($type == 'rsvp') {
        $edit = ['new_storage_type' => 'list_string', 'label' => 'RSVP', 'field_name' => 'rsvp'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/fields/add-field', $edit, 'Save and continue');
        $this->drupalPostForm(NULL, ['settings[allowed_values]' => $this->rsvp_settings], 'Save field settings');
        $this->drupalPostForm(NULL, [], 'Save settings');
        $edit = ['fields[field_rsvp][type]' => 'list_microformat'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/display', $edit, 'Save');
      }

      if ($type == 'event') {
        $edit = ['new_storage_type' => 'daterange', 'label' => 'Date', 'field_name' => 'date'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/fields/add-field', $edit, 'Save and continue');
        $this->drupalPostForm(NULL, [], 'Save field settings');
        $this->drupalPostForm(NULL, [], 'Save settings');
      }
    }

    drupal_flush_all_caches();
  }

  /**
   * Enable webmention functionality in the UI.
   */
  protected function enableWebmention() {
    $edit = [
      'webmention_enable' => 1,
      'pingback_enable' => 1,
      'webmention_secret' => 'valid_secret',
      'webmention_endpoint' => 'https://webmention.io/example.com/webmention',
      'pingback_endpoint' => 'https://webmention.io/webmention?forward=http://example.com/webmention/notify',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');
  }

  /**
   * Sends a webmention request.
   *
   * @param $post
   * @param $debug
   *
   * @return int $status_code
   */
  protected function sendWebmentionRequest($post = [], $debug = FALSE) {
    $micropub_endpoint = Url::fromRoute('indieweb.webmention.notify', [], ['absolute' => TRUE])->toString();

    $client = \Drupal::httpClient();
    try {
      $response = $client->post($micropub_endpoint, ['json' => $post]);
      $status_code = $response->getStatusCode();
    }
    catch (\Exception $e) {
      $status_code = 400;
      if (strpos($e->getMessage(), '404 Not Found') !== FALSE) {
        $status_code = 404;
      }
      // Use following line if you want to debug the exception in tests.
      if ($debug) {
        debug($e->getMessage());
      }
    }

    return $status_code;
  }

  /**
   * Sends a micropub request.
   *
   * @param $post
   * @param $access_token
   * @param $debug
   * @param $auth_in_body
   *
   * @return int $status_code
   */
  protected function sendMicropubRequest($post, $access_token = 'this_is_a_valid_token', $debug = FALSE, $auth_in_body = FALSE) {
    $auth = 'Bearer ' . $access_token;
    $micropub_endpoint = Url::fromRoute('indieweb.micropub.endpoint', [], ['absolute' => TRUE])->toString();

    $client = \Drupal::httpClient();
    $headers = [
      'Accept' => 'application/json',
    ];

    if (!$auth_in_body) {
      $headers['Authorization'] = $auth;
    }
    else {
      $post['access_token'] = $access_token;
    }

    try {
      $response = $client->post($micropub_endpoint, ['form_params' => $post, 'headers' => $headers]);
      $status_code = $response->getStatusCode();
    }
    catch (\Exception $e) {
      // Assume 400 on exception.
      $status_code = 400;
      if ($debug) {
        debug($e->getMessage());
      }
    }

    return $status_code;
  }

  /**
   * Assert node count.
   *
   * @param $count
   * @param $type
   */
  protected function assertNodeCount($count, $type) {
    $node_count = \Drupal::database()->query('SELECT count(nid) FROM {node} WHERE type = :type', [':type' => $type])->fetchField();
    self::assertEquals($count, $node_count);
  }

  /**
   * Get the last nid.
   *
   * @param $type
   *
   * @return mixed
   */
  protected function getLastNid($type = '') {
    if ($type) {
      return \Drupal::database()->query('SELECT nid FROM {node} WHERE type = :type ORDER by nid DESC LIMIT 1', [':type' => $type])->fetchField();
    }
    else {
      return \Drupal::database()->query('SELECT nid FROM {node} ORDER by nid DESC LIMIT 1')->fetchField();
    }
  }

  /**
   * Assert queue items.
   *
   * @param array $channels
   * @param $nid
   */
  protected function assertQueueItems($channels = [], $nid = NULL) {
    if ($channels) {
      $count = \Drupal::queue(WEBMENTION_QUEUE_NAME)->numberOfItems();
      $this->assertTrue($count == count($channels));

      // We use a query here, don't want to use a while loop. When there's
      // nothing in the queue yet, the table won't exist, so the query will
      // fail. When the first item is inserted, we'll be fine.
      try {
        $query = 'SELECT * FROM {queue} WHERE name = :name';
        $records = \Drupal::database()->query($query, [':name' => WEBMENTION_QUEUE_NAME]);
        foreach ($records as $record) {
          $data = unserialize($record->data);
          if (!empty($data['source']) && !empty($data['target'])) {
            $this->assertTrue(in_array($data['target'], $channels));
            $this->assertEquals($data['source'], Url::fromRoute('entity.node.canonical', ['node' => $nid], ['absolute' => TRUE])->toString());
          }
        }
      }
      catch (\Exception $ignored) {}
    }
    else {
      $count = \Drupal::queue(WEBMENTION_QUEUE_NAME)->numberOfItems();
      $this->assertFalse($count);
    }
  }

  /**
   * Truncate the queue.
   */
  protected function clearQueue() {
    \Drupal::database()->delete('queue')->condition('name', WEBMENTION_QUEUE_NAME)->execute();
    $this->assertQueueItems();
  }

  /**
   * Create a syndication record.
   *
   * @param $url
   * @param string $entity_type_id
   * @param int $entity_id
   *
   * @throws \Exception
   */
  protected function createSyndication($url, $entity_type_id = 'node', $entity_id = 1) {
    $values = [
      'entity_id' => $entity_id,
      'entity_type_id' => $entity_type_id,
      'url' => $url
    ];

    \Drupal::database()
      ->insert('webmention_syndication')
      ->fields($values)
      ->execute();
  }

}

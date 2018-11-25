<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Component\Utility\Random;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Signer\Rsa\Sha512;

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
    'block',
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
   * @var \Drupal\Core\Session\AccountInterface
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
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

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
   *
   * @param $types
   *   Only create those types.
   */
  protected function createNodeTypes($types = []) {

    $this->drupalLogin($this->adminUser);

    foreach (['like', 'bookmark', 'repost', 'reply', 'rsvp', 'event'] as $type) {

      if (!empty($types)) {
        if (!in_array($type, $types)) {
          continue;
        }
      }

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
        $edit = ['existing_storage_name' => 'indieweb_rsvp', 'existing_storage_label' => 'RSVP'];
        $this->drupalPostForm('admin/structure/types/manage/' . $type . '/fields/add-field', $edit, 'Save and continue');
        $edit = ['fields[indieweb_rsvp][type]' => 'list_microformat'];
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
   * Configures webmention functionality in the UI.
   *
   * @param $edit
   */
  protected function configureWebmention($edit = []) {
    $edit += [
      'webmention_internal' => FALSE,
      'webmention_notify' => TRUE,
      'webmention_expose_link_tag' => TRUE,
      'webmention_uid' => $this->adminUser->id(),
      'webmention_secret' => 'valid_secret',
      'webmention_endpoint' => 'https://webmention.io/example.com/webmention',
      'pingback_notify' => TRUE,
      'pingback_expose_link_tag' => TRUE,
      'pingback_internal' => FALSE,
      'pingback_endpoint' => 'https://webmention.io/webmention?forward=http://example.com/webmention/notify',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');
  }

  /**
   * Creates a page and send webmention to url potentially.
   *
   * @param $target_url
   * @param $publish
   * @param $custom
   */
  protected function createPage($target_url = '', $publish = FALSE, $custom = FALSE, $edit = []) {
    $edit += [
      'title[0][value]' => 'It sure it!',
      'body[0][value]' => 'And here is mine!',
    ];

    if ($publish) {
      $edit['indieweb_syndication_targets[' . $target_url . ']'] = TRUE;
    }

    if ($custom) {
      $edit['indieweb_send_custom_url'] = $target_url;
    }

    $this->drupalPostForm('node/add/page', $edit, 'Save');
  }

  /**
   * Change block configuration.
   *
   * @param $block_id
   *   The block id.
   * @param $edit
   *   The edit
   */
  protected function changeBlockConfiguration($block_id, $edit) {
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/structure/block/manage/' . $block_id, $edit, 'Save block');
    $this->drupalLogout();
  }

    /**
   * Assert comment count.
   *
   * @param $count
   */
  protected function assertCommentCount($count) {
    $comment_count = \Drupal::database()->query('SELECT count(cid) FROM {comment_field_data}')->fetchField();
    self::assertEquals($count, $comment_count);
  }

  /**
   * Gets a minimum webmention notification payload.
   *
   * @param $node
   * @param string $secret
   *
   * @return array
   */
  protected function getWebmentionNotificationPayload(NodeInterface $node, $secret = 'in_valid_secret') {

    $webmention = [
      'secret' => $secret,
      'source' => 'http://external.com/page/1',
      'target' => '/node/' . $node->id(),
      'post' => [
        'type' => 'entry',
        'wm-property' => 'like-of',
        'content' => [
          'text' => 'Webmention from external.com'
        ],
      ],
    ];

    return $webmention;
  }

  /**
   * Sends a webmention notification request.
   *
   * @param $post
   * @param $json
   * @param $debug
   *
   * @return int $status_code
   */
  protected function sendWebmentionNotificationRequest($post = [], $json = TRUE, $debug = FALSE) {
    $notify_endpoint = Url::fromRoute('indieweb.webmention.notify', [], ['absolute' => TRUE])->toString();

    $client = \Drupal::httpClient();
    try {
      if ($json) {
        $response = $client->post($notify_endpoint, ['json' => $post]);
      }
      else {
        $response = $client->post($notify_endpoint, ['form_params' => $post]);
      }
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
   * Sends a pingback request.
   *
   * @param $post
   * @param $internal = FALSE
   *
   * @return int $status_code
   */
  protected function sendPingbackRequest($post = [], $internal = FALSE) {

    $endpoint = Url::fromRoute('indieweb.pingback.notify', [], ['absolute' => TRUE])->toString();
    if ($internal) {
      $endpoint = Url::fromRoute('indieweb.pingback.internal', [], ['absolute' => TRUE])->toString();
    }

    $client = \Drupal::httpClient();
    try {
      $response = $client->post($endpoint, ['form_params' => $post]);
      $status_code = $response->getStatusCode();
    }
    catch (\Exception $e) {
      $status_code = 400;
      if (strpos($e->getMessage(), '404 Not Found') !== FALSE) {
        $status_code = 404;
      }
    }

    return $status_code;
  }

  /**
   * Sends an internal webmention request.
   *
   * @param $source_url
   * @param $target_url
   *
   * @return null|\Psr\Http\Message\ResponseInterface
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function sendWebmentionInternalRequest($source_url, $target_url) {

    $post = [
      'source' => $source_url,
      'target' => $target_url,
    ];

    $url = Url::fromRoute('indieweb.webmention.internal')->toString();

    try {
      $response = $this->httpClient->request('post', $url, ['form_params' => $post]);
    }
    catch (ClientException $e) {
      $response = $e->getResponse();
    }
    catch (ServerException $e) {
      $response = $e->getResponse();
    }

    return $response;
  }

  /**
   * Sends a micropub request.
   *
   * @param $post
   * @param $access_token
   * @param $debug
   * @param $type
   *   Either POST or JSON (form_params or json)
   * @param $return_array_response
   *
   * @return array|int
   */
  protected function sendMicropubRequest($post, $access_token = 'is_valid', $debug = FALSE, $type = 'form_params', $return_array_response = FALSE) {
    $location = '';
    $micropub_endpoint = Url::fromRoute('indieweb.micropub.endpoint', [], ['absolute' => TRUE])->toString();

    $client = \Drupal::httpClient();
    $headers = [
      'Accept' => 'application/json',
      'Authorization' => 'Bearer ' . $access_token,
    ];

    try {
      $response = $client->post($micropub_endpoint, [$type => $post, 'headers' => $headers]);
      $status_code = $response->getStatusCode();
      $headersLocation = $response->getHeader('Location');
      if (!empty($headersLocation)) {
        $location = $headersLocation[0];
      }
    }
    catch (\Exception $e) {

      // Default 400 on exception.
      $status_code = 400;

      if (strpos($e->getMessage(), '401') !== FALSE) {
        $status_code = 401;
      }

      if (strpos($e->getMessage(), '403') !== FALSE) {
        $status_code = 403;
      }

      if ($debug) {
        debug($e->getMessage());
      }
    }

    if ($return_array_response) {
      return [
        'location' => $location,
        'code' => $status_code,
      ];
    }
    else {
      return $status_code;
    }
  }

  /**
   * Sends a microsub request.
   *
   * @param $query
   *   url params
   * @param $type
   *   either get or post
   * @param $access_token
   *   the access token
   * @param $debug
   *   Whether to debug or not.
   *
   * @return array|int
   */
  protected function sendMicrosubRequest($query = [], $type = 'get', $access_token = 'is_valid', $debug = FALSE) {
    $body = '';

    $headers = ['Accept' => 'application/json'];
    if ($access_token != 'no_auth_header') {
      $headers['Authorization'] = 'Bearer ' . $access_token;
    }

    try {

      if ($type == 'get') {
        $this->drupalGet('indieweb/microsub', ['query' => $query], $headers);
        $status_code = $this->getSession()->getStatusCode();
        $body = $this->getSession()->getPage()->getContent();
      }
      else {
        $client = \Drupal::httpClient();
        $microsub_endpoint = Url::fromRoute('indieweb.microsub.endpoint', [], ['absolute' => TRUE])->toString();
        $response = $client->post($microsub_endpoint, ['query' => $query, 'headers' => $headers]);
        $status_code = $response->getStatusCode();
      }

    }
    catch (\Exception $e) {

      // Default 400 on exception.
      $status_code = 400;

      if (strpos($e->getMessage(), '401') !== FALSE) {
        $status_code = 401;
      }

      if (strpos($e->getMessage(), '403') !== FALSE) {
        $status_code = 403;
      }

      if ($debug) {
        debug($e->getMessage());
      }
    }

    return ['body' => $body, 'code' => $status_code];
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
   * Assert webmention queue items.
   *
   * @param array $urls
   * @param $id
   */
  protected function assertWebmentionQueueItems($urls = [], $id = NULL) {
    if ($urls) {
      $count = \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->numberOfItems();
      $this->assertTrue($count == count($urls));

      // We use a query here, don't want to use a while loop. When there's
      // nothing in the queue yet, the table won't exist, so the query will
      // fail. When the first item is inserted, we'll be fine.
      try {
        $query = 'SELECT * FROM {queue} WHERE name = :name';
        $records = \Drupal::database()->query($query, [':name' => INDIEWEB_WEBMENTION_QUEUE]);
        foreach ($records as $record) {
          $data = unserialize($record->data);
          if (!empty($data['source']) && !empty($data['target']) && $id) {
            $this->assertTrue(in_array($data['target'], $urls));
            if ($data['entity_type_id'] == 'node') {
              $this->assertEquals($data['source'], Url::fromRoute('entity.node.canonical', ['node' => $id], ['absolute' => TRUE])->toString());
            }
            elseif ($data['entity_type_id'] == 'comment') {
              $this->assertEquals($data['source'], Url::fromRoute('entity.comment.canonical', ['comment' => $id], ['absolute' => TRUE])->toString());
            }
          }
        }
      }
      catch (\Exception $ignored) {
        //debug($ignored->getMessage());
      }
    }
    else {
      $count = \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->numberOfItems();
      $this->assertFalse($count);
    }
  }

  /**
   * Assert count in a table.
   *
   * @param $type
   *   Either channel, source or item.
   * @param $expected_total
   *   The total to expect
   * @param $status
   *   Whether to add the status condition or not.
   * @param $source_id
   *   The channel id.
   * @param $channel_id
   *   The channel id.
   */
  protected function assertMicrosubItemCount($type, $expected_total, $status = NULL, $source_id = NULL, $channel_id = NULL) {
    $table = 'microsub_' . $type;
    $query = \Drupal::database()
      ->select($table, 't');
    if (is_integer($status)) {
      $query->condition('status', $status);
    }
    if (is_integer($channel_id)) {
      $query->condition('channel_id', $channel_id);
    }
    if (is_integer($source_id)) {
      $query->condition('source_id', $source_id);
    }
    $total = $query->countQuery()->execute()->fetchField();
    self::assertEquals($expected_total, (int) $total);
  }

  /**
   * Clears a microsub table.
   *
   * @param $type
   */
  protected function microsubClear($type) {
    $table = 'microsub_' . $type;
    \Drupal::database()->truncate($table)->execute();
  }

  /**
   * Assert post context queue items.
   *
   * @param array $urls
   * @param $id
   */
  protected function assertPostContextQueueItems($urls = [], $id = NULL) {
    if ($urls) {
      $count = \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->numberOfItems();
      $this->assertTrue($count == count($urls));

      // We use a query here, don't want to use a while loop. When there's
      // nothing in the queue yet, the table won't exist, so the query will
      // fail. When the first item is inserted, we'll be fine.
      try {
        $query = 'SELECT * FROM {queue} WHERE name = :name';
        $records = \Drupal::database()->query($query, [':name' => INDIEWEB_POST_CONTEXT_QUEUE]);
        foreach ($records as $record) {
          $data = unserialize($record->data);
          $this->assertTrue(in_array($data['url'], $urls));
          $this->assertTrue($data['id'] == $id);
        }
      }
      catch (\Exception $ignored) {
        //debug($ignored->getMessage());
      }
    }
    else {
      $count = \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->numberOfItems();
      $this->assertFalse($count);
    }
  }

  /**
   * Assert there are post context items for
   *
   * @param $type
   *   Either node or microsub
   * @param $id
   *   The id
   * @param $found
   *   Whether there should be or not
   */
  protected function assertPostContextItem($type, $id, $found = TRUE) {
    if ($type == 'node') {
      $entry = \Drupal::database()->query('SELECT content FROM {indieweb_post_context} WHERE entity_id = :id', [':id' => $id])->fetchField();
      if ($found) {
        self::assertTrue($entry);
      }
      else {
        self::assertFalse($entry);
      }
    }
  }

  /**
   * Runs the post context queue. Both calls cron and drush.
   */
  protected function runPostContextQueue() {
    if (\Drupal::config('indieweb_context.settings')->get('handler') == 'drush') {
      \Drupal::service('indieweb.post_context.client')->handleQueue();
    }
    indieweb_context_cron();
  }

  /**
   * Truncates a queue.
   *
   * @param $queue_name
   */
  protected function clearQueue($queue_name = INDIEWEB_WEBMENTION_QUEUE) {
    \Drupal::database()->delete('queue')->condition('name', $queue_name)->execute();
    $this->assertWebmentionQueueItems();
  }

  /**
   * Runs the queue. Both calls cron and drush.
   */
  protected function runWebmentionQueue() {
    if (\Drupal::config('indieweb_webmention.settings')->get('send_webmention_handler') == 'drush') {
      \Drupal::service('indieweb.webmention.client')->handleQueue();
    }
    indieweb_webmention_cron();
  }

  /**
   * Asserts a send item.
   *
   * @param $source
   * @param $entity_id
   */
  protected function assertSendItem($source, $entity_id) {
    $object = \Drupal::database()->query('SELECT * FROM {webmention_send} WHERE source = :source AND entity_id = :id', [':source' => $source, ':id' => $entity_id])->fetchObject();
    if (isset($object->url)) {
      self::assertEquals($source, $object->url);
    }
    else {
      // explicit fail
      $this->assertTrue($object, 'no send item found');
    }
  }

  /**
   * Asserts no send item.
   *
   * @param $id
   */
  protected function assertNoSendItem($id) {
    $false = \Drupal::database()->query('SELECT * FROM {webmention_send} WHERE id = :id', [':id' => $id])->fetchObject();
    self::assertTrue($false === FALSE);
  }

  /**
   * Asserts a syndication.
   *
   * @param $entity_id
   * @param $url
   */
  protected function assertSyndication($entity_id, $url) {
    $object = \Drupal::database()->query('SELECT * FROM {webmention_syndication} WHERE entity_id = :id', [':id' => $entity_id])->fetchObject();
    if (isset($object->url)) {
      self::assertEquals($url, $object->url);
    }
    else {
      // explicit fail
      $this->assertTrue($object, 'no syndication found');
    }
  }

  /**
   * Asserts no syndication.
   *
   * @param $entity_id
   */
  protected function assertNoSyndication($entity_id) {
    $false = \Drupal::database()->query('SELECT * FROM {webmention_syndication} WHERE entity_id = :id', [':id' => $entity_id])->fetchField();
    self::assertTrue($false === FALSE);
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

  /**
   * Set IndieAuth endpoint.
   *
   * @param $login
   * @param $logout
   *
   * This function assumes you are authenticated already.
   */
  protected function setIndieAuthEndPoints($login = FALSE, $logout = FALSE) {
    if ($login) {
      $this->drupalLogin($this->adminUser);
    }

    $edit = ['expose_link_tag' => 1, 'token_endpoint' => Url::fromRoute('indieweb_test.token_endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    if ($logout) {
      $this->drupalLogout();
    }
  }

  /**
   * Use the internal IndieAuth token endpoint.
   *
   * @param bool $createToken
   *   Whether to create a token or not.
   * @param string $scopes
   *
   * @return null|string
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setIndieAuthInternal($createToken = FALSE, $scopes = '') {
    $token = NULL;
    $this->drupalLogin($this->adminUser);

    $edit = [
      'auth_internal' => TRUE,
      'expose_link_tag' => TRUE,
      'generate_keys' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    if ($createToken) {
      $token = $this->createIndieAuthToken($scopes);
    }

    $this->drupalLogout();

    return $token;
  }

  /**
   * Creates an IndieAuth token.
   *
   * @param $scopes
   *
   * @return string
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createIndieAuthToken($scopes) {

    $created = time();
    $random = new Random();
    $access_token = $random->name(128);
    $signer = new Sha512();

    $JWT = (new Builder())
      ->setIssuer(\Drupal::request()->getSchemeAndHttpHost())
      ->setAudience('internal')
      ->setId($access_token, true)
      ->setIssuedAt($created)
      ->set('uid', 1)
      ->sign($signer,  file_get_contents(\Drupal::config('indieweb_indieauth.settings')->get('private_key')))
      ->getToken();

    $values = [
      'expire' => 0,
      'changed' => 0,
      'created' => $created,
      'access_token' => $access_token,
      'client_id' => 'test',
      'uid' => 1,
      'scope' => $scopes,
    ];

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface $token */
    $token = \Drupal::entityTypeManager()->getStorage('indieweb_indieauth_token')->create($values);
    $token->save();

    return (string) $JWT;
  }

  /**
   * Set IndieAuth to use the external endpoint.
   */
  protected function setIndieAuthExternal() {
    $this->drupalLogin($this->adminUser);
    $edit = [
      'auth_internal' => FALSE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');
    $this->drupalLogout();
  }

}

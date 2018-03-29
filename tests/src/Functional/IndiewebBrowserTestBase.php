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
   * Name of the queue.
   *
   * @var string
   */
  protected $queue_name = 'indieweb_publish';

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

    // Turn off rdf for now.
    \Drupal::service('module_installer')->uninstall(['rdf']);
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
   *
   * @return int $status_code
   */
  protected function sendMicropubRequest($post, $access_token = 'this_is_a_valid_token', $debug = FALSE) {
    $auth = 'Bearer ' . $access_token;
    $micropub_endpoint = Url::fromRoute('indieweb.micropub.endpoint', [], ['absolute' => TRUE])->toString();

    $client = \Drupal::httpClient();
    $headers = [
      'Accept' => 'application/json',
      'Authorization' => $auth,
    ];
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

}

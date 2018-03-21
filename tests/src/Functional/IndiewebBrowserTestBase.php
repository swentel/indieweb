<?php

namespace Drupal\Tests\indieweb\Functional;

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
   * An admin user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * The permissions of the admin user.
   *
   * @var string[]
   */
  protected $adminUserPermissions = [
    'administer indieweb',
    'administer webmention entities',
  ];

  /**
   * A simple authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $unauthorizedUser;

  /**
   * The anonymous user used for this test.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $anonymousUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the users used for the tests.
    $this->adminUser = $this->drupalCreateUser($this->adminUserPermissions);
    $this->anonymousUser = $this->drupalCreateUser();

    // Create an article node type, if not already present.
    if (!NodeType::load('article')) {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    // Create a page node type, if not already present.
    if (!NodeType::load('page')) {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Page',
      ]);
    }

    // Set front page to custom page instead of /user/login or /user/x
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('page.front', '/indieweb-test-front')
      ->save();

  }

}

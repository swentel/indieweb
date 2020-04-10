<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of 410.
 *
 * @group indieweb
 */
class GoneTest extends IndiewebBrowserTestBase {

  protected $defaultTheme = 'stark';

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'node',
    'rabbit_hole',
    'rh_node',
  ];

    /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->createContentType(['type' => 'page']);
  }

  /**
   * Tests the 410 plugin.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testGone() {
    $settings = [
      'type' => 'page',
      'uid' => 1,
      'title' => 'Nice page',
      'status' => 1,
      'body' => [
        'value' => 'This is visible',
        'format' => 'plain_text',
      ],
    ];
    $page = $this->createNode($settings);
    $this->drupalGet('node/' . $page->id());
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      'rh_action' => 'indieweb_410_gone',
    ];
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('node/1/edit', $edit, 'Save');
    $this->drupalLogout();

    $this->drupalGet('node/' . $page->id());
    $this->assertSession()->statusCodeEquals(410);
  }

}

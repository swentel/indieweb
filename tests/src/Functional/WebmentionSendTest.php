<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of sending webmentions.
 *
 * @group indieweb
 */
class WebmentionSendTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * The syndication targets used in this test.
   *
   * @var string
   */
  protected $syndication_targets = "Twitter (bridgy)|https://brid.gy/publish/twitter\nAnother channel|https://example.com/publish/test";

    /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'indieweb',
    'indieweb_webmention',
    'indieweb_test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->authUser = $this->drupalCreateUser(['create article content']);
  }

  /**
   * Tests send webmention functionality.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSendWebmention() {

    // Login and configure syndication targets.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/send', ['syndication_targets' => $this->syndication_targets], 'Save configuration');

    // Verify syndication targets.
    $syndication_targets = indieweb_get_syndication_targets();
    $posted_syndication_targets = explode("\n", $this->syndication_targets);
    foreach ($posted_syndication_targets as $line) {
      $line = trim($line);
      list($label, $url) = explode('|', $line);
      $this->assertTrue(isset($syndication_targets[$url]) && $syndication_targets[$url] == $label);
    }

    // Go to manage display and verify the fields are there.
    $this->drupalGet('admin/structure/types/manage/article/display');
    foreach ($syndication_targets as $url => $name) {
      $this->assertSession()->pageTextContains($name);
    }

    // Create a node and verify none of those fields
    //   - are available on the node form
    //   - are not rendered in the markup.
    // and no queue item has been created.
    $edit = [
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
    ];
    $this->drupalGet('node/add/article');
    $this->assertSyndicationTargetFieldsOnNodeForm($syndication_targets);
    $this->drupalPostForm('node/add/article', $edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->body_text);
    $this->assertSyndicationTargetFieldsOnNodeView($syndication_targets, FALSE);
    $this->assertWebmentionQueueItems([]);

    // Configure manage display to display the fields.
    $edit = [];
    foreach ($syndication_targets as $url => $name) {
      $machine_name = 'fields[' . indieweb_generate_machine_name_from_url($url) . '][region]';
      $edit[$machine_name] = 'content';
    }
    $this->drupalPostForm('admin/structure/types/manage/article/display', $edit, 'Save');

    // Verify the syndication targets are now visible on the node view.
    $this->drupalGet('node/1');
    $this->assertSyndicationTargetFieldsOnNodeView($syndication_targets);

    // Configure the back link.
    $this->drupalPostForm('admin/config/services/indieweb/send', ['bridgy_back_link' => 'never'], 'Save configuration');
    $this->drupalGet('node/1');
    $this->assertSyndicationTargetFieldsOnNodeView($syndication_targets, TRUE, 'never');
    $this->drupalPostForm('admin/config/services/indieweb/send', ['bridgy_back_link' => 'maybe'], 'Save configuration');
    $this->drupalGet('node/1');
    $this->assertSyndicationTargetFieldsOnNodeView($syndication_targets, TRUE, 'maybe');

    // Verify that an authenticated user does not see the publish section.
    $this->drupalLogout();
    $this->drupalLogin($this->authUser);
    $this->drupalGet('node/add/article');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('title[0][value]');
    $this->assertSyndicationTargetFieldsOnNodeForm($syndication_targets, FALSE);

    // Go back to node and select two syndication targets to publish.
    $this->drupalLogout();
    $this->drupalLogin($this->adminUser);
    $targets_queued = [];
    $targets_queued[] = 'https://brid.gy/publish/twitter';
    $targets_queued[] = 'https://example.com/publish/test';
    $edit = [];
    foreach ($targets_queued as $url) {
      $edit['indieweb_syndication_targets[' . $url . ']'] = TRUE;
    }
    $this->drupalPostForm('node/1/edit', $edit, 'Save');
    $this->assertWebmentionQueueItems($targets_queued, 1);
  }

  /**
   * Verify that target field are (not) available on the node form.
   *
   * @param $syndication_targets
   * @param bool $visible
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Behat\Mink\Exception\ResponseTextException
   */
  protected function assertSyndicationTargetFieldsOnNodeForm($syndication_targets, $visible = TRUE) {

    foreach ($syndication_targets as $url => $name) {
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
   * Verify that syndication target field are (not) rendered in the node markup.
   *
   * @param $syndication_targets
   * @param bool $visible
   * @param $add_back_link
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertSyndicationTargetFieldsOnNodeView($syndication_targets, $visible = TRUE, $add_back_link = 'always') {

    foreach ($syndication_targets as $url => $name) {
      if ($visible) {
        $this->assertSession()->responseContains($url);

        if ($add_back_link == 'never') {
          $this->assertSession()->responseContains('class="p-bridgy-omit-link" value="true"');
        }
        elseif ($add_back_link == 'maybe') {
          $this->assertSession()->responseContains('class="p-bridgy-omit-link" value="maybe"');
        }
        else {
          $this->assertSession()->responseNotContains('class="p-bridgy-omit-link" value="true"');
          $this->assertSession()->responseNotContains('class="p-bridgy-omit-link" value="maybe"');
        }

      }
      else {
        $this->assertSession()->responseNotContains('<a href="' . $url);
      }
    }
  }

}

<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of publishing.
 *
 * @group indieweb
 */
class PublishTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * The channels used in this test.
   *
   * @var string
   */
  protected $channels = "Twitter (bridgy)|https://brid.gy/publish/twitter\nFacebook (bridgy)|https://brid.gy/publish/facebook\nAnother channel|https://example.com/publish/test";

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
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
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
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

    // Configure the back link.
    $this->drupalPostForm('admin/config/services/indieweb/publish', ['back_link' => 'never'], 'Save configuration');
    $this->drupalGet('node/1');
    $this->assertChannelFieldsOnNodeView($channels, TRUE, 'never');
    $this->drupalPostForm('admin/config/services/indieweb/publish', ['back_link' => 'maybe'], 'Save configuration');
    $this->drupalGet('node/1');
    $this->assertChannelFieldsOnNodeView($channels, TRUE, 'maybe');

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
   * @param $add_back_link
   */
  protected function assertChannelFieldsOnNodeView($channels, $visible = TRUE, $add_back_link = 'always') {

    foreach ($channels as $url => $name) {
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
        $this->assertSession()->responseNotContains($url);
      }
    }
  }

}

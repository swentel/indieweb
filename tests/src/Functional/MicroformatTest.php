<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests integration of Microformats2.
 *
 * @group indieweb
 */
class MicroformatTest extends IndiewebBrowserTestBase {

  use TestFileCreationTrait;

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'indieweb',
    'indieweb_microformat',
    'indieweb_test',
  ];

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Microformats supported by this module.
   *
   * @var array
   */
  protected $microformats = ['h-entry', 'u-photo', 'e-content', 'p-summary', 'dt-published', 'u-url', 'p-name', 'p-bridgy-twitter-content'];

  /**
   * Tests microformats functionality.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMicroformats() {
    $this->drupalLogin($this->adminUser);

    // Add a summary field.
    $edit = ['new_storage_type' => 'string_long', 'label' => 'Summary', 'field_name' => 'summary'];
    $this->drupalPostForm('admin/structure/types/manage/article/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');

    // Configure that field and all microformats on the settings page.
    $edit = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_name_exclude_node_type' => 'page',
      'p_bridgy_twitter_content' => 1,
      'p_summary' => 'field_summary',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');

    // Create a node and verify all default microformats are printed.
    $image = current($this->getTestFiles('image'));
    $image_path = \Drupal::service('file_system')->realpath($image->uri);
    $edit = [
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
      'field_summary[0][value]' => $this->summary_text,
      'files[field_image_0]' => $image_path,
    ];
    $this->drupalPostForm('node/add/article', $edit, 'Save');
    $this->drupalPostForm(NULL, ['field_image[0][alt]' => 'Alternative'], 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($this->body_text);
    $this->assertMicroformats($this->microformats);

    // Turn them all off.
    $edit = [
      'h_entry' => 0,
      'u_photo' => 0,
      'e_content' => 0,
      'post_metadata' => 0,
      'p_summary' => '',
      'p_bridgy_twitter_content' => 0,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');
    $this->drupalGet('node/1');
    $this->assertMicroformats($this->microformats, FALSE, TRUE);

    // Turn all on again, exclude node page for p-name.
    $edit = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_name_exclude_node_type' => 'page',
      'p_bridgy_twitter_content' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');

    // Create a 'note', we use page for that. Assert that p-name is printed with
    // p-name and e-content.
    $edit = [
      'display_submitted' => 1,
    ];
    $this->drupalPostForm('admin/structure/types/manage/page', $edit, 'Save content type');
    $edit = [
      'title[0][value]' => $this->title_text,
      'body[0][value]' => $this->body_text,
    ];
    $this->drupalPostForm('node/add/page', $edit, 'Save');
    $this->assertMicroformats(['p-name'], TRUE, TRUE);

    // Add new content type, but with other content field and delete the
    // standard body field.
    $edit = [
      'name' => 'other',
      'type' => 'other',
    ];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save and manage fields');
    $edit = ['new_storage_type' => 'string_long', 'label' => 'Other area', 'field_name' => 'other'];
    $this->drupalPostForm('admin/structure/types/manage/other/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');
    $this->drupalPostForm('admin/structure/types/manage/other/fields/node.other.body/delete', [], 'Delete');

    // Configure other textarea field.
    $edit = [
      'e_content_fields' => 'field_other',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');

    $edit = [
      'title[0][value]' => $this->title_text,
      'field_other[0][value]' => 'This text will be via another field',
    ];
    $this->drupalPostForm('node/add/other', $edit, 'Save');
    $this->assertSession()->responseContains('e-content');

    // Test author block.
    $settings = [
      'region' => 'content',
      'name' => 'swentel',
      'note' => 'this is my note',
      'image' => 'https://example.com/image.png'
    ];
    $this->placeBlock('indieweb_author', $settings);
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('h-card');
    $this->assertSession()->responseContains('https://example.com/image.png');
    $this->assertSession()->pageTextContains('swentel');
    $this->assertSession()->pageTextContains('this is my note');
  }

  /**
   * Assert microformats.
   *
   * @param $formats
   * @param $visible
   * @param $all_off_or_e_content
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertMicroformats($formats = [], $visible = TRUE, $all_off_or_e_content = FALSE) {
    foreach ($formats as $class) {
      if ($visible) {

        // Make sure the HTML is not escaped.
        if ($class == 'p-name') {
          if ($all_off_or_e_content) {
            $this->assertSession()->responseContains('class="e-content p-name');
          }
          else {
            $this->assertSession()->responseContains('<span class="p-name">');
          }
        }

        $this->assertSession()->responseContains($class);
      }
      else {
        if ($class == 'p-name' && $visible && $all_off_or_e_content) {
          $this->assertSession()->responseContains('class="e-content p-name');
        }
        else {
          $this->assertSession()->responseNotContains($class);
        }
      }
    }
  }

}

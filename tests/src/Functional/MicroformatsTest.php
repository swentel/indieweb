<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\Tests\TestFileCreationTrait;

/**
 * Tests integration of microformats.
 *
 * @group indieweb
 */
class MicroformatsTest extends IndiewebBrowserTestBase {

  use TestFileCreationTrait;

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
   */
  public function testMicroformats() {
    $this->drupalLogin($this->adminUser);

    // Add a summary field.
    $edit = ['new_storage_type' => 'string_long', 'label' => 'Summary', 'field_name' => 'summary'];
    $this->drupalPostForm('admin/structure/types/manage/article/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');

    // Configure that field on the microformats settings page.
    $this->drupalPostForm('admin/config/services/indieweb/microformats', ['p_summary' => 'field_summary'], 'Save configuration');

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
    $this->assertMicroFormats($this->microformats);

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
    $this->assertMicroFormats($this->microformats, FALSE);
  }

  /**
   * Assert micro formats.
   *
   * @param $formats
   * @param $visible
   */
  protected function assertMicroFormats($formats = [], $visible = TRUE) {
    foreach ($formats as $class) {
      if ($visible) {
        $this->assertSession()->responseContains($class);
      }
      else {
        $this->assertSession()->responseNotContains($class);
      }
    }
  }

}

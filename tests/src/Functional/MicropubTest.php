<?php

namespace Drupal\Tests\indieweb\Functional;
use Drupal\Core\Url;

/**
 * Tests integration of micropub.
 *
 * @group indieweb
 */
class MicropubTest extends IndiewebBrowserTestBase {

  /**
   * The profile to use. Use Standard as we need a lot.
   *
   * @var string
   */
  protected $profile = 'standard';

  /**
   * Default note $_POST content.
   *
   * @var array
   */
  protected $note = [
    'h' => 'entry',
    'content' => 'A note content',
  ];

  /**
   * Tests micropub functionality.
   */
  public function testMicropub() {
    $this->drupalGet('<front>');

    // Request to micropub should be a 404.
    $this->drupalGet('indieweb/micropub');
    $this->assertSession()->statusCodeEquals(404);

    // Enable the endpoint.
    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('admin/config/services/indieweb/micropub', ['micropub_enable' => 1, 'micropub_add_header_link' => 1], 'Save configuration');
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');
    $this->drupalGet('indieweb/micropub');
    $this->assertSession()->statusCodeEquals(400);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('/indieweb/micropub');

    // Set IndieAuth token endpoint.
    $this->drupalLogin($this->adminUser);
    $edit = ['enable' => '1', 'expose' => 1, 'token_endpoint' => Url::fromRoute('indieweb_test.token_endpoint', [], ['absolute' => TRUE])->toString()];
    $this->drupalPostForm('admin/config/services/indieweb/indieauth', $edit, 'Save configuration');

    // Configure note, but set 'me' to invalid domain.
    $edit = ['note_create_node' => 1, 'note_node_type' => 'page', 'micropub_me' => 'https://indieweb.micropub.invalid.testdomain'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Send request to create a note, will fail because the 'me' is wrong.
    $this->drupalLogout();
    $code = $this->sendMicropubRequest($this->note);
    self::assertEquals(400, $code);

    // Set me right.
    $this->drupalLogin($this->adminUser);
    $edit = ['micropub_me' => 'https://indieweb.micropub.testdomain'];
    $this->drupalPostForm('admin/config/services/indieweb/micropub', $edit, 'Save configuration');

    // Send request, first with invalid token, then with valid, we should have
    // a note then.
    $this->drupalLogout();
    $this->sendMicropubRequest($this->note, 'invalid_token');
    self::assertEquals(400, $code);
    $count = \Drupal::database()->query('SELECT count(nid) FROM {node} WHERE type = :type', [':type' => 'page'])->fetchField();
    self::assertEquals(0, $count);
    // With valid access token now.
    // TODO test url from 201 header
    $code = $this->sendMicropubRequest($this->note);
    self::assertEquals(201, $code);
    $count = \Drupal::database()->query('SELECT count(nid) FROM {node} WHERE type = :type', [':type' => 'page'])->fetchField();
    self::assertEquals(1, $count);
    $nid = \Drupal::database()->query('SELECT nid FROM {node} WHERE type = :type', [':type' => 'page'])->fetchField();
    if ($nid) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
      self::assertEquals($this->note['content'], $node->get('body')->value);
    }
    else {
      // Explicit failure.
      $this->assertTrue($nid, 'No node found');
    }

  }

}

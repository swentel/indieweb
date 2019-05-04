<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests integration of RSVP.
 *
 * @group indieweb
 */
class RsvpTest extends IndiewebBrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'block',
    'node',
    'indieweb',
    'indieweb_webmention',
    'indieweb_test',
    'datetime_range',
    'field_ui',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $type = 'page';
    $edit = ['name' => $type, 'type' => $type];
    $this->drupalPostForm('admin/structure/types/add', $edit, 'Save and manage fields');

    $edit = ['new_storage_type' => 'daterange', 'label' => 'Date', 'field_name' => 'date'];
    $this->drupalPostForm('admin/structure/types/manage/page/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm(NULL, [], 'Save field settings');
    $this->drupalPostForm(NULL, [], 'Save settings');
    $this->drupalLogout();

    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['view published webmention entities']);
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), ['view published webmention entities']);
  }

  /**
   * Tests RSVP block and allow authenticated users to RSVP
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testRsvpBlockAndAuthenticatedUsers() {

    $this->drupalLogin($this->adminUser);
    $this->configureWebmention(['webmention_uid' => 0]);

    $this->placeBlock('indieweb_rsvp', ['region' => 'content', 'label' => 'RSVP title block', 'id' => 'rsvp', 'allow_user_rsvp' => 1, 'node_type' => 'page', 'node_daterange_field' => 'field_date']);
    $this->createPage();
    $this->createPage();

    $this->drupalGet('node/1');
    $this->assertSession()->responseNotContains('RSVP title block');

    $this->drupalLogin($this->adminUser);
    $this->drupalPostForm('node/1/edit', [
      'field_date[0][value][date]' => \Drupal::service('date.formatter')->format(time() + (86400 * 2), 'custom', 'Y-m-d'),
      'field_date[0][end_value][date]' => \Drupal::service('date.formatter')->format(time() + (86400 * 3), 'custom', 'Y-m-d'),
      'field_date[0][value][time]' => '00:00:00',
      'field_date[0][end_value][time]' => '00:00:00',
    ], 'Save');
    $this->drupalLogout();

    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $webmention = $this->getWebmentionNotificationPayload($node, 'valid_secret');
    $webmention['target'] = '/node/' . $node->id();
    $webmention['post']['rsvp'] = 'yes';
    $webmention['post']['wm-property'] = 'rsvp';
    $webmention['post']['author'] = ['name' => 'swentel'];
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['author'] = ['name' => 'Dries'];
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['author'] = ['name' => 'swentie'];
    $webmention['post']['rsvp'] = 'maybe';
    $this->sendWebmentionNotificationRequest($webmention);

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('RSVP title block');
    $this->assertSession()->responseContains('<div class="item-list"><h3>Yes</h3><ul><li>Dries</li><li>swentel</li></ul></div>');
    $this->assertSession()->responseContains('<div class="item-list"><h3>Maybe</h3><ul><li>swentie</li></ul></div>');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/1');
    $this->drupalPostForm(NULL, ['rsvp' => 'interested'], 'Update RSVP');
    $this->assertSession()->responseContains('Your RSVP has been added');
    $this->assertSession()->responseContains('<div class="item-list"><h3>Interested</h3><ul><li>' . $this->adminUser->getAccountName() . '</li></ul></div>');
    $this->drupalPostForm(NULL, ['rsvp' => 'no'], 'Update RSVP');
    $this->assertSession()->responseContains('Your RSVP has been updated');
    $this->assertSession()->responseContains('<div class="item-list"><h3>No</h3><ul><li>' . $this->adminUser->getAccountName() . '</li></ul></div>');

    $this->drupalLogout();
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('RSVP title block');
    $this->assertSession()->pageTextContains('Sign in to RSVP to this event');

    $this->drupalGet('node/2');
    $this->assertSession()->responseNotContains('RSVP title block');
  }

}

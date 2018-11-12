<?php

namespace Drupal\Tests\indieweb\Functional;

use Drupal\comment\Tests\CommentTestTrait;
use Drupal\Core\Url;
use Drupal\indieweb_test\WebmentionClient\WebmentionClientTest;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests integration of webmentions.
 *
 * @group indieweb
 */
class WebmentionTest extends IndiewebBrowserTestBase {

  use CommentTestTrait;

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
    'indieweb_webmention',
    'indieweb_microformat',
    'link',
    'field_ui',
    'comment',
  ];

  /**
   * The external webmention endpoint.
   *
   * @var string
   */
  protected $webmention_endpoint = '<link rel="webmention" href="https://webmention.io/example.com/webmention" />';

  /**
   * The external pingback endpoint.
   *
   * @var string
   */
  protected $pingback_endpoint = '<link rel="pingback" href="https://webmention.io/webmention?forward=http://example.com/webmention/notify" />';

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'page']);
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['view published webmention entities', 'access comments']);

    // This is needed because guzzle in the webmention client otherwise can't
    // find the content (don't ask me why though).
    drupal_flush_all_caches();
  }

  /**
   * Tests receiving webmention and pingbacks via notification endpoints.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testNotificationWebmentionAndPingback() {

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $this->drupalGet('admin/config/services/indieweb/webmention');
    $this->assertSession()->statusCodeEquals(403);

    $code = $this->sendWebmentionNotificationRequest();
    self::assertEquals(404, $code);

    $this->drupalLogin($this->adminUser);
    $this->configureWebmention();

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->webmention_endpoint);
    $this->assertSession()->responseContains($this->pingback_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains($this->webmention_endpoint);
    $this->assertSession()->responseContains($this->pingback_endpoint);

    // Do not expose.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'webmention_expose_link_tag' => FALSE,
      'pingback_expose_link_tag' => FALSE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');

    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    $this->drupalLogout();
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains($this->webmention_endpoint);
    $this->assertSession()->responseNotContains($this->pingback_endpoint);

    // Test that when you try to turn on notify and internal together, you
    // get a warning.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'webmention_internal' => TRUE,
      'webmention_notify' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');
    $this->assertSession()->pageTextContains('You can not enable the webmention notification and internal endpoint together');
    $edit = [
      'pingback_internal' => TRUE,
      'pingback_notify' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');
    $this->assertSession()->pageTextContains('You can not enable the pingback notification and internal endpoint together');
    $this->drupalLogout();

    $node = $this->drupalCreateNode(['type' => 'page']);
    $node_2 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is not on']]);
    $node_3 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is on! ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString()]]);

    // Send a webmention request, try invalid one first, then a valid.
    $webmention = $this->getWebmentionNotificationPayload($node);
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(400, $code);
    $webmention['secret'] = 'valid_secret';
    $code = $this->sendWebmentionNotificationRequest($webmention);
    self::assertEquals(202, $code);
    $webmentionEntity = $this->getLatestWebmention();
    self::assertEquals($this->adminUser->id(), $webmentionEntity->getOwnerId());
    self::assertNumberOfWebmentions(1);
    $this->sendWebmentionNotificationRequest($webmention);
    self::assertNumberOfWebmentions(2);

    // Detect identical
    $this->drupalLogin($this->adminUser);
    $edit = [
      'webmention_detect_identical' => TRUE,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/webmention', $edit, 'Save configuration');
    $this->drupalLogout();

    // Identical on source.
    $this->sendWebmentionNotificationRequest($webmention);
    self::assertNumberOfWebmentions(2);

    // Test pingback.
    $pingback = [
      'source' => 'blahahaasd;flkjsf',
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_2->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback);
    self::assertEquals(202, $code);
  }

  /**
   * Test internal webmention and pingback endpoints.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function testInternalWebmentionAndPingbackEndpoint() {

    $this->httpClient = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => $this->baseUrl]);

    $internal_endpoint = Url::fromRoute('indieweb.webmention.internal')->toString();
    $this->drupalGet($internal_endpoint);
    $this->assertSession()->statusCodeEquals(404);

    // Configure that field and all microformats on the settings page.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'h_entry' => 1,
      'u_photo' => 1,
      'e_content' => 1,
      'post_metadata' => 1,
      'p_bridgy_twitter_content' => 1,
      'p_summary' => 'field_summary',
    ];
    $this->drupalPostForm('admin/config/services/indieweb/microformats', $edit, 'Save configuration');
    $this->configureWebmention(['webmention_internal' => TRUE, 'pingback_internal' => TRUE, 'webmention_notify' => FALSE, 'pingback_notify' => FALSE]);
    $this->drupalLogout();

    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('webmention/receive');
    $this->assertSession()->responseContains('pingback/receive');

    $node_1 = $this->drupalCreateNode(['type' => 'page', 'title' => 'hello', ['body' => ['value' => 'page one']]]);
    $node_2 = $this->drupalCreateNode(['type' => 'page', 'title' => 'is me you are looking for', ['body' => ['value' => 'page two']]]);
    $node_1_path = $node_1->toUrl('canonical', ['absolute' => TRUE])->toString();
    $node_2_path = $node_2->toUrl('canonical', ['absolute' => TRUE])->toString();

    $response = $this->sendWebmentionInternalRequest("", "");
    self::assertEquals(400, $response->getStatusCode());

    $response = $this->sendWebmentionInternalRequest($node_1_path, "");
    self::assertEquals(400, $response->getStatusCode());

    $response = $this->sendWebmentionInternalRequest("", $node_1_path);
    self::assertEquals(400, $response->getStatusCode());

    $response = $this->sendWebmentionInternalRequest($node_1_path, $node_1_path);
    self::assertEquals(400, $response->getStatusCode());

    $response = $this->sendWebmentionInternalRequest($node_1_path, $node_2_path);
    self::assertEquals(202, $response->getStatusCode());

    $webmention = $this->getLatestWebmention();
    self::assertEquals($node_1_path, $webmention->get('source')->value);
    self::assertEquals($node_2_path, $webmention->get('target')->value);
    self::assertEquals('webmention', $webmention->get('type')->value);
    self::assertEquals('received', $webmention->get('property')->value);
    self::assertEquals(0, $webmention->get('status')->value);

    // Process the webmention via cron.
    $this->assertNumberOfWebmentions(1, 'webmention');
    $this->processWebmentions();
    $this->assertNumberOfWebmentions(1, 'webmention');
    $this->drupalLogin($this->adminUser);
    $this->configureWebmention(['webmention_internal_handler' => 'cron', 'webmention_internal' => TRUE, 'webmention_notify' => FALSE]);
    $this->drupalLogout();
    $this->processWebmentions();
    $this->assertNumberOfWebmentions(0, 'webmention');

    // Process the webmention via drush.
    $response = $this->sendWebmentionInternalRequest($node_1_path, $node_2_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->assertNumberOfWebmentions(1, 'webmention');
    $this->drupalLogin($this->adminUser);
    $this->configureWebmention(['webmention_internal_handler' => 'drush', 'webmention_internal' => TRUE]);
    $this->drupalLogout();
    $this->processWebmentions();
    $this->assertNumberOfWebmentions(0, 'webmention');
    \Drupal::database()->delete('webmention_received')->execute();
    $this->assertNumberOfWebmentions(0);

    // --------------------------------------------------------------------
    // Test internal pingback.
    // --------------------------------------------------------------------

    $this->drupalLogin($this->adminUser);
    $this->configureWebmention(['pingback_internal' => TRUE, 'pingback_notify' => FALSE]);
    $this->drupalLogout();

    $node_3 = $this->drupalCreateNode(['type' => 'page']);
    $node_4 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is not on']]);
    $node_5 = $this->drupalCreateNode(['type' => 'page', 'title' => 'wicked', 'body' => ['value' => 'url is on! ' . $node_3->toUrl('canonical', ['absolute' => TRUE])->toString()]]);

    $pingback = [
      'source' => 'blahahaasd;flkjsf',
      'target' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback, TRUE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_4->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback, TRUE);
    self::assertEquals(400, $code);
    $pingback = [
      'source' => $node_5->toUrl('canonical', ['absolute' => TRUE])->toString(),
      'target' => $node_3->toUrl('canonical', ['absolute' => TRUE])->toString(),
    ];
    $code = $this->sendPingbackRequest($pingback, TRUE);
    self::assertEquals(202, $code);
    $this->assertNumberOfWebmentions(1, 'pingback');
  }

  /**
   * Test various types of post types when a webmention comes in on the internal
   * endpoint. The source URL will always be internal, the target are examples
   * typically from either twitter or a website.
   *
   * This test behaves weird on the testbot, so let's keep it out for now.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \GuzzleHttp\Exception\GuzzleException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function _testInternalWebmentionPostTypes() {

    $this->httpClient = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => $this->baseUrl]);

    $this->addDefaultCommentField('node', 'page');

    // Configure.
    $this->drupalLogin($this->adminUser);
    $edit = ['existing_storage_name' => 'indieweb_webmention', 'existing_storage_label' => 'Webmention reference'];
    $this->drupalPostForm('admin/structure/comment/manage/comment/fields/add-field', $edit, 'Save and continue');
    $this->drupalPostForm('admin/structure/comment/manage/comment/display', ['fields[indieweb_webmention][type]' => 'entity_reference_entity_view'], 'Save');

    // Enable comment creation.
    $edit = [
      'comment_create_enable' => 1,
      'comment_create_webmention_reference_field' => 'indieweb_webmention',
      'comment_create_default_status' => 1,
    ];
    $this->drupalPostForm('admin/config/services/indieweb/comments', $edit, 'Save configuration');

    $this->configureWebmention(['webmention_internal' => TRUE, 'webmention_internal_handler' => 'drush', 'webmention_detect_identical' => TRUE, 'webmention_notify' => FALSE]);
    $this->drupalLogout();

    $node_1 = $this->drupalCreateNode(['type' => 'page', 'title' => 'hello', ['body' => ['value' => 'someone will like this']]]);
    $node_1_path = $node_1->toUrl('canonical', ['absolute' => TRUE])->toString();
    // Visit it once so it's cached.
    $this->drupalGet($node_1_path);

    // ------------------------------------------------------------------
    // Like.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_like_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'property' => 'like-of',
      'target' => '/node/1',
      'type' => 'entry',
      'author_name' => 'Pieter Frenssen',
      'author_photo' => 'https://pbs.twimg.com/profile_images/589400481572790273/UDzrzoyO.jpg',
      'author_url' => 'https://twitter.com/pfrenssen',
      'url' => 'https://twitter.com/swentel/status/1057282744458317825#favorited-by-190024882',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => '',
      'content_text' => '',
      'private' => 0,
      'rsvp' => '',
    ];
    $this->assertWebmention($expected);

    $source = Url::fromRoute('indieweb_test.webmention_like_site', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'like-of',
      'author_name' => 'Ryan Barrett',
      'author_photo' => 'https://secure.gravatar.com/avatar/947b5f3f323da0ef785b6f02d9c265d6?s=96&d=blank&r=g',
      'author_url' => 'https://snarfed.org/',
      'url' => 'https://snarfed.org/2018-03-19_10-realize-be',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => 'likes <a class="u-like u-like-of" href="http://realize.local/node/1">#10 | realize.be</a>',
      'content_text' => 'likes #10 | realize.be',
      'private' => 0,
      'rsvp' => '',
      'created' => 1521500743,
    ];
    $this->assertWebmention($expected);

    // ------------------------------------------------------------------
    // Repost.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_repost_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'repost-of',
      'author_name' => 'Gábor Hojtsy',
      'author_photo' => 'https://pbs.twimg.com/profile_images/1010975431305125889/sSiTFfJZ.jpg',
      'author_url' => 'http://hojtsy.hu',
      'url' => 'https://twitter.com/gaborhojtsy/status/1058794158540972032',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => '',
      'content_text' => 'The Drupal #indieweb module now contains a built-in IndieAuth server. Authorize applications by simply logging in with your Drupal account!',
      'private' => 0,
      'rsvp' => '',
      'created' => 1541271173,
    ];
    $this->assertWebmention($expected);

    // ------------------------------------------------------------------
    // Reply - this should also create a comment.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_reply_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'in-reply-to',
      'author_name' => 'Suchi Garg',
      'author_photo' => 'https://pbs.twimg.com/profile_images/941094497030586370/PJc-n99Q.jpg',
      'author_url' => 'http://bit.ly/meet-suchi-garg',
      'url' => 'https://twitter.com/gargsuchi/status/1057421577678073861',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => 'Totally agree. I always look it up. <a href="https://twitter.com/search?q=%23newbie">#newbie</a> <a href="https://twitter.com/search?q=%23php">#php</a> (I have been working with PHP since 2000)',
      'content_text' => 'Totally agree. I always look it up. #newbie #php (I have been working with PHP since 2000)',
      'private' => 0,
      'rsvp' => '',
      'created' => 1540943924,
    ];
    $this->assertWebmention($expected);
    $this->assertCommentCount(1);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains($expected['content_text']);

    $source = Url::fromRoute('indieweb_test.webmention_reply_fediverse', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'in-reply-to',
      'author_name' => 'https://mastodon.social/users/swentel',
      'author_photo' => '',
      'author_url' => 'https://mastodon.social/users/swentel',
      'url' => 'https://mastodon.social/@swentel/100915150921296401',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => '<p><span class="h-card"><a href="https://fed.brid.gy/r/http://realize.be" class="u-url">@<span>realize.be</span></a></span> Don\'t exaggerate now, ok ? ;)</p>',
      'content_text' => "@realize.be Don't exaggerate now, ok ? ;)",
      'private' => 0,
      'rsvp' => '',
      'created' => 1539843001,
    ];
    $this->assertWebmention($expected);
    $this->assertCommentCount(2);
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains($expected['content_text']);

    $source = Url::fromRoute('indieweb_test.webmention_reply_site', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'in-reply-to',
      'author_name' => 'Eddie Hinkle',
      'author_photo' => 'https://eddiehinkle.com/images/profile.jpg',
      'author_url' => 'https://eddiehinkle.com/',
      'url' => 'https://eddiehinkle.com',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => '',
      'content_text' => 'That is a fair concern. That said, the good news is it doesn’t FEEL like last time. I’m sure they are gonna do something to mess with our heads but so far while they have had multiple timelines they have been honest about the different timelines rather then making it a secret like season 1',
      'private' => 0,
      'rsvp' => '',
      'created' => 1524581499,
    ];
    $this->assertWebmention($expected);
    $this->assertCommentCount(3);
    $this->drupalGet('node/1');
    $this->assertSession()->pageTextContains($expected['content_text']);

    // ------------------------------------------------------------------
    // Bookmark.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_bookmark_site', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'bookmark-of',
      'author_name' => 'Chris Aldrich',
      'author_photo' => 'https://secure.gravatar.com/avatar/d5fb4e498fe609cc29b04e5b7ad688c4?s=49&d=https://boffosocko.com/wp-content/plugins/semantic-linkbacks/img/mm.jpg&r=pg',
      'author_url' => 'https://boffosocko.com/',
      'url' => 'https://boffosocko.com/2018/03/31/indieweb-module-for-drupal/',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'private' => 0,
      'rsvp' => '',
      'created' => 1522568610,
    ];
    $this->assertWebmention($expected);

    // ------------------------------------------------------------------
    // RSVP.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_rsvp_site', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'rsvp',
      'author_name' => 'Supercool Indiewebauthor',
      'author_photo' => 'http://mysite.example.org/icon.jpg',
      'author_url' => 'http://mysite.example.org',
      'url' => '',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'private' => 0,
      'rsvp' => 'yes',
    ];
    $this->assertWebmention($expected);

    // ------------------------------------------------------------------
    // Follow.
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_follow_fediverse', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'entry',
      'property' => 'follow-of',
      'author_name' => '',
      'author_photo' => '',
      'author_url' => 'https://mastodon.social/@swentel',
      'url' => '',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'private' => 0,
      'rsvp' => '',
    ];
    $this->assertWebmention($expected);

    // ------------------------------------------------------------------
    // Mention (this is the default).
    // ------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_mention_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, \Drupal::request()->getSchemeAndHttpHost() . '/');
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/',
      'type' => 'entry',
      'property' => 'mention-of',
      'author_name' => 'Ton Zijlstra',
      'author_photo' => 'https://pbs.twimg.com/profile_images/659398307081379840/pyAVq5hk.jpg',
      'author_url' => 'https://www.zylstra.org/blog',
      'url' => 'https://twitter.com/ton_zylstra/status/1053619970201018370',
      'uid' => $this->adminUser->id(),
      'status' => 1,
      'content_html' => '<a href="https://twitter.com/swentel">@swentel</a> Thank you for Indigenous! Allows me to easily post from my phone to my blog.',
      'content_text' => '@swentel Thank you for Indigenous! Allows me to easily post from my phone to my blog.',
      'private' => 0,
      'rsvp' => '',
      'created' => 1540037550,
    ];
    $this->assertWebmention($expected);

    // --------------------------------------------------------------------
    // Target not found in source - just use the previous one, but send
    // with a completely different target than used in the router.
    // --------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_mention_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'type' => 'no_link_found',
      'property' => 'no_link_found',
      'status' => 0,
    ];
    $this->assertWebmention($expected);

    // --------------------------------------------------------------------
    // Exception.
    // --------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_exception', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'type' => 'exception',
      'property' => 'exception',
      'status' => 0,
    ];
    $this->assertWebmention($expected);

    // --------------------------------------------------------------------
    // Detect identical webmention.
    // --------------------------------------------------------------------

    $source = Url::fromRoute('indieweb_test.webmention_like_twitter', [], ['absolute' => TRUE])->toString();
    $response = $this->sendWebmentionInternalRequest($source, $node_1_path);
    self::assertEquals(202, $response->getStatusCode());
    $this->processWebmentions();
    $expected = [
      'source' => $source,
      'target' => '/node/1',
      'type' => 'duplicate',
      'property' => 'duplicate',
      'status' => 0,
    ];
    $this->assertWebmention($expected);

  }

  /**
   * Tests sending webmentions.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testSendingWebmention() {

    // Test that the test indieweb client is used. If not, we bail out because
    // otherwise we bleed into the live site, and possibly start pinging
    // webmention.io.
    $class = \Drupal::service('indieweb.webmention.client');
    self::assertTrue($class instanceof WebmentionClientTest);

    $test_endpoint = Url::fromRoute('indieweb_test.webmention_endpoint', [], ['absolute' => TRUE])->toString();
    $this->drupalLogin($this->adminUser);
    $this->configureWebmention(['webmention_endpoint' => $test_endpoint]);

    $edit = ['send_custom_url' => TRUE];
    $this->drupalPostForm('admin/config/services/indieweb/send', $edit, 'Save configuration');

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'Best article ever']);
    $node_1_url = $node->toUrl('canonical', ['absolute' => TRUE])->toString();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains($test_endpoint);

    $this->createPage($node_1_url, FALSE, TRUE);
    $this->assertWebmentionQueueItems([$node_1_url], 2);

    // Handle queue, should not be send yet.
    $this->runWebmentionQueue();
    $this->assertWebmentionQueueItems([$node_1_url]);

    // Send by cron.
    $edit = ['send_webmention_handler' => 'cron'];
    $this->drupalPostForm('admin/config/services/indieweb/send', $edit, 'Save configuration');
    $this->runWebmentionQueue();
    $this->assertWebmentionQueueItems();

    // Send by drush.
    $this->createPage($node_1_url, FALSE, TRUE);
    $this->assertWebmentionQueueItems([$node_1_url]);
    $edit = ['send_webmention_handler' => 'drush'];
    $this->drupalPostForm('admin/config/services/indieweb/send', $edit, 'Save configuration');
    $this->runWebmentionQueue();
    $this->assertWebmentionQueueItems();

    // Put node 1 in syndication targets, so it can be a publish URL, so we can
    // test syndications.
    $edit = ['syndication_targets' => 'Twitter (bridgy)|' . $node_1_url];
    $this->drupalPostForm('admin/config/services/indieweb/send', $edit, 'Save configuration');

    $this->createPage($node_1_url, TRUE);
    $this->assertWebmentionQueueItems([$node_1_url]);
    $this->runWebmentionQueue();
    $this->assertWebmentionQueueItems();
    $this->assertSyndication(4, $node_1_url);

    // Remove syndication.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(4);
    $node->delete();
    $this->assertNoSyndication(4);

  }

  /**
   * Tests the Webmention Block.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function testWebmentionInteractionsBlock() {

    $this->drupalLogin($this->adminUser);
    $this->configureWebmention();

    $this->placeBlock('indieweb_webmention', ['region' => 'content', 'label' => 'Interactions', 'id' => 'webmention']);
    $this->createPage();
    $this->createPage();
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
    $node_2 = \Drupal::entityTypeManager()->getStorage('node')->load(2);
    $this->drupalLogout();

    $this->drupalGet('node/1');
    $this->assertSession()->responseNotContains('Interactions');

    $webmention = $this->getWebmentionNotificationPayload($node, 'valid_secret');
    $webmention['post']['author'] = ['name' => 'swentel'];
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['author'] = ['name' => 'Dries'];
    $webmention['post']['wm-property'] = 'mention-of';
    $this->sendWebmentionNotificationRequest($webmention);
    $webmention['post']['wm-property'] = 'repost-of';
    $webmention['post']['author'] = ['name' => 'swentie'];
    $this->sendWebmentionNotificationRequest($webmention);

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseNotContains('Dries');

    $this->drupalGet('node/2');
    $this->assertSession()->responseNotContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseNotContains('Dries');

    $edit = ['settings[webmentions][show_reposts]' => TRUE];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');
    $this->assertSession()->responseNotContains('Dries');

    $webmention['target'] = '/node/' . $node_2->id();
    $webmention['post']['wm-property'] = 'repost-of';
    $webmention['post']['author'] = ['name' => 'Dries'];
    $this->sendWebmentionNotificationRequest($webmention);

    $this->drupalGet('node/2');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseNotContains('swentie');
    $this->assertSession()->responseContains('Reposted by Dries');

    $edit = ['settings[webmentions][number_of_posts]' => 1];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseNotContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');

    $edit = ['settings[webmentions][number_of_posts]' => 0];
    $this->changeBlockConfiguration('webmention', $edit);
    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Interactions');
    $this->assertSession()->responseContains('Liked by swentel');
    $this->assertSession()->responseContains('Reposted by swentie');
  }

  /**
   * Test webmention notify block form.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testWebmentionNotifyBlock() {

    $this->drupalLogin($this->adminUser);
    $this->configureWebmention();
    $this->placeBlock('system_messages_block', ['region' => 'content', 'label' => 'Messages', 'id' => 'messages']);
    $this->placeBlock('indieweb_webmention_notify', ['region' => 'content', 'label' => 'Notify', 'id' => 'webmention_notify']);
    $this->createPage();
    $this->drupalLogout();

    $this->drupalGet('node/1');
    $this->assertSession()->responseContains('Have you written a response to this? Let me know the URL');

    $edit = ['source' => 'https://example.com/webmention-source-url'];
    $this->drupalPostForm('node/1', $edit, 'Send webmention');
    $this->assertSession()->responseContains('Thanks for letting me know!');
    $query = 'SELECT * FROM {queue} WHERE name = :name';
    $records = \Drupal::database()->query($query, [':name' => INDIEWEB_WEBMENTION_QUEUE]);
    foreach ($records as $record) {
      $data = unserialize($record->data);
      $this->assertTrue($data['source'] == $edit['source']);
      $this->assertTrue(strpos($data['target'], 'node/1') !== FALSE);
    }
  }

  /**
   * Get latest webmention.
   *
   * @return \Drupal\indieweb_webmention\Entity\WebmentionInterface|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getLatestWebmention() {
    $webmention_id = \Drupal::database()->query("SELECT id FROM {webmention_received} ORDER by id DESC limit 1")->fetchField();
    /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention */
    $webmention = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->load($webmention_id);
    return $webmention;
  }

  /**
   * Assert the number of webmentions.
   *
   * @param $total
   * @param $type
   */
  protected function assertNumberOfWebmentions($total, $type = '') {
    $params = [];
    $query = 'SELECT count(id) FROM {webmention_received}';
    if ($type) {
      $params[':type'] = $type;
      $query .= ' WHERE type = :type';
    }
    $total_query = \Drupal::database()->query($query, $params)->fetchField();
    self::assertEquals($total, $total_query);
  }

  /**
   * Verify the webmention.
   *
   * @param $expected
   *   An array of expected values
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function assertWebmention($expected) {
    $webmention = $this->getLatestWebmention();
    foreach ($expected as $field => $expected_value) {
      $fuzzy = FALSE;
      if ($field == 'uid') {
        $actual = $webmention->get($field)->target_id;
      }
      // Testbot runs in a subdirectory and we don't support this (yet).
      elseif ($field == 'target') {
        $fuzzy = TRUE;
        $actual = $webmention->get($field)->value;
      }
      else {
        $actual = $webmention->get($field)->value;
      }

      if ($fuzzy) {
        $host = \Drupal::request()->getSchemeAndHttpHost();
        self::assertTrue(strpos($actual, $host) === FALSE);
        self::assertTrue(strpos($actual, $expected_value) !== FALSE);
      }
      else {
        self::assertEquals($expected_value, $actual, "$field is equal");
      }
    }
  }

  /**
   * Process webmentions, use both cron and drush.
   */
  protected function processWebmentions() {
    if (\Drupal::config('indieweb_webmention.settings')->get('webmention_internal_handler') == 'drush') {
      \Drupal::service('indieweb.webmention.client')->processWebmentions();
    }
    indieweb_webmention_cron();
  }

}

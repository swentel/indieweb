<?php

namespace Drupal\Tests\indieweb\Functional;

/**
 * Tests integration of Media Cache.
 *
 * @group indieweb
 */
class MediaCacheTest extends IndiewebBrowserTestBase {

  /**
   * Modules to enable for this test.
   *
   * @var string[]
   */
  public static $modules = [
    'indieweb',
    'indieweb_test',
    'indieweb_cache',
  ];

  /**
   * Tests media cache client.
   *
   * We can't use imagecache external yet as its config schema is wrong. The
   * indieweb_test module overrides the media cache client so we can simple
   * test the extra function already in the indieweb_cache client class.
   */
  public function testMediaCache() {

    $filename = file_create_url(drupal_get_path('module', 'indieweb_test') . '/images/indieweb-building-blocks.png');
    $filename2 = file_create_url(drupal_get_path('module', 'indieweb_test') . '/images/indieauth-monocle.png');

    /** @var \Drupal\indieweb\MediaCacheClient\MediaCacheClientInterface $client */
    $client = \Drupal::service('indieweb.media_cache.client');

    $string = 'This is content with an <img alt="something" src="' . $filename . '" /> in it which will be rewritten. 
      This one will be rewritten too: <img title="test" src="' . $filename2 . '" width="300">';

    $string = $client->replaceImagesInString($string);

    self::assertTrue(strpos($string, 'images/indieweb-building-blocks.png') === FALSE);
    self::assertTrue(strpos($string, 'images/indieweb-building-blocks.jpg') !== FALSE);
    self::assertTrue(strpos($string, 'images/indieauth-monocle.png') === FALSE);
    self::assertTrue(strpos($string, 'images/indieauth-monocle.jpg') !== FALSE);
  }

}
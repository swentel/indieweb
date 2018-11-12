<?php

namespace Drupal\indieweb_cache\MediaCacheClient;

use Drupal\image\Entity\ImageStyle;
use Drupal\indieweb\MediaCacheClient\MediaCacheClientInterface;

class MediaCacheClient implements MediaCacheClientInterface {

  /**
   * {@inheritdoc}
   */
  public function imageCacheExternalEnabled() {
    $cache = \Drupal::config('indieweb_cache.settings');
    return $cache->get('enable') && $cache->get('use_imagecache_external');
  }

  /**
   * {@inheritdoc}
   */
  public function applyImageCache($filename, $type = 'avatar') {
    // Return early if the filename is empty.
    if (empty($filename)) {
      return $filename;
    }

    $cache = \Drupal::config('indieweb_cache.settings');
    if ($cache->get('enable')) {

      // Ignore webmention io.
      if ($cache->get('ignore_webmention_io') && strpos($filename, 'webmention.io/avatar') !== FALSE) {
        return $filename;
      }

      // Imagecache external module.
      if ($cache->get('use_imagecache_external') && \Drupal::moduleHandler()->moduleExists('imagecache_external')) {
        $image_style = $cache->get('image_style_' . $type);
        $filename = imagecache_external_generate_path($filename);
        $style = ImageStyle::load($image_style);
        if ($style) {
          if ($style->supportsUri($filename)) {
            $filename = $style->buildUri($filename);
          }
        }
        $filename = file_create_url($filename);
      }
    }

    return $filename;
  }

}
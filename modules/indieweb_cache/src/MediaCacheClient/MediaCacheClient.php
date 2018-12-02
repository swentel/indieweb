<?php

namespace Drupal\indieweb_cache\MediaCacheClient;

use Drupal\image\Entity\ImageStyle;
use Drupal\indieweb\MediaCacheClient\MediaCacheClientDefault;

class MediaCacheClient extends MediaCacheClientDefault {

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
  public function replaceImagesInString($content, $type = 'photo') {
    $images = [];
    $extracted_images = $this->extractImages($content);
    if (!empty($extracted_images)) {
      foreach ($extracted_images as $image) {
        $images[$image] = $this->applyImageCache($image, $type);
      }
      $content = str_replace(array_keys($images), array_values($images), $content);
    }
    return $content;
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
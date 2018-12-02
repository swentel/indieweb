<?php

namespace Drupal\indieweb\MediaCacheClient;

interface MediaCacheClientInterface {

  /**
   * Returns whether the imagecache external is enabled.
   *
   * @return bool
   */
  public function imageCacheExternalEnabled();

  /**
   * Replaces images in strings.
   *
   * @param $content
   * @param $type
   *
   * @return string $content
   */
  public function replaceImagesInString($content, $type = 'photo');

   /**
   * Applies imagecache to a file.
   *
   * @param $filename
   * @param string $type
   *
   * @return string $filename
   */
  public function applyImageCache($filename, $type = 'avatar');

}
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
   * Applies image cache to an image.
   *
   * @param $filename
    *   The filename
   * @param string $type
    *   The type, e.g. avatar or photo
   * @param string $context
    *   The context where the file is used, e.g. webmention_image,
    *   microsub image.
   *
   * @return string $filename
   */
  public function applyImageCache($filename, $type = 'avatar', $context = '');

}
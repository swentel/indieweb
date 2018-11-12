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
   * Applies imagecache to a file.
   *
   * @param $filename
   * @param string $type
   *
   * @return string $filename
   */
  public function applyImageCache($filename, $type = 'avatar');

}
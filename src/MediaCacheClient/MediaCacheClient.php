<?php

namespace Drupal\indieweb\MediaCacheClient;

class MediaCacheClient implements MediaCacheClientInterface {

  /**
   * {@inheritdoc}
   */
  public function imageCacheExternalEnabled() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function applyImageCache($filename, $type = 'avatar') {
    return $filename;
  }

}
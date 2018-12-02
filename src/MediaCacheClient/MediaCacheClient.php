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
  public function replaceImagesInString($content, $type = 'photo') {
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function applyImageCache($filename, $type = 'avatar') {
    return $filename;
  }

}
<?php

namespace Drupal\indieweb\MediaCacheClient;

use DOMDocument;

class MediaCacheClientDefault implements MediaCacheClientInterface {

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

  /**
   * Extracts images from a string.
   *
   * @param $html
   *
   * @return array $images
   */
  protected function extractImages($html) {
    $images = [];

    $dom = new domDocument;
    libxml_use_internal_errors(TRUE);
    $dom->loadHTML($html);
    $dom->preserveWhiteSpace = FALSE;
    $image_list = $dom->getElementsByTagName('img');

    foreach ($image_list as $image) {
     $images[] = $image->getAttribute('src');
    }

    return $images;
  }

}
<?php

namespace Drupal\indieweb_cache\MediaCacheClient;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileSystemInterface;
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
  public function applyImageCache($filename, $type = 'avatar', $context = '') {
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

        // Store images forever.
        if (
            ($context == 'webmention_avatar' && $cache->get('protect_webmention_avatar_from_flush')) ||
            ($context == 'webmention_image' && $cache->get('protect_webmention_image_from_flush')) ||
            ($context == 'contact_avatar' && $cache->get('protect_contact_image_from_flush')) ||
            ($context == 'post_context_image' && $cache->get('protect_webmention_image_from_flush'))
          ) {
          $media_cache_directory = 'public://indieweb-media-cache';
          $filename = $this->generatePath($filename, $media_cache_directory);
        }
        else {
           $filename = $this->generatePath($filename);
        }

        // Apply image style.
        $image_style = $cache->get('image_style_' . $type);
        $style = ImageStyle::load($image_style);
        if ($style) {
          if ($style->supportsUri($filename)) {
            $filename = $style->buildUrl($filename);
          }
          else {
            $filename = file_create_url($filename);
          }
        }
        else {
          $filename = file_create_url($filename);
        }
      }
    }

    return $filename;
  }

  /**
   * This is a copy of imagecache_external_generate_path(), but with an optional
   * destination directory.
   *
   * @param $url
   * @param string $directory
   *
   * @return bool|string
   */
  protected function generatePath($url, $directory = '') {
    // Get configuration.
    $config = imagecache_external_config();

    // Create the extenal images directory and ensure it's writable.
    $hash = md5($url);
    $filename = $hash;

    // Get the FileSystem service.
    $file_system = \Drupal::service('file_system');

    // Check if this is a non-standard file stream and adjust accordingly.
    $scheme = $file_system->uriScheme($url);
    if ($scheme != 'http' && $scheme != 'https') {
      // Obtain the "real" URL to this file.
      $url = $file_system->realpath($url);
    }

    // Parse the url to get the path components.
    $url_parameters = UrlHelper::parse($url);

    // Add the extension for real images.
    if ($extension = strtolower(pathinfo($url_parameters['path'], PATHINFO_EXTENSION))) {
      if (in_array($extension, ['jpg', 'png', 'gif', 'jpeg'])) {
        $filename .= '.' . $extension;
      }
    }
    else {
      // Use jpg as default extension.
      $filename .= $config->get('imagecache_default_extension');
    }

    if (empty($directory)) {
      $default_scheme = \Drupal::config('system.file')->get('default_scheme');
      $directory = $default_scheme . '://' . $config->get('imagecache_directory');
    }

    if (\Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
      $needs_refresh = FALSE;
      $filepath = $directory . '/' . $filename;
      // Allow modules to add custom logic to check if it needs to be re-fetched.
      \Drupal::moduleHandler()->alter('imagecache_external_needs_refresh', $needs_refresh, $filepath);
      if ($needs_refresh === FALSE) {
        return $filepath;
      }
      elseif ($filepath = imagecache_external_fetch($url, $directory . '/' . $filename)) {
        return $filepath;
      }
    }

    // We couldn't get the file.
    return FALSE;
  }

}

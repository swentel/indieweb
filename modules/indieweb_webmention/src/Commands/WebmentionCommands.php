<?php

namespace Drupal\indieweb_webmention\Commands;

use Drush\Commands\DrushCommands;

/**
 * IndieWeb webmention Drush commands file.
 */
class WebmentionCommands extends DrushCommands {

  /**
   * Send webmentions
   *
   * @command indieweb:send-webmentions
   * @aliases isw,indieweb-send-webmentions
   */
  public function sendWebmentions() {
    if (\Drupal::config('indieweb_webmention.settings')->get('send_webmention_handler') == 'drush') {
      \Drupal::service('indieweb.webmention.client')->handleQueue();
    }
  }

  /**
   * Process webmentions
   *
   * @command indieweb:process-webmentions
   * @aliases ipw,indieweb-process-webmentions
   */
  public function processWebmentions() {
    if (\Drupal::config('indieweb_webmention.settings')->get('webmention_internal_handler') == 'drush') {
      \Drupal::service('indieweb.webmention.client')->processWebmentions();
    }
  }

  /**
   * Replace avatar.
   *
   * @param $search
   *   The avatar url to search
   * @param $replace
   *   The avatar url to replace
   * @param $field_name
   *   The field to search. Defaults to author_photo.
   *
   * @command indieweb:replace-avatar
   * @aliases ira,indieweb-replace-avatar
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function replaceAvatar($search, $replace, $field_name = 'author_photo') {
    $ids = \Drupal::entityQuery('indieweb_webmention')
      ->condition($field_name, $search)
      ->execute();

    if (!empty($ids)) {
      /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface[] $webmentions */
      $webmentions = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->loadMultiple($ids);
      foreach ($webmentions as $webmention) {
        $webmention->set('author_photo', $replace);
        $webmention->save();
      }
    }

    drush_print('Replaced avatars: ' . count($ids));
  }

}

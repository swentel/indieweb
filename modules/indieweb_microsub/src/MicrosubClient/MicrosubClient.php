<?php

namespace Drupal\indieweb_microsub\MicrosubClient;

use Drupal\Core\Url;
use Drupal\indieweb_microsub\Entity\MicrosubItem;
use Drupal\indieweb_webmention\Entity\WebmentionInterface;
use p3k\XRay;

class MicrosubClient implements MicrosubClientInterface {

  /**
   * {@inheritdoc}
   */
  public function fetchItems() {
    $xray = new XRay();

    $post_context_handler = \Drupal::config('indieweb_context.settings')->get('handler');
    $post_context_enabled = !empty($post_context_handler) && $post_context_handler != 'disabled';

    // Cleanup old items.
    $cleanup_old_items = \Drupal::config('indieweb_microsub.settings')->get('microsub_internal_cleanup_items');
    if ($cleanup_old_items) {
      \Drupal::database()
        ->delete('microsub_item')
        ->condition('created', \Drupal::time()->getRequestTime() - $cleanup_old_items, '<')
        ->execute();
    }

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface[] $sources */
    $sources = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_source')->getSourcesToRefresh();
    foreach ($sources as $source) {

      // Continue if the channel is disabled.
      if (!$source->getChannel()->getStatus()) {
        continue;
      }

      $url = $source->label();
      $tries = $source->getTries();
      $empty = $source->getItemCount() == 0;
      $source_id = $source->id();
      $channel_id = $source->getChannelId();
      $disable_image_cache = $source->disableImageCache();
      $tries++;

      // Allow internal URL's, at the moment only for testing.
      if (strpos($url, 'internal:/') !== FALSE) {
        $url = Url::fromUri($url, ['absolute' => TRUE])->toString();
      }

      try {

        // Get content.
        $response = \Drupal::httpClient()->get($url);
        $body = $response->getBody()->getContents();

        $hash = md5($body);
        if ($source->getHash() != $hash) {

          // Context.
          $context = $post_context_enabled ? $source->getPostContext() : [];

          // Parse the body.
          $parsed = $xray->parse($url, $body, ['expect' => 'feed']);
          if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
            $items = array_reverse($parsed['data']['items']);
            foreach ($items as $i => $item) {
              $this->saveItem($item, $tries, $source_id, $channel_id, $empty, $context, $disable_image_cache);
            }
          }

          // Set new hash.
          $source->setHash($hash);
        }

        $source->setNextFetch();
        $source->setTries($tries);
        $source->save();

      }
      catch (\Exception $e) {
        \Drupal::logger('indieweb_microsub')->notice('Error fetching new items for @url : @message', ['@url' => $url, '@message' => $e->getMessage()]);
      }

    }
  }

  /**
   * Saves an item.
   *
   * @param $item
   * @param int $tries
   * @param int $source_id
   * @param int $channel_id
   * @param bool $empty
   * @param array $context
   * @param $disable_image_cache
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveItem($item, &$tries = 0, $source_id = 0, $channel_id = 0, $empty = FALSE, $context = [], $disable_image_cache = FALSE) {

    // Prefer uid, then url, then hash the content
    if (isset($item['uid'])) {
      $guid = '@'.$item['uid'];
    }
    elseif (isset($item['url'])) {
      $guid = $item['url'];
    }
    else {
      $guid = '#'.md5(json_encode($item));
    }

    // Check if this entry exists.
    $exists = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->itemExists($source_id, $guid);
    if ($exists) {
      return;
    }

    // Reset tries.
    $tries = 0;

    // Cleanup data.
    $item = $this->cleanupAndCache($item, $disable_image_cache);

    // Save the entry.
    $values = [
      'langcode' => 'en',
      'source_id' => $source_id,
      'channel_id' => $channel_id,
      'data' => json_encode($item),
      'context' => '',
      'guid' => $guid,
      'is_read' => $empty ? 1 : 0,
      'post_type' => isset($item['post-type']) ? $item['post-type'] : 'unknown',
    ];

    if (isset($item['published'])) {
      $values['timestamp'] = strtotime($item['published']);
    }
    else {
      $values['timestamp'] = \Drupal::time()->getRequestTime();
    }

    $values['created'] = $empty ? $values['timestamp'] : \Drupal::time()->getRequestTime();

    $microsub_item = MicrosubItem::create($values);
    $microsub_item->save();

    // Save post context in queue.
    if (!empty($context) && in_array($values['post_type'], $context)) {
      foreach ($context as $post_type) {
        $key = '';
        switch ($post_type) {
          case 'reply':
            $key = 'in-reply-to';
            break;
          case 'like':
            $key = 'like-of';
            break;
          case 'bookmark':
            $key = 'bookmark-of';
            break;
          case 'repost':
            $key = 'repost-of';
            break;
        }

        if ($key && !empty($item[$key][0])) {
          \Drupal::service('indieweb.post_context.client')->createQueueItem($item[$key][0], $microsub_item->id(), 'microsub_item');
        }
      }
    }
  }

  /**
   * Cleans and caches data.
   *
   * @param $item
   * @param $disable_image_cache
   *
   * @return mixed
   */
  protected function cleanupAndCache($item, $disable_image_cache) {

    // Author names sometimes have newlines in the name, remove them.
    if (!empty($item['author']['name'])) {
      $item['author']['name'] = preg_replace('/\s+/', ' ', trim($item['author']['name']));
    }

    // Apply caching to author and photo.
    if (!$disable_image_cache && !empty($item['author']['photo'])) {
      \Drupal::service('indieweb.media_cache.client')->applyImageCache($item['author']['photo']);
    }

    if (!$disable_image_cache && !empty($item['photo']) && is_array($item['photo'])) {
      foreach ($item['photo'] as $i => $p) {
        \Drupal::service('indieweb.media_cache.client')->applyImageCache($p, 'photo');
      }
    }

    // TODO apply to content

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function sendNotification(WebmentionInterface $webmention, $parsed = NULL) {
    $microsub = \Drupal::config('indieweb_microsub.settings');

    // Send to aperture.
    if (!$microsub->get('microsub_internal') && $microsub->get('aperture_enable_micropub') && !empty($microsub->get('aperture_api_key'))) {
      if ($post = $this->getPost($webmention)) {
        /** @var \Drupal\indieweb_microsub\ApertureClient\ApertureClientInterface $client */
        $client = \Drupal::service('indieweb.aperture.client');
        $client->sendPost($microsub->get('aperture_api_key'), $webmention);
      }
    }

    // Send to internal notifications channel.
    if ($microsub->get('microsub_internal')) {
      $xray = new XRay();
      $url = $webmention->get('source')->value;
      $target = \Drupal::request()->getSchemeAndHttpHost() . $webmention->get('target')->value;

      try {

        // Get content if parsed is not set.
        if (!isset($parsed)) {
          $response = \Drupal::httpClient()->get($url);
          $body = $response->getBody()->getContents();
          $parsed = $xray->parse($url, $body);
        }

        if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'entry') {
          $item = $parsed['data'];

          foreach (['like-of', 'repost-of', 'bookmark-of', 'in-reply-to', 'mention-of'] as $item_url) {
            if (isset($item[$item_url]) && !empty($item[$item_url][0])) {
              $item[$item_url][0] = $target;

              // Make sure the array is unique.
              $item[$item_url] = array_unique($item[$item_url]);
            }
          }

          // Set url to canonical webmention for in-reply-to. This makes sure
          // that you can  reply to it from a reader as the micropub endpoint
          // will get the right node or comment.
          if (isset($item['in-reply-to']) && !empty($item['in-reply-to'][0])) {
            $item['url'] = $webmention->toUrl('canonical', ['absolute' => TRUE])->toString();
          }

          // Remove media, it isn't really necessary to see those back in the
          // notification channel.
          foreach (['photo', 'video', 'audio'] as $key) {
            if (isset($item[$key])) {
              unset($item[$key]);
            }
          }

          $this->saveItem($item);
        }

      }
      catch (\Exception $e) {
        \Drupal::logger('indieweb_microsub')->notice('Error saving notification for @url : @message', ['@url' => $url, '@message' => $e->getMessage()]);
      }
    }

  }

  /**
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   *
   * @return \stdClass
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getPost(WebmentionInterface $webmention) {
    $properties = [];
    $base_url = \Drupal::request()->getSchemeAndHttpHost();
    $type = $webmention->get('property')->value;

    switch ($type) {

      case 'like-of':
        $properties['like-of'] = [$base_url . $webmention->get('target')->value];
        break;
      case 'repost-of':
        $properties['repost-of'] = [$base_url . $webmention->get('target')->value];
        break;
      case 'bookmark-of':
        $properties['bookmark-of'] = [$base_url . $webmention->get('target')->value];
        $properties['content'][0]['html'] = 'Bookmark available on <a href="' . $webmention->get('source')->value . '">' . $webmention->get('source')->value . '</a>';
        break;
      case 'in-reply-to':
        $properties['in-reply-to'] = [$base_url . $webmention->get('target')->value];
        $content = $webmention->get('content_text')->value;

        // Add comment url if found.
        if (($comment_config = \Drupal::config('indieweb_webmention.comment')) && $comment_config->get('comment_create_enable')) {
          $comment_comment_webmention_field_name = $comment_config->get('comment_create_webmention_reference_field');
          $table_name = 'comment__' . $comment_comment_webmention_field_name;
          if (\Drupal::database()->schema()->tableExists($table_name)) {
            $cid = \Drupal::database()
              ->select($table_name, 'a')
              ->fields('a', ['entity_id'])
              ->condition($comment_comment_webmention_field_name . '_target_id', $webmention->id())
              ->execute()
              ->fetchField();

            if ($cid) {
              $content .= "\n\n" . t('Comment available at @comment_url', ['@comment_url' => Url::fromRoute('indieweb.comment.canonical', ['comment' => $cid], ['absolute' => TRUE])->toString()]);
            }
          }
        }

        $properties['content'] = [$content];
        break;
      case 'mention-of':
        $properties['name'] = ['You were mentioned'];
        if (!empty($webmention->get('content_text')->value)) {
          $properties['content'] = [$webmention->get('content_text')->value];
        }
        break;
    }

    if (!empty($properties)) {

      $properties['published'] = [\Drupal::service('date.formatter')->format(\Drupal::time()->getRequestTime(), 'html_datetime')];
      $properties['url'] = [$webmention->toUrl('canonical', ['absolute' => TRUE])->toString()];
      $this->getAuthor($properties, $webmention);

      $post = new \stdClass();
      $post->type = ['h-entry'];
      $post->properties = $properties;
      return $post;
    }
  }

  /**
   * Adds the author the post.
   *
   * @param $post
   *   The post to create.
   * @param \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention
   *   The incoming webmention.
   */
  protected function getAuthor(&$post, $webmention) {
    $author = [];

    if (!empty($webmention->get('author_name')->value)) {
      $author['type'] = ['h-card'];
      $properties = [];
      $properties['name'] = [$webmention->get('author_name')->value];
      if ($author_url = $webmention->get('author_url')->value) {
        $properties['url'] = [$author_url];
      }
      if ($author_photo = $webmention->get('author_photo')->value) {
        $properties['photo'] = [$author_photo];
      }
      $author['properties'] = (object) $properties;
    }

    if (!empty($author)) {
      $post['author'] = [(object) $author];
    }
  }

}
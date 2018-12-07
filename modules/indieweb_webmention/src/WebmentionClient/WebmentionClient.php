<?php

namespace Drupal\indieweb_webmention\WebmentionClient;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\indieweb_webmention\Entity\WebmentionInterface;
use Exception;
use IndieWeb\MentionClient;
use p3k\XRay;

class WebmentionClient implements WebmentionClientInterface {

  /**
   * {@inheritdoc}
   */
  public function createQueueItem($source, $target, $entity_id = '', $entity_type_id = '') {
    $data = [
      'source' => $source,
      'target' => $target,
      'entity_id' => $entity_id,
      'entity_type_id' => $entity_type_id,
    ];

    // Bail out when target is one of the silos, but not actually the webmention
    // endpoint. This can happen with reply urls to twitter for example.
    if ($this->isSiloURL($target)) {
      return;
    }

    try {
      \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->createItem($data);
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_queue')->notice('Error creating queue item: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleQueue() {
    $end = time() + 15;
    $syndication_targets = indieweb_get_syndication_targets();
    while (time() < $end && ($item = \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->claimItem())) {
      $data = $item->data;
      if (!empty($data['source']) && !empty($data['target'])) {

        try {

          $sourceURL = $data['source'];
          $targetURL = $data['target'];

          // Send with IndieWeb client.
          $response = $this->sendWebmention($sourceURL, $targetURL);

          // Store the syndication when the targetUrl is in the syndication
          // targets.
          if (isset($syndication_targets[$targetURL])) {
            if (!empty($response) && $response['code'] == 201 && !empty($response['headers']['Location'])) {

              if (!empty($data['entity_id']) && !empty($data['entity_type_id'])) {
                $values = [
                  'entity_id' => $data['entity_id'],
                  'entity_type_id' => $data['entity_type_id'],
                  'url' => $response['headers']['Location'],
                ];

                $syndication = \Drupal::entityTypeManager()->getStorage('indieweb_syndication')->create($values);
                $syndication->save();

                Cache::invalidateTags([$data['entity_type_id'] . ':' . $data['entity_id']]);
              }
            }
          }

          // Log the response if configured.
          if (\Drupal::config('indieweb_webmention.settings')->get('send_log_response')) {
            \Drupal::logger('indieweb_send_response')->notice('response for @source to @target: @response', ['@response' => print_r($response, 1), '@source' => $sourceURL, '@target' => $targetURL]);
          }

        }
        catch (Exception $e) {
          \Drupal::logger('indieweb_send')->notice('Error sending webmention for @source to @target: @message', ['@message' => $e->getMessage(), '@source' => $sourceURL, '@target' => $targetURL]);
        }
      }

      // Store in send table.
      $values = [
        'source' => $data['source'],
        'target' => $data['target'],
        'entity_id' => !empty($data['entity_id']) ? $data['entity_id'] : 0,
        'entity_type_id' => !empty($data['entity_type_id']) ? $data['entity_type_id'] : '',
        'created' => \Drupal::time()->getCurrentTime(),
      ];

      try {
        $send = \Drupal::entityTypeManager()->getStorage('indieweb_webmention_send')->create($values);
        $send->save();
      }
      catch (\Exception $e) {
        \Drupal::logger('indieweb_send')->notice('Error saving send webmention record: @message', ['@message' => $e->getMessage()]);
      }

      // Remove the item - always.
      \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->deleteItem($item);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendWebmention($sourceURL, $targetURL) {
    $client = new MentionClient();
    return $client->sendWebmention($sourceURL, $targetURL);
  }

  /**
   * {@inheritdoc}
   */
  public function processWebmentions() {
    $xray = new XRay();

    $values = [
      'status' => 0,
      'type' => 'webmention',
    ];

    /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface[] $webmentions */
    $webmentions = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->loadByProperties($values);
    foreach ($webmentions as $webmention) {
      $error = FALSE;
      $parsed = [];

      try {

        // Get the source body.
        $source = $webmention->getSource();
        $target = $webmention->getTarget();
        $response = \Drupal::httpClient()->get($source);
        $body = $response->getBody()->getContents();

        // ------------------------------------------------------------------
        // Parse the body. Make sure the target is found.
        // ------------------------------------------------------------------

        $parsed = $xray->parse($source, $body, ['target' => $target]);

        // ------------------------------------------------------------------
        // Target url was found on source and doc is valid, start parsing.
        //
        // There is a possibility a feed was found, so check for that first.
        // If there's a feed, take the first item.
        // ------------------------------------------------------------------

        if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'feed') {
          $parsed = ['data' => $parsed['data']['items'][0]];
        }

        if ($parsed && isset($parsed['data']['type']) && $parsed['data']['type'] == 'entry') {
          $data = $parsed['data'];

          // Type.
          $type = 'entry';
          if (!empty($data['type'])) {
            $type = $data['type'];
          }
          $webmention->set('type', $type);

          // Author.
          foreach (['name', 'photo', 'url'] as $key) {
            if (!empty($data['author'][$key])) {
              $author_value = trim($data['author'][$key]);
              if (!empty($author_value)) {
                $webmention->set('author_' . $key, $author_value);

                // Trigger cache if configured.
                if ($key == 'photo') {
                  \Drupal::service('indieweb.media_cache.client')->applyImageCache($author_value);
                }
              }
            }
          }

          // Content.
          foreach (['html', 'text'] as $key) {
            if (!empty($data['content'][$key])) {
              $webmention->set('content_' . $key, $data['content'][$key]);
            }
          }

          // Published.
          if (isset($data['published']) && !empty($data['published'])) {
            $webmention->set('created', strtotime($data['published']));
          }

          // Property. 'mention-of' is the default if we can't detect anything
          // specific. In case rsvp is set, set $data['url'] to 'in-reply-to'.
          $property = 'mention-of';
          $properties = ['rsvp', 'like-of', 'repost-of', 'in-reply-to', 'mention-of', 'bookmark-of', 'follow-of'];
          foreach ($properties as $p) {
            if (isset($data[$p]) && !empty($data[$p])) {
              $property = $p;
              break;
            }
          }
          $webmention->set('property', $property);

          // RSVP
          if ($property == 'rsvp') {
            $webmention->set('rsvp', $data['rsvp']);
          }

          // Url.
          if (!empty($data['url'])) {
            $webmention->set('url', $data['url']);
          }

          // Remove the base url and protect against empty target.
          $target = trim(str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $target));
          if (empty($target)) {
            $target = '/';
          }
          $webmention->set('target', $target);

          // Check identical webmentions. If the source, target and property are
          // the same, trigger an error.
          if (\Drupal::config('indieweb_webmention.settings')->get('webmention_detect_identical')) {
            $exists = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->checkIdenticalWebmention($source, $target, $property);
            if ($exists) {
              $error = TRUE;
              $parsed['error'] = 'duplicate';
              $parsed['error_description'] = strtr('Source @source, target @target and @property already exists.', ['@source' => $source, '@target' => $target, '@property' => $property]);
            }
          }
        }

        // ------------------------------------------------------------------
        // Error while parsing
        // ------------------------------------------------------------------

        elseif (isset($parsed['error'])) {
          $error = TRUE;
        }

        // ------------------------------------------------------------------
        // Unknown error..
        // ------------------------------------------------------------------

        else {
          $error = TRUE;
          $parsed['error'] = 'unknown';
          $parsed['error_description'] = 'Unknown error';
        }

      }
      catch (\Exception $e) {
        $error = TRUE;
        $parsed['error'] = 'exception';
        $parsed['error_description'] = $e->getMessage();
      }

      // ------------------------------------------------------------------
      // Valid webmention.
      // ------------------------------------------------------------------

      if (!$error) {

        // Set to published and save.
        $webmention->setPublished();
        $webmention->save();

        // Check syndication. If it exists, no need for further actions.
        if (!$this->sourceExistsAsSyndication($webmention)) {

          // Notification.
          if (\Drupal::hasService('indieweb.microsub.client')) {
            $client = \Drupal::service('indieweb.microsub.client');
            $client->sendNotification($webmention, $parsed);
          }

          // Create a comment.
          $this->createComment($webmention);
        }

      }

      // ------------------------------------------------------------------
      // Reset the type and property.
      // ------------------------------------------------------------------

      else {
        $error_type = isset($parsed['error']) ? $parsed['error'] : 'unknown';

        $webmention->set('type', $error_type);
        $webmention->set('property', $error_type);
        $webmention->save();

        // Log the error message if configured.
        if (\Drupal::config('indieweb_webmention.settings')->get('webmention_log_processing')) {
          $message = isset($parsed['error_description']) ? $parsed['error_description'] : 'Unknown parsing error';
          \Drupal::logger('indieweb_webmention_process')->notice('Error processing webmention @id: @message', ['@id' => $webmention->id(), '@message' => $message]);
        }

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createComment(WebmentionInterface $webmention) {
    if (($config = \Drupal::config('indieweb_webmention.comment')) && $config->get('comment_create_enable')) {

      // This can be simplified when config validation is ok
      if (
      !empty($config->get('comment_create_comment_type')) &&
      !empty($config->get('comment_create_webmention_reference_field')) &&
      !empty($config->get('comment_create_node_comment_field')) &&
      $webmention->get('property')->value == 'in-reply-to' &&
      !empty($webmention->get('content_text')->value)) {

        $pid = 0;
        $path = \Drupal::service('path.alias_manager')->getPathByAlias($webmention->get('target')->value);
        try {
          $params = Url::fromUri("internal:" . $path)->getRouteParameters();

          // This can be a reply on a comment. Get the node to attach the
          // comment there and set pid.
          if (!empty($params) && key($params) == 'comment') {
            /** @var \Drupal\comment\CommentInterface $comment */
            $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($params['comment']);
            if ($comment && $comment->getCommentedEntityTypeId() == 'node') {
              $pid = $comment->id();
              $params = ['node' => $comment->getCommentedEntityId()];
            }
          }

          // We currently support only comments on nodes.
          if (!empty($params) && key($params) == 'node') {
            $entity_type = key($params);

            $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
            if ($storage) {

              $comment_type = $config->get('comment_create_comment_type');
              $comment_comment_webmention_field_name = $config->get('comment_create_webmention_reference_field');
              $comment_node_comment_field_name = $config->get('comment_create_node_comment_field');

              /** @var \Drupal\node\NodeInterface $node */
              $node = $storage->load($params[$entity_type]);
              if ($node && $node->hasField($comment_node_comment_field_name) && $node->{$comment_node_comment_field_name}->status == 2) {

                $subject = 'Auto created comment from webmention';

                $values = [
                  'subject' => $subject,
                  'status' => $config->get('comment_create_default_status'),
                  'entity_id' => $node->id(),
                  'entity_type' => 'node',
                  'pid' => $pid,
                  'created' > $webmention->getCreatedTime(),
                  'comment_type' => $comment_type,
                  'field_name' => $comment_node_comment_field_name,
                  $comment_comment_webmention_field_name => [
                    'target_id' => $webmention->id(),
                  ],
                ];

                // Match authors if possible.
                $authors = Settings::get('indieweb_comment_authors', []);
                $author_name = $webmention->get('author_name')->value;
                $values['name'] = $author_name;
                if (isset($authors[$author_name])) {
                  $values['uid'] = $authors[$author_name];
                }

                $comment = Comment::create($values);
                $comment->save();

                // Send mail notification.
                if ($config->get('comment_create_mail_notification')) {
                  $mailManager = \Drupal::service('plugin.manager.mail');
                  $module = 'indieweb_webmention';
                  $key = 'webmention_comment_created';
                  $to = $config->get('comment_create_mail_notification');
                  $params['comment'] = $comment;
                  $params['comment_webmention_body'] = $webmention->getPlainContent();
                  $langcode = \Drupal::currentUser()->getPreferredLangcode();
                  $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
                }
              }
            }
          }
        }
        catch (Exception $e) {
          \Drupal::logger('indieweb_comment')->notice('Failed to create a comment: @message', ['@message' => $e->getMessage()]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sourceExistsAsSyndication(WebmentionInterface $webmention) {
    $exists = FALSE;

    if (strpos($webmention->getSource(), 'brid-gy.appspot') !== FALSE) {
      $parts = parse_url($webmention->getSource());
      $path_parts = explode('/', $parts['path']);
      if (!empty($path_parts[5])) {
        $exists = \Drupal::entityTypeManager()->getStorage('indieweb_syndication')->checkIdenticalSyndication($path_parts[5]);
      }
    }

    return $exists;
  }


  /**
   * Checks if url is a silo URL or not. Only handles Twitter urls right now.
   *
   * e.g. https://twitter.com/studioemma/status/999193968234713093 should be
   * marked as a silo url.
   *
   * @param $url
   *
   * @return bool
   */
  protected function isSiloURL($url) {
    $is_silo_url = FALSE;

    if (strpos($url, 'twitter.com') !== FALSE) {
      $is_silo_url = TRUE;
    }

    return $is_silo_url;
  }

}

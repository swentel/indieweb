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

    // Different domain for content.
    $config = \Drupal::config('indieweb_webmention.settings');
    $content_domain = $config->get('webmention_content_domain');
    if (!empty($content_domain)) {
      $data['source'] = str_replace(\Drupal::request()->getSchemeAndHttpHost(), $content_domain, $data['source']);
    }

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
    $remove_queue_item = TRUE;
    $syndication_targets = indieweb_get_syndication_targets();
    while (time() < $end && ($item = \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->claimItem())) {
      $data = $item->data;

      $store_send = FALSE;
      $send_mention = TRUE;
      if (!empty($data['source']) && !empty($data['target'])) {

        try {

          $sourceURL = $data['source'];
          $targetURL = $data['target'];

          // Check if the content exists if a content domain is configured.
          $config = \Drupal::config('indieweb_webmention.settings');
          $content_domain = $config->get('webmention_content_domain');
          if (!empty($content_domain)) {
            try {
              \Drupal::httpClient()->get($sourceURL . '?time=' . time());
            }
            catch (\Exception $exception) {
              // This is a 404 (well, normally it should be).
              $send_mention = FALSE;
              $remove_queue_item = FALSE;
            }
          }

          // Send with IndieWeb client.
          if ($send_mention) {

            $response = $this->sendWebmention($sourceURL, $targetURL);

            if ($response) {
              $store_send = TRUE;

              // Store the syndication when the targetUrl is in the syndication
              // targets.
              if (isset($syndication_targets[$targetURL])) {
                if ($response->getStatusCode() == 201 && !empty($response->getHeader('Location'))) {

                  if (!empty($data['entity_id']) && !empty($data['entity_type_id'])) {
                    $values = [
                      'entity_id' => $data['entity_id'],
                      'entity_type_id' => $data['entity_type_id'],
                      'url' => $response->getHeader('Location')[0],
                    ];

                    $syndication = \Drupal::entityTypeManager()->getStorage('indieweb_syndication')->create($values);
                    $syndication->save();

                    Cache::invalidateTags([$data['entity_type_id'] . ':' . $data['entity_id']]);
                  }
                }
              }
            }

            // Log the response if configured.
            if (\Drupal::config('indieweb_webmention.settings')->get('send_log_response')) {
              if ($response) {
                \Drupal::logger('indieweb_send')->notice('response code for @source to @target: @code', ['@code' => $response->getStatusCode(), '@source' => $sourceURL, '@target' => $targetURL]);
              }
              else {
                \Drupal::logger('indieweb_send')->notice('No webmention endpoint found for @source to @target', ['@source' => $sourceURL, '@target' => $targetURL]);
              }
            }
          }
        }
        catch (Exception $e) {
          \Drupal::logger('indieweb_send')->notice('Error sending webmention for @source to @target: @message', ['@message' => $e->getMessage(), '@source' => $sourceURL, '@target' => $targetURL]);
        }
      }

      // Store in send table.
      if ($store_send) {

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
      }

      // Remove the queue item.
      if ($remove_queue_item) {
        \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->deleteItem($item);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function sendWebmention($sourceURL, $targetURL) {
    $client = new MentionClient();
    $webmentionEndpoint = $client->discoverWebmentionEndpoint($targetURL);

    if ($webmentionEndpoint) {
      $client = \Drupal::httpClient();

      $options = [
        'headers' => [
          'Accept' => 'application/json, */*;q=0.8',
        ],
        'form_params' => [
          'source' => $sourceURL,
          'target' => $targetURL,
        ],
      ];

      return $client->post($webmentionEndpoint, $options);
    }

    return FALSE;
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

    // Allow local domain URL matching, used for testbot.
    $allow_local_domain = ['allow_local_domain' => (bool) drupal_valid_test_ua()];

    // Store valid webmentions for push notifications.
    $valid_webmentions = [];

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

        $parsed = $xray->parse($source, $body, ['target' => $target] + $allow_local_domain);

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
          $author_values = [];
          foreach (['name', 'photo', 'url'] as $key) {
            if (!empty($data['author'][$key])) {
              $author_value = trim($data['author'][$key]);
              if (!empty($author_value)) {
                $webmention->set('author_' . $key, $author_value);
                $author_values[$key] = $author_value;

                // Trigger cache if configured.
                if ($key == 'photo') {
                  \Drupal::service('indieweb.media_cache.client')->applyImageCache($author_value, 'avatar', 'webmention_avatar');
                }
              }
            }
          }

          // Contacts.
          if (!empty($author_values) && \Drupal::config('indieweb_contact.settings')->get('create_on_webmention')) {
            \Drupal::service('indieweb.contact.client')->storeContact($author_values);
          }

          // Content.
          foreach (['html', 'text'] as $key) {
            if (!empty($data['content'][$key])) {
              $webmention->set('content_' . $key, $data['content'][$key]);
            }
          }

          // Media.
          foreach (['photo', 'video', 'audio'] as $key) {
            if (!empty($data[$key])) {
              $webmention->set($key, $data[$key]);
            }
          }

          // Published.
          if (isset($data['published']) && !empty($data['published'])) {
            $webmention->setCreatedTime(strtotime($data['published']));
          }

          // Updated.
          if (isset($data['updated']) && !empty($data['updated'])) {
            $webmention->setChangedTime(strtotime($data['updated']));
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

          // Get the path without hostname.
          $target = indieweb_get_path($target);
          $webmention->set('target', $target);

          // In case of a comment, let's set the parent target to the node.
          if (strpos($target, 'comment') !== FALSE) {
            $comment_id = str_replace(['/comment/indieweb/', '/comment/'], '', $target);
            /** @var \Drupal\comment\CommentInterface $comment */
            $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($comment_id);
            if ($comment && $comment->getCommentedEntityTypeId() == 'node' && ($parent_target_id = $comment->getCommentedEntityId())) {
              $parent_target = Url::fromRoute('entity.node.canonical', ['node' => $parent_target_id])->toString();
              $webmention->set('parent_target', $parent_target);
            }
          }

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

        // Clear cache.
        if ($webmention->getProperty() != 'in-reply-to') {
          $this->clearCache($webmention->getTarget());
        }

        // Check syndication. If it exists, no need for further actions.
        if (!$this->sourceExistsAsSyndication($webmention)) {

          // Notification.
          if (\Drupal::hasService('indieweb.microsub.client')) {
            $client = \Drupal::service('indieweb.microsub.client');
            $client->sendNotification($webmention, $parsed);
            $valid_webmentions[] = $webmention;
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

    // Send push notification.
    if (!empty($valid_webmentions)) {
      if (\Drupal::hasService('indieweb.microsub.client')) {
        $client = \Drupal::service('indieweb.microsub.client');
        $client->sendPushNotification($valid_webmentions);
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
        $path = \Drupal::service('path_alias.manager')->getPathByAlias($webmention->get('target')->value);
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
                  'comment_type' => $comment_type,
                  'field_name' => $comment_node_comment_field_name,
                  $comment_comment_webmention_field_name => [
                    'target_id' => $webmention->id(),
                  ],
                ];

                // Auto approve
                if (!$values['status'] && ($trusted_domains = $config->get('comment_create_whitelist_domains')) && $this->autoApproveComment($trusted_domains, $webmention->getSource())) {
                  $values['status'] = 1;
                }

                // Match authors if possible.
                $authors = Settings::get('indieweb_comment_authors', []);
                $author_name = $webmention->get('author_name')->value;
                $values['name'] = $author_name;
                if (isset($authors[$author_name])) {
                  $values['uid'] = $authors[$author_name];
                }

                $comment = Comment::create($values);
                $comment->setCreatedTime($webmention->getCreatedTime());
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
  public function isSiloURL($url) {
    $is_silo_url = FALSE;

    if (strpos($url, 'twitter.com') !== FALSE) {
      $is_silo_url = TRUE;
    }

    return $is_silo_url;
  }

  /**
   * Clears the cache for a target.
   *
   * @param $target
   */
  public function clearCache($target) {
    $path = \Drupal::service('path_alias.manager')->getPathByAlias($target);
    try {
      $params = Url::fromUri("internal:" . $path)->getRouteParameters();
      if (!empty($params)) {
        $entity_type = key($params);

        $storage = \Drupal::entityTypeManager()->getStorage($entity_type);
        if ($storage) {
          /** @var \Drupal\Core\Entity\EntityInterface $entity */
          $entity = $storage->load($params[$entity_type]);
          if ($entity) {
            $storage->resetCache([$entity->id()]);
            Cache::invalidateTags([$entity_type . ':' . $entity->id()]);
          }
        }
      }
    }
    catch (Exception $ignored) {}
  }

  /**
   * Auto approve comment or not.
   *
   * @param $trusted_domains
   * @param $webmention_domain
   *
   * @return bool
   */
  public function autoApproveComment($trusted_domains, $webmention_domain) {
    $approve = FALSE;

    $domains = explode("\n", trim($trusted_domains));
    if (!empty($domains)) {
      foreach ($domains as $domain) {
        $trim = trim($domain);
        if (strlen($trim) > 0) {
          if (strpos($webmention_domain, $domain) !== FALSE) {
            $approve = TRUE;
            break;
          }
        }
      }
    }

    return $approve;
  }

}

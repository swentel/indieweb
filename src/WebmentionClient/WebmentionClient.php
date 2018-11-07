<?php

namespace Drupal\indieweb\WebmentionClient;

use Drupal\comment\Entity\Comment;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\indieweb\Entity\WebmentionInterface;
use Drupal\indieweb\XRay\DrupalHTML;
use Exception;
use IndieWeb\MentionClient;
use p3k\HTTP;

class WebmentionClient implements WebmentionClientInterface {

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
    $http = new HTTP();

    $values = [
      'status' => 0,
      'type' => 'webmention',
    ];

    /** @var \Drupal\indieweb\Entity\WebmentionInterface[] $webmentions */
    $webmentions = \Drupal::entityTypeManager()->getStorage('webmention_entity')->loadByProperties($values);
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
        // Parse the body with our parser which is based on the XRay parser.
        // We can remove this when https://github.com/aaronpk/XRay/pull/78
        // gets in.
        // ------------------------------------------------------------------

        $parsed = DrupalHtml::parse($http, $body, $source, ['target' => $target]);

        // ------------------------------------------------------------------
        // Target url was found on source and doc is valid, start parsing.
        // ------------------------------------------------------------------

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
          // specific. In case rsvp is set, set $data['url'] to 'in-reply-to.
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
          if (\Drupal::config('indieweb.webmention')->get('webmention_detect_identical')) {
            $exists = \Drupal::database()->query("SELECT id FROM {webmention_entity} WHERE source = :source AND target = :target AND property = :property ORDER by id DESC limit 1", [':source' => $source, ':target' => $target, ':property' => $property])->fetchField();
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

        // Send notification if configured.
        $client = \Drupal::service('indieweb.microsub.client');
        $client->sendNotification($webmention, $parsed);

        // Create a comment if configured.
        $this->createComment($webmention);

      }

      // ------------------------------------------------------------------
      // Reset the type and property.
      // ------------------------------------------------------------------

      else {
        $error = isset($parsed['error']) ? $parsed['error'] : 'unknown';

        $webmention->set('type', $error);
        $webmention->set('property', $error);
        $webmention->save();

        // Log the error message if configured.
        if (\Drupal::config('indieweb.webmention')->get('webmention_log_processing')) {
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
    // Comment creation.
    if (($config = \Drupal::config('indieweb.comment')) && $config->get('comment_create_enable')) {

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

                // Last check now is to make sure we do not create the same
                // comment again.
                if (indieweb_source_exists_as_syndication($webmention)) {
                  return;
                }

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
                  $module = 'indieweb';
                  $key = 'webmention_comment_created';
                  $to = $config->get('comment_create_mail_notification');
                  $params['comment'] = $comment;
                  $params['comment_webmention_body'] = $webmention->get('content_text')->value;
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


}

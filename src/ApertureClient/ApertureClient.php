<?php

namespace Drupal\indieweb\ApertureClient;

use Drupal\Core\Url;
use Drupal\indieweb\Entity\WebmentionInterface;

class ApertureClient implements ApertureClientInterface {

  /**
   * {@inheritdoc}
   */
  public function sendPost($api_key, WebmentionInterface $webmention) {
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
        if (($comment_config = \Drupal::config('indieweb.comment')) && $comment_config->get('comment_create_enable')) {
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
              $content .= "\n\n" . t('Comment available at @comment_url', ['@comment_url' => Url::fromRoute('indieweb.comment.canonical', ['comment' => $cid])->toString()]);
            }
          }
        }

        $properties['content'] = [$content];
        break;
      case 'mention-of':
        $properties['name'] = ['You were mentioned'];
        $properties['content'] = [$webmention->get('content_text')->value];
        break;
    }

    if (!empty($properties)) {

      $properties['published'] = [\Drupal::service('date.formatter')->format(\Drupal::time()->getRequestTime(), 'html_datetime')];
      $properties['url'] = [$webmention->toUrl('canonical', ['absolute' => TRUE])->toString()];
      $this->getAuthor($properties, $webmention);

      $post = new \stdClass();
      $post->type = ['h-entry'];
      $post->properties = $properties;
      $this->sendMicropubRequest($api_key, $post);
    }

  }

  /**
   * Adds the author the post.
   *
   * @param $post
   *   The post to create.
   * @param \Drupal\indieweb\Entity\WebmentionInterface $webmention
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

  /**
   * Send micropub request.
   *
   * @param $api_key
   *   The Aperture Channel API key.
   * @param $post
   *   The micropub post to send.
   */
  public function sendMicropubRequest($api_key, $post) {
    $auth = 'Bearer ' . $api_key;

    $client = \Drupal::httpClient();
    $headers = [
      'Accept' => 'application/json',
    ];

    // Access token is always in the headers when using Request from p3k.
    $headers['Authorization'] = $auth;

    try {
      $response = $client->post('https://aperture.p3k.io/micropub', ['json' => $post, 'headers' => $headers]);
      $status_code = $response->getStatusCode();
      $headersLocation = $response->getHeader('Location');
      if (empty($headersLocation[0]) || $status_code != 201) {
        \Drupal::logger('indieweb_aperture')->notice('Error sending micropub request: @code', ['@code' => $status_code]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('indieweb_aperture')->notice('Error sending micropub request: @message', ['@message' => $e->getMessage()]);
    }

  }

}
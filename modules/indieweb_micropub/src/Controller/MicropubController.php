<?php

namespace Drupal\indieweb_micropub\Controller;

use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MicropubController extends ControllerBase {

  /**
   * The action of the request.
   *
   * @var string
   */
  public $action = '';

  /**
   * The object type.
   */
  public $object_type = NULL;

  /**
   * The values used to create the node.
   *
   * @var array
   */
  public $values = [];

  /**
   * The request input.
   *
   * @var array
   */
  public $input = [];

  /**
   * An object URL to act on.
   *
   * @var null
   */
  public $object_url = NULL;

  /**
   * The original payload
   *
   * @var null
   */
  public $payload_original = NULL;

  /**
   * The node which is being created.
   *
   * @var \Drupal\node\NodeInterface
   */
  public $node = NULL;

  /**
   * The comment which is being created.
   *
   * @var \Drupal\comment\CommentInterface
   */
  public $comment = NULL;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Drupal\indieweb_indieauth\IndieAuthClient\IndieAuthClientInterface
   */
  protected $indieAuth;

  /**
   * Routing callback: micropub post endpoint.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function postEndpoint(Request $request) {
    $this->indieAuth = \Drupal::service('indieweb.indieauth.client');
    $this->config = \Drupal::config('indieweb_micropub.settings');
    $micropub_enabled = $this->config->get('micropub_enable');

    // Early response when endpoint is not enabled.
    if (!$micropub_enabled) {
      return new JsonResponse('', 404);
    }

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    // q=syndicate-to request.
    if (isset($_GET['q']) && $_GET['q'] == 'syndicate-to') {

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      if ($this->indieAuth->isValidToken($auth_header)) {
        $response_code = 200;
        $response_message = [
          'syndicate-to' => $this->getSyndicationTargets(),
        ];
      }
      else {
        $response_code = 403;
      }

      return new JsonResponse($response_message, $response_code);
    }

    // q=config request.
    if (isset($_GET['q']) && $_GET['q'] == 'config') {

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      if ($this->indieAuth->isValidToken($auth_header)) {
        $response_code = 200;
        $response_message = [
          'syndicate-to' => $this->getSyndicationTargets(),
        ];

        // Get post-types.
        $response_message['post-types'] = $this->getPostTypes();

        // Check media endpoint.
        if ($this->config->get('micropub_media_enable')) {
          $response_message['media-endpoint'] = Url::fromRoute('indieweb.micropub.media.endpoint', [], ['absolute' => TRUE])->toString();
        }
      }
      else {
        $response_code = 403;
      }

      return new JsonResponse($response_message, $response_code);
    }

    // q=source request.
    if (isset($_GET['q']) && $_GET['q'] == 'source') {

      // Early response when this is not enabled.
      if (!$this->config->get('micropub_enable_source')) {
        return new JsonResponse('', 404);
      }

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      if ($this->indieAuth->isValidToken($auth_header)) {
        $response_code = 200;
        $response_message = $this->getSourceResponse();
      }
      else {
        $response_code = 403;
      }

      return new JsonResponse($response_message, $response_code);
    }

    // q=category request.
    if (isset($_GET['q']) && $_GET['q'] == 'category') {

      // Early response when this is not enabled.
      if (!$this->config->get('micropub_enable_category')) {
        return new JsonResponse('', 404);
      }

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      if ($this->indieAuth->isValidToken($auth_header)) {
        $response_code = 200;
        $response_message = $this->getCategoryResponse();
      }
      else {
        $response_code = 403;
      }

      return new JsonResponse($response_message, $response_code);
    }

    // Indigenous sends POST vars along with multipart, we use all().
    if (strpos($request->headers->get('Content-Type'), 'multipart/form-data') !== FALSE) {
      $input = $request->request->all();
    }
    else {
      $input = $request->getContent();
    }
    // Determine action and input from request. This can either be POST or JSON
    // request. We use p3k/Micropub to handle that part.
    $micropub_request = \p3k\Micropub\Request::create($input);
    if ($micropub_request instanceof \p3k\Micropub\Request && $micropub_request->action) {
      $this->action = $micropub_request->action;

      if ($this->action == 'update') {
        $this->input = $micropub_request->update;
        $this->object_url = $micropub_request->url;
      }
      elseif ($this->action == 'delete') {
        $this->object_url = $micropub_request->url;
      }
      else {
        $mf2 = $micropub_request->toMf2();
        $this->object_type = !empty($mf2['type'][0]) ? $mf2['type'][0] : '';
        $this->input = $mf2['properties'];
        $this->input += $micropub_request->commands;
      }
    }
    else {
      $description = $micropub_request->error_description ? $micropub_request->error_description : 'Unknown error';
      $this->getLogger('indieweb_micropub')->notice('Error parsing incoming request: @message - @input', ['@message' => $description, '@input' => print_r($input, 1)]);
      return new JsonResponse('Bad request', 400);
    }

    // Attempt to delete a node, comment or webmention.
    if ($this->action == 'delete' && $this->config->get('micropub_enable_delete') && !empty($this->object_url)) {

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      // Validate token. Return early if it's not valid.
      $valid_token = $this->indieAuth->isValidToken($auth_header, 'delete');
      if (!$valid_token) {
        return new JsonResponse('', 403);
      }

      $response_message = '';
      $response_code = 404;

      $path = str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $this->object_url);
      try {
        $params = Url::fromUri("internal:" . $path)->getRouteParameters();

        if (!empty($params) && in_array(key($params), ['comment', 'node', 'indieweb_webmention'])) {

          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $this->entityTypeManager()->getStorage(key($params))->load($params[key($params)]);
          if ($entity) {
            $response_message = '';
            $response_code = 200;
            $entity->delete();
          }
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_micropub')->notice('Error in deleting post: @message', ['@message' => $e->getMessage()]);
      }

      return new Response($response_message, $response_code);
    }

    // Attempt to update a node or comment.
    if ($this->action == 'update' && $this->config->get('micropub_enable_update') && !empty($this->object_url) && !empty($this->input['replace'])) {

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      // Validate token. Return early if it's not valid.
      $valid_token = $this->indieAuth->isValidToken($auth_header, 'update');
      if (!$valid_token) {
        return new JsonResponse('', 403);
      }

      $path = str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $this->object_url);
      try {
        $params = Url::fromUri("internal:" . $path)->getRouteParameters();

        if (!empty($params) && in_array(key($params), ['comment', 'node'])) {

          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $this->entityTypeManager()->getStorage(key($params))->load($params[key($params)]);
          if ($entity) {
            $update = FALSE;

            // Post status.
            if (!empty($this->input['replace']['post-status'][0])) {
              $status = NULL;
              if ($this->input['replace']['post-status'][0] == 'draft') {
                $status = FALSE;
              }
              if ($this->input['replace']['post-status'][0] == 'published') {
                $status = TRUE;
              }

              if (isset($status)) {
                $update = TRUE;
                $status ? $entity->setPublished() : $entity->setUnpublished();
              }
            }

            // Title.
            if (!empty($this->input['replace']['name'][0])) {
              $update = TRUE;
              $label_field = 'title';
              if ($entity->getEntityTypeId() == 'comment') {
                $label_field = 'name';
              }
              $entity->set($label_field, $this->input['replace']['name'][0]);
            }

            // Body.
            if (!empty($this->input['replace']['content'][0])) {
              $update = TRUE;
              $label_field = 'body';
              if ($entity->getEntityTypeId() == 'comment') {
                $label_field = 'comment_body';
              }
              $entity->set($label_field, $this->input['replace']['content'][0]);
            }

            if ($update) {
              $entity->save();
            }

            $response_message = '';
            $response_code = 200;
          }
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_micropub')->notice('Error in updating object: @message', ['@message' => $e->getMessage()]);
      }

      return new Response($response_message, $response_code);
    }

    // Attempt to create a node.
    if (!empty($this->input) && $this->action == 'create') {

      // Get authorization header, response early if none found.
      $auth_header = $this->indieAuth->getAuthorizationHeader();
      if (!$auth_header) {
        return new JsonResponse('', 401);
      }

      // Validate token. Return early if it's not valid.
      $valid_token = $this->indieAuth->isValidToken($auth_header, 'create');
      if (!$valid_token) {
        return new JsonResponse('', 403);
      }

      // Store original input so it can be inspected by hooks.
      $this->payload_original = $this->input;

      // Log payload.
      if ($this->config->get('micropub_log_payload')) {
        $this->getLogger('indieweb_micropub_payload')->notice('input: @input', ['@input' => print_r($this->input, 1)]);
      }

      // The order here is of importance. Don't change it, unless there's a good
      // reason for, see https://indieweb.org/post-type-discovery. This does not
      // follow the exact rules, because we can be more flexible in Drupal.

      // Event support.
      if ($this->createNodeFromPostType('event') && $this->isHEvent() && $this->hasRequiredInput(['start', 'end', 'name'])) {
        $this->createNode($this->input['name'], 'event');

        // Date.
        $date_field_name = $this->config->get('event_date_field');
        if ($date_field_name && $this->node->hasField($date_field_name)) {
          $this->node->set($date_field_name, ['value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, strtotime($this->input['start'][0])), 'end_value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, strtotime($this->input['end'][0]))]);
        }

        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // RSVP support.
      if ($this->createNodeFromPostType('rsvp') && $this->isHEntry() && $this->hasRequiredInput(['in-reply-to', 'rsvp'])) {
        $this->createNode('RSVP on ' . $this->input['in-reply-to'][0], 'rsvp', 'in-reply-to');

        // RSVP field
        $rsvp_field_name = $this->config->get('rsvp_rsvp_field');
        if ($rsvp_field_name && $this->node->hasField($rsvp_field_name)) {
          $this->node->set($rsvp_field_name, $this->input['rsvp']);
        }

        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Repost support.
      if ($this->createNodeFromPostType('repost') && $this->isHEntry() && $this->hasRequiredInput(['repost-of'])) {
        $this->createNode('Repost of ' . $this->input['repost-of'][0], 'repost', 'repost-of');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Bookmark support.
      if ($this->createNodeFromPostType('bookmark') && $this->isHEntry() && $this->hasRequiredInput(['bookmark-of'])) {
        $this->createNode('Bookmark of ' . $this->input['bookmark-of'][0], 'bookmark', 'bookmark-of');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Like support.
      if ($this->createNodeFromPostType('like') && $this->isHEntry() && $this->hasRequiredInput(['like-of'])) {
        $this->createNode('Like of ' . $this->input['like-of'][0], 'like', 'like-of');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Issue support.
      if ($this->createNodeFromPostType('issue') && $this->isHEntry() && $this->hasRequiredInput(['content', 'name', 'in-reply-to']) && $this->hasNoKeysSet(['bookmark-of', 'repost-of', 'like-of'])) {
        $this->createNode($this->input['name'], 'issue');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Reply support.
      if ($this->createNodeFromPostType('reply') && $this->isHEntry() && $this->hasRequiredInput(['in-reply-to', 'content']) && $this->hasNoKeysSet(['rsvp'])) {

        // Check if we should create a comment.
        if ($this->config->get('reply_create_comment') && (($comment_config = \Drupal::config('indieweb_webmention.comment')) && $comment_config->get('comment_create_enable'))) {
          $pid = 0;
          $link_field_url = '';
          $reply = $this->input['in-reply-to'][0];
          $reply = str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $reply);
          $path = \Drupal::service('path.alias_manager')->getPathByAlias($reply);
          try {
            $params = Url::fromUri("internal:" . $path)->getRouteParameters();

            // This can be a reply on a webmention. Check if there is a comment
            // connected with it.
            if (!empty($params) && key($params) == 'indieweb_webmention') {
              /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention_target */
              $comment_comment_webmention_field_name = $comment_config->get('comment_create_webmention_reference_field');
              $table_name = 'comment__' . $comment_comment_webmention_field_name;
              $webmention_target = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->load($params['indieweb_webmention']);
              if ($webmention_target && $webmention_target->get('property')->value == 'in-reply-to') {
                if (\Drupal::database()->schema()->tableExists($table_name)) {
                  $cid = \Drupal::database()
                    ->select($table_name, 'a')
                    ->fields('a', ['entity_id'])
                    ->condition($comment_comment_webmention_field_name . '_target_id', $webmention_target->id())
                    ->execute()
                    ->fetchField();

                  if ($cid) {

                    // Check url.
                    $url = $webmention_target->get('url')->value;
                    if (!empty($url)) {
                      $link_field_url = $url;
                    }

                    $params = ['comment' => $cid];
                  }
                }
              }
            }

            // This can be a reply on a comment, or set via a webmention in the
            // previous if statement. Get the node to attach the comment there
            // and set pid.
            if (!empty($params) && key($params) == 'comment') {
              /** @var \Drupal\comment\CommentInterface $comment */
              $comment = \Drupal::entityTypeManager()->getStorage('comment')->load($params['comment']);
              if ($comment && $comment->getCommentedEntityTypeId() == 'node') {
                $pid = $comment->id();
                $params = ['node' => $comment->getCommentedEntityId()];
              }
            }

            // Target is a node.
            if (!empty($params) && key($params) == 'node') {

              /** @var \Drupal\node\NodeInterface $node */
              $node = $this->entityTypeManager()->getStorage('node')->load($params['node']);

              $comment_type = $comment_config->get('comment_create_comment_type');
              $comment_node_comment_field_name = $comment_config->get('comment_create_node_comment_field');
              if ($node && $node->hasField($comment_node_comment_field_name) && $node->{$comment_node_comment_field_name}->status == 2) {

                $subject = 'Auto created comment from reply micropub';

                $values = [
                  'subject' => $subject,
                  // Since this is a micropub request, this can be published.
                  'status' => 1,
                  'uid' => $this->config->get('reply_uid'),
                  'entity_id' => $node->id(),
                  'entity_type' => 'node',
                  'pid' => $pid,
                  'comment_type' => $comment_type,
                  'field_name' => $comment_node_comment_field_name,
                  'comment_body' => [
                    'value' => $this->input['content'][0],
                    'format' => 'restricted_html',
                  ],
                ];

                // Create comment.
                $this->comment = Comment::create($values);

                // Check link field.
                if (!empty($link_field_url)) {
                  $link_fields_string = \Drupal::config('indieweb_webmention.settings')->get('send_link_fields');
                  if (!empty($link_fields_string)) {
                    $link_fields = explode('|', $link_fields_string);
                    foreach ($link_fields as $field) {
                      if ($this->comment->hasField($field)) {

                        // Save link.
                        $this->comment->set($field, ['uri' => $link_field_url, 'title' => '']);

                        // Syndicate.
                        if ($this->config->get('reply_auto_send_webmention')) {
                          if (isset($this->input['mp-syndicate-to'])) {
                            $this->input['mp-syndicate-to'][] = $link_field_url;
                          }
                          else {
                            $this->input['mp-syndicate-to'] = [$link_field_url];
                          }
                        }
                      }
                    }
                  }
                }

                $response = $this->saveComment();
                if ($response instanceof Response) {
                  return $response;
                }
              }
            }
          }
          catch (\Exception $e) {
            // Ignore error messages when Url::fromUri can't detect this is an
            // internal url.
            if (strpos($e->getMessage(), 'internal:/foo') === FALSE) {
              $this->getLogger('indieweb_micropub')->notice('Error trying to create a comment from reply: @message', ['@message' => $e->getMessage()]);
            }
          }
        }

        // We got here, so it's a standard node.
        $this->createNode('In reply to ' . $this->input['in-reply-to'][0], 'reply', 'in-reply-to');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Note post type.
      if ($this->createNodeFromPostType('note') && $this->isHEntry() && $this->hasRequiredInput(['content']) && $this->hasNoKeysSet(['name', 'in-reply-to', 'bookmark-of', 'repost-of', 'like-of'])) {
        $this->createNode('Micropub post', 'note');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Article post type.
      if ($this->createNodeFromPostType('article') && $this->isHEntry() && $this->hasRequiredInput(['content', 'name']) && $this->hasNoKeysSet(['in-reply-to', 'bookmark-of', 'repost-of', 'like-of'])) {
        $this->createNode($this->input['name'], 'article');
        $response = $this->saveNode();
        if ($response instanceof Response) {
          return $response;
        }
      }

      // If we get end up here, it means that no node has been created.
      $location = \Drupal::moduleHandler()->invokeAll('indieweb_micropub_no_post_made', [$this->payload_original]);
      if (!empty($location[0])) {
        header('Location: ' . $location[0]);
        return new JsonResponse('', 201);
      }

    }

    return new JsonResponse($response_message, $response_code);
  }

  /**
   * Upload files through the media endpoint.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function mediaEndpoint() {
    $this->indieAuth = \Drupal::service('indieweb.indieauth.client');
    $this->config = \Drupal::config('indieweb_micropub.settings');
    $micropub_media_enabled = $this->config->get('micropub_media_enable');

    // Early response when endpoint is not enabled.
    if (!$micropub_media_enabled) {
      return new JsonResponse('', 404);
    }

    // Default message.
    $response_message = 'Bad request';

    // Get authorization header, response early if none found.
    $auth_header = $this->indieAuth->getAuthorizationHeader();
    if (!$auth_header) {
      return new JsonResponse('', 401);
    }

    if ($this->indieAuth->isValidToken($auth_header, 'media')) {
      $response_code = 200;
      $extensions = 'jpg jpeg gif png';
      $validators['file_validate_extensions'] = [];
      $validators['file_validate_extensions'][0] = $extensions;
      $sub_directory = date('Y') . '/' . date('m');
      $file = $this->saveUpload('file', 'public://micropub/' . $sub_directory, $validators);
      if ($file) {

        // Set permanent.
        $file->setPermanent();
        $file->save();

        // Return the url in Location.
        $response_code = 201;
        $file_url = file_create_url($file->getFileUri());
        $response_message = [];
        $response_message['url'] = $file_url;
        header('Location: ' . $file_url);
      }
    }
    else {
      $response_code = 403;
      $response_message = 'Forbidden';
    }

    return new JsonResponse($response_message, $response_code);
  }

  /**
   * Returns whether the input type is a h-entry.
   *
   * @return bool
   */
  protected function isHEntry() {
    return isset($this->object_type) && $this->object_type == 'h-entry';
  }

  /**
   * Returns whether the input type is a h-event.
   *
   * @return bool
   */
  protected function isHEvent() {
    return isset($this->object_type) && $this->object_type == 'h-event';
  }

  /**
   * Checks if required values are in input.
   *
   * @param array $keys
   *
   * @return bool
   */
  protected function hasRequiredInput($keys = []) {
    $has_required_values = TRUE;

    foreach ($keys as $key) {
      if (empty($this->input[$key])) {
        $has_required_values = FALSE;
        break;
      }
    }

    return $has_required_values;
  }

  /**
   * Check that none of the keys are set in input.
   *
   * @param $keys
   *
   * @return bool
   */
  protected function hasNoKeysSet($keys) {
    $has_no_keys_set = TRUE;

    foreach ($keys as $key) {
      if (isset($this->input[$key])) {
        $has_no_keys_set = FALSE;
        break;
      }
    }

    return $has_no_keys_set;
  }

  /**
   * Whether creating a node for this post type is enabled.
   *
   * @param $post_type
   *
   * @return array|mixed|null
   */
  protected function createNodeFromPostType($post_type) {
    return $this->config->get($post_type . '_create_node');
  }

  /**
   * Create a node.
   *
   * @param $title
   *   The title for the node.
   * @param $post_type
   *   The IndieWeb post type.
   * @param $link_input_name
   *   The name of the property in input for auto syndication.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createNode($title, $post_type, $link_input_name = NULL) {

    $this->values = [
      'uid' => $this->config->get($post_type . '_uid'),
      'title' => $title,
      'type' => $this->config->get($post_type . '_node_type'),
      'status' => $this->config->get($post_type . '_status'),
    ];

    // Check post-status.
    if (!empty($this->input['post-status'][0])) {
      if ($this->input['post-status'][0] == 'published') {
        $this->values['status'] = 1;
      }
      if ($this->input['post-status'][0] == 'draft') {
        $this->values['status'] = 0;
      }
    }

    // Add link to syndicate to.
    if ($link_input_name && $this->config->get($post_type . '_auto_send_webmention')) {
      if (isset($this->input['mp-syndicate-to'])) {
        $this->input['mp-syndicate-to'] += $this->input[$link_input_name];
      }
      else {
        $this->input['mp-syndicate-to'] = $this->input[$link_input_name];
      }
    }

    // Allow code to change the values and payload.
    \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $this->values, $this->input);

    $this->node = Node::create($this->values);

    // Content.
    $content_field_name = $this->config->get($post_type . '_content_field');
    if (!empty($this->input['content'][0]) && $this->node->hasField($content_field_name)) {
      $this->node->set($content_field_name, $this->input['content']);
    }

    // Link.
    $link_field_name = $this->config->get($post_type . '_link_field');
    if ($link_field_name && $this->node->hasField($link_field_name)) {
      $this->node->set($link_field_name, ['uri' => $this->input[$link_input_name][0], 'title' => '']);
    }

    // Uploads.
    $this->handleUpload($post_type . '_upload_field');

    // Categories.
    $this->handleCategories($post_type . '_tags_field');

    // Geo location.
    $this->handleGeoLocation($post_type . '_geo_field');
  }

  /**
   * Saves the node.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveNode() {

    $this->node->save();
    if ($this->node->id()) {

      // Syndicate.
      $this->syndicateToNode();

      // Allow modules to react after the node is saved.
      \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$this->node, $this->values, $this->input, $this->payload_original]);

      header('Location: ' . $this->node->toUrl('canonical', ['absolute' => TRUE])->toString());
      return new Response('', 201);
    }

  }

  /**
   * Saves the comment.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function saveComment() {

    $this->comment->save();
    if ($this->comment->id()) {

      // Syndicate.
      $this->syndicateToComment();

      header('Location: ' . $this->comment->toUrl('canonical', ['absolute' => TRUE])->toString());
      return new Response('', 201);
    }

  }

  /**
   * Helper function to upload file(s).
   *
   * Currently limited to 1 file.
   *
   * @param $file_key
   *   The key in the $_FILES variable to look for in upload.
   * @param string $destination
   *   The destination of the file.
   * @param array $validators
   *   A list of validators. If empty, anything is allowed.
   *
   * @return bool|\Drupal\file\FileInterface
   */
  protected function saveUpload($file_key, $destination = 'public://', $validators = []) {
    $file = FALSE;

    // Return early if there are no uploads.
    $files = \Drupal::request()->files->get($file_key);
    if (empty($files)) {
      return $file;
    }

    // Set files.
    \Drupal::request()->files->set('files', [$file_key => $files]);

    // Try to save the file.
    try {
      file_prepare_directory($destination, FILE_CREATE_DIRECTORY);
      $file = file_save_upload($file_key, $validators, $destination, 0);
      $messages = drupal_get_messages();
      if (!empty($messages)) {
        foreach ($messages as $message) {
          $this->getLogger('indieweb_micropub')->notice('Error saving file: @message', ['@message' => print_r($message, 1)]);
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('indieweb_micropub')->notice('Exception saving file: @message', ['@message' => $e->getMessage()]);
    }

    return $file;
  }

  /**
   * Handle uploads
   *
   * @param $upload_field
   */
  protected function handleUpload($upload_field) {
    // File (currently only image, limited to 1).
    $file_field_name = $this->config->get($upload_field);
    if ($file_field_name && $this->node->hasField($file_field_name)) {
      $file = $this->saveUpload('photo');
      if ($file) {
        $this->node->set($file_field_name, $file->id());
      }
    }
  }

  /**
   * Handle and set categories.
   *
   * @param $config_key
   *   The config key for the tags field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function handleCategories($config_key) {
    $tags_field_name = $this->config->get($config_key);

    if ($tags_field_name && $this->node->hasField($tags_field_name) && !empty($this->input['category'])) {
      $values = $bundles = [];
      $auto_create = FALSE;

      // Check field definition settings of the field.
      $field_settings = $this->node->getFieldDefinition($tags_field_name)->getSettings();
      if (!empty($field_settings['handler_settings']['auto_create'])) {
        $auto_create = TRUE;
      }
      if (!empty($field_settings['handler_settings']['target_bundles'])) {
        $bundles = $field_settings['handler_settings']['target_bundles'];
      }

      // Only go further if we have bundles, should be as it's a required
      // property.
      if (!empty($bundles)) {

        // Take the first bundle, there should only be one, but you never know.
        $vocabulary = array_shift($bundles);

        // Get any ids that match with the incoming categories.
        $existing_categories = [];
        $existing_category_ids = \Drupal::entityQuery('taxonomy_term')
          ->condition('name', $this->input['category'], 'IN')
          ->execute();

        // Get the actual terms.
        if (!empty($existing_category_ids)) {
          $existing_categories_loaded = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($existing_category_ids);
          foreach ($existing_categories_loaded as $loaded_term) {
            $existing_categories[$loaded_term->label()] = $loaded_term;
          }
        }

        // Now loop over incoming categories.
        foreach ($this->input['category'] as $category) {

          // Auto create and not existing term.
          if ($auto_create && !isset($existing_categories[$category])) {
            $term = Term::create([
              'name' => $category,
              'vid' => $vocabulary,
              'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
            ]);
            $values[] = ['entity' => $term];
          }
          // Existing term.
          elseif (isset($existing_categories[$category])) {
            $values[] = ['entity' => $existing_categories[$category]];
          }
        }

        if (!empty($values)) {
          $this->node->set($tags_field_name, $values);
        }
      }
    }
  }

  /**
   * Handles geo location input.
   *
   * @param $config_key
   *   The config key for the tags field.
   */
  protected function handleGeoLocation($config_key) {
    $geo_field_name = $this->config->get($config_key);
    if ($geo_field_name && $this->node->hasField($geo_field_name) && !empty($this->input['location'][0])) {
      $properties = explode(':', $this->input['location'][0]);
      if (!empty($properties[0]) && $properties[0] == 'geo' && !empty($properties[1])) {
        $lat_lon = explode(',', $properties[1]);
        if (!empty($lat_lon[0]) && !empty($lat_lon[1])) {
          try {
            $service = \Drupal::service('geofield.wkt_generator');
            if ($service) {
              $value = $service->wktBuildPoint([trim($lat_lon[1]), trim($lat_lon[0])]);
              if (!empty($value)) {
                $this->node->set($geo_field_name, $value);
              }
            }
          }
          catch (\Exception $e) {
            $this->getLogger('indieweb_micropub')->notice('Error saving geo location: @message', ['@message' => $e->getMessage()]);
          }
        }
      }
    }
  }

  /**
   * Syndicate to for nodes.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function syndicateToNode() {
    if (\Drupal::moduleHandler()->moduleExists('indieweb_webmention')) {
      if (!empty($this->input['mp-syndicate-to']) && $this->node->isPublished()) {
        $source = $this->node->toUrl()->setAbsolute(TRUE)->toString();
        foreach ($this->input['mp-syndicate-to'] as $target) {
          \Drupal::service('indieweb.webmention.client')->createQueueItem($source, $target, $this->node->id(), 'node');
        }
      }
    }
  }

  /**
   * Syndicate to for comments.
   */
  protected function syndicateToComment() {
    if (\Drupal::moduleHandler()->moduleExists('indieweb_webmention')) {
      if (!empty($this->input['mp-syndicate-to']) && $this->comment->isPublished()) {
        $source = Url::fromRoute('indieweb.comment.canonical', ['comment' => $this->comment->id()], ['absolute' => TRUE])->toString();
        foreach ($this->input['mp-syndicate-to'] as $target) {
          \Drupal::service('indieweb.webmention.client')->createQueueItem($source, $target, $this->comment->id(), 'comment');
        }
      }
    }
  }

  /**
   * Get post types.
   */
  protected function getPostTypes() {
    $post_types = [];

    foreach (indieweb_micropub_post_types() as $type) {
      if ($this->config->get($type . '_create_node')) {
        $post_types[] = (object) array(
          'type' => $type,
          'name' => ucfirst($type),
        );
      }
    }

    if (\Drupal::moduleHandler()->moduleExists('comment')) {
      $post_types[] = (object) array(
        'type' => 'comment',
        'name' => t('Commments'),
      );
    }

    return $post_types;
  }

  /**
   * Gets syndication targets.
   *
   * @return array
   */
  protected function getSyndicationTargets() {
    $syndication_targets = [];

    if (\Drupal::moduleHandler()->moduleExists('indieweb_webmention')) {
      $targets = indieweb_get_syndication_targets();
      if (!empty($targets)) {
        foreach ($targets as $url => $name) {
          $syndication_targets[] = [
            'uid' => $url,
            'name' => $name,
          ];
        }
      }
    }

    return $syndication_targets;
  }

  /**
   * Gets category response: return a list of terms.
   *
   * @return array $terms
   *   A list of terms.
   */
  protected function getCategoryResponse() {
    $vocabulary = $this->config->get('micropub_category_vocabulary');

    $terms = \Drupal::database()
      ->select('taxonomy_term_field_data', 't')
      ->fields('t', ['name'])
      ->condition('vid', $vocabulary)
      ->orderBy('name', 'ASC')
      ->execute()
      ->fetchCol('name');

    return $terms;
  }

  /**
   * Returns the source response. This can either be a list of post items, or a
   * single post with properties.
   *
   * @see https://github.com/indieweb/micropub-extensions/issues/4
   *
   * @return array $return.
   *   Either list of posts or a single item with properties.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function getSourceResponse() {
    $return = [];

    // Single URL.
    if (isset($_GET['url'])) {

      $path = str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $_GET['url']);
      try {
        $params = Url::fromUri("internal:" . $path)->getRouteParameters();
        if (!empty($params) && in_array(key($params), ['comment', 'node'])) {

          /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
          $entity = $this->entityTypeManager()->getStorage(key($params))->load($params[key($params)]);
          if ($entity) {

            $properties = [];
            switch ($entity->getEntityTypeId()) {
              case 'node':
                $properties = $this->getNodeProperties($entity);
                break;
              case 'comment':
                $properties = $this->getCommentProperties($entity);
                break;
            }
            $return = ['properties' => (object) $properties];
          }
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_micropub')->notice('Error in getting url post: @message', ['@message' => $e->getMessage()]);
      }

    }
    // List of posts.
    else {
      $offset = 0;
      $range = 10;
      $after = 1;
      $items = [];
      $filter = '';
      $get_nodes = TRUE;
      $get_comments = TRUE;

      // Filter on post-type.
      if (isset($_GET['post-type']) && !empty($_GET['post-type'])) {
        $type = $_GET['post-type'];
        if ($type == 'comment') {
          $get_nodes = FALSE;
          $filter = 'comment';
        }
        else {
          $post_types = $this->getPostTypes();
          foreach ($post_types as $post_type) {
            if ($post_type->type == $type) {
              $get_comments = FALSE;
              $filter = $this->config->get($type . '_node_type');
              break;
            }
          }
        }
      }

      // Override limit.
      if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] > 0 && $_GET['limit'] <= 100) {
        $range = $_GET['limit'];
      }

      // Override offset.
      if (isset($_GET['after']) && is_numeric($_GET['after'])) {
        $offset = $range * $_GET['after'];
        $after = $_GET['after'] + 1;
      }

      // Get nodes.
      if (\Drupal::moduleHandler()->moduleExists('node') && $get_nodes) {

        $query = \Drupal::entityQuery('node')
          ->sort('created', 'DESC')
          ->range($offset, $range);

        if ($filter) {
          $query->condition('type', $filter);
        }

        $ids = $query->execute();

        if (!empty($ids)) {
          $nodes = $this->entityTypeManager()
            ->getStorage('node')
            ->loadMultiple($ids);
          /** @var \Drupal\node\NodeInterface $node */
          foreach ($nodes as $node) {
            $item = new \stdClass();
            $item->type = ['h-entry'];
            $item->properties = (object) $this->getNodeProperties($node);
            $items[$node->getCreatedTime()] = $item;
          }
        }
      }

      // Get comments.
      if (\Drupal::moduleHandler()->moduleExists('comment') && $get_comments) {

        $query = \Drupal::entityQuery('comment')
          ->sort('created', 'DESC')
          ->range($offset, $range);

        $ids = $query->execute();

        if (!empty($ids)) {
          $comments = $this->entityTypeManager()
            ->getStorage('comment')
            ->loadMultiple($ids);
          /** @var \Drupal\comment\CommentInterface $comment */
          foreach ($comments as $comment) {
            $item = new \stdClass();
            $item->type = ['h-entry'];
            $item->properties = (object) $this->getCommentProperties($comment);
            $items[$comment->getCreatedTime()] = $item;
          }
        }
      }

      krsort($items);
      $items_sorted = [];
      foreach ($items as $item) {
        $items_sorted[] = $item;
      }
      $return['items'] = $items_sorted;

      if (!empty($items_sorted)) {
        $return['paging'] = (object) array('after' => $after);
      }
    }

    return $return;
  }

  /**
   * Get node properties.
   *
   * @param \Drupal\node\NodeInterface $node
   *
   * @return array $properties
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function getNodeProperties(NodeInterface $node) {
    $properties = [];

    $properties['url'] = [$node->toUrl('canonical', ['absolute' => TRUE])->toString()];
    $properties['name'] = [$node->label()];
    if ($node->hasField('body')) {
      if (!empty($node->get('body')->value)) {
        $properties['content'] = [$node->get('body')->value];
      }
    }
    $properties['post-status'] = [($node->isPublished() ? 'published' : 'draft')];
    $properties['published'] = [\Drupal::service('date.formatter')->format($node->getCreatedTime(), 'html_datetime')];

    return $properties;
  }

  /**
   * Get comment properties.
   *
   * @param \Drupal\comment\CommentInterface $comment
   *
   * @return array $properties
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  private function getCommentProperties(CommentInterface $comment) {
    $properties = [];
    $properties['url'] = [$comment->toUrl('canonical', ['absolute' => TRUE])->toString()];
    $properties['name'] = [$comment->label()];
    $content = '';
    if ($comment->hasField('comment_body')) {
      if (!empty($comment->comment_body->value)) {
        $comment->get('comment_body')->value;
      }
    }
    // Check if a webmention is connected, if so, get the text from in
    // into content
    if (!empty($comment->indieweb_webmention->target_id)) {
      /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention */
      $webmention = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->load($comment->indieweb_webmention->target_id);
      if ($webmention) {
        if (!empty($webmention->content_text->value)) {
          $content = check_markup($webmention->content_text->value, 'restricted_html');
        }
      }
    }

    $properties['content'] = [$content];
    $properties['post-status'] = [($comment->isPublished() ? 'published' : 'draft')];
    $properties['published'] = [\Drupal::service('date.formatter')->format($comment->getCreatedTime(), 'html_datetime')];

    return $properties;
  }
}

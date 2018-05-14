<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MicropubController extends ControllerBase {

  /** @var  \Drupal\Core\Config\Config */
  protected $config;

  /**
   * Routing callback: micropub endpoint.
   */
  public function endpoint() {
    $this->config = \Drupal::config('indieweb.micropub');
    $micropub_enabled = $this->config->get('micropub_enable');

    // Early return when endpoint is not enabled.
    if (!$micropub_enabled) {
      return new JsonResponse('', 404);
    }

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    // q=syndicate-to request.
    if (isset($_GET['q']) && $_GET['q'] == 'syndicate-to') {

      if ($this->isValidToken()) {

        $syndicate_channels = [];

        $response_code = 200;
        $channels = indieweb_get_publishing_channels();
        if (!empty($channels)) {
          foreach ($channels as $url => $name) {
            $syndicate_channels[] = [
              'uid' => $url,
              'name' => $name,
            ];
          }
        }

        $response_message = [
          'syndicate-to' => $syndicate_channels
        ];
      }
    }

    $input = NULL;
    if (!empty($_POST)) {
      $input = $_POST;
    }
    else {
      $php_input = file('php://input');
      $php_input = is_array($php_input) ? array_shift($php_input) : '';
      $input = json_decode($php_input, TRUE);

      // Need to figure out why quill nests everything in 'properties'
      // probably todo with the the format
      if (isset($input['properties'])) {
        $input += $input['properties'];
      }

    }
    if (!empty($input)) {

      $payload_original = $input;
      $valid_token = $this->isValidToken($input);

      if ($this->config->get('micropub_log_payload')) {
        $this->getLogger('indieweb_micropub_payload')->notice('input: @input', ['@input' => print_r($input, 1)]);
      }

      // Event support.
      if ($this->config->get('event_create_node') && !empty($input['start']) && !empty($input['end']) && !empty($input['name']) && (!empty($input['h']) && $input['h'] == 'event') && $valid_token) {

        $values = [
          'uid' => $this->config->get('event_uid'),
          'title' => $input['name'],
          'type' => $this->config->get('event_node_type'),
          'status' => $this->config->get('event_status'),
        ];

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Content.
        $content_field_name = $this->config->get('event_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // Date.
        $date_field_name = $this->config->get('event_date_field');
        if ($date_field_name && $node->hasField($date_field_name)) {
          $node->set($date_field_name, ['value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, strtotime($input['start'])), 'end_value' => gmdate(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, strtotime($input['end']))]);
        }

        // Categories.
        $this->handleCategories($input, $node, 'event_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // RSVP support.
      if ($this->config->get('rsvp_create_node') && !empty($input['in-reply-to']) && !empty($input['rsvp']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('rsvp_uid'),
          'title' => 'RSVP on ' . $input['in-reply-to'],
          'type' => $this->config->get('rsvp_node_type'),
          'status' => $this->config->get('rsvp_status'),
        ];

        // Add url to syndicate to.
        if (isset($input['mp-syndicate-to'])) {
          $input['mp-syndicate-to'][] = $input['in-reply-to'];
        }
        else {
          $input['mp-syndicate-to'] = [$input['in-reply-to']];
        }

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $rsvp_link_field = $this->config->get('rsvp_link_field');
        $node->set($rsvp_link_field, ['uri' => $input['in-reply-to'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('rsvp_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // RSVP.
        $rsvp_field_name = $this->config->get('rsvp_rsvp_field');
        if ($rsvp_field_name && $node->hasField($rsvp_field_name)) {
          $node->set($rsvp_field_name, $input['rsvp']);
        }

        // Categories.
        $this->handleCategories($input, $node, 'rsvp_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Repost support.
      if ($this->config->get('repost_create_node') && !empty($input['repost-of']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('repost_uid'),
          'title' => 'Repost of ' . $input['repost-of'],
          'type' => $this->config->get('repost_node_type'),
          'status' => $this->config->get('repost_status'),
        ];

        // Add url to syndicate to.
        if (isset($input['mp-syndicate-to'])) {
          $input['mp-syndicate-to'][] = $input['repost-of'];
        }
        else {
          $input['mp-syndicate-to'] = [$input['repost-of']];
        }

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $repost_link_field = $this->config->get('repost_link_field');
        $node->set($repost_link_field, ['uri' => $input['repost-of'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('repost_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // Categories.
        $this->handleCategories($input, $node, 'repost_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Bookmark support.
      if ($this->config->get('bookmark_create_node') && !empty($input['bookmark-of']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('bookmark_uid'),
          'title' => 'Bookmark of ' . $input['bookmark-of'],
          'type' => $this->config->get('bookmark_node_type'),
          'status' => $this->config->get('bookmark_status'),
        ];

        if (!empty($input['name'])) {
          $values['title'] = $input['name'];
        }

        // Add url to syndicate to.
        if (isset($input['mp-syndicate-to'])) {
          $input['mp-syndicate-to'][] = $input['bookmark-of'];
        }
        else {
          $input['mp-syndicate-to'] = [$input['bookmark-of']];
        }

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $bookmark_link_field = $this->config->get('bookmark_link_field');
        $node->set($bookmark_link_field, ['uri' => $input['bookmark-of'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('bookmark_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // Categories.
        $this->handleCategories($input, $node, 'bookmark_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Like support.
      if ($this->config->get('like_create_node') && !empty($input['like-of']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('like_uid'),
          'title' => 'Like of ' . $input['like-of'],
          'type' => $this->config->get('like_node_type'),
          'status' => $this->config->get('like_status'),
        ];

        // Add url to syndicate to.
        if (isset($input['mp-syndicate-to'])) {
          $input['mp-syndicate-to'][] = $input['like-of'];
        }
        else {
          $input['mp-syndicate-to'] = [$input['like-of']];
        }

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $like_link_field = $this->config->get('like_link_field');
        $node->set($like_link_field, ['uri' => $input['like-of'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('like_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // Categories.
        $this->handleCategories($input, $node, 'like_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Reply support.
      if ($this->config->get('reply_create_node') && !empty($input['in-reply-to']) && !empty($input['content']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('reply_uid'),
          'title' => 'In reply to ' . $input['in-reply-to'],
          'type' => $this->config->get('reply_node_type'),
          'status' => $this->config->get('reply_status'),
        ];

        // Add url to syndicate to.
        if (isset($input['mp-syndicate-to'])) {
          $input['mp-syndicate-to'][] = $input['in-reply-to'];
        }
        else {
          $input['mp-syndicate-to'] = [$input['in-reply-to']];
        }

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $reply_link_field = $this->config->get('reply_link_field');
        $node->set($reply_link_field, ['uri' => $input['in-reply-to'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('reply_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // Categories.
        $this->handleCategories($input, $node, 'reply_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Note support.
      if ($this->config->get('note_create_node') && !empty($input['content']) && !isset($input['name']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('note_uid'),
          'title' => 'Micropub post',
          'type' => $this->config->get('note_node_type'),
          'status' => $this->config->get('note_status'),
        ];

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Content.
        $content_field_name = $this->config->get('note_content_field');
        if (!empty($input['content']) && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // File (currently only image, limited to 1).
        $file_field_name = $this->config->get('note_upload_field');
        if ($file_field_name && $node->hasField($file_field_name)) {
          $file = $this->saveUpload('photo');
          if ($file) {
            $node->set($file_field_name, $file->id());
          }
        }

        // Categories.
        $this->handleCategories($input, $node, 'note_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

      // Article support.
      if ($this->config->get('article_create_node') && !empty($input['content']) && !empty($input['name']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('article_uid'),
          'title' => $input['name'],
          'type' => $this->config->get('article_node_type'),
          'status' => $this->config->get('article_status'),
        ];

        // Allow code to change the values.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Content.
        $content_field_name = $this->config->get('article_content_field');
        if (!empty($input['content']) && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

        // File (currently only image, limited to 1).
        $file_field_name = $this->config->get('article_upload_field');
        if ($file_field_name && $node->hasField($file_field_name)) {
          $file = $this->saveUpload('photo');
          if ($file) {
            $node->set($file_field_name, $file->id());
          }
        }

        // Categories.
        $this->handleCategories($input, $node, 'article_tags_field');

        $node->save();
        if ($node->id()) {

          // Syndicate.
          $this->syndicateTo($input, $node);

          // Allow code to react after the node is saved.
          \Drupal::moduleHandler()->invokeAll('indieweb_micropub_node_saved', [$node, $values, $input, $payload_original]);

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }
      }

    }

    return new JsonResponse($response_message, $response_code);
  }

  /**
   * Check if there's a valid access token in the request.
   *
   * @param $input
   *   The input.
   *
   * @return bool
   */
  protected function isValidToken($input = []) {
    $valid_token = FALSE;

    $auth = NULL;
    $auth_header = \Drupal::request()->headers->get('Authorization');
    if ($auth_header && preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
      $auth = $auth_header;
    }
    elseif (!empty($input['access_token'])) {
      $auth = 'Bearer ' . $input['access_token'];
    }

    if ($auth) {
      try {
        $client = \Drupal::httpClient();
        $headers = [
          'Accept' => 'application/json',
          'Authorization' => $auth,
        ];

        $response = $client->get(\Drupal::config('indieweb.indieauth')->get('token_endpoint'), ['headers' => $headers]);
        $json = json_decode($response->getBody());
        if (isset($json->me) && $json->me == $this->config->get('micropub_me')) {
          $valid_token = TRUE;
        }
      }
      catch (\Exception $e) {
        $this->getLogger('indieweb_micropub')->notice('Error validating the access token: @message', ['@message' => $e->getMessage()]);
      }
    }

    return $valid_token;
  }

  /**
   * Helper function to upload file(s).
   * Currently limited to 1 file.
   *
   * @param $file_key
   *   The key in the $_FILES variable to look for in upload.
   *
   * @return \Drupal\file\FileInterface $file|false.
   */
  protected function saveUpload($file_key) {
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
      $file = file_save_upload($file_key, array(), "public://", 0);
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
   * Handle and set categories.
   *
   * @param $input
   *   The input from the request.
   * @param \Drupal\node\NodeInterface $node
   *   The current node which is going to be created.
   * @param $config_key
   *   The config key for the tags field.
   */
  protected function handleCategories($input, NodeInterface $node, $config_key) {
    $tags_field_name = $this->config->get($config_key);

    if ($tags_field_name && $node->hasField($tags_field_name) && !empty($input['category'])) {
      $values = $bundles = [];
      $auto_create = FALSE;

      // Check field definition settings of the field.
      $field_settings = $node->getFieldDefinition($tags_field_name)->getSettings();
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
          ->condition('name', $input['category'], 'IN')
          ->execute();

        // Get the actual terms.
        if (!empty($existing_category_ids)) {
          $existing_categories_loaded = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($existing_category_ids);
          foreach ($existing_categories_loaded as $loaded_term) {
            $existing_categories[$loaded_term->label()] = $loaded_term;
          }
        }

        // Now loop over incoming categories.
        foreach ($input['category'] as $category) {

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
          $node->set($tags_field_name, $values);
        }
      }
    }
  }

  /**
   * Syndicate to.
   *
   * @param $input
   * @param \Drupal\node\NodeInterface $node
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function syndicateTo($input, NodeInterface $node) {
    if (!empty($input['mp-syndicate-to'])) {
      $source = $node->toUrl()->setAbsolute(TRUE)->toString();
      foreach ($input['mp-syndicate-to'] as $target) {
        indieweb_webmention_create_queue_item($source, $target, $node->id(), 'node');
      }
    }
  }

}

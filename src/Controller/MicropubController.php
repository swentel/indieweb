<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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
      $valid_token = $this->isValidToken();

      // Like support.
      if ($this->config->get('like_create_node') && !empty($input['like-of']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        $values = [
          'uid' => $this->config->get('like_uid'),
          'title' => 'Like of ' . $input['like-of'],
          'type' => $this->config->get('like_node_type'),
          'status' => $this->config->get('like_status'),
        ];

        // Allow code to change the values and payload.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values, $input);

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        // Link field.
        $like_link_field = $this->config->get('like_link_field');
        $node->set($like_link_field, ['uri' => $input['like-of'], 'title' => '']);

        // Content.
        $content_field_name = $this->config->get('note_content_field');
        if (!empty($input['content']) && $content_field_name && $node->hasField($content_field_name)) {
          $node->set($content_field_name, $input['content']);
        }

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

        if ($this->config->get('micropub_log_payload')) {
          $this->getLogger('micropub_log_payload')->notice('input: @input', ['@input' => print_r($input, 1)]);
        }

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

        if ($this->config->get('micropub_log_payload')) {
          $this->getLogger('micropub_log_payload')->notice('input: @input', ['@input' => print_r($input, 1)]);
        }

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
   * @return bool
   */
  protected function isValidToken() {
    $valid_token = FALSE;

    // Start storing this, see https://github.com/swentel/indieweb/issues/79
    $auth = \Drupal::request()->headers->get('Authorization');
    if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $matches)) {

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
   * Syndicate to other channels.
   *
   * @param $input
   * @param \Drupal\node\NodeInterface $node
   */
  protected function syndicateTo($input, NodeInterface $node) {
    if (!empty($input['mp-syndicate-to'])) {
      $source_url = $node->toUrl()->setAbsolute(TRUE)->toString();
      foreach ($input['mp-syndicate-to'] as $target_url) {
        indieweb_webmention_create_queue_item($source_url, $target_url, $node->id(), 'node');
      }
    }
  }

}

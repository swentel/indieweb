<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MicropubController extends ControllerBase {

  // TODO inject
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
    // TODO use request
    if (!empty($_POST)) {
      $input = $_POST;
    }
    if (!empty($input)) {

      $valid_token = $this->isValidToken();

      // Note support.
      if ($this->config->get('note_create_node') && !empty($input['content']) && !isset($input['name']) && (!empty($input['h']) && $input['h'] == 'entry') && $valid_token) {

        if ($this->config->get('micropub_log_payload')) {
          $this->getLogger('indieweb_micropub')->notice('input: @input', ['@input' => print_r($input, 1)]);
        }

        $values = [
          'uid' => $this->config->get('note_uid'),
          'title' => 'Micropub post',
          'type' => $this->config->get('note_node_type'),
          'status' => 1,
          // Add complete payload on node, so developers can act on it.
          // e.g. on hook_micropub_node_pre_create_alter().
          'micropub_payload' => $input,
        ];

        // Allow code to change the values.
        \Drupal::moduleHandler()->alter('indieweb_micropub_node_pre_create', $values);

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
          if (!empty($input['mp-syndicate-to'])) {
            $source_url = $node->toUrl()->setAbsolute(TRUE)->toString();
            foreach ($input['mp-syndicate-to'] as $target_url) {
              indieweb_publish_create_queue_item($source_url, $target_url);
            }
          }

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

    // TODO we can probably store this token so we don't have to talk
    // to indieauth all the time.
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

}

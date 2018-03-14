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
          'is_micropub_post' => TRUE,
        ];

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        $field_name = $this->config->get('note_content_field');
        if ($node->hasField($field_name)) {
          $node->set($field_name, $input['content']);
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

}

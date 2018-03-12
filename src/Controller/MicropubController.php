<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MicropubController extends ControllerBase {

  /**
   * Routing callback: receive micropub posts.
   */
  public function endpoint() {

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    $input = NULL;

    // TODO use request
    if (!empty($_POST)) {
      $input = $_POST;
    }

    if (Settings::get('indieweb_allow_micropub_posts', FALSE) && !empty($input)) {

      $valid_token = FALSE;

      $auth = \Drupal::request()->headers->get('Authorization');
      if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $matches)) {

        // TODO we can probably store this token so we don't have to talk
        // to indieauth all the time, should check with Aaron

        $client = \Drupal::httpClient();
        $headers = [
          'Accept' => 'application/json',
          'Authorization' => $auth,
        ];
        // TODO access token check should use a configurable authentication
        // endpoint
        $response = $client->get('https://tokens.indieauth.com/token', ['headers' => $headers]);
        $json = json_decode($response->getBody());
        if (isset($json->me) && $json->me == Settings::get('indieweb_micropub_me', '')) {
          $valid_token = TRUE;
        }
      }

      if (Settings::get('indieweb_micropub_log_payload', FALSE)) {
        $this->getLogger('micropub')->notice('input: @input', ['@input' => print_r($input, 1)]);
      }

      // TODO validate on 'h-entry' type.
      if (!empty($input['content']) && $valid_token) {

        $values = [
          'uid' => Settings::get('indieweb_micropub_uid', 1),
          'title' => 'Micropub post',
          'type' => Settings::get('indieweb_micropub_node_type', 'note'),
          'status' => 1,
          'is_micropub_post' => TRUE,
        ];

        /** @var \Drupal\node\NodeInterface $node */
        $node = Node::create($values);

        $field_name = Settings::get('indieweb_micropub_content_field', 'body');
        if ($node->hasField($field_name)) {
          $node->set($field_name, $input['content']);
        }

        $node->save();
        if ($node->id()) {

          // TODO Don't make this hardcoded of course
          if (Settings::get('indieweb_micropub_send_webmention', FALSE)) {
            $sourceURL = $node->toUrl()->setAbsolute(TRUE)->toString();
            \Drupal::database()->insert('queue')
              ->fields([
                'name' => 'publish_bridgy',
                'data' => serialize(['url' => $sourceURL])
              ])
              ->execute();
          }

          $response_code = 201;
          $response_message = '';
          header('Location: ' . $node->toUrl('canonical', ['absolute' => TRUE])->toString());
          return new Response($response_message, $response_code);
        }

      }

    }

    // TODO doesn't have to be a json response
    $response = ['result' => $response_message];
    return new JsonResponse($response, $response_code);
  }

}

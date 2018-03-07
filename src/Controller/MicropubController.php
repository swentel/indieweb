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

    if (Settings::get('indieweb_micropub_log_payload', FALSE)) {
      $this->getLogger('micropub')->notice('input: @input', ['@input' => print_r($input, 1)]);
    }

    // TODO verify header token.
    if (Settings::get('indieweb_allow_micropub_posts', FALSE)) {

      if (!empty($input['content'])) {

        $values = [
          'uid' => 1,
          'title' => 'Micropub post',
          'type' => 'note',
          'body' => [
            'value' => $input['content'],
          ],
          'status' => 1,
        ];
        $node = Node::create($values);
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

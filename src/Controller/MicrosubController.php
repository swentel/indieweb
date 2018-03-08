<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class MicrosubController extends ControllerBase {

  /**
   * Main microsub endpoint
   */
  public function endpoint() {

    // Default response code and message.
    $response = [];
    $response_code = 200;

    if (isset($_GET['action']) && $_GET['action'] == 'timeline') {
      $response = [
        'items' => [
          [
            'type' => 'entry',
            'published' => '2017-04-28T11:58:35-07:00',
            'url' => 'http://realize.be/notes',
            'author' => [
              'type' => 'card',
              'name' => 'swentel',
              'url' => 'https://realize.be',
            ],
            'content' => [
              'text' => 'some nice content',
              'html' => '<p>some nice content</p>',
            ],
          ]
        ],
      ];
    }

    if (isset($_GET['action']) && $_GET['action'] == 'channels') {
      $response = [
        'channels' => [
          [
            'uid' => 'notifications',
            'name' => 'Notifications',
          ],
          [
            'uid' => 'swentel_blog',
            'name' => 'Swentel blog',
          ],
          [
            'uid' => 'swentel_notes',
            'name' => 'Swentel notes',
          ],
        ],
      ];
    }

    // TODO doesn't have to be a json response
    return new JsonResponse($response, $response_code);
  }

}

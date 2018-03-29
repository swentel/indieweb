<?php

namespace Drupal\indieweb_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class IndiewebTestController extends ControllerBase {

  /**
   * Indieweb test front page.
   *
   * @return array
   */
  public function front() {
    return ['#markup' => 'Indieweb test front'];
  }

  /**
   * Indieweb test IndieAuth token endpoint
   */
  public function testTokenEndpoint() {
    $data = [];
    $status = 400;

    $auth = \Drupal::request()->headers->get('Authorization');
    if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $matches) && strpos($auth, 'this_is_a_valid_token') !== FALSE) {
      $status = 200;
      $data['me'] = 'https://indieweb.micropub.testdomain';
    }

    return new JsonResponse($data, $status);
  }

}

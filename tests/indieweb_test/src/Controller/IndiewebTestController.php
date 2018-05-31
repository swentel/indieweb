<?php

namespace Drupal\indieweb_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class IndiewebTestController extends ControllerBase {

  /**
   * IndieWeb test front page.
   *
   * @return array
   */
  public function front() {
    return ['#markup' => 'Indieweb test front'];
  }

  /**
   * IndieWeb test webmention endpoint.
   *
   * Also acts as publish endpoint, so we'll always send 201 back.
   *
   * @see \Drupal\Tests\indieweb\Functional\WebmentionTest::testSendingWebmention();
   */
  public function testWebmentionEndpoint() {
    header('Location: ' . Url::fromRoute('entity.node.canonical', ['node' => '1'], ['absolute' => TRUE])->toString());
    return new Response('', 201);
  }

  /**
   * IndieWeb test IndieAuth token endpoint
   */
  public function testTokenEndpoint() {
    $data = [];
    $status = 400;

    $auth = \Drupal::request()->headers->get('Authorization');
    if ($auth && preg_match('/Bearer\s(\S+)/', $auth, $matches) && strpos($auth, 'is_valid') !== FALSE) {
      $status = 200;
      $data['me'] = 'https://indieweb.micropub.testdomain';
    }

    return new JsonResponse($data, $status);
  }

  /**
   * IndieWeb test IndieAuth login endpoint.
   */
  public function testLoginEndPoint() {

    // Redirect with code.
    if (!empty($_GET['state']) && !empty($_GET['redirect_uri']) && !empty($_GET['client_id'])) {
      return new RedirectResponse($_GET['redirect_uri'] . '?state=' . $_GET['state'] . '&client_id=' . $_GET['client_id'] . '&code=1234');
    }

    // Verify code request.
    if (!empty($_POST['code'])) {

      if ($_POST['code'] == '1234') {
        return new JsonResponse(['me' => 'https://example.com/'], 200);
      }
      else {
        return new JsonResponse(['error' => 'Invalid request', 'error_description' => 'The code was not valid.'], 400);
      }
    }

    return new Response('Page not found', 404);
  }

}

<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebmentionController extends ControllerBase {

  /**
   * Routing callback: receive webmentions and pingbacks.
   */
  public function endpoint() {
    $valid = FALSE;

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    // Check if there's any input from the webhook.
    $input = file('php://input');
    $input = is_array($input) ? array_shift($input) : '';
    $mention = json_decode($input, TRUE);

    // Check if this is a forward pingback, which is a POST request.
    if (empty($mention) && (!empty($_POST['source']) && !empty($_POST['target']))) {
      if ($this->validateSource($_POST['source'], $_POST['target'])) {
        $valid = TRUE;
        $mention = [];
        $mention['source'] = $_POST['source'];
        $mention['post'] = [];
        $mention['post']['type'] = 'pingback';
        $mention['post']['wm-property'] = 'pingback';
        $mention['target'] = $_POST['target'];
      }
    }
    else {
      $secret = Settings::get('indieweb_webmention_io_secret', '');
      if (!empty($mention['secret']) && $mention['secret'] == $secret) {
        $valid = TRUE;
      }
    }

    // Debug.
    if (Settings::get('indieweb_webmention_log_payload', FALSE)) {
      $this->getLogger('indieweb')->notice('input: @input', ['@input' => print_r($input, 1)]);
    }

    // We have a valid mention.
    if (!empty($mention) && $valid) {

      // Debug.
      if (Settings::get('indieweb_webmention_log_payload', FALSE)) {
        $this->getLogger('indieweb')->notice('object: @object', ['@object' => print_r($mention, 1)]);
      }

      $response_code = 202;
      $response_message = 'Webmention was successful';

      $values = [
        'user_id' => Settings::get('indieweb_webmention_uid', 1),
        // Remove the base url.
        'target' => ['value' => str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $mention['target'])],
        'source' => ['value' => $mention['source']],
        'type' => ['value' => $mention['post']['type']],
        'property' => ['value' => $mention['post']['wm-property']]
      ];

      // Set created to published or wm-received if available.
      if (!empty($mention['post']['wm-received'])) {
        $values['created'] = strtotime($mention['post']['wm-received']);
      }
      elseif (!empty($mention['post']['published'])) {
        $values['created'] = strtotime($mention['post']['published']);
      }

      // Author info.
      foreach (['name', 'photo', 'url'] as $key) {
        if (!empty($mention['post']['author'][$key])) {
          $values['author_' . $key] = ['value' => $mention['post']['author'][$key]];
        }
      }

      // Text content.
      foreach (['html', 'text'] as $key) {
        if (!empty($mention['post']['content'][$key])) {
          $values['content_' . $key] = ['value' => $mention['post']['content'][$key]];
        }
      }

      // Private or not.
      if (!empty($mention['post']['wm-private'])) {
        $values['private'] = ['value' => TRUE];
      }

      // Save the entity.
      $webmention = $this->entityTypeManager()->getStorage('webmention_entity')->create($values);
      $webmention->save();

      // Clear cache.
      $this->clearCache($values['target']['value']);
    }

    $response = ['result' => $response_message];
    return new JsonResponse($response, $response_code);
  }

  /**
   * Validates that target is linked on source.
   *
   * @param $source
   * @param $target
   *
   * @return bool
   */
  protected function validateSource($source, $target) {
    $valid = FALSE;

    $content = file_get_contents($source, $target);
    if ($content && strpos($content, $target) !== FALSE) {
      $valid = TRUE;
    }

    return $valid;
  }

  /**
   * Clears the cache for the target URL.
   *
   * @param $target
   */
  protected function clearCache($target) {

    $path = \Drupal::service('path.alias_manager')->getPathByAlias($target);
    try {
      $params = Url::fromUri("internal:" . $path)->getRouteParameters();
      if (!empty($params)) {
        $entity_type = key($params);

        $storage = $this->entityTypeManager()->getStorage($entity_type);
        if ($storage) {
          /** @var \Drupal\Core\Entity\EntityInterface $entity */
          $entity = $storage->load($params[$entity_type]);
          if ($entity) {
            $storage->resetCache([$entity->id()]);
            Cache::invalidateTags([$entity_type . ':' . $entity->id()]);
          }
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('indieweb')->notice('Error clearing cache for @target: @message', ['@target' => $target, '@message' => $e->getMessage()]);
    }
  }


}

<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;

class WebmentionController extends ControllerBase {

  /**
   * Routing callback: Webmention send list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function sendAdminOverview() {
    $build = $header = $rows = [];

    $header = [
      $this->t('Source'),
      $this->t('Target'),
      $this->t('Send'),
    ];

    $limit = 30;
    /** @noinspection PhpUndefinedMethodInspection */
    $select = \Drupal::database()->select('webmention_send', 's')
      ->fields('s')
      ->orderBy('id', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($limit);

    /** @noinspection PhpUndefinedMethodInspection */
    $records = $select->execute();
    foreach ($records as $record) {
      $row = [];

      // Source.
      if (!empty($record->entity_id) && !empty($record->entity_type_id)) {
        $entity = $this->entityTypeManager()->getStorage($record->entity_type_id)->load($record->entity_id);
        if ($entity) {
          $row[] = ['data' => ['#markup' => Link::fromTextAndUrl($entity->label(), $entity->toUrl())->toString() . ' (' . $entity->id() . ')']];
        }
        else {
          $row[] = $this->t('Unknown entity: @id (@type)', ['@id' => $record->entity_id, '@type' => $record->entity_type_id]);
        }
      }
      else {
        $row[] = $record->source;
      }

      // Target.
      try {
        $row[] = Link::fromTextAndUrl($record->target, Url::fromUri($record->target, ['external' => TRUE, 'attributes' => ['target' => '_blank']]))->toString();
      }
      catch (\Exception $ignored) {
        $row[] = $record->target;
      }

      // Created.
      $row[] = \Drupal::service('date.formatter')->format($record->created, 'medium');

      // Add to rows.
      $rows[] = $row;
    }

    $build['queue'] = [
      '#markup' => '<p>' . $this->t('Items in queue: @count', ['@count' => \Drupal::queue(WEBMENTION_QUEUE_NAME)->numberOfItems()]) . '</p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No send webmentions found'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Routing callback: receive webmentions and pingbacks.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function endpoint() {
    $valid = FALSE;

    $config = \Drupal::config('indieweb.webmention');
    $webmentions_enabled = $config->get('webmention_enable');
    $pingbacks_enabled = $config->get('pingback_enable');

    // Early return when nothing is enabled.
    if (!$webmentions_enabled && !$pingbacks_enabled) {
      return new JsonResponse('', 404);
    }

    // Default response code and message.
    $response_code = 400;
    $response_message = 'Bad request';

    // Check if there's any input from the webhook.
    $input = file('php://input');
    $input = is_array($input) ? array_shift($input) : '';
    $mention = json_decode($input, TRUE);

    // Check if this is a forward pingback, which is a POST request.
    if (empty($mention) && $pingbacks_enabled && (!empty($_POST['source']) && !empty($_POST['target']))) {
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
    elseif ($webmentions_enabled) {
      $secret = $config->get('webmention_secret');
      if (!empty($mention['secret']) && $mention['secret'] == $secret) {
        $valid = TRUE;
      }
    }

    // We have a valid mention.
    if (!empty($mention) && $valid) {

      // Debug.
      if ($config->get('webmention_log_payload')) {
        $this->getLogger('indieweb_webmention_payload')->notice('object: @object', ['@object' => print_r($mention, 1)]);
      }

      // Remove the base url
      $target = str_replace(\Drupal::request()->getSchemeAndHttpHost(), '', $mention['target']);

      // Check identical webmentions. If the source, target and property are the
      // same, ignore it.
      if ($config->get('webmention_detect_identical')) {
        $source = $mention['source'];
        $property = $mention['post']['wm-property'];
        $exists = \Drupal::database()->query("SELECT id FROM {webmention_entity} WHERE source = :source AND target = :target AND property = :property ORDER by id DESC limit 1", [':source' => $source, ':target' => $target, ':property' => $property])->fetchField();
        if ($exists) {
          $this->getLogger('indieweb_webmention_identical')->notice('Source @source, target @target and @property already exists.', ['@source' => $source, '@target' => $target, '@property' => $property]);
          $response = ['result' => $response_message];
          return new JsonResponse($response, $response_code);
        }
      }

      $response_code = 202;
      $response_message = 'Webmention was successful';

      $values = [
        'user_id' => $config->get('webmention_uid'),
        'target' => ['value' => $target],
        'source' => ['value' => $mention['source']],
        'type' => ['value' => $mention['post']['type']],
        'property' => ['value' => $mention['post']['wm-property']]
      ];

      // Set created to published or wm-received if available.
      if (!empty($mention['post']['published'])) {
        $values['created'] = strtotime($mention['post']['published']);
      }
      elseif (!empty($mention['post']['wm-received'])) {
        $values['created'] = strtotime($mention['post']['wm-received']);
      }

      // Author info.
      foreach (['name', 'photo', 'url'] as $key) {
        if (!empty($mention['post']['author'][$key])) {
          $author_value = trim($mention['post']['author'][$key]);
          if (!empty($author_value)) {
            $values['author_' . $key] = ['value' => $author_value];
          }
        }
      }

      // Url.
      if (!empty($mention['post']['url'])) {
        $values['url'] = ['value' => $mention['post']['url']];
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

      // Rsvp.
      if (!empty($mention['post']['rsvp'])) {
        $values['rsvp'] = ['value' => $mention['post']['rsvp']];
      }

      // Save the entity.
      try {
        /** @var \Drupal\indieweb\Entity\WebmentionInterface $webmention */
        $webmention = $this->entityTypeManager()->getStorage('webmention_entity')->create($values);
        $webmention->save();
      }
      catch (\Exception $ignored) {}

      // Send micropub request to Aperture if configured.
      if (isset($webmention)) {
        $microsub = \Drupal::config('indieweb.microsub');
        if ($microsub->get('aperture_enable_micropub') && !empty($microsub->get('aperture_api_key'))) {
          /** @var \Drupal\indieweb\ApertureClient\ApertureClientInterface $client */
          $client = \Drupal::service('indieweb.aperture.client');
          $client->sendPost($microsub->get('aperture_api_key'), $webmention);
        }
      }

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

    try {
      $client = \Drupal::httpClient();
      $response = $client->get($source);
      $content = $response->getBody();
      if ($content && strpos($content, $target) !== FALSE) {
        $valid = TRUE;
      }
    }
    catch (\Exception $e) {
      $this->getLogger('indieweb_webmention')->notice('Error validating pingback url: @message', ['@message' => $e->getMessage()]);
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
      $this->getLogger('indieweb_webmention')->notice('Error clearing cache for @target: @message', ['@target' => $target, '@message' => $e->getMessage()]);
    }
  }

}

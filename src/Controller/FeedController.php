<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\indieweb\Entity\FeedInterface;
use Exception;
use p3k\XRay;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

class FeedController extends ControllerBase {

  /**
   * Routing callback: update items for a feed.
   *
   * @param \Drupal\indieweb\Entity\FeedInterface $indieweb_feed
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   */
  public function updateItems(FeedInterface $indieweb_feed) {
    indieweb_update_feed_items($indieweb_feed);
    drupal_set_message($this->t('Updated items for %feed', ['%feed' => $indieweb_feed->label()]));
    return new RedirectResponse(Url::fromRoute('entity.indieweb_feed.collection')->toString());
  }

  /**
   * Routing callback: returns a microformat feed.
   *
   * @param \Drupal\indieweb\Entity\FeedInterface $indieweb_feed
   *
   * @return array|\Symfony\Component\HttpFoundation\Response
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function feedMicroformat(FeedInterface $indieweb_feed) {
    $build = [];

    $build['#title'] = $indieweb_feed->label();

    // Author info.
    if ($indieweb_feed->getAuthor()) {
      $build['author'] = [
        '#markup' => '<div class="h-card author-information hidden">' .
          $indieweb_feed->getAuthor() .
          '</div>',
        '#allowed_tags' => ['a', 'img', 'div', 'span'],
      ];
    }

    $items = [];
    $query = \Drupal::database()
      ->select('indieweb_feed_items', 'ifi')
      ->extend('\Drupal\Core\Database\Query\PagerSelectExtender');
    $query->fields('ifi');
    $query->condition('feed', $indieweb_feed->id());
    $query->condition('published', 1);
    $records = $query
      ->limit($indieweb_feed->getLimit())
      ->orderBy('timestamp', 'DESC')
      ->execute();


    foreach ($records as $record) {
      $entity = \Drupal::entityTypeManager()->getStorage($record->entity_type_id)->load($record->entity_id);
      if ($entity) {
        try {
          $items[] = \Drupal::entityTypeManager()->getViewBuilder($record->entity_type_id)->view($entity, 'indieweb_microformat');
        }
        catch (Exception $ignored) {}
      }
    }

    if (empty($items)) {
      $build['info']['#markup'] = '<p>' . $this->t('No items found') . '</p>';
    }
    else {

      $build['wrapper'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['h-feed'],
        ],
      ];

      $build['wrapper']['items'] = $items;

      $build['pager'] = [
        '#type' => 'pager',
      ];
    }

    $build['#cache']['tags'][] = 'indieweb_feed:' . $indieweb_feed->id();

    return $build;
  }

  /**
   * Routing callback: returns a jf2 feed.
   *
   * @param \Drupal\indieweb\Entity\FeedInterface $indieweb_feed
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function feedJf2(FeedInterface $indieweb_feed) {
    $data = [];

    $path = Url::fromUri('internal:/' . $indieweb_feed->getPath(), ['absolute' => TRUE])->toString();
    $client = \Drupal::httpClient();
    try {
      $response = $client->get($path);
      $body = $response->getBody()->getContents();
      $xray = new XRay();
      $data = $xray->parse($path, $body, ['expect' => 'feed']);
    }
    catch (Exception $ignored) {}

    return JsonResponse::create($data);
  }

}

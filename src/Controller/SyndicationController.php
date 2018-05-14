<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class SyndicationController extends ControllerBase {

  /**
   * Routing callback: list of syndications.
   */
  public function adminOverview() {
    $build = $header = $rows = [];

    $header = [
      $this->t('Source'),
      $this->t('Syndication link'),
    ];

    $limit = 30;

    $select = \Drupal::database()->select('webmention_syndication', 's')
      ->fields('s')
      ->orderBy('id', 'DESC')
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit($limit);

    $records = $select->execute();
    foreach ($records as $record) {
      $row = [];

      // Source.
      $entity = $this->entityTypeManager()->getStorage($record->entity_type_id)->load($record->entity_id);
      if ($entity) {
        $row[] = ['data' => ['#markup' => Link::fromTextAndUrl($entity->label(), $entity->toUrl())->toString() . ' (' . $entity->id() . ')']];
      }
      else {
        $row[] = $this->t('Unknown entity: @id (@type)', ['@id' => $record->entity_id, '@type' => $record->entity_type_id]);
      }

      // Syndication link.
      try {
        $row[] = Link::fromTextAndUrl($record->url, Url::fromUri($record->url, ['external' => TRUE, 'attributes' => ['target' => '_blank']]))->toString();
      }
      catch (\Exception $ignored) {
        $row[] = $record->url;
      }

      // Add to rows.
      $rows[] = $row;
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No syndications available'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

}

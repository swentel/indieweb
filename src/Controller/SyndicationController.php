<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;

class SyndicationController extends ControllerBase {

  /**
   * Routing callback: list of syndications.
   */
  public function adminOverview() {
    $build = $header = $rows = [];

    $header = [
      $this->t('Source'),
      $this->t('Target'),
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

      $entity = $this->entityTypeManager()->getStorage($record->entity_type_id)->load($record->entity_id);
      if ($entity) {
        $row[] = ['data' => ['#markup' => Link::fromTextAndUrl($entity->label(), $entity->toUrl())->toString() . ' (' . $entity->id() . ')']];
        $row[] = $record->url;
      }

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

<?php

namespace Drupal\indieweb_feed\Entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Feed entities.
 */
class FeedListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Feed');
    $header['path'] = $this->t('Path');
    $header['items'] = $this->t('Number of items');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['path'] = $entity->getPath();
    $row['items'] = \Drupal::entityTypeManager()->getStorage('indieweb_feed_item')->getItemCount($entity->id());
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['#title'] = $this->t('Feeds');

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = parent::buildOperations($entity);
    $operations['#links']['update_items'] =  [
      'title' => $this->t('Update items'),
      'weight' => 10,
      'url' => $this->ensureDestination($entity->toUrl('update-items')),
    ];

    return $operations;
  }

}

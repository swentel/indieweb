<?php

namespace Drupal\indieweb;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Webmention entities.
 *
 * @ingroup webmention
 */
class WebmentionEntityListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['source'] = $this->t('Source');
    $header['target'] = $this->t('Target');
    $header['type'] = $this->t('Type');
    $header['property'] = $this->t('Property');
    $header['author'] = $this->t('Author');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\indieweb\Entity\indiewebEntity */
    $row['source'] = $entity->get('source')->value;
    $row['target'] = $entity->get('target')->value;
    $row['type'] = $entity->get('type')->value;
    $row['property'] = $entity->get('property')->value;
    $row['author'] = $entity->get('author_name')->value ?: '/' ;
    $row['created'] = \Drupal::service('date.formatter')->format($entity->getCreatedTime());
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityIds() {
    // Sort descending default.
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('id'), 'DESC');

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

}

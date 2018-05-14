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
class WebmentionListBuilder extends EntityListBuilder {

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
    /* @var $entity \Drupal\indieweb\Entity\WebmentionInterface */
    $row['source'] = $entity->get('source')->value;
    $row['target'] = ['data' => ['#markup' => '<a href="' . \Drupal::request()->getSchemeAndHttpHost() . $entity->get('target')->value . '">' . $entity->get('target')->value . '</a>', '#allowed_tags' => ['a']]];
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

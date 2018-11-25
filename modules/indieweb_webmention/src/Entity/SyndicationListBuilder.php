<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Syndication entities.
 */
class SyndicationListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['source'] = $this->t('Source');
    $header['syndication'] = $this->t('Target');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\indieweb_webmention\Entity\SyndicationInterface */
    $entity_id = $entity->getSourceEntityId();
    $entity_type_id = $entity->getSourceEntityTypeId();
    $url = $entity->getUrl();

    // Source.
    $source_entity = NULL;
    try {
      $source_entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    }
    catch (\Exception $ignored) {}
    if ($source_entity) {
      $row[] = ['data' => ['#markup' => Link::fromTextAndUrl($source_entity->label(), $source_entity->toUrl())->toString() . ' (' . $source_entity->id() . ')']];
    }
    else {
      $row[] = $this->t('Unknown entity: @id (@type)', ['@id' => $entity_id, '@type' => $entity_type_id]);
    }

    // Syndication link.
    try {
      $row[] = Link::fromTextAndUrl($url, Url::fromUri($url, ['external' => TRUE, 'attributes' => ['target' => '_blank']]))->toString();
    }
    catch (\Exception $ignored) {
      $row[] = $url;
    }

    // Return row.
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

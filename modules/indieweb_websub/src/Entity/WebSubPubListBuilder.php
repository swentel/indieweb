<?php

namespace Drupal\indieweb_websub\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of WebSubPub entities.
 */
class WebSubPubListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['queue'] = [
      '#markup' => '<p>' . $this->t('Items in queue: @count', ['@count' => \Drupal::queue(INDIEWEB_WEBSUB_QUEUE)->numberOfItems()]) . '</p>',
      '#weight' => -10,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['source'] = $this->t('Source');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\indieweb_websub\Entity\WebSubPubInterface */
    $entity_id = $entity->getSourceEntityId();
    $entity_type_id = $entity->getSourceEntityTypeId();

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

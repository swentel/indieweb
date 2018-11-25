<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of Webmention send entities.
 */
class SendListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['queue'] = [
      '#markup' => '<p>' . $this->t('Items in queue: @count', ['@count' => \Drupal::queue(INDIEWEB_WEBMENTION_QUEUE)->numberOfItems()]) . '</p>',
      '#weight' => -10,
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['source'] = $this->t('Source');
    $header['target'] = $this->t('Target');
    $header['created'] = $this->t('Created');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\indieweb_webmention\Entity\SendInterface */
    $entity_id = $entity->getSourceEntityId();
    $entity_type_id = $entity->getSourceEntityTypeId();
    $source = $entity->getSource();
    $target = $entity->getTarget();

    // Source.
    if (!empty($entity_id) && !empty($entity_type_id)) {

      $source_entity = NULL;
      try {
        $source_entity = \Drupal::entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
      }
      catch (\Exception $ignored) {}
      if ($source_entity) {
        $row['source'] = ['data' => ['#markup' => Link::fromTextAndUrl($source_entity->label(), $source_entity->toUrl())->toString() . ' (' . $source_entity->id() . ')']];
      }
      else {
        $row['source'] = $this->t('Unknown entity: @id (@type)', ['@id' => $entity_id, '@type' => $entity_type_id]);
      }
    }
    else {
      $row['source'] = $source;
    }

    // Target.
    try {
      $row['target'] = Link::fromTextAndUrl($target, Url::fromUri($target, ['external' => TRUE, 'attributes' => ['target' => '_blank']]))->toString();
    }
    catch (\Exception $ignored) {
      $row['target'] = $target;
    }

    // Created.
    $row['created'] = \Drupal::service('date.formatter')->format($entity->getCreatedTime(), 'medium');

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

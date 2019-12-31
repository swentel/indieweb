<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Webmention entities.
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
    /* @var $entity \Drupal\indieweb_webmention\Entity\WebmentionInterface */
    $row['source'] = $entity->getSource();
    $row['target'] = ['data' => ['#markup' => '<a href="' . \Drupal::request()->getSchemeAndHttpHost() . $entity->getTarget() . '">' . $entity->getTarget() . '</a>', '#allowed_tags' => ['a']]];
    $row['type'] = $entity->getType();
    $row['property'] = $entity->getProperty();
    $row['author'] = $entity->getAuthorName() ?: '/';
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

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    if (\Drupal::config('indieweb_webmention.settings')->get('webmention_internal') && \Drupal::currentUser()->hasPermission('reprocess webmention')) {
      $operations['reprocess'] = [
        'title' => $this->t('Reprocess'),
        'weight' => 20,
        'url' => $this->ensureDestination($entity->toUrl('reprocess')),
      ];
    }

    return $operations;
  }

}

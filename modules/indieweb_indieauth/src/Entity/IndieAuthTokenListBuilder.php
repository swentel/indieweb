<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of IndieAuth token entities.
 */
class IndieAuthTokenListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Token');
    $header['status'] = $this->t('Status');
    $header['client'] = $this->t('Client');
    $header['access'] = $this->t('Last access');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var $entity \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface */
    $row['label'] = $entity->label();
    $row['status'] = $entity->isRevoked() ? t('Revoked') : t('Active');
    $row['client_id'] = $entity->getClientId();
    $last_access = $entity->getChanged() ? \Drupal::service('date.formatter')->format($entity->getChanged(), 'short') : t('Never');
    $row['access'] = $last_access;
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = parent::buildOperations($entity);

    $operations['#links']['status'] =  [
      'title' => $entity->isRevoked() ? $this->t('Enable') : $this->t('Revoke'),
      'weight' => 10,
      'url' => $this->ensureDestination($entity->toUrl('change-status')),
    ];

    return $operations;
  }

}

<?php

namespace Drupal\indieweb;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\indieweb\Entity\MicrosubChannelInterface;

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
    /** @var \Drupal\indieweb\Entity\IndieAuthTokenInterface */
    $row['label'] = $entity->label();
    $row['status'] = $entity->get('status')->value ? t('Active') : t('Revoked');
    $row['client_id'] = $entity->get('client_id')->value;
    $last_access = $entity->get('changed')->value ? \Drupal::service('date.formatter')->format($entity->get('changed')->value, 'short') : t('Never');
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

<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of IndieAuth authorization code entities.
 */
class IndieAuthAuthorizationCodeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Code');
    $header['uid'] = $this->t('User id');
    $header['status'] = $this->t('Status');
    $header['expire'] = $this->t('Expires');
    $header['client'] = $this->t('Client');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var $entity \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface */
    $row['label'] = $entity->label();
    $row['uid'] = $entity->getOwnerId();
    $row['status'] = $entity->isRevoked() ? $this->t('Revoked') : $this->t('Active');
    $expires = $entity->getExpiretime() ? \Drupal::service('date.formatter')->format($entity->getExpiretime(), 'short') : t('Never');
    $row['expire'] = $expires;
    $row['client'] = $entity->getClientId();
    return $row + parent::buildRow($entity);
  }


}

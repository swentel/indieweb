<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\indieweb\Entity\MicrosubChannelInterface;

/**
 * Defines a class to build a listing of IndieAuth authorization code entities.
 */
class IndieAuthAuthorizationCodeListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Code');
    $header['status'] = $this->t('Status');
    $header['expire'] = $this->t('Expires');
    $header['client'] = $this->t('Client');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeInterface */
    $row['label'] = $entity->label();
    $row['status'] = $entity->get('status')->value ? t('Active') : t('Revoked');
    $expires = $entity->get('expire')->value ? \Drupal::service('date.formatter')->format($entity->get('expire')->value, 'short') : t('Never');
    $row['expire'] = $expires;
    $row['client'] = $entity->get('client_id')->value;
    return $row + parent::buildRow($entity);
  }


}

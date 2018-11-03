<?php

namespace Drupal\indieweb;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of microsub channel entities.
 */
class MicrosubChannelListBuilder extends IndieWebDraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_microsub_channel_overview_form';
  }

    /**
   * Loads entity IDs using a pager sorted by the entity id.
   *
   * @return array
   *   An array of entity IDs.
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort($this->entityType->getKey('weight'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Channel name');
    $header['enable'] = $this->t('Status');
    $header['sources'] = $this->t('Sources');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['status'] = ['#markup' => $entity->get('status')->value ? t('Enabled') : t('Disabled')];
    /** @var \Drupal\indieweb\Entity\MicrosubChannelInterface $entity */
    $sources = $entity->getSources();
    $row['sources'] = ['#markup' => Link::fromTextAndUrl(
        $this->formatPlural(count($sources), '1 source', '@count sources'),
        Url::fromRoute('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $entity->id()]))->toString()
    ];

    return $row + parent::buildRow($entity);
  }

}

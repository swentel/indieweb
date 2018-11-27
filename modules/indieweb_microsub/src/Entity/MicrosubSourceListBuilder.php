<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Defines a class to build a listing of microsub source entities.
 */
class MicrosubSourceListBuilder extends EntityListBuilder {

  /**
   * The channel.
   *
   * @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface
   */
  protected $channel;

    /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    return $this->getStorage()->getQuery()
      ->condition('channel_id', $this->channel->id())
      ->sort($this->entityType->getKey('label'))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render(MicrosubChannelInterface $channel = NULL) {
    $this->channel = $channel;
    $build = parent::render();
    $build['#title'] = $this->t('Sources in channel: @channel', ['@channel' => $this->channel->label()]);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Source');
    $header['status'] = $this->t('Status');
    $header['media_cache'] = $this->t('Media cache');
    $header['items'] = $this->t('Total Items');
    $header['fetch_next'] = $this->t('Next update');
    $header['in_keep'] = $this->t('Feed/keep');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $entity */
    $row['label'] = $entity->label();
    $row['status'] = $entity->getStatus() ? t('Enabled') : t('Disabled');
    $row['media_cache'] = $entity->disableImageCache() ? t('Disabled') : t('Enabled');
    $row['items'] = $entity->getItemCount();
    $next = $entity->getNextFetch();

    $fetch_next = '/';
    if ($entity->getStatus() && $entity->getChannel()->getStatus()) {
      if ($next < \Drupal::time()->getRequestTime()) {
        $fetch_next = $this->t('imminently');
      }
      else {
        $fetch_next = $this->t('%time left', ['%time' => \Drupal::service('date.formatter')->formatInterval($next - \Drupal::time()->getRequestTime())]);
      }
    }
    $row['fetch_next'] = $fetch_next;
    $row['in_keep'] = $entity->getItemsInFeed() . '/' . $entity->getKeepItemsInFeed();
    return $row + parent::buildRow($entity);
  }

    /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = parent::buildOperations($entity);

    $operations['#links']['reset_next_fetch'] =  [
      'title' => $this->t('Reset next update'),
      'weight' => 10,
      'url' => $this->ensureDestination($entity->toUrl('reset-next-fetch')),
    ];

    $operations['#links']['delete_items'] =  [
      'title' => $this->t('Delete items'),
      'weight' => 11,
      'url' => $this->ensureDestination($entity->toUrl('delete-items')),
    ];

    return $operations;
  }

}

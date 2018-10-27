<?php

namespace Drupal\indieweb;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\indieweb\Entity\MicrosubChannelInterface;

/**
 * Defines a class to build a listing of microsub source entities.
 *
 * @see \Drupal\block\Entity\Block
 */
class MicrosubSourceListBuilder extends EntityListBuilder {

  /**
   * The channel.
   *
   * @var \Drupal\indieweb\Entity\MicrosubChannelInterface
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
    $header['items'] = $this->t('Items');
    $header['fetch_next'] = $this->t('Next update');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\indieweb\Entity\MicrosubSourceInterface */
    $row['label'] = $entity->label();
    $row['items'] = $entity->getItemCount();
    $next = $entity->getNextFetch();
    if ($next < \Drupal::time()->getRequestTime()) {
      $fetch_next = $this->t('imminently');
    }
    else {
      $fetch_next = $this->t('%time left', ['%time' => \Drupal::service('date.formatter')->formatInterval($next - \Drupal::time()->getRequestTime())]);
    }
    $row['fetch_next'] = $fetch_next;
    return $row + parent::buildRow($entity);
  }

}
<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
use Drupal\user\UserInterface;

/**
 * Defines a class to build a listing of microsub source entities.
 */
class MicrosubSourceListBuilder extends EntityListBuilder {

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface $user
   */
  protected $user;

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
      ->condition('uid', $this->user->id())
      ->sort($this->entityType->getKey('label'))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function render(UserInterface $user = NULL, MicrosubChannelInterface $channel = NULL) {
    $this->channel = $channel;
    $this->user = $user;
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

    if ($this->mediaCacheAccess()) {
      $header['media_cache'] = $this->t('Media cache');
    }

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

    if ($this->mediaCacheAccess()) {
      $row['media_cache'] = $entity->disableImageCache() ? t('Disabled') : t('Enabled');
    }

    $row['items'] = $entity->getItemCount();

    $next = $entity->getNextFetch();
    $fetch_next = '/';
    if (($entity->getStatus() && $entity->getChannel()->getStatus()) || $entity->usesWebSub()) {
      if ($next < \Drupal::time()->getRequestTime()) {
        $fetch_next = $this->t('imminently');
        if ($entity->usesWebSub()) {
          $fetch_next = $this->t('WebSub subscription ended');
        }
      }
      else {
        $time_string = '%time left';
        if ($entity->usesWebSub()) {
          $time_string = 'WebSub subscription ends in %time';
        }
        $fetch_next = $this->t($time_string, ['%time' => \Drupal::service('date.formatter')->formatInterval($next - \Drupal::time()->getRequestTime())]);
      }
    }

    $row['fetch_next'] = $fetch_next;
    $row['in_keep'] = $entity->getItemsInFeed() . '/' . $entity->getKeepItemsInFeed();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = [];
    if ($entity->access('update') && $entity->hasLinkTemplate('edit-form')) {
      $operations['edit'] = [
        'title' => $this->t('Edit'),
        'weight' => 10,
        'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_source.edit_form', ['indieweb_microsub_source' => $entity->id(), 'user' => $this->user->id()])),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_source.delete_form', ['indieweb_microsub_source' => $entity->id(), 'user' => $this->user->id()])),
      ];
    }

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = parent::buildOperations($entity);

    $operations['#links']['reset_next_fetch'] =  [
      'title' => $this->t('Reset next update'),
      'weight' => 10,
      'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_source.reset_next_fetch', ['indieweb_microsub_source' => $entity->id(), 'user' => $this->user->id()])),
    ];

    $operations['#links']['delete_items'] =  [
      'title' => $this->t('Delete items'),
      'weight' => 11,
      'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_source.delete_items', ['indieweb_microsub_source' => $entity->id(), 'user' => $this->user->id()])),
    ];

    return $operations;
  }

  /**
   * Has media cache access.
   *
   * @return bool
   */
  protected function mediaCacheAccess() {
    return \Drupal::moduleHandler()->moduleExists('indieweb_cache') && $this->user->hasPermission('disable image cache on source');
  }

}

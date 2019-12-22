<?php

namespace Drupal\indieweb_microsub\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\indieweb\IndieWebDraggableListBuilder;
use Drupal\user\UserInterface;

/**
 * Defines a class to build a listing of microsub channel entities.
 */
class MicrosubChannelListBuilder extends IndieWebDraggableListBuilder {

  /**
   * The authenticated user.
   *
   * @var \Drupal\user\UserInterface $user
   */
  protected $user = NULL;

  public function render(UserInterface $user = NULL) {
    $this->user = $user;

    $build = parent::render();

    $delete_link  = '';
    $count = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->getItemCountByChannel(0, $this->user);
    if ($count > 0) {
      $delete_link = ' - ' . Link::createFromRoute($this->t('Delete notifications'), 'entity.indieweb_microsub.delete_notifications', ['user' => $this->user->id()])->toString();
    }
    $build['notifications'] = [
      '#markup' => $this->t('Number of notifications: @count', ['@count' => $count]) . $delete_link,
      '#weight' => -10,
    ];

    return $build;
  }

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
      ->condition('uid', $this->user->id())
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
    $header['status'] = $this->t('Status');
    $header['items'] = $this->t('Items');
    $header['sources'] = $this->t('Sources');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $entity */
    $row['label'] = $entity->label();
    $row['status'] = ['#markup' => $entity->get('status')->value ? t('Enabled') : t('Disabled')];
    $row['items'] = ['#markup' => $entity->getItemCount()];
    $sources = $entity->getSources();
    $row['sources'] = ['#markup' => Link::fromTextAndUrl(
        $this->formatPlural(count($sources), '1 source', '@count sources'),
        Url::fromRoute('indieweb.admin.microsub_sources', ['user' => $this->user->id(), 'indieweb_microsub_channel' => $entity->id()]))->toString()
    ];

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
        'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_channel.edit_form', ['indieweb_microsub_channel' => $entity->id(), 'user' => $this->user->id()])),
      ];
    }
    if ($entity->access('delete') && $entity->hasLinkTemplate('delete-form')) {
      $operations['delete'] = [
        'title' => $this->t('Delete'),
        'weight' => 100,
        'url' => $this->ensureDestination(Url::fromRoute('entity.indieweb_microsub_channel.delete_form', ['indieweb_microsub_channel' => $entity->id(), 'user' => $this->user->id()])),
      ];
    }

    return $operations;
  }

}

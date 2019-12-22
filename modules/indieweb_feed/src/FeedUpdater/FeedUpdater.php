<?php

namespace Drupal\indieweb_feed\FeedUpdater;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\indieweb_feed\Entity\FeedInterface;
use Drupal\user\EntityOwnerInterface;

class FeedUpdater implements FeedUpdaterInterface {

  /**
   * {@inheritdoc}
   */
  public function checkEntityOnInsertOrUpdate(EntityInterface $entity) {

    if (!($entity instanceof EntityOwnerInterface)) {
      return;
    }

    /** @var \Drupal\indieweb_feed\Entity\FeedInterface $feed */
    foreach (\Drupal::entityTypeManager()->getStorage('indieweb_feed')->loadMultiple() as $feed) {
      if ($entity->getOwnerId() > 0 && ($entity->getOwnerId() == $feed->getOwnerId() || $feed->getOwnerId() === 0) && in_array($entity->getEntityTypeId() . '|' . $entity->bundle(), $feed->getBundles())) {
        $this->insertItemIntoFeed($entity, $feed);
        Cache::invalidateTags(['indieweb_feed:' . $feed->id()]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteEntityFromFeeds(EntityInterface $entity) {
    \Drupal::entityTypeManager()->getStorage('indieweb_feed_item')->removeItemByEntity($entity->id(), $entity->getEntityTypeId());
  }

  /**
   * {@inheritdoc}
   */
  public function updateFeedItems(FeedInterface $feed) {

    // Remove first.
    \Drupal::entityTypeManager()->getStorage('indieweb_feed_item')->deleteItemsInFeed($feed->id());

    foreach ($feed->getBundles() as $item) {
      list($entity_type, $bundle) = explode('|', $item);

      $entityType = \Drupal::entityTypeManager()->getDefinition($entity_type);
      $bundle_key = $entityType->getKey('bundle');

      $query = \Drupal::entityQuery($entity_type)
        ->condition($bundle_key, $bundle)
        ->sort('created', 'DESC')
        ->range(0, $feed->getLimit());

      if ($feed->getOwnerId()) {
        $query->condition('uid', $feed->getOwnerId());
      }

      $ids = $query->execute();

      if (!empty($ids)) {
        foreach (\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($ids) as $entity) {
          $this->insertItemIntoFeed($entity, $feed);
        }
      }
    }

    // Invalidate feed cache.
    Cache::invalidateTags(['indieweb_feed:' . $feed->id()]);
  }

  /**
   * Inserts an entity into a feed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to save.
   * @param \Drupal\indieweb_feed\Entity\FeedInterface $feed
   *   The feed to save this item to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function insertItemIntoFeed(EntityInterface $entity, FeedInterface $feed) {
    \Drupal::entityTypeManager()->getStorage('indieweb_feed_item')->insertItemIntoFeed($entity, $feed);
  }

}

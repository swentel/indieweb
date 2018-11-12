<?php

namespace Drupal\indieweb_feed\FeedUpdater;

use Drupal\Core\Entity\EntityInterface;
use Drupal\indieweb_feed\Entity\FeedInterface;

interface FeedUpdaterInterface {

  /**
   * Checks whether an entity needs to inserted in a feed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The inserted or updated entity.
   */
  public function checkEntityOnInsertOrUpdate(EntityInterface $entity);

  /**
   * Deletes an entity from feeds.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be deleted.
   */
  public function deleteEntityFromFeeds(EntityInterface $entity);

  /**
   * Update items for a feed.
   *
   * @param \Drupal\indieweb_feed\Entity\FeedInterface $feed
   */
  public function updateFeedItems(FeedInterface $feed);

}
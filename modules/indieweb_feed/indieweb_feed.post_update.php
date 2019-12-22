<?php

/**
 * @file
 * Post update functions for IndieWeb Feed.
 */

/**
 * Refreshes the existing feeds.
 */
function indieweb_feed_post_update_0001() {
  /** @var \Drupal\indieweb_feed\Entity\FeedInterface[] $feeds */
  $feeds = \Drupal::entityTypeManager()->getStorage('indieweb_feed')->loadMultiple();
  foreach ($feeds as $feed) {
    \Drupal::service('indieweb_feed.updater')->updateFeedItems($feed);
  }
}

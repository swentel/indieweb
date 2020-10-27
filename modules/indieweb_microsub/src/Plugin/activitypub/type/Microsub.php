<?php

namespace Drupal\indieweb_microsub\Plugin\activitypub\type;

use Drupal\activitypub\Entity\ActivityPubActivityInterface;
use Drupal\activitypub\Services\Type\TypePluginBase;
use p3k\HTTP;
use p3k\XRay;

/**
 * The ActivityPub static types.
 *
 * @ActivityPubType(
 *   id = "activitypub_microsub",
 *   label = @Translation("ActivityPub microsub integration")
 * )
 */
class Microsub extends TypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function isExposed() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function onActivitySave(ActivityPubActivityInterface $activity, $update = TRUE) {
    if (!$update && $activity->getCollection() == 'inbox' && ($channel_id = $this->configFactory->get('indieweb_microsub.settings')->get('activitypub_channel')) && is_numeric($channel_id)) {
      $json = @json_decode($activity->getPayLoad(), TRUE);
      $xray = new XRay();
      $parsed = $xray->parse($json['id'], $json);
      if ($parsed && !empty($parsed['data']['type']) && $parsed['data']['type'] != 'unknown') {

        $target = \Drupal::request()->getSchemeAndHttpHost();
        if (!empty($json['object']['inReplyTo'])) {
          $target = $json['object']['inReplyTo'];
        }

        $values = [
          'source' => $json['id'],
          'target' => $target,
          'author_name' => $parsed['data']['author']['name'],
        ];

        /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention */
        $webmention = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->create($values);
        /** @var \Drupal\indieweb_microsub\MicrosubClient\MicrosubClientInterface $microsubClient */
        $microsubClient = \Drupal::service('indieweb.microsub.client');
        $microsubClient->sendNotification($webmention, $parsed, $channel_id);

        // Send push notification if there's an in-reply-to and the host matches
        // this one.
        if (!empty($parsed['data']['in-reply-to'][0]) && strpos($parsed['data']['in-reply-to'][0], \Drupal::request()->getSchemeAndHttpHost()) !== FALSE) {
          $microsubClient->sendPushNotification([$webmention]);
        }

        // Cleanup items.
        $items_to_keep = $this->configFactory->get('indieweb_microsub.settings')->get('activitypub_items_in_feed');
        if ($items_to_keep) {
          $guids = [$parsed['url']];
          $items_to_keep += 5;
          $timestamp = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->getTimestampByRangeSourceAndChannel($items_to_keep, $channel_id, 0);
          if ($timestamp) {
            \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->removeItemsBySourceAndChannelOlderThanTimestamp($timestamp, $channel_id, 0, $guids);
          }
        }
      }
    }
  }
}

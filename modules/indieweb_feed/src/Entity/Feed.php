<?php

namespace Drupal\indieweb_feed\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the IndieWeb Feed entity.
 *
 * @ConfigEntityType(
 *   id = "indieweb_feed",
 *   label = @Translation("Feed"),
 *   label_collection = @Translation("Feeds"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\indieweb_feed\Entity\FeedListBuilder",
 *     "form" = {
 *       "add" = "Drupal\indieweb_feed\Form\FeedForm",
 *       "edit" = "Drupal\indieweb_feed\Form\FeedForm",
 *       "delete" = "Drupal\indieweb_feed\Form\FeedDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\indieweb_feed\Routing\FeedHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "indieweb_feed",
 *   admin_permission = "administer indieweb",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/services/indieweb/feeds/add",
 *     "edit-form" = "/admin/config/services/indieweb/feeds/{indieweb_feed}/edit",
 *     "delete-form" = "/admin/config/services/indieweb/feeds/{indieweb_feed}/delete",
 *     "collection" = "/admin/config/services/indieweb/feeds",
 *     "update-items" = "/admin/config/services/indieweb/feeds/{indieweb_feed}/update-items"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "path",
 *     "limit",
 *     "author",
 *     "feedTitle",
 *     "excludeIndexing",
 *     "jf2",
 *     "feedLinkTag",
 *     "jf2LinkTag",
 *     "bundles",
 *     "ownerId"
 *   }
 * )
 */
class Feed extends ConfigEntityBase implements FeedInterface {

  /**
   * The Feed ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Feed label.
   *
   * @var string
   */
  protected $label;

  /**
   * The feed path
   *
   * @var string
   */
  protected $path;

  /**
   * The limit of items.
   *
   * @var int
   */
  protected $limit = 10;

  /**
   * The author information.
   *
   * @var string
   */
  protected $author;

  /**
   * The feed title.
   *
   * @var string
   */
  protected $feedTitle;

  /**
   * Exclude from indexing.
   */
  protected $excludeIndexing = FALSE;

  /**
   * Whether to create a jf2 feed.
   *
   * @var bool
   */
  protected $jf2;

  /**
   * Whether to expose rel="feed"
   *
   * @var bool
   */
  protected $feedLinkTag;

  /**
   * Whether to expose application/jf2feed+json
   *
   * @var bool
   */
  protected $jf2LinkTag;

  /**
   * The bundles for this feed.
   *
   * @var array
   */
  protected $bundles = [];

  /**
   * The user id to get feed items for.
   *
   * @var int
   */
  protected $ownerId = 1;

  /**
   * {@inheritdoc}
   */
  public function excludeIndexing() {
    return $this->excludeIndexing;
  }

  /**
   * {@inheritdoc}
   */
  public function exposeJf2Feed() {
    return $this->jf2;
  }

  /**
   * {@inheritdoc}
   */
  public function exposeRelLinkTag() {
    return $this->feedLinkTag;
  }

  /**
   * {@inheritdoc}
   */
  public function exposeJf2LinkTag() {
    return $this->jf2LinkTag;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthor() {
    return $this->author;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedTitle() {
    return $this->feedTitle;
  }

  /**
   * {@inheritdoc}
   */
  public function getLimit() {
    return $this->limit;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    return $this->bundles;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->ownerId;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {

    // Remove leading slash.
    if (substr($this->getPath(), 0, 1) == '/') {
      $this->setPath(substr_replace($this->getPath(), '', 0, 1));
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    \Drupal::service('indieweb_feed.updater')->updateFeedItems($this);
    // We need to clear the cache as the link header tags might be changed.
    // TODO check we just can't add a cache tag to the attachments, should
    // probably be easier.
    Cache::invalidateTags(['rendered']);
    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    parent::delete();
    \Drupal::entityTypeManager()->getStorage('indieweb_feed_item')->deleteItemsInFeed($this->id());
  }

}

<?php

namespace Drupal\indieweb\Entity;

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
 *     "list_builder" = "Drupal\indieweb\FeedListBuilder",
 *     "form" = {
 *       "add" = "Drupal\indieweb\Form\FeedForm",
 *       "edit" = "Drupal\indieweb\Form\FeedForm",
 *       "delete" = "Drupal\indieweb\Form\FeedDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\indieweb\FeedHtmlRouteProvider",
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
   * Whether to create an atom feed.
   *
   * @var bool
   */
  protected $atom;

  /**
   * Whether to use a hub or not.
   *
   * @var bool
   */
  protected $hub;

  /**
   * The default hub URL.
   *
   * @var string
   */
  protected $hubUrl = 'https://bridgy-fed.superfeedr.com/';

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
  protected $relHeader;

  /**
   * Whether to expose application/atom+xml
   *
   * @var bool
   */
  protected $relHeaderAtom;

  /**
   * Whether to expose application/jf2feed+json
   *
   * @var bool
   */
  protected $relHeaderJf2;

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
  public function exposeAtomFeed() {
    return $this->atom;
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
  public function exposeRelHeaderLink() {
    return $this->relHeader;
  }

  /**
   * {@inheritdoc}
   */
  public function exposeAtomHeaderLink() {
    return $this->relHeaderAtom;
  }

  /**
   * {@inheritdoc}
   */
  public function useHub() {
    return $this->hub;
  }

  /**
   * {@inheritdoc}
   */
  public function exposeJf2HeaderLink() {
    return $this->relHeaderJf2;
  }

  /**
   * {@inheritdoc}
   */
  public function getHubUrl() {
    return $this->hubUrl;
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

}

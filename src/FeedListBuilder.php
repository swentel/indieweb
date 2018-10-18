<?php

namespace Drupal\indieweb;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Feed entities.
 */
class FeedListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Feed');
    $header['path'] = $this->t('Path');
    $header['items'] = $this->t('Number of items');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['path'] = $entity->getPath();
    $row['items'] = \Drupal::database()->query('SELECT count(id) FROM {indieweb_feed_items} WHERE feed = :feed', [':feed' => $entity->id()])->fetchField();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();

    $build['#title'] = $this->t('Feeds');

    $build['info'] = [
      '#weight' => -10,
      '#markup' => $this->t('<p>Besides the standard RSS feed which you can create where readers can subscribe to, you can also create microformat feeds. These can either return HTML, Atom feed or jf2feed+json.<br />Because content can be nodes, comments etc, it isn\'t possible to use views. However, you can create multiple feeds which aggregate the content in a page and/or feed.<br />The feeds are controlled by the \'access content\' permission.<br />All items in the HTML feed will be rendered with the \'Microformat\' view mode.<br />If your homepage is a feed with microformats, you don\'t necessarily need this. Atom feeds are generated using https://granary.io/</p><p>You will need feeds when:</p><ul><li>you use Bridgy: the service will look for html link headers with rel="feed" and use those pages to crawl so it knows to which content it needs to send webmentions to.</li><li>you want to allow IndieWeb readers (Monocle, Together, Indigenous) to subscribe to your content. These are alternate types which can either link to a page with microformat entries. It\'s advised to have an h-card on that page too as some parsers don\'t go to the homepage to fetch that content.</li></ul>') . '<p></p>',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOperations(EntityInterface $entity) {
    $operations = parent::buildOperations($entity);
    $operations['#links']['update_items'] =  [
      'title' => $this->t('Update items'),
      'weight' => 10,
      'url' => $this->ensureDestination($entity->toUrl('update-items')),
    ];

    return $operations;
  }

}

<?php

namespace Drupal\indieweb\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Webmention entities.
 */
class WebmentionViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Additional information for Views integration, such as table joins, can be
    // put here.

    return $data;
  }

}

<?php

namespace Drupal\indieweb_webmention\Entity;

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

    // Jobs bulk form.
    $data['webmention_received']['webmention_bulk_form'] = array(
      'title' => $this->t('Webmention operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple webmentions.'),
      'field' => array(
        'id' => 'webmention_bulk_form',
      ),
    );

    // Change filter for type and property to our filter.
    $data['webmention_received']['type']['filter']['id'] = 'webmention_filter_select';
    $data['webmention_received']['property']['filter']['id'] = 'webmention_filter_select';

    return $data;
  }

}

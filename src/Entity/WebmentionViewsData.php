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

    // Jobs bulk form.
    $data['webmention_entity']['webmention_bulk_form'] = array(
      'title' => $this->t('Webmention operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple webmentions.'),
      'field' => array(
        'id' => 'webmention_bulk_form',
      ),
    );

    return $data;
  }

}

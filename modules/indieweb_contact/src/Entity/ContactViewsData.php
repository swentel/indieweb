<?php

namespace Drupal\indieweb_contact\Entity;

use Drupal\views\EntityViewsData;

/**
 * Provides Views data for Contact entities.
 */
class ContactViewsData extends EntityViewsData {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    // Jobs bulk form.
    $data['indieweb_contact']['contact_bulk_form'] = array(
      'title' => $this->t('Contact operations bulk form'),
      'help' => $this->t('Add a form element that lets you run operations on multiple contacts.'),
      'field' => array(
        'id' => 'indieweb_contact_bulk_form',
      ),
    );

    return $data;
  }

}

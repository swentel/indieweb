<?php

namespace Drupal\indieweb_contact\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a contact operations bulk form element.
 *
 * @ViewsField("indieweb_contact_bulk_form")
 */
class ContactBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No contacts selected.');
  }

}

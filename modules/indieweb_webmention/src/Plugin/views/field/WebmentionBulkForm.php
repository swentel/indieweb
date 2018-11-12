<?php

namespace Drupal\indieweb_webmention\Plugin\views\field;

use Drupal\views\Plugin\views\field\BulkForm;

/**
 * Defines a webmention operations bulk form element.
 *
 * @ViewsField("webmention_bulk_form")
 */
class WebmentionBulkForm extends BulkForm {

  /**
   * {@inheritdoc}
   */
  protected function emptySelectedMessage() {
    return $this->t('No webmentions selected.');
  }

}

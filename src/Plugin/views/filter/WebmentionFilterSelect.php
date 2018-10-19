<?php

namespace Drupal\indieweb\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\ManyToOne;

/**
 * Extends string filter to use dropdowns.
 *
 * @ViewsFilter("webmention_filter_select")
 */
class WebmentionFilterSelect extends ManyToOne {

  protected $valueFormType = 'select';

  protected function valueForm(&$form, FormStateInterface $form_state) {
    parent::valueForm($form, $form_state);

    $options = \Drupal::database()->select('webmention_entity', 'w')
      ->fields('w', [$this->field])
      ->distinct()
      ->execute()
      ->fetchAllKeyed(0, 0);

    $form['value']['#options'] = $options;
  }

}
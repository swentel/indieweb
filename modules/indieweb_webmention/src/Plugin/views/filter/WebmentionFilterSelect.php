<?php

namespace Drupal\indieweb_webmention\Plugin\views\filter;

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
    $options = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->getFieldOptions($this->field);
    $form['value']['#options'] = $options;
  }

}
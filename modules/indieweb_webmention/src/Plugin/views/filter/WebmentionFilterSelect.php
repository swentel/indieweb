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

    // Add sensible defaults in case not many or even no webmentions were
    // received yet.
    switch ($this->field) {
      case 'property':
        $options += [
          'like-of' => 'like-of',
          'in-reply-to' => 'in-reply-to',
          'repost-of' => 'repost-of',
          'mention-of' => 'mention-of',
          'bookmark-of' => 'bookmark-of',
          'follow-of' => 'follow-of',
        ];
        break;

      case 'type':
        $options += [
          'entry' => 'entry',
        ];
        break;
    }

    $form['value']['#options'] = $options;
  }

  public function adminSummary() {
    if (!empty($this->value)) {
      return implode(', ', array_keys($this->value));
    }
    else {
      return 'all';
    }
  }

  public function validate() {
    // Do not validate.
    return [];
  }

}

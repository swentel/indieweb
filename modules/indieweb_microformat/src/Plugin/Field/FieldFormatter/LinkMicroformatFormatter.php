<?php

namespace Drupal\indieweb_microformat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'link_microformat' formatter.
 *
 * @FieldFormatter(
 *   id = "link_microformat",
 *   label = @Translation("Microformat link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class LinkMicroformatFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'microformat_class' => 'u-like-of',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['microformat_class'] = [
      '#type' => 'select',
      '#options' =>[
        'u-like-of' => $this->t('Like'),
        'u-bookmark-of' => $this->t('Bookmark'),
        'u-bookmark-of h-cite' => $this->t('Bookmark and cite'),
        'u-in-reply-to' => $this->t('In reply to'),
        'u-repost-of' => $this->t('Repost or Quotation'),
        'u-follow-of' => $this->t('Follow of'),
        'p-author h-card' => $this->t('Author'),
      ],
      '#title' => t('Class'),
      '#default_value' => $this->getSetting('microformat_class'),
      '#description' => $this->t('Note: if a repost link field contains a title, "h-cite u-quotation-of" will be used, "repost-of" otherwise.')
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings();

    if (!empty($settings['microformat_class'])) {
      $summary[] = t('Microformat class: @class', ['@class' => $settings['microformat_class']]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = parent::viewElements($items, $langcode);
    $settings = $this->getSettings();

    if (!empty($element) && !empty($settings['microformat_class'])) {
      foreach ($element as $delta => $item) {

        if ($settings['microformat_class'] == 'u-repost-of') {
          if (!empty($item['#title']) && $item['#title'] != $item['#url']->getUri()) {
            $element[$delta]['#options']['attributes']['class'][] = 'u-url';
            $element[$delta]['#prefix'] = '<cite class="h-cite u-quotation-of">';
            $element[$delta]['#suffix'] = '</cite>';
          }
          else {
            $element[$delta]['#options']['attributes']['class'][] = $settings['microformat_class'];
          }
        }
        else {
          $element[$delta]['#options']['attributes']['class'][] = $settings['microformat_class'];
        }
      }
    }

    return $element;
  }

}

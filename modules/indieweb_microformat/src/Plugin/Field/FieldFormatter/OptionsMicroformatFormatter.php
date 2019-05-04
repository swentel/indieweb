<?php

namespace Drupal\indieweb_microformat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\AllowedTagsXssTrait;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;

/**
 * Plugin implementation of the 'list_microformat' formatter.
 *
 * @FieldFormatter(
 *   id = "list_microformat",
 *   label = @Translation("Microformat RSVP/Geocache"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   }
 * )
 */
class OptionsMicroformatFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'property_type' => 'p-rsvp',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['property_type'] = [
      '#type' => 'select',
      '#options' =>[
        'p-rsvp' => $this->t('RSVP'),
        'p-geocache-log-type' => $this->t('Geocache log type'),
      ],
      '#title' => t('Property'),
      '#default_value' => $this->getSetting('property_type'),
    ];

    return $elements;
  }

    /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings();

    if (!empty($settings['property_type'])) {
      $summary[] = t('Property: @property', ['@property' => $settings['property_type']]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getSettings();
    $property_type = $settings['property_type'];

    // Only collect allowed options if there are actually items to display.
    if ($items->count()) {
      $provider = $items->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getOptionsProvider('value', $items->getEntity());
      // Flatten the possible options, to support opt groups.
      $options = OptGroup::flattenOptions($provider->getPossibleOptions());

      foreach ($items as $delta => $item) {
        $value = $item->value;
        // If the stored value is in the current set of allowed values, display
        // the associated label, otherwise just display the raw value.
        $output = isset($options[$value]) ? $options[$value] : $value;
        $elements[$delta] = [
          '#markup' => '<data class="' . $property_type . '" value="' . $value . '">' . $output . '</data>',
          '#allowed_tags' => array_merge(FieldFilteredMarkup::allowedTags(), ['data']),
        ];
      }
    }

    return $elements;
  }

}

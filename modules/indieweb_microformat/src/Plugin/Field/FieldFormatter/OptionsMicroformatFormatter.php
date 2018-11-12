<?php

namespace Drupal\indieweb_microformat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\AllowedTagsXssTrait;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\OptGroup;

/**
 * Plugin implementation of the 'list_microformat' formatter.
 *
 * @FieldFormatter(
 *   id = "list_microformat",
 *   label = @Translation("Microformat RSVP"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   }
 * )
 */
class OptionsMicroformatFormatter extends FormatterBase {

  use AllowedTagsXssTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

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
          '#markup' => '<data class="p-rsvp" value="' . $value . '">' . $output . '</data>',
          '#allowed_tags' => array_merge(FieldFilteredMarkup::allowedTags(), ['data']),
        ];
      }
    }

    return $elements;
  }

}

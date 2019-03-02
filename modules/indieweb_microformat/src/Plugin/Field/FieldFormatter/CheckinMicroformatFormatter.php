<?php

namespace Drupal\indieweb_microformat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'checkin_microformat' formatter.
 *
 * @FieldFormatter(
 *   id = "checkin_microformat",
 *   label = @Translation("Microformat checkin"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class CheckinMicroformatFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'geofield' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);


    $geo_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    $field_types = \Drupal::service('plugin.manager.field.field_type')->getDefinitions();
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['geofield'])) {
        $geo_fields[$key] = $field_types[$field->getType()]['label'] . ': ' . $field->getName();
      }
    }

    $elements['geofield'] = [
      '#type' => 'select',
      '#options' => [
        '' => 'No field available',
      ] + $geo_fields,
      '#title' => $this->t('Geo field'),
      '#description' => $this->t('Select a geo field from the geofield module to get the latitude and longitude, if available'),
      '#default_value' => $this->getSetting('geofield'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $settings = $this->getSettings();

    if (!empty($settings['geofield'])) {
      $summary[] = t('Geofield: @field', ['@field' => $settings['geofield']]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = parent::viewElements($items, $langcode);
    $settings = $this->getSettings();
    $geoPhpWrapper = \Drupal::service('geofield.geophp');

    if (!empty($element)) {
      foreach ($element as $delta => $item) {
        $element[$delta]['#prefix'] = '<span class="u-checkin h-card">';
        $element[$delta]['#options']['attributes']['class'][] = 'p-name';
        $suffix = '';

        if (!empty($settings['geofield']) && $geoPhpWrapper) {
          $entity = $items->getEntity();
          if ($entity->hasField($settings['geofield'])) {
            $value = $entity->get($settings['geofield'])->value;
            $geom = $geoPhpWrapper->load($value);
            if ($geom && $geom->getGeomType() == 'Point') {
              $suffix = '<span class="p-latitude hidden">' . $geom->y() . '</span><span class="p-longitude hidden">' . $geom->x() . '</span>';
            }
          }
        }

        $suffix .= '</span>';
        $element[$delta]['#suffix'] = $suffix;
      }
    }

    return $element;
  }

}

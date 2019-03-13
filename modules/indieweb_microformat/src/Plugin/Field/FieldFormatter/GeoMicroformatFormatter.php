<?php

namespace Drupal\indieweb_microformat\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Plugin\Field\FieldFormatter\LatLonFormatter;

/**
 * Plugin implementation of the 'checkin_microformat' formatter.
 *
 * @FieldFormatter(
 *   id = "geo_microformat",
 *   label = @Translation("Microformat geo"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeoMicroformatFormatter extends LatLonFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'output_format' => 'decimal',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $output = ['#markup' => ''];
      $geom = $this->geoPhpWrapper->load($item->value);
      if ($geom && $geom->getGeomType() == 'Point') {
        $output = [
          '#theme' => 'indieweb_geofield_latlon',
          '#lat' => $geom->y(),
          '#lon' => $geom->x(),
        ];
      }
      $elements[$delta] = $output;
    }

    return $elements;
  }

}
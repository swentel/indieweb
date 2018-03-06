<?php

namespace Drupal\indieweb\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Plugin implementation of the 'webmention reference' formatter.
 *
 * @FieldFormatter(
 *   id = "entity_reference_webmention",
 *   label = @Translation("Webmention"),
 *   description = @Translation("Display a webmention reference."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceWebmentionFormatter extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if ($entity->id()) {
        $elements[$delta] = [
          '#markup' => check_markup($entity->get('content_text')->value, 'restricted_html'),
          // Create a cache tag entry for the referenced entity. In the case
          // that the referenced entity is deleted, the cache for referring
          // entities must be cleared.
          '#cache' => [
            'tags' => $entity->getCacheTags(),
          ],
        ];
      }
    }

    return $elements;
  }

}

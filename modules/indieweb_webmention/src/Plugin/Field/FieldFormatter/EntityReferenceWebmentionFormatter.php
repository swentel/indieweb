<?php

namespace Drupal\indieweb_webmention\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\Core\Form\FormStateInterface;

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
  public static function defaultSettings() {
    return [
      'show_avatar' => FALSE,
      'show_summary' => FALSE,
      'show_created' => FALSE,
      'replace_comment_user_picture' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {

    $elements['show_summary'] = [
      '#title' => t('Show summary'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_summary'),
      '#description' => $this->t('Use this if you are doing custom comment templating.'),
    ];

    $elements['show_avatar'] = [
      '#title' => t('Show avatar'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_avatar'),
      '#description' => $this->t('This will only show up if summary is enabled. Use this if you are doing custom comment templating.'),
    ];

    $elements['show_created'] = [
      '#title' => t('Show created time'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('show_created'),
      '#description' => $this->t('This will only show up if summary is enabled. Use this if you are doing custom comment templating.'),
    ];

    $elements['replace_comment_user_picture'] = [
      '#title' => t('Replace comment user picture'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('replace_comment_user_picture'),
      '#description' => $this->t('If the webmention has a picture from the author, move it into the user picture element of the comment template.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = t('Show summary') . ': ' . ($this->getSetting('show_summary') ? t('yes') : t('no'));
    $summary[] = t('Show avatar') . ': ' . ($this->getSetting('show_avatar') ? t('yes') : t('no'));
    $summary[] = t('Show created time') . ': ' . ($this->getSetting('show_created') ? t('yes') : t('no'));
    $summary[] = t('Replace comment user picture') . ': ' . ($this->getSetting('replace_comment_user_picture') ? t('yes') : t('no'));
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $entity */
    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $entity) {
      if ($entity->id()) {

        $suggestion = !empty($entity->get('property')->value) ? '__' . str_replace('-', '_', $entity->get('property')->value) : '';
        $elements[$delta] = [
          '#theme' => 'webmention' . $suggestion,
          '#show_summary' => $this->getSetting('show_summary'),
          '#show_avatar' => $this->getSetting('show_avatar'),
          '#show_created' => $this->getSetting('show_created'),
          '#replace_comment_user_picture' => $this->getSetting('replace_comment_user_picture'),
          '#property' => $entity->get('property')->value,
          '#author_name' => $entity->get('author_name')->value,
          '#author_photo' => $entity->get('author_photo')->value,
          '#created' => $entity->get('created')->value,
          '#source' => $entity->get('source')->value,
          '#content_text' => $entity->get('content_text')->value,
          '#content_html' => $entity->get('content_html')->value,
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

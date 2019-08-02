<?php

namespace Drupal\indieweb_webmention\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb syndication entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_syndication",
 *   label = @Translation("Syndication"),
 *   label_collection = @Translation("Syndications"),
 *   label_singular = @Translation("Syndication"),
 *   label_plural = @Translation("Syndications"),
 *   label_count = @PluralTranslation(
 *     singular = "@count Syndication",
 *     plural = "@count Syndications",
 *   ),
 *   persistent_cache = FALSE,
 *   admin_permission = "administer syndication entities",
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage" = "Drupal\indieweb_webmention\Entity\Storage\SyndicationStorage",
 *     "list_builder" = "Drupal\indieweb_webmention\Entity\SyndicationListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\indieweb_webmention\Entity\SyndicationAccessControlHandler",
 *     "route_provider" = {
 *      "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "webmention_syndication",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "delete-form" = "/admin/content/syndication/{indieweb_syndication}/delete",
 *     "collection" = "/admin/content/syndication",
 *   }
 * )
 */
class Syndication extends ContentEntityBase implements SyndicationInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityTypeId() {
    return $this->get('entity_type_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->get('entity_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrl() {
    return $this->get('url')->value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('Syndication id'))
      ->setDescription(t('The id of the item.'));

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('The entity id'));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('entity_type_id'))
      ->setDescription(t('The entity type id.'))
      ->setSetting('max_length', 128);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Url'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    return $fields;
  }

}

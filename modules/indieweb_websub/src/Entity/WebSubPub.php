<?php

namespace Drupal\indieweb_websub\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the indieweb WebSubPub entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_websubpub",
 *   label = @Translation("WebSubPub"),
 *   label_collection = @Translation("WebSubPub items"),
 *   label_singular = @Translation("WebSubPub"),
 *   label_plural = @Translation("WebSubPub items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count WebSubPub",
 *     plural = "@count WebSubPub items",
 *   ),
 *   persistent_cache = FALSE,
 *   admin_permission = "administer websubpub entities",
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "storage" = "Drupal\indieweb_websub\Entity\Storage\WebSubPubStorage",
 *     "list_builder" = "Drupal\indieweb_websub\Entity\WebSubPubListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "access" = "Drupal\indieweb_websub\Entity\WebSubPubAccessControlHandler",
 *     "route_provider" = {
 *      "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "indieweb_websubpub",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "delete-form" = "/admin/content/websub/{indieweb_websubpub}/delete",
 *     "collection" = "/admin/content/websub",
 *   }
 * )
 */
class WebSubPub extends ContentEntityBase implements WebSubPubInterface {

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
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('WebSubPub id'))
      ->setDescription(t('The id of the item.'));

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('The entity id'));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('entity_type_id'))
      ->setDescription(t('The entity type id.'))
      ->setSetting('max_length', 128);

    return $fields;
  }

}

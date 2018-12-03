<?php

namespace Drupal\indieweb_context\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the indieweb post context entity class.
 *
 * @ContentEntityType(
 *   id = "indieweb_post_context",
 *   label = @Translation("Post context"),
 *   persistent_cache = FALSE,
 *   admin_permission = "administer indieweb",
 *   base_table = "indieweb_post_context",
 *   handlers = {
 *     "storage" = "Drupal\indieweb_context\Entity\Storage\PostContextStorage"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *   }
 * )
 */
class PostContext extends ContentEntityBase implements ContentEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    /** @var \Drupal\Core\Field\BaseFieldDefinition[] $fields */
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id']->setLabel(t('Post context id'))
      ->setDescription(t('The id of the item.'));

    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('The entity id'));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('entity_type_id'))
      ->setDescription(t('The entity type id.'))
      ->setSetting('max_length', 128);

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Url'))
      ->setSetting('max_length', 255);

    $fields['content'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Content'))
      ->setDescription(t('The content of the item.'));

    return $fields;
  }

}

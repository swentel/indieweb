<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the IndieAuth authorization code entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_indieauth_code",
 *   label = @Translation("IndieAuth authorization code"),
 *   label_collection = @Translation("Authorization codes"),
 *   label_plural = @Translation("IndieAuth authorization codes"),
 *   handlers = {
 *     "storage" = "Drupal\indieweb_indieauth\Entity\Storage\IndieAuthAuthorizationCodeStorage",
 *     "storage_schema" = "Drupal\indieweb_indieauth\Entity\Storage\IndieAuthAuthorizationCodeStorageSchema",
 *     "list_builder" = "Drupal\indieweb_indieauth\Entity\IndieAuthAuthorizationCodeListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "indieauth_authorization_code",
 *   admin_permission = "administer indieweb",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "code",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/indieweb/indieauth/authorization-codes",
 *     "delete-form" = "/admin/config/services/indieweb/indieauth/authorization-codes/{indieweb_indieauth_code}/delete"
 *   }
 * )
 */
class IndieAuthAuthorizationCode extends ContentEntityBase implements IndieAuthAuthorizationCodeInterface {

  /**
   * {@inheritdoc}
   */
  public function enable() {
    $this->set('status', TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function revoke() {
    $this->set('status', 0);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevoked() {
    return !$this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isExpired() {
    $expire = $this->get('expire')->value;
    return $expire > 0 && $expire < \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    // TODO should this also check the permission again of the user
    // or is it ok enough on the the authorize forms ?
    return !$this->isRevoked() && !$this->isExpired();
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes() {
    return explode(' ', $this->get('scope')->value);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the code entity'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the code'))
      ->setReadOnly(TRUE);

    $fields['code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('The code'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['me'] = BaseFieldDefinition::create('string')
      ->setLabel(t('The url owner'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['scope'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Scopes'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['client_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('The client ID'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['redirect_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(t('redirect_uri'))
      ->setRequired(FALSE)
      ->setSetting('max_length', 255);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('The owner of the code'))
      ->setSetting('target_type', 'user');

    $fields['expire'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Expire'))
      ->setDescription(t('The time when the token expires'));

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status of the code'))
      ->setDefaultValue(1);


    return $fields;
  }

}
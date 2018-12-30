<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the IndieAuth token entity.
 *
 * @ContentEntityType(
 *   id = "indieweb_indieauth_token",
 *   label = @Translation("IndieAuth Token"),
 *   label_collection = @Translation("Tokens"),
 *   label_plural = @Translation("IndieAuth tokens"),
 *   handlers = {
 *     "storage" = "Drupal\indieweb_indieauth\Entity\Storage\IndieAuthTokenStorage",
 *     "storage_schema" = "Drupal\indieweb_indieauth\Entity\Storage\IndieAuthTokenStorageSchema",
 *     "list_builder" = "Drupal\indieweb_indieauth\Entity\IndieAuthTokenListBuilder",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\indieweb_indieauth\Routing\IndieAuthTokenRouteProvider",
 *     },
 *   },
 *   base_table = "indieauth_token",
 *   admin_permission = "administer indieweb",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "access_token",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "uid" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/indieweb/indieauth/tokens",
 *     "delete-form" = "/admin/config/services/indieweb/indieauth/tokens/{indieweb_indieauth_token}/delete",
 *     "change-status" = "/admin/config/services/indieweb/indieauth/tokens/{indieweb_indieauth_token}/status"
 *   }
 * )
 */
class IndieAuthToken extends ContentEntityBase implements IndieAuthTokenInterface {

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    return $this->get('access_token')->value;
  }

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
    return !$this->isRevoked() && !$this->isExpired();
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return (bool) $this->get('status')->value;
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
  public function getScopesAsString() {
    return $this->get('scope')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientId() {
    return $this->get('client_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getChanged() {
    return $this->get('changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the token entity'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the token'))
      ->setReadOnly(TRUE);

    $fields['access_token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('The access token'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255);

    $fields['refresh_token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Refresh token'))
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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('The owner of the token'))
      ->setSetting('target_type', 'user');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the token was created'));

    $fields['changed'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the token was changed'));

    $fields['expire'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Expire'))
      ->setDescription(t('The time when the token expires'));

    $fields['status'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Status of the token'))
      ->setDefaultValue(1);


    return $fields;
  }

}
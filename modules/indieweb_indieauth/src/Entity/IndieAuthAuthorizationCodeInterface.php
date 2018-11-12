<?php

namespace Drupal\indieweb_indieauth\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface IndieAuthAuthorizationCodeInterface extends ContentEntityInterface {

  /**
   * Enables a token.
   *
   * @return $this
   */
  public function enable();

  /**
   * Revokes a token.
   *
   * @return $this
   */
  public function revoke();

  /**
   * Checks whether a token is revoked or not.
   *
   * @return bool
   */
  public function isRevoked();

  /**
   * Returns whether the token is expired or not.
   *
   * @return bool
   */
  public function isExpired();

  /**
   * Returns whether the token is valid or not. Takes expire and status into
   * account.
   *
   * @return bool
   */
  public function isValid();

  /**
   * Returns the scopes for this token.
   *
   * @return array
   */
  public function getScopes();

}
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

  /**
   * Returns the owner id.
   *
   * @return integer
   */
  public function getOwnerId();

  /**
   * Returns the client id.
   *
   * @return string
   */
  public function getClientId();

  /**
   * Returns the expired time.
   *
   * @return integer
   */
  public function getExpiretime();

  /**
   * Returns the me URL.
   *
   * @return string
   */
  public function getMe();

  /**
   * Returns the redirect URI.
   *
   * @return string
   */
  public function getRedirectURI();

  /**
   * Returns the code.
   *
   * @return string
   */
  public function getCode();

  /**
   * Returns the code challenge.
   *
   * @return string
   */
  public function getCodeChallenge();

  /**
   * Returns the code challenge method.
   *
   * @return string
   */
  public function getCodeChallengeMethod();

}

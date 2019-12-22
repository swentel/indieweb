<?php

namespace Drupal\indieweb_indieauth\IndieAuthClient;

interface IndieAuthClientInterface {

  /**
   * Get the authorization header from the current request.
   *
   * @return string|NULL
   */
  public function getAuthorizationHeader();

  /**
   * Check if there's a valid access token in the request.
   *
   * @param $auth_header
   *   The authorization header.
   * @param $scope_to_check
   *   The scope needed for this request, optional.
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function isValidToken($auth_header, $scope_to_check = NULL);

  /**
   * Get the uid of the current request.
   *
   * @return boolean|integer
   */
  public function getAuthor();

  /**
   * Gets the internal token, if available.
   *
   * @return \Drupal\indieweb_indieauth\Entity\IndieAuthTokenInterface $indieAuthToken|boolean
   */
  public function getToken();

  /**
   * Revokes a token.
   *
   * @param $token
   */
  public function revokeToken($token);

  /**
   * Generates keys.
   *
   * @return bool|array
   */
  public function generateKeys();

  /**
   * Maps an account with a domain
   *
   * @param $uid
   *   The Drupal user id
   * @param $domain
   *   The domain
   * @param $is_drush
   *   Whether this is a Drush call or not.
   *
   * @return
   */
  public function externalauthMapAccount($uid, $domain, $is_drush = FALSE);

}

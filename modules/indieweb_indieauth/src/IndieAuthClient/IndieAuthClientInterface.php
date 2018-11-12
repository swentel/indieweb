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

}
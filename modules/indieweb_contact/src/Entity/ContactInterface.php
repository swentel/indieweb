<?php

namespace Drupal\indieweb_contact\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

interface ContactInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Returns the nickname.
   *
   * @return mixed
   */
  public function getNickname();

  /**
   * Returns the website.
   *
   * @return mixed
   */
  public function getWebsite();

  /**
   * Returns the photo.
   *
   * @return mixed
   */
  public function getPhoto();

}

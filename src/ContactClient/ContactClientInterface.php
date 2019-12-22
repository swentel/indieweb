<?php

namespace Drupal\indieweb\ContactClient;

interface ContactClientInterface {

  /**
   * Get all contacts.
   *
   * @param $uid
   *
   * @return array
   */
  public function getAllContacts($uid = 0);

  /**
   * Search contacts.
   *
   * @param $search
   * @param $uid
   *
   * @return mixed
   */
  public function searchContacts($search, $uid = 0);

  /**
   * Store a contact.
   *
   * @param array $values
   *   Keys: name, nickname, photo, url
   *
   * @return \Drupal\indieweb_contact\Entity\ContactInterface $contact|NULL
   *
   * @return mixed
   */
  public function storeContact(array $values);

}

<?php

namespace Drupal\indieweb\ContactClient;

interface ContactClientInterface {

  /**
   * Get all contacts.
   *
   * @return array
   */
  public function getAllContacts();

  /**
   * Search contacts.
   *
   * @param $search
   *
   * @return mixed
   */
  public function searchContacts($search);

  /**
   * Store a contact.
   *
   * @param array $values
   *   Keys: name, nickname, photo, url
   *
   * @return mixed
   */
  public function storeContact(array $values);

}

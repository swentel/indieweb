<?php

namespace Drupal\indieweb\ContactClient;

class ContactClient implements ContactClientInterface {

  /**
   * {@inheritdoc}
   */
  public function getAllContacts($uid = 0) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function searchContacts($search, $uid = 0) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeContact(array $values) {
    return NULL;
  }

}

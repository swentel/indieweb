<?php

namespace Drupal\indieweb\ContactClient;

class ContactClient implements ContactClientInterface {

  /**
   * {@inheritdoc}
   */
  public function getAllContacts() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function searchContacts($search) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeContact(array $values) {}

}

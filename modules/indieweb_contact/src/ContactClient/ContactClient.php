<?php

namespace Drupal\indieweb_contact\ContactClient;

use Drupal\Core\Database\Database;
use Drupal\Core\Database\Query\Condition;
use Drupal\indieweb\ContactClient\ContactClientInterface;

class ContactClient implements ContactClientInterface {

  /**
   * {@inheritdoc}
   */
  public function getAllContacts() {
    /** @var \Drupal\indieweb_contact\Entity\ContactInterface[] $contacts */
    $contacts = \Drupal::entityTypeManager()->getStorage('indieweb_contact')->loadMultiple();
    return $this->massageReturn($contacts);
  }

  /**
   * {@inheritdoc}
   */
  public function searchContacts($search) {
    $or = new Condition('OR');
    $or->condition('name', '%' . Database::getConnection()->escapeLike($search) . '%', 'LIKE');
    $or->condition('nickname', '%' . Database::getConnection()->escapeLike($search) . '%', 'LIKE');
    $ids = \Drupal::entityQuery('indieweb_contact')
      ->condition($or)
      ->sort('name', 'ASC')
      ->execute();

    if ($ids) {
      /** @var \Drupal\indieweb_contact\Entity\ContactInterface[] $contacts */
      $contacts = \Drupal::entityTypeManager()->getStorage('indieweb_contact')->loadMultiple($ids);
      return $this->massageReturn($contacts);
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeContact(array $values) {
    if (!empty($values['name'])) {

      // Don't store duplicates.
      $contact = \Drupal::entityTypeManager()->getStorage('indieweb_contact')->loadByProperties(['name' => $values['name']]);
      if (!$contact) {

        // Get nickname if the url is from twitter.
        if (empty($values['nickname']) && !empty($values['url']) && strpos($values['url'], 'twitter.com') !== FALSE) {
          $parsed = parse_url($values['url']);
          if (!empty($parsed['path'])) {
            $values['nickname'] = str_replace('/', '', $parsed['path']);
          }
        }

        /** @var \Drupal\indieweb_contact\Entity\ContactInterface $entity */
        try {
          $entity = \Drupal::entityTypeManager()->getStorage('indieweb_contact')->create($values);
          $entity->save();
          return $entity;
        }
        catch (\Exception $ignored) {}
      }
    }

    return NULL;
  }

  /**
   * Massage contacts.
   *
   * @param \Drupal\indieweb_contact\Entity\ContactInterface[] $contacts
   *
   * @return array
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function massageReturn($contacts) {
    $return = [];
    foreach ($contacts as $contact) {
      $return[] = [
        'name' => $contact->label(),
        'nickname' => $contact->getNickname(),
        'photo' => \Drupal::service('indieweb.media_cache.client')->applyImageCache($contact->getPhoto(), 'avatar', 'contact_avatar'),
        'url' => $contact->getWebsite(),
        '_internal_url' => $contact->toUrl('canonical', ['absolute' => TRUE])->toString(),
      ];
    }
    return $return;
  }
}

<?php

namespace Drupal\indieweb_contact\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Contact edit forms.
 *
 * @ingroup contact
 */
class ContactForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label contact.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label contact.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.indieweb_contact.canonical', ['indieweb_contact' => $entity->id()]);
  }

}

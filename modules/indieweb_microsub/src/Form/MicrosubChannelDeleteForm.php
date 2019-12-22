<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubChannelDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will also delete all sources and items in this channel. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('indieweb.admin.microsub_channels', ['user' => $this->currentUser()->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    $this->messenger()->addMessage($this->t('Deleted @label.', ['@label' => $this->entity->label(),]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubChannelDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('indieweb.admin.microsub_channels');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // TODO delete all sources and items

    $this->entity->delete();
    $this->messenger()->addMessage($this->t('Deleted @label.', ['@label' => $this->entity->label(),]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

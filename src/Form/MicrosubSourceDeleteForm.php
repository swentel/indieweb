<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubSourceDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will also delete all items from this source. This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $this->entity->getChannel()]);
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

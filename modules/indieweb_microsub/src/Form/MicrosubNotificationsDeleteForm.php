<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\indieweb_microsub\Entity\MicrosubSourceInterface;

class MicrosubNotificationsDeleteForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_microsub_notifications_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all notifications?');
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
    \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->removeAllItemsBySource(0);
    $this->messenger()->addMessage($this->t('Deleted all notifications'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

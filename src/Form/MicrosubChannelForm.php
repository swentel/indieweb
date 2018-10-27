<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubChannelForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $channel = $this->entity;

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $channel->label(),
      '#description' => $this->t("Label for the channel."),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $channel->getStatus(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $channel = $this->entity;
    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', ['%label' => $channel->label(),]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $channel->label(),]));
    }
    $form_state->setRedirectUrl(Url::fromRoute('indieweb.admin.microsub_channels'));

  }

}

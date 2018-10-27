<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubSourceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\indieweb\Entity\MicrosubSourceInterface $source */
    $source = $this->entity;

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#default_value' => $source->label(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $source->getStatus(),
    ];

    // Channels
    $options = [];
    $channels = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadMultiple();
    foreach ($channels as $id => $channel) {
      $options[$channel->id()] = $channel->label();
    }

    $form['channel_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Channel'),
      '#options' => $options,
      '#default_value' => $source->getChannel(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\indieweb\Entity\MicrosubItemInterface $source */
    $source = $this->entity;
    $status = parent::save($form, $form_state);

    // TODO move items to different channel if channel has changed

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', ['%label' => $source->label(),]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $source->label(),]));
    }
    $form_state->setRedirectUrl(Url::fromRoute('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $source->getChannel()]));

  }

}

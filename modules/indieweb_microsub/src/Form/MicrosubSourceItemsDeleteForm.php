<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\indieweb_microsub\Entity\MicrosubSourceInterface;

class MicrosubSourceItemsDeleteForm extends ConfirmFormBase {

  /**
   * The source.
   *
   * @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface
   */
  protected $source;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_microsub_items_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete all items from source %source?', ['%source' => $this->source->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $this->source->getChannelId()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, MicrosubSourceInterface $indieweb_microsub_source = NULL) {
    $this->source = $indieweb_microsub_source;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::entityTypeManager()->getStorage('indieweb_microsub_item')->removeAllItemsBySource($this->source->id());
    $this->messenger()->addMessage($this->t('Deleted all items from @source.', ['@source' => $this->source->label(),]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}

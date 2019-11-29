<?php

namespace Drupal\indieweb_contact\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ContactSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_contact.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_contact_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('indieweb_contact.settings');

    $form['create_on_webmention'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('create_on_webmention'),
      '#title' => $this->t('Create a contact when a webmention is received'),
      '#disabled' => !\Drupal::moduleHandler()->moduleExists('indieweb_webmention'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_contact.settings')
      ->set('create_on_webmention', $form_state->getValue('create_on_webmention'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

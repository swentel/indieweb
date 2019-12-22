<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class IndieWebSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('indieweb.settings');
    $module_handler = \Drupal::moduleHandler();

    $disabled = FALSE;
    $description = $this->t('This will automatically set the IndieAuth, Webmention and Microsub endpoints to internal so users can be identified when webmentions arrive, creating posts or reading their feeds.<br />Instead of the homepage, the discovery of endpoints happens via the user page on user/{uid} or the alias URL if the user has one.<br />Checkout the README for the complete list of features this setting enables or changes.');
    if (!$module_handler->moduleExists('indieweb_indieauth')) {
      $disabled = TRUE;
      $description .= '<br />' . $this->t('You must enable the IndieWeb IndieAuth module to set this site to multi-user.');
    }

    $form['multi_user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('This is a multi-user site'),
      '#default_value' => $config->get('multi_user'),
      '#disabled' => $disabled,
      '#description' => $description,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.settings')
      ->set('multi_user', $form_state->getValue('multi_user'))
      ->save();

    $module_handler = \Drupal::moduleHandler();

    // Set IndieAuth endpoint.
    if ($module_handler->moduleExists('indieweb_indieauth')) {
      \Drupal::configFactory()->getEditable('indieweb_indieauth.settings')->set('auth_internal', TRUE)->save();
    }

    // Set Webmention endpoint.
    if ($module_handler->moduleExists('indieweb_webmention')) {
      \Drupal::configFactory()->getEditable('indieweb_webmention.settings')->set('webmention_internal', TRUE)->save();
    }

    // Set Microsub endpoint.
    if ($module_handler->moduleExists('indieweb_microsub')) {
      \Drupal::configFactory()->getEditable('indieweb_microsub.settings')->set('microsub_internal', TRUE)->save();
    }

    drupal_flush_all_caches();

    parent::submitForm($form, $form_state);
  }
}

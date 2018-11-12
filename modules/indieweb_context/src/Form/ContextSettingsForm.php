<?php

namespace Drupal\indieweb_context\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class ContextSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_context.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_contexts_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('indieweb_context.settings');

    $form['queue'] = [
      '#markup' => '<p>' . $this->t('Items in queue: @count', ['@count' => \Drupal::queue(INDIEWEB_POST_CONTEXT_QUEUE)->numberOfItems()]) . '</p>',
    ];

    $form['contexts'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Fetch content'),
    ];

    $form['contexts']['handler'] = [
      '#title' => t('Handler'),
      '#title_display' => 'invisible',
      '#type' => 'radios',
      '#options' => [
        'disabled' => $this->t('Disabled'),
        'cron' => $this->t('On cron run'),
        'drush' => $this->t('With drush'),
      ],
      '#default_value' => $config->get('handler'),
      '#description' => $this->t('Fetch items either by cron or drush, or disable completely.<br />The drush command is <strong>indieweb-fetch-post-contexts</strong>'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_context.settings')
      ->set('handler', $form_state->getValue('handler'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

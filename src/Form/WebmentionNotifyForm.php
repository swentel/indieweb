<?php

namespace Drupal\indieweb\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class webmentionNotifyForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webmention_notify_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['source_url'] = [
      '#title' => $this->t('Have you written a response to this? Let me know the URL'),
      '#type' => 'textfield',
      '#required' => TRUE,
    ];

    $form['target_url'] = [
      '#type' => 'value',
      '#value' => \Drupal::request()->getSchemeAndHttpHost() . \Drupal::request()->getPathInfo(),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send webmention'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!UrlHelper::isValid($form_state->getValue('source_url'))) {
      $form_state->setErrorByName('source_url', $this->t('This URL is not valid'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source_url = $form_state->getValue('source_url');
    $target_url = $form_state->getValue('target_url');
    indieweb_webmention_create_queue_item($source_url, $target_url);
    $this->t('Thanks for letting me know!');
  }

}

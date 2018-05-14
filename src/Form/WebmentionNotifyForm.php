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

    $form['source'] = [
      '#title' => $this->t('Have you written a response to this? Let me know the URL'),
      '#type' => 'textfield',
      '#required' => TRUE,
    ];

    $form['target'] = [
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
    if (!UrlHelper::isValid($form_state->getValue('source'))) {
      $form_state->setErrorByName('source', $this->t('This URL is not valid'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $source = $form_state->getValue('source');
    $target = $form_state->getValue('target');
    indieweb_webmention_create_queue_item($source, $target);
    $this->t('Thanks for letting me know!');
  }

}

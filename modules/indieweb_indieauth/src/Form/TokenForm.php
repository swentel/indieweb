<?php

namespace Drupal\indieweb_indieauth\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class TokenForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'indieweb/admin';

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthToken $token */
    $token = $this->entity;
    $form['uid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User id'),
      '#maxlength' => 255,
      '#default_value' => $token->getOwnerId(),
      '#required' => TRUE,
    ];

    $form['scope'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scope'),
      '#description' => $this->t('Separate scopes by space'),
      '#maxlength' => 255,
      '#default_value' => $token->getScopesAsString(),
    ];

    $form['me'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Me'),
      '#maxlength' => 255,
      '#default_value' => $token->getMe(),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client'),
      '#maxlength' => 255,
      '#default_value' => $token->getClientId(),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $token = $this->entity;

    /** @var \Drupal\indieweb_indieauth\Entity\IndieAuthToken $token */
    if ($token->isNew()) {
      $random = new Random();
      $access_token = $random->name(128);
      $token->set('expire', 0);
      $token->set('access_token', $access_token);
      $token->set('changed', 0);
    }

    $token->save();
    $this->messenger()->addMessage($this->t('Saved token'));
    $form_state->setRedirectUrl($token->toUrl('collection'));

    // Trigger a router rebuild.
    \Drupal::service('router.builder')->rebuild();
  }

}

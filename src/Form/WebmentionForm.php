<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Webmention edit forms.
 *
 * @ingroup webmention
 */
class WebmentionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $form['info'] = [
      '#markup' => '<p>' . $this->t('This form stores a received webmention.') . '</p>',
      '#weight' => -100,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Webmention.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Webmention.', [
          '%label' => $entity->label(),
        ]));
    }
    $form_state->setRedirect('entity.webmention_entity.canonical', ['webmention_entity' => $entity->id()]);
  }

}

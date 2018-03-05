<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Webmention edit forms.
 *
 * @ingroup webmention
 */
class WebmentionEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\indieweb\Entity\indiewebEntity */
    $form = parent::buildForm($form, $form_state);

    $entity = $this->entity;

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

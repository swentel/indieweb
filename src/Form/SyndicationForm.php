<?php

namespace Drupal\indieweb\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\indieweb\Entity\Webmention;

class SyndicationForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_syndication_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $form['entity_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Entity ID'),
    ];

    $form['entity_type_id'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The machine name of the entity type, deg. "node", "user" ..')
    ];

    $form['url'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('URL'),
      '#description' => $this->t('An external URL where this entity has been syndicated on.')
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add syndication'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = [
      'entity_id' => $form_state->getValue('entity_id'),
      'entity_type_id' => $form_state->getValue('entity_type_id'),
      'url' => $form_state->getValue('url'),
    ];

    try {
      \Drupal::database()
        ->insert('webmention_syndication')
        ->fields($values)
        ->execute();

      drupal_set_message($this->t('The syndication has been saved.'));
    }
    catch (\Exception $e) {
      $this->getLogger('indieweb_syndication')->notice('Error saving syndication: @message', ['@message' => $e->getMessage()]);
      drupal_set_message($this->t('An error occurred saving the syndication.'));
    }

    $form_state->setRedirect('indieweb.syndications_list');
  }

}

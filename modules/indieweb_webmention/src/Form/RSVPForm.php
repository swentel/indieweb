<?php

namespace Drupal\indieweb_webmention\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\indieweb_webmention\Entity\Webmention;

class RSVPForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_rsvp_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];

    $rsvp = $this->getRSVP();
    $form['rsvp'] = [
      '#title' => $this->t('RSVP'),
      '#title_display' => 'hidden',
      '#type' => 'select',
      '#required' => TRUE,
      '#default_value' => isset($rsvp->rsvp) ? $rsvp->rsvp : '',
      '#options' => [
        'yes' => 'I will attend',
        'no' => 'Can not make it',
        'maybe' => 'I might come',
        'interested' => 'I am interested',
      ],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update RSVP'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rsvp_value = $form_state->getValue('rsvp');
    $rsvp = $this->getRSVP();
    if (!empty($rsvp->id)) {
      \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->updateRSVP($rsvp_value, $rsvp->id);
    }
    else {
      $values = [
        'uid' => \Drupal::currentUser()->id(),
        'rsvp' => $rsvp_value,
        'property' => 'rsvp',
        'author_name' => \Drupal::currentUser()->getAccountName(),
        'target' => \Drupal::request()->getPathInfo(),
        'source' => \Drupal::request()->getSchemeAndHttpHost()
      ];
      $mention = \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->create($values);
      $mention->save();
    }

    drupal_set_message($this->t('Your RSVP has been updated.'));
  }

  /**
   * Gets the current RSVP status on this node for the current user.
   *
   * @return mixed
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRSVP() {
    return \Drupal::entityTypeManager()->getStorage('indieweb_webmention')->getWebmentionByTargetPropertyAndUid(\Drupal::request()->getPathInfo(), 'rsvp', \Drupal::currentUser()->id());
  }

}

<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubChannelForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $channel = $this->entity;

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $channel->label(),
      '#description' => $this->t("Label for the channel."),
      '#required' => TRUE,
    ];

    // contexts
    $form['exclude_post_type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude post types in timeline'),
      '#options' => [
        'reply' => $this->t('Replies'),
        'like' => $this->t('Reposts'),
        'repost' => $this->t('Bookmarks'),
        'bookmark' => $this->t('Likes'),
        'note' => $this->t('Notes'),
        'article' => $this->t('Articles'),
        'photo' => $this->t('Photos'),
        'video' => $this->t('Videos'),
        'checkin' => $this->t('Checkins'),
        'rsvp' => $this->t('RSVPs'),
      ],
      '#default_value' => $channel->getPostTypesToExclude(),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $channel->getStatus(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $channel = $this->entity;

    $channel->set('exclude_post_type', serialize($form_state->getValue('exclude_post_type')));

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', ['%label' => $channel->label(),]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $channel->label(),]));
    }
    $form_state->setRedirectUrl(Url::fromRoute('indieweb.admin.microsub_channels'));

  }

}

<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicrosubSourceForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source */
    $source = $this->entity;

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#maxlength' => 255,
      '#default_value' => $source->label(),
      '#required' => TRUE,
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $source->getStatus(),
    ];

    $form['cache_image_disable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable image cache'),
      '#default_value' => $source->disableImageCache(),
      '#description' => $this->t('Disable image cache for this source. Image cache is currently @status.', ['@status' => \Drupal::service('indieweb.media_cache.client')->imageCacheExternalEnabled() ? $this->t('enabled') : $this->t('disabled')]),
    ];

    // Channels
    $options = [];
    $channels = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadMultiple();
    foreach ($channels as $id => $channel) {
      $options[$channel->id()] = $channel->label();
    }

    $form['channel_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Channel'),
      '#options' => $options,
      '#default_value' => $source->getChannelId(),
    ];

    // contexts
    $form['post_context'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Get post context for'),
      '#options' => [
        'reply' => $this->t('Replies'),
        'like' => $this->t('Reposts'),
        'repost' => $this->t('Bookmarks'),
        'bookmark' => $this->t('Likes'),
      ],
      '#default_value' => $source->getPostContext(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source */
    $source = $this->entity;

    $source->set('post_context', serialize($form_state->getValue('post_context')));

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', ['%label' => $source->label(),]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $source->label(),]));
    }
    $form_state->setRedirectUrl(Url::fromRoute('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $source->getChannelId()]));

  }

}

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
      '#autocomplete_route_name' => 'indieweb_microsub.source_search',
      '#description' => $this->t('Enter a URL or use a suggestion from the autocomplete.<br />Some feeds might not return suggestions, like Instagram, but will still work fine.<br />An Instagram URL needs an ending slash too!'),
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
      '#description' => $this->t('Disable image cache for this source (avatars and pictures). Image cache is currently @status.', ['@status' => \Drupal::service('indieweb.media_cache.client')->imageCacheExternalEnabled() ? $this->t('enabled') : $this->t('disabled')]),
    ];

    // Channels
    $options = [];
    $channels = \Drupal::entityTypeManager()->getStorage('indieweb_microsub_channel')->loadMultiple();
    foreach ($channels as $id => $channel) {
      $options[$channel->id()] = $channel->label();
    }

    $default_channel = $source->getChannelId();
    if (!empty(\Drupal::request()->get('channel'))) {
      $default_channel = \Drupal::request()->get('channel');
    }
    $form['channel_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Channel'),
      '#options' => $options,
      '#default_value' => $default_channel,
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

    // WebSub integration.
    if (\Drupal::moduleHandler()->moduleExists('indieweb_websub')) {
      if ($source->usesWebSub()) {
        $form['websub_unsubscribe'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Unsubscribe WebSub'),
          '#default_value' => FALSE,
          '#weight' => 11,
          '#description' => $this->t('This source is updated via WebSub notifications. Click to unsubscribe and start polling again.')
        ];
      }
      else {
        $form['websub_subscribe'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Subscribe WebSub'),
          '#default_value' => FALSE,
          '#weight' => 11,
          '#description' => $this->t('If the feed supports WebSub, updates will come in via PuSH notifications.<br />A subscribe request will be send after submit.')
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\indieweb_microsub\Entity\MicrosubSourceInterface $source */
    $source = $this->entity;

    $source->set('post_context', serialize($form_state->getValue('post_context')));

    // Save
    $status = parent::save($form, $form_state);

    // Unsubscribe WebSub
    if ($form_state->hasValue('websub_unsubscribe') && $form_state->getValue('websub_unsubscribe')) {

      /** @var \Drupal\indieweb_websub\WebSubClient\WebSubClientInterface $websub_service */
      $websub_service = \Drupal::service('indieweb.websub.client');
      if ($info = $websub_service->discoverHub($source->label())) {
        $this->messenger()->addStatus($this->t('An unsubscribe request has been send.'));
        $websub_service->subscribe($info['self'], $info['hub'],'unsubscribe');
      }
      else {
        $this->messenger()->addStatus($this->t('No hub was found for this feed.'));
      }
    }

    // Subscribe WebSub
    if ($form_state->hasValue('websub_subscribe') && $form_state->getValue('websub_subscribe')) {
      /** @var \Drupal\indieweb_websub\WebSubClient\WebSubClientInterface $websub_service */
      $websub_service = \Drupal::service('indieweb.websub.client');
      if ($info = $websub_service->discoverHub($source->label())) {
        $source->set('url', $info['self'])->save();
        $websub_service->subscribe($info['self'], $info['hub'],'subscribe');
        $this->messenger()->addStatus($this->t('A subscribe request has been send.'));
      }
      else {
        $this->messenger()->addStatus($this->t('No hub was found for this feed.'));
      }
    }

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label', ['%label' => $source->label()]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $source->label()]));
    }
    $form_state->setRedirectUrl(Url::fromRoute('indieweb.admin.microsub_sources', ['indieweb_microsub_channel' => $source->getChannelId()]));

  }

}

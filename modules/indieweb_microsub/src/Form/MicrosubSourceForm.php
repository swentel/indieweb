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
      '#description' => $this->t('Disable image cache for this source (avatars and pictures). Image cache is currently @status.', ['@status' => \Drupal::service('indieweb.media_cache.client')->imageCacheExternalEnabled() ? $this->t('enabled') : $this->t('disabled')]),
      '#access' => \Drupal::moduleHandler()->moduleExists('indieweb_cache') && $this->currentUser()->hasPermission('disable image cache on source'),
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
    if (\Drupal::moduleHandler()->moduleExists('indieweb_websub') && $this->config('indieweb_websub.settings')->get('microsub_subscribe')) {
      if (!$source->usesWebSub()) {
        $form['websub_subscribe'] = [
          '#type' => 'value',
          '#value' => TRUE,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    // @todo Consider renaming the action key from submit to save. The impacts
    //   are hard to predict. For example, see
    //   \Drupal\language\Element\LanguageConfiguration::processLanguageConfiguration().
    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm', '::save'],
    ];

    if (!$this->entity->isNew() && $this->entity->hasLinkTemplate('delete-form')) {
      $route_info = Url::fromRoute('entity.indieweb_microsub_source.delete_form', ['user' => $this->currentUser()->id(), 'indieweb_microsub_source' => $this->entity->id()]);
      if ($this->getRequest()->query->has('destination')) {
        $query = $route_info->getOption('query');
        $query['destination'] = $this->getRequest()->query->get('destination');
        $route_info->setOption('query', $query);
      }
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
      $actions['delete']['#url'] = $route_info;
    }

    return $actions;
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

    // Subscribe WebSub
    if ($form_state->hasValue('websub_subscribe') && $form_state->getValue('websub_subscribe')) {
      /** @var \Drupal\indieweb_websub\WebSubClient\WebSubClientInterface $websub_service */
      $websub_service = \Drupal::service('indieweb.websub.client');
      if ($info = $websub_service->discoverHub($source->label())) {
        $source->set('url', $info['self'])->save();
        $websub_service->subscribe($info['self'], $info['hub'],'subscribe');
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

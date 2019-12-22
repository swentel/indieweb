<?php

namespace Drupal\indieweb_microsub\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\indieweb_microsub\Entity\MicrosubChannelInterface;

class MicrosubChannelForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\indieweb_microsub\Entity\MicrosubChannelInterface $channel */
    $channel = $this->entity;

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $channel->label(),
      '#description' => $this->t("Label for the channel."),
      '#required' => TRUE,
    ];

    // read indicator
    $form['read_indicator'] = [
      '#type' => 'select',
      '#title' => $this->t('Read tracking'),
      '#options' => [
        MicrosubChannelInterface::readIndicatorCount => $this->t('Show unread count'),
        MicrosubChannelInterface::readIndicatorNew => $this->t('Show unread indicator'),
        MicrosubChannelInterface::readIndicatorOmit => $this->t('Disabled'),
      ],
      '#default_value' => $channel->getReadIndicator()
    ];

    // contexts
    $form['exclude_post_type'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Exclude post types in timeline'),
      '#options' => [
        'reply' => $this->t('Replies'),
        'repost' => $this->t('Reposts'),
        'bookmark' => $this->t('Bookmarks'),
        'like' => $this->t('Likes'),
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
      $route_info = Url::fromRoute('entity.indieweb_microsub_channel.delete_form', ['user' => $this->currentUser()->id(), 'indieweb_microsub_channel' => $this->entity->id()]);
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

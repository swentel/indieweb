<?php

namespace Drupal\indieweb_feed\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class FeedForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'indieweb/admin';

    /** @var \Drupal\indieweb_feed\Entity\FeedInterface $feed */
    $feed = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $feed->label(),
      '#description' => $this->t("Label for the Feed."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $feed->id(),
      '#machine_name' => [
        'exists' => '\Drupal\indieweb_feed\Entity\Feed::load',
      ],
      '#disabled' => !$feed->isNew(),
    ];

    $form['feedTitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Feed title'),
      '#maxlength' => 255,
      '#default_value' => $feed->getFeedTitle(),
      '#description' => $this->t("Title for the feed. This will be printed hidden."),
      '#required' => TRUE,
    ];

    $form['path'] = [
      '#required' => TRUE,
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Path for the feed.'),
      '#default_value' => $feed->getPath(),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#default_value' => $feed->getLimit(),
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['ownerId'] = [
      '#type' => 'number',
      '#title' => $this->t('Owner id'),
      '#default_value' => $feed->getOwnerId(),
      '#description' => $this->t('The author user id for the posts.'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['excludeIndexing'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Exclude from indexing by search engines'),
      '#default_value' => $feed->excludeIndexing(),
    ];

    $form['jf2'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose JF2 feed'),
      '#default_value' => $feed->exposeJf2Feed(),
      '#description' => $this->t('Experimental'),
    ];

    $form['feedLinkTag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose feed link tag'),
      '#default_value' => $feed->exposeRelLinkTag(),
    ];

    $form['jf2LinkTag'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose JF2 feed link tag'),
      '#default_value' => $feed->exposeJf2LinkTag(),
      '#states' => array(
        'visible' => array(
          ':input[name="jf2"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['author'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Author'),
      '#description' => $this->t('Put the h-card in here for the author information which will be printed in a hidden span. Leave empty if you already have an author element on the page. Example:<br /><span class="indieweb-highlight-code">&lt;a class="u-url p-name" href="/"&gt;Your name&lt;/a&gt;&lt;img src="https://example.com/image/avatar.png" class="u-photo hidden" alt="Your name"&gt;'),
      '#default_value' => $feed->getAuthor(),
    ];

    $options = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entityType) {
      if ($entityType instanceof ContentEntityTypeInterface) {
        $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entityType->id());
        if (!empty($bundles)) {
          $label = $entityType->id();
          $options[$label] = [];
          foreach ($bundles as $key => $info) {
            $options[$label][$label . '|' . $key] = $info['label'];
          }
        }
      }
    }
    $form['bundles'] = [
      '#title' => $this->t('Bundles for this feed'),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#options' => $options,
      '#default_value' => $feed->getBundles(),
      '#size' => count($options),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $feed = $this->entity;
    $status = $feed->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created %label.', ['%label' => $feed->label(),]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved %label', ['%label' => $feed->label(),]));
    }
    $form_state->setRedirectUrl($feed->toUrl('collection'));

    // Trigger a router rebuild.
    \Drupal::service('router.builder')->rebuild();
  }

}

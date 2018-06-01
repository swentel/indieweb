<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Exception;

/**
 * Class FeedForm.
 */
class FeedForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#attached']['library'][] = 'indieweb/admin';

    /** @var \Drupal\indieweb\Entity\FeedInterface $feed */
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
        'exists' => '\Drupal\indieweb\Entity\Feed::load',
      ],
      '#disabled' => !$feed->isNew(),
    ];

    $form['feedTitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Feed title'),
      '#maxlength' => 255,
      '#default_value' => $feed->getFeedTitle(),
      '#description' => $this->t("Title for the feed. This will be printed hidden and used for atom feeds for instance"),
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

    $form['atom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Atom feed'),
      '#default_value' => $feed->exposeAtomFeed(),
      '#description' => $this->t('This uses https://granary.io by default.'),
    ];

    $form['jf2'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose JF2 feed'),
      '#default_value' => $feed->exposeJf2Feed(),
      '#description' => $this->t('Experimental'),
    ];

    $form['relHeader'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose feed header link'),
      '#default_value' => $feed->exposeRelHeaderLink(),
    ];

    $form['relHeaderAtom'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose Atom feed header link'),
      '#default_value' => $feed->exposeAtomHeaderLink(),
      '#states' => array(
        'visible' => array(
          ':input[name="atom"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['relHeaderJf2'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Expose JF2 feed header link'),
      '#default_value' => $feed->exposeJf2HeaderLink(),
      '#states' => array(
        'visible' => array(
          ':input[name="jf2"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['author'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Author'),
      '#description' => $this->t('Put the h-card in here for the author information (without span element). This will be printed in a hidden span. Example:<br /><span class="indieweb-highlight-code">&lt;span class="h-card hidden"&gt;&lt;a class="u-url p-name" href="/"&gt;Your name&lt;/a&gt;&lt;img src="https://example.com/image/avatar.png" class="u-photo hidden" alt="Your name"&gt;&lt;/span&gt;</span>'),
      '#default_value' => $feed->getAuthor(),
    ];

    $options = [];
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entityType) {
      if ($entityType instanceof ContentEntityTypeInterface) {
        $bundles = \Drupal::entityManager()->getBundleInfo($entityType->id());
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
        drupal_set_message($this->t('Created %label.', ['%label' => $feed->label(),]));
        break;

      default:
        drupal_set_message($this->t('Saved %label', ['%label' => $feed->label(),]));
    }
    $form_state->setRedirectUrl($feed->toUrl('collection'));

    // Trigger a router rebuild.
    \Drupal::service('router.builder')->rebuild();

    try {
      indieweb_update_feed_items($this->entity);
    }
    catch (Exception $ignored) {}

    Cache::invalidateTags(['rendered']);
  }

}

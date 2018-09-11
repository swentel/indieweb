<?php

namespace Drupal\indieweb\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block to display 'Webmentions'.
 *
 * @Block(
 *   id = "indieweb_webmention",
 *   admin_label = @Translation("Webmentions"),
 * )
 */
class WebmentionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_likes' => TRUE,
      'show_reposts' => FALSE,
      'show_avatar' => TRUE,
      'show_created' => FALSE,
      'number_of_posts' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['webmentions'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('Do not forget to check permissions for viewing webmentions.'),
      '#title' => $this->t('Configuration'),
    ];
    $form['webmentions']['show_likes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Likes'),
      '#default_value' => $this->configuration['show_likes'],
    ];

    $form['webmentions']['show_reposts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Reposts'),
      '#default_value' => $this->configuration['show_reposts'],
    ];

    $form['webmentions']['show_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show avatar'),
      '#default_value' => $this->configuration['show_avatar'],
      '#description' => $this->t('This will only show up if summary is enabled.'),
    ];

    $form['webmentions']['show_created'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show created time'),
      '#default_value' => $this->configuration['show_created'],
      '#description' => $this->t('This will only show up if summary is enabled.'),
    ];

    $form['webmentions']['number_of_posts'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of mentions to show'),
      '#description' => $this->t('Set to 0 to show all'),
      '#default_value' => $this->configuration['number_of_posts'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValue('webmentions');
    $this->configuration['show_likes'] = $values['show_likes'];
    $this->configuration['show_reposts'] = $values['show_reposts'];
    $this->configuration['show_avatar'] = $values['show_avatar'];
    $this->configuration['show_created'] = $values['show_created'];
    $this->configuration['number_of_posts'] = $values['number_of_posts'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $types = [
      'like-of' => 'like-of',
      'repost-of' => 'repost-of',
    ];
    if (!$this->configuration['show_likes']) {
      unset($types['like-of']);
    }
    if (!$this->configuration['show_reposts']) {
      unset($types['repost-of']);
    }

    // Return early if no types selected.
    if (empty($types)) {
      return $build;
    }

    $show_avatar = $this->configuration['show_avatar'];
    $show_created = $this->configuration['show_created'];

    $items = [];

    // Get mentions. We use a query and not entity api at all to make sure this
    // block is fast because if you have tons of webmentions, this can be rough.
    $query = \Drupal::database()
      ->select('webmention_entity', 'w')
      ->fields('w', ['author_name', 'author_photo', 'property', 'created', 'source', 'content_text', 'content_html'])
      ->condition('status', 1)
      ->condition('target', \Drupal::request()->getPathInfo())
      ->condition('property', $types, 'IN');

    $query->orderBy('id', 'DESC');

    if ($this->configuration['number_of_posts']) {
      $query->range(0, $this->configuration['number_of_posts']);
    }

    $records = $query->execute();

    foreach ($records as $record) {
      $items[] = [
        '#theme' => 'webmention',
        '#show_summary' => TRUE,
        '#show_avatar' => $show_avatar,
        '#show_created' => $show_created,
        '#property' => $record->property,
        '#author_name' => $record->author_name,
        '#author_photo' => $record->author_photo,
        '#created' => $record->created,
        '#source' => $record->source,
        '#content_text' => $record->content_text,
        '#content_html' => $record->content_html,
      ];
    }

    if (!empty($items)) {
      $build = $items;
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'view published webmention entities');
  }

}

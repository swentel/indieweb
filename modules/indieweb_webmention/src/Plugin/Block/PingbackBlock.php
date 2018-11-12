<?php

namespace Drupal\indieweb_webmention\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides a block to display 'Pingbacks'.
 *
 * @Block(
 *   id = "indieweb_pingback",
 *   admin_label = @Translation("Pingbacks"),
 * )
 */
class PingbackBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'number_of_posts' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['pingbacks'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('Do not forget to check permissions for viewing pingbacks.'),
      '#title' => $this->t('Configuration'),
    ];

    $form['pingbacks']['number_of_posts'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of pingbacks to show'),
      '#description' => $this->t('Set to 0 to show all'),
      '#default_value' => $this->configuration['number_of_posts'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValue('pingbacks');
    $this->configuration['number_of_posts'] = $values['number_of_posts'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $items = [];

    // Get pingbacks. We use a query and not entity api at all to make sure this
    // block is fast because if you have tons of webmentions, this can be rough.
    $query = \Drupal::database()
      ->select('webmention_received', 'w')
      ->fields('w', ['source'])
      ->condition('status', 1)
      ->condition('target', \Drupal::request()->getPathInfo())
      ->condition('property', 'pingback');

    $query->orderBy('id', 'DESC');

    if ($this->configuration['number_of_posts']) {
      $query->range(0, $this->configuration['number_of_posts']);
    }

    $records = $query->execute();

    foreach ($records as $record) {

      $items[] = [
        '#type' => 'link',
        '#title' => $record->source,
        '#url' => Url::fromUri($record->source),
      ];

    }

    if (!empty($items)) {
      $build['pingbacks'] = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
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

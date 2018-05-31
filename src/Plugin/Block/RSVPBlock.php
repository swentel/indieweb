<?php

namespace Drupal\indieweb\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides a block to display 'RSVP's'.
 *
 * @Block(
 *   id = "indieweb_rsvp",
 *   admin_label = @Translation("RSVP"),
 * )
 */
class RSVPBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'show_avatar' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['rsvp'] = [
      '#type' => 'fieldset',
      '#description' => $this->t('Do not forget to check permissions for viewing webmentions.'),
      '#title' => $this->t('Configuration'),
    ];

    $form['rsvp']['show_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show avatar'),
      '#default_value' => $this->configuration['show_avatar'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValue('rsvp');
    $this->configuration['show_avatar'] = $values['show_avatar'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $types = [
      'rsvp' => 'rsvp',
    ];

    // Get mentions.
    $query = \Drupal::entityQuery('webmention_entity')
      ->condition('target', \Drupal::request()->getPathInfo())
      ->condition('property', $types, 'IN');
    $ids = $query->execute();

    $values = [
      0 => 'yes', 1 => 'maybe', 2 => 'interested', 3 => 'no',
    ];
    $values_array = [];

    if (!empty($ids)) {

      $show_avatar = $this->configuration['show_avatar'];

      /** @var \Drupal\indieweb\Entity\WebmentionInterface $mention */
      $mentions = \Drupal::entityTypeManager()->getStorage('webmention_entity')->loadMultiple($ids);
      foreach ($mentions as $mention) {

        $image = '';
        if ($show_avatar && !($mention->get('author_photo')->isEmpty())) {
          $image = '<img width="40" src="' . $mention->get('author_photo')->value . '" />&nbsp;';
        }

        $rsvp = $mention->get('rsvp')->value;

        $values_array[$rsvp][] = [
          '#markup' => $image . $mention->get('author_name')->value,
          '#allowed_tags' => ['img']
        ];

      }

      foreach ($values as $weight => $value) {
        if (!empty($values_array[$value])) {
          $build[$value] = [
            '#title' => ucfirst($value),
            '#weight' => $weight,
            '#theme' => 'item_list',
            '#items' => $values_array[$value],
          ];
        }
      }


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

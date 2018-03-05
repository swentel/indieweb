<?php

namespace Drupal\indieweb\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
      'number_of_posts' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['webmentions'] = [
      '#type' => 'fieldset',
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
      '#title' => $this->t('Whether to show the avatar or not'),
      '#default_value' => $this->configuration['show_avatar'],
    ];

    $form['webmentions']['number_of_posts'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of mentions to show'),
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

    // Get mentions.
    $query = \Drupal::entityQuery('webmention_entity')
      ->condition('target', \Drupal::request()->getRequestUri())
      ->condition('property', $types, 'IN')
      ->range(0, $this->configuration['number_of_posts']);

    $ids = $query->execute();

    if (!empty($ids)) {

      $show_avatar = $this->configuration['show_avatar'];
      $items = [];

      /** @var \Drupal\indieweb\Entity\WebmentionEntityInterface $mention */
      $mentions = \Drupal::entityTypeManager()->getStorage('webmention_entity')->loadMultiple($ids);
      foreach ($mentions as $mention) {

        $image = '';
        if ($show_avatar && !($mention->get('author_photo')->isEmpty())) {
          $image = '<img width="40" src="' . $mention->get('author_photo')->value . '" />&nbsp;';
        }

        $type = 'Liked by ';
        if ($mention->get('property')->value == 'repost-of') {
          $type = 'Reposted by ';
        }

        $items[] = [
          '#markup' => $image . $type . $mention->get('author_name')->value,
          '#allowed_tags' => ['img']
        ];

      }

      $build['webmentions'] = [
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
    // TODO cache per URL is probably better.
    return 0;
  }

}

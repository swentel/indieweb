<?php

namespace Drupal\indieweb_webmention\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block to display the 'Webmentions notify form'.
 *
 * @Block(
 *   id = "indieweb_webmention_notify",
 *   admin_label = @Translation("Webmention notify form"),
 * )
 */
class WebmentionNotifyFormBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\indieweb_webmention\Form\WebmentionNotifyForm');
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}

<?php

namespace Drupal\indieweb_indieauth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block to login with your domain..
 *
 * @Block(
 *   id = "indieweb_indieauth_login",
 *   admin_label = @Translation("Web sign-in"),
 * )
 */
class IndieAuthLoginBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $render_form = TRUE;

    if (\Drupal::currentUser()->isAuthenticated()) {
      /** @var \Drupal\externalauth\AuthmapInterface $external_authmap */
      $external_authmap = \Drupal::service('externalauth.authmap');
      if ($external_authmap && $external_authmap->get(\Drupal::currentUser()->id(), 'indieweb')) {
        $render_form = FALSE;
      }
    }

    if ($render_form) {
      $build['form'] = \Drupal::formBuilder()->getForm('Drupal\indieweb_indieauth\Form\IndieAuthLoginForm');
    }
    return $build;
  }

}

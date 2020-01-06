<?php

namespace Drupal\indieweb_indieauth\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
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
class IndieAuthLoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Set the form builder.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder (e.g. from the container).
   */
  public function setFormBuilder(FormBuilderInterface $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [
      'form' => $this->formBuilder->getForm('Drupal\indieweb_indieauth\Form\IndieAuthLoginForm'),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\indieweb_indieauth\Plugin\Block\IndieAuthLoginBlock $block */
    $block = new static($configuration, $plugin_id, $plugin_definition);
    $block->setFormBuilder($container->get('form_builder'));

    return $block;
  }

}

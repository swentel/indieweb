<?php

namespace Drupal\indieweb\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a block to display an Author h-card.
 *
 * @Block(
 *   id = "indieweb_author",
 *   admin_label = @Translation("Author"),
 * )
 */
class AuthorBlock extends BlockBase {

    /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'name' => '',
      'image' => '',
      'note' => '',
    ];
  }

    /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $form['name'] = [
      '#title' => $this->t('Name'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->configuration['name'],
      '#description' => $this->t('Enter your author name. This does not have to your real name, a nickname is totally fine too!')
    ];

    $form['image'] = [
      '#title' => $this->t('Avatar'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['image'],
      '#description' => $this->t('Enter a full URL to your avatar.')
    ];

    $form['note'] = [
      '#title' => $this->t('Info'),
      '#type' => 'textarea',
      '#description' => t('Enter information about yourself. This is optional, the author h-card will still work.<br />You can also enter rel="me" links here for IndieAuth detection.<br />The content of this note will be rendered with the Restricted HTML content format that comes in Drupal 8.<br />For more information about h-card, see <a href="https://indieweb.org/h-card" target="_blank">https://indieweb.org/h-card</a>'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['name'] = $values['name'];
    $this->configuration['image'] = $values['image'];
    $this->configuration['note'] = $values['note'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $markup = 'ale jong';
    return [
      '#theme' => 'indieweb_author',
      '#name' => $this->configuration['name'],
      '#note' => check_markup($markup, 'restricted_html'),
      '#image' => $this->configuration['image'],
      '#domain' => \Drupal::request()->getSchemeAndHttpHost(),
    ];

  }

}
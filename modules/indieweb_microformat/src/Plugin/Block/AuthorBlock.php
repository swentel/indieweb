<?php

namespace Drupal\indieweb_microformat\Plugin\Block;

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

    if (!indieweb_is_multi_user()) {
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
        '#default_value' => $this->configuration['note'],
        '#description' => t('Enter information about yourself. This is optional, the author h-card will still work.<br />You can also enter rel="me" links here for IndieAuth detection.<br />The content of this note will be rendered with the Restricted HTML content format that comes in Drupal 8.<br />For more information about h-card, see <a href="https://indieweb.org/h-card" target="_blank">https://indieweb.org/h-card</a>'),
      ];
    }
    else {
      $form['info'] = ['#markup' => '<p><strong>' . $this->t('The site is set to multiple users. Every user will be able to set the name, avatar and note on their user profile. The visibility of the block will also be handled automatically and will only be rendered on the user profile page.') . '</strong></p>'];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (indieweb_is_multi_user()) {
      $this->configuration['name'] = $values['name'];
      $this->configuration['image'] = $values['image'];
      $this->configuration['note'] = $values['note'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $render = FALSE;
    $name = $note = $image = $domain = '';

    if (indieweb_is_multi_user()) {
      if (\Drupal::routeMatch()->getRouteName() == 'entity.user.canonical') {
        $render = TRUE;

        /** @var \Drupal\user\UserInterface $account */
        $account = \Drupal::routeMatch()->getParameter('user');

        // Name.
        $name = $account->getAccountName();

        // Note.
        if ($note = \Drupal::service('user.data')->get('indieweb_microformat', $account->id(), 'note')) {
          $note = check_markup($note, 'restricted_html');
        }

        // Avatar.
        if (!empty($account->user_picture)) {
          /** @var \Drupal\file\FileInterface $file */
          $file = $account->get('user_picture')->entity;
          if ($file) {
            $image = file_create_url($file->getFileUri());
          }
        }

        // Domain.
        $domain = $account->toUrl('canonical', ['absolute' => TRUE])->toString();
      }
    }
    else {
      $render = TRUE;
      $name = $this->configuration['name'];
      $note = check_markup($this->configuration['note'], 'restricted_html');
      $image = $this->configuration['image'];
      $domain = \Drupal::request()->getSchemeAndHttpHost();
    }

    if ($render) {
      return [
        '#theme' => 'indieweb_author',
        '#name' => $name,
        '#note' => $note,
        '#image' => $image,
        '#domain' => $domain,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}

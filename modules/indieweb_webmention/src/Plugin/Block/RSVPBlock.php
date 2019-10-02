<?php

namespace Drupal\indieweb_webmention\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

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
      'allow_user_rsvp' => FALSE,
      'node_type' => '',
      'node_daterange_field' => '',
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

    $form['rsvp']['allow_user_rsvp'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow RSVP'),
      '#description' => $this->t('Drupal users with the "allow rsvp on event" permission will be able to RSVP when authenticated.'),
      '#default_value' => $this->configuration['allow_user_rsvp'],
    ];

    $form['rsvp']['node_type'] = [
      '#type' => 'select',
      '#options' => ['' => ''] + node_type_get_names(),
      '#title' => $this->t('Event node type'),
      '#description' => $this->t('Select the event node type.'),
      '#default_value' => $this->configuration['node_type'],
      '#states' => array(
        'visible' => array(
          ':input[name="settings[rsvp][allow_user_rsvp]"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $date_range_fields = [];
    $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $field */
    foreach ($fields as $key => $field) {
      if (in_array($field->getType(), ['daterange'])) {
        $date_range_fields[$key] = 'Date range: ' . $field->getName();
      }
    }
    $form['rsvp']['node_daterange_field'] = [
      '#type' => 'select',
      '#options' => ['' => ''] + $date_range_fields,
      '#title' => $this->t('Event daterange field'),
      '#description' => $this->t('If the event has passed, RSVP will not be allowed anymore.'),
      '#default_value' => $this->configuration['node_daterange_field'],
      '#states' => array(
        'visible' => array(
          ':input[name="settings[rsvp][allow_user_rsvp]"]' => array('checked' => TRUE),
        ),
      ),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $values = $form_state->getValue('rsvp');
    $this->configuration['show_avatar'] = $values['show_avatar'];
    $this->configuration['allow_user_rsvp'] = $values['allow_user_rsvp'];
    $this->configuration['node_type'] = $values['node_type'];
    $this->configuration['node_daterange_field'] = $values['node_daterange_field'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    $types = [
      'rsvp' => 'rsvp',
    ];

    // RSVP on-site.
    if ($this->configuration['allow_user_rsvp'] && !empty($this->configuration['node_type']) && !empty($this->configuration['node_daterange_field']) && \Drupal::routeMatch()->getRouteName() == 'entity.node.canonical') {
      /** @var \Drupal\node\NodeInterface $node */
      $node = \Drupal::routeMatch()->getParameter('node');
      if ($node && $node->bundle() == $this->configuration['node_type'] && $node->hasField($this->configuration['node_daterange_field'])) {
        $date = $node->get($this->configuration['node_daterange_field'])->getValue();
        if (!empty($date[0]['end_value']) && strtotime($date[0]['end_value']) > \Drupal::time()->getCurrentTime()) {
          if (\Drupal::currentUser()->isAuthenticated() && \Drupal::currentUser()->hasPermission('allow rsvp')) {
            $build['rsvp_form'] = \Drupal::formBuilder()->getForm('Drupal\indieweb_webmention\Form\RSVPForm');
          }
          else {
            $build['sign_in'] = [
              '#markup' => '<br /><p>' . $this->t('<a href=":signin">Sign in</a> to RSVP to this event', [':signin' => Url::fromRoute('user.login')->toString()]) . '</p>',
              '#weight' => 100,
            ];
          }
        }
      }
    }

    // Get mentions. We use a query and not entity api at all to make sure this
    // block is fast because if you have tons of webmentions, this can be rough.
    $records = \Drupal::entityTypeManager()
      ->getStorage('indieweb_webmention')
      ->getWebmentions($types, \Drupal::request()->getPathInfo());

    $values = [
      0 => 'yes', 1 => 'maybe', 2 => 'interested', 3 => 'no',
    ];
    $values_array = [];
    $show_avatar = $this->configuration['show_avatar'];

    foreach ($records as $record) {

      $image = '';
      if ($show_avatar && !empty($record->author_photo)) {
        $image = '<img width="40" src="' . \Drupal::service('indieweb.media_cache.client')->applyImageCache($record->author_photo, 'avatar', 'webmention_avatar') . '" />&nbsp;';
      }

      $rsvp = $record->rsvp;
      $values_array[$rsvp][] = [
        '#markup' => $image . $record->author_name,
        '#allowed_tags' => ['img']
      ];
    }

    foreach ($values as $weight => $value) {
      if (!empty($values_array[$value])) {
        $build['rsvps'][$value] = [
          '#title' => ucfirst($value),
          '#weight' => $weight,
          '#theme' => 'item_list__indieweb_rsvp',
          '#items' => $values_array[$value],
        ];
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

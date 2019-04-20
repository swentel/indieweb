<?php

namespace Drupal\indieweb_cache\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CacheSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_cache.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_cache_settings_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('indieweb_cache.settings');
    $module_handler = \Drupal::moduleHandler();

    $form['cache'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cache'),
    ];

    $form['cache']['enable'] = [
      '#title' => $this->t('Enable'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('enable'),
      '#description' => $this->t('Leave this disabled if you are not using the built-in webmention or microsub endpoint.')
    ];

    $form['imagecache_external'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Imagecache external'),
      '#states' => array(
        'visible' => array(
          ':input[name="enable"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $imagecache_external_module_enabled = $module_handler->moduleExists('imagecache_external');
    $form['imagecache_external']['use_imagecache_external'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('use_imagecache_external'),
      '#title' => $this->t('Use imagecache external'),
      '#disabled' => !$imagecache_external_module_enabled,
      '#description' => $this->t('Uses the Imagecache external module to download image files locally. This is applied to author avatars and images in webmentions and microsub posts.'),
    ];

    $form['imagecache_external']['image_style_avatar'] = [
      '#type' => 'select',
      '#options' => image_style_options(),
      '#default_value' => $config->get('image_style_avatar'),
      '#title' => $this->t('Image style for avatars'),
      '#disabled' => !$imagecache_external_module_enabled,
      '#description' => $this->t('Select an image style to apply to an avatar.'),
      '#states' => array(
        'visible' => array(
          ':input[name="use_imagecache_external"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['imagecache_external']['image_style_photo'] = [
      '#type' => 'select',
      '#options' => image_style_options(),
      '#default_value' => $config->get('image_style_photo'),
      '#title' => $this->t('Image style for photos'),
      '#disabled' => !$imagecache_external_module_enabled,
      '#description' => $this->t('Select an image style to apply to any photo.'),
      '#states' => array(
        'visible' => array(
          ':input[name="use_imagecache_external"]' => array('checked' => TRUE),
        ),
      ),
    ];

    if (!$imagecache_external_module_enabled) {
      $form['imagecache_external']['use_imagecache_external']['#description'] = $this->t('You need to install the <a href="https://www.drupal.org/project/imagecache_external" target="_blank">Imagecache external</a> module for this feature to work.');
    }

    $form['imagecache_external']['ignore_webmention_io'] = [
      '#title' => $this->t('Ignore Webmention IO'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('ignore_webmention_io'),
      '#description' => $this->t('If you previously used Webmention IO, the images for author avatars already are cached in a proxy there. Toggle this to keep using that proxy.'),
      '#states' => array(
        'visible' => array(
          ':input[name="use_imagecache_external"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['imagecache_external']['protect_webmention_image_from_flush'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('protect_webmention_image_from_flush'),
      '#title' => $this->t('Protect webmention images from flush'),
      '#disabled' => !$imagecache_external_module_enabled,
      '#description' => $this->t('Stores images in webmentions outside the imagecache external directory so they never need to be downloaded again. Enable this if you periodically flush the imagecache externals directory.'),
      '#states' => array(
        'visible' => array(
          ':input[name="use_imagecache_external"]' => array('checked' => TRUE),
        ),
      ),
    ];

    $form['imagecache_external']['protect_post_context_image_from_flush'] = [
      '#type' => 'checkbox',
      '#default_value' => $config->get('protect_post_context_image_from_flush'),
      '#title' => $this->t('Protect post context images from flush'),
      '#disabled' => !$imagecache_external_module_enabled,
      '#description' => $this->t('Stores images in post contexts outside the imagecache external directory so they never need to be downloaded again. Enable this if you periodically flush the imagecache externals directory.'),
      '#states' => array(
        'visible' => array(
          ':input[name="use_imagecache_external"]' => array('checked' => TRUE),
        ),
      ),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_cache.settings')
      ->set('enable', $form_state->getValue('enable'))
      ->set('use_imagecache_external', $form_state->getValue('use_imagecache_external'))
      ->set('protect_webmention_image_from_flush', $form_state->getValue('protect_webmention_image_from_flush'))
      ->set('protect_post_context_image_from_flush', $form_state->getValue('protect_post_context_image_from_flush'))
      ->set('image_style_avatar', $form_state->getValue('image_style_avatar'))
      ->set('image_style_photo', $form_state->getValue('image_style_photo'))
      ->set('ignore_webmention_io', $form_state->getValue('ignore_webmention_io'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }
}
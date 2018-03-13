<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class MicroformatsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb.microformats'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'microformats_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attached']['library'][] = 'indieweb/admin';

    $config = $this->config('indieweb.microformats');

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Microformats are extensions to HTML for marking up people, organizations, events, locations, blog posts, products, reviews, resumes, recipes etc. Sites use microformats to publish a standard API that is consumed and used by search engines, aggregators, and other tools. See <a href="https://indieweb.org/microformats" target="_blank">https://indieweb.org/microformats</a> for more info. You will want to enable this if you want to publish. The module will add classes on content, images etc. You can also add it to the markup yourself.') . '</p>',
    ];

    $form['classes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Classes to add on elements'),
      '#description' => $this->t('You might need to clear all caches before markup is added or removed when saving this form.')
    ];

    $form['classes']['h_entry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>h-entry</em> on node wrappers. This will be done on the full and teaser view mode.'),
      '#default_value' => $config->get('h_entry'),
    ];

    $form['classes']['e_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>e-content</em> on standard body fields.'),
      '#default_value' => $config->get('e_content'),
    ];

    $form['classes']['u_photo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>u-photo</em> on all image style links.'),
      '#default_value' => $config->get('u_photo'),
    ];

    $form['classes']['p_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('<em>p-summary</em>'),
      '#default_value' => $config->get('p_summary'),
      '#description' => $this->t('This class is handy to limit the text to publish on social networks. Twitter has a limit of 280 chars, so by having a summary field, you can have better experience.<br />Enter the machine names of the fields line per line you want to use as summary fields.')
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.microformats')
      ->set('h_entry', $form_state->getValue('h_entry'))
      ->set('e_content', $form_state->getValue('h_entry'))
      ->set('u_photo', $form_state->getValue('u_photo'))
      ->set('p_summary', trim($form_state->getValue('p_summary')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}

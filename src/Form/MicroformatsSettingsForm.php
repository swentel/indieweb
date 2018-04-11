<?php

namespace Drupal\indieweb\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

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
    return 'indieweb_microformats_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('indieweb.microformats');

    $form['#attached']['library'][] = 'indieweb/admin';

    $form['info'] = [
      '#markup' => '<p>' . $this->t('Microformats are extensions to HTML for marking up people, organizations, events, locations, blog posts, products, reviews, resumes, recipes etc. Sites use microformats to publish a standard API that is consumed and used by search engines, aggregators, and other tools. See <a href="https://indieweb.org/microformats" target="_blank">https://indieweb.org/microformats</a> for more info. You will want to enable this if you want to publish or want other sites and readers to parse your content. The module will add classes on content, images etc. You can also add it to the markup yourself. Also read <a href="https://brid.gy/about#microformats">https://brid.gy/about#microformats</a> for details how Bridgy decides what to publish if you are using that service.<br /><br />Your homepage should also contain a h-card entry. This module does not expose this for you. An example:<br />
        <div class="indieweb-highlight-code">&lt;div class="h-card"&gt;My name is &lt;a class="u-url p-name" rel="me" href="/"&gt;Your name&lt;/a&gt;&lt;/div&gt;</div>') . '</p>',
    ];

    $form['classes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Classes to add on elements'),
    ];

    $form['classes']['h_entry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>h-entry</em> on node wrappers.'),
      '#default_value' => $config->get('h_entry'),
      '#description' => $this->t('This will be added on full, teaser and microformat view mode.'),
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

    $form['classes']['post_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>dt-published</em>, <em>p-name</em>, <em>u-author</em> and <em>u-url</em> in a hidden span element.'),
      '#description' => $this->t('This will be added on full, teaser and microformat view mode. Make sure \'Display author and date information\' is enabled, or put {{ metadata }} in your node template. Example:<br /><div class="indieweb-highlight-code">&lt;span class="hidden"&gt;&lt;a href="http://url" class="u-url"&gt;&lt;span class="p-name"&gt;title&lt;/span&gt;&lt;span class="dt-published"&gt;2018-01-31T20:38:25+01:00&lt;/span&gt;&lt;/a&gt;&lt;a class="u-author" href="/"&gt;&lt;/a&gt;&lt;/span&gt;</div>'),
      '#default_value' => $config->get('post_metadata'),
    ];

    $form['classes']['p_name_exclude_node_type'] = [
      '#title' => $this->t('Exclude p-name'),
      '#type' => 'select',
      '#options' => ['' => 'None'] + node_type_get_names(),
      '#default_value' => $config->get('p_name_exclude_node_type'),
      '#description' => $this->t('The "p-name" class should not be printed on "notes". Think of it as a tweet, they do not have titles either. Your website can render a title, but microformat parsers should not discover it.'),
    ];

    $form['classes']['p_bridgy_twitter_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>p-bridgy-twitter-content</em>'),
      '#default_value' => $config->get('p_bridgy_twitter_content'),
      '#description' => $this->t('For original tweets, Bridgy prefers p-name over e-content. If you enable the node metadata, but still want to control that p-summary or e-content (in that order) is used, enable this class.<br />This class is printed even if you did not publish to Twitter, that will be altered later.')
    ];

    $form['classes']['p_summary'] = [
      '#type' => 'textarea',
      '#title' => $this->t('<em>p-summary</em>'),
      '#default_value' => $config->get('p_summary'),
      '#description' => $this->t('This class is handy to limit the text to publish on social networks. Twitter has a limit of 280 chars, so by having a summary field, you can have better experience, especially for blog posts.<br />Enter the machine names of the fields line per line you want to use as summary fields.')
    ];

    $form['classes']['other'] = [
      '#type' => 'item',
      '#title' => $this->t('Formatters'),
      '#markup' => 'To add "p-category" classes on tags, you can use the "Label link with p-category class" formatter.<br />To add "u-like-of" or "u-bookmark-of" on links, use the "Microformat link" formatter.',
      '#description' => 'Go to the "Manage display" pages and select the formatter you want to use.',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb.microformats')
      ->set('h_entry', $form_state->getValue('h_entry'))
      ->set('post_metadata', $form_state->getValue('post_metadata'))
      ->set('p_name_exclude_node_type', $form_state->getValue('p_name_exclude_node_type'))
      ->set('e_content', $form_state->getValue('h_entry'))
      ->set('u_photo', $form_state->getValue('u_photo'))
      ->set('p_summary', trim($form_state->getValue('p_summary')))
      ->set('p_bridgy_twitter_content', $form_state->getValue('p_bridgy_twitter_content'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

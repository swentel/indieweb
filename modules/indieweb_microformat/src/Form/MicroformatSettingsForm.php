<?php

namespace Drupal\indieweb_microformat\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MicroformatSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['indieweb_microformat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'indieweb_microformat_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $comment_enabled = \Drupal::moduleHandler()->moduleExists('comment');

    $config = $this->config('indieweb_microformat.settings');

    $form['#attached']['library'][] = 'indieweb/admin';

    $form['classes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Classes to add on elements'),
    ];

    $form['classes']['h_entry'] = [
      '#prefix' => '<p>' . $this->t('Microformats will be applied to the "full" and "Microformat" view mode of nodes and the "Microformat" view mode of comments which is available on /comment/indieweb/{cid}.') . '</p>',
      '#type' => 'checkbox',
      '#title' => $this->t('<em>h-entry</em> on node wrappers'),
      '#default_value' => $config->get('h_entry'),
    ];

    $form['classes']['h_entry_comment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>h-entry</em> on comment wrappers'),
      '#default_value' => $config->get('h_entry_comment'),
      '#description' => $this->t('This will be added on the microformat view mode on comment/indieweb/id'),
    ];

    $form['classes']['h_event'] = [
      '#type' => 'select',
      '#title' => $this->t('<em>h-event</em> on node wrappers'),
      '#default_value' => $config->get('h_event'),
      '#options' => ['' => $this->t('No event')] + node_type_get_names(),
      '#description' => $this->t('h-event for an event node type.<br />This will also add dt-start and dt-end classes on the date range fields. (date fields are not supported)'),
    ];

    $form['classes']['e_content'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>e-content</em> on standard body fields'),
      '#default_value' => $config->get('e_content'),
    ];

    $form['classes']['e_content_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('<em>e-content</em> on other textarea fields'),
      '#description' => $this->t('Use this to define other fields than "body" where the "e-content" class should be applied to.<br />Note, your content type should only contain one of those fields, including body!'),
      '#default_value' => $config->get('e_content_fields'),
    ];

    $form['classes']['e_content_comment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>e-content</em> on standard comment body fields'),
      '#default_value' => $config->get('e_content_comment'),
      '#access' => $comment_enabled,
    ];

    $form['classes']['u_photo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>u-photo</em> on image styles'),
      '#default_value' => $config->get('u_photo'),
    ];

    $form['classes']['post_metadata'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>dt-published</em>, <em>p-name</em>, <em>u-author</em> and <em>u-url</em> in a hidden span element on nodes.'),
      '#description' => $this->t('Make sure \'Display author and date information\' is enabled, or put {{ metadata }} in your node template. Example:<br /><div class="indieweb-highlight-code">&lt;span class="hidden"&gt;&lt;a href="http://url" class="u-url"&gt;&lt;span class="p-name"&gt;title&lt;/span&gt;&lt;span class="dt-published"&gt;2018-01-31T20:38:25+01:00&lt;/span&gt;&lt;/a&gt;&lt;a class="u-author" href="/"&gt;&lt;/a&gt;&lt;/span&gt;</div>'),
      '#default_value' => $config->get('post_metadata'),
    ];

    $form['classes']['post_metadata_comment'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>dt-published</em>, <em>u-author</em> and <em>u-url</em> in a hidden span element on comments.'),
      '#description' => $this->t('p-name will always be excluded. Example:<br /><div class="indieweb-highlight-code">&lt;span class="hidden"&gt;&lt;a href="http://url" class="u-url"&gt;&lt;span class="dt-published"&gt;2018-01-31T20:38:25+01:00&lt;/span&gt;&lt;/a&gt;&lt;a class="u-author" href="/"&gt;&lt;/a&gt;&lt;/span&gt;</div>'),
      '#default_value' => $config->get('post_metadata_comment'),
    ];

    $form['classes']['provide_iso_datetime_variable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Provide <code>iso_datetime</code> template variable'),
      '#description' => $this->t('As an alternative to the post metadata options above (<em>dt-published</em>, <em>u-author</em> and <em>u-url</em> in a hidden span) you can provide equivalent markup in your node or comment templates yourself — with the help of this variable; include it in your template for example like this:<br /><div class="indieweb-highlight-code">&lt;time class="dt-published" datetime="{{ iso_datetime }}"&gt;{{ date }}&lt;/time&gt;</div>'),
      '#default_value' => $config->get('provide_iso_datetime_variable'),
    ];

    $form['classes']['u_video'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>u-video</em> on all videos'),
      '#default_value' => $config->get('u_video'),
    ];

    $form['classes']['u_audio'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<em>u-audio</em> on all audio'),
      '#default_value' => $config->get('u_audio'),
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

    $form['classes']['ds_node_support'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Display Suite node support'),
      '#description' => $this->t('If a node is rendered with a Display Suite layout, add the classes like "h-entry", "h-event" on those layouts. You will need to alter the templates to add the metadata content.'),
      '#default_value' => $config->get('ds_node_support'),
      '#disabled' => !\Drupal::moduleHandler()->moduleExists('ds'),
    ];

    $form['classes']['other'] = [
      '#type' => 'item',
      '#title' => $this->t('Formatters'),
      '#markup' => 'To add "p-category" classes on tags, you can use the "Label link with p-category class" formatter.<br />To add "u-like-of", "u-bookmark-of", "u-in-reply-to" (use in reply, rsvp)  or "u-repost-of" on links, use the "Microformat link" formatter.<br />To add the "p-rsvp" class, use the "Microformat RSVP" formatter. For more information about RSVP, go to the Micropub configuration screen for RSVP so you know what kind of field you need to create.<br />To add (basic) checkin markup, use the Microformat checkin formatter on a link field. On that formatter, you can also select the geofield for latitude and longitude.<br />To add (basic) geo markup, use the Microformat Geo formatter on a geofield field. This can serve as an example how to markup up geo information.',
      '#description' => 'Go to the "Manage display" pages and select the formatter you want to use. ',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('indieweb_microformat.settings')
      ->set('h_entry', $form_state->getValue('h_entry'))
      ->set('h_entry_comment', $form_state->getValue('h_entry_comment'))
      ->set('h_event', $form_state->getValue('h_event'))
      ->set('post_metadata', $form_state->getValue('post_metadata'))
      ->set('post_metadata_comment', $form_state->getValue('post_metadata_comment'))
      ->set('provide_iso_datetime_variable', $form_state->getValue('provide_iso_datetime_variable'))
      ->set('p_name_exclude_node_type', $form_state->getValue('p_name_exclude_node_type'))
      ->set('e_content', $form_state->getValue('e_content'))
      ->set('e_content_fields', $form_state->getValue('e_content_fields'))
      ->set('e_content_comment', $form_state->getValue('e_content_comment'))
      ->set('u_photo', $form_state->getValue('u_photo'))
      ->set('u_video', $form_state->getValue('u_video'))
      ->set('u_audio', $form_state->getValue('u_audio'))
      ->set('p_summary', trim($form_state->getValue('p_summary')))
      ->set('p_bridgy_twitter_content', $form_state->getValue('p_bridgy_twitter_content'))
      ->set('ds_node_support', $form_state->getValue('ds_node_support'))
      ->save();

    Cache::invalidateTags(['rendered']);

    parent::submitForm($form, $form_state);
  }

}

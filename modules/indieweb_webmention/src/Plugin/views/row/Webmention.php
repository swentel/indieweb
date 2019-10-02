<?php

namespace Drupal\indieweb_webmention\Plugin\views\row;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the webmention through the template.
 *
 * @ViewsRow(
 *   id = "webmention_template",
 *   title = @Translation("Webmention template"),
 *   help = @Translation("Display the webmention through the webmention template"),
 *   register_theme = FALSE,
 *   base = {"webmention_received"},
 *   display_types = {"normal"}
 * )
 */
class Webmention extends RowPluginBase {

  // Basic properties that let the row style follow relationships.
  public $base_table = 'webmention_received';

  public $base_field = 'id';

  // Stores the webmentions loaded with preRender.
  public $webmentions = [];

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'indieweb_webmention';

  /**
   * The node storage
   *
   * @var \Drupal\indieweb_webmention\Entity\Storage\WebmentionStorageInterface
   */
  protected $webmentionStorage;

  /**
   * Constructs the Webmention object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
    $this->webmentionStorage = $entity_type_manager->getStorage('indieweb_webmention');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['show_summary'] = ['default' => FALSE];
    $options['show_avatar'] = ['default' => FALSE];
    $options['show_created'] = ['default' => FALSE];
    $options['show_photo'] = ['default' => FALSE];
    $options['replace_comment_user_picture'] = ['default' => FALSE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['show_summary'] = [
      '#title' => t('Show summary'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_summary'],
      '#description' => $this->t('Shows meta information, like author and created time.'),
    ];

    $form['show_avatar'] = [
      '#title' => t('Show avatar'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_avatar'],
      '#description' => $this->t('This will only show up if summary is enabled.'),
    ];

    $form['show_created'] = [
      '#title' => t('Show created time'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_created'],
      '#description' => $this->t('This will only show up if summary is enabled.'),
    ];

    $form['show_photo'] = [
      '#title' => t('Show photo'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['show_photo'],
      '#description' => $this->t('This will be rendered by default above the content'),
    ];

    $form['replace_comment_user_picture'] = [
      '#title' => t('Replace comment user picture'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['replace_comment_user_picture'],
      '#description' => $this->t('If the webmention has a picture from the author, move it into the user picture element of the comment template.'),
    ];

  }

  public function preRender($values) {
    $ids = [];
    foreach ($values as $row) {
      $ids[] = $row->{$this->field_alias};
    }
    if (!empty($ids)) {
      $this->webmentions = $this->webmentionStorage->loadMultiple($ids);
    }
  }

  public function render($row) {

    $id = $row->{$this->field_alias};
    if (!is_numeric($id)) {
      return;
    }

    // Get the specified webmention.
    /** @var \Drupal\indieweb_webmention\Entity\WebmentionInterface $webmention */
    $webmention = $this->webmentions[$id];
    if (empty($webmention)) {
      return;
    }

    $suggestion = !empty($webmention->getProperty()) ? '__' . str_replace('-', '_', $webmention->getProperty()) : '';
    $build = [
      '#theme' => 'webmention' . $suggestion,
      '#show_summary' => $this->options['show_summary'],
      '#show_avatar' => $this->options['show_avatar'],
      '#show_created' => $this->options['show_created'],
      '#show_photo' => $this->options['show_photo'],
      '#replace_comment_user_picture' => $this->options['replace_comment_user_picture'],
      '#property' => $webmention->getProperty(),
      '#author_name' => $webmention->getAuthorName(),
      '#author_photo' => \Drupal::service('indieweb.media_cache.client')->applyImageCache($webmention->getAuthorAvatar(), 'avatar', 'webmention_avatar'),
      '#created' => $webmention->getCreatedTime(),
      '#source' => $webmention->getSource(),
      '#content_text' => $webmention->getPlainContent(),
      '#content_html' => $webmention->getHTMLContent(),
      '#photo' => \Drupal::service('indieweb.media_cache.client')->applyImageCache($webmention->getPhoto(), 'photo', 'webmention_image'),
      '#video' => $webmention->getVideo(),
      '#audio' => $webmention->getAudio(),
      // Create a cache tag entry for the referenced entity. In the case
      // that the referenced entity is deleted, the cache for referring
      // entities must be cleared.
      '#cache' => [
        'tags' => $webmention->getCacheTags(),
      ],
    ];

    return $build;
  }

}

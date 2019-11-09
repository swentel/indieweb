<?php

namespace Drupal\indieweb_websub\Plugin\views\style;

use Drupal\Core\Url;
use Drupal\views\Plugin\views\style\Rss;

/**
 * Default style plugin to render an RSS feed.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "websub_rss",
 *   title = @Translation("RSS Feed with WebSub discovery"),
 *   help = @Translation("Generates an RSS feed from a view."),
 *   theme = "views_view_rss",
 *   display_types = {"feed"}
 * )
 */
class WebSub extends Rss {

  public function render() {
    if (empty($this->view->rowPlugin)) {
      trigger_error('Drupal\views\Plugin\views\style\Rss: Missing row plugin', E_WARNING);
      return [];
    }
    $rows = [];

    // This will be filled in by the row plugin and is used later on in the
    // theming output.
    $this->namespaces = ['xmlns:dc' => 'http://purl.org/dc/elements/1.1/'];

    // Fetch any additional elements for the channel and merge in their
    // namespaces.
    $this->channel_elements = $this->getChannelElements();
    foreach ($this->channel_elements as $element) {
      if (isset($element['namespace'])) {
        $this->namespaces = array_merge($this->namespaces, $element['namespace']);
      }
    }

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    $config = \Drupal::config('indieweb_websub.settings');
    $links = [
      '<' . $config->get('hub_endpoint') . '>; rel="hub"',
      '<' . Url::fromUri('internal:/' . $this->view->getPath(), ['absolute' => TRUE])->toString() . '>; rel="self"',
    ];
    $this->view->getResponse()->headers->set('link', $links);

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
    ];
    unset($this->view->row_index);

    return $build;
  }

}

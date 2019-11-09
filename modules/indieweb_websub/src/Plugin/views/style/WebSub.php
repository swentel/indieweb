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
    $build = parent::render();

    // Will only work if views caching is disabled, but we'll fix that later.
    $config = \Drupal::config('indieweb_websub.settings');
    $links = [
      '<' . $config->get('hub_endpoint') . '>; rel="hub"',
      '<' . Url::fromUri('internal:/' . $this->view->getPath(), ['absolute' => TRUE])->toString() . '>; rel="self"',
    ];
    $this->view->getResponse()->headers->set('link', $links);

    return $build;
  }

}

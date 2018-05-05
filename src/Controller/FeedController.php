<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;

class FeedController extends ControllerBase {

  /**
   * Routing callback: admin overview.
   */
  public function adminFeedList() {
    $build = [];

    $build['info'] = [
      '#markup' => $this->t('<p>Besides the standard RSS feed which you can create where readers can subscribe to, you can also create microformat feeds. These can either be in HTML or in json. You will need feeds when:</p><ul><li>you use brid.gy: the service will look for html link headers with rel="feed" and use those pages to crawl so it knows to which content it needs to send webmentions too.</li><li>you want to allow IndieWeb readers (Monocle, Together, Indigenous) to subscribe to your content. These are alternatetypes which can either link to a page with microformat entries. It\'s advised to have an h-card on that page too as some parsers don\'t go to the homepage to fetch that content.</li></ul><p>Because content can be nodes or comments, it isn\'t possible to use views. However, you can create multiple feeds which aggregate the content in a page and/or feed.</p>')
    ];

    return $build;
  }

}

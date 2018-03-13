<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;

class IndiewebController extends ControllerBase {

  /**
   * Routing callback: indieweb main dashboard.
   *
   * @return array
   */
  public function dashboard() {
    return ['#markup' => '<p>More to come later!</p>'];
  }

}

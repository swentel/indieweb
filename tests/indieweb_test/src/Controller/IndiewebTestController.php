<?php

namespace Drupal\indieweb_test\Controller;

use Drupal\Core\Controller\ControllerBase;

class IndiewebTestController extends ControllerBase {

  public function front() {
    return ['#markup' => 'Indieweb test front'];
  }

}

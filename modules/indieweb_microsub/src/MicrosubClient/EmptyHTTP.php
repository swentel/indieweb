<?php

namespace Drupal\indieweb_microsub\MicrosubClient;

class EmptyHTTP {

  public function get($url = NULL, $options = NULL) {
    return [
      'error' => TRUE,
    ];
  }

}

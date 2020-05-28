<?php

namespace Drupal\indieweb_microsub\MicrosubClient;

class EmptyHTTP {

  public function get($url, $options) {
    return [
      'error' => TRUE,
    ];
  }

}

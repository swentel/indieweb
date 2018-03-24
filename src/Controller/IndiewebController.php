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
    return ['#markup' => '
<p>The IndieWeb is a people-focused alternative to the "corporate web". When you post something on the web, it should belong to you, not a corporation. Read all about it on <a href="https://indieweb.org/" target="_blank">https://indieweb.org/</a></p>
<p>Use <a href="https://indiewebify.me/" target="_blank">https://indiewebify.me/</a> to perform initial checks to see if your site is Indieweb ready. It can scan for certain
markup after you\'ve done the configuration with this module (and optionally more yourself).</p>
<p>This module aims to bring that functionality in your Drupal site. You can configure webmentions, publishing, microformats, indieauth, comments and micropub.</p>'];
  }

}

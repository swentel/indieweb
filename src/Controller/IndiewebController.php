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
<p>This module aims to bring that functionality in your Drupal site. Currently you can configure webmentions, publishing, microformats, indieauth and micropub.<br />You can also create comments, but there is no UI for that yet, consult the README on how to configure this. More fuctionality will be added later.</p>'];
  }

}

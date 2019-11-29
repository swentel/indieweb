<?php

namespace Drupal\indieweb\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class IndiewebController extends ControllerBase {

  /**
   * Routing callback: indieweb main dashboard.
   *
   * @return array
   */
  public function dashboard() {
    $build = [];
    $build['intro']['#markup'] = '
<p>The IndieWeb is a people-focused alternative to the "corporate web". When you post something on the web, it should belong to you, not a corporation. Read all about it on <a href="https://indieweb.org/" target="_blank">https://indieweb.org/</a><br />
This module aims to bring that functionality in your Drupal site. You can configure webmentions, microformats, micropub and much more.</p>
<p><a>Use <a href="https://indiewebify.me/" target="_blank">https://indiewebify.me/</a> to perform initial checks to see if your site is Indieweb ready. It can scan for certain
markup after you\'ve done the configuration with this module (and optionally more yourself).<br />Note that author discovery doesn\'t fully work 100% on IndieWebify for posts, use <a href="https://sturdy-backbone.glitch.me" target="_blank">https://sturdy-backbone.glitch.me</a><br />Finally, another good tool is <a href="http://xray.p3k.io" target="_blank">http://xray.p3k.io</a> which displays the results in JSON.
</p>';

    $items = [];
    $modules = [
      'https://www.drupal.org/project/externalauth' => 'External Authentication',
      'https://www.drupal.org/project/auto_entitylabel' => 'Automatic Entity Label',
      'https://www.drupal.org/project/realname' => 'Real Name',
      'https://www.drupal.org/project/rabbit_hole' => 'Rabbit hole',
      'https://www.drupal.org/project/imagecache_external' => 'Imagecache External',
      'https://www.drupal.org/project/cdn' => 'CDN',
      'https://www.drupal.org/project/geofield' => 'Geofield',
      'https://www.drupal.org/project/geocoder' => 'Geocoder',
      'https://www.drupal.org/project/prepopulate' => 'Prepopulate',
    ];

    foreach ($modules as $url => $label) {
      $items[] = [
        '#type' => 'link',
        '#url' => Url::fromUri($url),
        '#title' => $label,
      ];
    }

    $build['links'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => t('Useful additional modules'),
    ];

    return $build;
  }

}

# Indieweb integration for Drupal 8

## About this module.

Integrates the philosophy of Indieweb in your Drupal website in a minimal way.

For more information see https://indieweb.org/.

Current functionality:

- Webmention.io integration
- Brid.gy publishing for all nodes
- microformats for content and images

## To install

- composer require indieweb/mention-client in the root of your Drupal installation.
- go to admin/modules and toggle 'Indieweb'.

## Webmention.io

Webmention.io is a hosted service created to easily handle webmentions (and legacy pingbacks) on any web page. The
module exposes an endpoint (/webmention/notify) to receive pingbacks and webmentions via this service. Pingbacks are
also validated to make sure that the source URL has a valid link to the target.

You need an account for receiving the webhooks at https://webmention.io. As soon as one webmention is recorded, you can
set the the webhook to http://your_domain/webmention/notify. Pingbacks can be done without an account, but you probably
want both right :)

To configure:

- Add the webmention header tags to html.html.twig (or use hooks to only add these head tags on certain pages).

 ```
  <link rel="pingback" href="https://webmention.io/webmention?forward=http://your_domain/webmention/notify" />
  <link rel="webmention" href="https://webmention.io/your_domain/webmention" />
  ```

- Pingbacks and webmentions are stored in a simple entity type called webmentions as user 1.
  An overview of collected links is available at admin/content/webmentions.

- Two settings can be configured by adding lines to settings.php

  - Logging the payload in watchdog:

  ```
  $settings['indieweb_webmention_log_payload'] = TRUE;
  ```

  - Assigning a different user id for the webmention:

  ```
  $settings['indieweb_webmention_uid'] = 321;
  ```

## Brid.gy

Brid.gy allows you to publish content on your social networks, as well as pulling back replies, likes etc. You need to
allow brid.gy to post and retrieve them. You can also just allow to retrieve.

A checkbox will be available on the node form for publishing your content. In case you want to publish, this is stored
in the queue table and is currently handled by a drush command (proper queue integration coming soon).

drush command is 'indieweb-send-webmentions'

Note that in case you want to publish, you need to make sure your content has proper microformat classes.

## Microformats

Minimum classes needed:

- h-entry
- p-content or e-content

Note: these classes are not yet added by this module, coming soon.

Optional classes:

- u-photo: for pictures.

Added by default on all images styles.

## TODO

  - Add API to get backlinks for a certain URL.
  - Expose that data in a block.
  - validate secret

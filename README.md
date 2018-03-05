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
set the webhook to http://your_domain/webmention/notify. Pingbacks can be done without an account, but you probably want
both right :)

Pingbacks and webmentions are stored in a simple entity type called webmentions as user 1. An overview of collected
links is available at admin/content/webmentions.

To create an account, you need to authenticate with https://indieauth.com/ which requires you to add the "rel=me"
attribute on links to your social accounts. See https://indieauth.com/setup for full instructions.

To configure:

- Add the webmention header tags to html.html.twig (or use hooks to only add these head tags on certain pages).

 ```
  <link rel="pingback" href="https://webmention.io/webmention?forward=http://your_domain/webmention/notify" />
  <link rel="webmention" href="https://webmention.io/your_domain/webmention" />
  ```

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

A checkbox will be available on the node form for publishing your content. When you toggle to publish, an entry is
created in the queue table which is currently handled by a drush command (proper queue integration coming soon).

drush command is 'indieweb-send-webmentions'

In case you want to publish, you need to make sure your content has proper microformat classes and add following snippet
to the page you want to publish to twitter.

  ```
  <a href="https://brid.gy/publish/twitter"></a>
  ```

This module currently exposes it on the full view mode of a node, see indieweb_node_view_alter().
More info about this at https://brid.gy/about#webmentions

Note that brid.gy prefers p-summary over e-content, see https://brid.gy/about#microformats.

## Microformats

Classes added for minimum publication.

- h-entry: added on node wrapper, see indieweb_preprocess_node().
- e-content: added on default body field, see indieweb_preprocess_field().
- u-photo: added on image styles, indieweb_preprocess_image_style().
- p-summary: the field where you want this class to be added on can be configured via

  ```
  $settings['indieweb_p_summary_fields'] = ['field_summary'];
  ```

## TODO

  - Add API to get backlinks for a certain URL.
  - Expose webmentions in a block.
  - validate secret
  - enabled/disable publish to bridgy
  - make publishing plugins
  - configure publishing per node type
  - use proper queue
  - add publish webmentions snippets as simple extra fields
  - add more channels to publish to (so more plugins)
  - add social profile links to home (for indieauth.com)
  - create comments from content?
  - allow replying
  - configure whether to create new 'conversations' when say target it / and type is 'mention-of' because that is
    a mention on twitter
  - send webmention to a url which starts a 'conversation' on local site
  - figure out rel="feed"
  - micropub ?
  - indieauth local site?

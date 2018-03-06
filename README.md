# Indieweb integration for Drupal 8

## About this module.

Integrates the philosophy of Indieweb in your Drupal website in a minimal way.

For more information see https://indieweb.org/.

Current functionality:

- Webmention.io integration
- Brid.gy publishing for nodes
- microformats for content and images
- Creating comments from 'in-reply-to'

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

- settings which can be configured by adding lines to settings.php

  - Webmention.io secret, needed to validate webmentions send to the controller.

  ```
  $settings['indieweb_webmention_io_secret'] = 'your_secret';
  ```

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

This module exposes an extra field which you can configure on the 'Manage Display' pages of each node. The content
itself is added in indieweb_node_view(). More info about this at https://brid.gy/about#webmentions

Note that brid.gy prefers p-summary over e-content, see https://brid.gy/about#microformats.

More channels coming soon.

## Microformats

Classes added for minimum publication.

- h-entry: added on node wrapper, see indieweb_preprocess_node().
- e-content: added on default body field, see indieweb_preprocess_field().
- u-photo: added on image styles, indieweb_preprocess_image_style().
- p-summary: the field where you want this class to be added on can be configured via

  ```
  $settings['indieweb_p_summary_fields'] = ['field_summary'];
  ```

## Creating comments

Warning: experimental, but fun :)

When a webmention is saved and is of property 'in-reply-to', it is possible to create a comment. Currently,
configuration is done by adding a field on comments and configuring lines in settings.php. (at some point, we'll move
this to configuration when it's well tested). Following steps are needed:

  - Create an entity reference field on your comment which points to a webmention. On the 'Manage display' page you can
  set the formatter to 'Webmention'. Currently the format uses the textual version of the reply run through the
  'restricted_html' content format which comes default with Drupal 8. Don't forget to set permissions to view
  webmentions.

  - enable the creation of the comment in settings.php.

  ```
  $settings['indieweb_webmention_create_comment'] = TRUE;
  ```

  - The name of the comment type to use in settings.php.

  ```
  $settings['indieweb_comment_type'] = 'comment';
  ```

  - The name of the webmention reference field in settings.php.

  ```
  $settings['indieweb_comment_webmention_reference_field'] = 'field_webmention';
  ```

  - The name of the comment field on the node type in settings.php.

  ```
  $settings['indieweb_node_comment_field'] = 'comment';
  ```

  - Status of the new comment (defaults to moderated) in settings.php. (1 is published)

  ```
  $settings['indieweb_comment_status'] = 1;
  ```

That's it. The module will check whether the node type has comments enabled and if the comment status is set to open.
See indieweb_webmention_entity_insert().

## Output

A basic block is available to render webmentions per page.
Needs a lot of updates on theming, but it gets the job done for now.

## Screenshot

![ScreenShot](https://realize.be/sites/default/files/2018-03/webmention-basic.png)

## TODO

  - more flexible theming in block, and in general
  - default avatar ?
  - make publishing plugins and allow to create on the fly
  - enabled/disable publish to bridgy (when plugins are there)
  - add more default channels to publish to (when plugins are in)
  - configure publishing per node type
  - use proper queue
  - add social profile links to home (for indieauth.com)
  - match author of comment with yourself on the site
  - allow replying on comments which send a webmention then so a reply on that gets linked again
  - better configuration of comments (e.g. also author picture etc, better subject?)
  - configure whether to create new 'conversations' when say target it / and type is 'mention-of' because that is
    a mention on twitter
  - send webmention to a url which starts a 'conversation' on local site
  - figure out rel="feed"
  - micropub ?
  - indieauth local site?
  - inject all the things!
  - Add API to get backlinks for a certain URL from drupal
  - Add API to get backlinks (for a certain URL) from webmention.io and store them (again)
  - tests (sigh)

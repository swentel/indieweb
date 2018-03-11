# Indieweb integration for Drupal 8

## About this module.

Integrates the philosophy of Indieweb in your Drupal website in a minimal way.

For more information see https://indieweb.org/.

Current functionality:

- Webmention.io integration
- Brid.gy publishing for nodes
- microformats for content and images
- Creating comments from 'in-reply-to'
- Info about adding IndieAuth headers (see below)
- micropub requests

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
attribute on links to your social accounts. See https://indieauth.com/setup for full instructions. See also IndieAuth
below.

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

  - Status of the new comment (defaults to moderated) in settings.php. (1 is published) (optional)

  ```
  $settings['indieweb_comment_status'] = 1;
  ```

  - Match author names with uid's in settings.php. (optional)

  ```
  $settings['indieweb_comment_authors'] = ['Your name' => 3];
  ```

That's it. The module will check whether the node type has comments enabled and if the comment status is set to open.
See indieweb_webmention_entity_insert().

## IndieAuth support

If you use apps like https://quill.p3k.io (Web) or Indigenous (iOS), the easiest way to let you login with your domain
is by using indieauth.com. Less code to maintain right :)

Add following headers to your html.html.twig file.

  ```
  <link rel="authorization_endpoint" href="https://indieauth.com/auth" />
  <link rel="token_endpoint" href="https://tokens.indieauth.com/token" />
  ```

You need rel="me" links on your homepage which point to your own site and your social media accounts.
e.g.

  ```
  <a class="h-card" rel="me">https://your_domain</a>
  <a href="https://twitter.com/swentel" target="_blank" title="Twitter" rel="me">
  ```

## Micropub

Warning: experimental, but fun :)

Allow posting to your site, cool no ?
If you would send a micropub post request with the content parameter, it will create a node.
You can configure the node type below, but we will make this more flexible in the near future.
It will store the content into a field with machine name 'body' which  can be overridden too.

  Add following header to your html.html.twig file.

  ```
  <link rel="micropub" href="https://your_domain/indieweb/micropub">
  ```

  Settings you can change in settings.php

  - allow micropub requests.

  ```
  $settings['indieweb_allow_micropub_posts'] = TRUE;
  ```

  - Set the 'me' value, this is your domain which you use to sign in with Indieauth. Note the trailing slash!

  ```
  $settings['indieweb_micropub_me'] = 'https://realize.be/';
  ```

  - Allow sending a webmention (currently hardcoded to bridgy twitter):

  ```
  $settings['indieweb_micropub_send_webmention'] = TRUE;
  ```

  - Assigning a node type for the post (defaults to 'note'):

  ```
  $settings['indieweb_micropub_node_type'] = 'micropub_note';
  ```

  - The field which will store the 'content' from the micropub post (defaults to 'body'):

  ```
  $settings['indieweb_micropub_content_field'] = 'my_body';
  ```

  - Assigning a different user id for the post (default to 1):

  ```
  $settings['indieweb_micropub_uid'] = 321;
  ```

  - Logging the payload in watchdog:

  ```
  $settings['indieweb_micropub_log_payload'] = TRUE;
  ```

## Microsub

Allow your site to be 'read'.

Warning: experimental, but fun :)

Note, the routing definition is commented out at this point as there's no dynamic content yet.
More to come later.

Add following header to your html.html.twig file.

  ```
  <link rel="microsub" href="https://your_domain/indieweb/microsub">
  ```

## Output

A basic block is available to render webmentions per page.
Needs a lot of updates on theming, but it gets the job done for now.

## Screenshot

![ScreenShot](https://realize.be/sites/default/files/2018-03/webmention-basic.png)

## Want to help out ?

Great! Check the issue queue at https://github.com/swentel/indieweb/issues

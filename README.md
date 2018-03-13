# Indieweb integration for Drupal 8

## About this module.

Integrates the philosophy of Indieweb in your Drupal website in a minimal way.
For more information about indieweb, see https://indieweb.org/.

Current functionality:

- Receive webmentions and pingbacks via Webmention.io
- Publish content, likes via bridg.y
- Microformats for content and images
- Creating comments from 'in-reply-to'
- IndieAuth headers
- Micropub (experimental)
- Microsub (extremely experimental)

This is only the top of the iceberg, much more to come.

## IndieWebify.me

You can use https://indiewebify.me/ to perform initial checks to see if your site is Indieweb ready. It will scan for
certain markup, so after you've done  the configuration with this module (and optionally more yourself), use it to make
sure everything is ok.

## To install

- composer require indieweb/mention-client in the root of your Drupal installation.
- go to admin/modules and toggle 'Indieweb' to enable the module.

## Webmentions / Webmention.io

Webmention.io is a hosted service created to easily handle webmentions (and legacy pingbacks) on any web page. The
module exposes an endpoint (/webmention/notify) to receive pingbacks and webmentions via this service. Pingbacks are
also validated to make sure that the source URL has a valid link to the target. Webmention.io is open source so you can
also host this service yourself.

You need an account for receiving the webhooks at https://webmention.io. As soon as one webmention is recorded at that
service, you can set the webhook to http://your_domain/webmention/notify and enter a secret. Pingbacks can be done
without an account, but you probably want both right :)

Pingbacks and webmentions are stored in a simple entity type called webmentions as user 1. An overview of collected
links is available at admin/content/webmentions.

To create an account, you need to authenticate with https://indieauth.com/ which requires you to add the "rel=me"
attribute on links to your social accounts. See https://indieauth.com/setup for full instructions. See also Indieauth
further below.

- Configuration is at /admin/config/services/indieweb/webmention
- Overview of all collected webmentions and pingbacks are at /admin/content/webmention

A basic block (Webmentions) is available to render like and repost webmentions per page.

![ScreenShot](https://realize.be/sites/default/files/2018-03/webmention-basic.png)

## Pulling and publishing content / Bridgy

Bridgy pulls comments, likes, and reshares on social networks back to your web site. You can also use it to post to
social networks - or comment, like, reshare, or even RSVP - from your own web site.

To receive content from those networks, bridgy will send a webmention, so you only need to enable the webmention
endpoint and make sure rel="me" links with the url to your social networks are available on the homepage. e.g.

  ```
  <a href="https://twitter.com/swentel" target="_blank" title="Twitter" rel="me">Twitter</a>
  ```

These links can even be hidden on your page.

For publishing, a checkbox will be available on the node form for publishing your content per channel (e.g. twitter,
facebook etc). When you toggle to publish, an entry is created in the queue which you can either handle with drush or
by cron. This will send a webmention to bridgy.

The drush command is 'indieweb-send-webmentions'

Your content needs to have proper microformat classes on your content, images etc and following snippet needs to
available on the page you want to publish, e.g.

  ```
  <a href="https://brid.gy/publish/twitter"></a>
  ```

Note that Bridgy prefers p-summary over e-content, see https://brid.gy/about#microformats. You can preview your posts
on Bridgy to verify your markup is ok.

This module exposes per channel an extra field which you can configure on the 'Manage Display' pages of each node type.
That field exposes that snippet. See indieweb_node_view(). More info about this at https://brid.gy/about#webmentions

The module ships with default Twitter and Facebook channels. More channels and other configuration can be configured at
/admin/config/services/indieweb/publish. These channels are also used for the q=syndicate-to request for micropub, see
micropub for more information.

## Microformats

Microformats are extensions to HTML for marking up people, organizations, events, locations, blog posts, products,
reviews, resumes, recipes etc. Sites use microformats to publish a standard API that is consumed and used by search
engines, aggregators, and other tools. See https://indieweb.org/microformats for more info. You will want to enable this
if you want to publish.

Classes added for publication (or other functionality).

- h-entry: added on node wrapper, see indieweb_preprocess_node().
- e-content: added on default body field, see indieweb_preprocess_field().
- u-photo: added on image styles, indieweb_preprocess_image_style().
- p-summary: the field where you want this class to be added on can be configured via

  ```
  $settings['indieweb_p_summary_fields'] = ['field_summary'];
  ```

You can configure this at /admin/config/services/indieweb/microformats

## Creating comments

When a webmention is saved and is of property 'in-reply-to', it is possible to create a comment if the target of the
webmention has comments enabled. Currently, configuration is done by adding a field on comments and configuring lines
in settings.php. (at some point, we'll move this to configuration when it's well tested). Following steps are needed:

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

If you use apps like Quill (https://quill.p3k.io - web) or Indigenous (Beta iOS, Alpha Android) or other clients which
can post via micropub or read via microsub, the easiest way to let those clients log you in with your domain is by using
indieauth.com. Indieauth is open source so you can also host this service yourself.

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

## Want to help out ?

Great! Check the issue queue at https://github.com/swentel/indieweb/issues

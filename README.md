# Indieweb integration for Drupal 8

## About this module.

Integrates the philosophy of Indieweb in your Drupal website.
For more information about indieweb, see https://indieweb.org/.

Current functionality:

- Receive webmentions and pingbacks via Webmention.io
- Publish content, likes etc via bridg.y
- Microformats for content and images
- IndieAuth for Authentication API
- Micropub for creating content, likes etc
- Creating comments from 'in-reply-to' (experimental and no UI yet)

This is only the tip of the iceberg and much more functionality will be added.

Development happens on github: https://github.com/swentel/indieweb
Releases are available on drupal.org: https://www.drupal.org/project/indieweb

## IndieWebify.me

Use https://indiewebify.me/ to perform initial checks to see if your site is Indieweb ready. It can scan for certain
markup after you've done the configuration with this module (and optionally more yourself).

## To install

- composer require indieweb/mention-client in the root of your Drupal installation.
- go to admin/modules and toggle 'Indieweb' to enable the module.

## Webmentions / Webmention.io

Webmention.io is a hosted service created to easily handle webmentions (and legacy pingbacks) on any web page. The
module exposes an endpoint (/webmention/notify) to receive pingbacks and webmentions via this service. Pingbacks are
also validated to make sure that the source URL has a valid link to the target. Webmention.io is open source so you can
also host the service yourself.

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
social networks - or comment, like, reshare, or even RSVP - from your own web site. Bridgy is open source so you can
also host the service yourself.

To receive content from those networks, bridgy will send a webmention, so you only need to enable the webmention
endpoint.

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
- p-summary: see indieweb_preprocess_field().

You can configure this at /admin/config/services/indieweb/microformats

## IndieAuth: sign in with your domain name.

IndieAuth is a way to use your own domain name to sign in to websites. It works by linking your website to one or more
authentication providers such as Twitter or Google, then entering your domain name in the login form on websites that
support IndieAuth. Indieauth.com is a hosted service that does this for you and also adds Authentication API.
Indieauth.com is open source so you can also host the service yourself.

The easy way is to add rel="me" links on your homepage which point to your social media accounts and on each of those
services adding a link back to your home page. They can even be hidden.

  ```
  <a href="https://twitter.com/swentel" target="_blank" title="Twitter" rel="me"></a>
  ```

You can also use a PGP key if you don't want to use a third party service. See https://indieauth.com/setup for full
details. This module does not expose any of these links or help you with the PGP setup, you will have to manage this
yourself.

If you use apps like Quill (https://quill.p3k.io - web) or Indigenous (Beta iOS, Alpha Android) or other clients which
can post via micropub or read via microsub, the easiest way to let those clients log you in with your domain is by using
indieauth.com too and exchange access tokens for further requests. Only expose these header links if you want to use
micropub or microsub.

## Micropub

Allow posting to your site. Before you can post, you need to authenticate and enable the IndieAuth Authentication API.
Every request will contain an access token which will be verified to make sure it is really you who is posting. See
IndieAuth to configure. More information about micropub: https://indieweb.org/Micropub

A very good client to test is https://quill.p3k.io. A full list is available at https://indieweb.org/Micropub/Clients.
Indigenous (for iOS and Android) are in beta/alpha and are also microsub readers.

Create a node when a 'note' is posted. A note request contains 'content', but no 'name' and the 'h' value is 'entry'.
Think of it as a Tweet. The note can also contain a 'mp-syndicate-to' value which will contain the channel you want to
publish to, see the Publish section to configure this.

You can configure this at /admin/config/services/indieweb/micropub

## Creating comments

This is currently experimental and has no UI configuration yet.

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

## Microsub

Allow your site to be 'read'.

Warning: experimental, no UI, don't use it yet.

The routing definition is commented out at this point as there's no dynamic content yet and hardcoded to my site.
More to come later.

Add following header to your html.html.twig file.

  ```
  <link rel="microsub" href="https://your_domain/indieweb/microsub">
  ```

## Want to help out ?

Great! Check the issue queue at https://github.com/swentel/indieweb/issues

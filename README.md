# Send to E-Reader

- Contributors: akirk
- Tags: e-reader, epub, kindle, pocketbook, email
- Requires at least: 6.0
- Requires PHP: 7.4
- Tested up to: 7.1
- Stable tag: 1.1.0
- License: GPL-2.0-or-later
- License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send posts to your e-reader as ePub files, by email or direct download. Works standalone or with the Friends plugin.

## Description

[Try it in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https%3A%2F%2Fraw.githubusercontent.com%2Fakirk%2Fsend-to-e-reader%2Fmain%2Fblueprint.json)

Send to E-Reader turns one or more WordPress posts into an ePub file. You can download that file straight away, or have it mailed to the address your e-reader listens on, so the article shows up on the device without a cable.

See the post [Subscribing to RSS Feeds on your E-Reader using your own WordPress blog](https://wpfriends.at/2021/09/20/subscribing-to-rss-feeds-on-your-e-reader/) for more details on how it works.

### Where you use it

- On the **Posts** screen, a *Send to E-Reader* row action sends a single post. With several e-readers configured, each one gets its own entry.
- Select several posts and pick *Send to E-Reader* from the **Bulk actions** menu to bundle them into one multi-chapter ePub.
- With the [Friends plugin](https://github.com/akirk/friends/) installed, the same actions appear on the posts of the feeds you follow, so you can move a batch of unread articles onto your e-reader in one go. The plugin remembers which posts it has already sent, so you can send just the new ones.
- If the [AI Assistant plugin](https://github.com/akirk/ai-assistant) is active, conversations can be exported as ePub through its export menu.

### Delivery methods

You configure any number of e-readers on the plugin's *E-Readers* screen. Each one is one of:

- **ePub via E-Mail** — a generic e-reader that accepts documents at an email address.
- **Kindle** — sends to your Send-to-Kindle address (`@free.kindle.com`). Amazon accepts ePub directly, so no conversion is needed.
- **PocketBook** — sends to your PocketBook Cloud address (`@pbsync.com`).
- **Download ePub** — no email at all; the file is offered as a download in the browser.

Mail is sent through WordPress's own `wp_mail()`, so whatever SMTP setup the site already has is what gets used. The configured e-readers are stored site-wide: everyone who can reach the *E-Readers* screen works from the same list of devices. If more than one e-reader is active, the *Posts* screen offers one row action and one bulk action per device, so you pick where a post goes.

### The ePub it builds

The ePub is generated on the fly from the post content: a title page, one chapter per post, and the post's byline and permalink. Images in the post body are embedded into the file, including images hosted on the same site, so the article still reads correctly offline. Content is converted to XHTML so the e-reader's renderer accepts it.

ePubs can also be fetched over a special download URL: append a password-derived query parameter to any page of your site and it returns an ePub of all posts, the new ones, the last one, or a list to pick from. Some e-readers can open a URL directly, which makes this the fastest route onto the device.

Standalone, the plugin adds its screens under **Tools → Send to E-Reader** (and **Settings → Send to E-Reader**). With the Friends plugin active, they move under the Friends menu instead.

## Installation

1. Upload the plugin files to `/wp-content/plugins/send-to-e-reader`, or install the plugin through the WordPress *Plugins* screen.
2. Activate the plugin through the *Plugins* screen in WordPress.
3. Go to **Tools → E-Readers** and add an e-reader: give it a name and, for the email-based types, the address your device receives documents on.
4. Open the **Posts** screen and use the *Send to E-Reader* row action, or select several posts and use the bulk action.

The plugin has no required dependencies. The [Friends plugin](https://github.com/akirk/friends/) is optional and unlocks sending posts from the feeds you follow.

## Frequently Asked Questions

### Do I need the Friends plugin?

No. The plugin works standalone on any WordPress site and adds its own settings screens. If the Friends plugin happens to be active, the two integrate and the e-reader actions also appear on your feed posts.

### Which devices are supported?

Anything that can receive a document by email, plus a plain download for everything else. There are dedicated presets for Kindle and PocketBook; the generic *ePub via E-Mail* reader covers any other device with a send-to-device address.

### Why ePub and not MOBI?

Amazon accepts ePub by mail, so MOBI support was removed. Every supported device reads ePub.

### The email never arrives. What should I check?

The plugin uses WordPress's `wp_mail()`. If other mail from your site does not arrive either, the problem is the site's mail configuration, not this plugin — an SMTP plugin usually fixes it. Also check that your device's service allows mail from your site's sending address; Kindle in particular only accepts mail from addresses on its approved-senders list.

### Can I send several posts as one file?

Yes. Select them on the Posts screen and use the *Send to E-Reader* bulk action; they become one ePub with a chapter per post.

### Where are my e-reader addresses stored?

In a site option, not per user. The *E-Readers* screen requires the `edit_private_posts` capability, so editors and administrators share one list of devices. Sending a post additionally requires that you are allowed to read that post.

## Changelog

### 1.1.0
- Fix saving the E-Readers screen deleting every configured e-reader
- Keep e-reader ids stable so delivery settings survive an edit
- Pick which e-reader to send to when several are configured
- Only send posts the current user is allowed to read
- Embed same-site images locally in the generated ePub
- Export AI Assistant conversations as ePub
- Reuse Static Archive HTML for ePub exports
- Manage article notes through the [Post Collection](https://github.com/akirk/post-collection) plugin
- Clearer admin messaging when running standalone

### 1.0.0
- Plugin now works fully standalone without requiring the Friends plugin
- Renamed plugin from "Friends Send to E-Reader" to "Send to E-Reader"
- Added standalone settings pages and admin UI
- Added test suite

### 0.8.4
- Allow creating books via bulk edit ([#13])
- Fixed a bug where a non-existant image could cause the rest of the document to be a link
- Enable the URL GET parameter on any page
- One more fix for empty titles in posts

### 0.8.3
- Try harder to ensure the title is not empty ([#12])

### 0.8.2
- Ensure the title is not empty ([#11])
- Improve the Reading Summary function ([#9])

### 0.8.1
- Add a Download URL previewer ([#7])
- Add the ability to mark an article as new ([#6])

### 0.8.0
- Fix choking on invalid SVGs
- Enable unsent posts for any author
- Add the ability to download ePub through special URLs ([#5])

### 0.7
- Fix multi-item dialog not popping up.

### 0.6
- Remove MOBI support since Amazon now accepts EPubs by mail.
- Introduce Reading Summaries: You can create a new draft posts from your sent articles so that you can easily post about them.
- Remember which posts were already sent, enabling a "Send x new posts to your e-reader" button in the header.

### 0.5
- Remember which posts were sent and allow sending just the new ones. [WIP display works, actual sending not yet]
- Automatically send new posts every week. [WIP setting screen is there, saving setting and cron not yet]
- Allow auto-creating of "reading summary" draft posts with link plus excerpt and room for your own comments.
- New-style setting screen with separate screen for reading summaries.

### 0.4
- Update for Friends 2.0

### 0.3
- Allow downloading the ePub.
- Theoretically add support for Tolino. Not functional because Thalia doesn't want to provide OAuth2 credentials.

[#12]: https://github.com/akirk/send-to-e-reader/pull/12
[#11]: https://github.com/akirk/send-to-e-reader/pull/11
[#9]: https://github.com/akirk/send-to-e-reader/pull/9
[#7]: https://github.com/akirk/send-to-e-reader/pull/7
[#6]: https://github.com/akirk/send-to-e-reader/pull/6
[#5]: https://github.com/akirk/send-to-e-reader/pull/5

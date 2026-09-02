=== Plogins Assets - Conditional Script & Style Loading ===
Contributors: wppoland
Tags: performance, assets, scripts, styles, optimization
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dequeue individual scripts and styles on the pages that do not need them. Fewer requests, lighter pages, faster loads - using WordPress core.

== Description ==

Most plugins load their CSS and JavaScript on every page, even where their feature never appears. A contact form script on your checkout, a slider stylesheet on your blog posts, a social-share bundle on your privacy policy. That weight slows every visit.

Plogins Assets lets you remove a specific script or style from exactly the pages where it is not needed - front page, blog index, single posts and pages (by ID), or by device. It uses the standard WordPress enqueue system (`wp_dequeue_script` / `wp_dequeue_style`), so there is no page-builder lock-in and nothing proprietary to learn.

= What it does =

* Remove any registered script or style handle by name.
* Choose *where* it is removed: everywhere, the front page, the blog posts index, single posts/pages (by ID), or only mobile / only desktop.
* Autocomplete hints from the handles registered on your site.
* Runs late on the front end so it removes handles reliably, and never touches the admin.

= What it does not do =

It does not minify, combine or defer. It removes what you tell it to remove - nothing automatic, nothing hidden. Removing a handle that another script depends on can break functionality, so change one rule at a time and check the front end.

= Part of the Plogins family =

Plogins Assets is one of a family of small, focused WordPress and WooCommerce plugins by WPPoland. Learn more at [plogins.com](https://plogins.com/).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/plogins-assets`, or install it from the Plugins screen.
2. Activate it.
3. Go to **Plogins Assets** in the main admin menu.
4. Add a rule: type the handle, pick script or style, and choose where to remove it.
5. Save, then check the front end.

== Frequently Asked Questions ==

= How do I find a handle name? =

Open the page in your browser, view source or the Network tab, and look at the `id` attribute of the `<script>` / `<link>` tags - it usually ends in `-js` or `-css` (the handle is that value without the suffix). The settings screen also offers autocomplete from the handles currently registered on your site.

= When do the Post/Page IDs apply? =

Only to the two single posts/pages conditions. The other conditions (everywhere, front page, blog index, mobile, desktop) decide from the request alone, so the IDs box is greyed out for them and a rule set that way removes the handle on every page it matches.

= Will this break my site? =

It only removes what you configure. If a handle you remove is a dependency of another script, that other script may stop working. Change one rule at a time and verify the front end. You can remove a rule to restore the asset instantly.

= Does it work without a page builder or WooCommerce? =

Yes. It uses WordPress core functions only and works on any theme.

= Does it run in the admin? =

No. Rules apply on the front end only.

== Screenshots ==

1. The rule table: remove a handle on the pages that do not need it.

== Changelog ==

= 1.0.4 =
* Tested against WordPress 7.1. Verified by activating this build on a clean 7.1 install with WooCommerce 11.1, not by editing the header.

= 1.0.3 =
* The Post/Page IDs box is now available only for the two single posts/pages conditions, the ones that can act on it. With any other condition it is greyed out with a short note, so a rule set to Everywhere or to a device can no longer look as if it were limited to a few pages while the handle is in fact removed across the whole site.

= 1.0.1 =
* Version bump to meet the family release floor for the WordPress.org submission.

= 1.0.0 =
* Initial release.

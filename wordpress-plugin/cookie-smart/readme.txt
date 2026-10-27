=== Cookie Smart – GDPR Cookie Banner & Consent Mode v2 ===
Contributors: pcwebagency
Tags: cookie banner, gdpr, cookie consent, consent mode, cookies
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie banner that really blocks tracking until consent: prior blocking, Google Consent Mode v2 and a consent log. Requires a Cookie Smart account.

== Description ==

Cookie Smart shows a cookie banner on your website and **blocks pixels, analytics and embedded videos until the visitor gives consent**. Every choice is recorded in a consent log you can export as proof.

It follows the GDPR, the ePrivacy Directive and the cookie guidelines of the Italian Data Protection Authority (Garante privacy, 10 June 2021): "Reject" as visible as "Accept", the close button (X) counts as a rejection, no cookie wall, no pre-ticked categories.

**This plugin connects your website to the Cookie Smart service** (cookiesmart.it), where you configure the banner, the categories and the services to block. A Cookie Smart account is required: you can try it free for 14 days, without a credit card. See the plans at [cookiesmart.it/prezzi](https://cookiesmart.it/prezzi).

= What the plugin does =

* **Banner first in the page**: the banner script is printed as the first element of the `<head>`, before Google Tag Manager and the other tags.
* **Server-side blocking**: scripts added with `wp_enqueue_script` that belong to services to block (Meta Pixel, Hotjar, TikTok, YouTube, Google Maps and many more) are sent to the browser as `type="text/plain"`, so they are not even downloaded before consent. It works with caching plugins too.
* **Blocking in the theme** (optional, WordPress 6.9 or later): also rewrites scripts and iframes written directly in the theme or in the content, through the WordPress template output buffer.
* **One source for the rules**: the list of services to block comes from the Cookie Smart service, the same one used by the banner. New services reach every website without updating the plugin.
* **Google Consent Mode v2**: Google tags receive `default: denied` and then the visitor's choice (advanced mode); basic mode is available in the Cookie Smart dashboard.
* **WP Consent API**: the visitor's choices are passed to the plugins that use the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/).
* **"Cookie preferences" link**: use the shortcode `[cookie_smart_preferences]` or a link to `#cookiesmart-preferences` in your footer menu.
* **Resilient**: if the service cannot be reached, the plugin keeps using the last configuration it received, so the services stay blocked.

= What the Cookie Smart service adds =

* Cookie scanner that finds and classifies the cookies of your website.
* Banner customisation: colours, position, texts in Italian and English.
* Consent log kept for 12 months, with CSV export and the exact banner shown to each visitor.
* Statistics on accept, reject and custom choices.
* Multiple websites and team members, depending on the plan.

== Installation ==

1. Install the plugin from **Plugins › Add New** by searching for "Cookie Smart", or upload the zip file, then activate it.
2. Create a Cookie Smart account at [cookiesmart.it](https://cookiesmart.it/app/register) (14-day free trial, no credit card) and add your website with its domain.
3. Copy the **Site key** from your website page in the Cookie Smart dashboard.
4. In WordPress open **Cookie Smart** (shield icon in the side menu), paste the site key and save.
5. If you use a caching or optimisation plugin, exclude the Cookie Smart script from minification, combination and "delay JavaScript". The script carries the `data-no-optimize`, `data-no-defer` and `data-cfasync="false"` attributes, which most of them respect automatically.

== Frequently Asked Questions ==

= Do I need an account? =

Yes. The banner, the scanner and the consent log are provided by the Cookie Smart service. Without a site key the plugin does nothing on your website and shows only a reminder in the admin area.

= Is it free? =

The plugin is free. The Cookie Smart service is a paid subscription with a 14-day free trial, no credit card required.

= Does it work with caching plugins? =

Yes. Blocking happens when the page is generated, so cached pages already contain the blocked scripts. Exclude the Cookie Smart script from JavaScript minification and delay features.

= Does it block Google Analytics and Google Tag Manager? =

By default Google tags are not blocked: they run in Consent Mode v2 advanced mode, receiving "denied" until the visitor consents. If you prefer, enable basic mode in the Cookie Smart dashboard: Google tags are then blocked until consent.

= Does the visitor's IP address reach Cookie Smart? =

The consent log stores the IP address truncated (the last part is removed) together with the choice. See the "External services" section below for all the details.

= Can I remove the "Consent managed with Cookie Smart" link? =

The preferences panel shows a small "Consent managed with Cookie Smart" link. It is part of the service and can be removed with the Agency plan.

= What happens if Cookie Smart is unreachable? =

The banner script is served by a European CDN that keeps working from its cache, and the plugin keeps blocking services with the last configuration received.

== External services ==

This plugin connects your website to the Cookie Smart service, provided by PC Web Agency (Italy). The service is required: it provides the cookie banner, the list of services to block and the consent log. Nothing is sent until you enter your site key in the plugin settings.

**1. Cookie Smart API (cookiesmart.it)** – configuration
From your server, the plugin requests the configuration of your website (the services to block and the address of the banner script) from `https://cookiesmart.it/api/v1/sites/{site key}/config`. It sends only the site key. The request is made when you save the settings, when you open the plugin page and at most every 6 hours (every 10 minutes if the service did not answer).

**2. Banner script (cdn.cookiesmart.it and cookiesmart.it)** – in your visitors' browsers
Each page of your website loads the banner script from `https://cdn.cookiesmart.it` (bunny.net CDN, European servers) or from `https://cookiesmart.it`. Like any web request, it sends the visitor's IP address and browser information to the CDN and to Cookie Smart.

**3. Consent log (cookiesmart.it)** – in your visitors' browsers
When a visitor makes a choice in the banner, the browser sends it to `https://cookiesmart.it/api/v1/consents`: a random consent ID, the categories accepted or rejected, the configuration version, the page address, the language and the browser user agent. The IP address is truncated before it is stored (IPv4 /24, IPv6 /48). Records are kept for 12 months as proof of consent.

Cookie Smart terms of service: https://cookiesmart.it/termini
Cookie Smart privacy policy: https://cookiesmart.it/privacy

== Screenshots ==

1. The cookie banner on a website: "Reject" as visible as "Accept all".
2. The preferences panel with the categories and the cookies found by the scanner.
3. The plugin settings in WordPress: site key and connection status.
4. The Cookie Smart dashboard with the consent statistics.
5. The consent log with filters and CSV export.

== Changelog ==

= 1.4.0 =
* Ready for the WordPress.org plugin directory: English source texts with the Italian translation included, scripts printed with the WordPress functions, "External services" section in the readme.
* The plugin header now includes the plugin and author pages and the translations folder.
* "Blocking in the theme" now uses the WordPress template output buffer (WordPress 6.9 or later) instead of a buffer opened by the plugin.

= 1.3.0 =
* Setup guide for users who install the plugin without an account: how to create the account, add the website and find the site key, with links to the Cookie Smart website.
* Reminder on the Plugins page and on the Dashboard until the plugin is connected.

= 1.2.0 =
* Updates from the WordPress dashboard for the version distributed on GitHub, with the zip verified by its sha256 fingerprint.

= 1.1.0 =
* The banner is loaded from the Cookie Smart European CDN when the service provides it: faster, and available even if the main server is down.
* If the service is unreachable, the plugin keeps using the last configuration received: scripts stay blocked.

= 1.0.0 =
* First public release.
* Cookie Smart banner printed as the first element of the `<head>`.
* Server-side blocking of the scripts and iframes of the services to block, also with caching plugins.
* Services to block read from the Cookie Smart service: one source for banner and plugin.
* Bridge to the WP Consent API.
* Dedicated menu with icon and the [cookie_smart_preferences] shortcode.

== Upgrade Notice ==

= 1.4.0 =
Texts translated through the WordPress translation system; no change to how the banner works.

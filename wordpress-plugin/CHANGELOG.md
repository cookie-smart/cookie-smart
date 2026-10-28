# Registro delle modifiche

Novità del plugin WordPress di Cookie Smart. Le versioni sono scaricabili dalle [release](../../../releases).

## 1.5.0

- New `[cookie_smart_declaration]` shortcode: the cookie declaration for your cookie policy page, kept up to date by the Cookie Smart scanner.

## 1.4.0

- Ready for the WordPress.org plugin directory: English source texts with the Italian translation included, scripts printed with the WordPress functions, "External services" section in the readme.
- The plugin header now includes the plugin and author pages and the translations folder.
- "Blocking in the theme" now uses the WordPress template output buffer (WordPress 6.9 or later) instead of a buffer opened by the plugin.

## 1.3.0

- Setup guide for users who install the plugin without an account: how to create the account, add the website and find the site key, with links to the Cookie Smart website.
- Reminder on the Plugins page and on the Dashboard until the plugin is connected.

## 1.2.0

- Updates from the WordPress dashboard for the version distributed on GitHub, with the zip verified by its sha256 fingerprint.

## 1.1.0

- The banner is loaded from the Cookie Smart European CDN when the service provides it: faster, and available even if the main server is down.
- If the service is unreachable, the plugin keeps using the last configuration received: scripts stay blocked.

## 1.0.0

- First public release.
- Cookie Smart banner printed as the first element of the `<head>`.
- Server-side blocking of the scripts and iframes of the services to block, also with caching plugins.
- Services to block read from the Cookie Smart service: one source for banner and plugin.
- Bridge to the WP Consent API.
- Dedicated menu with icon and the [cookie_smart_preferences] shortcode.

== Upgrade Notice ==

## 1.5.0

Adds the [cookie_smart_declaration] shortcode to show the up-to-date cookie list in your cookie policy.

## 1.4.0

Texts translated through the WordPress translation system; no change to how the banner works.

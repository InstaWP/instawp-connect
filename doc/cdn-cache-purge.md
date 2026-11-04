# CDN cache auto-purge

`includes/class-instawp-cdn-cache-purge.php` clears the InstaWP CDN (Bunny) cache when site content
changes, so visitors never keep seeing an old page. It is on by default and controlled by the
`instawp_cdn_auto_purge` option (`on` / `off`).

## When it runs at all

The class adds no hooks at all unless the site is connected (`connect_id` in `instawp_api_options`)
and `cdn_enabled` there is not `false` (a missing value counts as on). Both are read from an option
every request loads anyway, so the check adds no query. The auto-purge toggle is checked when a
change is queued.

**Until client-app sends these fields, nothing here changes behaviour:** no flag is ever stored, so
every connected site keeps purging (and a site without CDN keeps calling client-app on each change,
as before). client-app must start pushing `cdn_enabled: true` on plan upgrades (`v2/manage/cdn`) no
later than it starts sending `cdn_enabled: false`, or a site upgraded in between stays "off" until
its next heartbeat (at most a day; never, if the heartbeat is turned off on that site).

`cdn_enabled` and `is_insta_site` come from client-app: the connect response (saved by
connect-helpers, which also clears them when the connect ID changes), the restore response, every
purge response (`data.cdn_enabled`), every heartbeat response (at least daily, which corrects a
stored "no CDN" on a site that gained the CDN some other way), and the `POST v2/manage/cdn` route client-app calls when a plan
change turns the CDN on. Only an explicit boolean is saved. A connected site without CDN therefore
stops calling client-app after its first answer. The WooCommerce hooks are added from
`woocommerce_loaded`, so only when WooCommerce is active. `InstaWP_CDN_Cache_Purge::purge_url()`
(WP-CLI) and the admin "Purge InstaCDN" button work whatever the flag says; a successful manual
purge sets it back to on.

## What triggers a purge

| Change | What is purged |
|--------|----------------|
| A post, page or product is published, updated, unpublished, trashed or deleted | Every page that shows it (below) |
| A post's slug changes | The same, plus its old URL |
| A post is moved out of a category or tag | The same, plus the archive it left |
| WooCommerce stock status changes (e.g. an order sells the last item) | The product's pages |
| WooCommerce stock quantity changes (an order that does not sell out) | Depends on the store's stock display format; see below. Listings show stock status, not quantity |
| WooCommerce saves a product outside the editor (REST API, CSV import, scheduled sale, quick edit) | The product's pages. A visitor's checkout save of a stock-managed product counts as a quantity change |
| A variation changes | Its parent product's pages |
| Plugin, theme or core update, theme switch, zip-replace of a plugin/theme | The whole CDN cache |

Drafts and other unpublished content never trigger a purge.

## Stock quantity changes

What a shopper sees of the quantity is set by **WooCommerce > Settings > Products > Inventory >
Stock display format**, and a quantity change purges accordingly:

| Stock display format | When the quantity changes |
|---|---|
| Never show quantity remaining | Nothing is purged |
| Only show quantity remaining when low | The product's page is purged at once if the new quantity is at or below the product's low-stock threshold (WooCommerce's own, `wc_get_low_stock_amount()`), else nothing |
| Always show quantity remaining (the default) | The product goes on a daily list (`instawp_cdn_stock_daily`); one WP-Cron run (`instawp_cdn_stock_daily_purge`), scheduled when the first product is added, purges every listed product's page. More than 20 products is one full purge |

For "only when low", the quantity before the change counts too, so a restock clears an
"Only N left". A sale's own product saves (and WooCommerce's re-save of a variation's parent) are
left to this rule; any other save in a shopper's request, such as an approved review, purges the
product's page as before. A product's mark lasts until both its save and its stock hook have run
(in either order), and a parent's until WooCommerce's re-sync at shutdown, so a later unrelated save
in the same request purges.

The daily list is read and written without a lock, so two checkouts at the same moment can drop
one product from it. That product's page then shows the old quantity until it sells again or the
CDN copy expires; checkout always checks real stock. Without WP-Cron (`DISABLE_WP_CRON` and no
system cron) the daily run never fires.

A stock status change (in stock / out of stock) always purges the product and its listings at once,
in every mode. A variation counts as its parent product. A theme that shows the quantity differently
can override the decision:

```php
// 'none', 'now' (purge the product's page at once) or 'daily'.
add_filter( 'instawp_cdn_purge_stock_quantity', function ( $action, $product ) {
	return 'now';
}, 10, 2 );
```

## "Every page that shows it"

- the post's own URL (while published)
- the home page
- the post type archive (the WooCommerce shop page for products, the blog page for posts)
- the archive of each public taxonomy term the post has (product categories and tags, post
  categories and tags) and of each parent term
- page 2 of the home page and of the post type archive (term archives: first page only)

A typical product save is about 8-10 URLs. All of it goes out as one call per request to
`connects/{id}/purge-cache-urls`. The URLs are purged one by one server-side (about 1-4 s each), so a
set larger than 20 (a bulk edit, an import batch) is sent as one full purge
(`connects/{id}/purge-cache`) instead.

## A failed purge is retried later

A purge is not retried straight away. When it fails with a 429, a 5xx, a timeout or a connection
error, its URLs (or a "full purge" marker) go into the `instawp_cdn_purge_pending` option
(autoloaded, so checking it costs no query):

- the next purge on the site sends them along with its own URLs (more than 20 together becomes one
  full purge);
- with nothing new to purge, they are sent on their own at shutdown once 5 minutes have passed, but
  only where nobody waits for it: an HTTP wp-cron run, or a request whose response PHP-FPM/LiteSpeed
  finished first. Never at the end of a WP-CLI command, including `wp cron event run`. The request claims the list before sending, so two
  requests rarely both send it (a duplicate purge is harmless). On hosts with neither, the list
  waits for the next purge or the next HTTP WP-Cron run, not for the 5 minutes.
- A purge that could not be handed to WP-Cron also goes on the list.

On multisite the gate is decided per request for the site that loaded it, so a change made with
`switch_to_blog()` into a connected subsite from an unconnected one is not purged.

A successful purge removes what it covered (a full purge: everything) that was added before it was
sent. A "no CDN" answer turns the flag off and empties the list; any other 4xx, or a 2xx that is not
a success (e.g. a firewall page), drops the URLs it was sent with. The list
is ignored when it was saved under another connect ID (reconnect, staging copy, migrated site).

## Nobody waits for the purge

The purge runs at the very end of the request (`shutdown`, priority 999). On PHP-FPM and LiteSpeed
the response is sent to the browser first (`fastcgi_finish_request()`), and the purge then runs in
the same PHP worker. The editor or shopper never waits, there is no extra request, and nothing
depends on WP-Cron. An open PHP session is closed first so it does not lock the user's next request.

Without that (e.g. mod_php), a purge triggered by a site visitor (checkout, payment callback) is
handed to WP-Cron (`instawp_cdn_purge_deferred`), and an editor's is sent at the end of the request
as before. WP-CLI and cron always send it right away.

## Filters

```php
// Add URLs this class cannot derive, e.g. a menu link to a category under a non-canonical path.
add_filter( 'instawp_cdn_purge_urls', function ( $urls, $post_ids ) {
	$urls[] = home_url( '/product-category/in-stock/' );
	return $urls;
}, 10, 2 );

// How many pages of the home page and post type archive to purge (default 2, max 10).
add_filter( 'instawp_cdn_purge_listing_pages', function () {
	return 3;
} );
```

## Known limits

- Pages that list a post in other ways (related-products blocks, widgets, menus with custom links,
  page 2 and later of a term archive, page 3 and later of the shop/home) are not derived. The CDN's own cache lifetime for HTML is the
  safety net for these; keep it short.
- A browser that already holds a page keeps it for as long as the CDN told it to. No purge can
  reach a browser.
- The deferred (WP-Cron) fallback needs WP-Cron. The plugin starts the cron runner right away (a
  non-blocking loopback) unless `DISABLE_WP_CRON` is set, in which case the system cron sends it.
- On multilingual sites only the current language's home and archives are derived; on multi-domain
  setups the purge endpoint drops URLs on another domain.
- Renaming a term or changing a term's slug, and changing a parent page's slug (which moves its
  children's URLs), do not trigger a purge yet.

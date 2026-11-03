# CDN cache auto-purge

`includes/class-instawp-cdn-cache-purge.php` clears the InstaWP CDN (Bunny) cache when site content
changes, so visitors never keep seeing an old page. It is on by default and controlled by the
`instawp_cdn_auto_purge` option (`on` / `off`). It does nothing on a site that is not connected.

## What triggers a purge

| Change | What is purged |
|--------|----------------|
| A post, page or product is published, updated, unpublished, trashed or deleted | Every page that shows it (below) |
| A post's slug changes | The same, plus its old URL |
| A post is moved out of a category or tag | The same, plus the archive it left |
| WooCommerce stock or stock status changes (e.g. an order sells the last item) | The product's pages |
| WooCommerce saves a product outside the editor (REST API, CSV import, scheduled sale, quick edit) | The product's pages |
| A variation changes | Its parent product's pages |
| Plugin, theme or core update, theme switch, zip-replace of a plugin/theme | The whole CDN cache |

Drafts and other unpublished content never trigger a purge.

## "Every page that shows it"

- the post's own URL (while published)
- the home page
- the post type archive (the WooCommerce shop page for products, the blog page for posts)
- the archive of each public taxonomy term the post has (product categories and tags, post
  categories and tags) and of each parent term
- page 2 of each of those listings

All of it goes out as one call per request to `connects/{id}/purge-cache-urls`. That endpoint takes
at most 50 URLs, so a larger set (a bulk edit, an import batch) is sent as one full purge
(`connects/{id}/purge-cache`) instead.

## Visitor requests do not wait for the purge

A purge triggered by a site visitor (a checkout that reduces stock, a payment callback) is handed to
WP-Cron (`instawp_cdn_purge_deferred`) instead of being sent at the end of that request, so the
shopper's response is never held up by the purge call. Requests from users who can `edit_posts`,
WP-CLI and cron send the purge right away.

## Filters

```php
// Add URLs this class cannot derive, e.g. a menu link to a category under a non-canonical path.
add_filter( 'instawp_cdn_purge_urls', function ( $urls, $post_ids ) {
	$urls[] = home_url( '/product-category/in-stock/' );
	return $urls;
}, 10, 2 );

// How many pages of each listing to purge (default 2, max 10).
add_filter( 'instawp_cdn_purge_listing_pages', function () {
	return 3;
} );
```

## Known limits

- Pages that list a post in other ways (related-products blocks, widgets, menus with custom links,
  page 3 and later of an archive) are not derived. The CDN's own cache lifetime for HTML is the
  safety net for these; keep it short.
- A browser that already holds a page keeps it for as long as the CDN told it to. No purge can
  reach a browser.
- Deferred purges need WP-Cron to run (WordPress's own loopback, or a system cron).

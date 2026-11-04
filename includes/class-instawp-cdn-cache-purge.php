<?php
/**
 * CDN Cache Purge - Auto-purge CDN cache when content changes, and purge the whole
 * CDN cache after plugin/theme/core updates and theme switches.
 *
 * When a post (or WooCommerce product) changes, every page that shows it is purged,
 * not only its own permalink: the home page, the post type archive (the WooCommerce
 * shop page for products), the archives of each of its taxonomy terms and their
 * parent terms, and the first pages of those listings. A post that leaves a listing
 * (unpublished, trashed, moved out of a category, slug changed) also purges the pages
 * it used to appear on.
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'InstaWP_CDN_Cache_Purge' ) ) {

	class InstaWP_CDN_Cache_Purge {

		/**
		 * Most URLs sent in one URL purge; a larger set is sent as one full purge.
		 *
		 * The endpoint accepts 50, but the URLs are purged one by one server-side
		 * (about 1-4 s each, measured on PR #583 QA): 11 URLs take ~3 s, 26 take ~30 s,
		 * and 40 outlived the 60 s HTTP timeout. A full purge is one fast call, so past
		 * this size it is cheaper for the request that sends it.
		 */
		const MAX_URLS = 20;

		/**
		 * WP-Cron hook that sends a purge deferred out of a visitor's request.
		 */
		const DEFERRED_HOOK = 'instawp_cdn_purge_deferred';

		/**
		 * Option holding purges that failed and are retried later. Autoloaded and
		 * always present, so the check on every shutdown costs no query.
		 */
		const PENDING_OPTION = 'instawp_cdn_purge_pending';

		/**
		 * Seconds to wait before a failed purge is retried on its own.
		 */
		const RETRY_DELAY = 300;

		/**
		 * Posts changed during the current request, keyed by post ID. The value says
		 * whether the post's listing pages are purged too (true), or only its own URL
		 * (false, a stock quantity change from a checkout).
		 *
		 * @var array
		 */
		private $pending_posts = array();

		/**
		 * Archive URLs of terms removed from a post during the current request,
		 * keyed by post ID, then URL. Purged only if that post is queued.
		 *
		 * @var array
		 */
		private $removed_term_urls = array();

		/**
		 * Extra URLs to purge during the current request (the pages a post used to
		 * appear on), keyed by URL.
		 *
		 * @var array
		 */
		private $pending_urls = array();

		/**
		 * Whether a full CDN purge was requested during the current request.
		 *
		 * @var bool
		 */
		private $full_purge_pending = false;

		/**
		 * Constructor.
		 */
		public function __construct() {
			// Not connected, or client-app said the site has no CDN: nothing here can
			// purge, so no hook is added at all. Both come from instawp_api_options, which
			// every request reads anyway. The auto-purge toggle is checked when something
			// is queued, as before, so this adds no query. The static purge methods still work.
			if ( empty( Helper::get_connect_id() ) || self::is_cdn_disabled() ) {
				return;
			}

			add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 20, 3 );
			add_action( 'post_updated', array( $this, 'handle_post_updated' ), 20, 3 );
			add_action( 'set_object_terms', array( $this, 'handle_object_terms' ), 20, 6 );
			add_action( 'before_delete_post', array( $this, 'handle_post_delete' ), 20, 2 );

			// This plugin loads before WooCommerce, so class_exists() would be false here.
			if ( did_action( 'woocommerce_loaded' ) ) {
				$this->register_woocommerce_hooks();
			} else {
				add_action( 'woocommerce_loaded', array( $this, 'register_woocommerce_hooks' ) );
			}

			add_action( 'upgrader_process_complete', array( $this, 'handle_upgrade_complete' ), 20, 2 );
			add_action( 'automatic_updates_complete', array( $this, 'handle_automatic_updates_complete' ), 20 );
			add_action( 'switch_theme', array( $this, 'queue_full_purge' ), 20 );
			// Replacing a plugin/theme by zip upload completes as action=install, not update.
			add_action( 'upgrader_overwrote_package', array( $this, 'queue_full_purge' ), 20 );

			add_action( self::DEFERRED_HOOK, array( $this, 'handle_deferred_purge' ) );
			// Late, so every other shutdown callback has sent its output before the
			// response may be finished early.
			add_action( 'shutdown', array( $this, 'flush_pending_purges' ), 999 );
		}

		/**
		 * WooCommerce writes that do not go through a post status transition:
		 * stock changes from orders, REST/CSV imports, scheduled sales, quick edit.
		 * Runs only when WooCommerce is active.
		 */
		public function register_woocommerce_hooks() {
			add_action( 'woocommerce_update_product', array( $this, 'handle_product_save' ), 20 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'handle_product_save' ), 20 );
			add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_quantity' ), 20 );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_quantity' ), 20 );
			add_action( 'woocommerce_product_set_stock_status', array( $this, 'handle_stock_status' ), 20 );
			add_action( 'woocommerce_variation_set_stock_status', array( $this, 'handle_stock_status' ), 20 );
		}

		/**
		 * Handle post status transitions: a post published, updated while published,
		 * or taken out of publish (draft, private, pending, trash).
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 */
		public function handle_post_transition( $new_status, $old_status, $post ) {
			if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
				return;
			}

			if ( ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			$this->queue_post( $post->ID, true );
		}

		/**
		 * Keep the URL a published post had before this update. It changes when the
		 * slug changes, and a post taken out of publish loses its pretty permalink.
		 *
		 * @param int     $post_id     Post ID.
		 * @param WP_Post $post_after  Post object after the update.
		 * @param WP_Post $post_before Post object before the update.
		 */
		public function handle_post_updated( $post_id, $post_after, $post_before ) {
			if ( ! $post_before instanceof WP_Post || 'publish' !== $post_before->post_status ) {
				return;
			}

			if ( ! $this->is_purgeable_post( $post_before ) ) {
				return;
			}

			$this->add_url( get_permalink( $post_before ) );
		}

		/**
		 * A published post deleted outright (skipping the trash, e.g. a forced REST
		 * delete) is gone by shutdown, so its URLs are collected now.
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		public function handle_post_delete( $post_id, $post = null ) {
			$post = $post instanceof WP_Post ? $post : get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status || ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			foreach ( $this->get_post_urls( $post->ID ) as $url ) {
				$this->add_url( $url );
			}
		}

		/**
		 * Purge the archives of terms removed from a published post. Terms the post
		 * still has are covered when its listing URLs are collected.
		 *
		 * @param int    $object_id  Object ID.
		 * @param array  $terms      Terms passed to wp_set_object_terms() (unused).
		 * @param array  $tt_ids     New term taxonomy IDs.
		 * @param string $taxonomy   Taxonomy slug.
		 * @param bool   $append     Whether terms were appended (unused).
		 * @param array  $old_tt_ids Term taxonomy IDs before the change.
		 */
		public function handle_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
			$removed = array_diff( array_map( 'intval', (array) $old_tt_ids ), array_map( 'intval', (array) $tt_ids ) );
			$added   = array_diff( array_map( 'intval', (array) $tt_ids ), array_map( 'intval', (array) $old_tt_ids ) );
			if ( empty( $removed ) && empty( $added ) ) {
				return;
			}

			if ( ! $this->is_listed_taxonomy( $taxonomy ) ) {
				return;
			}

			$post = get_post( $object_id );
			if ( ! $post || ! is_object_in_taxonomy( $post->post_type, $taxonomy ) || ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			// Kept whatever the status: when a save unpublishes a post AND removes a term,
			// the status is already written by the time terms change. The URLs are used
			// only if the post is queued (it is published, or was before this save).
			foreach ( $removed as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', $tt_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					foreach ( $this->get_term_urls( $term ) as $url ) {
						$this->removed_term_urls[ $post->ID ][ $url ] = true;
					}
				}
			}

			if ( 'publish' === $post->post_status ) {
				$this->queue_post( $post->ID, true );
			}
		}

		/**
		 * A WooCommerce product save outside the editor (REST, import, scheduled sale).
		 * A checkout also saves every product it sells; in a visitor's request that save
		 * is only a stock quantity change, so it purges the product's own URL.
		 *
		 * @param int $product_id Product or variation ID.
		 */
		public function handle_product_save( $product_id ) {
			$this->queue_product( $product_id, ! $this->is_visitor_request() );
		}

		/**
		 * A stock quantity change. Listings show stock status, not quantity, so only the
		 * product's own URL is purged.
		 *
		 * @param WC_Product $product Product or variation.
		 */
		public function handle_stock_quantity( $product ) {
			if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$this->queue_product( $product->get_id(), false );
			}
		}

		/**
		 * A stock status change (e.g. the last item sold): listings change too.
		 *
		 * @param int $product_id Product or variation ID.
		 */
		public function handle_stock_status( $product_id ) {
			$this->queue_product( $product_id, true );
		}

		/**
		 * Queue a published product, mapping a variation to its parent.
		 *
		 * @param int  $product_id    Product or variation ID.
		 * @param bool $with_listings Whether to purge its listing pages too.
		 */
		private function queue_product( $product_id, $with_listings ) {
			$product_id = absint( $product_id );
			if ( empty( $product_id ) ) {
				return;
			}

			if ( 'product_variation' === get_post_type( $product_id ) ) {
				$product_id = wp_get_post_parent_id( $product_id );
			}

			$post = $product_id ? get_post( $product_id ) : null;
			if ( ! $post || 'publish' !== $post->post_status || ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			$this->queue_post( $post->ID, $with_listings );
		}

		/**
		 * Queue a post for this request. Purging its listings wins over not purging them.
		 *
		 * @param int  $post_id       Post ID.
		 * @param bool $with_listings Whether to purge its listing pages too.
		 */
		private function queue_post( $post_id, $with_listings ) {
			$this->pending_posts[ $post_id ] = ! empty( $this->pending_posts[ $post_id ] ) || $with_listings;
		}

		/**
		 * Queue a full purge after a plugin, theme or core update.
		 *
		 * upgrader_process_complete fires for every update source that goes through the
		 * WP upgrader: the admin screens, WP-CLI, background auto-updates and external
		 * managers such as ManageWP/MainWP. Installs and translation updates are ignored.
		 *
		 * @param WP_Upgrader $upgrader Upgrader instance (unused).
		 * @param array       $options  Update context: type, action and affected items.
		 */
		public function handle_upgrade_complete( $upgrader, $options ) {
			if ( empty( $options['action'] ) || 'update' !== $options['action'] ) {
				return;
			}

			if ( empty( $options['type'] ) || ! in_array( $options['type'], array( 'plugin', 'theme', 'core' ), true ) ) {
				return;
			}

			$this->queue_full_purge();
		}

		/**
		 * Queue a full purge after a background auto-update run that updated
		 * a plugin, theme or core (translation-only runs are ignored).
		 *
		 * @param array $results Update results keyed by type.
		 */
		public function handle_automatic_updates_complete( $results ) {
			if ( ! is_array( $results ) ) {
				return;
			}

			foreach ( array( 'core', 'plugin', 'theme' ) as $type ) {
				if ( ! empty( $results[ $type ] ) ) {
					$this->queue_full_purge();
					return;
				}
			}
		}

		/**
		 * Request a full CDN purge at the end of the current request.
		 *
		 * Coalesced per request, so a bulk update of N items produces one purge.
		 */
		public function queue_full_purge() {
			if ( ! $this->can_purge() ) {
				return;
			}

			$this->full_purge_pending = true;
		}

		/**
		 * Flush pending purges on shutdown.
		 *
		 * One call per request: a full purge if one was requested or if the changed
		 * posts add up to more URLs than one call accepts, else one URL purge.
		 * The response is finished first (PHP-FPM/LiteSpeed), so nobody waits for the
		 * purge call; without that, a visitor's purge goes to WP-Cron instead.
		 *
		 * Purges that failed earlier (the pending list) go out with this request's
		 * purge. With nothing new to purge, the pending list is sent on its own once
		 * it is due, but only where nobody waits for it.
		 */
		public function flush_pending_purges() {
			try {
				$full = $this->full_purge_pending;
				$urls = $full ? array() : $this->collect_urls();

				$this->full_purge_pending = false;
				$this->pending_posts      = array();
				$this->removed_term_urls  = array();
				$this->pending_urls       = array();

				$pending = self::get_pending();

				if ( ! $full && is_array( $urls ) && empty( $urls ) ) {
					$this->retry_pending( $pending );

					return;
				}

				if ( $pending['full'] ) {
					$full = true;
				} elseif ( ! $full && is_array( $urls ) && ! empty( $pending['urls'] ) ) {
					$urls = array_values( array_unique( array_merge( $urls, array_keys( $pending['urls'] ) ) ) );
				}

				if ( $full || null === $urls || count( $urls ) > self::MAX_URLS ) {
					$full = true;
					$urls = array();
				}

				// WP-CLI and cron have no one waiting on a response.
				if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
					$this->send( $full, $urls );

					return;
				}

				// Send the response to the browser first, then purge in this same worker:
				// nobody waits for the purge and no extra request is made.
				if ( $this->finish_request() ) {
					$this->send( $full, $urls );

					return;
				}

				// No way to finish early (not PHP-FPM/LiteSpeed): a visitor's purge goes to
				// WP-Cron so they never wait, an editor's is sent here as before.
				if ( $this->is_visitor_request() ) {
					$this->defer( $full, $urls );

					return;
				}

				$this->send( $full, $urls );
			} catch ( \Throwable $th ) {
				Helper::add_error_log(
					array(
						'message' => 'CDN cache purge threw',
						'error'   => $th->getMessage(),
					)
				);
			}
		}

		/**
		 * Send the pending list on its own when it is due and nobody waits for it (cron,
		 * or a response finished early). Other requests leave it for a later one.
		 *
		 * @param array $pending Pending list from get_pending().
		 */
		private function retry_pending( array $pending ) {
			if ( ! self::is_pending_due( $pending ) ) {
				return;
			}

			// Not under WP-CLI: it would hold up whatever unrelated command is ending.
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ! $this->can_purge() ) {
				return;
			}

			if ( ! wp_doing_cron() && ! $this->finish_request() ) {
				return;
			}

			// Claim it first, so a request running at the same time skips it. Re-read it,
			// as another request may have sent or claimed it since this one started.
			$pending = self::get_pending_fresh();
			if ( ! self::is_pending_due( $pending ) ) {
				return;
			}

			$pending['next_at'] = time() + self::RETRY_DELAY;
			self::save_pending( $pending );

			$full = $pending['full'] || count( $pending['urls'] ) > self::MAX_URLS;
			$this->send( $full, $full ? array() : array_keys( $pending['urls'] ) );
		}

		/**
		 * Send a purge deferred out of a visitor's request (WP-Cron callback).
		 *
		 * @param array $payload Array with 'full' (bool) and 'urls' (string[]).
		 */
		public function handle_deferred_purge( $payload ) {
			try {
				if ( ! is_array( $payload ) || ! $this->can_purge() ) {
					return;
				}

				$full = ! empty( $payload['full'] );
				$urls = ! empty( $payload['urls'] ) && is_array( $payload['urls'] ) ? array_values( $payload['urls'] ) : array();

				if ( ! $full && empty( $urls ) ) {
					return;
				}

				$this->send( $full, $urls );
			} catch ( \Throwable $th ) {
				Helper::add_error_log(
					array(
						'message' => 'Deferred CDN cache purge threw',
						'error'   => $th->getMessage(),
					)
				);
			}
		}

		/**
		 * Hand a purge to WP-Cron and start the cron runner without waiting for it
		 * (unless the site runs cron from the system instead).
		 *
		 * @param bool  $full Whether to purge the whole CDN cache.
		 * @param array $urls URLs to purge when not a full purge.
		 */
		private function defer( $full, array $urls ) {
			$payload = array(
				'full' => $full,
				'urls' => $urls,
			);

			$scheduled = wp_schedule_single_event( time(), self::DEFERRED_HOOK, array( $payload ) );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) {
				// Kept for the next purge or the stand-alone retry rather than lost.
				self::add_pending( $full, $urls );

				return;
			}

			if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
				spawn_cron();
			}
		}

		/**
		 * Send a full purge or a URL purge.
		 *
		 * @param bool  $full Whether to purge the whole CDN cache.
		 * @param array $urls URLs to purge when not a full purge.
		 */
		private function send( $full, array $urls ) {
			$sent_at  = microtime( true );
			$response = $full ? instawp_purge_cdn_cache() : self::purge_urls( $urls );

			if ( is_array( $response ) && ! empty( $response['success'] ) ) {
				return;
			}

			// Not retried now: a rate limit or a busy server needs time, and a timed-out
			// call is usually still being worked through. It goes out with the next purge,
			// or on its own after RETRY_DELAY.
			if ( self::is_retryable( $response ) ) {
				self::add_pending( $full, $urls );

				return;
			}

			// It would fail the same way again (e.g. URLs not on the site's domain): drop it,
			// so it does not ride along with, and spoil, every later purge.
			self::clear_covered( $full, $urls, $sent_at );

			Helper::add_error_log(
				array(
					'message' => $full ? 'CDN full cache purge failed' : 'CDN cache purge failed',
					'error'   => is_wp_error( $response ) ? $response->get_error_message() : ( is_array( $response ) && isset( $response['message'] ) ? $response['message'] : '' ),
					'code'    => is_array( $response ) && isset( $response['code'] ) ? $response['code'] : '',
					'urls'    => $urls,
				)
			);
		}

		/**
		 * Whether a failed purge is worth retrying later: a rate limit (429), a server
		 * error (5xx), or no HTTP response at all (timeout, connection error). Any other
		 * 4xx fails the same way every time.
		 *
		 * @param mixed $response Response from purge_urls() or instawp_purge_cdn_cache().
		 *
		 * @return bool
		 */
		private static function is_retryable( $response ) {
			if ( ! is_array( $response ) ) {
				return false;
			}

			$code = isset( $response['code'] ) ? intval( $response['code'] ) : 0;

			// No HTTP code also means the call was never made (no API key or domain);
			// that does not fix itself.
			if ( 0 === $code ) {
				return ! empty( Helper::get_api_key() ) && ! empty( Helper::get_api_domain() );
			}

			return 429 === $code || 500 <= $code;
		}

		/**
		 * Finish the HTTP response so the client stops waiting, keeping the PHP worker
		 * running. Available on PHP-FPM and LiteSpeed.
		 *
		 * @return bool Whether the response was finished.
		 */
		private function finish_request() {
			// A plugin's open PHP session would stay locked while the purge runs and
			// block the user's next request; it is fully written by now.
			if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
				session_write_close();
			}

			if ( function_exists( 'fastcgi_finish_request' ) ) {
				return (bool) fastcgi_finish_request();
			}

			if ( function_exists( 'litespeed_finish_request' ) ) {
				return (bool) litespeed_finish_request();
			}

			return false;
		}

		/**
		 * Collect every URL to purge for the posts changed in this request.
		 *
		 * @return array|null Unique URLs, or null when there are too many for one call.
		 */
		private function collect_urls() {
			if ( empty( $this->pending_posts ) && empty( $this->pending_urls ) ) {
				return array();
			}

			$urls = $this->pending_urls;

			foreach ( $this->pending_posts as $post_id => $with_listings ) {
				$post_urls = $with_listings ? $this->get_post_urls( $post_id ) : $this->get_permalink_urls( $post_id );
				if ( ! empty( $this->removed_term_urls[ $post_id ] ) ) {
					$post_urls = array_merge( $post_urls, array_keys( $this->removed_term_urls[ $post_id ] ) );
				}

				foreach ( $post_urls as $url ) {
					$urls[ $url ] = true;
				}

				// A bulk change ends as one full purge anyway; stop deriving URLs.
				if ( count( $urls ) > self::MAX_URLS ) {
					return null;
				}
			}

			/**
			 * Filter the URLs purged from the CDN after content changes.
			 *
			 * Use it to add pages this class cannot derive, e.g. a menu link to a
			 * category under a non-canonical path. More than 20 URLs become one
			 * full purge.
			 *
			 * @param string[] $urls     URLs to purge.
			 * @param int[]    $post_ids IDs of the posts changed in this request.
			 */
			$urls = apply_filters( 'instawp_cdn_purge_urls', array_keys( $urls ), array_keys( $this->pending_posts ) );

			return array_values( array_unique( array_filter( (array) $urls, array( $this, 'is_valid_url' ) ) ) );
		}

		/**
		 * The URL of a published post on its own.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @return array URLs.
		 */
		private function get_permalink_urls( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post || 'publish' !== $post->post_status ) {
				return array();
			}

			return array_filter( array( get_permalink( $post ) ), array( $this, 'is_valid_url' ) );
		}

		/**
		 * URLs of every page that shows a post: its permalink (while published), the
		 * home page, its post type archive and its term archives, plus the first
		 * pages of those listings.
		 *
		 * @param int $post_id Post ID.
		 *
		 * @return array URLs.
		 */
		private function get_post_urls( $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return array();
			}

			$urls = array();
			if ( 'publish' === $post->post_status ) {
				$urls[] = get_permalink( $post );
			}

			$home     = home_url( '/' );
			$listings = array( $home );

			// For posts the archive is often the home page itself, without the trailing slash.
			$archive = get_post_type_archive_link( $post->post_type );
			if ( $this->is_valid_url( $archive ) && untrailingslashit( $archive ) !== untrailingslashit( $home ) ) {
				$listings[] = $archive;
			}

			$term_urls = array();
			foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
				if ( ! $this->is_listed_taxonomy( $taxonomy ) ) {
					continue;
				}

				$terms = get_the_terms( $post, $taxonomy );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					$term_urls = array_merge( $term_urls, $this->get_term_urls( $term ) );
				}
			}

			// Later pages only for home and the post type archive, where new items land;
			// term archives get their first page, which keeps a save to ~10 URLs.
			foreach ( $listings as $listing ) {
				$urls = array_merge( $urls, $this->with_pagination( $listing ) );
			}

			$urls = array_merge( $urls, $term_urls );

			return array_filter( $urls, array( $this, 'is_valid_url' ) );
		}

		/**
		 * Archive URLs (first page) of a term and its ancestors.
		 *
		 * @param WP_Term $term Term.
		 *
		 * @return array URLs.
		 */
		private function get_term_urls( $term ) {
			$term_ids = array_merge( array( (int) $term->term_id ), get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
			$urls     = array();

			foreach ( $term_ids as $term_id ) {
				$link = get_term_link( (int) $term_id, $term->taxonomy );
				if ( ! $this->is_valid_url( $link ) ) {
					continue;
				}

				$urls[] = $link;
			}

			return $urls;
		}

		/**
		 * A listing URL plus its next pages (page 2 by default).
		 *
		 * @param string $url Listing URL.
		 *
		 * @return array URLs.
		 */
		private function with_pagination( $url ) {
			if ( ! $this->is_valid_url( $url ) ) {
				return array();
			}

			/**
			 * Filter how many pages of each listing are purged (1 = the first page only).
			 *
			 * @param int $pages Number of pages, 1-10.
			 */
			$pages = min( 10, max( 1, (int) apply_filters( 'instawp_cdn_purge_listing_pages', 2 ) ) );
			$urls  = array( $url );

			for ( $page = 2; $page <= $pages; $page++ ) {
				$urls[] = $this->get_paged_url( $url, $page );
			}

			return $urls;
		}

		/**
		 * URL of page N of a listing, for pretty and plain permalinks.
		 *
		 * @param string $url  Listing URL.
		 * @param int    $page Page number.
		 *
		 * @return string URL.
		 */
		private function get_paged_url( $url, $page ) {
			global $wp_rewrite;

			if ( $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks() ) {
				$parts = explode( '?', $url, 2 );
				$paged = user_trailingslashit( trailingslashit( $parts[0] ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' );

				return isset( $parts[1] ) ? $paged . '?' . $parts[1] : $paged;
			}

			return add_query_arg( 'paged', $page, $url );
		}

		/**
		 * Whether a post is one whose pages should be purged: a public post type, not
		 * a revision, on a site where auto-purge is on and connected.
		 *
		 * @param WP_Post $post Post object.
		 *
		 * @return bool
		 */
		private function is_purgeable_post( $post ) {
			if ( ! $post instanceof WP_Post || wp_is_post_revision( $post->ID ) ) {
				return false;
			}

			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object || ! $post_type_object->public ) {
				return false;
			}

			return $this->can_purge();
		}

		/**
		 * Whether a taxonomy has public archive pages that list posts.
		 *
		 * @param string $taxonomy Taxonomy slug.
		 *
		 * @return bool
		 */
		private function is_listed_taxonomy( $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );

			return $taxonomy_object && $taxonomy_object->public && $taxonomy_object->publicly_queryable;
		}

		/**
		 * Add one extra URL to purge in this request.
		 *
		 * @param mixed $url URL.
		 */
		private function add_url( $url ) {
			if ( $this->is_valid_url( $url ) ) {
				$this->pending_urls[ $url ] = true;
			}
		}

		/**
		 * Whether a value is a usable URL.
		 *
		 * @param mixed $url Value.
		 *
		 * @return bool
		 */
		private function is_valid_url( $url ) {
			return is_string( $url ) && '' !== $url;
		}

		/**
		 * Whether the current request comes from a site visitor (checkout, payment
		 * callback) rather than an editor, WP-CLI or cron. A visitor's purge is
		 * deferred so they never wait for it.
		 *
		 * @return bool
		 */
		private function is_visitor_request() {
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
				return false;
			}

			return ! current_user_can( 'edit_posts' );
		}

		/**
		 * Purge an array of URLs via the client-app API.
		 *
		 * @param array $urls URLs to purge.
		 *
		 * @return array|false API response or false on failure.
		 */
		public static function purge_urls( array $urls ) {
			$connect_id = Helper::get_connect_id();
			if ( empty( $connect_id ) ) {
				Helper::add_error_log(
					array(
						'message' => 'CDN cache purge failed: no connect_id',
						'urls'    => $urls,
					)
				);
				return false;
			}

			$sent_at  = microtime( true );
			$response = Curl::do_curl( "connects/{$connect_id}/purge-cache-urls", array( 'urls' => $urls ) );

			self::after_purge( false, $urls, $sent_at, $response );

			return $response;
		}

		/**
		 * Bookkeeping after any purge call (auto, full, manual, WP-CLI): keep the CDN
		 * flag in step with what client-app reports, and drop from the pending list
		 * what a successful purge covered.
		 *
		 * @param bool  $full     Whether it was a full purge.
		 * @param array $urls     URLs sent, for a URL purge.
		 * @param float $sent_at  microtime() just before the call.
		 * @param mixed $response Curl::do_curl() response.
		 */
		public static function after_purge( $full, array $urls, $sent_at, $response ) {
			self::save_cdn_flags_from_response( $response );

			if ( is_array( $response ) && ! empty( $response['success'] ) ) {
				self::clear_covered( $full, $urls, $sent_at );
			}
		}

		/**
		 * Whether client-app has said this site has no CDN. Unknown counts as on.
		 *
		 * @return bool
		 */
		public static function is_cdn_disabled() {
			$api_options = Helper::get_options();

			return is_array( $api_options ) && isset( $api_options['cdn_enabled'] ) && false === $api_options['cdn_enabled'];
		}

		/**
		 * Save the CDN flags client-app sent in a response's data, if any.
		 *
		 * @param mixed $response Curl::do_curl() response.
		 */
		public static function save_cdn_flags_from_response( $response ) {
			if ( is_array( $response ) && ! empty( $response['data'] ) && is_array( $response['data'] ) ) {
				self::save_cdn_flags( $response['data'] );
			}
		}

		/**
		 * Save cdn_enabled / is_insta_site into instawp_api_options. Only explicit
		 * booleans are taken, and the option is written only when a value changes.
		 * A site whose CDN turns off also loses its pending list.
		 *
		 * @param array $flags Values keyed by flag name.
		 */
		public static function save_cdn_flags( array $flags ) {
			$api_options = Helper::get_options();
			$api_options = is_array( $api_options ) ? $api_options : array();
			$changed     = false;

			foreach ( array( 'cdn_enabled', 'is_insta_site' ) as $key ) {
				if ( ! isset( $flags[ $key ] ) || ! is_bool( $flags[ $key ] ) ) {
					continue;
				}

				if ( ! isset( $api_options[ $key ] ) || $api_options[ $key ] !== $flags[ $key ] ) {
					$api_options[ $key ] = $flags[ $key ];
					$changed             = true;
				}
			}

			if ( ! $changed ) {
				return;
			}

			// This request's copy may be from before a long purge call; write on a fresh one so
			// a key or token saved meanwhile is kept. No usable row means the read failed or the
			// site was disconnected meanwhile: write nothing then. Writing an empty array would
			// drop the API key, and writing this request's copy could bring back a deleted one.
			// The flags arrive again with the next heartbeat or purge response.
			$fresh = maybe_unserialize( self::read_option_fresh( 'instawp_api_options' ) );
			if ( ! is_array( $fresh ) || empty( $fresh['api_key'] ) ) {
				return;
			}
			foreach ( array( 'cdn_enabled', 'is_insta_site' ) as $key ) {
				if ( isset( $flags[ $key ] ) && is_bool( $flags[ $key ] ) ) {
					$fresh[ $key ] = $flags[ $key ];
				}
			}

			Helper::set_settings( $fresh );

			if ( isset( $flags['cdn_enabled'] ) && false === $flags['cdn_enabled'] ) {
				self::save_pending( self::empty_pending() );
			}
		}

		/**
		 * An empty pending list.
		 *
		 * @return array
		 */
		private static function empty_pending() {
			return array(
				'connect_id' => 0,
				'urls'       => array(),
				'full'       => false,
				'full_at'    => 0,
				'next_at'    => 0,
			);
		}

		/**
		 * A stored pending list in its expected shape. A list saved under another
		 * connect ID (a reconnect, a staging copy, a migrated site) counts as empty.
		 *
		 * @param mixed $value Stored value.
		 *
		 * @return array
		 */
		private static function normalize_pending( $value ) {
			$empty = self::empty_pending();
			if ( ! is_array( $value ) || empty( $value['connect_id'] ) || (int) $value['connect_id'] !== (int) Helper::get_connect_id() ) {
				return $empty;
			}

			$pending         = array_merge( $empty, array_intersect_key( $value, $empty ) );
			$pending['urls'] = is_array( $pending['urls'] ) ? $pending['urls'] : array();
			$pending['full'] = ! empty( $pending['full'] );

			return $pending;
		}

		/**
		 * The pending list, from this request's option cache. Creates the option on
		 * first use, autoloaded, so later reads cost no query.
		 *
		 * @return array
		 */
		private static function get_pending() {
			$value = get_option( self::PENDING_OPTION, false );

			if ( false === $value ) {
				add_option( self::PENDING_OPTION, self::empty_pending(), '', 'yes' );
				// Debounce data from the previous version of this class.
				delete_option( 'instawp_cdn_purge_queue' );

				return self::empty_pending();
			}

			return self::normalize_pending( $value );
		}

		/**
		 * The pending list as stored right now. Another request may have changed it
		 * since this one loaded its options, so it is read from the database, and the
		 * request's cache is updated so update_option() compares against it.
		 *
		 * @return array
		 */
		private static function get_pending_fresh() {
			$raw = self::read_option_fresh( self::PENDING_OPTION );
			if ( null === $raw ) {
				return self::get_pending();
			}

			return self::normalize_pending( maybe_unserialize( $raw ) );
		}

		/**
		 * An option's stored value read from the database, bypassing this request's copy.
		 * The request's cache is updated to match, so update_option() compares against
		 * what is really stored, whether the option is autoloaded or not.
		 *
		 * @param string $name Option name.
		 *
		 * @return string|null Raw (serialized) value, or null when the row does not exist.
		 */
		private static function read_option_fresh( $name ) {
			global $wpdb;

			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", $name ) );
			if ( null === $raw ) {
				return null;
			}

			// Forced, as update_option() does: with a persistent object cache the request's own
			// copy may be minutes old, and writing it back would revert other options.
			$alloptions = wp_load_alloptions( true );
			if ( isset( $alloptions[ $name ] ) ) {
				$alloptions[ $name ] = $raw;
				wp_cache_set( 'alloptions', $alloptions, 'options' );
			} else {
				wp_cache_set( $name, $raw, 'options' );
			}

			return $raw;
		}

		/**
		 * Store the pending list under the current connect ID, keeping it autoloaded.
		 *
		 * @param array $pending Pending list.
		 */
		private static function save_pending( array $pending ) {
			$pending['connect_id'] = (int) Helper::get_connect_id();

			update_option( self::PENDING_OPTION, $pending, true );
		}

		/**
		 * Whether the pending list has something to send and its wait is over.
		 *
		 * @param array $pending Pending list.
		 *
		 * @return bool
		 */
		private static function is_pending_due( array $pending ) {
			return ( $pending['full'] || ! empty( $pending['urls'] ) ) && time() >= (int) $pending['next_at'];
		}

		/**
		 * Add a failed purge to the pending list, to be retried after RETRY_DELAY or
		 * with the next purge, whichever comes first.
		 *
		 * @param bool  $full Whether it was a full purge.
		 * @param array $urls URLs, for a URL purge.
		 */
		private static function add_pending( $full, array $urls ) {
			$pending = self::get_pending_fresh();
			$now     = microtime( true );

			if ( $full ) {
				$pending['full']    = true;
				$pending['full_at'] = $now;
			} else {
				foreach ( $urls as $url ) {
					$pending['urls'][ $url ] = $now;
				}
			}

			$pending['next_at'] = time() + self::RETRY_DELAY;

			self::save_pending( $pending );
		}

		/**
		 * Remove from the pending list what a successful purge covered: entries added
		 * before it was sent (all of them for a full purge, the URLs it sent for a URL
		 * purge). An entry added while it was in flight stays.
		 *
		 * @param bool  $full    Whether it was a full purge.
		 * @param array $urls    URLs sent, for a URL purge.
		 * @param float $sent_at microtime() just before the call.
		 */
		private static function clear_covered( $full, array $urls, $sent_at ) {
			$pending = self::get_pending();
			if ( ! $pending['full'] && empty( $pending['urls'] ) ) {
				return;
			}

			$pending = self::get_pending_fresh();
			$before  = $pending;
			$covered = $full ? array_keys( $pending['urls'] ) : $urls;

			foreach ( $covered as $url ) {
				if ( isset( $pending['urls'][ $url ] ) && (float) $pending['urls'][ $url ] <= $sent_at ) {
					unset( $pending['urls'][ $url ] );
				}
			}

			if ( $full && $pending['full'] && (float) $pending['full_at'] <= $sent_at ) {
				$pending['full']    = false;
				$pending['full_at'] = 0;
			}

			if ( ! $pending['full'] && empty( $pending['urls'] ) ) {
				$pending['next_at'] = 0;
			}

			if ( $pending !== $before ) {
				self::save_pending( $pending );
			}
		}

		/**
		 * Purge a single URL. Convenience method for WP-CLI.
		 *
		 * @param string $url URL to purge.
		 *
		 * @return array|false API response or false on failure.
		 */
		public static function purge_url( $url ) {
			return self::purge_urls( array( $url ) );
		}

		/**
		 * Whether auto-purge is on, the site is connected, and client-app has not said
		 * the site has no CDN.
		 *
		 * @return bool
		 */
		private function can_purge() {
			return $this->is_auto_purge_enabled() && ! empty( Helper::get_connect_id() ) && ! self::is_cdn_disabled();
		}

		/**
		 * Check if auto-purge is enabled.
		 *
		 * @return bool
		 */
		public function is_auto_purge_enabled() {
			return Option::get_option( 'instawp_cdn_auto_purge', 'on' ) === 'on';
		}
	}

	new InstaWP_CDN_Cache_Purge();
}

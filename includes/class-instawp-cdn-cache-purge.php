<?php
/**
 * CDN Cache Purge - Auto-purge CDN cache when content changes, and purge the whole
 * CDN cache on a theme switch. Core, plugin and theme updates do not purge.
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
		 *
		 * Shape: { connect_id, urls: { url: added_at }, full, full_at, next_at }.
		 * added_at / full_at are microtime(true) floats, compared only with a purge's
		 * sent_at (also microtime) so an entry added during a purge is not cleared by it.
		 * next_at is whole seconds, compared only with time().
		 */
		const PENDING_OPTION = 'instawp_cdn_purge_pending';

		/**
		 * Seconds to wait before a failed purge is retried on its own.
		 */
		const RETRY_DELAY = 300;

		/**
		 * Products whose stock quantity changed on a store that always shows the
		 * quantity, purged together once a day (keyed by product ID). Autoloaded and
		 * small: past MAX_URLS products the daily run is a full purge anyway.
		 */
		const STOCK_DAILY_OPTION = 'instawp_cdn_stock_daily';

		/**
		 * WP-Cron hook of the daily stock-quantity purge.
		 */
		const STOCK_DAILY_HOOK = 'instawp_cdn_stock_daily_purge';

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
		 * Products whose stock WooCommerce is changing right now, keyed by ID:
		 * { before: quantity before the change, saved: its save was seen, done: its
		 * set_stock hook ran }. WooCommerce fires the save and set_stock in either order
		 * (wc_update_product_stock(): save first; a plugin's set_stock_quantity() + save():
		 * set_stock first), so a mark ends once both have been seen. A later, unrelated
		 * save of the same product in the same request then purges normally.
		 *
		 * @var array
		 */
		private $stock_changes = array();

		/**
		 * What each WooCommerce product save in this request changed, keyed by ID
		 * (from woocommerce_before_*_object_save). Lets a save that only carries a stock
		 * change be told apart from a real edit made in the same save.
		 *
		 * @var array
		 */
		private $save_changes = array();

		/**
		 * Parents of variations whose stock changed in this request, keyed by ID.
		 * WooCommerce re-saves them at shutdown (priority 10) to sync them; the marks are
		 * cleared right after that (priority 11).
		 *
		 * @var array
		 */
		private $stock_sync_parents = array();

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
			try {
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

				// A theme switch changes every page. Core, plugin and theme updates do not purge:
				// asset URLs usually carry ?ver=, so cached HTML keeps working, and a full purge after a
				// fleet-wide auto-update would send every site cold to its origin at once.
				add_action( 'switch_theme', array( $this, 'queue_full_purge' ), 20 );

				add_action( self::DEFERRED_HOOK, array( $this, 'handle_deferred_purge' ) );
				add_action( self::STOCK_DAILY_HOOK, array( $this, 'handle_daily_stock_purge' ) );
				// Right after WooCommerce re-syncs variation parents at shutdown (priority 10).
				add_action( 'shutdown', array( $this, 'clear_stock_sync_parents' ), 11 );
				// Late, so every other shutdown callback has sent its output before the
				// response may be finished early.
				add_action( 'shutdown', array( $this, 'flush_pending_purges' ), 999 );
			} catch ( \Throwable $th ) {
				self::log_throwable( '__construct', $th );
			}
		}

		/**
		 * WooCommerce writes that do not go through a post status transition:
		 * stock changes from orders, REST/CSV imports, scheduled sales, quick edit.
		 * Runs only when WooCommerce is active.
		 */
		public function register_woocommerce_hooks() {
			try {
				// Variations fire this one too (their object_type is "product"). Last, so it sees
				// changes other before-save callbacks add.
				add_action( 'woocommerce_before_product_object_save', array( $this, 'handle_before_object_save' ), PHP_INT_MAX );
				add_action( 'woocommerce_product_before_set_stock', array( $this, 'handle_before_set_stock' ), 20 );
				add_action( 'woocommerce_variation_before_set_stock', array( $this, 'handle_before_set_stock' ), 20 );
				add_action( 'woocommerce_update_product', array( $this, 'handle_product_save' ), 20 );
				add_action( 'woocommerce_update_product_variation', array( $this, 'handle_product_save' ), 20 );
				add_action( 'woocommerce_product_set_stock', array( $this, 'handle_stock_quantity' ), 20 );
				add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_stock_quantity' ), 20 );
				add_action( 'woocommerce_product_set_stock_status', array( $this, 'handle_stock_status' ), 20 );
				add_action( 'woocommerce_variation_set_stock_status', array( $this, 'handle_stock_status' ), 20 );
			} catch ( \Throwable $th ) {
				self::log_throwable( 'register_woocommerce_hooks', $th );
			}
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
			try {
				if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
					return;
				}

				if ( ! $this->is_purgeable_post( $post ) ) {
					return;
				}

				$this->queue_post( $post->ID, true );
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_post_transition', $th );
			}
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
			try {
				if ( ! $post_before instanceof WP_Post || 'publish' !== $post_before->post_status ) {
					return;
				}

				if ( ! $this->is_purgeable_post( $post_before ) ) {
					return;
				}

				$this->add_url( get_permalink( $post_before ) );
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_post_updated', $th );
			}
		}

		/**
		 * A published post deleted outright (skipping the trash, e.g. a forced REST
		 * delete) is gone by shutdown, so its URLs are collected now.
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		public function handle_post_delete( $post_id, $post = null ) {
			try {
				$post = $post instanceof WP_Post ? $post : get_post( $post_id );
				if ( ! $post || 'publish' !== $post->post_status || ! $this->is_purgeable_post( $post ) ) {
					return;
				}

				foreach ( $this->get_post_urls( $post->ID ) as $url ) {
					$this->add_url( $url );
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_post_delete', $th );
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
			try {
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
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_object_terms', $th );
			}
		}

		/**
		 * WooCommerce is about to change a product's stock (wc_update_product_stock(),
		 * every sale). Remember it, with the quantity before the change, so its saves in
		 * this request are left to the stock hooks.
		 *
		 * @param WC_Product $product Product or variation whose stock is changing.
		 */
		public function handle_before_set_stock( $product ) {
			try {
				if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
					return;
				}

				$product_id = (int) $product->get_id();
				if ( ! array_key_exists( $product_id, $this->stock_changes ) ) {
					// WC_Data's own $data holds the stored value even when a plugin has already
					// set a new quantity on the object (set_stock_quantity() then save()), where
					// get_stock_quantity() would return the new one. Read directly: get_data()
					// also loads the product's meta, which is a query on every sale.
					$data = self::read_wc_data_property( $product, 'data' );
					if ( is_array( $data ) && array_key_exists( 'stock_quantity', $data ) ) {
						$before = $data['stock_quantity'];
					} else {
						$before = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null;
					}

					$this->stock_changes[ $product_id ] = array(
						'before' => $before,
						'saved'  => false,
						'done'   => false,
					);
				}

				// WooCommerce re-saves the parent of a variation at shutdown to sync it.
				$parent_id = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;
				if ( $parent_id ) {
					$this->stock_sync_parents[ $parent_id ] = true;
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_before_set_stock', $th );
			}
		}

		/**
		 * WooCommerce is about to save a product: remember which fields the save changes.
		 *
		 * @param WC_Product $product Product or variation being saved.
		 */
		public function handle_before_object_save( $product ) {
			try {
				if ( is_object( $product ) && method_exists( $product, 'get_id' ) && method_exists( $product, 'get_changes' ) ) {
					$changes = array_keys( (array) $product->get_changes() );

					if ( self::has_meta_changes( $product ) ) {
						$changes[] = 'meta_data';
					}

					$this->save_changes[ (int) $product->get_id() ] = $changes;
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_before_object_save', $th );
			}
		}

		/**
		 * A protected WC_Data property (data, meta_data) as it is, without the getters,
		 * which load the product's meta from the database when it is not loaded yet.
		 *
		 * @param mixed  $object   A WooCommerce data object.
		 * @param string $property Property name.
		 *
		 * @return mixed The value, or null when $object is not a WC_Data.
		 */
		private static function read_wc_data_property( $object, $property ) {
			if ( ! class_exists( 'WC_Data' ) || ! $object instanceof \WC_Data ) {
				return null;
			}

			$read = \Closure::bind(
				function () use ( $property ) {
					return isset( $this->$property ) ? $this->$property : null;
				},
				$object,
				'WC_Data'
			);

			return $read ? $read() : null;
		}

		/**
		 * Whether a product carries unsaved meta changes (update_meta_data(), REST
		 * meta_data, delete_meta_data()), which get_changes() does not report. Only meta
		 * already loaded on the object is looked at: WooCommerce loads meta lazily, every
		 * meta setter loads it first, so meta that was never loaded has no unsaved changes,
		 * and get_meta_data() would cost a query. Deleted entries (value null) count too.
		 *
		 * @param WC_Product $product Product or variation.
		 *
		 * @return bool
		 */
		private static function has_meta_changes( $product ) {
			$meta = self::read_wc_data_property( $product, 'meta_data' );

			if ( ! is_array( $meta ) ) {
				return false;
			}

			foreach ( $meta as $entry ) {
				if ( ! is_object( $entry ) ) {
					continue;
				}

				if ( empty( $entry->id ) || null === $entry->value || ( method_exists( $entry, 'get_changes' ) && $entry->get_changes() ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * A WooCommerce product save outside the editor (REST, import, scheduled sale,
		 * stock sync, checkout). A save that only carries a stock change (a sale, a
		 * restock, an inventory sync, and the parent re-sync of a sold variation) is left
		 * to the stock hooks (handle_stock_quantity / handle_stock_status), in every
		 * context. Any other save purges the product and its listings, or only its own
		 * URL in a visitor's request (e.g. an approved review updating the rating).
		 *
		 * @param int $product_id Product or variation ID.
		 */
		public function handle_product_save( $product_id ) {
			try {
				$product_id = absint( $product_id );
				$stock_only = $this->is_stock_only_save( $product_id );
				unset( $this->save_changes[ $product_id ] );

				if ( isset( $this->stock_changes[ $product_id ] ) ) {
					// The stock change's own save. If set_stock already ran, the change is over.
					if ( $this->stock_changes[ $product_id ]['done'] ) {
						unset( $this->stock_changes[ $product_id ] );
					} else {
						$this->stock_changes[ $product_id ]['saved'] = true;
					}

					if ( $stock_only ) {
						return;
					}
				} elseif ( isset( $this->stock_sync_parents[ $product_id ] ) && $stock_only ) {
					return;
				}

				$this->queue_product( $product_id, ! $this->is_visitor_request() );
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_product_save', $th );
			}
		}

		/**
		 * Whether a product's save changes nothing but its stock. A save WooCommerce did
		 * not announce (no before_object_save seen) counts as stock-only, which is what a
		 * marked product's save is.
		 *
		 * @param int $product_id Product or variation ID.
		 *
		 * @return bool
		 */
		private function is_stock_only_save( $product_id ) {
			if ( ! isset( $this->save_changes[ $product_id ] ) ) {
				return true;
			}

			return array() === array_diff( $this->save_changes[ $product_id ], array( 'stock_quantity', 'stock_status' ) );
		}

		/**
		 * A stock quantity change. Listings show stock status, not quantity, so at most
		 * the product's own page changes; see handle_quantity_change().
		 *
		 * @param WC_Product $product Product or variation.
		 */
		public function handle_stock_quantity( $product ) {
			try {
				if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
					$this->handle_quantity_change( $product );

					// The change ends once its save has been seen too (it may still be to come).
					$product_id = (int) $product->get_id();
					if ( isset( $this->stock_changes[ $product_id ] ) ) {
						if ( $this->stock_changes[ $product_id ]['saved'] ) {
							unset( $this->stock_changes[ $product_id ] );
						} else {
							$this->stock_changes[ $product_id ]['done'] = true;
						}
					}
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_stock_quantity', $th );
			}
		}

		/**
		 * Shutdown, priority 11: WooCommerce has re-saved the parents of the variations
		 * sold in this request (priority 10), so their marks are no longer needed.
		 */
		public function clear_stock_sync_parents() {
			$this->stock_sync_parents = array();
		}

		/**
		 * Decide what a stock quantity change needs from the store's "Stock display
		 * format" (WooCommerce > Settings > Products > Inventory), which is what a
		 * shopper actually sees on the product page:
		 *
		 * - never show the quantity: nothing to purge;
		 * - only show it when low: purge now if it is, or was before this change, at or
		 *   below the product's low-stock threshold (WooCommerce's own, per product or
		 *   store-wide), so a restock also clears an "Only N left";
		 * - always show it (WooCommerce's default): add the product to the daily list.
		 *
		 * A stock status change (in/out of stock) is handled by handle_stock_status()
		 * and always purges the product and its listings at once.
		 *
		 * @param WC_Product $product Product or variation.
		 */
		private function handle_quantity_change( $product ) {
			if ( self::is_switched_blog() ) {
				return;
			}

			$format = get_option( 'woocommerce_stock_format', '' );

			if ( 'no_amount' === $format ) {
				$action = 'none';
			} elseif ( 'low_amount' === $format ) {
				$quantity  = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null;
				$threshold = function_exists( 'wc_get_low_stock_amount' ) ? wc_get_low_stock_amount( $product ) : (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
				$before    = isset( $this->stock_changes[ (int) $product->get_id() ] ) ? $this->stock_changes[ (int) $product->get_id() ]['before'] : null;
				$low_now   = null !== $quantity && (int) $quantity <= (int) $threshold;
				$low_then  = null !== $before && (int) $before <= (int) $threshold;
				$action    = ( $low_now || $low_then ) ? 'now' : 'none';
			} else {
				$action = 'daily';
			}

			/**
			 * Filter what a stock quantity change purges: 'none', 'now' (the product's
			 * own page, at once) or 'daily' (with the daily batch). For a theme that
			 * shows the quantity where WooCommerce's setting would not.
			 *
			 * @param string     $action  'none', 'now' or 'daily'.
			 * @param WC_Product $product Product or variation.
			 */
			$action = apply_filters( 'instawp_cdn_purge_stock_quantity', $action, $product );

			if ( 'now' === $action ) {
				$this->queue_product( $product->get_id(), false );
			} elseif ( 'daily' === $action && $this->can_purge() ) {
				self::add_to_daily_stock( $product->get_id() );
			}
		}

		/**
		 * Remember a product for the daily stock-quantity purge, and make sure the daily
		 * run is scheduled.
		 *
		 * @param int $product_id Product or variation ID (a variation counts as its parent).
		 */
		private static function add_to_daily_stock( $product_id ) {
			$product_id = absint( $product_id );
			if ( 'product_variation' === get_post_type( $product_id ) ) {
				$product_id = wp_get_post_parent_id( $product_id );
			}

			if ( empty( $product_id ) ) {
				return;
			}

			// This request's copy first: a product sold again is usually listed already, and
			// that costs no query. Only when adding is the list read from the database, so
			// a product another checkout added since this request started is kept.
			$list = get_option( self::STOCK_DAILY_OPTION, array() );
			$list = is_array( $list ) ? $list : array();

			// Past MAX_URLS products the daily run is one full purge, so more IDs add nothing.
			if ( ! isset( $list[ $product_id ] ) && count( $list ) <= self::MAX_URLS ) {
				$raw  = self::read_option_fresh( self::STOCK_DAILY_OPTION );
				$list = null === $raw ? array() : maybe_unserialize( $raw );
				$list = is_array( $list ) ? $list : array();

				if ( ! isset( $list[ $product_id ] ) && count( $list ) <= self::MAX_URLS ) {
					$list[ $product_id ] = true;
					update_option( self::STOCK_DAILY_OPTION, $list, true );
				}
			}

			if ( ! wp_next_scheduled( self::STOCK_DAILY_HOOK ) ) {
				wp_schedule_single_event( time() + DAY_IN_SECONDS, self::STOCK_DAILY_HOOK );
			}
		}

		/**
		 * The daily run: purge the page of every product whose quantity changed since the
		 * last one. Queued like any other change, so it is sent at the end of this cron
		 * request, becomes a full purge past MAX_URLS, and goes on the pending list if
		 * it fails.
		 */
		public function handle_daily_stock_purge() {
			try {
				// From the database: a checkout may have added a product since this cron request
				// started (earlier events in the same run can take a while).
				$raw  = self::read_option_fresh( self::STOCK_DAILY_OPTION );
				$list = null === $raw ? array() : maybe_unserialize( $raw );
				update_option( self::STOCK_DAILY_OPTION, array(), true );

				if ( ! is_array( $list ) || empty( $list ) || ! $this->can_purge() ) {
					return;
				}

				// One product that throws (a broken product, a filter elsewhere) must not cost
				// the others their purge: the list is already emptied, so they would be lost.
				foreach ( array_keys( $list ) as $product_id ) {
					try {
						$this->queue_product( $product_id, false );
					} catch ( \Throwable $th ) {
						self::log_throwable( 'handle_daily_stock_purge (product ' . $product_id . ')', $th );
					}
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_daily_stock_purge', $th );
			}
		}

		/**
		 * A stock status change (e.g. the last item sold): listings change too.
		 *
		 * @param int $product_id Product or variation ID.
		 */
		public function handle_stock_status( $product_id ) {
			try {
				$this->queue_product( $product_id, true );
			} catch ( \Throwable $th ) {
				self::log_throwable( 'handle_stock_status', $th );
			}
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
		 * Request a full CDN purge at the end of the current request (a theme switch).
		 *
		 * Coalesced per request, so several requests for it produce one purge.
		 */
		public function queue_full_purge() {
			try {
				if ( self::is_switched_blog() || ! $this->can_purge() ) {
					return;
				}

				$this->full_purge_pending = true;
			} catch ( \Throwable $th ) {
				self::log_throwable( 'queue_full_purge', $th );
			}
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
				self::log_throwable( 'flush_pending_purges', $th );
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

			if ( ! wp_doing_cron() && ! $this->can_finish_request() ) {
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

			// Only the request that claimed it finishes its response early.
			if ( ! wp_doing_cron() ) {
				$this->finish_request();
			}

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
				self::log_throwable( 'handle_deferred_purge', $th );
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

			if ( ! $this->can_finish_request() ) {
				return false;
			}

			// false only means another shutdown callback (e.g. WooCommerce's deferred
			// emails) already finished the response: nobody is waiting either way.
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} else {
				litespeed_finish_request();
			}

			return true;
		}

		/**
		 * Whether the response can be finished early in this request (PHP-FPM or
		 * LiteSpeed). On PHP-FPM, only from PHP 7.0.16, as WordPress's own wp-cron.php
		 * requires.
		 *
		 * @return bool
		 */
		private function can_finish_request() {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				return version_compare( PHP_VERSION, '7.0.16', '>=' );
			}

			return function_exists( 'litespeed_finish_request' );
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
			if ( ! $post instanceof WP_Post || wp_is_post_revision( $post->ID ) || self::is_switched_blog() ) {
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

			// An Action Scheduler queue run (scheduled sales, imports) can be started from a
			// shopper's admin-ajax request and carries their cookies; it is not a shopper.
			if ( did_action( 'action_scheduler_before_process_queue' ) ) {
				return false;
			}

			// instawp_is_admin() loads the capability functions if needed and returns false
			// instead of a fatal when they are missing. false: no admin-area / login check,
			// so an editor's REST or AJAX request still counts as an editor.
			return ! instawp_is_admin( 'edit_posts', false );
		}

		/**
		 * Whether the request is switched to another site of a multisite network. The
		 * queue is sent at shutdown for the site that loaded the request, so a change made
		 * in another site (switch_to_blog()) would purge the wrong site's pages.
		 *
		 * @return bool
		 */
		private static function is_switched_blog() {
			return function_exists( 'ms_is_switched' ) && ms_is_switched();
		}

		/**
		 * Log an error raised inside this class and swallow it. Every method WordPress
		 * calls directly (hook callbacks, the constructor, the entry points used by the
		 * manual purge, heartbeat, restore and the REST route) catches through here, so
		 * a bug in the CDN purge can never break a post save, a checkout, an update or
		 * the request that loaded the plugin.
		 *
		 * @param string     $where Method that caught it.
		 * @param \Throwable $th    What was thrown.
		 */
		private static function log_throwable( $where, $th ) {
			Helper::add_error_log(
				array(
					'message' => 'CDN cache purge: ' . $where . ' threw',
					'error'   => $th->getMessage(),
				),
				$th
			);
		}

		/**
		 * Purge an array of URLs via the client-app API.
		 *
		 * @param array $urls URLs to purge.
		 *
		 * @return array|false API response or false on failure.
		 */
		public static function purge_urls( array $urls ) {
			try {
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
			} catch ( \Throwable $th ) {
				self::log_throwable( 'purge_urls', $th );

				return false;
			}
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
			try {
				self::save_cdn_flags_from_response( $response );

				if ( is_array( $response ) && ! empty( $response['success'] ) ) {
					self::clear_covered( $full, $urls, $sent_at );
				}
			} catch ( \Throwable $th ) {
				self::log_throwable( 'after_purge', $th );
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
		 * Save cdn_enabled / is_insta_site into instawp_api_options, sanitized with
		 * Helper::sanitize_boolean() (as connect-helpers does for the connect response).
		 * The option is written only when a value changes. A site whose CDN turns off
		 * also loses its pending list and daily stock list.
		 *
		 * @param array $flags Values keyed by flag name.
		 *
		 * @return bool True when the stored flags now match (written, or already so);
		 *              false when nothing could be written (no usable row, or an error).
		 */
		public static function save_cdn_flags( array $flags ) {
			try {
				$sanitized = array();
				foreach ( array( 'cdn_enabled', 'is_insta_site' ) as $key ) {
					if ( isset( $flags[ $key ] ) ) {
						$sanitized[ $key ] = Helper::sanitize_boolean( $flags[ $key ] );
					}
				}

				$api_options = Helper::get_options();
				$api_options = is_array( $api_options ) ? $api_options : array();
				$changed     = false;

				foreach ( $sanitized as $key => $value ) {
					if ( ! isset( $api_options[ $key ] ) || $api_options[ $key ] !== $value ) {
						$changed = true;
					}
				}

				if ( ! $changed ) {
					return true;
				}

				// This request's copy may be from before a long purge call; write on a fresh one so
				// a key or token saved meanwhile is kept. No usable row means the read failed or the
				// site was disconnected meanwhile: write nothing then. Writing an empty array would
				// drop the API key, and writing this request's copy could bring back a deleted one.
				// The flags arrive again with the next heartbeat or purge response.
				$fresh = maybe_unserialize( self::read_option_fresh( 'instawp_api_options' ) );
				if ( ! is_array( $fresh ) || empty( $fresh['api_key'] ) ) {
					return false;
				}
				$wanted = $fresh;
				foreach ( $sanitized as $key => $value ) {
					$wanted[ $key ] = $value;
				}

				// Already stored (another request saved them meanwhile): nothing to write.
				// Otherwise update_option() returning false means the write failed.
				if ( $wanted !== $fresh && false === Helper::set_settings( $wanted ) ) {
					return false;
				}

				if ( isset( $sanitized['cdn_enabled'] ) && false === $sanitized['cdn_enabled'] ) {
					self::save_pending( self::empty_pending() );
					if ( false !== get_option( self::STOCK_DAILY_OPTION, false ) ) {
						update_option( self::STOCK_DAILY_OPTION, array(), true );
					}
					wp_clear_scheduled_hook( self::STOCK_DAILY_HOOK );
				}

				return true;
			} catch ( \Throwable $th ) {
				self::log_throwable( 'save_cdn_flags', $th );

				return false;
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
		 * Deliberately skips the option filters (pre_option_*, option_*): it is used only
		 * for this class's own pending list and for instawp_api_options right before a
		 * write, where a stale or filtered copy is the very thing to avoid. It runs only
		 * on those write paths, never on an ordinary request.
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
			// The cached copy first: nearly every purge finds the list empty, and this
			// way it costs no query. Only a non-empty list is re-read from the database.
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

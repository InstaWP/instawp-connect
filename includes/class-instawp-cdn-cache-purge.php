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
		 * Most URLs the client-app purge-cache-urls endpoint accepts in one call.
		 * A larger set is sent as one full purge instead.
		 */
		const MAX_URLS = 50;

		/**
		 * WP-Cron hook that sends a purge deferred out of a visitor's request.
		 */
		const DEFERRED_HOOK = 'instawp_cdn_purge_deferred';

		/**
		 * Posts changed during the current request, keyed by post ID.
		 *
		 * @var array
		 */
		private $pending_posts = array();

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
			add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 20, 3 );
			add_action( 'post_updated', array( $this, 'handle_post_updated' ), 20, 3 );
			add_action( 'set_object_terms', array( $this, 'handle_object_terms' ), 20, 6 );
			add_action( 'before_delete_post', array( $this, 'handle_post_delete' ), 20, 2 );

			// WooCommerce writes that do not go through a post status transition:
			// stock changes from orders, REST/CSV imports, scheduled sales, quick edit.
			add_action( 'woocommerce_update_product', array( $this, 'handle_product_id' ), 20 );
			add_action( 'woocommerce_update_product_variation', array( $this, 'handle_product_id' ), 20 );
			add_action( 'woocommerce_product_set_stock', array( $this, 'handle_product_object' ), 20 );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_product_object' ), 20 );
			add_action( 'woocommerce_product_set_stock_status', array( $this, 'handle_product_id' ), 20 );
			add_action( 'woocommerce_variation_set_stock_status', array( $this, 'handle_product_id' ), 20 );

			add_action( 'upgrader_process_complete', array( $this, 'handle_upgrade_complete' ), 20, 2 );
			add_action( 'automatic_updates_complete', array( $this, 'handle_automatic_updates_complete' ), 20 );
			add_action( 'switch_theme', array( $this, 'queue_full_purge' ), 20 );
			// Replacing a plugin/theme by zip upload completes as action=install, not update.
			add_action( 'upgrader_overwrote_package', array( $this, 'queue_full_purge' ), 20 );

			add_action( self::DEFERRED_HOOK, array( $this, 'handle_deferred_purge' ) );
			add_action( 'shutdown', array( $this, 'flush_pending_purges' ) );
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

			$this->pending_posts[ $post->ID ] = true;
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
			if ( ! $post || 'publish' !== $post->post_status || ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			$this->pending_posts[ $post->ID ] = true;

			foreach ( $removed as $tt_id ) {
				$term = get_term_by( 'term_taxonomy_id', $tt_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					foreach ( $this->get_term_urls( $term ) as $url ) {
						$this->add_url( $url );
					}
				}
			}
		}

		/**
		 * Queue a WooCommerce product (or a variation's parent) changed outside a
		 * post status transition.
		 *
		 * @param int $product_id Product or variation ID.
		 */
		public function handle_product_id( $product_id ) {
			$product_id = absint( $product_id );
			if ( empty( $product_id ) ) {
				return;
			}

			$parent_id = wp_get_post_parent_id( $product_id );
			if ( $parent_id && 'product_variation' === get_post_type( $product_id ) ) {
				$product_id = $parent_id;
			}

			$post = get_post( $product_id );
			if ( ! $post || 'publish' !== $post->post_status || ! $this->is_purgeable_post( $post ) ) {
				return;
			}

			$this->pending_posts[ $post->ID ] = true;
		}

		/**
		 * Queue a product from a hook that passes a WC_Product object.
		 *
		 * @param WC_Product $product Product or variation.
		 */
		public function handle_product_object( $product ) {
			if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
				$this->handle_product_id( $product->get_id() );
			}
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
		 * A request from a site visitor (e.g. a checkout reducing stock) hands the
		 * purge to WP-Cron instead, so the shopper never waits for the purge call.
		 */
		public function flush_pending_purges() {
			$full = $this->full_purge_pending;
			$urls = $full ? array() : $this->collect_urls();

			$this->full_purge_pending = false;
			$this->pending_posts      = array();
			$this->pending_urls       = array();

			if ( ! $full && empty( $urls ) ) {
				return;
			}

			if ( count( $urls ) > self::MAX_URLS ) {
				$full = true;
				$urls = array();
			}

			if ( $this->should_defer() ) {
				$payload = array(
					'full' => $full,
					'urls' => $urls,
				);
				wp_schedule_single_event( time(), self::DEFERRED_HOOK, array( $payload ) );

				return;
			}

			$this->send( $full, $urls );
		}

		/**
		 * Send a purge deferred out of a visitor's request (WP-Cron callback).
		 *
		 * @param array $payload Array with 'full' (bool) and 'urls' (string[]).
		 */
		public function handle_deferred_purge( $payload ) {
			if ( ! is_array( $payload ) || ! $this->can_purge() ) {
				return;
			}

			$full = ! empty( $payload['full'] );
			$urls = ! empty( $payload['urls'] ) && is_array( $payload['urls'] ) ? array_values( $payload['urls'] ) : array();

			if ( ! $full && empty( $urls ) ) {
				return;
			}

			$this->send( $full, $urls );
		}

		/**
		 * Send a full purge or a URL purge.
		 *
		 * @param bool  $full Whether to purge the whole CDN cache.
		 * @param array $urls URLs to purge when not a full purge.
		 */
		private function send( $full, array $urls ) {
			if ( $full ) {
				$this->flush_full_purge();

				return;
			}

			$response = self::purge_urls( $urls );

			// Retry once on failure.
			if ( ! $response || empty( $response['success'] ) ) {
				$response = self::purge_urls( $urls );

				if ( ! $response || empty( $response['success'] ) ) {
					Helper::add_error_log(
						array(
							'message' => 'CDN cache purge failed after retry',
							'urls'    => $urls,
						)
					);
				}
			}
		}

		/**
		 * Collect every URL to purge for the posts changed in this request.
		 *
		 * @return array Unique URLs.
		 */
		private function collect_urls() {
			if ( empty( $this->pending_posts ) && empty( $this->pending_urls ) ) {
				return array();
			}

			$urls = $this->pending_urls;

			foreach ( array_keys( $this->pending_posts ) as $post_id ) {
				foreach ( $this->get_post_urls( $post_id ) as $url ) {
					$urls[ $url ] = true;
				}
			}

			/**
			 * Filter the URLs purged from the CDN after content changes.
			 *
			 * Use it to add pages this class cannot derive, e.g. a menu link to a
			 * category under a non-canonical path. More than 50 URLs become one
			 * full purge.
			 *
			 * @param string[] $urls     URLs to purge.
			 * @param int[]    $post_ids IDs of the posts changed in this request.
			 */
			$urls = apply_filters( 'instawp_cdn_purge_urls', array_keys( $urls ), array_keys( $this->pending_posts ) );

			return array_values( array_unique( array_filter( (array) $urls, 'is_string' ) ) );
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

			foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
				if ( ! $this->is_listed_taxonomy( $taxonomy ) ) {
					continue;
				}

				$terms = get_the_terms( $post, $taxonomy );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					$listings = array_merge( $listings, $this->get_term_urls( $term, false ) );
				}
			}

			foreach ( $listings as $listing ) {
				$urls = array_merge( $urls, $this->with_pagination( $listing ) );
			}

			return array_filter( $urls, array( $this, 'is_valid_url' ) );
		}

		/**
		 * Archive URLs of a term and its ancestors, with pagination by default.
		 *
		 * @param WP_Term $term     Term.
		 * @param bool    $paginate Whether to add the paginated URLs.
		 *
		 * @return array URLs.
		 */
		private function get_term_urls( $term, $paginate = true ) {
			$term_ids = array_merge( array( (int) $term->term_id ), get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
			$urls     = array();

			foreach ( $term_ids as $term_id ) {
				$link = get_term_link( (int) $term_id, $term->taxonomy );
				if ( ! $this->is_valid_url( $link ) ) {
					continue;
				}

				$urls = array_merge( $urls, $paginate ? $this->with_pagination( $link ) : array( $link ) );
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

			if ( $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks() && false === strpos( $url, '?' ) ) {
				return user_trailingslashit( trailingslashit( $url ) . $wp_rewrite->pagination_base . '/' . $page, 'paged' );
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
		 * Whether the purge should leave the current request. True for site visitors
		 * (checkout, payment callbacks); editors, WP-CLI and cron purge right away.
		 *
		 * @return bool
		 */
		private function should_defer() {
			if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
				return false;
			}

			return ! current_user_can( 'edit_posts' );
		}

		/**
		 * Purge the whole CDN cache. Runs on shutdown, so a failure is logged and
		 * swallowed: it must never break the request that triggered it.
		 */
		private function flush_full_purge() {
			try {
				$response = instawp_purge_cdn_cache();

				if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
					Helper::add_error_log(
						array(
							'message'  => 'CDN full cache purge failed',
							'response' => is_wp_error( $response ) ? $response->get_error_message() : $response,
						)
					);
				}
			} catch ( \Throwable $th ) {
				Helper::add_error_log(
					array(
						'message' => 'CDN full cache purge threw',
						'error'   => $th->getMessage(),
					)
				);
			}
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

			return Curl::do_curl( "connects/{$connect_id}/purge-cache-urls", array( 'urls' => $urls ) );
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
		 * Whether auto-purge is on and the site is connected.
		 *
		 * @return bool
		 */
		private function can_purge() {
			return $this->is_auto_purge_enabled() && ! empty( Helper::get_connect_id() );
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

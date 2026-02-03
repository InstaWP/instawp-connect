<?php
/**
 * CDN Cache Purge - Auto-purge CDN cache when posts are published, updated, or trashed.
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'InstaWP_CDN_Cache_Purge' ) ) {

	class InstaWP_CDN_Cache_Purge {

		/**
		 * URLs collected during the current request.
		 *
		 * @var array
		 */
		private $pending_urls = array();

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_action( 'transition_post_status', array( $this, 'handle_post_transition' ), 20, 3 );
			add_action( 'shutdown', array( $this, 'flush_pending_purges' ) );
		}

		/**
		 * Handle post status transitions.
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 */
		public function handle_post_transition( $new_status, $old_status, $post ) {
			error_log( '[CDN-PURGE] handle_post_transition called: post_id=' . $post->ID . ', old_status=' . $old_status . ', new_status=' . $new_status );

			if ( ! $this->is_auto_purge_enabled() ) {
				error_log( '[CDN-PURGE] Auto-purge is DISABLED, skipping' );
				return;
			}

			$connect_id = Helper::get_connect_id();
			if ( empty( $connect_id ) ) {
				error_log( '[CDN-PURGE] No connect_id found, skipping' );
				return;
			}

			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object || ! $post_type_object->public ) {
				error_log( '[CDN-PURGE] Post type not public: ' . $post->post_type );
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				error_log( '[CDN-PURGE] Autosave detected, skipping' );
				return;
			}

			if ( wp_is_post_revision( $post->ID ) ) {
				error_log( '[CDN-PURGE] Post is revision, skipping' );
				return;
			}

			$should_purge = false;
			$url          = '';

			// Publish: new post published.
			if ( 'publish' === $new_status && 'publish' !== $old_status ) {
				$should_purge = true;
				$url          = get_permalink( $post->ID );
			}

			// Update: already published post updated.
			if ( 'publish' === $new_status && 'publish' === $old_status ) {
				$should_purge = true;
				$url          = get_permalink( $post->ID );
			}

			// Trash: published post trashed.
			if ( 'trash' === $new_status && 'publish' === $old_status ) {
				$should_purge = true;
				// get_permalink may not work after trash, use stored URL.
				$url = get_permalink( $post->ID );
				if ( empty( $url ) || is_wp_error( $url ) ) {
					$url = home_url( '/?p=' . $post->ID );
				}
			}

			if ( ! $should_purge || empty( $url ) ) {
				error_log( '[CDN-PURGE] should_purge=' . ( $should_purge ? 'true' : 'false' ) . ', url=' . $url . ' - skipping' );
				return;
			}

			// Debounce: skip if recently purged.
			$transient_key = 'iwp_cdn_purge_' . md5( $url );
			if ( get_transient( $transient_key ) ) {
				error_log( '[CDN-PURGE] Debounce transient exists for URL, skipping: ' . $url );
				return;
			}

			$this->pending_urls[] = $url;
			error_log( '[CDN-PURGE] URL added to pending: ' . $url );
		}

		/**
		 * Flush pending purge URLs on shutdown.
		 */
		public function flush_pending_purges() {
			error_log( '[CDN-PURGE] flush_pending_purges called, pending_urls count: ' . count( $this->pending_urls ) );

			if ( empty( $this->pending_urls ) ) {
				return;
			}

			$this->pending_urls = array_unique( $this->pending_urls );

			// Set transients before sending to prevent duplicate purges.
			foreach ( $this->pending_urls as $url ) {
				$transient_key = 'iwp_cdn_purge_' . md5( $url );
				set_transient( $transient_key, 1, 30 );
			}

			error_log( '[CDN-PURGE] Calling purge_urls API with URLs: ' . wp_json_encode( $this->pending_urls ) );
			$response = self::purge_urls( $this->pending_urls );
			error_log( '[CDN-PURGE] API response: ' . wp_json_encode( $response ) );

			// Retry once on failure.
			if ( ! $response || empty( $response['success'] ) || false === $response['success'] ) {
				error_log( '[CDN-PURGE] First attempt failed, retrying...' );
				$response = self::purge_urls( $this->pending_urls );
				error_log( '[CDN-PURGE] Retry response: ' . wp_json_encode( $response ) );

				if ( ! $response || empty( $response['success'] ) || false === $response['success'] ) {
					Helper::add_error_log(
						array(
							'message' => 'CDN cache purge failed after retry',
							'urls'    => $this->pending_urls,
						)
					);
				}
			}

			$this->pending_urls = array();
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
				error_log( '[CDN-PURGE] purge_urls: No connect_id' );
				return false;
			}

			error_log( '[CDN-PURGE] purge_urls: Calling API connects/' . $connect_id . '/purge-cache-urls' );
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

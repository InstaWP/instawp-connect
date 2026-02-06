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
			if ( ! $this->is_auto_purge_enabled() ) {
				return;
			}

			$connect_id = Helper::get_connect_id();
			if ( empty( $connect_id ) ) {
				return;
			}

			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object || ! $post_type_object->public ) {
				return;
			}

			if ( wp_is_post_revision( $post->ID ) ) {
				return;
			}

			$should_purge = false;
			$url          = '';

			if ( 'publish' === $new_status ) {
				$should_purge = true;
				$url          = get_permalink( $post->ID );
			} elseif ( 'trash' === $new_status && 'publish' === $old_status ) {
				$should_purge = true;
				$url          = get_permalink( $post->ID );
				if ( empty( $url ) || is_wp_error( $url ) ) {
					$url = home_url( '/?p=' . $post->ID );
				}
			}

			if ( ! $should_purge || empty( $url ) ) {
				return;
			}

			// Debounce: skip if recently purged.
			$purge_data = get_option( 'instawp_cdn_purge_queue', array() );
			if ( isset( $purge_data[ $post->ID ] ) && $purge_data[ $post->ID ]['expire'] > time() ) {
				return;
			}

			$this->pending_urls[ $post->ID ] = $url;
		}

		/**
		 * Flush pending purge URLs on shutdown.
		 */
		public function flush_pending_purges() {
			if ( empty( $this->pending_urls ) ) {
				return;
			}

			$urls = array_values( $this->pending_urls );

			// Update debounce data in wp_options.
			$purge_data = get_option( 'instawp_cdn_purge_queue', array() );

			// Clean expired entries.
			foreach ( $purge_data as $pid => $entry ) {
				if ( $entry['expire'] <= time() ) {
					unset( $purge_data[ $pid ] );
				}
			}

			// Add current batch.
			foreach ( $this->pending_urls as $post_id => $url ) {
				$purge_data[ $post_id ] = array( 'url' => $url, 'expire' => time() + 30 );
			}

			update_option( 'instawp_cdn_purge_queue', $purge_data, false );

			$response = self::purge_urls( $urls );

			// Retry once on failure.
			if ( ! $response || empty( $response['success'] ) || false === $response['success'] ) {
				$response = self::purge_urls( $urls );

				if ( ! $response || empty( $response['success'] ) || false === $response['success'] ) {
					Helper::add_error_log(
						array(
							'message' => 'CDN cache purge failed after retry',
							'urls'    => $urls,
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

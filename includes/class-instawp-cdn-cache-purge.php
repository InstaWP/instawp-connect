<?php
/**
 * CDN Cache Purge - Auto-purge CDN cache when posts are published, updated, or trashed,
 * and purge the whole CDN cache after plugin/theme/core updates and theme switches.
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
			add_action( 'upgrader_process_complete', array( $this, 'handle_upgrade_complete' ), 20, 2 );
			add_action( 'automatic_updates_complete', array( $this, 'handle_automatic_updates_complete' ), 20 );
			add_action( 'switch_theme', array( $this, 'queue_full_purge' ), 20 );
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

			// Skip if already pending in current request (fastest check).
			if ( isset( $this->pending_urls[ $post->ID ] ) ) {
				return;
			}

			// Debounce: skip if recently purged (database check).
			$purge_data = get_option( 'instawp_cdn_purge_queue', array() );
			if ( isset( $purge_data[ $post->ID ] ) && $purge_data[ $post->ID ]['expire'] > time() ) {
				return;
			}

			$this->pending_urls[ $post->ID ] = $url;
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
			if ( ! $this->is_auto_purge_enabled() ) {
				return;
			}

			$this->full_purge_pending = true;
		}

		/**
		 * Flush pending purge URLs on shutdown.
		 */
		public function flush_pending_purges() {
			if ( $this->full_purge_pending ) {
				// A full purge covers every pending URL.
				$this->full_purge_pending = false;
				$this->pending_urls       = array();
				$this->flush_full_purge();

				return;
			}

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
		 * Purge the whole CDN cache. Runs on shutdown after an update, so a failure
		 * is logged and swallowed: it must never break the update that triggered it.
		 */
		private function flush_full_purge() {
			try {
				$response = instawp_purge_cdn_cache();

				if ( is_wp_error( $response ) || empty( $response['success'] ) ) {
					Helper::add_error_log(
						array(
							'message'  => 'CDN full cache purge after update failed',
							'response' => is_wp_error( $response ) ? $response->get_error_message() : $response,
						)
					);
				}
			} catch ( \Throwable $th ) {
				Helper::add_error_log(
					array(
						'message' => 'CDN full cache purge after update threw',
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

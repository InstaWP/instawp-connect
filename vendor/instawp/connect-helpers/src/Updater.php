<?php

namespace InstaWP\Connect\Helpers;

class Updater {

	/**
	 * wp_options key that durably records the WordPress core version
	 * the site was on immediately BEFORE its most recent core upgrade
	 * via instawp-connect. The rollback path verifies its requested
	 * target against this value before installing anything, so the
	 * plugin owns the truth for "what is a valid rollback target?"
	 * regardless of what the caller claims. Shape:
	 *   [ 'version' => '6.7.2', 'next' => '6.9.4', 'updated_at' => 1715520000 ]
	 */
	const LAST_CORE_VERSION_OPTION = 'instawp_last_core_version';

	public $args;

	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	/**
	 * Read the snapshot of the WP core version the site was on just
	 * before its most recent instawp-connect-driven upgrade. Returns
	 * null when no upgrade has been recorded yet.
	 */
	private function get_last_core_version_snapshot() {
		$val = get_option( self::LAST_CORE_VERSION_OPTION, null );

		return is_array( $val ) ? $val : null;
	}

	/**
	 * Record the WP core version the site is on right now, plus the
	 * version we're about to install. autoload=false because this is
	 * only read during upgrade flows.
	 */
	private function set_last_core_version_snapshot( $current, $next ) {
		update_option(
			self::LAST_CORE_VERSION_OPTION,
			[
				'version'    => (string) $current,
				'next'       => (string) $next,
				'updated_at' => time(),
			],
			false
		);
	}

	public function update() {
		if ( count( $this->args ) < 1 || count( $this->args ) > 5 ) {
			return [
				'success' => false,
				'message' => esc_html( 'Minimum 1 and Maximum 5 updates are allowed!' ),
			];
		}

		$results = [];
		foreach ( $this->args as $update ) {
			if ( ! isset( $update['type'], $update['slug'] ) ) {
				$results[ $update['slug'] ] = [
					'success' => false,
					'message' => esc_html( 'Required parameters are missing!' ),
				];
				continue;
			}

			// Routing for type='core':
			//   - allow_downgrade flag (or action='rollback') →
			//     core_downgrade() which doctors the update_core
			//     transient and then DELEGATES to core_updater(). All
			//     install/orchestration logic stays in core_updater() —
			//     strictly one path through Core_Upgrader.
			//   - Anything else → core_updater() directly (forward).
			if ( 'core' === $update['type'] ) {
				$is_rollback = ! empty( $update['allow_downgrade'] )
					|| ( isset( $update['action'] ) && 'rollback' === $update['action'] );

				$results[ $update['slug'] ] = $is_rollback
					? $this->core_downgrade( $update )
					: $this->core_updater( $update );
			} else {
				$results[ $update['slug'] ] = $this->updater( $update['type'], $update['slug'] );
			}
		}

		return $results;
	}

	private function core_updater( array $args = [] ) {
		$args = wp_parse_args( $args, [
			'locale'  => get_locale(),
			'version' => get_bloginfo( 'version' )
		] );

		if ( ! function_exists( 'find_core_update' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'show_message' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		// Refresh WordPress's cached core-update offer before resolving
		// the target. The `update_core` site transient can be stale —
		// most importantly right after a rollback: that rollback request
		// rebuilt the transient from a stale wp_get_wp_version() (a
		// per-request static the Core_Upgrader cannot change in-process),
		// so it still advertises the previous version with
		// response='latest'. find_core_update() would then return that
		// offer and Core_Upgrader rejects the upgrade as "WordPress is
		// at the latest version.". This call runs in a fresh request, so
		// wp_get_wp_version() now reports the real current version —
		// deleting the transient and forcing wp_version_check() rebuilds
		// a correct offer, so the update succeeds on the first attempt.
		// Skipped for the downgrade path, which installs its own
		// doctored offer via the pre_site_transient_update_core filter.
		if ( empty( $args['skip_core_check'] ) ) {
			delete_site_transient( 'update_core' );
			wp_version_check( [], true );
		}

		$update = find_core_update( $args['version'], $args['locale'] );
		if ( ! $update ) {
			return [
				'message' => esc_html( 'Update not found!' ),
				'success' => false,
			];
		}

		/*
		 * Allow relaxed file ownership writes for User-initiated upgrades when the API specifies
		 * that it's safe to do so. This only happens when there are no new files to create.
		*/
		$allow_relaxed_file_ownership = isset( $update->new_files ) && ! $update->new_files;

		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( 'Core_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-core-upgrader.php';
		}

		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}

		// Capture version before upgrade for verification
		$old_version = get_bloginfo( 'version' );

		// Snapshot the pre-upgrade version into wp_options so a future
		// rollback request has a verified target. Written here (before
		// Core_Upgrader runs) so it survives even if the upgrader
		// crashes mid-way — the site is in a known prior state.
		$this->set_last_core_version_snapshot( $old_version, $args['version'] );

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Core_Upgrader( $skin );
		$result   = $upgrader->upgrade( $update, [
			'allow_relaxed_file_ownership' => $allow_relaxed_file_ownership,
		] );

		delete_site_transient( 'update_core' );
		wp_version_check( [], true );

		if ( is_wp_error( $result ) ) {
			if ( $result->get_error_data() && is_string( $result->get_error_data() ) ) {
				$error_message = $result->get_error_message() . ': ' . $result->get_error_data();
			} else {
				$error_message = $result->get_error_message();
			}

			if ( 'up_to_date' !== $result->get_error_code() && 'locked' !== $result->get_error_code() ) {
				$error_message = __( 'Installation failed.' );
			}
		}

		$message = isset( $error_message ) ? trim( $error_message ) : '';
		$success = empty( $message );

		// new_version reported back is the version we asked
		// Core_Upgrader to install — accurate on success. On failure we
		// report old_version because nothing was installed. We do NOT
		// compare get_bloginfo() before vs after the upgrade because
		// $wp_version is a cached PHP global that Core_Upgrader does
		// not refresh in-process. WP itself trusts Core_Upgrader's
		// return value (is_wp_error check above) and so do we.
		$new_version = $success ? (string) $args['version'] : $old_version;

		return [
			'message'     => $success ? esc_html( 'Success!' ) : $message,
			'success'     => $success,
			'old_version' => $old_version,
			'new_version' => $new_version,
		];
	}

	private function core_downgrade( array $args = [] ) {
		$args = wp_parse_args( $args, [
			'locale'  => get_locale(),
			'version' => '',
		] );

		// 1. Verify against the plugin's own snapshot.
		$snapshot = $this->get_last_core_version_snapshot();
		if ( empty( $snapshot ) || empty( $snapshot['version'] ) ) {
			Helper::add_error_log( 'core_downgrade: rejected — no snapshot recorded' );

			return [
				'message' => esc_html( 'No previous core version recorded' ),
				'success' => false,
			];
		}

		if ( empty( $args['version'] ) ) {
			$args['version'] = $snapshot['version'];
		} elseif ( (string) $args['version'] !== (string) $snapshot['version'] ) {
			Helper::add_error_log( sprintf(
				'core_downgrade: rejected — stored %s, requested %s',
				$snapshot['version'],
				$args['version']
			) );

			return [
				'message' => sprintf(
					/* translators: 1: stored version, 2: requested version */
					esc_html__( 'Rollback target mismatch (stored: %1$s, requested: %2$s)', 'instawp-connect' ),
					$snapshot['version'],
					$args['version']
				),
				'success' => false,
			];
		}

		$target_version = $args['version'];

		// 2. Reject malformed version strings before going to the network.
		if ( ! preg_match( '/^\d+(\.\d+){1,2}([\-\.][A-Za-z0-9]+)?$/', (string) $target_version ) ) {
			Helper::add_error_log( sprintf(
				'core_downgrade: rejected malformed version "%s"', $target_version
			) );

			return [
				'message' => esc_html( 'Update not found!' ),
				'success' => false,
			];
		}

		// 3. Canonical WP.org package URL. en_US / empty locale → no
		//    prefix; other locales → "<locale>/" before the filename.
		$locale_prefix = ( 'en_US' === $args['locale'] || empty( $args['locale'] ) )
			? ''
			: $args['locale'] . '/';
		$package_url = sprintf(
			'https://downloads.wordpress.org/release/%swordpress-%s.zip',
			$locale_prefix,
			rawurlencode( $target_version )
		);

		// 4. Pre-flight HEAD — accept 200/301/302. Catches 404s before
		//    invoking Core_Upgrader so failures surface cleanly.
		$head = wp_remote_head( $package_url, [ 'timeout' => 15, 'redirection' => 5 ] );
		if ( is_wp_error( $head )
			|| ! in_array( (int) wp_remote_retrieve_response_code( $head ), [ 200, 301, 302 ], true )
		) {
			Helper::add_error_log( sprintf(
				'core_downgrade: package %s unavailable (code: %s)',
				$package_url,
				is_wp_error( $head ) ? $head->get_error_message() : wp_remote_retrieve_response_code( $head )
			) );

			return [
				'message' => esc_html( 'Update not found!' ),
				'success' => false,
			];
		}

		// 5. Filter callback that rewrites the update_core transient.
		$rewrite_offer = function ( $updates ) use ( $target_version, $package_url ) {
			if ( ! is_object( $updates ) || empty( $updates->updates ) ) {
				$updates                  = new \stdClass();
				$updates->last_checked    = time();
				$updates->version_checked = get_bloginfo( 'version' );
				$updates->updates         = [ new \stdClass() ];
				$updates->updates[0]->response        = 'upgrade';
				$updates->updates[0]->locale          = get_locale();
				$updates->updates[0]->php_version     = '5.6.20';
				$updates->updates[0]->mysql_version   = '5.0';
				$updates->updates[0]->new_files       = true;
				$updates->updates[0]->partial_version = '';
				$updates->updates[0]->packages        = new \stdClass();
			}

			$updates->updates[0]->current               = $target_version;
			$updates->updates[0]->version               = $target_version;
			$updates->updates[0]->download              = $package_url;
			$updates->updates[0]->new_files             = true;
			$updates->updates[0]->packages->full        = $package_url;
			$updates->updates[0]->packages->no_content  = '';
			$updates->updates[0]->packages->new_bundled = '';
			$updates->updates[0]->packages->partial     = '';
			$updates->updates[0]->packages->rollback    = '';

			return $updates;
		};

		// 6. Hook filters before the delegated call.
		add_filter( 'pre_site_transient_update_core', $rewrite_offer, 10, 1 );
		add_filter( 'site_transient_update_core', $rewrite_offer, 10, 1 );
		delete_site_transient( 'update_core' );

		// 7. Delegate to core_updater() — find_core_update() now sees
		//    the rewritten offer and Core_Upgrader runs with no
		//    special casing.
		$result = $this->core_updater( [
			'version'         => $target_version,
			'locale'          => $args['locale'],
			// Downgrade already deletes the transient and installs its
			// own rewritten offer via filters — a forced wp_version_check
			// in core_updater() would just be a wasted remote call.
			'skip_core_check' => true,
		] );

		// 8. Always tear down our filters.
		remove_filter( 'pre_site_transient_update_core', $rewrite_offer, 10 );
		remove_filter( 'site_transient_update_core', $rewrite_offer, 10 );

		return $result;
	}

	private function updater( $type, $item ) {
		if ( ! class_exists( 'WP_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		}

		if ( ! class_exists( 'Plugin_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
		}

		if ( ! class_exists( 'Theme_Upgrader' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
		}

		if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
		}

		if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
		}

		if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
		}

		if ( ! function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		add_filter( 'automatic_updater_disabled', '__return_false', 201 );
		add_filter( "auto_update_{$type}", '__return_true', 201 );

		$skin        = new \Automatic_Upgrader_Skin();
		$result      = false;
		$old_version = '';
		$new_version = '';

		if ( 'plugin' === $type ) {
			// Capture current version before upgrade
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $item );
			$old_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '';

			wp_update_plugins();

			$upgrader = new \Plugin_Upgrader( $skin );

			$upgrader->init();
			$upgrader->upgrade_strings();

			$current = get_site_transient( 'update_plugins' );

			if ( isset( $current->response[ $item ] ) ) {
				$r               = $current->response[ $item ];
				$self_update_res = $upgrader->run(
					array(
						'package'           => $r->package,
						'destination'       => WP_PLUGIN_DIR,
						'clear_destination' => true,
						'clear_working'     => true,
						'hook_extra'        => array(
							'plugin'      => $item,
							'type'        => 'plugin',
							'action'      => 'update',
							'temp_backup' => array(
								'slug' => dirname( $item ),
								'src'  => WP_PLUGIN_DIR,
								'dir'  => 'plugins',
							),
						),
					)
				);

				if ( is_wp_error( $self_update_res ) ) {
					$result = $self_update_res;
				} else {
					$result = true;
				}
			} else {
				$result = null;
			}

			if ( ! function_exists( 'activate_plugin' ) || ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$is_plugin_active = is_plugin_active( $item );

			if ( $is_plugin_active ) {
				activate_plugin( $item, '', false, true );
			}

			wp_clean_plugins_cache();

			// Re-read version after upgrade to verify it actually changed
			// Note: get_plugin_data() reads directly from disk, no transient needed
			$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $item );
			$new_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '';
		} elseif ( 'theme' === $type ) {
			// Capture current version before upgrade
			$theme_obj   = wp_get_theme( $item );
			$old_version = $theme_obj->get( 'Version' );

			wp_update_themes();

			$upgrader = new \Theme_Upgrader( $skin );
			$result   = $upgrader->upgrade( $item );

			wp_clean_themes_cache();

			// Re-read version after upgrade to verify it actually changed
			// Note: wp_get_theme() reads directly from disk, no transient needed
			$theme_obj   = wp_get_theme( $item );
			$new_version = $theme_obj->get( 'Version' );
		}

		remove_filter( 'automatic_updater_disabled', '__return_false', 201 );
		remove_filter( "auto_update_{$type}", '__return_true', 201 );

		if ( is_wp_error( $result ) ) {
			if ( $result->get_error_data() && is_string( $result->get_error_data() ) ) {
				$error_message = $result->get_error_message() . ': ' . $result->get_error_data();
			} else {
				$error_message = $result->get_error_message();
			}

			$message = isset( $error_message ) ? trim( $error_message ) : '';

			return [
				'message'     => empty( $message ) ? esc_html( 'Success!' ) : $message,
				'success'     => empty( $message ),
				'old_version' => $old_version,
				'new_version' => $new_version,
			];
		} elseif ( $result === null ) {
			// No update in WordPress transient — plugin/theme is already at latest version
			return [
				'message'     => esc_html( 'Already up to date.' ),
				'success'     => true,
				'old_version' => $old_version,
				'new_version' => $new_version,
			];
		}

		// Verify version actually changed — upgrader may report success without updating files
		$success = (bool) $result;
		$message = $success ? esc_html( 'Success!' ) : esc_html( 'Update Failed!' );

		if ( $success && ! empty( $old_version ) && $old_version === $new_version ) {
			$success = false;
			$message = esc_html( 'Update completed but version unchanged.' );
		}

		return [
			'message'     => $message,
			'success'     => $success,
			'old_version' => $old_version,
			'new_version' => $new_version,
		];
	}
}

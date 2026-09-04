<?php
/**
 * Staging creation over the V4 migration engine.
 *
 * ADDITIVE BY DESIGN. This file adds a second, parallel path for creating a staging site; it does not
 * modify the V3 engine in any way. V3 keeps working untouched and is removed later as one deliberate
 * change rather than eroded here — which also keeps this diff reviewable and the revert trivial.
 *
 * WHY THE PLUGIN CAN SKIP MOST OF THE HOSTED WIZARD. instawp-connect runs INSIDE the source site, so
 * it already holds everything client-app's live-import wizard spends its first three steps
 * collecting: it installs instamigrate locally, mints its API key, and measures the site with
 * InstaWP_Tools::get_total_sizes(). No application password, no authorize popup, no manual plugin
 * download. It therefore enters client-app's pipeline at the point that wizard reaches after step 3.
 *
 * THE SEQUENCE:
 *   1. confirm the V4 engine is live               Helper::getMigrationEngine()
 *   2. install + activate instamigrate locally     Helper::installInstaMigrate()
 *   3. read its API key                            Helper::getInstaMigrateApiKey()
 *   4. seed the migration                          POST v2/migrate-v4/staging-init
 *   5. create the destination + start              POST v2/live-import/{uuid}/start
 *   6. hand the user the agent's own screen        migration_url, else tracking_url; persisted
 *
 * @package InstaWP
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

/**
 * Class InstaWP_Staging_V4
 */
class InstaWP_Staging_V4 {

	/**
	 * Option holding the last V4 staging run, so the agent URL survives a closed tab.
	 *
	 * Mirrors how V3 persists instawp_migration_details.
	 */
	const DETAILS_OPTION = 'instawp_staging_v4_details';

	/**
	 * InstaWP_Staging_V4 constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_instawp_staging_init_v4', array( $this, 'staging_init' ) );
		add_action( 'wp_ajax_instawp_staging_status_v4', array( $this, 'staging_status' ) );
	}

	/**
	 * AJAX: read the run's status, and capture the agent's URL once it exists.
	 *
	 * Neither URL is available when staging_init() returns. client-app only contacts the migration
	 * agent after the destination site has finished provisioning, so both are null until then and
	 * first appear on the status response. Once seen the chosen one is persisted, which is what lets
	 * the customer close the tab and come back.
	 */
	public function staging_status() {
		InstaWP_Tools::verify_ajax_request();

		$details = Option::get_option( self::DETAILS_OPTION );
		$uuid    = Helper::get_args_option( 'uuid', (array) $details, '' );

		if ( empty( $uuid ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No staging migration in progress.', 'instawp-connect' ) ) );
		}

		$response = Curl::do_curl( 'migrations/' . $uuid . '/status', array(), array(), 'GET' );

		if ( empty( $response['success'] ) ) {
			wp_send_json_error( array( 'message' => Helper::get_args_option( 'message', $response, esc_html__( 'Could not read the migration status.', 'instawp-connect' ) ) ) );
		}

		$data = Helper::get_args_option( 'data', $response, array() );

		// ALWAYS prefer migration_url — the agent's hosted flow page, carrying its session token.
		// tracking_url (public, token-less, never expires) is the fallback, and is what this flow
		// gets today: our migrates_v4 row is created by the webhook, which never sees a hosted URL.
		// Written as a preference rather than hardcoding the fallback so that the moment a hosted
		// URL does exist for this route, it is used with no change here.
		$migration_url = Helper::get_args_option( 'migration_url', $data, '' );
		$tracking_url  = Helper::get_args_option( 'tracking_url', $data, '' );
		$agent_url     = ! empty( $migration_url ) ? $migration_url : $tracking_url;

		if ( ! empty( $agent_url ) && $agent_url !== Helper::get_args_option( 'agent_url', (array) $details, '' ) ) {
			$details['agent_url'] = esc_url_raw( $agent_url );

			Option::update_option( self::DETAILS_OPTION, $details, false );
		}

		wp_send_json_success(
			array(
				'uuid'      => $uuid,
				'status'    => Helper::get_args_option( 'status', $data, '' ),
				'agent_url' => $agent_url,
			)
		);
	}

	/**
	 * Is the V4 migration engine live for this site?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$api_key = Helper::get_api_key();

		if ( empty( $api_key ) ) {
			return false;
		}

		// NOTE the return shape. Every connect-helpers method used in this class returns
		// Helper::sendResponse()'s envelope — array( 'success', 'message', 'data' ) — never a bare
		// value and never a WP_Error. Comparing the return directly against 'v4' silently yields
		// false forever.
		$response = Helper::getMigrationEngine( $api_key, 'staging' );

		return ! empty( $response['success'] ) && 'v4' === Helper::get_args_option( 'engine', Helper::get_args_option( 'data', $response, array() ), '' );
	}

	/**
	 * AJAX: create a staging site through the V4 engine.
	 *
	 * The agent URL is not known yet at this point — poll staging_status() for it.
	 */
	public function staging_init() {
		// Nonce AND capability. A nonce alone only proves the request came from a logged-in
		// browser session — any subscriber can read it out of the page source.
		InstaWP_Tools::verify_ajax_request();

		$result = self::run( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array_merge(
				array( 'message' => $result->get_error_message() ),
				(array) $result->get_error_data()
			) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Run the V4 staging sequence.
	 *
	 * Separate from the AJAX handler so the existing Create-Staging flow can delegate to it without
	 * going through a second HTTP round trip. Returns the data payload, or a WP_Error whose error
	 * data carries anything extra the caller should surface (e.g. recommended_plan_id).
	 *
	 * @param array $posted The posted request data.
	 *
	 * @return array|WP_Error
	 */
	public static function run( $posted ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'engine_not_v4', esc_html__( 'The V4 migration engine is not enabled for this site.', 'instawp-connect' ) );
		}

		$connect_id = instawp_get_connect_id();

		if ( empty( $connect_id ) ) {
			return new WP_Error( 'not_connected', esc_html__( 'This site is not connected to InstaWP.', 'instawp-connect' ) );
		}

		$migrate_settings = InstaWP_Tools::get_migrate_settings( $posted );
		$plan_id          = (int) Helper::get_args_option( 'plan_id', $migrate_settings, 0 );

		// Measured locally — files AND database. This is the number client-app sizes the plan
		// against, so it must match what the plan picker showed the user.
		$total_size_mb = self::total_size_mb( $migrate_settings );

		// Step 2 + 3: the plugin is the source, so it provisions its own credential. Nothing leaves
		// the site except the key itself.
		$api_key = self::provision_instamigrate();

		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		$payload = array(
			'source_url'        => Helper::wp_site_url( '', true ),
			'plugin_api_key'    => $api_key,
			'total_size_mb'     => $total_size_mb,
			'parent_connect_id' => $connect_id,
			'plan_id'           => empty( $plan_id ) ? null : $plan_id,
			'wp_version'        => get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'is_multisite'      => is_multisite(),
			'exclude'           => self::build_exclude( $migrate_settings ),
		);

		// NOTE: the legacy disk allowance is deliberately NOT sent. client-app derives it itself from
		// planAllow/planUsed at migration-start time — a quota supplied by the caller could be
		// inflated, and it is exactly the kind of number the server must not take on trust.

		$response = Curl::do_curl( 'migrate-v4/staging-init', $payload );

		if ( empty( $response['success'] ) ) {
			// FAIL-SAFE: instamigrate is installed but no migration exists. Leaving it silently
			// installed on a customer's production site is the worst outcome here, so record that it
			// needs cleaning up and tell the caller plainly.
			self::mark_instamigrate_orphaned();

			return new WP_Error(
				'staging_init_failed',
				Helper::get_args_option( 'message', $response, esc_html__( 'Could not start the staging migration.', 'instawp-connect' ) ),
				array(
					'recommended_plan_id' => Helper::get_args_option( 'recommended_plan_id', Helper::get_args_option( 'data', $response, array() ), 0 ),
				)
			);
		}

		$data = Helper::get_args_option( 'data', $response, array() );
		$uuid = Helper::get_args_option( 'uuid', $data, '' );

		if ( empty( $uuid ) ) {
			self::mark_instamigrate_orphaned();

			return new WP_Error( 'no_migration_reference', esc_html__( 'InstaWP did not return a migration reference.', 'instawp-connect' ) );
		}

		// Step 5: create the destination site and start. This is client-app's EXISTING endpoint —
		// unchanged, and shared with the hosted import wizard.
		// Omit rather than send a literal 0: start() validates plan_id as required|integer for a
		// non-legacy user, so 0 passes validation and fails later inside canCreateSiteWithPlan with a
		// worse message than "plan_id is required".
		$start_args      = array();
		$server_group_id = (int) Helper::get_args_option( 'server_group_id', $migrate_settings, 0 );

		if ( ! empty( $plan_id ) ) {
			$start_args['plan_id'] = $plan_id;
		}

		if ( ! empty( $server_group_id ) ) {
			$start_args['server_group_id'] = $server_group_id;
		}

		$start = Curl::do_curl( 'live-import/' . $uuid . '/start', $start_args );

		if ( empty( $start['success'] ) ) {
			self::mark_instamigrate_orphaned();

			return new WP_Error( 'site_create_failed', Helper::get_args_option( 'message', $start, esc_html__( 'Could not create the staging site.', 'instawp-connect' ) ) );
		}

		self::remember_run( $uuid );

		return array(
			// The wizard branches on this: a v4 run polls staging_status_v4 for the agent URL
			// instead of the V3 progress endpoint.
			'engine'     => 'v4',
			'uuid'       => $uuid,
			'started_at' => time(),
			'message'    => esc_html__( 'Staging site creation started.', 'instawp-connect' ),
		);
	}

	/**
	 * Total source size in MB — files AND database.
	 *
	 * Decimal MB (1000^2), matching the plan picker's own arithmetic, so the number the user saw
	 * disabling plans is the number client-app validates against.
	 *
	 * @param array $migrate_settings Migration settings.
	 *
	 * @return float
	 */
	private static function total_size_mb( $migrate_settings ) {
		$files = InstaWP_Tools::get_total_sizes( 'files', $migrate_settings );
		$db    = InstaWP_Tools::get_total_sizes( 'db' );

		return round( ( $files + $db ) / ( 1000 * 1000 ), 2 );
	}

	/**
	 * Install and activate instamigrate, and return its API key.
	 *
	 * @return string|WP_Error
	 */
	private static function provision_instamigrate() {
		$installed = Helper::installInstaMigrate();

		if ( empty( $installed['success'] ) ) {
			return new WP_Error(
				'instamigrate_install_failed',
				Helper::get_args_option( 'message', $installed, esc_html__( 'Could not install InstaMigrate.', 'instawp-connect' ) )
			);
		}

		$key_response = Helper::getInstaMigrateApiKey();

		if ( empty( $key_response['success'] ) ) {
			return new WP_Error(
				'instamigrate_key_missing',
				Helper::get_args_option( 'message', $key_response, esc_html__( 'Could not read the InstaMigrate API key.', 'instawp-connect' ) )
			);
		}

		$api_key = Helper::get_args_option( 'insta_mig_key', Helper::get_args_option( 'data', $key_response, array() ), '' );

		if ( empty( $api_key ) ) {
			return new WP_Error( 'instamigrate_key_missing', esc_html__( 'InstaMigrate returned an empty API key.', 'instawp-connect' ) );
		}

		return $api_key;
	}

	/**
	 * Translate the wizard's exclusions into the migration agent's vocabulary.
	 *
	 * The agent's contract differs from V3's in three ways that matter:
	 *   - paths are WP-CONTENT-RELATIVE, so selections are mapped rather than passed through;
	 *   - a glob's * crosses /, so uploads/* takes the whole tree, not one level;
	 *   - options and sitemeta are NEVER skippable. They are stripped server-side whatever we send,
	 *     so they are dropped here instead of appearing to be honoured.
	 *
	 * @param array $migrate_settings Migration settings.
	 *
	 * @return array
	 */
	private static function build_exclude( $migrate_settings ) {
		global $wpdb;

		$paths  = (array) Helper::get_args_option( 'excluded_paths', $migrate_settings, array() );
		$tables = (array) Helper::get_args_option( 'excluded_tables', $migrate_settings, array() );

		/*
		 * `excluded_paths` values are ROOT-RELATIVE, not absolute. The checkbox value is
		 * $data['relative_path'] (migrate/templates/part-create-staging.php:208), the hardcoded ones
		 * are 'wp-admin' / 'wp-includes' (class-instawp-tools.php:1032), and get_total_sizes()
		 * re-absolutises them with instawp_get_root_path() . '/' . $path before use.
		 *
		 * An earlier revision compared them against the ABSOLUTE WP_CONTENT_DIR, so every entry was
		 * dropped and exclude.paths was always empty. That was worse than a no-op: get_total_sizes()
		 * DOES honour the exclusions, so a user excluding a large uploads directory was sized for the
		 * small site, passed the plan check, and the agent then copied the full one.
		 */
		$root        = wp_normalize_path( rtrim( instawp_get_root_path(), '/' ) );
		$content_abs = wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) );
		$content_rel = trim( str_replace( $root, '', $content_abs ), '/' );

		$relative = array();

		foreach ( $paths as $path ) {
			$path = trim( wp_normalize_path( (string) $path ), '/' );

			if ( '' === $path || '' === $content_rel ) {
				continue;
			}

			// Excluding wp-content itself has no representation — the agent's paths are relative TO
			// it. Dropping it silently would turn "exclude everything" into "exclude nothing", so it
			// is skipped explicitly and left for the caller to notice.
			if ( $path === $content_rel ) {
				continue;
			}

			// Match on the separator so a sibling directory (wp-content-backup) cannot match.
			if ( 0 !== strpos( $path, $content_rel . '/' ) ) {
				continue;
			}

			$relative[] = substr( $path, strlen( $content_rel ) + 1 );
		}

		// wp_sitemeta is keyed off base_prefix, not prefix: on a subsite $wpdb->prefix is wp_2_,
		// and wp_2_sitemeta does not exist — so a real wp_sitemeta entry would slip past the filter.
		$protected = array( $wpdb->prefix . 'options', $wpdb->base_prefix . 'sitemeta' );
		$skippable = array();

		foreach ( $tables as $table ) {
			$table = (string) $table;

			if ( '' === $table || in_array( $table, $protected, true ) ) {
				continue;
			}

			$skippable[] = $table;
		}

		return array_filter(
			array(
				'paths'           => array_values( array_unique( array_filter( $relative ) ) ),
				'skip_table_data' => array_values( array_unique( $skippable ) ),
			)
		);
	}

	/**
	 * Persist the run so the tracking URL survives a closed tab.
	 *
	 * @param string $uuid client-app's migration reference.
	 *
	 * @return void
	 */
	private static function remember_run( $uuid ) {
		Option::update_option(
			self::DETAILS_OPTION,
			array(
				'uuid'       => $uuid,
				'started_at' => time(),
			),
			false
		);
	}

	/**
	 * Record that instamigrate was installed for a migration that never started.
	 *
	 * The plugin is left on the customer's production site otherwise, with nothing to explain it.
	 *
	 * @return void
	 */
	private static function mark_instamigrate_orphaned() {
		Option::update_option( 'instawp_instamigrate_orphaned', time(), false );

		Helper::add_error_log( 'V4 staging: instamigrate installed but the migration did not start' );
	}
}

new InstaWP_Staging_V4();

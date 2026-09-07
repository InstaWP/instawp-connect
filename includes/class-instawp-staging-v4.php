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
	 * Option holding the last V4 staging run (uuid + agent URL).
	 *
	 * ⚠ Persisted but NOT yet resumed. Nothing re-enters the watcher from this option on page load:
	 * the resume path in scripts.js is V3-only, gated on a server-rendered `loading` class a V4 run
	 * never sets. So closing the tab currently DOES lose the live view, and this option is a record
	 * for support and a future resume, not a working one. Do not describe it as tab-safe until a V4
	 * resume actually reads it.
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

		// Compare the ESCAPED form against the escaped value we stored, not the raw one — otherwise
		// any URL that esc_url_raw() alters looks different on every 3s poll and rewrites the option
		// each time.
		$agent_url = esc_url_raw( $agent_url );

		if ( ! empty( $agent_url ) && $agent_url !== Helper::get_args_option( 'agent_url', (array) $details, '' ) ) {
			$details['agent_url'] = $agent_url;

			Option::update_option( self::DETAILS_OPTION, $details, false );
		}

		wp_send_json_success(
			array(
				'uuid'      => $uuid,
				'status'    => Helper::get_args_option( 'status', $data, '' ),
				// Carried through so a `failed` status can say WHY. Without it the wizard can only
				// show a generic failure, which is barely better than the silent spinner it used to
				// show.
				'message'   => Helper::get_args_option( 'error_message', $data, '' ),
				'agent_url' => esc_url_raw( $agent_url ),
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

		/*
		 * CACHED, for two reasons that both bite V3.
		 *
		 * migrate_init() calls this as its FIRST statement on every Create-Staging click, v3 runs
		 * included, and Curl::do_curl scales its timeout off max_execution_time up to 290s. Uncached,
		 * V3 inherits client-app reachability as a latency and failure surface it never had — which
		 * would break "V4 is additive, V3 untouched" behaviourally even though the diff is +22/-0.
		 * A v4 run also asked twice (once from the caller, once at the top of run()), so this halves
		 * that too.
		 *
		 * Keyed on the api key so re-connecting the site to a different account re-reads it, and
		 * short enough (5 min) that flipping MIGRATION_ENGINE server-side takes effect promptly.
		 */
		$cache_key = 'instawp_migration_engine_' . md5( $api_key );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return 'v4' === $cached;
		}

		// NOTE the return shape. Every connect-helpers method used in this class returns
		// Helper::sendResponse()'s envelope — array( 'success', 'message', 'data' ) — never a bare
		// value and never a WP_Error. Comparing the return directly against 'v4' silently yields
		// false forever.
		$response = Helper::getMigrationEngine( $api_key, 'staging' );

		if ( empty( $response['success'] ) ) {
			// NOT cached. An unreachable client-app must not pin the site to v3 for five minutes,
			// and falling through to V3 is the safe direction anyway.
			return false;
		}

		$engine = Helper::get_args_option( 'engine', Helper::get_args_option( 'data', $response, array() ), '' );

		set_transient( $cache_key, $engine, 5 * MINUTE_IN_SECONDS );

		return 'v4' === $engine;
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

		// Order matters: the exclusions must be built BEFORE the site is sized, because the size is
		// computed against what we transmit rather than what the user selected. See total_size_mb().
		$exclude = self::build_exclude( $migrate_settings );

		/*
		 * Measured locally — files AND database — and DELIBERATELY NOT identical to the plan
		 * picker's number.
		 *
		 * get_site_plans() sizes with the full $migrate_settings, so it subtracts wp-admin,
		 * wp-includes and any root-level path the user ticked. This subtracts only what
		 * build_exclude() actually transmits, which excludes none of those. So this number is
		 * LARGER than the picker's, by the size of the root-level exclusions (~25-40 MB at minimum,
		 * more if the user ticked something big at root).
		 *
		 * The divergence is in the safe direction — we never under-state what the agent will copy —
		 * but it is real: a user sitting exactly on a plan boundary can pass the picker and then be
		 * told by the API to size up. Fixing that properly means teaching the picker the same
		 * transmitted-only rule; do not "fix" it by handing the raw settings back to this function,
		 * which is the bug this replaced.
		 */
		$total_size_mb = self::total_size_mb( $exclude );

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
			'exclude'           => $exclude,
		);

		// NOTE: the legacy disk allowance is deliberately NOT sent. client-app derives it itself from
		// planAllow/planUsed at migration-start time — a quota supplied by the caller could be
		// inflated, and it is exactly the kind of number the server must not take on trust.

		$response = Curl::do_curl( 'migrate-v4/staging-init', $payload );

		if ( empty( $response['success'] ) ) {
			self::log_orphaned_instamigrate( 'staging-init refused' );

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
			self::log_orphaned_instamigrate( 'no migration reference returned' );

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
			self::log_orphaned_instamigrate( 'destination site creation failed' );

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
	 * Decimal MB (1000^2), the same arithmetic the plan picker uses — but DELIBERATELY NOT the same
	 * number. The picker subtracts every selected exclusion; this subtracts only the ones
	 * build_exclude() can transmit, so this number is larger. See the call site in run() for why
	 * that divergence exists and why it is the safe direction.
	 *
	 * @param array $exclude The transmitted exclusion set, as returned by build_exclude().
	 *
	 * @return float
	 */
	private static function total_size_mb( $exclude ) {
		/*
		 * Sized against the exclusions we ACTUALLY TRANSMIT, not the ones the user selected.
		 *
		 * get_total_sizes() subtracts every entry in excluded_paths, including root-level ones
		 * ('wp-admin', 'wp-includes', and anything the user ticks in the file browser, which is
		 * rooted at the SITE ROOT, not wp-content). build_exclude() can only transmit
		 * wp-content-relative paths, because that is the agent's contract. Passing the raw settings
		 * here therefore sized the site as though root-level exclusions applied while the agent
		 * never received them -- the same direction of failure as the round-1 bug: sized small,
		 * passed the plan check, agent copies more than the plan holds.
		 *
		 * Subtracting only the transmitted set makes that class of mismatch FAIL SAFE. Whatever the
		 * agent's copy scope turns out to be, this number can never be smaller than what it copies,
		 * so the worst outcome is a plan larger than strictly needed. (What the agent copies at root
		 * level is not knowable from this repo -- do not "optimise" this by measuring wp-content
		 * alone without confirming that against the agent contract first.)
		 */
		$content_rel = self::content_rel();
		$transmitted = array();

		foreach ( (array) Helper::get_args_option( 'paths', $exclude, array() ) as $relative_path ) {
			$transmitted[] = $content_rel . '/' . $relative_path;
		}

		$files = InstaWP_Tools::get_total_sizes( 'files', array( 'excluded_paths' => $transmitted ) );
		$db    = InstaWP_Tools::get_total_sizes( 'db' );

		return round( ( $files + $db ) / ( 1000 * 1000 ), 2 );
	}

	/**
	 * Install and activate instamigrate, and return its API key.
	 *
	 * @return string|WP_Error
	 */
	private static function provision_instamigrate() {
		// Whether instamigrate was ALREADY here. installInstaMigrate() reports success when the
		// class already exists, so without this we would mark ourselves responsible for a plugin we
		// did not install and cannot claim to clean up.
		$pre_existing = class_exists( '\\InstaMigrate' );

		$installed = Helper::installInstaMigrate();

		/*
		 * MARK BEFORE THE SUCCESS CHECK, and decide from the SITE not from the return value.
		 *
		 * installInstaMigrate() reports success=false in a case where the plugin IS installed and
		 * activated: the installer succeeds, but `class_exists('\InstaMigrate')` /
		 * INSTA_MIGRATE_OPTION_KEY are not yet defined in the same request, so it returns
		 * 'After install INSTA_MIGRATE_OPTION_KEY not defined.' (connect-helpers Helper.php:219-224).
		 * That is the most likely first-click outcome, and it is exactly "we installed it and the
		 * migration never started" — the case the flag exists for. Marking after the success check
		 * skipped it, which is the same defect this guard was moved here to fix once already.
		 *
		 * class_exists() cannot be the signal for the same reason it fails above. active_plugins is
		 * read from the DB and does not depend on what this request has loaded.
		 */
		if ( ! $pre_existing ) {
			foreach ( (array) get_option( 'active_plugins', array() ) as $plugin_file ) {
				if ( 0 === strpos( (string) $plugin_file, 'instamigrate/' ) ) {
					self::mark_instamigrate_orphaned();
					break;
				}
			}
		}

		if ( empty( $installed['success'] ) ) {
			self::log_orphaned_instamigrate( 'instamigrate installed but did not initialise' );

			return new WP_Error(
				'instamigrate_install_failed',
				Helper::get_args_option( 'message', $installed, esc_html__( 'Could not install InstaMigrate.', 'instawp-connect' ) )
			);
		}

		$key_response = Helper::getInstaMigrateApiKey();

		if ( empty( $key_response['success'] ) ) {
			self::log_orphaned_instamigrate( 'instamigrate key unreadable' );

			return new WP_Error(
				'instamigrate_key_missing',
				Helper::get_args_option( 'message', $key_response, esc_html__( 'Could not read the InstaMigrate API key.', 'instawp-connect' ) )
			);
		}

		$api_key = Helper::get_args_option( 'insta_mig_key', Helper::get_args_option( 'data', $key_response, array() ), '' );

		if ( empty( $api_key ) ) {
			self::log_orphaned_instamigrate( 'instamigrate returned an empty key' );

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
	private static function content_rel() {
		/*
		 * basename( WP_CONTENT_DIR ), matching the PRODUCER exactly
		 * (class-instawp-tools.php:1010). An earlier revision derived this by stripping
		 * instawp_get_root_path() out of WP_CONTENT_DIR with str_replace, which agrees only when
		 * wp-content sits directly under the root. On Bedrock (WP_CONTENT_DIR outside ABSPATH) or
		 * where instawp_get_root_path() returns DOCUMENT_ROOT rather than ABSPATH, str_replace
		 * stripped nothing, every path failed the prefix test, and 100% of exclusions were dropped
		 * silently -- the round-1 bug, returning under a different layout. str_replace was also
		 * unanchored, replacing every occurrence rather than the prefix.
		 */
		return basename( wp_normalize_path( untrailingslashit( WP_CONTENT_DIR ) ) );
	}

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
		 *
		 * Anything this function DROPS (root-level entries: wp-admin, wp-includes, and whatever the
		 * user ticks in the file browser, which is rooted at the site root) is dropped from the
		 * SIZING too — total_size_mb() measures against this function's OUTPUT, not against
		 * $migrate_settings. Keep those two together: sizing against the user's selection while
		 * transmitting a subset is what created the bug above, in both of its revisions.
		 */
		$content_rel = self::content_rel();

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
	 * Persist the run. See DETAILS_OPTION — this is not yet read back on page load.
	 *
	 * @param string $uuid client-app's migration reference.
	 *
	 * @return void
	 */
	private static function remember_run( $uuid ) {
		// The migration exists, so the install is accounted for — clear both the flag and the
		// once-only log latch, so a genuinely new orphan later on is reported again.
		Option::delete_option( 'instawp_instamigrate_orphaned' );
		Option::delete_option( 'instawp_instamigrate_orphan_logged' );

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
	 * Record that WE installed instamigrate, pending a migration that references it.
	 *
	 * ⚠ This is a DIAGNOSTIC BREADCRUMB, not a rollback. Nothing reads this option and nothing
	 * uninstalls instamigrate: if the run dies after the install, the plugin stays on the
	 * customer's site and this option plus the error-log line are the only record of why. Set on a
	 * real install, cleared by remember_run() once a migration references it, so a lingering value
	 * means exactly "we installed this and the run never started".
	 *
	 * Do not describe this as rollback anywhere. Implementing actual cleanup (an admin notice, or
	 * deactivate-and-delete when the flag is older than an hour) is a separate change.
	 *
	 * @return void
	 */
	private static function mark_instamigrate_orphaned() {
		/*
		 * NO LOG LINE HERE. This is called on the SUCCESS path, right after a real install and
		 * before the migration reference exists, so a message reading "the migration did not start"
		 * fired on every healthy first run — permanently, into a 150-entry ring the debug-info
		 * endpoint hands back verbatim to customers — while the actual failures logged nothing.
		 * The signal was exactly inverted. Setting the flag is the bookkeeping; ANNOUNCING an
		 * orphan is a different event and belongs where the run actually gives up.
		 */
		Option::update_option( 'instawp_instamigrate_orphaned', time(), false );
	}

	/**
	 * Announce that we installed instamigrate and the run then failed.
	 *
	 * Called at each give-up point, where the claim is actually true. The flag itself is set
	 * earlier, at install time, so it survives paths that never reach here.
	 *
	 * @param string $reason why the run stopped.
	 *
	 * @return void
	 */
	private static function log_orphaned_instamigrate( $reason ) {
		if ( empty( Option::get_option( 'instawp_instamigrate_orphaned' ) ) ) {
			// We did not install it, so it is not ours to report.
			return;
		}

		if ( ! empty( Option::get_option( 'instawp_instamigrate_orphan_logged' ) ) ) {
			// Already announced for this outstanding install. Says it once, not once per attempt.
			return;
		}

		Helper::add_error_log( 'V4 staging: instamigrate installed but the migration did not start (' . $reason . ')' );

		/*
		 * The ORPHAN FLAG IS KEPT; only the re-logging is suppressed, via a separate option.
		 *
		 * An earlier revision deleted the flag here, on the reasoning that a later run would
		 * otherwise re-log a claim that was no longer true. That reasoning was wrong: nothing
		 * uninstalls instamigrate, so after a failure the plugin IS still orphaned and the claim
		 * stays true. Deleting the flag destroyed the only durable record of it at the exact moment
		 * it became accurate — and that flag is what a future admin notice or cleanup is meant to
		 * read, so clearing it here would have made that feature impossible to build.
		 *
		 * remember_run() remains the only place the flag is cleared, because a migration
		 * referencing instamigrate is the only thing that makes it non-orphaned.
		 */
		Option::update_option( 'instawp_instamigrate_orphan_logged', time(), false );
	}
}

new InstaWP_Staging_V4();

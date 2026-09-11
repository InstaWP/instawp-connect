<?php
/**
 * Every way instamigrate or the V4 run record can be removed, and the one rule they all obey:
 * nothing automatic deletes while client-app still reports the run as live.
 *
 * WHY THIS EXISTS. A run client-app reported as `migrating` had its agent deleted by the plugin.
 * The status check existed -- copy-pasted at two call sites -- and two automatic paths bypassed it.
 * One of them, the daily housekeeping job, wiped the record of a live V4 run because it gated on
 * fields only V3 ever sets. The fix extracted the check into one implementation and routed every
 * automatic deletion through it. These tests pin that: the shared pieces, each call site, and the
 * two paths that are DELIBERATELY not gated -- the user's own confirmed Cancel, and the 48h backstop.
 *
 * Structure: one section per thing that can delete, plus the shared helpers first. A test name
 * states the rule it protects; a failure message says what broke.
 */

use PHPUnit\Framework\TestCase;

final class InstaMigrateCleanupTest extends TestCase {

	const LIVE_UUID  = '7e60b9a1-0000-0000-0000-000000000052';
	const ENDED_UUID = 'e34179d7-0000-0000-0000-000000000051';

	protected function setUp(): void {
		IWP_Test_World::reset();
	}

	/** A stored run: the shape remember_run() writes, plus whatever the case needs. */
	private function store_run( $uuid, array $extra = array(), $age_seconds = 600 ) {
		update_option(
			InstaWP_Staging_V4::DETAILS_OPTION,
			array_merge( array( 'uuid' => $uuid, 'started_at' => time() - $age_seconds ), $extra ),
			false
		);
	}

	private function record() {
		return (array) get_option( InstaWP_Staging_V4::DETAILS_OPTION, array() );
	}

	private function admin_with_delete_plugins() {
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options', 'delete_plugins' );
	}

	// =============================================================================================
	// The shared pieces. Everything below is built on these three.
	// =============================================================================================

	public function test_terminal_statuses_are_exactly_completed_and_failed() {
		$this->assertTrue( InstaWP_Staging_V4::is_terminal_status( 'completed' ) );
		$this->assertTrue( InstaWP_Staging_V4::is_terminal_status( 'failed' ) );

		foreach ( array( 'migrating', 'creating_site', 'blocked', 'pending', '', 'COMPLETED', null, 0 ) as $not ) {
			$this->assertFalse( InstaWP_Staging_V4::is_terminal_status( $not ), var_export( $not, true ) . ' must not be terminal' );
		}
	}

	public function test_fetch_run_status_returns_the_status_client_app_reports() {
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->assertSame( 'migrating', InstaWP_Staging_V4::fetch_run_status( self::LIVE_UUID ) );

		$calls = IWP_Test_World::curl_calls_to( 'migrations/' . self::LIVE_UUID . '/status' );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'GET', $calls[0]['method'] );
	}

	public function test_fetch_run_status_answers_empty_when_client_app_cannot_be_read() {
		IWP_Test_World::client_app_unreadable();

		$this->assertSame( '', InstaWP_Staging_V4::fetch_run_status( self::LIVE_UUID ), 'unreadable must be "", never a guess' );
	}

	public function test_fetch_run_status_answers_empty_when_the_field_is_missing() {
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => true, 'data' => array( 'uuid' => 'x' ), 'code' => 200 );
		};

		$this->assertSame( '', InstaWP_Staging_V4::fetch_run_status( self::LIVE_UUID ) );
	}

	public function test_fetch_run_status_survives_a_throw_from_the_http_layer() {
		IWP_Test_World::$curl_responder = function () {
			throw new TypeError( 'simulated failure inside the HTTP client' );
		};

		$this->assertSame( '', InstaWP_Staging_V4::fetch_run_status( self::LIVE_UUID ), 'a throw is "unknown", and unknown is never terminal' );
		$this->assertNotEmpty( IWP_Test_World::$log, 'the failure must be logged, not swallowed silently' );
	}

	// ---------------------------------------------------------------------------------------------
	// run_has_ended(): the gate every automatic deletion goes through.
	// ---------------------------------------------------------------------------------------------

	public function test_run_has_ended_is_true_with_no_run_to_protect() {
		$this->assertTrue( InstaWP_Staging_V4::run_has_ended() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'no run, no request' );
	}

	public function test_run_has_ended_is_false_while_client_app_reports_the_run_live() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->assertFalse( InstaWP_Staging_V4::run_has_ended() );
	}

	public function test_run_has_ended_is_true_once_client_app_reports_terminal() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::client_app_reports( 'failed' );

		$this->assertTrue( InstaWP_Staging_V4::run_has_ended() );
	}

	public function test_run_has_ended_fails_closed_when_the_status_cannot_be_read() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::client_app_unreadable();

		$this->assertFalse( InstaWP_Staging_V4::run_has_ended(), 'an unreadable status must refuse, not permit' );
	}

	public function test_run_has_ended_trusts_local_finished_at_without_a_request() {
		$this->store_run( self::LIVE_UUID, array( 'finished_at' => time() - 60 ) );
		IWP_Test_World::client_app_unreadable();

		$this->assertTrue( InstaWP_Staging_V4::run_has_ended(), 'the plugin already saw this run end' );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'local evidence means no round trip' );
	}

	public function test_run_has_ended_trusts_local_instamigrate_removed_at_without_a_request() {
		$this->store_run( self::LIVE_UUID, array( 'instamigrate_removed_at' => time() - 60 ) );
		IWP_Test_World::client_app_unreadable();

		$this->assertTrue( InstaWP_Staging_V4::run_has_ended() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	// =============================================================================================
	// cleanup_instamigrate(): the deleter. It deletes; it does not decide.
	// =============================================================================================

	public function test_cleanup_is_idempotent_when_the_plugin_is_already_gone() {
		$this->store_run( self::ENDED_UUID );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertCount( 0, IWP_Test_World::$deleted_plugins, 'nothing to delete, so delete_plugins() must not be called' );
		$this->assertNotEmpty( $this->record()['instamigrate_removed_at'], 'still stamped: the run is retired either way' );
	}

	public function test_cleanup_deletes_the_plugin_and_stamps_the_run_when_no_user_is_driving() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$logged_in = false; // the REST push: API key, no WP user

		$this->assertTrue( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( array( array( 'instamigrate/insta-migrate.php' ) ), IWP_Test_World::$deleted_plugins );
		$this->assertNotEmpty( $this->record()['instamigrate_removed_at'] );
	}

	public function test_cleanup_refuses_a_logged_in_user_who_cannot_delete_plugins() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options' ); // multisite subsite admin

		$this->assertFalse( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'files must survive' );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record(), 'a refused delete must stay retryable' );
	}

	public function test_cleanup_does_not_stamp_the_run_when_the_delete_fails() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only filesystem' );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record(), 'the only record that a retry is needed' );
	}

	// =============================================================================================
	// CALL SITE 1 -- staging_status(): the 3s poll from the wizard.
	// =============================================================================================

	/** Run the poll and hand back what it would have sent to the browser. */
	private function poll() {
		try {
			( new InstaWP_Staging_V4() )->staging_status();
		} catch ( IWP_Json_Exit $e ) {
			return $e;
		}

		$this->fail( 'staging_status() must end in wp_send_json_*' );
	}

	public function test_poll_leaves_the_plugin_alone_while_the_run_is_live() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'migrating' );

		$sent = $this->poll();

		$this->assertTrue( $sent->success );
		$this->assertSame( 'migrating', $sent->payload['status'] );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'finished_at', $this->record() );
	}

	public function test_poll_removes_the_plugin_and_stamps_finished_on_a_terminal_status() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );

		$sent = $this->poll();

		$this->assertTrue( $sent->success );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertNotEmpty( $this->record()['finished_at'] );
		$this->assertCount( 1, IWP_Test_World::$curl_calls, 'the poll already had the status; it must not ask again' );
	}

	public function test_poll_still_answers_the_screen_when_the_delete_throws() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'failed' );
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'no credentials' );

		$sent = $this->poll();

		$this->assertTrue( $sent->success, 'a failed delete must not turn a terminal response into an error' );
		$this->assertSame( 'failed', $sent->payload['status'] );
		$this->assertNotEmpty( $this->record()['finished_at'], 'finished_at is stamped BEFORE the delete is attempted' );
	}

	public function test_poll_reports_an_error_when_client_app_cannot_be_read_and_deletes_nothing() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_unreadable();

		$sent = $this->poll();

		$this->assertFalse( $sent->success );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	// =============================================================================================
	// CALL SITE 2 -- run_cleanup_check() on admin_init: three arms.
	// =============================================================================================

	private function admin_init() {
		( new InstaWP_Staging_V4() )->maybe_cleanup_instamigrate();
	}

	public function test_admin_init_does_nothing_without_delete_plugins() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'failed' );
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options' );

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'no capability, no request' );
	}

	public function test_six_hour_arm_asks_client_app_and_leaves_a_live_run_alone() {
		$this->admin_with_delete_plugins();
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->admin_init();

		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/status' ) );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertNotEmpty( $this->record()['cleanup_checked_at'], 'the attempt is stamped so the next load does not ask again' );
	}

	public function test_six_hour_arm_removes_the_plugin_once_client_app_reports_terminal() {
		$this->admin_with_delete_plugins();
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );

		$this->admin_init();

		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_six_hour_arm_leaves_the_plugin_when_the_status_is_unreadable() {
		$this->admin_with_delete_plugins();
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_unreadable();

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'nothing is deleted on a guess' );
	}

	public function test_six_hour_arm_is_throttled() {
		$this->admin_with_delete_plugins();
		$this->store_run( self::LIVE_UUID, array( 'cleanup_checked_at' => time() - 60 ) );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );

		$this->admin_init();

		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'checked a minute ago; must not ask again for six hours' );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	public function test_six_hour_arm_skips_a_run_whose_plugin_is_already_recorded_removed() {
		$this->admin_with_delete_plugins();
		$this->store_run( self::ENDED_UUID, array( 'instamigrate_removed_at' => time() - 60 ) );

		$this->admin_init();

		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_forty_eight_hour_arm_cancels_and_removes_regardless_of_status() {
		// The ONE automatic path that does not ask -- your bounded backstop for a run whose outcome
		// never arrives. Pinned so nobody "fixes" it into asking.
		$this->admin_with_delete_plugins();
		$this->store_run( self::LIVE_UUID, array(), InstaWP_Staging_V4::CLEANUP_DEADLINE + 60 );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->admin_init();

		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/cancel' ), 'cancel first, so the agent stops' );
		$this->assertCount( 0, IWP_Test_World::curl_calls_to( '/status' ), 'past the deadline the status cannot change the outcome' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_a_missing_start_time_fails_closed_rather_than_reading_as_ancient() {
		$this->admin_with_delete_plugins();
		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array( 'uuid' => self::LIVE_UUID ), false );
		IWP_Test_World::install_instamigrate();

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_orphan_arm_removes_an_install_that_never_got_a_run_after_the_deadline() {
		$this->admin_with_delete_plugins();
		IWP_Test_World::install_instamigrate();
		update_option( InstaWP_Staging_V4::ORPHAN_OPTION, time() - InstaWP_Staging_V4::CLEANUP_DEADLINE - 60 );

		$this->admin_init();

		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertFalse( get_option( InstaWP_Staging_V4::ORPHAN_OPTION ), 'the flag is cleared only on a confirmed removal' );
	}

	public function test_orphan_arm_keeps_the_flag_when_the_delete_fails() {
		$this->admin_with_delete_plugins();
		IWP_Test_World::install_instamigrate();
		update_option( InstaWP_Staging_V4::ORPHAN_OPTION, time() - InstaWP_Staging_V4::CLEANUP_DEADLINE - 60 );
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertNotFalse( get_option( InstaWP_Staging_V4::ORPHAN_OPTION ), 'losing the flag would mean never retrying' );
	}

	public function test_orphan_arm_waits_out_the_deadline() {
		$this->admin_with_delete_plugins();
		IWP_Test_World::install_instamigrate();
		update_option( InstaWP_Staging_V4::ORPHAN_OPTION, time() - 60 );

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'a user who retries within minutes would have it reinstalled' );
	}

	// =============================================================================================
	// CALL SITE 3 -- REST migration-finished: client-app's push to the source.
	// =============================================================================================

	private function rest_push( $uuid ) {
		$api = ( new ReflectionClass( 'IWP_Test_Rest_Api' ) )->newInstanceWithoutConstructor();

		return $api->migration_finished( new WP_REST_Request( array( 'uuid' => $uuid ) ) )->get_data();
	}

	public function test_rest_push_ignores_a_notification_for_a_run_this_site_does_not_hold() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );

		$data = $this->rest_push( 'some-other-run' );

		$this->assertTrue( $data['status'], 'still 200: a mismatch is not an error client-app can act on' );
		$this->assertStringContainsString( 'different migration', $data['message'] );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'refused before asking' );
	}

	public function test_rest_push_fails_closed_when_uuid_is_absent() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();

		$this->rest_push( null );

		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'an earlier revision deleted on a missing uuid' );
	}

	public function test_rest_push_confirms_before_removing_and_removes_when_confirmed() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'failed' );

		$data = $this->rest_push( self::ENDED_UUID );

		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/status' ), 'the push SAYS the run ended; we confirm' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( 'Migration agent removed.', $data['message'] );
	}

	public function test_rest_push_refuses_while_client_app_still_reports_live_and_says_so() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'migrating' );

		$data = $this->rest_push( self::LIVE_UUID );

		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'a delayed or replayed push must not delete a live run\'s agent' );
		$this->assertTrue( $data['status'], 'still 200 -- client-app must not retry a terminal notification' );
		$this->assertStringContainsString( 'still live', $data['message'], 'must not claim removal it did not perform' );
	}

	public function test_rest_push_answers_200_even_when_the_delete_throws() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$data = $this->rest_push( self::ENDED_UUID );

		$this->assertTrue( $data['status'] );
	}

	// =============================================================================================
	// CALL SITE 4 -- clean_migrate_files(): the daily housekeeping job. The ONE gated reset caller.
	// =============================================================================================

	private function daily_job() {
		( new ReflectionClass( 'instaWP' ) )->newInstanceWithoutConstructor()->clean_migrate_files();
	}

	public function test_daily_job_refuses_to_reset_while_client_app_reports_the_run_live() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls, 'this is the regression: housekeeping wiped a live run' );
		$this->assertNotEmpty( $this->record() );
	}

	public function test_daily_job_refuses_when_the_status_cannot_be_read_and_tries_tomorrow() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::client_app_unreadable();

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls );
		$this->assertNotEmpty( $this->record() );
	}

	public function test_daily_job_resets_once_the_run_has_ended() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::client_app_reports( 'failed' );

		$this->daily_job();

		$this->assertCount( 1, IWP_Test_World::$reset_calls );
		$this->assertSame( array(), $this->record(), 'the ended run\'s record is cleared as it always was' );
	}

	public function test_daily_job_resets_on_local_evidence_without_a_request() {
		$this->store_run( self::LIVE_UUID, array( 'finished_at' => time() - 60 ) );
		IWP_Test_World::client_app_unreadable();

		$this->daily_job();

		$this->assertCount( 1, IWP_Test_World::$reset_calls );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_daily_job_resets_when_there_is_no_v4_run_at_all() {
		$this->daily_job();

		$this->assertCount( 1, IWP_Test_World::$reset_calls, 'the pre-V4 behaviour, unchanged' );
	}

	public function test_daily_job_defers_to_a_v3_migration_before_anything_else() {
		update_option( 'instawp_migration_details', array( 'migrate_id' => 7, 'migrate_key' => 'k' ) );
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::client_app_reports( 'failed' );

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'a V3 run in flight means no V4 question is even asked' );
	}

	// =============================================================================================
	// CALL SITE 5 -- staging_cancel(): the user's own Cancel. DELIBERATELY not gated.
	// =============================================================================================

	private function cancel() {
		try {
			( new InstaWP_Staging_V4() )->staging_cancel();
		} catch ( IWP_Json_Exit $e ) {
			return $e;
		}

		$this->fail( 'staging_cancel() must end in wp_send_json_*' );
	}

	public function test_user_cancel_removes_the_plugin_without_asking_the_status_again() {
		// The confirmation box the user just clicked through IS the check.
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function ( $endpoint ) {
			return array( 'success' => true, 'data' => array(), 'code' => 200 );
		};

		$sent = $this->cancel();

		$this->assertTrue( $sent->success );
		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/cancel' ) );
		$this->assertCount( 0, IWP_Test_World::curl_calls_to( '/status' ), 'the user decided; do not second-guess them' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_user_cancel_treats_already_terminal_422_as_done_and_cleans_up() {
		$this->store_run( self::ENDED_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => false, 'message' => 'Migration already finished.', 'data' => array(), 'code' => 422 );
		};

		$sent = $this->cancel();

		$this->assertTrue( $sent->success );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_user_cancel_leaves_the_plugin_when_the_cancel_itself_fails() {
		$this->store_run( self::LIVE_UUID );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => false, 'message' => 'Server error', 'data' => array(), 'code' => 500 );
		};

		$sent = $this->cancel();

		$this->assertFalse( $sent->success );
		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'the migration is still live; deleting under it would break the run we failed to stop' );
	}

	public function test_user_cancel_with_no_run_is_an_error_and_touches_nothing() {
		IWP_Test_World::install_instamigrate();

		$sent = $this->cancel();

		$this->assertFalse( $sent->success );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	// =============================================================================================
	// The invariant, stated once: no automatic path removes a live run's agent.
	// =============================================================================================

	public function test_no_automatic_path_removes_the_agent_of_a_live_run() {
		$paths = array(
			'poll'       => function () { $this->poll(); },
			'admin_init' => function () { $this->admin_with_delete_plugins(); $this->admin_init(); },
			'rest_push'  => function () { $this->rest_push( self::LIVE_UUID ); },
			'daily_job'  => function () { $this->daily_job(); },
		);

		foreach ( $paths as $name => $path ) {
			IWP_Test_World::reset();
			$this->store_run( self::LIVE_UUID );
			IWP_Test_World::install_instamigrate();
			IWP_Test_World::client_app_reports( 'migrating' );

			$path();

			$this->assertTrue( IWP_Test_World::instamigrate_installed(), $name . ' removed the agent of a run client-app reports as migrating' );
			$this->assertNotEmpty( $this->record(), $name . ' wiped the record of a live run' );
		}
	}
}

/**
 * The REST class without its constructor (which registers routes) and without real auth, which is
 * not what these tests are about.
 */
final class IWP_Test_Rest_Api extends InstaWP_Rest_Api {
	public function validate_api_request( WP_REST_Request $request, $option = '', $match_key = false ) {
		return true;
	}
}

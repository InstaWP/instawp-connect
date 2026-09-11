<?php
/**
 * Every way instamigrate or the V4 run record can be removed, and the one rule they all obey:
 * nothing automatic deletes while the run record says the migration is live.
 *
 * WHY THIS EXISTS. A run client-app reported as `migrating` had its agent deleted by the plugin.
 * Every design since asked client-app "has it ended?" at the moment of deciding, and every failure
 * came from that question being unanswerable at the wrong moment -- token revoked, connect gone,
 * outage. So the plugin stopped asking. The run record is written by the channels that already
 * learn the status (the poll, client-app's push, the user's Cancel), and every cleanup decision reads
 * the record and nothing else. Past 48h it is forced.
 *
 * Staleness can only delay a cleanup, never cause a premature one.
 *
 * And nobody CALLS cleanup after writing. The record's own option hooks react: a write that makes
 * the record terminal, or a delete, removes the plugin. Writers just write. The one thing a hook
 * cannot see is time, so admin_init and the daily job call retire_run() for the 48h backstop.
 *
 * That is what these tests pin: the decision, the hooks, the lifecycle, every writer, the clock, and
 * the two paths DELIBERATELY not gated -- the user's own confirmed Cancel, and the 48h backstop.
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InstaMigrateCleanupTest extends TestCase {

	const UUID = '7e60b9a1-0000-0000-0000-000000000052';

	protected function setUp(): void {
		IWP_Test_World::reset();
	}

	/**
	 * A stored run at a given status and age -- a PRECONDITION, so written straight into the option
	 * store with no hooks. "The record already says X" is a state, not an event; the events are
	 * what each test then triggers. (Writing through update_option() here would fire the record's
	 * hooks at setup time and clean up before the test had begun.)
	 *
	 * The shape the lifecycle writes: installing_at and installed_at from provision, uuid/started_at
	 * from remember_run, then whatever the case adds.
	 */
	private function store_run( $status, $age_seconds = 600, array $extra = array() ) {
		$t = time() - $age_seconds;

		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array_merge(
			array(
				'status'        => $status,
				'installing_at' => $t - 30,
				'installed_at'  => $t - 20,
				'uuid'          => self::UUID,
				'started_at'    => $t,
			),
			$extra
		);
	}

	/** An orphaned install: instamigrate on the site, no run ever started. Silent, as above. */
	private function store_orphan( $age_seconds ) {
		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array(
			'status'        => 'installed',
			'installing_at' => time() - $age_seconds - 10,
			'installed_at'  => time() - $age_seconds,
		);
	}

	private function record() {
		return (array) get_option( InstaWP_Staging_V4::DETAILS_OPTION, array() );
	}

	/** A V3 migration in flight: the record V3 writes, with the identifiers a live run carries. */
	private function store_v3_run() {
		update_option(
			'instawp_migration_details',
			array( 'migrate_id' => 7, 'migrate_key' => 'k', 'status' => 'initiated', 'mode' => 'pull' )
		);
	}

	private function admin_with_delete_plugins() {
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options', 'delete_plugins' );
	}

	private function past_deadline() {
		return InstaWP_Staging_V4::CLEANUP_DEADLINE + 60;
	}

	// =============================================================================================
	// The decision: cleanup_allowed(). Local read only.
	// =============================================================================================

	public function test_terminal_statuses_are_exactly_completed_and_failed() {
		$this->assertTrue( InstaWP_Staging_V4::is_terminal_status( 'completed' ) );
		$this->assertTrue( InstaWP_Staging_V4::is_terminal_status( 'failed' ) );

		foreach ( array( 'migrating', 'creating_site', 'blocked', 'installing', 'installed', 'started', '', 'COMPLETED', null, 0 ) as $not ) {
			$this->assertFalse( InstaWP_Staging_V4::is_terminal_status( $not ), var_export( $not, true ) . ' must not be terminal' );
		}
	}

	public function test_allowed_with_no_record_at_all() {
		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed(), 'nothing to protect' );
	}

	#[DataProvider( 'live_statuses' )]
	public function test_refused_while_the_record_says_live_and_under_the_deadline( $status ) {
		$this->store_run( $status );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_allowed(), $status . ' under 48h must be refused' );
	}

	public static function live_statuses() {
		return array(
			array( 'installing' ),
			array( 'installed' ),
			array( 'started' ),
			array( 'creating_site' ),
			array( 'migrating' ),
			array( 'blocked' ),
			array( '' ),
		);
	}

	#[DataProvider( 'terminal_statuses' )]
	public function test_allowed_once_the_record_says_terminal( $status ) {
		$this->store_run( $status );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed() );
	}

	public static function terminal_statuses() {
		return array( array( 'completed' ), array( 'failed' ) );
	}

	#[DataProvider( 'live_statuses' )]
	public function test_forced_once_past_the_deadline_whatever_the_status( $status ) {
		$this->store_run( $status, $this->past_deadline() );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed(), 'the bounded backstop: ' . $status . ' at 48h+' );
	}

	public function test_decision_never_makes_a_request() {
		foreach ( array( 'migrating', 'completed' ) as $status ) {
			IWP_Test_World::reset();
			$this->store_run( $status );
			IWP_Test_World::client_app_reports( 'completed' ); // would say yes if asked

			InstaWP_Staging_V4::cleanup_allowed();

			$this->assertCount( 0, IWP_Test_World::$curl_calls, 'cleanup_allowed() asked client-app on ' . $status );
		}
	}

	public function test_a_record_from_an_older_plugin_version_still_reads_as_ended() {
		// Earlier versions stamped finished_at / instamigrate_removed_at and carried no status.
		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array( 'uuid' => self::UUID, 'started_at' => time() - 600, 'finished_at' => time() - 60 );
		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed(), 'finished_at alone must count' );

		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array( 'uuid' => self::UUID, 'started_at' => time() - 600, 'instamigrate_removed_at' => time() - 60 );
		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed(), 'instamigrate_removed_at alone must count' );
	}

	// ---------------------------------------------------------------------------------------------
	// The deadline anchor: started_at, else installed_at, else installing_at; nothing -> closed.
	// ---------------------------------------------------------------------------------------------

	public function test_deadline_anchors_on_the_install_when_the_run_never_started() {
		$this->store_orphan( $this->past_deadline() );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed(), 'an install with no run is retired by the same deadline' );
	}

	public function test_deadline_anchors_on_installing_when_the_install_never_finished() {
		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array( 'status' => 'installing', 'installing_at' => time() - $this->past_deadline() );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed() );
	}

	public function test_a_record_with_no_timestamp_fails_closed_rather_than_reading_as_ancient() {
		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array( 'status' => 'migrating', 'uuid' => self::UUID );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_allowed(), 'no timestamp is not evidence of age' );
	}

	public function test_a_fresh_install_waits_out_the_deadline() {
		$this->store_orphan( 60 );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_allowed(), 'a user who retries within minutes would have it reinstalled' );
	}

	// ---------------------------------------------------------------------------------------------
	// V3: a different engine, the same site, still a live migration.
	// ---------------------------------------------------------------------------------------------

	public function test_refused_while_a_v3_migration_is_in_flight() {
		$this->store_v3_run();

		$this->assertFalse( InstaWP_Staging_V4::cleanup_allowed() );
	}

	public function test_v3_outvotes_a_terminal_v4_record() {
		$this->store_v3_run();
		$this->store_run( 'completed' );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_allowed(), 'a stale V4 record must not permit resetting a live V3 run' );
	}

	public function test_a_finished_v3_migration_leaves_no_identifiers_and_does_not_block() {
		update_option( 'instawp_migration_details', array( 'status' => 'completed' ) );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_allowed() );
	}

	// =============================================================================================
	// The record's lifecycle: who writes what, and when.
	// =============================================================================================

	private function provision() {
		$m = new ReflectionMethod( 'InstaWP_Staging_V4', 'provision_instamigrate' );
		$m->setAccessible( true );

		return $m->invoke( null );
	}

	public function test_provision_writes_installing_before_and_installed_after_the_install() {
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'install_plugins' );

		$key = $this->provision();

		$r = $this->record();
		$this->assertSame( 'test-key', $key );
		$this->assertSame( 'installed', $r['status'] );
		$this->assertNotEmpty( $r['installing_at'] );
		$this->assertNotEmpty( $r['installed_at'] );
		$this->assertGreaterThanOrEqual( $r['installing_at'], $r['installed_at'], 'installing is stamped first' );
		$this->assertArrayNotHasKey( 'uuid', $r, 'no run yet' );
	}

	public function test_provision_starts_a_fresh_record_rather_than_inheriting_the_last_run() {
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'install_plugins' );
		$this->store_run( 'completed', 3600, array( 'agent_url' => 'https://old.example' ) );

		$this->provision();

		$r = $this->record();
		$this->assertArrayNotHasKey( 'uuid', $r, 'the previous run\'s uuid must not leak into a new lifecycle' );
		$this->assertArrayNotHasKey( 'agent_url', $r );
		$this->assertSame( 'installed', $r['status'] );
	}

	public function test_provision_records_installed_even_when_the_installer_reports_failure_but_the_file_is_there() {
		// The documented first-click case: installer succeeds, but INSTA_MIGRATE_OPTION_KEY is not
		// defined in the same request, so installInstaMigrate() returns success=false.
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'install_plugins' );
		IWP_Test_World::$install_succeeds = false;
		IWP_Test_World::install_instamigrate(); // files present regardless

		$result = $this->provision();

		$this->assertInstanceOf( 'WP_Error', $result );
		$this->assertSame( 'installed', $this->record()['status'], 'we put files there; the deadline must know' );
		$this->assertNotEmpty( IWP_Test_World::$log, 'and it is announced once' );
	}

	public function test_provision_announces_an_orphan_once_not_once_per_attempt() {
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'install_plugins' );
		IWP_Test_World::$install_succeeds = false;
		IWP_Test_World::install_instamigrate();

		$this->provision();
		$this->provision();

		$orphan_lines = array_filter( IWP_Test_World::$log, function ( $l ) {
			return is_string( $l ) && false !== strpos( $l, 'did not start' );
		} );
		$this->assertCount( 1, $orphan_lines );
	}

	public function test_remember_run_merges_into_the_record_and_marks_started() {
		IWP_Test_World::$options[ InstaWP_Staging_V4::DETAILS_OPTION ] = array( 'status' => 'installed', 'installing_at' => 100, 'installed_at' => 200 );

		$m = new ReflectionMethod( 'InstaWP_Staging_V4', 'remember_run' );
		$m->setAccessible( true );
		$m->invoke( null, self::UUID );

		$r = $this->record();
		$this->assertSame( self::UUID, $r['uuid'] );
		$this->assertSame( 'started', $r['status'] );
		$this->assertNotEmpty( $r['started_at'] );
		$this->assertSame( 100, $r['installing_at'], 'the install timestamps must survive' );
		$this->assertSame( 200, $r['installed_at'] );
	}

	public function test_record_terminal_accepts_only_terminal_statuses() {
		$this->store_run( 'migrating' );

		InstaWP_Staging_V4::record_terminal( 'migrating' );
		$this->assertSame( 'migrating', $this->record()['status'], 'a non-terminal status must be ignored' );
		$this->assertArrayNotHasKey( 'finished_at', $this->record() );

		InstaWP_Staging_V4::record_terminal( 'completed' );
		$this->assertSame( 'completed', $this->record()['status'] );
		$this->assertNotEmpty( $this->record()['finished_at'] );
	}

	public function test_record_terminal_keeps_the_first_finished_at() {
		$this->store_run( 'completed', 600, array( 'finished_at' => 12345 ) );

		InstaWP_Staging_V4::record_terminal( 'failed' );

		$this->assertSame( 12345, $this->record()['finished_at'], 'the moment we first saw it end is the one worth keeping' );
	}

	// =============================================================================================
	// cleanup_instamigrate(): the deleter. It deletes; it does not decide.
	// =============================================================================================

	public function test_cleanup_is_idempotent_when_the_plugin_is_already_gone() {
		$this->store_run( 'completed' );

		$this->assertTrue( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertCount( 0, IWP_Test_World::$deleted_plugins );
		$this->assertNotEmpty( $this->record()['instamigrate_removed_at'] );
	}

	public function test_cleanup_deletes_and_stamps_when_no_user_is_driving() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();

		$this->assertTrue( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertNotEmpty( $this->record()['instamigrate_removed_at'] );
	}

	public function test_cleanup_refuses_a_logged_in_user_who_cannot_delete_plugins() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options' );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record(), 'a refused delete must stay retryable' );
	}

	public function test_cleanup_does_not_stamp_when_the_delete_fails() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only filesystem' );

		$this->assertFalse( InstaWP_Staging_V4::cleanup_instamigrate() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record() );
	}

	// =============================================================================================
	// THE HOOKS. The record's option hooks are the one place an EVENT removes the plugin.
	// =============================================================================================

	public function test_the_class_registers_the_three_record_hooks_once() {
		new InstaWP_Staging_V4();
		new InstaWP_Staging_V4();

		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $prefix ) {
			$tag = $prefix . InstaWP_Staging_V4::DETAILS_OPTION;
			$this->assertCount( 1, IWP_Test_World::$hooks[ $tag ] ?? array(), $tag . ': static callables deduplicate, however often the class is built' );
		}
	}

	public function test_a_write_that_makes_the_record_terminal_removes_the_plugin() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array_merge( $this->record(), array( 'status' => 'completed' ) ) );

		$this->assertFalse( IWP_Test_World::instamigrate_installed(), 'the update hook is what retires the agent' );
		$this->assertNotEmpty( $this->record()['instamigrate_removed_at'] );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_a_write_that_leaves_the_record_live_removes_nothing() {
		$this->store_run( 'started' );
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array_merge( $this->record(), array( 'status' => 'migrating' ) ) );

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	public function test_a_write_on_an_old_but_live_record_does_not_force_the_plugin_off() {
		// The deadline belongs to the clock (retire_run), which cancels first. An event must not
		// short-circuit that by force-removing without the cancel.
		$this->store_run( 'migrating', $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array_merge( $this->record(), array( 'status' => 'blocked' ) ) );

		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'events react to endings; the deadline is not an event' );
	}

	public function test_the_first_write_of_a_terminal_record_also_removes_the_plugin() {
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array( 'status' => 'failed', 'uuid' => self::UUID, 'started_at' => time() - 60 ) );

		$this->assertContains( 'add_option_' . InstaWP_Staging_V4::DETAILS_OPTION, IWP_Test_World::$fired, 'a first write is add_option, not update_option' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_deleting_the_record_removes_the_plugin() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		delete_option( InstaWP_Staging_V4::DETAILS_OPTION );

		$this->assertFalse( IWP_Test_World::instamigrate_installed(), 'no record, nothing to protect: the plugin follows the record' );
	}

	public function test_an_unchanged_write_fires_nothing() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, $this->record() );

		$this->assertNotContains( 'update_option_' . InstaWP_Staging_V4::DETAILS_OPTION, IWP_Test_World::$fired );
		$this->assertTrue( IWP_Test_World::instamigrate_installed(), 'WordPress does not fire on an equal value, so neither does the cleanup' );
	}

	public function test_the_reactor_does_not_re_enter_itself() {
		// cleanup_instamigrate() stamps instamigrate_removed_at, which is itself a record write.
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array_merge( $this->record(), array( 'status' => 'completed' ) ) );

		$this->assertCount( 1, IWP_Test_World::$deleted_plugins, 'one delete, however many times the record was written on the way' );
	}

	public function test_a_failed_delete_on_the_hook_leaves_the_record_retryable() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		update_option( InstaWP_Staging_V4::DETAILS_OPTION, array_merge( $this->record(), array( 'status' => 'completed' ) ) );

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( 'completed', $this->record()['status'], 'the ending is recorded even though the delete failed' );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record(), 'so the clock can retry it' );
	}

	// =============================================================================================
	// THE CLOCK: retire_run(). What admin_init and the daily job call, because time is not an event.
	// =============================================================================================

	public function test_retire_leaves_a_live_run_alone() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		$this->assertFalse( InstaWP_Staging_V4::retire_run() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertNotEmpty( $this->record() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_retire_removes_plugin_and_record_on_a_terminal_run_whose_delete_had_failed() {
		// The retry path: the hook tried when the run ended, the filesystem refused, the record kept
		// its terminal status. The next admin load finishes the job.
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();

		$this->assertTrue( InstaWP_Staging_V4::retire_run() );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( array(), $this->record(), 'the record goes only once the plugin is confirmed gone' );
		$this->assertCount( 0, IWP_Test_World::curl_calls_to( '/cancel' ), 'a run that ended needs no cancelling' );
	}

	public function test_retire_forces_past_the_deadline_with_a_cancel_first() {
		$this->store_run( 'migrating', $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		$this->assertTrue( InstaWP_Staging_V4::retire_run() );
		$this->assertCount( 1, IWP_Test_World::curl_calls_to( 'migrations/' . self::UUID . '/cancel' ), 'tell the agent to stop before pulling the plugin out from under it' );
		$this->assertCount( 0, IWP_Test_World::curl_calls_to( '/status' ), 'still never asks the status' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( array(), $this->record() );
	}

	public function test_retire_does_not_cancel_an_orphan_that_has_no_run() {
		$this->store_orphan( $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		$this->assertTrue( InstaWP_Staging_V4::retire_run() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'no uuid, nothing to cancel' );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_retire_keeps_the_record_when_the_delete_fails() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$this->assertFalse( InstaWP_Staging_V4::retire_run() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertNotEmpty( $this->record(), 'deleting the record with the plugin still there would orphan it for good' );
	}

	public function test_retire_with_no_record_still_sweeps_a_stray_plugin() {
		IWP_Test_World::install_instamigrate();

		$this->assertTrue( InstaWP_Staging_V4::retire_run() );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	// =============================================================================================
	// CALL SITE 1 -- staging_status(): the 3s poll. Keeps the record current; cleans on terminal.
	// =============================================================================================

	private function poll() {
		try {
			( new InstaWP_Staging_V4() )->staging_status();
		} catch ( IWP_Json_Exit $e ) {
			return $e;
		}

		$this->fail( 'staging_status() must end in wp_send_json_*' );
	}

	public function test_poll_writes_client_app_status_into_the_record() {
		$this->store_run( 'started' );
		IWP_Test_World::client_app_reports( 'migrating' );

		$this->poll();

		$this->assertSame( 'migrating', $this->record()['status'], 'the poll is the record\'s main writer after the run starts' );
	}

	public function test_poll_leaves_the_plugin_alone_while_live() {
		$this->store_run( 'started' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'migrating' );

		$sent = $this->poll();

		$this->assertTrue( $sent->success );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'finished_at', $this->record() );
	}

	public function test_poll_records_the_end_and_the_hook_removes_the_plugin() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' );

		$this->poll();

		$r = $this->record();
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( 'completed', $r['status'] );
		$this->assertNotEmpty( $r['finished_at'] );
		$this->assertCount( 1, IWP_Test_World::$curl_calls, 'one request: the poll itself' );
	}

	public function test_poll_still_answers_the_screen_when_the_delete_throws() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'failed' );
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'no credentials' );

		$sent = $this->poll();

		$this->assertTrue( $sent->success );
		$this->assertSame( 'failed', $this->record()['status'], 'the record is written BEFORE the delete is attempted' );
	}

	public function test_poll_leaves_the_record_untouched_when_client_app_cannot_be_read() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_unreadable();

		$sent = $this->poll();

		$this->assertFalse( $sent->success );
		$this->assertSame( 'migrating', $this->record()['status'] );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	// =============================================================================================
	// CALL SITE 2 -- admin_init. One arm, no HTTP.
	// =============================================================================================

	private function admin_init() {
		( new InstaWP_Staging_V4() )->maybe_cleanup_instamigrate();
	}

	public function test_admin_init_does_nothing_without_delete_plugins() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$logged_in = true;
		IWP_Test_World::$caps      = array( 'manage_options' );

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	public function test_admin_init_leaves_a_live_run_alone_and_asks_nothing() {
		$this->admin_with_delete_plugins();
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::client_app_reports( 'completed' ); // would say yes if asked

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'admin_init makes no request' );
	}

	public function test_admin_init_finishes_a_terminal_run_whose_delete_had_failed() {
		$this->admin_with_delete_plugins();
		$this->store_run( 'failed' );
		IWP_Test_World::install_instamigrate();

		$this->admin_init();

		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( array(), $this->record() );
	}

	public function test_admin_init_forces_past_the_deadline_with_a_cancel_first() {
		$this->admin_with_delete_plugins();
		$this->store_run( 'migrating', $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		$this->admin_init();

		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/cancel' ) );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_admin_init_retires_an_orphaned_install_after_the_deadline() {
		$this->admin_with_delete_plugins();
		$this->store_orphan( $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		$this->admin_init();

		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_admin_init_survives_a_throw_from_the_deleter() {
		$this->admin_with_delete_plugins();
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$this->admin_init();

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertArrayNotHasKey( 'instamigrate_removed_at', $this->record(), 'left retryable' );
	}

	// =============================================================================================
	// CALL SITE 3 -- REST migration-finished: client-app's push. A status UPDATE, uuid-guarded.
	// =============================================================================================

	private function rest_push( $uuid, $status = null ) {
		$api    = ( new ReflectionClass( 'IWP_Test_Rest_Api' ) )->newInstanceWithoutConstructor();
		$params = array( 'uuid' => $uuid );

		if ( null !== $status ) {
			$params['status'] = $status;
		}

		return $api->migration_finished( new WP_REST_Request( $params ) )->get_data();
	}

	public function test_rest_push_ignores_a_notification_for_a_run_this_site_does_not_hold() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		$data = $this->rest_push( 'some-other-run', 'completed' );

		$this->assertTrue( $data['status'], 'still 200: a mismatch is not an error client-app can act on' );
		$this->assertStringContainsString( 'different migration', $data['message'] );
		$this->assertSame( 'migrating', $this->record()['status'], 'a foreign push must not touch the record' );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	public function test_rest_push_fails_closed_when_uuid_is_absent() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		$this->rest_push( null, 'completed' );

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( 'migrating', $this->record()['status'] );
	}

	public function test_rest_push_records_the_end_and_the_hook_removes_the_plugin() {
		// The tab-closed case: the poll never saw it end; this is how the plugin learns.
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		$data = $this->rest_push( self::UUID, 'completed' );

		$r = $this->record();
		$this->assertSame( 'completed', $r['status'] );
		$this->assertNotEmpty( $r['finished_at'] );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertSame( 'Migration agent removed.', $data['message'] );
		$this->assertCount( 0, IWP_Test_World::$curl_calls, 'an inbound push is not us asking; nothing goes back out' );
	}

	public function test_rest_push_with_a_non_terminal_status_changes_nothing() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();

		$data = $this->rest_push( self::UUID, 'blocked' );

		$this->assertSame( 'migrating', $this->record()['status'], 'the endpoint announces endings; anything else is not acted on' );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertStringContainsString( 'not recorded as ended', $data['message'] );
	}

	public function test_rest_push_cannot_move_a_finished_record_back_to_live() {
		$this->store_run( 'completed', 600, array( 'finished_at' => time() - 300 ) );

		$this->rest_push( self::UUID, 'migrating' );

		$this->assertSame( 'completed', $this->record()['status'] );
	}

	public function test_rest_push_without_a_status_writes_nothing_and_removes_nothing() {
		// A terminal record with the plugin still present means an earlier delete failed. That is
		// the clock's retry, not the push's: the push only ever writes what it carries.
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();

		$this->rest_push( self::UUID );

		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertNotContains( 'update_option_' . InstaWP_Staging_V4::DETAILS_OPTION, IWP_Test_World::$fired );
	}

	public function test_rest_push_answers_200_even_when_the_delete_throws() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$data = $this->rest_push( self::UUID, 'completed' );

		$this->assertTrue( $data['status'], 'client-app must never retry a terminal notification' );
		$this->assertSame( 'completed', $this->record()['status'], 'the end is recorded even though the delete failed' );
	}

	// =============================================================================================
	// CALL SITE 4 -- clean_migrate_files(): the daily job. Never resets without the record's say-so.
	// =============================================================================================

	private function daily_job() {
		( new ReflectionClass( 'instaWP' ) )->newInstanceWithoutConstructor()->clean_migrate_files();
	}

	#[DataProvider( 'live_statuses' )]
	public function test_daily_job_refuses_while_the_record_says_live( $status ) {
		$this->store_run( $status );
		IWP_Test_World::install_instamigrate();

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls, 'housekeeping wiped a live run (' . $status . ')' );
		$this->assertNotEmpty( $this->record() );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
	}

	public function test_daily_job_cleans_plugin_and_record_once_terminal() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();

		$this->daily_job();

		$this->assertFalse( IWP_Test_World::instamigrate_installed(), 'the agent comes off too' );
		$this->assertCount( 1, IWP_Test_World::$reset_calls );
		$this->assertSame( array(), $this->record() );
	}

	public function test_daily_job_does_not_reset_while_the_plugin_could_not_be_removed() {
		$this->store_run( 'completed' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$delete_result = new WP_Error( 'fs', 'read-only' );

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls, 'resetting would wipe the only record that a retry is owed' );
		$this->assertNotEmpty( $this->record() );
	}

	public function test_daily_job_forces_past_the_deadline() {
		$this->store_run( 'migrating', $this->past_deadline() );
		IWP_Test_World::install_instamigrate();

		$this->daily_job();

		$this->assertCount( 1, IWP_Test_World::curl_calls_to( '/cancel' ) );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 1, IWP_Test_World::$reset_calls );
	}

	public function test_daily_job_resets_when_there_is_no_v4_run_at_all() {
		$this->daily_job();

		$this->assertCount( 1, IWP_Test_World::$reset_calls, 'the pre-V4 behaviour, unchanged' );
	}

	public function test_daily_job_refuses_while_a_v3_migration_is_in_flight() {
		$this->store_v3_run();

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls );
		$this->assertNotEmpty( get_option( 'instawp_migration_details' ) );
	}

	public function test_daily_job_defers_to_v3_even_when_a_terminal_v4_record_exists() {
		$this->store_v3_run();
		$this->store_run( 'completed' );

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls );
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

	public function test_user_cancel_records_the_end_and_the_hook_removes_the_plugin() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => true, 'data' => array(), 'code' => 200 );
		};

		$sent = $this->cancel();

		$r = $this->record();
		$this->assertTrue( $sent->success );
		$this->assertSame( 'failed', $r['status'], 'Cancel is one of the three channels that keep the record current' );
		$this->assertNotEmpty( $r['finished_at'] );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
		$this->assertCount( 0, IWP_Test_World::curl_calls_to( '/status' ), 'the user decided; do not second-guess them' );
	}

	public function test_user_cancel_treats_already_terminal_422_as_done() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => false, 'message' => 'Migration already finished.', 'data' => array(), 'code' => 422 );
		};

		$sent = $this->cancel();

		$this->assertTrue( $sent->success );
		$this->assertSame( 'failed', $this->record()['status'] );
		$this->assertFalse( IWP_Test_World::instamigrate_installed() );
	}

	public function test_user_cancel_changes_nothing_when_the_cancel_itself_fails() {
		$this->store_run( 'migrating' );
		IWP_Test_World::install_instamigrate();
		IWP_Test_World::$curl_responder = function () {
			return array( 'success' => false, 'message' => 'Server error', 'data' => array(), 'code' => 500 );
		};

		$sent = $this->cancel();

		$this->assertFalse( $sent->success );
		$this->assertSame( 'migrating', $this->record()['status'], 'the run is still live; the record must say so' );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	public function test_user_cancel_with_no_run_is_an_error_and_touches_nothing() {
		IWP_Test_World::install_instamigrate();

		$sent = $this->cancel();

		$this->assertFalse( $sent->success );
		$this->assertCount( 0, IWP_Test_World::$curl_calls );
		$this->assertTrue( IWP_Test_World::instamigrate_installed() );
	}

	// =============================================================================================
	// The invariant, stated once: no automatic path removes a live run's agent or wipes its record.
	// =============================================================================================

	public function test_no_automatic_path_touches_a_live_run() {
		$paths = array(
			'poll'       => function () { IWP_Test_World::client_app_reports( 'migrating' ); $this->poll(); },
			'admin_init' => function () { $this->admin_with_delete_plugins(); $this->admin_init(); },
			'rest_push'  => function () { $this->rest_push( self::UUID, 'blocked' ); },
			'daily_job'  => function () { $this->daily_job(); },
		);

		foreach ( $paths as $name => $path ) {
			IWP_Test_World::reset();
			$this->store_run( 'migrating' );
			IWP_Test_World::install_instamigrate();

			$path();

			$this->assertTrue( IWP_Test_World::instamigrate_installed(), $name . ' removed the agent of a run the record says is live' );
			$this->assertSame( 'migrating', $this->record()['status'] ?? null, $name . ' altered the record of a live run' );
		}
	}

	public function test_no_automatic_path_resets_a_live_v3_migration() {
		$this->store_v3_run();

		$this->daily_job();

		$this->assertCount( 0, IWP_Test_World::$reset_calls, 'housekeeping reset a running V3 migration' );
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

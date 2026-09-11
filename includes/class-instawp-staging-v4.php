<?php
/**
 * Staging creation from wp-admin.
 *
 * Runs inside the source site, so it can install instamigrate, mint its key and measure the site
 * locally — which is why it enters client-app's import pipeline directly rather than going through
 * the hosted wizard's credential steps.
 *
 *   1. install + activate instamigrate locally     Helper::installInstaMigrate()
 *   2. read its API key                            Helper::getInstaMigrateApiKey()
 *   3. seed the migration                          POST v2/migrate-v4/staging-init
 *   4. create the destination + start              POST v2/live-import/{uuid}/start
 *   5. hand the user the agent's own screen        migration_url, else tracking_url; persisted
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
	 * Option holding the last V4 staging run (uuid, start time, agent URL, finish time).
	 *
	 * READ BACK ON PAGE LOAD by resumable_run(): part-create.php stamps a class from it, and
	 * scripts.js re-enters the watcher. Closing the tab therefore no longer loses the live view,
	 * within RESUME_WINDOW.
	 *
	 * Mirrors how V3 persists instawp_migration_details, except V3 resumes off a `loading` class
	 * driven by instawp_migration_details.migrate_id — a V4 run has no migrate_id and must never set
	 * that class, because it starts the V3 progress poll against a migration row that does not exist.
	 */
	const DETAILS_OPTION = 'instawp_staging_v4_details';

	/**
	 * How long after `started_at` a run is still worth re-entering on page load.
	 *
	 * The option has no expiry of its own, so without a window a run from any point in the past
	 * would reopen the "migration in progress" view forever — including runs whose outcome we never
	 * saw because the tab was closed before a poll returned a terminal status.
	 *
	 * 12h is chosen against the WORK, not the UI: a large source can migrate for hours, and the
	 * cost of being wrong differs sharply by direction. Too short and we abandon a live migration's
	 * view while it is still running; too long and the first poll returns a terminal status and the
	 * screen corrects itself in three seconds. So this errs long deliberately.
	 */
	const RESUME_WINDOW = 12 * HOUR_IN_SECONDS;

	/**
	 * WE installed instamigrate and no migration has referenced it yet.
	 *
	 * Set at install time so it survives every path that never reaches the end of the run; cleared
	 * only by remember_run(), because a migration referencing instamigrate is the one thing that
	 * makes the install non-orphaned.
	 */
	/**
	 * How long after `started_at` we keep deferring to a run that has not finished.
	 *
	 * Past this, instamigrate is removed whatever the status says. Deliberately well beyond any
	 * plausible migration -- anything still going after two days is wedged, not slow -- because the
	 * cost of being wrong here is destructive: the cancel that accompanies it also deletes the
	 * destination site.
	 */
	const CLEANUP_DEADLINE = 48 * HOUR_IN_SECONDS;

	/**
	 * How often an admin page load may ask client-app whether the run has finished.
	 *
	 * admin_init fires on EVERY wp-admin request, so without a throttle a busy dashboard would call
	 * client-app dozens of times a minute for a run whose answer changes once.
	 */
	const STATUS_CHECK_INTERVAL = 6 * HOUR_IN_SECONDS;

	const ORPHAN_OPTION = 'instawp_instamigrate_orphaned';

	/**
	 * The orphan above has already been written to the error log once.
	 *
	 * Separate from ORPHAN_OPTION on purpose: the flag is the durable record and must survive, while
	 * this only stops the same outstanding install being announced once per attempt.
	 */
	const ORPHAN_LOGGED_OPTION = 'instawp_instamigrate_orphan_logged';

	/**
	 * Statuses after which nothing on the source is needed.
	 *
	 * The ONLY place this list lives. It used to be written out inline at three sites, and a list
	 * copied three times is a list that will one day be edited in two of them.
	 */
	const TERMINAL_STATUSES = array( 'completed', 'failed' );

	/**
	 * InstaWP_Staging_V4 constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_instawp_staging_status_v4', array( $this, 'staging_status' ) );
		add_action( 'wp_ajax_instawp_staging_cancel_v4', array( $this, 'staging_cancel' ) );
		add_action( 'admin_init', array( $this, 'maybe_cleanup_instamigrate' ) );
	}

	/**
	 * AJAX: read the run's status, and capture the agent's URL once it exists.
	 *
	 * Neither URL is available when staging_init() returns. client-app only contacts the migration
	 * agent after the destination site has finished provisioning, so both are null until then and
	 * first appear on the status response. Once seen the chosen one is persisted, which is what lets
	 * the customer close the tab and come back.
	 */
	/**
	 * The stored run, if it is still worth re-entering the watcher for on page load.
	 *
	 * ONE place decides resumability, because two would drift: part-create.php stamps the class,
	 * part-create-staging.php seeds the link, and scripts.js acts on the class — all three must
	 * agree about whether a run is live, or the page renders "in progress" with no poll behind it.
	 *
	 * Refuses a run that has no uuid, one already marked finished by a terminal poll, and one older
	 * than RESUME_WINDOW.
	 *
	 * @return array the run details, or an empty array when there is nothing to resume.
	 */
	/**
	 * AJAX: cancel the run this site started, and take the agent back off.
	 *
	 * client-app's cancel does three things: it tells the agent to stop, claims the terminal
	 * transition as `failed`, and DELETES the destination site. The button's confirm text says so --
	 * a user stopping a slow migration would not otherwise expect to lose the site.
	 *
	 * A 422 is SUCCESS from here. It means client-app already considers the run terminal and we were
	 * simply never told, so the run is over either way and the only honest thing to do is stop
	 * showing it as live. Treating it as an error would leave the screen spinning on a migration that
	 * has finished -- which is the failure this whole flow exists to stop.
	 */
	public function staging_cancel() {
		InstaWP_Tools::verify_ajax_request();

		$details = (array) Option::get_option( self::DETAILS_OPTION );
		$uuid    = Helper::get_args_option( 'uuid', $details, '' );

		if ( empty( $uuid ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'No staging migration in progress.', 'instawp-connect' ) ) );
		}

		$response = Curl::do_curl( 'migrations/' . $uuid . '/cancel' );
		$code     = (int) Helper::get_args_option( 'code', $response, 0 );

		// Anything other than success or an already-terminal 422 leaves the run alone: the migration is
		// still live, and deleting instamigrate under it would break the run we failed to stop.
		if ( empty( $response['success'] ) && 422 !== $code ) {
			wp_send_json_error(
				array(
					'message' => Helper::get_args_option( 'message', $response, esc_html__( 'Could not cancel the migration.', 'instawp-connect' ) ),
				)
			);
		}

		// The run is terminal now, so its instamigrate is serving nothing.
		self::cleanup_instamigrate();

		// No payload. The caller does not branch on this -- the watcher is already polling and owns
		// what the screen shows, so anything returned here would be a second source of truth for a
		// question the next poll answers correctly three seconds later.
		wp_send_json_success();
	}

	/**
	 * admin_init: retire instamigrate once its migration can no longer use it.
	 *
	 * Two arms, cheapest first, because this runs on EVERY wp-admin request:
	 *
	 *   past CLEANUP_DEADLINE  -> cancel the run and delete, WITHOUT asking client-app. Past two days
	 *                             the status cannot change the outcome, so spending an HTTP call to
	 *                             reach the same answer is waste.
	 *   past STATUS_CHECK_INTERVAL -> ask client-app. Terminal, delete. Anything else -- including an
	 *                             error or no reply -- leave it and look again later. Nothing is
	 *                             deleted on a guess.
	 *
	 * Gated on delete_plugins rather than manage_options: this ends in delete_plugins(), and the two
	 * are held by different people on multisite. It is also how WP routes DISALLOW_FILE_MODS, which
	 * many managed hosts set -- skipping the capability would skip the host's policy with it.
	 */
	public function maybe_cleanup_instamigrate() {
		/*
		 * NOTHING escapes this method.
		 *
		 * It is on admin_init, so it runs on EVERY wp-admin request -- and it reaches out over HTTP
		 * (Curl::do_curl) and into the filesystem (delete_plugins). A throw from either would be a
		 * white screen on every admin page, for a background tidy-up the admin did not ask for and
		 * cannot see. Failing quietly and trying again in six hours is always the better trade here.
		 *
		 * Throwable, not Exception: a TypeError or a missing-function Error out of WordPress internals
		 * is exactly the class of failure that would otherwise take the dashboard down.
		 */
		try {
			$this->run_cleanup_check();
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'InstaMigrate cleanup check failed: ' . $e->getMessage() );
		}
	}

	/**
	 * The body of the admin_init check. See maybe_cleanup_instamigrate() for why it is wrapped.
	 */
	private function run_cleanup_check() {
		if ( ! is_user_logged_in() || ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		$details = (array) Option::get_option( self::DETAILS_OPTION );
		$uuid    = Helper::get_args_option( 'uuid', $details, '' );

		if ( empty( $uuid ) ) {
			/*
			 * No run, but we may still have installed instamigrate for one that never started.
			 *
			 * remember_run() only fires after live-import/start succeeds, and every arm below is
			 * gated on that uuid -- so a staging-init that 404s or is refused left the plugin
			 * installed and ACTIVE with no path out at all. ORPHAN_OPTION is exactly that record:
			 * set on a real install, cleared once a migration references it, so a lingering value
			 * means "we installed this and the run never started".
			 *
			 * Same deadline as a live run rather than a second number. There is no migration to
			 * protect here, so it could be shorter -- but a user whose first attempt failed often
			 * retries within minutes, and provision_instamigrate() would then reinstall what we had
			 * just removed.
			 */
			$orphaned_at = (int) Option::get_option( self::ORPHAN_OPTION, 0 );

			if ( $orphaned_at > 0 && ( time() - $orphaned_at ) > self::CLEANUP_DEADLINE ) {
				/*
				 * Gated on the RESULT. cleanup_instamigrate() returns false when delete_plugins() is
				 * unavailable, throws, or hands back a WP_Error -- a read-only mount, DISALLOW_FILE_MODS,
				 * no filesystem credentials. Clearing the flag regardless destroyed the only record
				 * that we installed it, so nothing ever retried and instamigrate stayed on a
				 * customer's production site for good. The uuid arm already gets this right:
				 * instamigrate_removed_at is written only on confirmed removal.
				 */
				if ( self::cleanup_instamigrate() ) {
					Option::delete_option( self::ORPHAN_OPTION );
					Option::delete_option( self::ORPHAN_LOGGED_OPTION );
				}
			}

			return;
		}

		// Already removed, so neither arm has anything to do. Checked before the deadlines because
		// the run record now OUTLIVES the cleanup -- it is kept for the watcher and the resume -- so
		// without this every admin page load would re-run the 6h status call forever.
		if ( ! empty( Helper::get_args_option( 'instamigrate_removed_at', $details, 0 ) ) ) {
			return;
		}

		$started_at = (int) Helper::get_args_option( 'started_at', $details, 0 );

		/*
		 * A missing or zero start time fails CLOSED -- it is not evidence the run is old, and treating
		 * it as epoch would make every such run instantly past the deadline and delete on the next
		 * admin load. resumable_run() takes the same position on the same field.
		 */
		if ( $started_at <= 0 ) {
			return;
		}

		$age = time() - $started_at;

		if ( $age > self::CLEANUP_DEADLINE ) {
			/*
			 * Cancel BEFORE deleting: cancelling tells the agent to stop, and deleting first would
			 * leave it working against a plugin that is no longer there.
			 *
			 * A 422 here is not a failure. It means client-app already considers the run terminal and
			 * we were simply never told -- which is the same situation, so the cleanup proceeds. Only
			 * the deletion is unconditional; the cancel is best-effort.
			 */
			Curl::do_curl( 'migrations/' . $uuid . '/cancel' );

			self::cleanup_instamigrate();

			return;
		}

		$last_checked = (int) Helper::get_args_option( 'cleanup_checked_at', $details, 0 );

		if ( ( time() - $last_checked ) < self::STATUS_CHECK_INTERVAL ) {
			return;
		}

		/*
		 * Stamped BEFORE the call, not after.
		 *
		 * The request below can be slow or fail outright, and a stamp written afterwards is never
		 * reached on those paths -- so every subsequent admin page load would re-fire it. Throttling
		 * on the ATTEMPT is what makes this once per six hours rather than once per request whenever
		 * client-app is unwell.
		 */
		$details['cleanup_checked_at'] = time();

		Option::update_option( self::DETAILS_OPTION, $details, false );

		// An unreadable status comes back as '' and is never terminal, so a client-app outage leaves
		// the plugin in place until the next check rather than deleting on silence.
		if ( self::is_terminal_status( self::fetch_run_status( $uuid ) ) ) {
			self::cleanup_instamigrate();
		}
	}

	/**
	 * Remove instamigrate from THIS site, and forget the run that needed it.
	 *
	 * We install instamigrate to serve one migration; once that migration can no longer progress
	 * there is nothing left for it to do, and leaving an active migration agent on a customer's
	 * production site is not a neutral default.
	 *
	 * Deletes whoever installed it -- a deliberate decision, not an oversight. provision_instamigrate()
	 * takes care NOT to overwrite a pre-existing copy; this does not extend that courtesy, so a
	 * customer who had instamigrate before a staging run will not have it afterwards.
	 *
	 * @return bool whether the plugin is gone from disk when this returns.
	 */
	public static function cleanup_instamigrate() {
		$plugin_file = WP_PLUGIN_DIR . '/instamigrate/insta-migrate.php';

		/*
		 * The run record is LEFT ALONE. An earlier revision deleted it here, which broke two things:
		 *
		 *  - staging_status() reads the uuid from it, so once it was gone the watcher's next poll
		 *    answered "No staging migration in progress", and five of those (15 seconds) painted
		 *    "Migration Failed" over a migration that had just SUCCEEDED. client-app's completion
		 *    push races a 3s poll, so that was the normal ending, not a corner case.
		 *  - it also removed the only state resumable_run() and the 48h arm read, so nothing could
		 *    retry a delete that had failed -- while the REST handler's docblock claimed admin_init
		 *    would.
		 *
		 * What retires the run is instamigrate_removed_at below, written only when the files are
		 * actually gone.
		 */
		if ( ! file_exists( $plugin_file ) ) {
			self::mark_instamigrate_removed();

			return true;
		}

		/*
		 * `delete_plugins`, and ONLY when a user is driving this.
		 *
		 * Two callers reach here, and they need opposite answers:
		 *
		 *  - The REST push from client-app is authenticated by API key. validate_api_request() never
		 *    calls wp_set_current_user(), so there is no WP user at all -- is_user_logged_in() is
		 *    false and cleanup proceeds. That is the path that MUST always run: the migration is over
		 *    and the agent has to come off regardless of who happens to be logged in.
		 *  - staging_status() and staging_cancel() arrive over admin-ajax, where verify_ajax_request()
		 *    has checked a nonce and manage_options. That is the right check for "may configure
		 *    InstaWP" but not for "may remove files from this filesystem", so the capability matching
		 *    the side effect is checked here.
		 *
		 * On single-site WP an Administrator holds both and nothing changes. On MULTISITE a subsite
		 * Administrator holds manage_options but NOT delete_plugins, and delete_plugins is also how WP
		 * enforces DISALLOW_FILE_MODS -- where delete_plugins() would fail anyway, so this only turns
		 * a silent failure into an early return.
		 *
		 * Mirrors provision_instamigrate()'s install_plugins check on the way in, and the identical
		 * gate run_cleanup_check() already applies on the admin_init path.
		 */
		if ( is_user_logged_in() && ! current_user_can( 'delete_plugins' ) ) {
			return false;
		}

		/*
		 * BOTH includes, every time.
		 *
		 * delete_plugins() lives in wp-admin/includes/plugin.php and needs the filesystem API from
		 * file.php. Neither is loaded in a REST request, which is exactly how the client-app
		 * notification arrives -- so relying on the admin bootstrap would work on the admin_init path
		 * and fatal on the push path. provision_instamigrate() already guards plugin.php the same way.
		 */
		if ( ! function_exists( 'delete_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) && file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! function_exists( 'delete_plugins' ) ) {
			Helper::add_error_log( 'InstaMigrate cleanup: delete_plugins() unavailable' );

			return false;
		}

		// Deactivate before deleting. delete_plugins() removes the files either way, but an entry left
		// in active_plugins for a directory that no longer exists is what produces "plugin file does
		// not exist" on the next admin load.
		// Same reasoning as the delete below: deactivate_plugins() fires deactivation hooks, and a
		// fatal in somebody else's hook must not become our caller's fatal.
		try {
			if ( is_plugin_active( 'instamigrate/insta-migrate.php' ) ) {
				deactivate_plugins( 'instamigrate/insta-migrate.php', true );
			}
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'InstaMigrate cleanup: deactivate threw - ' . $e->getMessage() );
		}

		/*
		 * delete_plugins() touches the filesystem through WP_Filesystem, which can fail in ways that
		 * throw rather than return WP_Error -- no credentials, a read-only mount, an unwritable
		 * plugins directory. Every caller of this method is a background one (admin_init, a REST
		 * notification, the cancel handler), so a throw here would surface as a white screen or a
		 * 500 on work the user is not waiting for.
		 */
		try {
			$deleted = delete_plugins( array( 'instamigrate/insta-migrate.php' ) );
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'InstaMigrate cleanup: delete threw - ' . $e->getMessage() );

			return false;
		}

		if ( is_wp_error( $deleted ) || false === $deleted ) {
			Helper::add_error_log(
				'InstaMigrate cleanup: delete failed - ' . ( is_wp_error( $deleted ) ? $deleted->get_error_message() : 'unknown' )
			);

			return false;
		}

		self::mark_instamigrate_removed();

		return true;
	}

	/**
	 * Make a typed site name into one start() will accept.
	 *
	 * V3 did this (class-instawp-ajax.php:401-403) and V4 did not, so a name as ordinary as
	 * "My Site" -- fine under V3 -- reached start()'s `[a-zA-Z0-9-]` rule and 422'd. The input carries
	 * only maxlength, no pattern, so nothing stops a space being typed. Worse, it failed LATE: the
	 * name is not checked by staging-init, so instamigrate was already installed and a
	 * migration_imports row already created before start() refused it.
	 *
	 * Deliberately STRICTER than V3, which kept underscores ([^a-z0-9-_]). client-app's rule has no
	 * underscore, so preserving it would reproduce the same late 422 for `acme_staging`.
	 *
	 * Trailing and repeated dashes are collapsed: "My  Site!" would otherwise become "my--site-", and
	 * a trailing dash is a poor subdomain even where the rule allows it.
	 *
	 * @param string $site_name The name as typed.
	 *
	 * @return string A name matching [a-z0-9-], or '' when nothing usable is left.
	 */
	private static function normalise_site_name( $site_name ) {
		$site_name = strtolower( trim( (string) $site_name ) );
		$site_name = preg_replace( '/[^a-z0-9-]/', '-', $site_name );
		$site_name = preg_replace( '/-+/', '-', $site_name );

		return trim( (string) $site_name, '-' );
	}

	/**
	 * Is this uuid the run we are currently holding?
	 *
	 * Public because the REST handler needs it and the run details are deliberately not exposed --
	 * they carry the agent URL.
	 *
	 * An EMPTY stored uuid answers false: with no run of our own, a notification naming one cannot be
	 * about us.
	 */
	public static function is_current_run( $uuid ) {
		$details = (array) Option::get_option( self::DETAILS_OPTION );
		$stored  = (string) Helper::get_args_option( 'uuid', $details, '' );

		return '' !== $stored && $stored === (string) $uuid;
	}

	/**
	 * Ask client-app for a run's current status.
	 *
	 * The one implementation of a request that was previously duplicated in run_cleanup_check() and
	 * staging_status(). Returns '' when the status cannot be read -- an unreachable server, a non-2xx,
	 * a body without the field -- and NEVER a guess. '' is not a terminal status, so every caller that
	 * tests the result with is_terminal_status() fails closed for free.
	 *
	 * @param string $uuid The migration_imports uuid.
	 *
	 * @return string The status, or '' when unknown.
	 */
	public static function fetch_run_status( $uuid ) {
		/*
		 * Throwable, not Exception, and caught HERE rather than at each caller. This is reached from
		 * admin_init, the 3s poll, a daily scheduled job, the heartbeat, WP-CLI and a REST handler; a
		 * TypeError out of the HTTP layer would otherwise take down whichever of those happened to be
		 * running. Catching it once, at the source, means every caller fails closed for free: a throw
		 * is "unknown", and unknown is never terminal.
		 */
		try {
			$response = Curl::do_curl( 'migrations/' . $uuid . '/status', array(), array(), 'GET' );

			if ( empty( $response['success'] ) ) {
				return '';
			}

			return (string) Helper::get_args_option( 'status', Helper::get_args_option( 'data', $response, array() ), '' );
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'Migration status check failed: ' . $e->getMessage() );

			return '';
		}
	}

	/**
	 * Is this a status after which the source may be cleaned up?
	 *
	 * @param string $status A status as reported by client-app.
	 *
	 * @return bool
	 */
	public static function is_terminal_status( $status ) {
		return in_array( (string) $status, self::TERMINAL_STATUSES, true );
	}

	/**
	 * Has the run this site is holding ended, according to client-app RIGHT NOW?
	 *
	 * The gate every AUTOMATIC deletion goes through before touching either the instamigrate plugin
	 * or the run record. Not the user's own Cancel: that follows a confirmation box, and the
	 * confirmation is the decision.
	 *
	 * Fails closed by construction -- see fetch_run_status(). No stored run answers true: with
	 * nothing to protect there is nothing to refuse, and the orphan arm has its own deadline.
	 *
	 * @return bool
	 */
	public static function run_has_ended() {
		// Guarded for the same reason as fetch_run_status(), and to the same end: "could not
		// confirm" answers false, and false leaves everything in place.
		try {
			$details = (array) Option::get_option( self::DETAILS_OPTION );
			$uuid    = (string) Helper::get_args_option( 'uuid', $details, '' );

			if ( '' === $uuid ) {
				return true;
			}

			// What the plugin has already SEEN outranks a fresh request. finished_at is stamped by the
			// poll on a terminal status and instamigrate_removed_at on confirmed removal; either one
			// means the run ended, and a run known to have ended must not become "unconfirmed" just
			// because client-app is unreachable at this moment. No round trip when the answer is local.
			if ( ! empty( $details['finished_at'] ) || ! empty( $details['instamigrate_removed_at'] ) ) {
				return true;
			}

			return self::is_terminal_status( self::fetch_run_status( $uuid ) );
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'Could not confirm the migration has ended: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Record that instamigrate is gone, so the deadline arms stop looking at this run.
	 *
	 * A flag on the run rather than deleting the run: the watcher, resumable_run() and the status
	 * poll all still need the uuid, and removing a plugin is not the same event as the migration
	 * ending. Written ONLY after the files are confirmed gone, so a failed delete stays retryable.
	 */
	private static function mark_instamigrate_removed() {
		$details = (array) Option::get_option( self::DETAILS_OPTION );

		if ( empty( Helper::get_args_option( 'uuid', $details, '' ) ) ) {
			return;
		}

		$details['instamigrate_removed_at'] = time();

		/*
		 * Stamp finished_at here too, or the run stays "resumable" for the full window.
		 *
		 * Only staging_status() wrote it, so a cancel, a migration-finished push, or the 48h arm
		 * removed the agent and left the run looking live. resumable_run() then kept reopening
		 * screen 5 on every wp-admin load -- with the screen buttons and Abort hidden -- and
		 * start_run() kept refusing a new run, for up to RESUME_WINDOW. A user who CANCELLED was
		 * locked out of the wizard by the act of cancelling.
		 *
		 * Safe as a general rule: every caller of this method has established the run is over --
		 * a terminal status, an explicit cancel, or a deadline. There is no path that removes the
		 * agent from a run still expected to progress.
		 */
		if ( empty( $details['finished_at'] ) ) {
			$details['finished_at'] = time();
		}

		Option::update_option( self::DETAILS_OPTION, $details, false );
	}

	public static function resumable_run() {
		/*
		 * Answering "no run" is always safe; throwing is not.
		 *
		 * Three template paths call this while RENDERING the migrate screen -- part-create.php stamps
		 * a class from it and part-create-staging.php seeds the link -- so an exception here blanks the
		 * page the user came to use. The worst a false negative costs is a resume that does not
		 * reappear; the watcher and the deadline arms still hold the run.
		 */
		try {
			$details = (array) Option::get_option( self::DETAILS_OPTION );
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'InstaMigrate resume check failed: ' . $e->getMessage() );

			return array();
		}

		if ( empty( Helper::get_args_option( 'uuid', $details, '' ) ) ) {
			return array();
		}

		// A terminal poll stamped it. The record stays for support; it just stops reopening.
		if ( ! empty( Helper::get_args_option( 'finished_at', $details, 0 ) ) ) {
			return array();
		}

		$started_at = (int) Helper::get_args_option( 'started_at', $details, 0 );

		// A missing or zero start time fails CLOSED. It is not evidence the run is recent, and an
		// absent timestamp would otherwise read as "started at the epoch" or as "always resumable"
		// depending on which way the comparison happened to be written.
		if ( $started_at <= 0 || ( time() - $started_at ) > self::RESUME_WINDOW ) {
			return array();
		}

		return $details;
	}

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

		$status  = Helper::get_args_option( 'status', $data, '' );
		$details = (array) $details;
		$dirty   = false;

		if ( ! empty( $agent_url ) && $agent_url !== Helper::get_args_option( 'agent_url', $details, '' ) ) {
			$details['agent_url'] = $agent_url;
			$dirty                = true;
		}

		/*
		 * Stamp the run finished so resumable_run() stops reopening it.
		 *
		 * Without this the ONLY thing retiring a completed run is RESUME_WINDOW, so for up to 12
		 * hours after a migration finished every wp-admin page load would reopen "Creating
		 * Staging", poll once, and correct itself — a flash of a migration the customer already
		 * saw finish. The window then does what it is actually for: retiring runs whose outcome we
		 * never observed because the tab was closed first.
		 */
		if ( self::is_terminal_status( $status ) && empty( $details['finished_at'] ) ) {
			$details['finished_at'] = time();
			$dirty                  = true;
		}

		if ( $dirty ) {
			Option::update_option( self::DETAILS_OPTION, $details, false );
		}

		/*
		 * Retire the agent here too -- this is the one path that fires while the user is WATCHING.
		 *
		 * client-app pushes v1/migration-finished on every terminal outcome, but that is an outbound
		 * call to a customer's site and it can simply not arrive. Without this, a failed push left
		 * instamigrate installed and active on the source for up to STATUS_CHECK_INTERVAL, and only
		 * then if an admin holding delete_plugins happened to load wp-admin.
		 *
		 * AFTER the option write, so finished_at is persisted even when the delete fails, and gated
		 * on the same terminal check rather than on $dirty -- a second poll arriving after the first
		 * already stamped it is not dirty, and would otherwise skip the cleanup entirely.
		 * cleanup_instamigrate() is idempotent: it returns early once the files are gone.
		 */
		if ( self::is_terminal_status( $status ) ) {
			// The response below is what tells the screen the run ended. A throw from delete_plugins()
			// -- a read-only mount, missing filesystem credentials -- must not turn that into a 500
			// and leave the user watching a spinner on a migration that has finished.
			try {
				self::cleanup_instamigrate();
			} catch ( \Throwable $e ) {
				Helper::add_error_log( 'InstaMigrate cleanup after terminal poll failed: ' . $e->getMessage() );
			}
		}

		wp_send_json_success(
			array(
				'uuid'      => $uuid,
				'status'    => $status,
				// Carried through so a `failed` status can say WHY. Without it the wizard can only
				// show a generic failure, which is barely better than the silent spinner it used to
				// show.
				'message'   => Helper::get_args_option( 'error_message', $data, '' ),
				'agent_url' => esc_url_raw( $agent_url ),
			)
		);
	}

	/**
	 * Where `wp instawp local push` went.
	 *
	 * Deliberately NOT a WP_Error and deliberately not phrased as a failure. The command has moved
	 * to the standalone InstaWP CLI, which offers the same `instawp local push` — so the reader does
	 * not need to be told something went wrong, they need to be told where it is now and how to get
	 * it. Returned as lines rather than one blob so the caller can render them the way its own
	 * output expects.
	 *
	 * ⚠ __(), not esc_html__(). This goes to a TERMINAL, not to HTML — escaping turned the
	 * placeholder in `instawp local push <name>` into `&lt;name&gt;`, in the one line the reader is
	 * meant to copy and run.
	 *
	 * @return list<string>
	 */
	public static function local_push_moved_notice() {
		return array(
			__( 'Local push now lives in the InstaWP CLI, not in this plugin.', 'instawp-connect' ),
			'',
			__( '  Install:  npm install -g @instawp/cli', 'instawp-connect' ),
			__( '  Then run: instawp local push <name>', 'instawp-connect' ),
			'',
			__( 'It does the same job — creates the destination site and deploys this one to it — and is maintained there. Docs: https://github.com/InstaWP/cli', 'instawp-connect' ),
		);
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
		/*
		 * Every ANTICIPATED failure below returns a WP_Error, which migrate_init() turns into
		 * wp_send_json_error() and the wizard renders in .migration-error. An unanticipated THROW had
		 * no such path, and the UI handles it worse than a plain 500: beforeSend adds `loading`,
		 * `complete` removes only `doing-ajax`, and there is no .fail() handler -- so the screen span
		 * forever with nothing said. A user watching that has no way to tell it from a slow migration.
		 *
		 * Converted to the WP_Error the caller already knows how to show. Throwable, not Exception:
		 * this walks the filesystem to size the site and drives Plugin_Upgrader to install
		 * instamigrate, and a TypeError out of either is not an Exception.
		 *
		 * A throw AFTER provision_instamigrate() leaves instamigrate on the site with no run recorded.
		 * That is the orphan case, and it is covered: the flag is written at install time and
		 * maybe_cleanup_instamigrate() removes an orphan older than CLEANUP_DEADLINE.
		 */
		try {
			return self::start_run( $posted );
		} catch ( \Throwable $e ) {
			Helper::add_error_log( 'Staging V4 run failed: ' . $e->getMessage() );

			// The message is OURS, not the exception's: $e->getMessage() can carry a file path, a query
			// or a truncated response body, and this string is rendered straight into the wizard.
			return new WP_Error(
				'staging_run_failed',
				esc_html__( 'Could not start the staging migration. Please try again.', 'instawp-connect' )
			);
		}
	}

	/**
	 * The body of run(). See run() for why it is wrapped.
	 *
	 * @param array $posted The posted request data.
	 *
	 * @return array|WP_Error
	 */
	private static function start_run( $posted ) {
		/*
		 * IDEMPOTENT ON AN IN-FLIGHT RUN, and this is a safety guard rather than a nicety.
		 *
		 * Nothing else stopped this method starting a SECOND staging migration: it installs
		 * instamigrate, seeds a migration_imports row and creates a destination SITE, so a repeat
		 * call bills a second site and orphans the first run's record when remember_run() overwrites
		 * the option. Reachable three ways -- a double-click on Create Staging, a second wp-admin
		 * tab, and (until the fix that accompanies this) the page-load resume, which re-entered
		 * screen 5 by triggering the change handler that calls migrate_init().
		 *
		 * Returning the EXISTING run rather than an error is deliberate: every caller wants to end
		 * up watching the live migration, and that is exactly what the returned uuid does.
		 */
		$in_flight = self::resumable_run();

		if ( ! empty( $in_flight ) ) {
			return array(
				'engine'     => 'v4',
				'uuid'       => Helper::get_args_option( 'uuid', $in_flight, '' ),
				'started_at' => (int) Helper::get_args_option( 'started_at', $in_flight, time() ),
				'message'    => esc_html__( 'A staging migration is already in progress.', 'instawp-connect' ),
			);
		}

		$connect_id = instawp_get_connect_id();

		if ( empty( $connect_id ) ) {
			return new WP_Error( 'not_connected', esc_html__( 'This site is not connected to InstaWP.', 'instawp-connect' ) );
		}

		$migrate_settings = InstaWP_Tools::get_migrate_settings( $posted );
		$plan_id          = (int) Helper::get_args_option( 'plan_id', $migrate_settings, 0 );

		/*
		 * The subdomain prefix the user typed (part-create-staging.php:681,
		 * migrate_settings[site_name]). It was collected and then never read: neither the
		 * staging-init payload nor $start_args carried it, so every plugin-created staging site got
		 * an auto-generated name however carefully the user named it.
		 *
		 * Trimmed, not validated. start() applies min:3 / max:30 / [a-zA-Z0-9-] and a SafeSiteName
		 * uniqueness rule, and duplicating any of that here would only let the two disagree.
		 */
		$site_name = self::normalise_site_name( Helper::get_args_option( 'site_name', $migrate_settings, '' ) );

		/*
		 * Never ship OUR OWN plugin to the destination.
		 *
		 * The destination is given a fresh instawp-connect by client-app's post-migration repair,
		 * which installs it after deleting the identity options the migrated wp_options carried
		 * over. Sending the source's copy as well only creates a window: plugin code present on the
		 * destination can run -- and phone home holding the PARENT's inherited credentials -- in the
		 * gap between the migration finishing and the repair running. That is how a staging site
		 * came to overwrite its parent's connect record.
		 *
		 * Root-relative on purpose: build_exclude() expects paths under the wp-content directory
		 * NAME and rewrites them relative to it, so this must be composed from content_rel() rather
		 * than hardcoding 'wp-content' -- the two disagree on Bedrock and anywhere WP_CONTENT_DIR is
		 * renamed.
		 *
		 * Excluding it also removes it from the SIZE, since total_size_mb() measures build_exclude()'s
		 * output. That is correct: we do not transmit it, so it must not be charged against the plan.
		 */
		$excluded_paths   = (array) Helper::get_args_option( 'excluded_paths', $migrate_settings, array() );
		$excluded_paths[] = self::content_rel() . '/plugins/' . INSTAWP_PLUGIN_SLUG;

		$migrate_settings['excluded_paths'] = $excluded_paths;

		// Order matters: the exclusions must be built BEFORE the site is sized, because the size is
		// computed against what we transmit rather than what the user selected. See total_size_mb().
		$exclude = self::build_exclude( $migrate_settings );

		/*
		 * Sized against what we actually TRANSMIT, so this is larger than the plan picker's number
		 * — the picker also subtracts root-level exclusions we do not send. Safe direction, but a
		 * user on a plan boundary can pass the picker and still be told to size up.
		 *
		 * Do not "fix" that by passing the raw settings here; the picker is the side to change.
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
			// Recorded on the row as part of the source analysis. start() below is what actually
			// applies it, but sending it here keeps the row a faithful record of the request.
			'site_name'         => '' === $site_name ? null : $site_name,
			'exclude'           => $exclude,
		);

		// NOTE: the legacy disk allowance is deliberately NOT sent. client-app derives it itself from
		// planAllow/planUsed at migration-start time — a quota supplied by the caller could be
		// inflated, and it is exactly the kind of number the server must not take on trust.

		$response = Curl::do_curl( 'migrate-v4/staging-init', $payload );

		if ( empty( $response['success'] ) ) {
			/*
			 * The CODE matters, and was being discarded. A 404 here means client-app has not
			 * deployed staging-init yet — a release-ordering fault, not anything the user did — and
			 * it looks identical to a plan or quota rejection unless the code is recorded. Whoever
			 * reads this log on release day needs to be able to tell them apart at a glance.
			 */
			$code = (int) Helper::get_args_option( 'code', $response, 0 );

			self::log_orphaned_instamigrate(
				404 === $code || 501 === $code
					? 'staging-init not available on client-app (HTTP ' . $code . ') — deploy ordering'
					: 'staging-init refused (HTTP ' . $code . ')'
			);

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

		/*
		 * THIS is the one that takes effect: SiteImportLiveController::start() reads site_name from
		 * the REQUEST, and -- unlike wp_version and php_version two lines below it there -- does not
		 * fall back to the stored source analysis. Omitted when empty so start()'s `nullable` rule
		 * is satisfied rather than being handed an empty string to reject.
		 */
		if ( '' !== $site_name ) {
			$start_args['site_name'] = $site_name;
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
		$plugin_file  = WP_PLUGIN_DIR . '/instamigrate/insta-migrate.php';
		$pre_existing = file_exists( $plugin_file );

		// is_plugin_active() and activate_plugin() are wp-admin only; admin-ajax does not load them.
		if ( ! function_exists( 'is_plugin_active' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		/*
		 * `install_plugins`, NOT just `manage_options`.
		 *
		 * verify_ajax_request() gates these handlers on manage_options, which is the right check for
		 * "may configure InstaWP" but NOT for "may put files on this filesystem". Installer::install()
		 * runs Plugin_Upgrader with overwrite_package and then activate_plugin(), and checks no
		 * capability of its own — so without this an AJAX endpoint installs a plugin on the strength
		 * of manage_options alone.
		 *
		 * On single-site WP an Administrator holds both, so nothing changes. On MULTISITE a subsite
		 * Administrator holds manage_options but NOT install_plugins (WP strips it from
		 * non-super-admins), so this is the difference between a subsite admin writing to the
		 * filesystem and not. It is also how WP enforces DISALLOW_FILE_MODS, which many managed hosts
		 * set: that is mapped through install_plugins, so skipping the capability skips the host's
		 * policy too.
		 *
		 * Same CWE-862 class as the v0.1.2.5 incident, one layer further in: the nonce and
		 * manage_options are present, but the capability that actually matches the side effect is not.
		 */
		if ( ! $pre_existing && ! current_user_can( 'install_plugins' ) ) {
			return new WP_Error(
				'cannot_install_plugins',
				esc_html__( 'You do not have permission to install plugins on this site.', 'instawp-connect' )
			);
		}

		/*
		 * PRESENCE ON DISK, not class_exists().
		 *
		 * class_exists('\InstaMigrate') conflates "not installed" with "installed but DEACTIVATED".
		 * On a site where the user had deliberately deactivated instamigrate, the class is not
		 * loaded, so this read false, installInstaMigrate() saw is_plugin_active() false, and the
		 * Installer ran with overwrite_package => true — OVERWRITING their copy and re-activating a
		 * plugin they had turned off. The wrong breadcrumb was the lesser half of that.
		 *
		 * The file either exists or it does not, whatever this request has loaded.
		 */

		/*
		 * ALREADY ON DISK BUT DEACTIVATED: activate it, do NOT reinstall.
		 *
		 * QA FAIL, and the defect the previous commit only half fixed. $pre_existing was consulted
		 * by the orphan-marking branch alone, while installInstaMigrate() was still called
		 * unconditionally — and it gates internally on is_plugin_active(), so a plugin the customer
		 * had deliberately DEACTIVATED went through Installer::install() with
		 * overwrite_package => true. Measured: their copy's md5 and mtime both changed and
		 * active_plugins gained the entry. We overwrote a file they own and switched it back on.
		 *
		 * Activation still needs its own capability: install_plugins (checked above) is not
		 * activate_plugins, and on multisite they are held by different people.
		 */
		if ( $pre_existing && ! is_plugin_active( 'instamigrate/insta-migrate.php' ) ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return new WP_Error(
					'cannot_activate_plugins',
					esc_html__( 'InstaMigrate is installed but not active, and you do not have permission to activate plugins on this site.', 'instawp-connect' )
				);
			}

			$activated = activate_plugin( 'instamigrate/insta-migrate.php' );

			if ( is_wp_error( $activated ) ) {
				return $activated;
			}
		}

		/*
		 * Safe to call unconditionally now: if we activated above, is_plugin_active() is true and
		 * installInstaMigrate() skips the Installer entirely, so it cannot reach overwrite_package.
		 */
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
		 *
		 * And marked on PRESENCE, not on activation: an install whose activate_plugin() then failed
		 * leaves the files on the customer's site with no active_plugins entry — which is exactly
		 * "we put files there and nothing started", the case the flag exists for, and the one an
		 * activation-based check misses.
		 */
		if ( ! $pre_existing && file_exists( $plugin_file ) ) {
			self::mark_instamigrate_orphaned();
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
		Option::delete_option( self::ORPHAN_OPTION );
		Option::delete_option( self::ORPHAN_LOGGED_OPTION );

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
	 * Set on a real install, cleared by remember_run() once a migration references it, so a
	 * lingering value means exactly "we installed this and the run never started".
	 *
	 * READ by maybe_cleanup_instamigrate(), which removes the plugin once the flag is older than
	 * CLEANUP_DEADLINE. It was a diagnostic breadcrumb until then -- a staging-init that failed left
	 * instamigrate installed and active with no path out, because every other cleanup arm is gated
	 * on a run uuid that a failed init never produced.
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
		Option::update_option( self::ORPHAN_OPTION, time(), false );
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
		/*
		 * Every caller passes a literal today, but this string is CONCATENATED into the error log:
		 * a non-string reason would emit "Array" (with a PHP notice) or fatal on an object with no
		 * __toString, and it would do so on the failure path — the one place the log is the only
		 * record of what happened. Coerce rather than refuse: losing the reason text is a far
		 * smaller loss than losing the line.
		 */
		if ( ! is_string( $reason ) ) {
			$reason = is_scalar( $reason ) ? (string) $reason : 'unspecified';
		}

		if ( empty( Option::get_option( self::ORPHAN_OPTION ) ) ) {
			// We did not install it, so it is not ours to report.
			return;
		}

		if ( ! empty( Option::get_option( self::ORPHAN_LOGGED_OPTION ) ) ) {
			// Already announced for this outstanding install. Says it once, not once per attempt.
			return;
		}

		Helper::add_error_log( 'V4 staging: instamigrate installed but the migration did not start (' . $reason . ')' );

		/*
		 * Suppress the re-logging, never the flag. Nothing uninstalls instamigrate, so after a
		 * failure the site IS still orphaned and the flag must survive to say so — remember_run()
		 * is the only place it is cleared.
		 */
		Option::update_option( self::ORPHAN_LOGGED_OPTION, time(), false );
	}
}

new InstaWP_Staging_V4();

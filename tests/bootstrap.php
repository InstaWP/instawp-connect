<?php
/**
 * Test bootstrap: a small fake WordPress, so the cleanup decisions can be exercised in isolation.
 *
 * Nothing here touches a real WordPress install, database, filesystem outside a per-process temp
 * directory, or network. Every dependency the code under test reaches for -- options, the current
 * user's capabilities, the connect-helpers HTTP client, the plugin deleter -- is an in-memory fake
 * that a test can set up and inspect through IWP_Test_World.
 *
 * The real `includes/functions.php` is NOT loaded: it is large and its load-time surface is wide.
 * instawp_reset_running_migration() is defined here as a spy instead, and the real definition is
 * `function_exists`-guarded, so this is exactly how a plugin override would land in production.
 *
 * Run with a standalone PHPUnit phar; nothing is added to the plugin's committed vendor/.
 */

namespace {

	// ---------------------------------------------------------------------------------------------
	// Constants the code under test reads. ABSPATH points at a directory with no wp-admin files, so
	// the `require_once ABSPATH . 'wp-admin/includes/...'` fallbacks never fire and the fakes below
	// are what the code sees.
	// ---------------------------------------------------------------------------------------------
	$iwp_test_root = sys_get_temp_dir() . '/iwp-connect-tests-' . getmypid();

	define( 'ABSPATH', $iwp_test_root . '/abspath/' );
	define( 'WP_CONTENT_DIR', $iwp_test_root . '/wp-content' );
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'INSTAWP_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	define( 'INSTAWP_PLUGIN_VERSION', 'test' );
	define( 'INSTAWP_PLUGIN_SLUG', 'instawp-connect' );
	define( 'INSTAWP_DEFAULT_BACKUP_DIR', 'instawpbackups' );

	/**
	 * Everything the fakes read and record. Reset between tests.
	 */
	final class IWP_Test_World {
		public static $options         = array();
		public static $logged_in       = false;
		public static $caps            = array();
		public static $curl_responder  = null;
		public static $curl_calls      = array();
		public static $delete_result   = true;
		public static $deleted_plugins = array();
		public static $reset_calls     = array();
		public static $log             = array();
		public static $install_succeeds = true;
		public static $install_calls    = 0;
		public static $hooks           = array();
		public static $fired           = array();

		public static function reset() {
			self::$options         = array();
			self::$logged_in       = false;
			self::$caps            = array();
			self::$curl_responder  = null;
			self::$curl_calls      = array();
			self::$delete_result   = true;
			self::$deleted_plugins = array();
			self::$reset_calls     = array();
			self::$log             = array();
			self::$install_succeeds = true;
			self::$install_calls    = 0;
			self::$fired           = array();

			self::rmdir_recursive( WP_PLUGIN_DIR );
			mkdir( WP_PLUGIN_DIR, 0777, true );
		}

		public static function instamigrate_file() {
			return WP_PLUGIN_DIR . '/instamigrate/insta-migrate.php';
		}

		public static function install_instamigrate() {
			mkdir( dirname( self::instamigrate_file() ), 0777, true );
			file_put_contents( self::instamigrate_file(), "<?php\n" );
		}

		public static function instamigrate_installed() {
			return file_exists( self::instamigrate_file() );
		}

		/**
		 * Shorthand for "client-app answers the status endpoint with this status".
		 */
		public static function client_app_reports( $status ) {
			self::$curl_responder = function ( $endpoint ) use ( $status ) {
				return array(
					'success' => true,
					'message' => 'Ok',
					'data'    => array( 'status' => $status, 'uuid' => 'x' ),
					'code'    => 200,
				);
			};
		}

		/**
		 * Shorthand for "client-app cannot be read" -- 401, 404, outage, whatever.
		 */
		public static function client_app_unreadable() {
			self::$curl_responder = function () {
				return array(
					'success' => false,
					'message' => 'Not found',
					'data'    => array(),
					'code'    => 404,
				);
			};
		}

		public static function curl_calls_to( $needle ) {
			return array_values(
				array_filter(
					self::$curl_calls,
					function ( $c ) use ( $needle ) {
						return false !== strpos( $c['endpoint'], $needle );
					}
				)
			);
		}

		private static function rmdir_recursive( $dir ) {
			if ( ! is_dir( $dir ) ) {
				return;
			}

			foreach ( scandir( $dir ) as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}

				$path = $dir . '/' . $entry;
				is_dir( $path ) ? self::rmdir_recursive( $path ) : unlink( $path );
			}

			rmdir( $dir );
		}
	}

	// ---------------------------------------------------------------------------------------------
	// WordPress functions the code under test calls.
	// ---------------------------------------------------------------------------------------------
	function get_option( $key, $default = false ) {
		return array_key_exists( $key, IWP_Test_World::$options ) ? IWP_Test_World::$options[ $key ] : $default;
	}

	/**
	 * Faithful to WordPress where it matters here: a first write fires add_option_{$key}, a changed
	 * write fires update_option_{$key}, and an UNCHANGED write fires nothing and returns false. The
	 * code under test reacts to the record through these hooks, so their firing rules are the
	 * behaviour being tested, not incidental.
	 */
	function update_option( $key, $value, $autoload = null ) {
		if ( ! array_key_exists( $key, IWP_Test_World::$options ) ) {
			IWP_Test_World::$options[ $key ] = $value;
			do_action( 'add_option_' . $key, $key, $value );

			return true;
		}

		$old = IWP_Test_World::$options[ $key ];

		if ( $old === $value ) {
			return false;
		}

		IWP_Test_World::$options[ $key ] = $value;
		do_action( 'update_option_' . $key, $old, $value, $key );

		return true;
	}

	function delete_option( $key ) {
		if ( ! array_key_exists( $key, IWP_Test_World::$options ) ) {
			return false;
		}

		unset( IWP_Test_World::$options[ $key ] );
		do_action( 'delete_option_' . $key, $key );

		return true;
	}

	/**
	 * A minimal hook registry. Deduplicates on the callable exactly as WordPress does, so the class
	 * registering its static handlers on every construction -- once in production, once per test
	 * instance here -- yields one handler, not one per instance.
	 */
	function add_action( $tag, $callable, $priority = 10, $accepted_args = 1 ) {
		if ( is_array( $callable ) ) {
			$id = ( is_object( $callable[0] ) ? spl_object_hash( $callable[0] ) : $callable[0] ) . '::' . $callable[1];
		} else {
			$id = is_object( $callable ) ? spl_object_hash( $callable ) : (string) $callable;
		}

		IWP_Test_World::$hooks[ $tag ][ $id ] = array( $callable, $accepted_args );
	}

	function add_filter( $tag, $callable, $priority = 10, $accepted_args = 1 ) {
		add_action( $tag, $callable, $priority, $accepted_args );
	}

	function do_action( $tag, ...$args ) {
		IWP_Test_World::$fired[] = $tag;

		foreach ( isset( IWP_Test_World::$hooks[ $tag ] ) ? IWP_Test_World::$hooks[ $tag ] : array() as $entry ) {
			call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) );
		}
	}

	function is_user_logged_in() {
		return IWP_Test_World::$logged_in;
	}

	function current_user_can( $cap ) {
		return in_array( $cap, IWP_Test_World::$caps, true );
	}

	function is_plugin_active( $plugin ) {
		return IWP_Test_World::instamigrate_installed();
	}

	function deactivate_plugins( $plugins, $silent = false ) {}

	function activate_plugin( $plugin ) {
		return null;
	}

	function request_filesystem_credentials() {
		return true;
	}

	/**
	 * The fake deleter. Records every call, honours a configured failure, and otherwise removes
	 * the file exactly as WordPress would.
	 */
	function delete_plugins( array $plugins ) {
		IWP_Test_World::$deleted_plugins[] = $plugins;

		if ( true !== IWP_Test_World::$delete_result ) {
			return IWP_Test_World::$delete_result;
		}

		$file = IWP_Test_World::instamigrate_file();

		if ( file_exists( $file ) ) {
			unlink( $file );
			rmdir( dirname( $file ) );
		}

		return true;
	}

	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}

	function esc_html__( $text, $domain = null ) {
		return $text;
	}

	function __( $text, $domain = null ) {
		return $text;
	}

	function esc_url_raw( $url ) {
		return $url;
	}

	function sanitize_text_field( $text ) {
		return $text;
	}

	function wp_json_encode( $data ) {
		return json_encode( $data );
	}

	/**
	 * wp_send_json_*() end the request. The fakes throw instead, so a test can assert on what
	 * would have been sent and execution stops where WordPress would have stopped it.
	 */
	class IWP_Json_Exit extends Exception {
		public $success;
		public $payload;

		public function __construct( $success, $payload ) {
			parent::__construct( 'wp_send_json' );
			$this->success = $success;
			$this->payload = $payload;
		}
	}

	function wp_send_json_success( $data = null ) {
		throw new IWP_Json_Exit( true, $data );
	}

	function wp_send_json_error( $data = null ) {
		throw new IWP_Json_Exit( false, $data );
	}

	function rest_ensure_response( $response ) {
		return $response;
	}

	class WP_REST_Request {
		private $params;

		public function __construct( array $params = array() ) {
			$this->params = $params;
		}

		public function get_param( $key ) {
			return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
		}
	}

	class WP_REST_Response {
		public $data;
		public $status = 200;

		public function __construct( $data = null ) {
			$this->data = $data;
		}

		public function set_status( $status ) {
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}
	}

	class InstaWP_Tools {
		public static function verify_ajax_request() {}
	}

	/**
	 * SPY for the reset. The real one is `function_exists`-guarded and lives in functions.php, which
	 * is deliberately not loaded. What the tests need to know is whether it was CALLED, and with
	 * what; what it then does to a real filesystem is not under test here.
	 */
	function instawp_reset_running_migration( $reset_type = 'soft', $abort_forcefully = false, $clear_events = false, $disconnect_connect = false ) {
		IWP_Test_World::$reset_calls[] = func_get_args();

		delete_option( 'instawp_migration_details' );
		delete_option( 'instawp_staging_v4_details' );

		return true;
	}
}

// -------------------------------------------------------------------------------------------------
// The connect-helpers classes, faked. Defined before anything could autoload the real ones, so
// these are what `use InstaWP\Connect\Helpers\...` resolves to.
// -------------------------------------------------------------------------------------------------
namespace InstaWP\Connect\Helpers {

	class Option {
		public static function get_option( $key, $default = false ) {
			return \get_option( $key, $default );
		}

		public static function update_option( $key, $value, $autoload = null ) {
			return \update_option( $key, $value, $autoload );
		}

		public static function delete_option( $key ) {
			return \delete_option( $key );
		}
	}

	class Helper {
		public static function get_args_option( $key = '', $args = array(), $default = '' ) {
			$args = is_array( $args ) ? $args : array();

			return isset( $args[ $key ] ) ? $args[ $key ] : $default;
		}

		public static function add_error_log( $payload, $th = null ) {
			\IWP_Test_World::$log[] = $payload;
		}

		/** The installer, faked: puts the file on the "site" unless told to fail. */
		public static function installInstaMigrate() {
			\IWP_Test_World::$install_calls++;

			if ( ! \IWP_Test_World::$install_succeeds ) {
				return array( 'success' => false, 'message' => 'simulated install failure' );
			}

			if ( ! \IWP_Test_World::instamigrate_installed() ) {
				\IWP_Test_World::install_instamigrate();
			}

			return array( 'success' => true );
		}

		public static function getInstaMigrateApiKey() {
			return array( 'success' => true, 'data' => array( 'insta_mig_key' => 'test-key' ) );
		}
	}

	class Curl {
		public static function do_curl( $endpoint, $body = array(), $headers = array(), $method = 'POST' ) {
			\IWP_Test_World::$curl_calls[] = compact( 'endpoint', 'body', 'headers', 'method' );

			$responder = \IWP_Test_World::$curl_responder;

			if ( null === $responder ) {
				return array(
					'success' => false,
					'message' => 'no responder configured',
					'data'    => array(),
					'code'    => 0,
				);
			}

			return $responder( $endpoint, $body, $headers, $method );
		}
	}
}

namespace {
	\IWP_Test_World::reset();

	require_once INSTAWP_PLUGIN_DIR . 'includes/class-instawp-staging-v4.php';
	require_once INSTAWP_PLUGIN_DIR . 'includes/class-instawp.php';
	require_once INSTAWP_PLUGIN_DIR . 'includes/apis/class-instawp-rest-api.php';
}

<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;
use InstaWP\Connect\Helpers\WPConfig;

defined( 'ABSPATH' ) || exit;

class instaWP {

	protected $plugin_name;

	protected $version;

	public $admin;

	public $is_staging = false;

	public $is_connected = false;

	public $is_on_local = false;

	public $is_parent_on_local = false;

	public $has_unsupported_plugins = false;

	public $can_bundle = false;

	public $activity_log_enabled = false;

	public $api_key = null;

	public $connect_id = null;

	public $tools = null;

	public function __construct() {

		$this->load_dependencies();

		$this->version     = INSTAWP_PLUGIN_VERSION;
		$this->plugin_name = INSTAWP_PLUGIN_SLUG;

		$this->connect_id              = instawp_get_connect_id();
		$this->api_key                 = Helper::get_api_key();
		$this->is_connected            = ! empty( $this->api_key );
		$this->is_on_local             = instawp_is_website_on_local();
		$this->is_staging              = (bool) Option::get_option( 'instawp_is_staging', false );
		$this->is_parent_on_local      = (bool) Option::get_option( 'instawp_parent_is_on_local', false );
		$this->has_unsupported_plugins = ! empty( InstaWP_Tools::get_unsupported_active_plugins() );
		$this->can_bundle              = ( class_exists( 'ZipArchive' ) || class_exists( 'PharData' ) );

		if ( is_admin() ) {
			$this->set_locale();
			$this->define_admin_hook();
		}

		add_action( 'init', array( $this, 'register_actions' ), 11 );
		add_action( 'instawp_prepare_large_files_list', array( $this, 'prepare_large_files_list' ) );
		add_action( 'add_option_instawp_max_file_size_allowed', array( $this, 'clear_staging_sites_list' ) );
		add_action( 'update_option_instawp_max_file_size_allowed', array( $this, 'clear_staging_sites_list' ) );
		add_action( 'instawp_clean_migrate_files', array( $this, 'clean_migrate_files' ) );
		add_action( 'add_option_instawp_enable_wp_debug', array( $this, 'toggle_wp_debug' ), 10, 2 );
		add_action( 'update_option_instawp_enable_wp_debug', array( $this, 'toggle_wp_debug' ), 10, 2 );
		add_action( 'add_option_instawp_rm_debug_log', array( $this, 'toggle_wp_debug' ), 10, 2 );
		add_action( 'update_option_instawp_rm_debug_log', array( $this, 'toggle_wp_debug' ), 10, 2 );
	}

	public function toggle_wp_debug( $old_value, $value ) {
		if ( $value === 'on' ) {
			$params = array(
				'WP_DEBUG'         => true,
				'WP_DEBUG_LOG'     => true,
				'WP_DEBUG_DISPLAY' => false,
			);
		} else {
			$params = array(
				'WP_DEBUG'         => false,
				'WP_DEBUG_LOG'     => false,
				'WP_DEBUG_DISPLAY' => false,
			);
		}

		try {
			$wp_config = new WPConfig( $params );
			$wp_config->set();
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
		}
	}

	public function register_actions() {
		if ( ! as_has_scheduled_action( 'instawp_prepare_large_files_list', array(), 'instawp-connect' ) ) {
			as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'instawp_prepare_large_files_list', array(), 'instawp-connect' );
		}

		if ( ! as_has_scheduled_action( 'instawp_clean_migrate_files', array(), 'instawp-connect' ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, 'instawp_clean_migrate_files', array(), 'instawp-connect' );
		}
	}

	public function clean_migrate_files() {

		$migration_details = Option::get_option( 'instawp_migration_details', array() );
		$migrate_id        = Helper::get_args_option( 'migrate_id', $migration_details );
		$migrate_key       = Helper::get_args_option( 'migrate_key', $migration_details );

		if ( empty( $migrate_id ) && empty( $migrate_key ) ) {
			instawp_reset_running_migration();
		}
	}

	public function prepare_large_files_list() {
		$maxbytes = (int) Option::get_option( 'instawp_max_file_size_allowed', INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED );
		$maxbytes = $maxbytes ? $maxbytes : INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED;
		$maxbytes = ( $maxbytes * 1024 * 1024 );
		$path     = ABSPATH;
		$data     = array();

		if ( $path !== '' && file_exists( $path ) && is_readable( $path ) ) {
			try {
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					try {
						if ( $object->getSize() > $maxbytes && strpos( $object->getPath(), 'instawpbackups' ) === false ) {
							$data[] = array(
								'size'          => $object->getSize(),
								'path'          => wp_normalize_path( $object->getPath() ),
								'pathname'      => wp_normalize_path( $object->getPathname() ),
								'realpath'      => wp_normalize_path( $object->getRealPath() ),
								'relative_path' => str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $object->getRealPath() ) ),
							);
						}
					} catch ( Exception $e ) {
						continue;
					}
				}
			} catch ( \Exception $e ) {
				error_log( 'error in prepare_large_files_list: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		set_transient( 'instawp_generate_large_files', true, HOUR_IN_SECONDS );
		Option::update_option( 'instawp_large_files_list', $data );
	}

	public function clear_staging_sites_list() {
		delete_option( 'instawp_large_files_list' );
		do_action( 'instawp_prepare_large_files_list' );
	}

	private function set_locale() {
		load_plugin_textdomain( 'instawp-connect', false, dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/' );
	}

	private function define_admin_hook() {
		$this->admin = new InstaWP_Admin( $this->get_plugin_name(), $this->get_version() );

		// Add Settings link to the plugin
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . 'instawp-connect.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this->admin, 'add_action_links' ) );
		add_filter( 'instawp_add_tab_page', array( $this->admin, 'instawp_add_default_tab_page' ) );
		add_action( 'admin_init', 'instawp_get_source_site_detail', 999 );
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}

	public function get_directory_contents( $dir, $sort_by ) {
		if ( empty( $dir ) || ! is_dir( $dir ) ) {
			return array();
		}

		$files_data = scandir( $dir );
		if ( ! $files_data ) {
			return array();
		}

		$path_to_replace = wp_normalize_path( instawp_get_root_path() . DIRECTORY_SEPARATOR );
		$files           = $folders = array();

		foreach ( $files_data as $value ) {
			$path = rtrim( $dir, '/' ) . DIRECTORY_SEPARATOR . $value;

			if ( empty( $path ) || $value === "." || $value === ".." || ! file_exists( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$normalized_path = wp_normalize_path( $path );

			try {
				if ( ! is_dir( $path ) ) {
					$size    = filesize( $path );
					$files[] = array(
						'name'          => $value,
						'relative_path' => str_replace( $path_to_replace, '', $normalized_path ),
						'full_path'     => $normalized_path,
						'size'          => $size,
						'count'         => 1,
						'type'          => 'file',
					);
				} else {
					$directory_info = $this->get_directory_info( $path );
					$folders[]      = array(
						'name'          => $value,
						'relative_path' => str_replace( $path_to_replace, '', $normalized_path ),
						'full_path'     => $normalized_path,
						'size'          => $directory_info['size'],
						'count'         => $directory_info['count'],
						'type'          => 'folder',
					);
				}
			} catch ( Exception $e ) {
			}
		}

		$files_list = array_merge( $folders, $files );

		if ( $sort_by === 'descending' ) {
			usort( $files_list, function ( $item1, $item2 ) {
				if ( $item1['size'] === $item2['size'] ) {
					return 0;
				}

				return ( $item1['size'] > $item2['size'] ) ? - 1 : 1;
			} );
		} elseif ( $sort_by === 'ascending' ) {
			usort( $files_list, function ( $item1, $item2 ) {
				if ( $item1['size'] === $item2['size'] ) {
					return 0;
				}

				return ( $item1['size'] < $item2['size'] ) ? - 1 : 1;
			} );
		}

		return $files_list;
	}

	public function get_directory_info( $path ) {
		$bytes_total = 0;
		$files_total = 0;
		try {
			if ( $path !== false && $path !== '' && file_exists( $path ) ) {
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					try {
						$bytes_total += $object->getSize();
						++ $files_total;
					} catch ( Exception $e ) {
						continue;
					}
				}
			}
		} catch ( Exception $e ) {
		}

		return array(
			'size'  => $bytes_total,
			'count' => $files_total,
		);
	}

	public function get_directory_size( $path ) {
		$info = $this->get_directory_info( $path );

		return $info['size'];
	}

	public function get_file_size_with_unit( $size, $unit = "", $binary = true ) {
		$base = $binary ? 1024 : 1000;
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$exponents = array( 0, 1, 2, 3 );
		
		// If unit is specified, find its index
		if ( $unit ) {
			$unit_index = array_search( strtoupper( $unit ), $units );
			if ( $unit_index !== false ) {
				$exponent = $exponents[ $unit_index ];
				return number_format( $size / ( $base ** $exponent ), 2 ) . " {$units[$unit_index]}";
			}
		}
		
		// Auto-determine appropriate unit
		$exponent = 0;
		for ( $i = count( $exponents ) - 1; $i >= 0; $i-- ) {
			if ( $size >= ( $base ** $exponents[ $i ] ) ) {
				$exponent = $exponents[ $i ];
				break;
			}
		}
		
		return number_format( $size / ( $base ** $exponent ), 2 ) . " {$units[$exponent]}";
	}

	public function get_current_mode( $data_to_get = '' ) {
		$mode_data = array();

		if ( defined( 'INSTAWP_CONNECT_MODE' ) && ! empty( INSTAWP_CONNECT_MODE ) ) {
			$mode_data['type'] = INSTAWP_CONNECT_MODE;
			$mode_data['name'] = defined( INSTAWP_CONNECT_MODE_NAME ) ? INSTAWP_CONNECT_MODE_NAME : '';
			$mode_data['link'] = defined( INSTAWP_CONNECT_MODE_LINK ) ? INSTAWP_CONNECT_MODE_LINK : '';
			$mode_data['desc'] = defined( INSTAWP_CONNECT_MODE_DESC ) ? INSTAWP_CONNECT_MODE_DESC : '';
			$mode_data['logo'] = defined( INSTAWP_CONNECT_MODE_LOGO ) ? INSTAWP_CONNECT_MODE_LOGO : '';
		}

		if ( ! empty( $data_to_get ) ) {
			return Helper::get_args_option( $data_to_get, $mode_data );
		}

		return $mode_data;
	}


	public static function get_asset_url( $asset_name ) {
		return INSTAWP_PLUGIN_URL . $asset_name;
	}


	public function instawp_check_usage_on_cloud( $migrate_settings = array() ) {
		$total_files_size = InstaWP_Tools::get_total_sizes( 'files', $migrate_settings );
		$total_db_size    = InstaWP_Tools::get_total_sizes( 'db' );
		$plan_id          = Helper::get_args_option( 'plan_id', $migrate_settings, 0 );

		// connects/<connect_id>/usage
		$api_response        = Curl::do_curl( "connects/{$this->connect_id}/usage?plan_id={$plan_id}", array(), array(), 'GET', 'v1' );
		$api_response_status = Helper::get_args_option( 'success', $api_response, false );
		$api_response_data   = Helper::get_args_option( 'data', $api_response, array() );

		// send usage check log before starting the pull
		instawp_send_connect_log( 'usage-check', wp_json_encode( $api_response ) );

		if ( ! $api_response_status ) {
			return array(
				'can_proceed'  => false,
				'connect_id'   => $this->connect_id,
				'api_response' => $api_response,
			);
		}

		$is_legacy = (bool) Helper::get_args_option( 'is_legacy', $api_response_data, false );

		if ( $is_legacy ) {
			$remaining_site       = (int) Helper::get_args_option( 'remaining_site', $api_response_data, '0' );
			$available_disk_space = (int) Helper::get_args_option( 'remaining_disk_space', $api_response_data, '0' );
			$can_proceed          = $remaining_site > 0;
			$issue_for            = 'remaining_site';
			$total_site_size      = round( $total_files_size / 1048576, 2 );

			if ( $can_proceed ) {
				$can_proceed = $total_site_size < $available_disk_space;
				$issue_for   = 'remaining_disk_space';
			}
		} else {
			$has_payment_method = (bool) Helper::get_args_option( 'has_payment_method', $api_response_data, false );
			$free_site_count    = Helper::get_args_option( 'free_site_count', $api_response_data, null );
			$current_plan       = Helper::get_args_option( 'plan', $api_response_data, null );
			$can_proceed        = $has_payment_method === true;
			$issue_for          = 'no_payment_method';
			$total_site_size    = round( ( $total_files_size + $total_db_size ) / 1000000, 2 );

			if ( $can_proceed ) {
				$can_proceed = $current_plan !== null && is_array( $current_plan );
				$issue_for   = 'no_plan_found';
			}

			if ( $can_proceed && $current_plan['name'] === 'free' && $free_site_count !== null ) {
				$can_proceed = intval( $free_site_count ) < 3;
				$issue_for   = 'free_site_limit_exceeded';
			}

			if ( $can_proceed ) {
				$disk_quota = array_filter( $current_plan['features'], function( $feature ) {
					return $feature['feature'] === 'disk_quota';
				} );
				$disk_quota = ! empty( $disk_quota ) ? array_shift( $disk_quota ) : null;
				
				if ( ! empty( $disk_quota ) ) {
					$can_proceed = $total_site_size <= (int) $disk_quota['value'];
					$issue_for   = 'storage_limit_exceeded';
				}
			}
		}

		$api_response_data['required_disk_space'] = $total_site_size;

		return array_merge( array(
			'can_proceed' => $can_proceed,
			'issue_for'   => ( $can_proceed ? '' : $issue_for ),
		), $api_response_data );
	}

	private function load_dependencies() {
		require_once INSTAWP_PLUGIN_DIR . '/admin/class-instawp-admin.php';

		require_once INSTAWP_PLUGIN_DIR . '/migrate/class-instawp-migrate.php';

		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-migrate-log.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-ajax.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-setting.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-database-management.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-tools.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-hooks.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-whitelabel.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-cli.php';
		// require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-updates.php';
		// require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-checksum.php';

		if ( ! defined( 'IWP_PLUGIN_DISABLE_HEARTBEAT' ) || IWP_PLUGIN_DISABLE_HEARTBEAT !== true ) {
			require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-heartbeat.php';
		}

		require_once INSTAWP_PLUGIN_DIR . '/includes/apis/class-instawp-rest-api.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/apis/class-instawp-rest-api-content.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/apis/class-instawp-rest-api-manage.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/apis/class-instawp-rest-api-migration.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/apis/class-instawp-rest-api-woocommerce.php';

		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-db.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-helpers.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-parser.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-ajax.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-apis.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-customize-setting.php';

		$files = array( 'option', 'plugin-theme', 'post', 'term', 'menu', 'user', 'customizer', 'wc' );
		foreach ( $files as $file ) {
			require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-' . sanitize_file_name( $file ) . '.php';
		}

		require_once INSTAWP_PLUGIN_DIR . '/includes/activity-log/class-instawp-activity-log.php';
		
		$this->activity_log_enabled = Option::get_option( 'instawp_activity_log', 'off' ) === 'on';

		if ( $this->activity_log_enabled ) {
			$files = array( 'core', 'posts', 'attachments', 'users', 'menus', 'plugins', 'themes', 'taxonomies', 'widgets' );
			foreach ( $files as $file ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/activity-log/class-instawp-activity-log-' . sanitize_file_name( $file ) . '.php';
			}
		}
	}
}

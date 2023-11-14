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

defined( 'ABSPATH' ) || exit;

class instaWP {

	protected $plugin_name;

	protected $version;

	public $admin;

	public $is_staging = false;

	public $is_connected = false;

	public $is_on_local = false;

	public $has_unsupported_plugins = false;

	public $has_sqlite = false;

	public $can_bundle = false;

	public $api_key = null;

	public $connect_id = null;

	public $tools = null;

	public function __construct() {

		$this->load_dependencies();

		$this->version      = INSTAWP_PLUGIN_VERSION;
		$this->plugin_name  = INSTAWP_PLUGIN_SLUG;
		$this->api_key      = InstaWP_Setting::get_api_key();
		$this->is_connected = ! empty( $this->api_key );
		$this->is_on_local  = instawp_is_website_on_local();
		$this->connect_id   = instawp_get_connect_id();
		$this->is_staging   = (bool) InstaWP_Setting::get_option( 'instawp_is_staging', false );

		$this->tools                   = new InstaWP_Tools();
		$this->has_unsupported_plugins = ! empty( $this->tools::get_unsupported_active_plugins() );
		$this->has_sqlite              = ( extension_loaded( 'sqlite3' ) || extension_loaded( 'pdo_sqlite' ) );
		$this->can_bundle              = ( class_exists( 'ZipArchive' ) || class_exists( 'PharData' ) );

		if ( is_admin() ) {
			$this->set_locale();
			$this->define_admin_hook();
		}

		add_action( 'init', array( $this, 'register_heartbeat_action' ), 11 );
		add_action( 'add_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
		add_action( 'update_option_instawp_api_heartbeat', array( $this, 'clear_heartbeat_action' ) );
		add_action( 'add_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
		add_action( 'update_option_instawp_rm_heartbeat', array( $this, 'clear_heartbeat_action' ) );
		add_action( 'instawp_handle_heartbeat', array( $this, 'handle_heartbeat' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_interval' ) );

		add_action( 'instawp_prepare_large_files_list', array( $this, 'prepare_large_files_list' ) );
		add_action( 'add_option_instawp_max_file_size_allowed', array( $this, 'clear_staging_sites_list' ) );
		add_action( 'update_option_instawp_max_file_size_allowed', array( $this, 'clear_staging_sites_list' ) );

		add_action( 'instawp_clean_completed_actions', array( $this, 'clean_events' ) );
		add_action( 'instawp_clean_migrate_files', array( $this, 'clean_migrate_files' ) );

		add_action( 'add_option_instawp_enable_wp_debug', array( $this, 'toggle_wp_debug' ), 10, 2 );
		add_action( 'update_option_instawp_enable_wp_debug', array( $this, 'toggle_wp_debug' ), 10, 2 );
		add_action( 'login_init', array( $this, 'instawp_auto_login_redirect' ) );
	}

	public function toggle_wp_debug( $old_value, $value ) {
		if ( $value === 'on' ) {
			$params = [
				'WP_DEBUG'         => true,
				'WP_DEBUG_LOG'     => true,
				'WP_DEBUG_DISPLAY' => false,
			];
		} else {
			$params = [
				'WP_DEBUG'         => false,
				'WP_DEBUG_LOG'     => false,
				'WP_DEBUG_DISPLAY' => false,
			];
		}

		$wp_config = new \InstaWP\Connect\Helpers\WPConfig( $params );
		$wp_config->update();
	}

	public function register_heartbeat_action() {

		$heartbeat = InstaWP_Setting::get_option( 'instawp_rm_heartbeat', 'on' );
		$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

		$interval = InstaWP_Setting::get_option( 'instawp_api_heartbeat', 15 );
		$interval = empty( $interval ) ? 15 : (int) $interval;

		if ( ! empty( InstaWP_Setting::get_api_key() ) && $heartbeat === 'on' && ! wp_next_scheduled( 'instawp_handle_heartbeat' ) ) {
			wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instawp_handle_heartbeat' );
		}

		if ( ! wp_next_scheduled( 'instawp_prepare_large_files_list' ) ) {
			wp_schedule_event( time(), 'hourly', 'instawp_prepare_large_files_list' );
		}

		if ( ! wp_next_scheduled( 'instawp_clean_completed_actions' ) ) {
			wp_schedule_event( time(), 'daily', 'instawp_clean_completed_actions' );
		}

		if ( ! wp_next_scheduled( 'instawp_clean_migrate_files' ) ) {
			wp_schedule_event( time(), 'hourly', 'instawp_clean_migrate_files' );
		}
	}

	public function cron_interval( $schedules ) {
		$interval = InstaWP_Setting::get_option( 'instawp_api_heartbeat', 15 );
		$interval = empty( $interval ) ? 15 : (int) $interval;

		$schedules['instawp_heartbeat_interval'] = [
			'interval' => $interval * 60,
			'display'  => esc_html__( 'Custom Interval' )
		];

		return $schedules;
	}

	public function clean_migrate_files() {
		$path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR . DIRECTORY_SEPARATOR;
		@unlink( $path . 'instawp_exclude_tables_rows_data.json' );
		@unlink( $path . 'instawp_exclude_tables_rows.json' );
	}

	public function handle_heartbeat() {
		$heartbeat = InstaWP_Setting::get_option( 'instawp_rm_heartbeat', 'on' );
		$heartbeat = empty( $heartbeat ) ? 'on' : $heartbeat;

		if ( $heartbeat !== 'on' ) {
			return;
		}

		date_default_timezone_set( "Asia/Kolkata" );

		if ( defined( 'INSTAWP_DEBUG_LOG' ) && true === INSTAWP_DEBUG_LOG ) {
			error_log( "HEARTBEAT RAN AT : " . date( 'd-m-Y, H:i:s, h:i:s' ) );
		}

		if ( empty( $this->api_key ) || empty( $this->connect_id ) ) {
			return;
		}

		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}
		$sizes_data = WP_Debug_Data::get_sizes();

		$wp_version   = get_bloginfo( 'version' );
		$php_version  = phpversion();
		$total_size   = $sizes_data['total_size']['size'];
		$active_theme = wp_get_theme()->get( 'Name' );

		$count_posts = wp_count_posts();
		$posts       = $count_posts->publish;

		$count_pages = wp_count_posts( 'page' );
		$pages       = $count_pages->publish;

		$count_users = count_users();
		$users       = $count_users['total_users'];

		// Curl constant
		global $InstaWP_Curl;

		$body = base64_encode(
			json_encode(
				array(
					"wp_version"  => $wp_version,
					"php_version" => $php_version,
					"total_size"  => $total_size,
					"theme"       => $active_theme,
					"posts"       => $posts,
					"pages"       => $pages,
					"users"       => $users,
				)
			)
		);

		$api_domain    = InstaWP_Setting::get_api_domain();
		$url           = $api_domain . INSTAWP_API_URL . '/connects/' . $this->connect_id . '/heartbeat';
		$body_json     = json_encode( $body );
		$curl_response = $InstaWP_Curl->curl( $url, $body_json );

		if ( defined( 'INSTAWP_DEBUG_LOG' ) && INSTAWP_DEBUG_LOG ) {
			error_log( "Heartbeat API Curl URL " . $url );
			error_log( "Print Heartbeat API Curl Response Start" );
			error_log( print_r( $curl_response, true ) );
			error_log( "Print Heartbeat API Curl Response End" );
		}
	}

	public function prepare_large_files_list() {
		$maxbytes = (int) InstaWP_Setting::get_option( 'instawp_max_file_size_allowed', INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED );
		$maxbytes = $maxbytes ? $maxbytes : INSTAWP_DEFAULT_MAX_FILE_SIZE_ALLOWED;
		$maxbytes = ( $maxbytes * 1024 * 1024 );
		$path     = realpath( ABSPATH );
		$data     = [];

		if ( $path !== false && $path != '' && file_exists( $path ) ) {
			foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $object ) {
				if ( $object->getSize() > $maxbytes && strpos( $object->getPath(), 'instawpbackups' ) === false ) {
					$data[] = [
						'size'          => $object->getSize(),
						'path'          => wp_normalize_path( $object->getPath() ),
						'pathname'      => wp_normalize_path( $object->getPathname() ),
						'realpath'      => wp_normalize_path( $object->getRealPath() ),
						'relative_path' => str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $object->getRealPath() ) ),
					];
				}
			}
		}

		set_transient( 'instawp_generate_large_files', true, HOUR_IN_SECONDS );
		update_option( 'instawp_large_files_list', $data );
	}

	public function clear_staging_sites_list() {
		delete_option( 'instawp_large_files_list' );
		do_action( 'instawp_prepare_large_files_list' );
	}

	public function clean_events() {
		if ( ! \ActionScheduler::is_initialized( __FUNCTION__ ) ) {
			return;
		}

		$statuses_to_purge = [
			\ActionScheduler_Store::STATUS_COMPLETE,
			\ActionScheduler_Store::STATUS_CANCELED,
			\ActionScheduler_Store::STATUS_FAILED,
		];
		$store             = \ActionScheduler::store();

		$deleted_actions = [];
		$action_ids      = [];
		foreach ( $statuses_to_purge as $status ) {
			$actions_to_delete = $store->query_actions( [
				'status'           => $status,
				'modified'         => as_get_datetime_object( '24 hours ago' ),
				'modified_compare' => '<=',
				'per_page'         => 200,
				'orderby'          => 'none',
				'group'            => 'instawp-connect'
			] );

			$action_ids = array_merge( $action_ids, $actions_to_delete );
		}

		foreach ( $action_ids as $action_id ) {
			try {
				$store->delete_action( $action_id );
			} catch ( Exception $e ) {
			}
		}
	}

	public function clear_heartbeat_action() {
		as_unschedule_all_actions( 'instawp_handle_heartbeat', [], 'instawp-connect' );
	}

	public function instawp_auto_login_redirect() {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$current_setup_plugins = array_keys( get_plugins() );
		$instawp_plugin        = null;
		$instawp_index_default = array_search( 'instawp-connect/instawp-connect.php', $current_setup_plugins );
		$instawp_index_main    = array_search( 'instawp-connect-main/instawp-connect.php', $current_setup_plugins );

		if ( false !== $instawp_index_default ) {
			$instawp_plugin = $current_setup_plugins[ $instawp_index_default ];
		}

		if ( false !== $instawp_index_main ) {
			$instawp_plugin = $current_setup_plugins[ $instawp_index_main ];
		}

		// check for plugin using plugin name
		if ( ! is_null( $instawp_plugin ) && is_plugin_active( $instawp_plugin ) ) {
			// Check for params
			if (
				isset( $_GET['reauth'] ) &&
				isset( $_GET['c'] ) &&
				isset( $_GET['s'] ) &&
				! empty( $_GET['reauth'] ) &&
				! empty( $_GET['c'] ) &&
				! empty( $_GET['s'] )
			) {
				$param_code   = $_GET['c'];
				$param_user   = base64_decode( $_GET['s'] );
				$current_code = get_transient( 'instawp_auto_login_code' );
				$username     = sanitize_user( $param_user );
				if (
					$param_code === $current_code &&
					false !== $current_code &&
					username_exists( $username )
				) {
					//plugin is activated
					require_once( 'wp-load.php' );
					$loginusername = $username;
					$user          = get_user_by( 'login', $loginusername );
					$user_id       = $user->ID;
					wp_set_current_user( $user_id, $loginusername );
					wp_set_auth_cookie( $user_id );
					do_action( 'wp_login', $loginusername, $user );

					// Remove transient
					delete_transient( 'instawp_auto_login_code' );
					wp_redirect( admin_url() );
					exit();
				} else {
					delete_transient( 'instawp_auto_login_code' );
					wp_redirect( wp_login_url( '', false ) );
					exit();
				}
			}
		}
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
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}

	public function get_directory_contents( $dir, $sort_by ) {
		$files_data      = scandir( $dir );
		$path_to_replace = wp_normalize_path( ABSPATH );
		$files           = $folders = [];

		foreach ( $files_data as $key => $value ) {
			$path            = realpath( $dir . DIRECTORY_SEPARATOR . $value );
			$normalized_path = wp_normalize_path( $path );

			try {
				if ( ! is_dir( $path ) ) {
					$size    = filesize( $path );
					$files[] = [
						'name'          => $value,
						'relative_path' => str_replace( $path_to_replace, '', $normalized_path ),
						'full_path'     => $normalized_path,
						'size'          => $size,
						'count'         => 1,
						'type'          => 'file',
					];
				} else if ( $value != "." && $value != ".." ) {
					$directory_info = $this->get_directory_info( $path );
					$folders[]      = [
						'name'          => $value,
						'relative_path' => str_replace( $path_to_replace, '', $normalized_path ),
						'full_path'     => $normalized_path,
						'size'          => $directory_info['size'],
						'count'         => $directory_info['count'],
						'type'          => 'folder',
					];
				}
			} catch ( Exception $e ) {
			}
		}

		$files_list = array_merge( $folders, $files );

		if ( $sort_by === 'descending' ) {
			usort( $files_list, function ( $item1, $item2 ) {
				return $item2['size'] <=> $item1['size'];
			} );
		} else if ( $sort_by === 'ascending' ) {
			usort( $files_list, function ( $item1, $item2 ) {
				return $item1['size'] <=> $item2['size'];
			} );
		}

		return $files_list;
	}

	public function get_directory_info( $path ) {
		$bytes_total = 0;
		$files_total = 0;
		$path        = realpath( $path );
		try {
			if ( $path !== false && $path != '' && file_exists( $path ) ) {
				foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) ) as $object ) {
					$bytes_total += $object->getSize();
					$files_total ++;
				}
			}
		} catch ( Exception $e ) {
		}

		return [
			'size'  => $bytes_total,
			'count' => $files_total
		];
	}

	public function get_directory_size( $path ) {
		$info = $this->get_directory_info( $path );

		return $info['size'];
	}

	public function get_file_size_with_unit( $size, $unit = "" ) {
		if ( ( ! $unit && $size >= 1 << 30 ) || $unit == "GB" ) {
			return number_format( $size / ( 1 << 30 ), 2 ) . " GB";
		}

		if ( ( ! $unit && $size >= 1 << 20 ) || $unit == "MB" ) {
			return number_format( $size / ( 1 << 20 ), 2 ) . " MB";
		}

		if ( ( ! $unit && $size >= 1 << 10 ) || $unit == "KB" ) {
			return number_format( $size / ( 1 << 10 ), 2 ) . " KB";
		}

		return number_format( $size ) . " B";
	}

	public function get_dir_files( &$files, &$folder, $path, $except_regex, $exclude_files = array(), $exclude_folder = array(), $exclude_file_size = 0, $flag = true ) {
		$handler = opendir( $path );
		if ( $handler === false ) {
			return;
		}

		while ( ( $filename = readdir( $handler ) ) !== false ) {
			if ( $filename != "." && $filename != ".." ) {
				$dir = str_replace( '/', DIRECTORY_SEPARATOR, $path . DIRECTORY_SEPARATOR . $filename );

				if ( in_array( $dir, $exclude_folder ) ) {
					continue;
				} elseif ( is_dir( $path . DIRECTORY_SEPARATOR . $filename ) ) {
					if ( $except_regex !== false ) {
						if ( $this->regex_match( $except_regex['file'], $path . DIRECTORY_SEPARATOR . $filename, $flag ) ) {
							continue;
						}
						$folder[] = $path . DIRECTORY_SEPARATOR . $filename;
					} else {
						$folder[] = $path . DIRECTORY_SEPARATOR . $filename;
					}
					$this->get_dir_files( $files, $folder, $path . DIRECTORY_SEPARATOR . $filename, $except_regex, $exclude_folder );
				} else {
					if ( $except_regex === false || ! $this->regex_match( $except_regex['file'], $path . DIRECTORY_SEPARATOR . $filename, $flag ) ) {
						if ( in_array( $filename, $exclude_files ) ) {
							continue;
						}
						if ( $exclude_file_size == 0 ) {
							$files[] = $path . DIRECTORY_SEPARATOR . $filename;
						} elseif ( filesize( $path . DIRECTORY_SEPARATOR . $filename ) < $exclude_file_size * 1024 * 1024 ) {
							$files[] = $path . DIRECTORY_SEPARATOR . $filename;
						}
					}
				}
			}
		}
		if ( $handler ) {
			@closedir( $handler );
		}

	}

	public function get_current_mode( $data_to_get = '' ) {
		$mode_data = [];

		if ( ! empty( INSTAWP_CONNECT_MODE ) ) {
			$mode_data['type'] = INSTAWP_CONNECT_MODE;
			$mode_data['name'] = INSTAWP_CONNECT_MODE_NAME ?? '';
			$mode_data['link'] = INSTAWP_CONNECT_MODE_LINK ?? '';
			$mode_data['desc'] = INSTAWP_CONNECT_MODE_DESC ?? '';
			$mode_data['logo'] = INSTAWP_CONNECT_MODE_LOGO ?? '';
		}

		if ( ! empty( $data_to_get ) ) {
			return InstaWP_Setting::get_args_option( $data_to_get, $mode_data );
		}

		return $mode_data;
	}

	public static function disable_cache_elements_before_restore() {

		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$file_name_ap   = ABSPATH . 'instawp-active-plugins.json';
		$active_plugins = (array) get_option( 'active_plugins', array() );

		// Ignore instawp plugin
		if ( ( $key = array_search( INSTAWP_PLUGIN_NAME, $active_plugins ) ) !== false ) {
			unset( $active_plugins[ $key ] );
		}

		file_put_contents( $file_name_ap, json_encode( $active_plugins ) );

		// For the Breeze plugin support
		if ( in_array( 'breeze/breeze.php', $active_plugins ) ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				include ABSPATH . 'wp-admin/includes/file.php';
				include WP_CONTENT_DIR . '/plugins/breeze/inc/cache/config-cache.php';
				include WP_CONTENT_DIR . '/plugins/breeze/inc/breeze-configuration.php';
			}
		}

		deactivate_plugins( $active_plugins );
	}


	public static function enable_cache_elements_before_restore() {

		if ( ! function_exists( 'get_plugins' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$file_name_ap   = ABSPATH . 'instawp-active-plugins.json';
		$active_plugins = file_get_contents( $file_name_ap );
		$active_plugins = json_decode( $active_plugins, true );
		$response       = activate_plugins( $active_plugins );

		if ( ! is_wp_error( $response ) && $response ) {
			unlink( $file_name_ap );
		}

		// Flush Redis Cache
		if ( class_exists( '\RedisCachePro\Plugin' ) ) {
			\RedisCachePro\Plugin::boot()->flush();
		}
	}


	public static function get_asset_url( $asset_name ) {
		return INSTAWP_PLUGIN_URL . $asset_name;
	}

	public static function get_exclude_default_plugins() {

		$exclude_plugins = array(
			'instawp-connect',
			'wp-cerber',
			'instawp-backup-pro',
			'.',
		);

		return apply_filters( 'INSTAWP_CONNECT/Filters/get_exclude_default_plugins', $exclude_plugins );
	}

	public static function get_folder_size( $root, $size ) {
		$count = 0;
		if ( is_dir( $root ) ) {
			$handler = opendir( $root );
			if ( $handler !== false ) {
				while ( ( $filename = readdir( $handler ) ) !== false ) {
					if ( $filename != "." && $filename != ".." ) {
						$count ++;

						if ( is_dir( $root . DIRECTORY_SEPARATOR . $filename ) ) {
							$size = self::get_folder_size( $root . DIRECTORY_SEPARATOR . $filename, $size );
						} else {
							$size += filesize( $root . DIRECTORY_SEPARATOR . $filename );
						}
					}
				}
				if ( $handler ) {
					@closedir( $handler );
				}
			}
		}

		return $size;
	}

	public static function get_plugins_list( $options = array(), $return_type = 'plugins_included' ) {

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins_included        = array();
		$plugins_excluded        = array();
		$list                    = get_plugins();
		$active_plugins_only     = $options['migrate_settings']['active_plugins_only'] ?? false;
		$exclude_default_plugins = self::get_exclude_default_plugins();

		foreach ( $list as $key => $item ) {
			$dirname = dirname( $key );

			if ( in_array( $dirname, $exclude_default_plugins ) ) {
				$plugins_excluded[] = $key;
				continue;
			}

			if ( ( 'true' == $active_plugins_only || '1' == $active_plugins_only ) && ! is_plugin_active( $key ) ) {
				$plugins_excluded[] = $key;
				continue;
			}

			$plugins_included[ $dirname ]['slug'] = $dirname;
			$plugins_included[ $dirname ]['size'] = self::get_folder_size( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $dirname, 0 );
		}

		$plugins_excluded = array_map( function ( $slug ) {
			$slug_parts = explode( '/', $slug );

			return $slug_parts[0] ?? '';
		}, $plugins_excluded );
		$plugins          = array(
			'plugins_included' => $plugins_included,
			'plugins_excluded' => array_filter( $plugins_excluded ),
		);

		if ( empty( $return_type ) ) {
			return $plugins;
		}

		return $plugins[ $return_type ] ?? array();
	}

	public static function get_themes_list( $options = array(), $return_type = 'themes_included' ) {

		if ( ! function_exists( 'wp_get_themes' ) ) {
			require_once ABSPATH . 'wp-includes/theme.php';
		}

		$themes_included    = array();
		$themes_excluded    = array();
		$current_theme      = wp_get_theme();
		$active_themes_only = $options['migrate_settings']['active_themes_only'] ?? false;

		foreach ( wp_get_themes() as $key => $item ) {
			if ( ( 'true' == $active_themes_only || '1' == $active_themes_only ) && ! in_array( $item->get_stylesheet(), [ $current_theme->get_stylesheet(), $current_theme->get_template() ] ) ) {
				$themes_excluded[] = $key;
				continue;
			}

			$themes_included[ $key ]['slug'] = $key;
			$themes_included[ $key ]['size'] = self::get_folder_size( get_theme_root() . DIRECTORY_SEPARATOR . $key, 0 );
		}

		$themes = array(
			'themes_included' => $themes_included,
			'themes_excluded' => $themes_excluded,
		);

		if ( empty( $return_type ) ) {
			return $themes;
		}

		return $themes[ $return_type ] ?? array();
	}

	public function instawp_check_usage_on_cloud( $total_size = 0 ) {

		$connect_id          = $this->connect_id ?? 0;
		$api_response        = InstaWP_Curl::do_curl( 'connects/' . $connect_id . '/usage', [], [], false, 'v1' );
		$api_response_status = InstaWP_Setting::get_args_option( 'success', $api_response, false );
		$api_response_data   = InstaWP_Setting::get_args_option( 'data', $api_response, [] );

		if ( ! $api_response_status ) {
			return array(
				'can_proceed'  => false,
				'connect_id'   => $connect_id,
				'api_response' => $api_response,
			);
		}

		$remaining_site       = (int) InstaWP_Setting::get_args_option( 'remaining_site', $api_response_data, '0' );
		$can_proceed          = $remaining_site > 0;
		$issue_for            = 'remaining_site';
		$available_disk_space = (int) InstaWP_Setting::get_args_option( 'remaining_disk_space', $api_response_data, '0' );


		$total_site_size = round( $total_size / 1048576, 2 );

		$api_response_data['require_disk_space'] = $total_site_size;

		if ( $can_proceed ) {
			$can_proceed = $total_site_size < $available_disk_space;
			$issue_for   = 'remaining_disk_space';
		}

		return array_merge( array( 'can_proceed' => $can_proceed, 'issue_for' => ( $can_proceed ? '' : $issue_for ) ), $api_response_data );
	}

	private function load_dependencies() {
		include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-migrate-log.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-curl.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-ajax.php';
		require_once INSTAWP_PLUGIN_DIR . '/admin/class-instawp-admin.php';

		include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-setting.php';

		include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-file-management.php';
		include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-database-management.php';

		include_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-tools.php';

		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-rest-api.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-hooks.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-cli.php';

		require_once INSTAWP_PLUGIN_DIR . '/migrate/class-instawp-migrate.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-cli.php';

		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-admin.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-events.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-wc.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-ajax.php';
		require_once INSTAWP_PLUGIN_DIR . '/includes/sync/class-instawp-sync-apis.php';
	}
}
<?php
/**
 * InstaWP Iterative Pull Push CLI Commands
 */

 use InstaWP\Connect\Helpers\Cache;
 use InstaWP\Connect\Helpers\Curl;
 use InstaWP\Connect\Helpers\DatabaseManager;
 use InstaWP\Connect\Helpers\FileManager;
 use InstaWP\Connect\Helpers\Helper;
 use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'INSTAWP_IPP_CLI_Commands' ) ) {
	class INSTAWP_IPP_CLI_Commands {

		/**
		 * The command start
		 * @var string
		 */
		private $command_start = 'instawp';

		/**
		 * Rows limit for each query
		 */
		private $rows_limit_per_query = 100;

		/**
		 * @var INSTAWP_IPP_Helper
		 */
		private $helper;

		/**
		 * Staging site path
		 */
		private $staging_url = '';

		/**
		 * Staging site path
		 */
		private $username = '';

		/**
		 * API Key
		 */
		private $api_key = '';

		/**
		 * Is staging
		 */
		private $is_staging = false;

		/**
		 * Instawp migration tables
		 */
		private $iwp_tables = array(
			"iwp_db_sent",
			"iwp_files_sent",
			"iwp_options",
		);

		/**
		 * Debug mode
		 */
		private $debug_mode = false;

		/**
		 * Database schema only push
		 */
		private $db_schema_only = true;

		/**
		 * INSTAWP_IPP_CLI_Commands Constructor
		 */
		public function __construct() {
		}

		private function init() {
			// Get hashed api key
			//$this->api_key = Helper::get_api_key( true );
			// staging site api key
			$this->api_key = '0e71f6cbeff626a99c09018522f075ccb0c9842fb0f84ed31406a1d7b88b5763';
			if ( empty( $this->api_key ) ) {
				WP_CLI::error( __( 'Missing API key.', 'instawp-connect' ) );
			}
			$connect_id    = instawp_get_connect_id();

			if ( empty( $connect_id ) ) {
				WP_CLI::error( __( 'Missing Connect ID.', 'instawp-connect' ) );
			}

			$staging_sites = get_option( 'instawp_staging_sites' );
			if ( ! empty( $staging_sites ) && ! empty( $staging_sites[0]['url'] ) && filter_var( $staging_sites[0]['url'], FILTER_VALIDATE_URL ) ) {
				$this->staging_url = esc_url( $staging_sites[0]['url'] );
				$this->username = $staging_sites[0]['username'];
			}
			
			if ( empty( $this->staging_url ) || empty( $this->username ) ) {
				WP_CLI::error( __( 'Staging site not found.', 'instawp-connect' ) );
			}

			// Check if its a staging site
			$api_options = get_option( 'instawp_api_options', array() );
			$this->is_staging  = ( ! empty( $api_options ) && ! empty( $api_options['api_url'] ) && false !== stripos( $api_options['api_url'], 'stage' ) ) ? 1 : 0;

			WP_CLI::log( __( 'Connected Site:', 'instawp-connect' ) . ' ' . $this->staging_url );
			// Helpers
			$this->helper = new INSTAWP_IPP_Helper();
		}


		/**
		 * RUN Command 
		 * wp instawp push
		 * wp instawp pull
		 * 
		 * @param array $args
		 * @param array $assoc_args
		 * 
		 */
		public function run_command( $args, $assoc_args ) {
			if ( empty( $args[0] ) || ! in_array( $args[0], array( 'push', 'pull' ) ) ) {
				WP_CLI::error( 'Missing or invalid type command. Must be push or pull. e.g. ' . $this->command_start . ' push or ' . $this->command_start . ' pull or IWP pull' );
				return false;
			}
			// Debug mode
			$this->debug_mode = isset( $assoc_args['debug'] );
			require_once INSTAWP_PLUGIN_DIR . '/includes/ipp/class-instawp-push-files.php';
			require_once INSTAWP_PLUGIN_DIR . '/includes/ipp/class-instawp-push-db.php';
			// Global variables
			global $tracking_db, $migrate_key, $migrate_id, $migrate_mode, $migrate_curl_title, $bearer_token, $target_url;
			
			try {
				global $wpdb;
				$this->init();

				// Set last run command
				update_option( $this->helper->vars['last_run_cli_time'], time() );

				if ( isset( $assoc_args['purge-cache'] ) ) {
					foreach ( $this->helper->vars as $option_name ) {
						if ( 0 === strpos( $option_name, 'iwp_ipp_' ) ) {
							delete_option( $option_name );
						}
					}
					WP_CLI::success( "Cache purged for itterative pull push successfully." );
					return;
				}

				$command = $args[0];
				// Set migrate settings
				$migrate_settings = array(
					'mode' => 'iterative_push',
					'destination_site_url' => $this->staging_url,
					'excluded_paths' => array(),
					'options' => array()
				);
				// Get exclude and include paths
				$excluded_paths = empty( $assoc_args['exclude-paths'] ) ? array() : explode( ',', $assoc_args['exclude-paths'] );
				$excluded_paths = array_merge( 
					$excluded_paths, 
					array(
						'editor',
						'wp-config.php',
						'wp-config-sample.php',
						'.htaccess',
						'.htpasswd',
						'wp-sitemap.xml',
						'sitemap.xml',
						'robots.txt',
						'iwp_log.txt',
						'.user.ini',
						'serve.php',
						'dest.php',
						'wp-content' . DIRECTORY_SEPARATOR . 'debug.log',
						'wp-content' . DIRECTORY_SEPARATOR . 'cache',
						'wp-content' . DIRECTORY_SEPARATOR . 'object-cache-iwp.php',
						'wp-content' . DIRECTORY_SEPARATOR . 'et-cache',
						'wp-content' . DIRECTORY_SEPARATOR . '.htaccess',
						'wp-content' . DIRECTORY_SEPARATOR . 'upgrade',
						'wp-content' . DIRECTORY_SEPARATOR . 'upgrade-temp-backup',
						'wp-content' . DIRECTORY_SEPARATOR . 'plugins-old',
						'wp-content' . DIRECTORY_SEPARATOR . 'themes-old',
						'wp-content' . DIRECTORY_SEPARATOR . INSTAWP_DEFAULT_BACKUP_DIR,
						'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'iwp-migration',
						'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'iwp-migration-main',
						'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'instawp-connect',
						'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins' . DIRECTORY_SEPARATOR . 'redis-cache-pro.php',
						'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins' . DIRECTORY_SEPARATOR . 'sso.php',
						'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins' . DIRECTORY_SEPARATOR . 'wp-stack-cache.php',
					)
				);
				
				$included_paths = empty( $assoc_args['include-paths'] ) ? array() : explode( ',', $assoc_args['include-paths'] );
				

				if ( ! empty( $included_paths ) ) {
					// Array that contains the entries from excluded_paths that are not present in include_paths 
					$excluded_paths = array_diff( $excluded_paths, $included_paths );
				}

				// Get exclude core
				if ( isset( $assoc_args['exclude-core'] ) ) {
					$excluded_paths = array_merge( $excluded_paths, $this->helper->core_files_folder_list() );
					$migrate_settings['options'][] = 'skip_core_files';
				}

				// Get exclude uploads
				if ( isset( $assoc_args['exclude-uploads'] ) ) {
					$migrate_settings['options'][] = 'skip_media_folder';
				}

				// Get exclude large files
				$exclude_large_files = isset( $assoc_args['exclude-large-files'] ) ? true : false;
				if ( $exclude_large_files ) {
					$migrate_settings['options'][] = 'skip_large_files';
				}

				// Iterative push
				if ( $command === 'push' ) {
					// Set migration mode
					$migrate_mode = 'iterative_push';
					// Set migration title
					$migrate_curl_title = 'InstaWP Migration Service - Iterative Push';
					
					// Get exclude large files
					if ( $exclude_large_files ) {
						$large_files = get_option( 'instawp_large_files_list' );
						if ( empty( $large_files ) ) {
							// Prepare large files
							do_action( 'instawp_prepare_large_files_list' );
							$large_files = get_option( 'instawp_large_files_list' );
						}

						if ( ! empty( $large_files ) ) {
							$excluded_paths = array_merge( $excluded_paths, array_column( $large_files, 'relative_path' ) );
						}
					}
					$excluded_paths = array_unique( $excluded_paths );
					
					$migrate_settings['excluded_paths'] = $excluded_paths;

					// Get exclude and include tables
					if ( isset( $assoc_args['with-db'] ) ) {
						$migrate_settings['excluded_tables_rows'] = array();
						$option_table_name = $wpdb->prefix . 'options';
						$migrate_settings['excluded_tables_rows'][$option_table_name] = array();
						foreach ( $this->helper->vars as $option_name ) {
							$migrate_settings['excluded_tables_rows'][$option_table_name][] = 'option_name:' . $option_name;
						}

						// Get exclude and include tables
						$excluded_tables = empty( $assoc_args['exclude-tables'] ) ? array() : explode( ',', $assoc_args['exclude-tables'] );
						$excluded_tables = array_merge( 
							$excluded_tables, 
							array( 
								"{$wpdb->prefix}actionscheduler_actions",
								"{$wpdb->prefix}actionscheduler_claims",
								"{$wpdb->prefix}actionscheduler_groups",
								"{$wpdb->prefix}actionscheduler_logs", 
							),
							$this->iwp_tables
						);
						$included_tables = empty( $assoc_args['include-tables'] ) ? array() : explode( ',', $assoc_args['include-tables'] );

						if ( ! empty( $included_tables ) ) {
							// Array that contains the entries from excluded_paths that are not present in include_paths 
							$excluded_tables = array_diff( $excluded_tables, $included_tables );
						}

						// Excluded tables
						if ( ! empty( $excluded_tables ) ) {
							$migrate_settings['excluded_tables'] = array_unique( $excluded_tables );
						}
					}

					// Get migrate settings
					$migrate_settings = InstaWP_Tools::get_migrate_settings( array(), $migrate_settings );
					
					// Detect file changes
					$migrate_settings['file_actions'] = $this->get_push_file_changes( $migrate_settings );
					// Detect database changes
					if ( isset( $assoc_args['with-db'] ) ) {
						$db_changes = $this->get_push_db_changes( $migrate_settings );
						$migrate_settings['db_actions'] = $db_changes['db_actions'];
					}
					$migrate_id = time();
					$target_url = 'https://rightful-grouse-51e3a4.a.instawpsites.com/wp-content/plugins/instawp-connect/dest.php';
					$migrate_key = 'bd402fc612d8ca58995153ed7e4804bd40ed5d19';
					$api_signature = 'adfe076580bff72cece9a8c991c2f09d94808ad21af72d79dd2ed6b1e33d7ed3c76f9930c62fdab2b93f79f8afee27eebe58d9a7a7bdfacc868856fc528c4bc7';
					$bearer_token = '0e71f6cbeff626a99c09018522f075ccb0c9842fb0f84ed31406a1d7b88b5763';

					// Generate migrate settings file
					$migrate_settings = InstaWP_Tools::generate_serve_file_response( $migrate_key, $api_signature, $migrate_settings );
					if ( empty( $migrate_settings ) ) {
						WP_CLI::error( __( 'Failed to generate migrate settings.', 'instawp-connect' ) );
					}
					// Tracking database
					$tracking_db = InstaWP_Tools::get_tracking_database( $migrate_key );
					if ( empty( $tracking_db ) ) {
						WP_CLI::error( __( 'Failed to create tracking database.', 'instawp-connect' ) );
					}
					
					$migrate_settings = $migrate_settings['migrate_settings'];
					
					$settings = array(
						'target_url' => $target_url,
						'working_directory' => wp_normalize_path( ABSPATH ),
						'source_domain' => $this->helper->get_domain(),
						'migrate_settings' => $migrate_settings,
						'migrate_id' => $migrate_id,
						'migrate_key' => $migrate_key,
						'api_signature' => $api_signature,
						'bearer_token' => $bearer_token,
						'db_host' => 'localhost',
						'db_username' => 'woxanilivi2839_picojaxune5617',
						'db_password' => 'inKaPFlc3GAsI6WS0kBC',
						'db_name' => 'woxanilivi2839_gGy1HvB6ZMTPqVKwtQI4',
						'dest_domain' => $this->helper->get_domain( $target_url ),
						'db_schema_only' => $this->db_schema_only,
					);
					// Push files
					if ( ! empty( $migrate_settings['file_actions'] ) && ( ! empty( $migrate_settings['file_actions']['to_send'] ) || ! empty( $migrate_settings['file_actions']['to_delete'] ) || ! empty( $migrate_settings['file_actions']['to_delete_folders'] ) ) ) {
						$this->helper->print_message( 'Pushing files...' );
						instawp_iterative_push_files( $settings );
					} else {
						$this->helper->print_message( 'No file changes detected for pushing.' );
					}
					
					// Push database
					if ( isset( $assoc_args['with-db'] ) ) {
						if ( ! empty( $migrate_settings['db_actions'] ) && ! empty( $migrate_settings['db_actions']['schema_queries'] ) ) {
							$this->helper->print_message( 'Pushing database schema...' );
							$push_db_status = instawp_iterative_push_db( $settings );
						} else {
							$this->helper->print_message( 'No database schema changes detected for pushing.' );
						}
					}

				} else {
					// Set migration mode
					$migrate_mode = 'iterative_pull';
					$migrate_curl_title = 'InstaWP Migration Service - Iterative Pull';
					$migrate_settings['mode'] = $migrate_mode;
					
					// Pull files
					$excluded_paths = array_unique( $excluded_paths );
					
					$migrate_settings['excluded_paths'] = $excluded_paths;
					
					// Detect file changes
					$migrate_settings['file_actions'] = $this->get_pull_file_changes( $migrate_settings );
					
					// Detect database changes
					if ( isset( $assoc_args['with-db'] ) ) {
						// $db_changes = $this->get_pull_db_changes( $migrate_settings );
						// $migrate_settings['db_actions'] = $db_changes['db_actions'];
					}
				}
			} catch ( \Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}

			if ( ! empty( $migrate_settings ) ) {
				// Save ipp run settings to option
				update_option( $this->helper->vars['ipp_run_settings'], $migrate_settings );
			}
			
			WP_CLI::success( "Run Successfully" );
		}

		/**
		 * Get push file changes
		 * 
		 * @param array $settings
		 * 
		 */
		private function get_push_file_changes( $settings ) {
			WP_CLI::log( "Detecting files changes..." );
			global $wpdb;
			$start = time();
			$action = array(
				'to_send' => array(), // Files to send
				'to_delete' => array(), // Deleted files
				'to_delete_folders' => array(), // Deleted folders
				'added_files_count' => 0, // Added files
				'updated_files_count' => 0, // Updated files
				'deleted_files_count' => 0, // Deleted files
			);

			$checksums = $this->helper->get_files_checksum(
				$settings
			);
			
			if ( ! empty( $checksums ) ) {
				$target_checksums = $this->call_api( 'files-checksum', array(
					'settings' => $settings
				) );
				
				if (  ! empty( $target_checksums ) ) {
					$excluded_paths = $settings['excluded_paths'];
		
					foreach ( $checksums as $path_hash => $file ) {
						if ( ! empty( $file['is_dir'] ) || in_array( $file['relative_path'], $excluded_paths ) || false !== strpos( $file['relative_path'], 'plugins/instawp-connect' ) || false !== strpos( $file['relative_path'], 'instawp-autologin' ) || false !== strpos( $file['relative_path'], 'migrate-p' ) ) {
							continue;
						}
						if ( ! isset( $target_checksums[$path_hash] ) || ( $target_checksums[$path_hash]['relative_path'] === $file['relative_path'] && $target_checksums[$path_hash]['checksum'] !== $file['checksum'] ) ) {
							$file['is_updated'] = isset( $target_checksums[$path_hash] );
							if ( $file['is_updated'] ) {
								$action['updated_files_count']++;
							} else {
								$action['added_files_count']++;
							}
							
							$action['to_send'][] = $file;
						}
					}
					foreach ( $target_checksums as $path_hash => $file ) {
						if ( ! isset( $checksums[$path_hash] ) && ! in_array( $file['relative_path'], $excluded_paths ) && false === strpos( $file['relative_path'], 'instawp-autologin' ) && false === strpos( $file['relative_path'], 'migrate-p' ) ) {
							if ( isset( $file['is_dir'] ) && true === $file['is_dir'] ) {
								$action['to_delete_folders'][] = $file;
							} else {
								$action['deleted_files_count']++;
								$action['to_delete'][] = $file;
							}
						}
					}

				
					if ( ! empty( $action['to_send'] ) ) {
						foreach ( $action['to_send'] as $file ) {
							$this->print_debug_log( "File: " . $file['relative_path'] . " is changed." );
						}
					}
					if ( ! empty( $action['to_delete'] ) ) {
						foreach ( $action['to_delete'] as $file ) {
							$this->print_debug_log( "File: " . $file['relative_path'] . " is deleted." );
						}
					}

					WP_CLI::log( __( 'Files Added:', 'instawp-connect' ) . ' ' . $action['added_files_count'] );
					WP_CLI::log( __( 'Files Updated:', 'instawp-connect' ) . ' ' . $action['updated_files_count'] );
					WP_CLI::log( __( 'Files Deleted:', 'instawp-connect' ) . ' ' . $action['deleted_files_count'] );
					WP_CLI::log( __( 'Directories Deleted:', 'instawp-connect' ) . ' ' . count( $action['to_delete_folders'] ) );
				}
				WP_CLI::success( "Detection of file changes completed in " . ( time() - $start ) . " seconds" );
			} else {
				WP_CLI::log( __( 'Checksums not found.', 'instawp-connect' ) );
			}

			return $action;
		}

		/**
		 * Print debug log
		 * 
		 * @param string|array $message
		 */
		public function print_debug_log( $message ) {
			if ( $this->debug_mode ) {
				$message = is_array( $message ) ? json_encode( $message ) : $message;
				WP_CLI::log( $message );
			}
		}
		/**
		 * Get push db changes
		 * 
		 * @param array $args
		 * @param array $assoc_args
		 * 
		 */
		private function get_push_db_changes( $settings ) {
			global $wpdb;
			WP_CLI::log( "Detecting database changes..." );
			$start = time();
			$excluded_tables = $settings['excluded_tables'];
			
			// Target DB
			$target_db = $this->call_api( 'db-schema' );
			$tables = $this->helper->get_tables();
			if ( empty( $tables ) || empty( $target_db['tables'] ) ) {
				WP_CLI::error( __( 'No tables found.', 'instawp-connect' ) );
			}
			
			$db_actions = array(
				'create_tables' => array(),
				'clone_tables' => array(),
				'drop_tables' => array(),
				'schema_queries' => array(), // create, drop or schema related queries
				'tables' => array(),
				'target' =>array(
					'tables' => $target_db['tables'],
					'table_prefix' => $target_db['table_prefix'],
				),
				'source' =>array(
					'tables' => $tables,
					'table_rows_count' => array(),
					'table_prefix' => $wpdb->prefix,
				),
			);

			// Drop tables
			foreach ( $target_db['tables'] as $table ) {
				if ( ! in_array( str_replace( $target_db['table_prefix'], $wpdb->prefix, $table ), $tables ) ) {
					$db_actions['drop_tables'][] = $table;
					$db_actions['schema_queries'][] = "DROP TABLE IF EXISTS `{$table}`";
				}
			}

			// flag to check if push db was successful
			$db_meta = get_option( $this->helper->vars['db_meta_repo'], array() );
			$new_tables_meta = array();
			$total = count( $tables );
			$processed = 0;
			foreach ( $tables as $table ) {
				$processed++;
				$this->helper->progress_bar( $processed, $total );
				if ( in_array( $table, $this->iwp_tables ) ) {
					continue;
				}
				// Source table meta
				$meta = $this->helper->get_table_meta( array( $table ), true, 0 );
				if ( empty( $meta ) ) {
					$this->print_debug_log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' No data found.', 'instawp-connect' ) );
					continue;
				}
				$db_actions['source']['table_rows_count'][ $table ] = $meta['rows_count'];

				$target_table_name = str_replace( $wpdb->prefix, $target_db['table_prefix'], $table );
				// Create tables
				if ( ! in_array( $target_table_name, $target_db['tables'] ) ) {
					$db_actions['create_tables'][] = $table;
					// Create table
					$create_table = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_A );
					if ( ! empty( $create_table ) && ! empty( $create_table['Create Table'] ) ) {
						$db_actions['schema_queries'][] = str_replace( $table, $target_table_name, $create_table['Create Table'] );
					}
				}
				// Skip excluded tables
				if ( in_array( $table, $excluded_tables ) ) {
					$this->print_debug_log( "Skipping table: " . $table );
					continue;
				} 

				// Table which need to be created but not excluded then insert all data
				if ( in_array( $table, $db_actions['create_tables'] ) ) {
					// Insert all source table data to target
					$db_actions['tables'][ $table ]['full_insert'] = true;
					continue;
				}

				$this->print_debug_log( "Checking table: " . $table );
				// Action required
				$db_actions['tables'][ $table ] = array(
					'columns_added' => array(),
					'columns_deleted' => array(),
					'columns_modified' => array(),
					'indexes_added' => array(),
					'indexes_deleted' => array(),
					'indexes_modified' => array(),
					'insert_start_id' => 0, // Insert start from this id
					'insert_end_id' => 0, // Insert end at this id
					'delete_start_id' => 0, // Delete start from this id
					'delete_end_id' => 0, // Delete end at this id
					'insert_data' => array(),
					'full_insert' => false,
					'update_data' => array(),
					'delete_data' => array(),
				);
				
				// Target table meta
				$target_table_meta = $this->call_api( 
					'table-checksum',
					array( 
						'table' => $target_table_name,
						'start_id' => 0,
					)
				);
				// Check if table exists
				if ( $target_table_meta['table'] !== $target_table_name ) {
					// Table mismatch
					WP_CLI::error( __( 'Table mismatch: ', 'instawp-connect' ) . $table . ' vs ' . $target_table_meta['table'] );
				}

				// Check table schema changes
				$queries = $this->check_table_schema_changes( $db_actions['tables'][ $table ], $meta, $target_table_meta );
				if ( ! empty( $queries ) && is_array( $queries ) ) {	
					$db_actions['schema_queries'] = array_merge( $db_actions['schema_queries'], $queries );
				}

				// Continue if schema push|pull only
				if ( $this->db_schema_only ) {
					continue;
				}

				// Check if source table is empty
				if ( 0 === absint( $meta['rows_count'] ) ) {
					$this->print_debug_log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' is empty.', 'instawp-connect' ) );
					continue;
				}
				// Table checksum
				$new_tables_meta[ $table ] = $meta;
				// Check if source table has no changes. Comapre from last checksum in sync
				if ( ! empty( $db_meta['meta'][ $table ] ) && ! empty( $db_meta['meta'][ $table ]['checksum'] ) && $db_meta['meta'][ $table ]['checksum'] === $meta['checksum'] ) {
					$this->print_debug_log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' no changes found since last sync.', 'instawp-connect' ) );
					continue;
				}

				// Check if target table is empty
				if ( 0 === absint( $target_table_meta['rows_count'] ) ) {
					// Insert all source table data to target
					$db_actions['tables'][ $table ]['full_insert'] = true;
					continue;
				}

				// Check if target table has primary key
				if ( ! empty( $meta['primary_key'] ) ) {
					// Continue if table has no checksum
					if ( ! isset( $meta['last_id'] ) ) {
						WP_CLI::error( __( 'Table: ', 'instawp-connect' ) . $table . __( ' has no primary key.', 'instawp-connect' ) );
					}

					// Get last id from target|source table where insertion is smaller
					$target_table_meta['last_id'] =absint( $target_table_meta['last_id'] );
					$meta['last_id'] = absint( $meta['last_id'] );
					$last_id_to_process = $target_table_meta['last_id'] < $meta['last_id'] ? $target_table_meta['last_id'] : $meta['last_id']; 
					$next_start_id = absint( $meta['next_start_id'] );
					// Loop through all batches
					while ( 0 < $next_start_id && $next_start_id <= $last_id_to_process ) {
						// Get next batch checksum data from source
						$meta = $this->helper->get_table_meta( 
							array( $table ), 
							true, 
							$next_start_id, 
							$last_id_to_process 
						);
						$new_tables_meta[ $table ] = $meta;
						// Get next batch checksum data from target
						$target_table_meta = $this->call_api( 
							'table-checksum', 
							array( 
								'table' => $target_table_name,
								'start_id' => $next_start_id,
								'last_id_to_process' => $last_id_to_process,
							)
						);
						// Continue if data is missing. In case rows are not present in table
						if ( empty( $meta['last_id'] ) || empty( $meta['rows_checksum'] ) || empty( $target_table_meta['rows_checksum'] ) ) {
							continue;
						}
						$db_actions['tables'][ $table ]['source_meta'] = $meta;
						$db_actions['tables'][ $table ]['target_meta'] = $target_table_meta;
						// Update action data
						$this->update_db_action( $db_actions['tables'][ $table ], $meta, $target_table_meta );
						// Get last id from target|source table where insertion is smaller
						$target_table_meta['last_id'] =absint( $target_table_meta['last_id'] );
						$meta['last_id'] = absint( $meta['last_id'] );
						$last_id_to_process = $target_table_meta['last_id'] < $meta['last_id'] ? $target_table_meta['last_id'] : $meta['last_id']; 
						// Get next start id
						$next_start_id = absint( $meta['next_start_id'] );
					}

					if ( $target_table_meta['last_id'] !== $meta['last_id'] ) {
						// Get first id from target|source table where insertion start
						if ( $target_table_meta['last_id'] < $meta['last_id'] ) {
							$db_actions['tables'][ $table ]['insert_start_id'] = $target_table_meta['last_id'] + 1;
							$db_actions['tables'][ $table ]['insert_end_id'] = $meta['last_id'];
						} else {
							$db_actions['tables'][ $table ]['delete_start_id'] = $meta['last_id'] + 1;
							$db_actions['tables'][ $table ]['delete_end_id'] = $target_table_meta['last_id'];
						}
					} 
					

				} else if ( ! empty( $meta['checksum'] ) ) {
					// Clone tables if checksum mismatch for tables without primary key
					if ( $meta['checksum'] !== $target_table_meta['checksum'] ) {
						// Table which need to be created but not excluded then insert all data
						if ( in_array( $table, $db_actions['create_tables'] ) ) {
							// Insert all source table data to target
							$db_actions['tables'][ $table ]['full_insert'] = true;
						} else {
							$db_actions['clone_tables'][] = $table;
						}
					}
				}
			}

			$this->print_debug_log( 'Table ' . $table . ': ' . json_encode( $db_actions ) );

			// Update DB meta ipp repo
			if ( ! empty( $new_tables_meta ) ) {
				$db_meta = empty( $db_meta ) ? array() : $db_meta;
				$db_meta = array_merge( $db_meta, array(
					'tables' => $tables,
					'time'	=> time(),
					'table_prefix' => $wpdb->prefix
				) );
				if ( ! isset( $db_meta['meta'] ) ) {
					$db_meta['meta'] = array();
				}
				foreach ( $new_tables_meta as $table => $meta ) {
					$db_meta['meta'][ $table ] = $meta;
				}
			}

			WP_CLI::success( "Detection of database changes completed in " . ( time() - $start ) . " seconds" );
			return array(
				'db_actions' => $db_actions,
				'db_update_meta' => $db_meta
			);
		}

		/**
		 * Get push file changes
		 * 
		 * @param array $settings
		 * 
		 */
		private function get_pull_file_changes( $settings ) {
			WP_CLI::log( "Detecting files changes..." );
			global $wpdb;
			$start = time();
			$action = array(
				'to_send' => array(), // Files to send
				'to_delete' => array(), // Deleted files
				'to_delete_folders' => array(), // Deleted folders
				'added_files_count' => 0, // Added files
				'updated_files_count' => 0, // Updated files
				'deleted_files_count' => 0, // Deleted files
			);

			// Site is itself a targeting site for pull
			$target_checksums = $this->helper->get_files_checksum(
				$settings
			);

			//$action['taget_checksums'] = $target_checksums;
			
			if ( ! empty( $target_checksums ) ) {
				$checksums = $this->call_api( 'files-checksum', array(
					'settings' => $settings
				) );
				//$action['checksums'] = $checksums;
				if (  ! empty( $checksums ) ) {
					$excluded_paths = $settings['excluded_paths'];
		
					foreach ( $checksums as $path_hash => $file ) {
						if ( ! empty( $file['is_dir'] ) || in_array( $file['relative_path'], $excluded_paths ) || false !== strpos( $file['relative_path'], 'plugins/instawp-connect' ) || false !== strpos( $file['relative_path'], 'instawp-autologin' ) || false !== strpos( $file['relative_path'], 'migrate-p' ) ) {
							continue;
						}
						if ( ! isset( $target_checksums[$path_hash] ) || ( $target_checksums[$path_hash]['relative_path'] === $file['relative_path'] && $target_checksums[$path_hash]['checksum'] !== $file['checksum'] ) ) {
							$file['is_updated'] = isset( $target_checksums[$path_hash] );
							if ( $file['is_updated'] ) {
								$action['updated_files_count']++;
							} else {
								$action['added_files_count']++;
							}
							
							$action['to_send'][] = $file;
						}
					}
					foreach ( $target_checksums as $path_hash => $file ) {
						if ( ! isset( $checksums[$path_hash] ) && ! in_array( $file['relative_path'], $excluded_paths ) && false === strpos( $file['relative_path'], 'instawp-autologin' ) && false === strpos( $file['relative_path'], 'migrate-p' ) ) {
							if ( isset( $file['is_dir'] ) && true === $file['is_dir'] ) {
								$action['to_delete_folders'][] = $file;
							} else {
								$action['deleted_files_count']++;
								$action['to_delete'][] = $file;
							}
						}
					}

				
					if ( ! empty( $action['to_send'] ) ) {
						foreach ( $action['to_send'] as $file ) {
							$this->print_debug_log( "File: " . $file['relative_path'] . " is changed." );
						}
					}
					if ( ! empty( $action['to_delete'] ) ) {
						foreach ( $action['to_delete'] as $file ) {
							$this->print_debug_log( "File: " . $file['relative_path'] . " is deleted." );
						}
					}

					WP_CLI::log( __( 'Files Added:', 'instawp-connect' ) . ' ' . $action['added_files_count'] );
					WP_CLI::log( __( 'Files Updated:', 'instawp-connect' ) . ' ' . $action['updated_files_count'] );
					WP_CLI::log( __( 'Files Deleted:', 'instawp-connect' ) . ' ' . $action['deleted_files_count'] );
					WP_CLI::log( __( 'Directories Deleted:', 'instawp-connect' ) . ' ' . count( $action['to_delete_folders'] ) );
				}
				WP_CLI::success( "Detection of file changes completed in " . ( time() - $start ) . " seconds" );
			} else {
				WP_CLI::log( __( 'Checksums not found.', 'instawp-connect' ) );
			}

			return $action;
		}

		/**
		 * Update DB action
		 * 
		 */
		private function update_db_action( &$db_actions, $meta, $target_table_meta ) {
			if ( empty( $meta['rows_checksum'] ) || empty( $target_table_meta['rows_checksum'] ) ) {
				return false;
			}
			// For same first id and last id query in source and target table
			if ( $meta['checksum_key'] === $target_table_meta['checksum_key'] ) {
				// Target site rows checksum
				$target_checksum = $target_table_meta['rows_checksum'][$target_table_meta['checksum_key']];
				// Source site rows checksum	
				$source_checksum = $meta['rows_checksum'][$meta['checksum_key']];
				
				if ( $target_checksum['rows_count'] !== $source_checksum['rows_count'] || $target_checksum['ids'] !== $source_checksum['ids'] ||( ! empty( $target_checksum['meta_hash'] ) && $target_checksum['meta_hash'] !== $source_checksum['meta_hash'] ) || ( ! empty( $target_checksum['content_hash'] ) && $target_checksum['content_hash'] !== $source_checksum['content_hash'] ) ) {
					
					// Update rows
					if ( ! empty( $source_checksum['ids'] ) ) {
						$source_checksum['ids'] = explode( ',', $source_checksum['ids'] );
						$db_actions['update_data'] = array_merge( $db_actions['update_data'], $source_checksum['ids'] );
					} else {
						$source_checksum['ids'] = array();
					}
					
					if ( ! empty( $target_checksum['ids'] ) ) {
						$target_checksum['ids'] = explode( ',', $target_checksum['ids'] );
						// ID which are in target table but not in source
						$deleted_ids = array_diff( $target_checksum['ids'], $source_checksum['ids'] );
						if ( ! empty( $deleted_ids ) ) {
							// Delete rows
							$db_actions['delete_data'] = array_merge(
								$db_actions['delete_data'],
								$deleted_ids
							);
						}
					}
				}
			} else {
				WP_CLI::log( __( 'Checksum key mismatch: ', 'instawp-connect' ) . $meta['checksum_key'] . ' vs ' . $target_table_meta['checksum_key'] );
			}
		}

		/**
		 * Check table schema changes
		 */
		private function check_table_schema_changes( &$db_actions, $meta, $target_table_meta ) {
			// Columns
			$target_columns = $target_table_meta['table_schema'];
			$target_table = $target_table_meta['table'];
			$source_table = $meta['table'];
			$source_columns = $meta['table_schema'];
			$queries = array();
			// Columns details
			$source_columns_details = array();
			$target_columns_details = array();
			foreach ( $source_columns as $source_column ) {
				unset( $source_column['Privileges'] ); // It's not required
				$source_columns_details[$source_column['Field']] = $source_column;
			}
			foreach ( $target_columns as $target_column ) {
				unset( $target_column['Privileges'] ); // It's not required
				$target_columns_details[$target_column['Field']] = $target_column;
			}
			// Detect column changes
			$target_column_names = array_column($target_columns, 'Field');
			$source_column_names = array_column($source_columns, 'Field');

			foreach ( $source_columns_details as $col_name => $column_details ) {
				if ( isset( $target_columns_details[$col_name] ) ) {
					if ( json_encode( $column_details ) !== json_encode( $target_columns_details[$col_name] ) ) {
						$db_actions['columns_modified'][$col_name] = [
							'target' => $column_details,
							'source' => $target_columns_details[$col_name],
						];
						$queries[] = sprintf(
							"ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` %s %s %s", // Renaming and modifying column
							$target_table,                // Table name
							$col_name,             // Old column name (the one being renamed)
							$col_name,             // New column name (the desired name)
							$column_details['Type'],      // Data type
							$column_details['Null'] === 'YES' ? 'NULL' : 'NOT NULL', // Nullability
							isset($column_details['Default']) ? "DEFAULT '{$column_details['Default']}'" : '' // Default value
						);
					}
				} else {
					$db_actions['columns_added'][] = $col_name;
				}
			}
	
			// Columns which are in target_table table but not in source_table
			$db_actions['columns_deleted'] = array_diff($target_column_names, $source_column_names);
	
			// Prepare deleted columns query
			if ( ! empty( $db_actions['columns_added'] ) ) {
				foreach ( $db_actions['columns_added'] as $column_added_key => $column_name ) {
					$column_details = $source_columns_details[$column_name];
					$old_column_name = false;
					// Check if column name modified or not
					if ( ! empty( $db_actions['columns_deleted'] ) ) {
						foreach ( $db_actions['columns_deleted'] as $del_col_key => $del_col_name ) {
							$columns_deleted_details = $target_columns_details[$del_col_name];
							if ( empty( $columns_deleted_details ) ) {
								continue;
							}
							// Check if column details are same
							foreach ( array(
								'Type',
								'Collation',
								'Null',
								'Default',
								'Key',
								'Extra'
							) as $key_to_check ) {
								if ( $column_details[$key_to_check] !== $columns_deleted_details[$key_to_check] ) {
									$old_column_name = false;
									break;
								} else	{
									$old_column_name = $del_col_name;
								}
							}
							// If column is renamed
							if ( $del_col_name === $old_column_name ) {
								// Unset added column key
								unset( $db_actions['columns_added'][$column_added_key] );
								// Unset deleted column key
								unset( $db_actions['columns_deleted'][$del_col_key] );
								break;
							}
						}
					}

					if ( ! empty( $old_column_name ) ) {
						// Renaming and modifying column
						$query = sprintf(
							"ALTER TABLE `%s` CHANGE COLUMN `%s` `%s` %s %s %s", 
							$target_table,                // Table name
							$old_column_name,             // Old column name (the one being renamed)
							$column_name,             // New column name (the desired name)
							$column_details['Type'],      // Data type
							$column_details['Null'] === 'YES' ? 'NULL' : 'NOT NULL', // Nullability
							isset($column_details['Default']) ? "DEFAULT '{$column_details['Default']}'" : '' // Default value
						);

						
						// Modified column
						$db_actions['columns_modified'][$column_name] = [
							'target' => $columns_deleted_details,
							'source' => $column_details,
						];
						
					} else {
						$query = sprintf(
							"ALTER TABLE `%s` ADD COLUMN `%s` %s %s %s",
							$target_table,
							$column_name,
							$column_details['Type'], // Data type
							$column_details['Null'] === 'YES' ? 'NULL' : 'NOT NULL', // Nullability
							isset($column_details['Default']) ? "DEFAULT '{$column_details['Default']}'" : '' // Default value
						);
						 // Add primary key constraint
						 if (isset($column_details['Key']) && $column_details['Key'] === 'PRI') {
							$query .= ", ADD PRIMARY KEY (`$column_name`)";
						}
				
						// Add unique key constraint
						if (isset($column_details['Key']) && $column_details['Key'] === 'UNI') {
							$query .= ", ADD UNIQUE (`$column_name`)";
						}
					
					}

					$queries[] = $query;
				}
			}

			// Prepare deleted columns query
			if ( ! empty( $db_actions['columns_deleted'] ) ) {
				foreach ( $db_actions['columns_deleted'] as $column_name ) {
					$query = sprintf(
						"ALTER TABLE `%s` DROP COLUMN `%s`",
						$target_table,
						$column_name
					);
					$queries[] = $query;
				}
			}
	
			// Return if no index found
			if ( empty( $target_table_meta['indexes'] ) && empty( $meta['indexes'] ) ) {
				return $queries;
			}
			// Detect index changes
			$target_indexes = empty( $target_table_meta['indexes'] ) ? array() : $target_table_meta['indexes'];
			$source_indexes = empty( $meta['indexes'] ) ? array() : $meta['indexes'];

			// Group indexes by name for easier comparison
			$target_grouped = $this->table_group_indexes_by_name($target_indexes);
			$source_grouped = $this->table_group_indexes_by_name($source_indexes);
		
			// Detect added and deleted indexes
			$target_index_names = array_keys($target_grouped);
			$source_index_names = array_keys($source_grouped);
		
			$db_actions['indexes_added'] = array_diff($source_index_names, $target_index_names);
			$db_actions['indexes_deleted'] = array_diff($target_index_names, $source_index_names);
		
			// Prepare added indexes query
			if ( ! empty( $db_actions['indexes_added'] ) ) {
				foreach ( $db_actions['indexes_added'] as $index_name ) {
					$index_details = $source_grouped[$index_name];
					$columns = array_column($index_details, 'Column_name');
					$is_unique = $index_details[0]['Non_unique'] == 0 ? 'UNIQUE' : '';
					$index_type = $index_details[0]['Index_type'];
			
					$queries[] = sprintf(
						"ALTER TABLE `%s` ADD %s INDEX `%s` (%s) USING %s",
						$target_table,
						$is_unique,
						$index_name,
						implode(', ', $columns),
						$index_type
					);
				}
			}
			 
			// Prepare deleted indexes query
			if ( ! empty( $db_actions['indexes_deleted'] ) ) {
				foreach ( $db_actions['indexes_deleted'] as $index_name ) {
					$index_details = $target_grouped[$index_name];
					$columns = array_column($index_details, 'Column_name');
					$is_unique = $index_details[0]['Non_unique'] == 0 ? 'UNIQUE' : '';
					$index_type = $index_details[0]['Index_type'];
			
					$queries[] = sprintf(
						"ALTER TABLE `%s` DROP INDEX `%s`",
						$target_table,
						$index_name
					);
				}
			}

			// Detect modified indexes
			foreach ($target_grouped as $index_name => $target_index) {
				if (isset($source_grouped[$index_name])) {
					$source_index = $source_grouped[$index_name];
					
					if ( json_encode( $target_index ) !== json_encode( $source_index ) ) {
						$db_actions['indexes_modified'][$index_name] = [
							'target_index' => $target_index,
							'source_index' => $source_index,
						];
						$columns = array_column($source_index, 'Column_name');
						$is_unique = $source_index[0]['Non_unique'] == 0 ? 'UNIQUE' : '';
        				$index_type = $source_index[0]['Index_type'];

						// Drop the old index
						$queries[] = sprintf(
							"ALTER TABLE `%s` DROP INDEX `%s`",
							$target_table,
							$index_name
						);
				
						// Add the modified index
						$queries[] = sprintf(
							"ALTER TABLE `%s` ADD %s INDEX `%s` (%s) USING %s",
							$target_table,
							$is_unique,
							$index_name,
							implode(', ', $columns),
							$index_type
						);
					}
				}
			}
			
			return $queries;
		}
		
		private function table_group_indexes_by_name($indexes) {
			$grouped = [];
			foreach ($indexes as $index) {
				$key_name = $index['Key_name'];
				if (!isset($grouped[$key_name])) {
					$grouped[$key_name] = array();
				}
					
				/**
				 * Fields That Are Descriptive or Informative (Not Directly Part of Schema):
				 * These fields provide additional information but do not define the core structure of the
				 * index:
				 * Table: The name of the table the index belongs to.
				 * Cardinality: An estimate of the number of unique values in the index. This is for 
				 * query optimization, not schema definition.
				 * Packed: Indicates whether the index is compressed.
				 * Null: Whether the column in the index allows NULL values.
				 * Comment: Additional comments about the index.
				 * Index_comment: Developer-defined comments on the index.
				 * Visible: Whether the index is visible to the optimizer (introduced in MySQL 8.0).
				 * Expression: Specifies if the index is based on an expression rather than a column 
				 * (used for functional indexes).
				 */
				// Unset these fields
				foreach ( array(
					'Table',
					'Cardinality',
				) as $unset_key ) {
					unset( $index[ $unset_key ] );
				}

				$grouped[$key_name][] = $index;
			}
			return $grouped;
		}
		

		private function call_api( $path, $body = array(), $only_data = true ) {
			
			$response = wp_remote_post( 
				$this->staging_url . '/wp-json/instawp-connect/v2/ipp/' . $path, 
				array( 
					'timeout'   => 200,
					'body' => $body,
					'headers' => array(
						'Authorization' => 'Bearer ' . $this->api_key,
						'staging'       => $this->is_staging,
					),
				) 
			);
			if ( is_wp_error( $response ) ) {
				WP_CLI::error( __( 'API call failed: ', 'instawp-connect' ) . $response->get_error_message() );
			}
	
			$response = wp_remote_retrieve_body( $response );
			$response = json_decode( $response, true );
	
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				WP_CLI::log( __( 'Invalid response format.', 'instawp-connect' ) );
				WP_CLI::error( $response );
			}

			if ( empty( $response['success'] ) ) {
				WP_CLI::error( $response['message'] );
			}
	
			return $only_data ? $response['data'] : $response;
		}

		/**
		 * Get database settings.
		 *
		 * @param array $args Optional arguments.
		 *
		 * @return array Array of database settings.
		 */
		public function get_db_settings( $args = array() ) {
			
		}

		
	}
}



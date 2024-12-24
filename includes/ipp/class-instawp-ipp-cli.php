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
		 * INSTAWP_IPP_CLI_Commands Constructor
		 */
		public function __construct() {
		}

		private function init() {
			// Get hashed api key
			//$this->api_key = Helper::get_api_key( true );
			$this->api_key = get_option( 'iwp_connect_api_key' );
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
			try {
				global $wpdb;
				$this->init();

				// Set last run command
				update_option( $this->helper->vars['last_run_cli_time'], time() );
				
				$command = $args[0];
				if ( $command === 'push' ) {
					if ( isset( $assoc_args['purge-cache'] ) ) {
						update_option( $this->helper->vars['file_checksum_repo'], array() );
						update_option( $this->helper->vars['db_meta_repo'], array() );
						WP_CLI::success( "Cache purged for {$command} files successfully." );
						return;
					}
					// Get exclude and include paths
					$excluded_paths = empty( $assoc_args['exclude-paths'] ) ? array() : explode( ',', $assoc_args['exclude-paths'] );
					$excluded_paths = array_merge( 
						$excluded_paths, 
						array(
							'editor',
							'wp-config.php',
							'wp-config-sample.php',
							'wp-content/' . INSTAWP_DEFAULT_BACKUP_DIR,
							'.htaccess',
							'wp-content/plugins/intawp-connect'
						)
					);
					

					$included_paths = empty( $assoc_args['include-paths'] ) ? array() : explode( ',', $assoc_args['include-paths'] );
					
					if ( ! empty( $included_paths ) ) {
						// Array that contains the entries from excluded_paths that are not present in include_paths 
						$excluded_paths = array_diff( $excluded_paths, $included_paths );
					}
					// Get exclude core files
					$exclude_core  = isset( $assoc_args['exclude-core'] ) ? true : false;
					if ( $exclude_core ) {
						$excluded_paths = array_merge( $excluded_paths, $this->helper->core_files_folder_list() );
					}

					// Get exclude large files
					$exclude_large_files = isset( $assoc_args['exclude-large-files'] ) ? true : false;
					if ( $exclude_large_files ) {
						$large_files = get_option( 'instawp_large_files_list' );
						if ( empty( $large_files ) ) {
							do_action( 'instawp_prepare_large_files_list' );
							$large_files = get_option( 'instawp_large_files_list' );
						}

						if ( ! empty( $large_files ) ) {
							$excluded_paths = array_merge( $excluded_paths, array_column( $large_files, 'relative_path' ) );
						}
					}
					$excluded_paths = array_unique( $excluded_paths );
					// Get exclude uploads
					$exclude_uploads = isset( $assoc_args['exclude-uploads'] ) ? true : false;
					$settings = array(
						'mode' => 'iterative_push',
						'destination_site_url' => $this->staging_url,
						'excluded_paths' => $excluded_paths,
						'options' => array()
					);

					if ( $exclude_uploads ) {
						$settings['options'][] = 'skip_media_folder';
					}

					if ( isset( $assoc_args['with-db'] ) ) {
						$settings['excluded_tables_rows'] = array();
						$option_table_name = $wpdb->prefix . 'options';
						$settings['excluded_tables_rows'][$option_table_name] = array();
						foreach ( $this->helper->vars as $option_name ) {
							$settings['excluded_tables_rows'][$option_table_name][] = 'option_name:' . $option_name;
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
							) 
						);
						$included_tables = empty( $assoc_args['include-tables'] ) ? array() : explode( ',', $assoc_args['include-tables'] );

						if ( ! empty( $included_tables ) ) {
							// Array that contains the entries from excluded_paths that are not present in include_paths 
							$excluded_tables = array_diff( $excluded_tables, $included_tables );
						}

						// Excluded tables
						if ( ! empty( $excluded_tables ) ) {
							$settings['excluded_tables'] = array_unique( $excluded_tables );
						}
					}
					$settings = InstaWP_Tools::get_migrate_settings( array(), $settings );
					// $_POST['migrate_settings'] = $settings;
					// do_action( 'instawp_migrate_init_ipp' );
					// $migration_details = Option::get_option( 'instawp_migration_details' );
					// $this->helper->print_message( 'migrate details' );
					// $this->helper->print_message( $migration_details );
					// $migrate_key       = Helper::get_args_option( 'migrate_key', $migration_details );
					// $tracking_db       = InstaWP_Tools::get_tracking_database( $migrate_key );
					// $settings  = $tracking_db->get_option( 'migrate_settings' );
					// Detect file changes
					$settings['file_actions'] = $this->get_push_file_changes( $settings );
					// Detect database changes
					if ( isset( $assoc_args['with-db'] ) ) {
						$settings['db_actions'] = $this->get_push_db_changes( $settings );
					}

					update_option( $this->helper->vars['ipp_run_settings'], $settings );
				}
			} catch ( \Exception $e ) {
				WP_CLI::error( $e->getMessage() );
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
				'to_send' => array(),
				'to_delete' => array(),
			);
			$checksums = $this->helper->get_files_checksum(
				$settings
			);

			if ( ! empty( $checksums ) ) {
				$target_checksums = $this->call_api( 'files-checksum', array(
					'settings' => $settings
				) );
				if (  ! empty( $target_checksums ) ) {
					$excluded_paths = array_merge( 
						$settings['excluded_paths'],  
						array( 
							'wp-config.php',
							'wp-config-sample.php',
							'.htaccess',
							'.htpasswd',
							'wp-sitemap.xml',
							'robots.txt',
							'wp-content/debug.log',
							'serve.php',
							'dest.php',
						)
					);
		
					foreach ( $checksums as $path_hash => $file ) {
						if ( in_array( $file['path'], $excluded_paths ) || false !== strpos( $file['path'], 'plugins/instawp-connect' ) || false !== strpos( $file['path'], 'instawp-autologin' ) ) {
							continue;
						}
						if ( ! isset( $target_checksums[$path_hash] ) || ( $target_checksums[$path_hash]['path'] === $file['path'] && $target_checksums[$path_hash]['checksum'] !== $file['checksum'] ) ) {
							$action['to_send'][] = $file;
						}
					}
					foreach ( $target_checksums as $path_hash => $file ) {
						if ( ! isset( $checksums[$path_hash] ) && ! in_array( $file['path'], $excluded_paths ) && false === strpos( $file['path'], 'instawp-autologin' ) ) {
							$action['to_delete'][] = $file;
						}
					}

					$changed_files = 0;
					$deleted_files = 0;
					if ( ! empty( $action['to_send'] ) ) {
						foreach ( $action['to_send'] as $file ) {
							//WP_CLI::log( "File: " . $file['path'] . " is changed." );
						}
						$changed_files = count( $action['to_send'] );
					}
					if ( ! empty( $action['to_delete'] ) ) {
						foreach ( $action['to_delete'] as $file ) {
							//WP_CLI::log( "File: " . $file['path'] . " is deleted." );
						}
						$deleted_files = count( $action['to_delete'] );
					}

					//WP_CLI::log( __( 'Files Changed:', 'instawp-connect' ) . ' ' . $changed_files );
					//WP_CLI::log( __( 'Files Deleted:', 'instawp-connect' ) . ' ' . $deleted_files );
				}
				WP_CLI::success( "Detecting files changes completed in " . ( time() - $start ) . " seconds" );
			} else {
				WP_CLI::log( __( 'Checksums not found.', 'instawp-connect' ) );
			}

			return $action;
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
				'tables' => array(),
				'target' =>array(
					'tables' => $target_db['tables'],
					'table_prefix' => $target_db['table_prefix'],
				),
				'source' =>array(
					'tables' => $tables,
					'table_prefix' => $wpdb->prefix,
				),
			);

			// Drop tables
			foreach ( $target_db['tables'] as $table ) {
				if ( ! in_array( str_replace( $target_db['table_prefix'], $wpdb->prefix, $table ), $tables ) ) {
					$db_actions['drop_tables'][] = $table;
				}
			}

			// flag to check if push db was successful
			$success_push_db = true;
			$db_meta = get_option( $this->helper->vars['db_meta_repo'], array() );
			$new_tables_meta = array();
			foreach ( $tables as $table ) {
				$target_table_name = str_replace( $wpdb->prefix, $target_db['table_prefix'], $table );
				// Create tables
				if ( ! in_array( $target_table_name, $target_db['tables'] ) ) {
					$db_actions['create_tables'][] = $table;
					continue;
				}
				// Skip excluded tables
				if ( in_array( $table, $excluded_tables ) ) {
					WP_CLI::log( "Skipping table: " . $table );
					continue;
				} 

				// Table which need to be created but not excluded then insert all data
				if ( in_array( $table, $db_actions['create_tables'] ) ) {
					// Insert all source table data to target
					$db_actions['tables'][ $table ]['full_insert'] = true;
					continue;
				}

				WP_CLI::log( "Checking table: " . $table );
				// Action required
				$db_actions['tables'][ $table ] = array(
					'insert_start_id' => 0, // Insert start from this id
					'insert_end_id' => 0, // Insert end at this id
					'delete_start_id' => 0, // Delete start from this id
					'delete_end_id' => 0, // Delete end at this id
					'insert_data' => array(),
					'full_insert' => false,
					'update_data' => array(),
					'delete_data' => array(),
				);
				
				// Source table meta
				$meta = $this->helper->get_table_meta( array( $table ), true, 0 );

				if ( empty( $meta ) ) {
					WP_CLI::log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' No data found.', 'instawp-connect' ) );
					continue;
				}
				// Check if source table is empty
				if ( 0 === absint( $meta['rows_count'] ) ) {
					WP_CLI::log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' is empty.', 'instawp-connect' ) );
					continue;
				}
				// Table checksum
				$new_tables_meta[ $table ] = $meta;
				// Check if source table has no changes. Comapre from last checksum in sync
				if ( ! empty( $db_meta['meta'][ $table ] ) && ! empty( $db_meta['meta'][ $table ]['checksum'] ) && $db_meta['meta'][ $table ]['checksum'] === $meta['checksum'] ) {
					WP_CLI::log( __( 'Table: ', 'instawp-connect' ) . $table . __( ' no changes found since last sync.', 'instawp-connect' ) );
					continue;
				}

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
						$this->update_db_action( $db_actions, $table, $meta, $target_table_meta );
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

			//WP_CLI::log( 'Table ' . $table . ': ' . json_encode( $db_actions ) );

			// Update DB meta ipp repo
			if ( $success_push_db === true && ! empty( $new_tables_meta ) ) {
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
				update_option( $this->helper->vars['db_meta_repo'], $db_meta );
			}

			WP_CLI::success( "Detecting database changes completed in " . ( time() - $start ) . " seconds" );
			return $db_actions;
		}

		/**
		 * Update DB action
		 * 
		 */
		private function update_db_action( &$db_actions, $table, $meta, $target_table_meta ) {
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
						$db_actions['tables'][ $table ]['update_data'] = array_merge( $db_actions['tables'][ $table ]['update_data'], $source_checksum['ids'] );
					} else {
						$source_checksum['ids'] = array();
					}
					
					if ( ! empty( $target_checksum['ids'] ) ) {
						$target_checksum['ids'] = explode( ',', $target_checksum['ids'] );
						// ID which are in target table but not in source
						$deleted_ids = array_diff( $target_checksum['ids'], $source_checksum['ids'] );
						if ( ! empty( $deleted_ids ) ) {
							// Delete rows
							$db_actions['tables'][ $table ]['delete_data'] = array_merge(
								$db_actions['tables'][ $table ]['delete_data'],
								$deleted_ids
							);
						}
					}
				}
			} else {
				WP_CLI::log( __( 'Checksum key mismatch: ', 'instawp-connect' ) . $meta['checksum_key'] . ' vs ' . $target_table_meta['checksum_key'] );
			}
		}

		private function call_api( $path, $body = array(), $only_data = true ) {
			
			$response = wp_remote_post( 
				$this->staging_url . '/wp-json/instawp-connect/v2/ipp/' . $path, 
				array( 
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
				WP_CLI::error( __( 'Invalid response format.', 'instawp-connect' ) );
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



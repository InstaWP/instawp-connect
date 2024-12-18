<?php
/**
 * InstaWP Iterative Pull Push CLI Commands
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\WPConfig;
use InstaWP\Connect\Helpers\Option;

if ( ! class_exists( 'INSTAWP_IPP_CLI_Commands' ) ) {
	class INSTAWP_IPP_CLI_Commands {

		/**
		 * The command start
		 * @var string
		 */
		private $command_start = 'instawp';

		/**
		 * File checksum name
		 */
		private $file_checksum_name = 'iwp_ipp_file_checksums_repo';
		/**
		 * Database checksum name
		 */
		private $db_meta_name = 'iwp_ipp_db_meta_repo';
		

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
			$this->api_key = Helper::get_api_key( true );
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
			$this->helper = new INSTAWP_IPP_Helper( true );
		}


		/**
		 * RUN Command 
		 * wp instawp push
		 * wp instawp pull
		 * 
		 * @param array $args
		 * 
		 */
		public function run_command( $args, $assoc_args ) {
			if ( empty( $args[0] ) || ! in_array( $args[0], array( 'push', 'pull' ) ) ) {
				WP_CLI::error( 'Missing or invalid type command. Must be push or pull. e.g. ' . $this->command_start . ' push or ' . $this->command_start . ' pull or IWP pull' );
				return false;
			}

			global $wpdb;
			$this->init();

			// Set last run command
			update_option( 'iwp_ipp_cli_last_run_time', time() );

			$command = $args[0];

			if ( empty( $args[1] ) || 'db' !== $args[1] ) {
				$command = $command . '-files';
			} else {
				$command = $command . '-db';
			}

			if ( $command === 'push-files' ) {
				if ( isset( $assoc_args['purge-cache'] ) ) {
					update_option( $this->file_checksum_name, array() );
					update_option( $this->db_checksum_name, array() );
					WP_CLI::success( "Cache purged for {$command} files successfully." );
					return;
				}

				WP_CLI::log( "Detecting files changes..." );

				$start = time();
				$action = array(
					'to_send' => array(),
					'to_delete' => array(),
				);
				$settings = $this->helper->get_file_settings(
					array(
						'exclude_paths' => empty( $assoc_args['exclude-paths'] ) ? array() : explode( ',', $assoc_args['exclude-paths'] ),
						'include_paths' => empty( $assoc_args['include-paths'] ) ? array() : explode( ',', $assoc_args['include-paths'] ),
						'exclude_core' => empty( $assoc_args['exclude-core'] ) ? false : true,
						'exclude_uploads' => empty( $assoc_args['exclude-core'] ) ? false : true,
						'exclude_large_files' => empty( $assoc_args['exclude-large-files'] ) ? false : true,
						'files_limit' => empty( $assoc_args['files-limit'] ) ? 0 : intval( $assoc_args['files-limit'] ),
					)
				);

				if ( ! empty( $settings['checksums'] ) ) {
					$target_checksums = $this->call_api( 'files-checksum' );
					if (  ! empty( $target_checksums['checksums'] ) ) {
						$exclude_paths = array_merge( 
							$settings['exclude_paths'],  
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
						$target_checksums = $target_checksums['checksums'];
						foreach ( $settings['checksums'] as $path_hash => $file ) {
							if ( in_array( $file['path'], $exclude_paths ) || false !== strpos( $file['path'], 'plugins/instawp-connect' ) || false !== strpos( $file['path'], 'instawp-autologin' ) ) {
								continue;
							}
							if ( ! isset( $target_checksums[$path_hash] ) || ( $target_checksums[$path_hash]['path'] === $file['path'] && $target_checksums[$path_hash]['checksum'] !== $file['checksum'] ) ) {
								$action['to_send'][] = $file;
							}
						}
						foreach ( $target_checksums as $path_hash => $file ) {
							if ( ! isset( $settings['checksums'][$path_hash] ) && ! in_array( $file['path'], $exclude_paths ) && false === strpos( $file['path'], 'instawp-autologin' ) ) {
								$action['to_delete'][] = $file;
							}
						}

						$changed_files = 0;
						$deleted_files = 0;
						if ( ! empty( $action['to_send'] ) ) {
							foreach ( $action['to_send'] as $file ) {
								WP_CLI::log( "File: " . $file['path'] . " is changed." );
							}
							$changed_files = count( $action['to_send'] );
						}
						if ( ! empty( $action['to_delete'] ) ) {
							foreach ( $action['to_delete'] as $file ) {
								WP_CLI::log( "File: " . $file['path'] . " is deleted." );
							}
							$deleted_files = count( $action['to_delete'] );
						}

						WP_CLI::log( __( 'Files Changed:', 'instawp-connect' ) . ' ' . $changed_files );
						WP_CLI::log( __( 'Files Deleted:', 'instawp-connect' ) . ' ' . $deleted_files );
					}
					WP_CLI::success( "Detecting files changes completed in " . ( time() - $start ) . " seconds" );
				}
			} else if ( $command === 'push-db' ) {
				WP_CLI::log( "Detecting database changes..." );
				$start = time();
				$exclude_tables = array_merge( 
					$this->helper->exclude_tables(), 
					empty( $assoc_args['exclude-tables'] ) ? array() : explode( ',', $assoc_args['exclude-tables'] ) 
				);
				$include_tables = empty( $assoc_args['include-tables'] ) ? array() : explode( ',', $assoc_args['include-tables'] );
				// Tables which are in exclude_tables but not in include_tables
				$exclude_tables = array_diff( $exclude_tables, $include_tables );
				
				// Target DB
				$target_db = $this->call_api( 'db-schema' );
				$tables = $this->helper->get_tables();
				if ( empty( $tables ) || empty( $target_db['tables'] ) ) {
					WP_CLI::error( __( 'No tables found.', 'instawp-connect' ) );
				}
				
				$actions = array(
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
						$actions['drop_tables'][] = $table;
					}
				}

				// flag to check if push db was successful
				$success_push_db = true;
				$db_meta = get_option( $this->db_meta_name, array() );
				$new_tables_meta = array();
				foreach ( $tables as $table ) {
					// Skip excluded tables
					if ( in_array( $table, $exclude_tables ) ) {
						WP_CLI::log( "Skipping table: " . $table );
						continue;
					}
					// Action required
					$actions['tables'][ $table ] = array(
						'insert_start_id' => 0, // Insert start from this id
						'insert_end_id' => 0, // Insert end at this id
						'delete_start_id' => 0, // Delete start from this id
						'delete_end_id' => 0, // Delete end at this id
						'insert_data' => array(),
						'full_insert' => false,
						'update_data' => array(),
						'delete_data' => array(),
					);
					$target_table_name = str_replace( $wpdb->prefix, $target_db['table_prefix'], $table );
					// Create tables
					if ( ! in_array( $target_table_name, $target_db['tables'] ) ) {
						$actions['create_tables'][] = $table;
						continue;
					}
					// Source table meta
					$meta = $this->helper->get_table_meta( array( $table ), true );

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

					$target_table_meta = $this->call_api( 'table-checksum', array( 'table' => $target_table_name ) );
					// Check if table exists
					if ( $target_table_meta['table'] !== $target_table_name ) {
						// Table mismatch
						WP_CLI::error( __( 'Table mismatch: ', 'instawp-connect' ) . $table . ' vs ' . $target_table_meta['table'] );
					}

					// Check if target table is empty
					if ( 0 === absint( $target_table_meta['rows_count'] ) ) {
						// Insert all source table data to target
						$actions['tables'][ $table ]['full_insert'] = true;
						continue;
					}

					// Check if target table has primary key
					if ( ! empty( $meta['primary_key'] ) ) {
						// Continue if table has no checksum
						if ( ! isset( $meta['last_id'] ) ) {
							WP_CLI::error( __( 'Table: ', 'instawp-connect' ) . $table . __( ' has no primary key.', 'instawp-connect' ) );
						}
						
						$this->update_db_action( $actions, $table, $meta, $target_table_meta );

						// Get last id from target|source table where insertion is smaller
						$last_id_to_process = absint( $target_table_meta['last_id'] < $meta['last_id'] ? $target_table_meta['last_id'] : $meta['last_id'] ); 
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
							// Update action data
							$this->update_db_action( $actions, $table, $meta, $target_table_meta );
							// Get next start id
							$next_start_id = absint( $meta['next_start_id'] );
						}

						// Get first id from target|source table where insertion start
						if ( $target_table_meta['last_id'] < $meta['last_id'] ) {
							$actions['tables'][ $table ]['insert_start_id'] = absint( $target_table_meta['last_id'] ) + 1;
							$actions['tables'][ $table ]['insert_end_id'] = absint( $meta['last_id'] );
						} else {
							$actions['tables'][ $table ]['delete_start_id'] = absint( $meta['last_id'] ) + 1;
							$actions['tables'][ $table ]['delete_end_id'] = absint( $target_table_meta['last_id'] );
						}

					} else if ( ! empty( $meta['checksum'] ) ) {
						// Clone tables if checksum mismatch for tables without primary key
						if ( $meta['checksum'] !== $target_table_meta['checksum'] ) {
							$actions['clone_tables'][] = $table;
						}
					}
				}

				foreach ($actions['tables'] as $table => $value) {
					WP_CLI::log( 'Table ' . $table . ': ' . json_encode( $value ) );
				}

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
					update_option( $this->db_meta_name, $db_meta );
				}

				WP_CLI::success( "Detecting database changes completed in " . ( time() - $start ) . " seconds" );
			}

			WP_CLI::success( "Run Successfully" );
		}

		/**
		 * Update DB action
		 * 
		 */
		private function update_db_action( &$action, $table, $meta, $target_table_meta ) {
			if ( empty( $meta['rows_checksum'] ) || empty( $target_table_meta['rows_checksum'] ) ) {
				return;
			}
			// For same first id and last id query in source and target table
			if ( $meta['checksum_key'] === $target_table_meta['checksum_key'] ) {
				// Target site rows checksum
				$target_checksum = $target_table_meta['rows_checksum'][$target_table_meta['checksum_key']];
				// Source site rows checksum	
				$source_checksum = $meta['rows_checksum'][$meta['checksum_key']];
				if ( $target_checksum['rows_count'] !== $source_checksum['rows_count'] || $target_checksum['ids'] !== $source_checksum['ids'] ||( ! empty( $target_checksum['meta_hash'] ) && $target_checksum['meta_hash'] !== $source_checksum['meta_hash'] ) || ( ! empty( $target_checksum['content_hash'] ) && $target_checksum['content_hash'] !== $source_checksum['content_hash'] ) ) {
					$target_checksum['ids'] = empty( $target_checksum['ids'] ) ? array(): explode( ',', $target_checksum['ids'] );
					$source_checksum['ids'] = empty( $source_checksum['ids'] ) ? array(): explode( ',', $source_checksum['ids'] );
					// Update rows
					$actions['tables'][ $table ]['update_data'][] = $source_checksum['ids'];
					// ID which are in target table but not in source
					$deleted_ids = array_diff( $target_checksum['ids'], $source_checksum['ids'] );
					if ( ! empty( $deleted_ids ) ) {
						// Delete rows
						$actions['tables'][ $table ]['delete_data'][] = $deleted_ids;
					}
				}
			} else {
				WP_CLI::error( __( 'Checksum key mismatch: ', 'instawp-connect' ) . $meta['checksum_key'] . ' vs ' . $target_table_meta['checksum_key'] );
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



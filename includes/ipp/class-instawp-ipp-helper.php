<?php
/**
 * InstaWP Iterative Pull Push Helper
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'INSTAWP_IPP_HELPER' ) ) {
	class INSTAWP_IPP_HELPER {

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
		 * Is cli
		 */
		private $is_cli = false;


		public function __construct( $is_cli = false ) {
			$this->is_cli = $is_cli;
		}

		/**
		 * Print message
		 * 
		 * @param string $message
		 */
		public function print_message( $message, $error = false ) {
			if ( $this->is_cli ) {
				if ( $error ) {
					WP_CLI::error( $message );
				}
				WP_CLI::warning( $message );
			}
		}


		/**
		 * Output a simple progress bar to the console.
		 *
		 * @param int $done  The number of items already processed.
		 * @param int $total The total number of items to be processed.
		 * @param int $width Optional. The width of the progress bar in characters. Default is 50.
		 */
		public function progress_bar($done, $total, $width = 50) {
			if ( ! $this->is_cli ) {
				return;
			}
			// Calculate the progress
			$progress = ($done / $total);
			$barWidth = floor($progress * $width);
			$percent = floor($progress * 100);
		
			$bar = str_repeat('-', $barWidth);
			$spaces = str_repeat(' ', $width - $barWidth);
		
			printf("\r[%s%s] %d%% ", $bar, $spaces, $percent);
		
			if ($done === $total) {
				echo PHP_EOL; // Move to the next line when complete
			}
		}

		/**
		 * Retrieves an iterator for all files in a directory, excluding specified folders.
		 *
		 * This function creates a recursive iterator that traverses the directory
		 * structure starting from the given root path. It applies a filter to exclude
		 * any directories or files specified in the $exclude_paths array.
		 *
		 * @param string $root         The root directory path to start the iteration from.
		 * @param array  $exclude_paths An array of paths to skip during iteration.
		 * @param array  $skip_subfolders An array of subfolders to skip during iteration.
		 * 
		 *
		 * @return RecursiveIteratorIterator An iterator for files in the directory,
		 *                                   excluding the specified folders.
		 */
		public function get_iterator_items( $root, $exclude_paths = array(), $skip_subfolders = array() ) {
			$is_subfolder_check = count( $skip_subfolders ) > 0; 
			$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $exclude_paths, $is_subfolder_check, $skip_subfolders ) {
	
				$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . '/' . $file->getBasename() : $file->getBasename();
				$relative_path = trim( $relative_path, '/' );
				$relative_path = trim( $relative_path, '/\\' );
				if ( in_array( $relative_path, $exclude_paths ) ) {
					return false;
				}

				if ( $is_subfolder_check ) {
					foreach ( $skip_subfolders as $subfolder ) {
						if ( false !== strpos( wp_normalize_path( $relative_path ), '/' . $subfolder . '/' ) ) {
							return false;
						}
					}
				}
	
				return ! in_array( $iterator->getSubPath(), $exclude_paths );
			};
			
			$directory        = new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
	
			return new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD );
		}

		/**
		 * Generate checksum for a single file using multiple factors
		 *
		 * @param string $filepath Path to the file
		 * @param int $filesize Size of the file
		 * @param string $relative_path Relative path of the file
		 * 
		 * @return string|false Returns file checksum or false on failure
		 */
		public function get_file_checksum( $filepath, $filesize, $relative_path ) {

			try {
				// Read file samples
				$handle = fopen( $filepath, 'rb' );
				if ( $handle === false ) {
					$this->print_message( 'Failed to open file: ' . $filepath );
					return false;
				}

				// Read first 4KB
				$header   = fread( $handle, 4096 );
				if ( $header === false ) {
					$this->print_message( 'Failed to read file: ' . $filepath );
					fclose( $handle );
					return false;
				}
				$checksum = crc32( $header );

				// For files less than 16KB, read end
				if ( $filesize > 4096 && $filesize < 16384 ) {
					// Read last 4KB
					if ( fseek( $handle, -4096, SEEK_END ) === 0 ) {
						$tail = fread( $handle, 4096 );
						if ( $tail !== false ) {
							$checksum ^= crc32( $tail );
						}
					}
				} else if ( $filesize >= 16384 ) {
					// Read middle 4KB
					$middle_position = (int) ( $filesize / 2 ) - 2048;
					if ( fseek( $handle, $middle_position, SEEK_SET ) === 0 ) {
						$middle = fread( $handle, 4096 );
						if ( $middle !== false ) {
							$checksum ^= crc32( $middle );
						}
					}

					// Read last 4KB
					if ( fseek( $handle, -4096, SEEK_END ) === 0 ) {
						$tail = fread( $handle, 4096 );
						if ( $tail !== false ) {
							$checksum ^= crc32( $tail );
						}
					}
				} 
			} finally {
				if ( isset( $handle ) && $handle !== false ) {
					fclose( $handle );
				}
			}

			// Combine with path and size
			$checksum ^= crc32( $relative_path );
			$checksum ^= $filesize;

			// Convert to unsigned
			return sprintf('%u', $checksum);;
		}


		/**
		 * Get checksums for all files in a directory
		 *
		 * @param string $dir Directory path
		 * 
		 * @return array|false Array of filepath => checksum pairs or false on failure
		 */
		public function get_file_settings( $args = array() ) {
			if ( ! defined( 'ABSPATH' ) || ! is_dir( ABSPATH ) ) {
				$this->print_message( 'ABSPATH not defined or invalid directory' );
				return false;
			}

			$args = wp_parse_args( $args, array( 
				'exclude_paths' => array(),
				'include_paths' => array(),
				'exclude_core' => false,
				'exclude_uploads' => false,
				'exclude_large_files' => false,
			) );
			
			$settings = array(
				'exclude_paths' => array( 
					'wp-content/cache', 
					'editor', 
					'wp-content/upgrade', 
					'wp-content/instawpbackups',
				),
				'checksums' => array(),
			);
			// Saved settings
			$checksum_repo =  get_option( $this->file_checksum_name, array() );
			// Exclude core	files
			if ( $args['exclude_core'] ) {
				$settings['exclude_paths'][] = array(
					'wp-includes',
					'wp-admin',
					'index.php',
					'readme.html',
					'wp-config-sample.php',
					'wp-activate.php',
					'wp-config.php',
					'wp-load.php',
					'wp-mail.php',
					'wp-login.php',
					'wp-settings.php',
					'wp-signup.php',
					'wp-activate.php',
					'wp-blog-header.php',
					'wp-comments-post.php',
					'wp-cron.php',
					'wp-links-opml.php',
					'wp-trackback.php',
					'xmlrpc.php',
				);
			}
			// Exclude uploads
			if ( $args['exclude_uploads'] ) {
				$upload_dir      = wp_upload_dir();
				$upload_base_dir = isset( $upload_dir['basedir'] ) ? $upload_dir['basedir'] : '';
				if ( ! empty( $upload_base_dir ) ) {
					$settings['exclude_paths'][] = str_replace( ABSPATH, '', $upload_base_dir );
				}
			}

			if ( ! empty( $args['exclude_paths'] ) ) {
				$settings['exclude_paths'] = array_merge( $settings['exclude_paths'], $args['exclude_paths'] );
			}
			$settings['exclude_paths'] = array_unique( $settings['exclude_paths'] );

			if ( ! empty( $args['include_paths'] ) ) {
				// Array that contains the entries from exclude_paths that are not present in include_paths 
			    $settings['exclude_paths'] = array_diff( $settings['exclude_paths'], $args['include_paths'] );
			}
			 
			$settings['exclude_paths'] = array_map( 'wp_normalize_path', $settings['exclude_paths'] );
			
			// prepare_large_files_list();
			if ( $args['exclude_large_files'] ) {
				$maxbytes = (int) get_option( 'instawp_max_filesize_allowed', INSTAWP_DEFAULT_MAX_filesize_ALLOWED );
				$maxbytes = $maxbytes ? $maxbytes : INSTAWP_DEFAULT_MAX_filesize_ALLOWED;
				$maxbytes = ( $maxbytes * 1024 * 1024 );
			}

			$abspath = wp_normalize_path( ABSPATH );
			
			$files = $this->get_iterator_items( 
				$abspath,
				$settings['exclude_paths'],
				array(
					'node_modules',
					'.github',
					'.git',
					'.gitignore',
					'.gitattributes',
					'.distignore',
					'.vscode',
					'.wordpress-org',
				)
			);

			$total = iterator_count( $files );
			// should be multiple of 1000
			$limit = empty( $args['files_limit'] ) ? 0 : absint( $args['files_limit'] );
			$is_limit = $limit > 0;
			if ( $is_limit ) {
				$limit_processed_count = get_option( $this->file_checksum_name . '_processed_count', 0 );
				if ( $limit_processed_count >= $total || 'completed' === $limit_processed_count ) {
					$this->print_message( 'Checksum processing completed' );
					return false;
				}
			}
			// Number of items actually processed
			$processed_files = 0;
			// Batch size to save checksum
			$batch = $is_limit ? $limit : 1000;
			$settings['total_files'] = $total;
			$processed = 0;
			// Missed files in processing
			$missed = array();

			foreach ( $files as $file ) {
				$processed += 1;
				$filepath    = wp_normalize_path( $file->getPathname() );
				if ( empty( $filepath ) ) {
					continue;
				}
				
				if ( $file->isFile() && $this->is_valid_file( $filepath ) ) {
					// Calculate relative path from WordPress root
					$relative_path = str_replace( 
						$abspath, 
						'', 
						$filepath
					);
					if ( $relative_path[0] === '.' ) {
						continue;
					}
					$rel_path_hash = md5( $relative_path ); // relative path hash
					$filesize    = $file->getSize(); // file size
					$filetime = $file->getMTime(); // file modified time
					$is_save_checksums = $processed % $batch === 0;

					// Output progress
					if ( $is_save_checksums ) {
						$this->progress_bar( $processed, $total );
					}

					if ( ! is_readable( $filepath ) ) {
						$missed[] = array( 
							'relative_path' => $relative_path,
							'message' => 'File is not readable', 
						);
						continue;
					}

					// Skip large files
					if ( ! empty( $args['exclude_large_files'] ) && $filesize > $maxbytes && strpos( $filepath, 'instawpbackups' ) === false ) {
						if ( ! in_array( $relative_path, $exclude_paths ) ) {
							$settings['exclude_paths'][] = $relative_path;
						}
						continue;
					}

					//Get checksum from transient
					if ( ! empty( $checksum_repo[ $rel_path_hash ] ) && $checksum_repo[ $rel_path_hash ]['filetime'] === $filetime ) {
						$settings['checksums'][ $rel_path_hash ] = $checksum_repo[ $rel_path_hash ];
						continue;
					}
					
					//WP_CLI::log( 'Preparing Checksum for ' . $relative_path );
					$file_checksum = $this->get_file_checksum( $filepath, $filesize, $relative_path );
					if ( false === $file_checksum ) {
						$missed[] = array( 
							'relative_path' => $relative_path,
							'message' => 'Failed to get file checksum', 
						);
						continue;
					}

					$checksum_repo[ $rel_path_hash ] = array(
						'checksum' => $file_checksum,
						'size'     => $filesize,
						'path'     => $relative_path,
						'filetime' => $filetime,
					);

					// Add to settings
					$settings['checksums'][ $rel_path_hash ] = $checksum_repo[ $rel_path_hash ];

					$processed_files++;

					if ( $is_save_checksums ) {
						// Update checksum repo
						update_option( $this->file_checksum_name, $checksum_repo );
						if ( $is_limit ) {
							update_option( $this->file_checksum_name . '_processed_count', $processed );
							break;
						}
					}
				}
			}

			if ( 0 === $processed_files ) {
				if ( $is_limit ) {
					update_option( $this->file_checksum_name . '_processed_count', 'completed' );
				}
			} else {
				// Update checksum repo
				update_option( $this->file_checksum_name, $checksum_repo );
				if ( $is_limit ) {
					update_option( $this->file_checksum_name . '_processed_count', $processed );
				}
			}
			
			$total_missed = count( $missed );
			// Output progress
			$this->progress_bar( $total - $total_missed, $total );
			if ( $total_missed > 0 ) {
				$this->print_message( 'Missed files: ' . json_encode( $missed ) );
			}

			return $settings;
		}

		/**
		 * Check if a file is valid to be processed.
		 *
		 * @param string $filepath Full path to the file.
		 *
		 * @return bool True if the file is valid, false otherwise.
		 */
		public function is_valid_file( $filepath ) {
			if ( ! file_exists( $filepath ) ) {
				return false;
			}
			if ( ! is_file( $filepath ) || $filepath[0] === '.' ) {
				return false;
			}

			$filename = basename( $filepath );
			// Check for disallowed characters
			$disallowed = array( '/', '\\', ':', '*', '?', '"', '<', '>', '|' );
			foreach ( $disallowed as $char ) {
				if ( strpos( $filename, $char ) !== false ) {
					return false;
				}
			}

			// Check for disallowed characters and file start with dot
			if ( $filename === '.' || $filename === '..' || $filename[0] === '.' ) {
				return false;
			}

			return true;
		}

		/**
		 * Check if any of the strings are present in the content.
		 *
		 * @param string $content The content to check.
		 * @param array  $strings The strings to check for.
		 *
		 * @return bool True if any of the strings are present in the content, false otherwise.
		 */
		public function has_any_string( $content, $strings ) {
			foreach ( $strings as $string ) {
				if ( false !== stripos( $content, $string ) ) {
					return true;
				}
			}
			return false;
		}


		/**
		 * Get the checksum of all tables in the database.
		 *
		 * @return array The checksums of all tables in the database.
		 */
		public function get_tables_meta( $is_cron = false ) {
			$tables_meta = array();
			$tables = $this->get_tables();

			foreach ( $tables as $table ) {
				$tables_meta[ $table ] = $this->get_table_meta( $table );
			}
			return $tables_meta;
		}

		/**
		 * Get the checksum key.
		 *
		 * @param int $start_id The start id of the table.
		 * @param int $end_id The end id of the table.
		 *
		 * @return string The checksum key.
		 */
		public function get_checksum_key( $start_id, $end_id ) {
			return $start_id . '-' . $end_id;
		}

		/**
		 * Get end id.
		 *
		 * @param int $start_id The start id of the table.
		 *
		 * @return int The end id.
		 */
		public function get_end_id( $start_id ) {
			return absint( absint( $start_id ) + $this->rows_limit_per_query );
		}

		/**
		 * Get meta and checksum of a table.
		 *
		 * @param array  $tables The tables to get the checksum of.
		 * @param bool   $is_api_call is api call.
		 *
		 * @return array The checksums of the tables.
		 */
		public function get_table_meta( $tables, $is_api_call = false, $start_id = 1, $last_id_to_process = 0 ) {
			global $wpdb;
			$db_meta = get_option( $this->db_meta_name, array() );
			$meta = array();
			try {
				// Get last run data
				if ( $is_api_call ) {
					// Set transient for 30 minutes
					$last_transient = get_transient( $this->db_meta_name . '_last_run_transient' );
					if ( ! empty( $last_transient ) && $last_transient['table'] === $tables[0] ) {
						$last_run_data = $last_transient;
					}
				} else {
					$last_run_data = get_option( $this->db_meta_name . '_last_run_data' );
				}

				$last_run_data = empty( $last_run_data ) ? array(): $last_run_data;
				$table = isset( $last_run_data['table'] ) ? $last_run_data['table']: $tables[0];
				$table = $this->prepare_table_name( $table );
				$start_id = absint( isset( $last_run_data['start_id'] ) ? $last_run_data['start_id']: $start_id );
				if ( isset( $last_run_data['home_url'] ) ) {
					$home_url = $last_run_data['home_url'];
				} else {
					// Get home url
					$home_url = home_url();
					// Remove protocol
					$home_url = str_replace( 'http://', '', $home_url );
					$home_url = str_replace( 'https://', '', $home_url );
				}
			
				// Get table meta
				if ( empty( $last_run_data['meta'] ) ) {	
					$table_meta = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $table, ARRAY_A );
					if ( empty( $table_meta ) || ! isset( $table_meta[0]['Field'] ) ) {
						return $meta;
					}
					$primary_key = '';
					$modified_at_field = '';
					$maybe_url_fields = array();
					$non_url_fields = array();
					foreach ( $table_meta as $meta_key => $meta_value ) {

						if ( $primary_key === '' && isset( $meta_value['Key'] ) && $meta_value['Key'] === 'PRI' && isset( $meta_value['Extra'] ) && $meta_value['Extra'] === 'auto_increment' ) {
							// Found primary key
							$primary_key = $meta_value['Field'];
						} else if ( $modified_at_field === '' && ( false !== strpos( $meta_value['Field'], 'modified' ) || false !== strpos( $meta_value['Field'], 'updated' ) ) && $meta_value['Type'] === 'timestamp' ) {
							//  Found modified_at_field
							$modified_at_field = $meta_value['Field'];
						} else if ( in_array( $meta_value['Type'], array( 'text', 'mediumtext', 'longtext' ) ) || ( 12 === strlen( $meta_value['Type'] ) && 0 === stripos( $meta_value['Type'], 'varchar' ) ) ) {
							// at least varchar(100) or text, mediumtext, longtext
							$maybe_url_fields[] = $meta_value['Field'];
						} else {
							$non_url_fields[] = $meta_value['Field'];
						}
						
					}

					$table_fields = array_column( $table_meta, 'Field' );
					$meta = array(
						'table' => $table,
						'fields' => $table_fields,
						'primary_key' => $primary_key,
						'modified_at_field' => $modified_at_field,
						'checksum' => '',
						'rows_checksum' => array(),
						'rows_count' => $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $table ),
						'maybe_url_fields' => $maybe_url_fields,
						'non_url_fields' => $non_url_fields,
						'time' => time(),
					);
					// Check if table is empty
					if ( 0 >= $meta['rows_count'] ) {
						return $meta;
					}
					if ( $is_api_call ) {
						// Set transient for 30 minutes
						set_transient( $this->db_meta_name . '_last_run_transient', array(
							'table' => $table,
							'home_url' => $home_url,
							'meta' => $meta
						), 1800 );
					}
				} else {
					$meta = $last_run_data['meta'];
				}

				$last_run_data = array();

				$meta['last_modified_at'] = empty( $meta['modified_at_field'] ) ? '' : $wpdb->get_var( 'SELECT MAX(' . $meta['modified_at_field'] . ') FROM ' . $table );
				$start = $start_id;
				
				if ( ! empty( $meta['primary_key'] ) ) {
					$query = $this->prepare_url_non_url_query( $meta['maybe_url_fields'], $meta['non_url_fields'], $home_url );
					$meta['last_id'] = $wpdb->get_var( 'SELECT ' . $meta['primary_key'] . ' FROM ' . $table . ' ORDER BY ' . $meta['primary_key'] . ' DESC LIMIT 1' );

					// Get checksum
					$end_id = $this->get_end_id( $start_id );
					if ( $last_id_to_process > 0 && $last_id_to_process < $end_id ) {
						$end_id = $last_id_to_process;
						$meta['next_start_id'] = 0;
					} else if ( $end_id > $meta['last_id'] ) {
						$end_id = $meta['last_id'];
						$meta['next_start_id'] = 0;
					}
					$meta['end_id'] = $end_id;
					$checksum_key = $this->get_checksum_key( $start_id, $end_id );
					$meta['checksum_key'] = $checksum_key;

					if ( ! empty( $meta['modified_at_field'] ) && 0 < $meta['rows_count'] ) {
						// Check if checksum is already stored and updated
						if ( $is_api_call ) {
							if ( ! empty( $db_meta ) && ! empty( $db_meta['meta'][ $table ] ) && ! empty( $db_meta['meta'][ $table ]['rows_checksum'][ $checksum_key ] ) ) {
								$query = $wpdb->prepare(
									"SELECT COUNT(*) AS rows_count, MAX(".$meta['modified_at_field'].") AS last_modified_at FROM $table WHERE ".$meta['primary_key']." BETWEEN %d AND %d;",
									$start,
									$end_id
								);
								$results = $wpdb->get_row( $query, ARRAY_A );
								if ( ! empty( $results ) && $results['last_modified_at'] === $db_meta['meta'][ $table ]['rows_checksum'][ $checksum_key ]['last_modified_at'] && intval( $results['rows_count'] ) === intval( $db_meta['meta'][ $table ]['rows_checksum'][ $checksum_key ]['rows_count'] ) ) {
									// Use last run data checksum
									$meta['rows_checksum'][ $checksum_key ] = $db_meta['meta'][ $table ]['rows_checksum'][ $checksum_key ];
								}
							}
						}

						if ( empty( $meta['rows_checksum'][ $checksum_key ] ) ) {

							$query = $wpdb->prepare(
								"SELECT COUNT(*) AS rows_count, GROUP_CONCAT(".$meta['primary_key'].") AS ids, MAX(".$meta['modified_at_field'].") AS last_modified_at, $query FROM $table WHERE ".$meta['primary_key']." BETWEEN %d AND %d",
								$start,
								$end_id
							);

							$results = $wpdb->get_row( $query, ARRAY_A );

							if ( ! empty( $results ) ) {
								$meta['rows_checksum'][ $checksum_key ] = $results;
								$this->update_table_meta_repo( $is_api_call, $db_meta, $checksum_key, $meta, $results );
							}
						}
						
					} else if ( 0 < $meta['rows_count'] ) {
						$query = $wpdb->prepare(
							"SELECT COUNT(*) AS rows_count, GROUP_CONCAT(".$meta['primary_key'].") AS ids, $query FROM $table WHERE ".$meta['primary_key']." BETWEEN %d AND %d",
							$start,
							$end_id
						);

						$results = $wpdb->get_row( $query, ARRAY_A );
						
						if ( ! empty( $results ) ) {
							$meta['rows_checksum'][ $checksum_key ] = $results;
							$this->update_table_meta_repo( $is_api_call, $db_meta, $checksum_key, $meta, $results );
						}
					}

					if ( $is_api_call && ! empty( $meta['rows_checksum'] ) ) {
						$meta['checksum'] = $meta['rows_checksum'][ $checksum_key ];
					}
					 
					if ( $end_id != $meta['last_id'] ) {
						$start = $end_id + 1;
						$meta['next_start_id'] = $start;
					}
				} else if ( $is_api_call ) {
					$meta['checksum'] = $this->get_table_checksum( $table, $meta, $home_url );
				}

				if ( ! $is_api_call ) {
					if ( $start_id === $start ) {
						$key = array_search( $table, $tables );
						if ( false !== $key && $key < ( count( $tables ) - 1 ) ) {
							$last_run_data = array(
								'table' => $tables[ $key + 1 ],
								'start_id' => 1,
								'home_url' => $home_url,
							);
						}
					} else {
						$last_run_data = array(
							'table' => $table,
							'start_id' => $start,
							'home_url' => $home_url,
							'meta' => $meta
						);
					}
	
					update_option( $this->db_meta_name . '_last_run_data', $last_run_data );
				}
			} catch (\Exception $th) {
				error_log( "IWP IPP Helper: get_table_meta: " . $th->getMessage() );
			}
			
			return $meta;
		}

		public function prepare_update_query( $table, $meta, $home_url ) {

		}

		/**
		 * Update table meta repo.
		 *
		 * @param bool $should_update
		 * @param array $db_meta
		 * @param string $checksum_key
		 * @param array $meta
		 * @param string $checksum
		 */
		private function update_table_meta_repo( $should_update, $db_meta, $checksum_key, $meta, $checksum = '' ) {
			if ( ! $should_update ) {
				return;
			}
			if ( empty( $db_meta ) ) {
				$db_meta = array();
			}
			if ( empty( $db_meta['meta'] ) ) {
				$db_meta['meta'] = array();
			}
			if ( empty( $db_meta['meta'][ $meta['table'] ] ) ) {
				$db_meta['meta'][ $meta['table'] ] = array();
			}
			if ( empty( $db_meta['meta'][ $meta['table'] ]['rows_checksum'] ) ) {
				$db_meta['meta'][ $meta['table'] ]['rows_checksum'] = array();
			}
			foreach ( $meta as $key => $value) {
				if ( $key !== 'rows_checksum' ) {
					$db_meta['meta'][ $meta['table'] ][ $key ] = $value;
				}
			}
			if ( ! empty( $checksum ) ) {
				$db_meta['meta'][ $meta['table'] ]['rows_checksum'][ $checksum_key ] = $checksum;
			}
			update_option( $this->db_meta_name, $db_meta );
		}

		/**
		 * Prepare url non url query.
		 *
		 * @param array  	$maybe_url_fields   may be url fields array
		 * @param array  	$non_url_fields     non url fields array
		 * @param string  	$home_url           home url
		 *
		 * @return string query
		 */
		public function prepare_url_non_url_query( $maybe_url_fields, $non_url_fields, $home_url ) {
			$query = '';
			if ( empty( $home_url ) ) {
				$this->print_message( 'Error: home_url is empty.', true );
				return $query;
			}
			// Prepare non url fields placeholders
			if ( 0 < count( $non_url_fields ) ) {
				$non_url_fields = array_map( function( $field ) {
					return sprintf( "IFNULL(`%s`, '')", $field );
				}, $non_url_fields );
				$non_url_fields = implode( ',', $non_url_fields );
				$query = "BIT_XOR(CAST(CRC32(CONCAT_WS('#', $non_url_fields )) AS UNSIGNED)) AS meta_hash";
			}

			// Prepare maybe url fields placeholders. Replace home url with empty string
			if ( 0 < count( $maybe_url_fields ) ) {
				$maybe_url_fields = array_map( function( $field ) use ( $home_url ) {
					return sprintf( " CASE WHEN `%s` IS NULL OR `%s` = '' THEN '' ELSE REPLACE(`%s`, '%s', '') END", $field, $field, $field, $home_url );
				}, $maybe_url_fields );
				$maybe_url_fields = implode( ',', $maybe_url_fields );
				$query = ( empty( $query ) ? "" : $query .", " ) . "BIT_XOR(CAST(CRC32(CONCAT_WS('#', $maybe_url_fields )) AS UNSIGNED)) as content_hash";
			}
			return $query;
		}

		/**
		 * Update the database checksum.
		 *
		 * @param string $key   The key of the checksum.
		 * @param string $value The value of the checksum.
		 */
		private function update_db_checksum( $key, $value ) {
			$db_checksums = get_option( $this->db_meta_name );
			if ( empty( $db_checksums ) ) {
				$db_checksums = array();
			}
			$db_checksums[$key] = $value;
			$db_checksums['time'] = time();
			update_option( $this->db_meta_name, $db_checksums );
		}

		/**
		 * Save the checksum of all tables in the database.
		 */
		public function save_tables_checksum() {
			$this->update_db_checksum( 'tables_meta', $this->get_tables_meta() );
		}

		/**
		 * Get the checksum of a table.
		 *
		 * @param string $table The name of the table to get the checksum of.
		 * @param string $home_url The home url of the site.
		 *
		 * @return string The checksum of the table.
		 */
		public function get_table_checksum( $table,$meta, $home_url ) {
			global $wpdb;
			$query = $this->prepare_url_non_url_query( $meta['maybe_url_fields'], $meta['non_url_fields'], $home_url );
			if ( empty( $query ) ) {
				return false;
			}
			$query = "SELECT COUNT(*) AS rows_count, $query FROM $table";
			$results = $wpdb->get_row( $query, ARRAY_A );
			return empty( $results ) ? false : $results;
		}

		/**
		 * Get and set list of tables in the database.
		 *
		 * @return array The list of tables in the database.
		 */
		public function get_tables() {
			global $wpdb;
			$tables = $wpdb->get_col( "SHOW TABLES" );
			return $tables;
		}

		public function prepare_table_name( $table ) {
			global $wpdb;
			return 0 === strpos( $table, $wpdb->prefix ) ? $table : $wpdb->prefix . $table;
		}

		/**
		 * Get the database schema.
		 *
		 * @return array The database schema.
		 */
		public function get_db_schema() {
			global $wpdb;
			return array(
				'tables' => $this->get_tables(),
				'table_prefix' => $wpdb->prefix,
			);
		}

		public function iwp_backoff_timer( $attempt, $base = 2, $maxWait = 300 ) {
			// Calculate the backoff time
			$waitTime = min( $base ** $attempt, $maxWait );
	
			echo "Backing off .. $waitTime seconds\n";
	
			// Return the wait time
			return $waitTime;
		}
	
	
		public function iwp_send_migration_log( $label = '', $description = '', $payload = [], $echo = true ) {
	
			if ( $echo ) {
				echo $description . "\n";
			}
	
			global $migrate_id, $bearer_token;
	
			$log_data = array(
				'migrate_id'  => $migrate_id,
				'type'        => 'instacp',
				'label'       => $label,
				'description' => $description,
				'payload'     => $payload,
			);
			$curl     = curl_init();
			$hostname = gethostname();
	
			if ( strpos( $hostname, 'production' ) !== false ) {
				$apiDomain = 'app.instawp.io';
			} else {
				$apiDomain = 'stage.instawp.io';
			}
	
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => 'https://' . $apiDomain . '/api/v2/migrates-v3/log',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT      => 'InstaWP Migration Service',
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode( $log_data ),
				CURLOPT_COOKIE         => 'instawp_skip_splash=true',
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Authorization: Bearer ' . $bearer_token
				),
			) );
	
			curl_exec( $curl );
			curl_close( $curl );
		}
	
		public function iwp_send_progress( $progress_files = 0, $progress_db = 0, $stages = [] ) {
	
			global $migrate_key, $migrate_id, $bearer_token;
	
			$curl      = curl_init();
			$post_data = array(
				'migrate_key' => $migrate_key,
				'stage'       => $stages
			);
	
			if ( $progress_files > 0 ) {
				$post_data['progress_files'] = $progress_files;
			}
	
			if ( $progress_db > 0 ) {
				$post_data['progress_db'] = $progress_db;
			}
	
			$hostname = gethostname();
	
			if ( strpos( $hostname, 'production' ) !== false ) {
				$apiDomain = 'app.instawp.io';
			} else {
				$apiDomain = 'stage.instawp.io';
			}
	
			curl_setopt_array( $curl, array(
				CURLOPT_URL            => 'https://' . $apiDomain . '/api/v2/migrates-v3/' . $migrate_id . '/update-status',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING       => '',
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_USERAGENT      => 'InstaWP Migration Service - Pull Files',
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_POSTFIELDS     => json_encode( $post_data ),
				CURLOPT_COOKIE         => 'instawp_skip_splash=true',
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					'Accept: application/json',
					'Authorization: Bearer ' . $bearer_token
				),
			) );
	
			curl_exec( $curl );
			curl_close( $curl );
		}
	
		public function iwp_process_curl_response( $curl_response, $curl_session, $response_headers, &$errors_counter, &$slow_sleep, $calling_from = '' ) {
	
			$header_message = $response_headers['x-iwp-message'] ?? '';
			$status_code    = curl_getinfo( $curl_session, CURLINFO_HTTP_CODE );
	
			if ( $curl_errno = curl_errno( $curl_session ) ) {
	
				$return_message = 'Request to the serve script is failed. Curl response: ' . json_encode( $curl_response ) . 'Here is the actual error message' . curl_error( $curl_session ) . ' -  ' . curl_errno( $curl_session );
	
				iwp_send_migration_log( $calling_from . ': Curl is failed', $return_message );
	
				sleep( iwp_backoff_timer( $errors_counter ) );
	
				$errors_counter ++;
	
				if ( $curl_errno == 52 ) {
					if ( $slow_sleep < 60 ) {
						$slow_sleep ++;
					}
				}
			} else if ( $curl_response === false ) {
	
				$return_message = 'Empty response received from the serve script. Actual error message: ' . curl_error( $curl_session );
	
				iwp_send_migration_log( $calling_from . ': Curl response is false', $return_message );
	
				sleep( iwp_backoff_timer( $errors_counter ) );
	
				$errors_counter ++;
			} else if ( $status_code != 200 ) {
	
				$return_message = 'Response status code is not 200 form serve script. Request ' . $errors_counter . ' is: ' . $status_code . ', Automatically the migration script is going to retry the same request soon. Full response:' . json_encode( $curl_response );
	
				iwp_send_migration_log( $calling_from . ': Curl status code is unexpected', $return_message );
	
				$errors_counter ++;
	
				// if empty , forbidden or too many requests
				if ( $status_code == 0 || $status_code == 403 || $status_code == 429 ) {
					sleep( iwp_backoff_timer( $errors_counter ) );
				}
			} else {
				return [ 'success' => true, 'status_code' => $status_code, 'message' => 'Response Header Message: ' . $header_message ];
			}
	
			$return_message .= ' Response header message: ' . $header_message;
	
			return [ 'success' => false, 'status_code' => $status_code, 'message' => $return_message ];
		}
	
	
	
		public function iwp_process_curl_headers( $curl, $header, &$headers ) {
			$length      = strlen( $header );
			$headerParts = explode( ':', $header, 2 );
	
			if ( count( $headerParts ) < 2 ) {
				return $length;
			}
	
			$name             = strtolower( trim( $headerParts[0] ) );
			$value            = trim( $headerParts[1] );
			$headers[ $name ] = $value;
	
			return $length;
		}
	
	
	
		public function iwp_is_serialized( $data, $strict = true ) {
			// If it isn't a string, it isn't serialized.
			if ( ! is_string( $data ) ) {
				return false;
			}
			$data = trim( $data );
			if ( 'N;' === $data ) {
				return true;
			}
			if ( strlen( $data ) < 4 ) {
				return false;
			}
			if ( ':' !== $data[1] ) {
				return false;
			}
			if ( $strict ) {
				$lastc = substr( $data, - 1 );
				if ( ';' !== $lastc && '}' !== $lastc ) {
					return false;
				}
			} else {
				$semicolon = strpos( $data, ';' );
				$brace     = strpos( $data, '}' );
				// Either ; or } must exist.
				if ( false === $semicolon && false === $brace ) {
					return false;
				}
				// But neither must be in the first X characters.
				if ( false !== $semicolon && $semicolon < 3 ) {
					return false;
				}
				if ( false !== $brace && $brace < 4 ) {
					return false;
				}
			}
			$token = $data[0];
			switch ( $token ) {
				case 's':
					if ( $strict ) {
						if ( '"' !== substr( $data, - 2, 1 ) ) {
							return false;
						}
					} elseif ( ! str_contains( $data, '"' ) ) {
						return false;
					}
				// Or else fall through.
				case 'a':
				case 'O':
				case 'E':
					return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
				case 'b':
				case 'i':
				case 'd':
					$end = $strict ? '$' : '';
	
					return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
			}
	
			return false;
		}
	
		public function iwp_maybe_serialize( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return serialize( $data );
			}
	
			if ( iwp_is_serialized( $data, false ) ) {
				return serialize( $data );
			}
	
			return $data;
		}
	
	
		public function iwp_recursive_unserialize_replace( $data, $search_replace ) {
	
			if ( is_string( $data ) ) {
				return str_replace( array_keys( $search_replace ), array_values( $search_replace ), $data );
			}
	
			if ( is_array( $data ) ) {
				$data = array_map( function ( $item ) use ( $search_replace ) {
					return iwp_recursive_unserialize_replace( $item, $search_replace );
				}, $data );
			} elseif ( is_object( $data ) ) {
				// Check if the object is __PHP_Incomplete_Class
				if ( $data instanceof __PHP_Incomplete_Class ) {
					$className = get_class( $data );
					iwp_send_migration_log( 'Incomplete Class Warning', "Encountered incomplete class: $className. Make sure this class is loaded before unserialization.", [ 'class' => $className ] );
	
					return $data;
				}
	
				$properties = [];
	
				try {
					$reflection = new ReflectionObject( $data );
					$properties = $reflection->getProperties();
				} catch ( Exception $e ) {
					iwp_send_migration_log(
						'Reflection Error',
						"Failed to reflect object of class " . get_class( $data ),
						[ 'error' => $e->getMessage() ]
					);
	
					return $data;
				}
	
				foreach ( $properties as $property ) {
					try {
						$property->setAccessible( true );
						$value     = $property->getValue( $data );
						$new_value = iwp_recursive_unserialize_replace( $value, $search_replace );
						$property->setValue( $data, $new_value );
					} catch ( Exception $e ) {
						// Skip this property if we can't access it
						continue;
					}
				}
			}
	
			return $data;
		}
	
		public function iwp_maybe_unserialize( $data ) {
			if ( iwp_is_serialized( $data ) ) {
				global $search_replace;
	
				$data = @unserialize( trim( $data ) );
	
				if ( is_array( $data ) ) {
					$data = iwp_recursive_unserialize_replace( $data, $search_replace );
				}
			}
	
			return $data;
		}
	
		public function iwp_array_filter_recursive( array $array, callable $callback = null ) {
			$array = is_callable( $callback ) ? array_filter( $array, $callback ) : array_filter( $array );
			foreach ( $array as &$value ) {
				if ( is_array( $value ) ) {
					$value = call_user_func( __FUNCTION__, $value, $callback );
				}
			}
	
			return $array;
		}
	
		public function iwp_parse_db_data( $data ) {
			$values = iwp_maybe_unserialize( $data );
	
			if ( is_array( $values ) && ! empty( $values ) ) {
				$data = iwp_maybe_serialize( iwp_array_filter_recursive( $values ) );
			}
	
			return $data;
		}
	
		public function iwp_search_and_comment_specific_line( $pattern, $file_contents ) {
	
			$matches = array();
	
			if ( preg_match_all( $pattern, $file_contents, $matches, PREG_OFFSET_CAPTURE ) ) {
				foreach ( $matches[0] as $match ) {
					$line_content  = strtok( substr( $file_contents, $match[1] ), "\n" );
					$file_contents = str_replace( $line_content, "// $line_content", $file_contents );
				}
			}
	
			return $file_contents;
		}
	
		public function iwp_process_files( $filePath, $relativePath ) {
	
			global $target_url;
	
			if ( basename( $relativePath ) === '.htaccess' ) {
	
				$content  = file_get_contents( $filePath );
				$tmp_file = tempnam( sys_get_temp_dir(), 'htaccess' );
	
				$dest_domain       = str_replace( array( 'http://', 'https://', 'wp-content/plugins/instawp-connect/dest.php', 'dest.php' ), '', $target_url );
				$dest_domain       = rtrim( $dest_domain, '/' );
				$dest_domain_parts = explode( '/', $dest_domain );
	
				if ( isset( $dest_domain_parts[0] ) ) {
					unset( $dest_domain_parts[0] );
				}
	
				$dest_subdomain = implode( '/', $dest_domain_parts );
	
				if ( ! empty( $dest_subdomain ) ) {
					$content = str_replace( "RewriteBase /", "RewriteBase /{$dest_subdomain}/", $content );
					$content = str_replace( "RewriteRule . /index.php", "RewriteRule . /{$dest_subdomain}/index.php", $content );
				}
	
				if ( file_put_contents( $tmp_file, $content ) ) {
					$filePath = $tmp_file;
				}
			}
	
			return $filePath;
		}
	
		public function iwp_get_iterator_items( $skip_folders, $relativePath ) {
			$filter_directory = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $skip_folders ) {
				$relative_path = ! empty( $iterator->getSubPath() ) ? $iterator->getSubPath() . DIRECTORY_SEPARATOR . $file->getBasename() : $file->getBasename();
	
				if ( in_array( $relative_path, $skip_folders ) ) {
					return false;
				}
	
				return ! in_array( $iterator->getSubPath(), $skip_folders );
			};
			$directory        = new RecursiveDirectoryIterator( $relativePath, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
	
			return new RecursiveIteratorIterator( new RecursiveCallbackFilterIterator( $directory, $filter_directory ), RecursiveIteratorIterator::LEAVES_ONLY );
		}
	
		/**
		 * Retrieves an array of files organized by specified WordPress directory groups.
		 *
		 * This function iterates over files within a given directory, filtering 
		 * and categorizing them into predefined WordPress directory groups such as 
		 * 'uploads', 'plugins', 'themes', etc. Files not matching any specific 
		 * group are categorized under 'root_other'.
		 *
		 * @param array  $excluded_paths An array of directory paths to be excluded from the iteration.
		 * @param string $relative_path  The base directory path to iterate over and apply filtering.
		 *
		 * @return array An associative array where keys represent WordPress directory groups and values are 
		 *               arrays of files belonging to those groups.
		 */
		public function iwp_get_files_array( $excluded_paths, $relative_path ) {
			try {
				$iterator = iwp_get_iterator_items( $excluded_paths, $relative_path );
				// file categories
				$file_category = array(
					'uploads' => 'wp-content' . DIRECTORY_SEPARATOR . 'uploads',
					'plugins' => 'wp-content' . DIRECTORY_SEPARATOR . 'plugins',
					'themes' => 'wp-content' . DIRECTORY_SEPARATOR . 'themes',
					'mu_plugins' => 'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins',
					'wp_content' => 'wp-content',
					'wp_admin' => 'wp-admin',
					'wp_includes' => 'wp-includes',
					'index' => 'index.php'
				);
	
				$files_array = array(
					'uploads' => array(),
					'plugins' => array(),
					'themes' => array(),
					'mu_plugins' => array(),
					'wp_content' => array(),
					'wp_admin' => array(),
					'wp_includes' => array(),
					'root_other' => array(),
					'index' => array()
				);
	
				// Get the file groups
				$file_groups = array_keys( array_intersect_key( $files_array, $file_category ) );
				// Array to check files details printing
				$check_file_groups = array();
	
				foreach ($iterator as $file) {
					$filepath = $file->getPathname();
					$relative_filepath = str_replace($relative_path, '', $filepath);
					$relative_filepath = trim( $relative_filepath, DIRECTORY_SEPARATOR );
	
					// Flag to check if the file belongs to any of the file groups
					$is_file_group_matched = false;
	
					// Check if the file belongs to any of the file groups
					foreach ( $file_groups as $file_group ) {
						if ( 0 === strpos( $relative_filepath, $file_category[ $file_group ] ) ) {
							$files_array[ $file_group ][] = $file;
							$is_file_group_matched = true;
							if ( empty( $check_file_groups[ $file_group ] ) ) {
								echo "File group matched: {$file_group} \n filepath: $filepath \n relative filepath: $relative_filepath \n";
								$check_file_groups[ $file_group ] = 1;
							}
							break;
						}
					}
	
					// If the file does not belong to any of the file groups, add it to the root_other group
					if ( false === $is_file_group_matched ) {
						$files_array['root_other'][] = $file;
					}
				}
				return $files_array;
			} catch ( Exception $e ) {
				iwp_send_migration_log( 'push-files: Failed to get files', $e->getMessage() );
				return array();
			}
		}
	
		public function iwp_get_zip_status_string( $status ) {
			if ( ! class_exists( 'ZipArchive' ) ) {
				return 'ZipArchive Extension is not enabled!';
			}
	
			switch ( $status ) {
				case ZipArchive::ER_OK:
					return 'No error';
				case ZipArchive::ER_MULTIDISK:
					return 'Multi-disk zip archives not supported';
				case ZipArchive::ER_RENAME:
					return 'Renaming temporary file failed';
				case ZipArchive::ER_CLOSE:
					return 'Closing zip archive failed';
				case ZipArchive::ER_SEEK:
					return 'Seek error';
				case ZipArchive::ER_READ:
					return 'Read error';
				case ZipArchive::ER_WRITE:
					return 'Write error';
				case ZipArchive::ER_CRC:
					return 'CRC error';
				case ZipArchive::ER_ZIPCLOSED:
					return 'Containing zip archive was closed';
				case ZipArchive::ER_NOENT:
					return 'No such file';
				case ZipArchive::ER_EXISTS:
					return 'File already exists';
				case ZipArchive::ER_OPEN:
					return 'Can\'t open file';
				case ZipArchive::ER_TMPOPEN:
					return 'Failure to create temporary file';
				case ZipArchive::ER_ZLIB:
					return 'Zlib error';
				case ZipArchive::ER_MEMORY:
					return 'Malloc failure';
				case ZipArchive::ER_CHANGED:
					return 'Entry has been changed';
				case ZipArchive::ER_COMPNOTSUPP:
					return 'Compression method not supported';
				case ZipArchive::ER_EOF:
					return 'Premature EOF';
				case ZipArchive::ER_INVAL:
					return 'Invalid argument';
				case ZipArchive::ER_NOZIP:
					return 'Not a zip archive';
				case ZipArchive::ER_INTERNAL:
					return 'Internal error';
				case ZipArchive::ER_INCONS:
					return 'Zip archive inconsistent';
				case ZipArchive::ER_REMOVE:
					return 'Can\'t remove file';
				case ZipArchive::ER_DELETED:
					return 'Entry has been deleted';
				default:
					return 'Unknown status: ' . $status;
			}
		}
	
		public function iwp_verify_checksum( $file_path, $expected_checksum ) {
			if ( ! file_exists( $file_path ) ) {
				echo "File does not exist: $file_path\n";
	
				return false;
			}
	
			$file_name              = basename( $file_path );
			$file_size              = filesize( $file_path );
			$calculated_checksum    = hash( 'crc32b', $file_name . $file_size );
			$verify_checksum_result = $calculated_checksum === $expected_checksum;
	
			return $verify_checksum_result;
		}
	
		public function iwp_unmark_sent_files( $target_url, $sent_filename, $checksum, $curl_fields ) {
			$curl          = curl_init( $target_url );
			$unmark_fields = array_merge( $curl_fields, array(
				'serve_type'    => 'unmark_sent_files',
				'sent_filename' => $sent_filename,
				'checksum'      => $checksum,
			) );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $unmark_fields );
			curl_setopt( $curl, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
				return iwp_process_curl_headers( $curl, $header, $headers );
			} );
	
			curl_exec( $curl );
			curl_close( $curl );
		}
	
		public function iwp_inventory_sent_files( $target_url, $slug, $item_type, $curl_fields, $failed_items = array() ) {
			$curl          = curl_init( $target_url );
			$unmark_fields = array_merge( $curl_fields, array(
				'serve_type'   => 'inventory_sent_files',
				'slug'         => $slug,
				'failed_items' => $failed_items,
				'item_type'    => $item_type,
			) );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $curl, CURLOPT_POST, true );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $unmark_fields ) );
			curl_setopt( $curl, CURLOPT_HEADERFUNCTION, function ( $curl, $header ) use ( &$headers ) {
				return iwp_process_curl_headers( $curl, $header, $headers );
			} );
	
			curl_exec( $curl );
			curl_close( $curl );
		}
		
	}
}



<?php
/**
 * InstaWP Iterative Pull Push Helper
 */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'INSTAWP_IPP_HELPER' ) ) {
	class INSTAWP_IPP_HELPER {
		/**
		 * variables
		 */
		public $vars = array();

		/**
		 * Rows limit for each query
		 */
		private $rows_limit_per_query = 100;

		/**
		 * Is cli
		 */
		private $is_cli = false;


		public function __construct() {
			$this->is_cli = 'cli' === php_sapi_name();
			/**
			 * IPP option table option name variables.
			 */
			$this->vars = array(
				'file_checksum_repo' => 'iwp_ipp_file_checksums_repo',
				'file_checksum_processed_count' => 'iwp_ipp_file_checksums_repo_processed_count',
				'db_meta_repo' => 'iwp_ipp_db_meta_repo',
				'db_last_run_transient' => 'iwp_ipp_db_meta_repo_last_run_transient',
				'db_last_run_data' => 'iwp_ipp_db_meta_repo_last_run_data',
				'last_run_cli_time' => 'iwp_ipp_cli_last_run_time',
				'ipp_run_settings' => 'iwp_ipp_run_settings',
			);
		}

		/**
		 * Get iterative push curl params
		 * @param array $params
		 * 
		 * @return string
		 */
		public function get_iterative_push_curl_params( $params = array() ) {
			$params = empty( $params ) ? array() : $params;
			return http_build_query( array_merge( 
				array(
					'mode' => 'iterative_push',
				),
				$params
			) );
		}

		/**
		 * Get iterative pull curl params
		 * @param array $params
		 * 
		 * @return string
		 */
		public function get_iterative_pull_curl_params( $params = array() ) {
			$params = empty( $params ) ? array() : $params;
			return http_build_query( array_merge( 
				array(
					'mode' => 'iterative_pull',
				),
				$params
			) );
		}

		// sanitize_array
		public function sanitize_array( $array, $textarea_sanitize = true ) {
			if ( ! empty( $array ) && is_array( $array ) ) {
				foreach ( (array) $array as $key => $value ) {
					if ( is_array( $value ) ) {
						$array[ $key ] = $this->sanitize_array( $value );
					} else {
						$array[ $key ] = true === $textarea_sanitize ? sanitize_textarea_field( $value )  : sanitize_text_field( $value );
					}
				}
			}
			return $array;
		}

		/**
		 * Print message
		 * 
		 * @param string|array $message
		 */
		public function print_message( $message, $error = false ) {
			$message = is_array( $message) ? json_encode( $message ) : $message;
			if ( $this->is_cli ) {
				if ( $error ) {
					WP_CLI::error( $message );
				}
				WP_CLI::log( $message );
			} else if ( $error ) {
				error_log( $message );
			}
		}

		public function wp_send_json( $res, $error = true ) {
			if ( $this->is_cli ) {
				$res = isset( $res['message'] ) ? $res['message'] : $res;
				$this->print_message( $res, $error );
			} else if ( $error ) {
				wp_send_json_error( $res );
			} else {
				wp_send_json_success( $res );
			}
		}

		public function reorder_files_for_push( $files, $relative_path ) {
			try {
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
	
				foreach ($files as $file) {
					$filepath = $file['filepath'];
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
				$this->print_message( 'push-files: Failed to get files', $e->getMessage() );
				return array();
			}
		}

		public function get_parent_folders_only($folders) {
			// First, let's extract just the paths for easier processing
			$paths = array_column($folders, 'relative_path');
			
			// Filter out folders that are subfolders of any other folder
			return array_values( array_filter( $folders, function( $folder ) use ( $paths ) {
				$currentPath = $folder['relative_path'];
				
				foreach ($paths as $path) {
					// If another path contains this path as a prefix AND they're not the same path
					// AND the other path is shorter (meaning it's a parent)
					if ( $path !== $currentPath && 
						str_starts_with($currentPath, $path . '/') ) {
						return false; // This is a subfolder, so filter it out
					}
				}
				return true; // This is either a parent folder or has no parent
			}) );
		}


		/**
		 * Output a simple progress bar to the console.
		 *
		 * @param int $done  The number of items already processed.
		 * @param int $total The total number of items to be processed.
		 * @param int $width Optional. The width of the progress bar in characters. Default is 50.
		 */
		public function progress_bar($done, $total, $width = 50, $migrate_mode = '') {
			if ( ! $this->is_cli ) {
				// Not a CLI environment
				return;
			}
			// Calculate the progress
			$progress = ($done / $total);
			$barWidth = floor($progress * $width);
			$percent = floor($progress * 100);

			if ( in_array( $migrate_mode, array( 'push', 'pull' ) ) ) {
				echo date( "H:i:s" ) . ": Progress: $percent%\n";
			}
		
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
		 * Get directories only from a given root path.
		 *
		 * @param string $root           The root directory path.
		 * @param array  $exclude_paths  Paths to exclude (relative to root).
		 * @param array  $skip_subfolders Subfolders to skip if found in the path.
		 *
		 * @return RecursiveIteratorIterator Iterator for directories only.
		 */
		public function get_directories_only( $root, $exclude_paths = array(), $skip_subfolders = array() ) {
			$is_subfolder_check = count( $skip_subfolders ) > 0;

			$filter_directories = function ( SplFileInfo $file, $key, RecursiveDirectoryIterator $iterator ) use ( $exclude_paths, $is_subfolder_check, $skip_subfolders ) {
				// Get the relative path of the current file/directory
				$relative_path = ! empty( $iterator->getSubPath() )
					? $iterator->getSubPath() . '/' . $file->getBasename()
					: $file->getBasename();

				$relative_path = trim( $relative_path, '/' );
				$relative_path = trim( $relative_path, '/\\' );

				// Skip excluded paths
				if ( in_array( $relative_path, $exclude_paths, true ) ) {
					return false;
				}

				// Skip subfolders if specified
				if ( $is_subfolder_check ) {
					foreach ( $skip_subfolders as $subfolder ) {
						if ( false !== strpos( wp_normalize_path( $relative_path ), '/' . $subfolder . '/' ) ) {
							return false;
						}
					}
				}

				// Include only directories
				return $file->isDir();
			};

			// Directory iterator
			$directory = new RecursiveDirectoryIterator( $root, RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );

			// Return an iterator for directories only
			return new RecursiveIteratorIterator(
				new RecursiveCallbackFilterIterator( $directory, $filter_directories ),
				RecursiveIteratorIterator::SELF_FIRST, // Include directories in the result
				RecursiveIteratorIterator::CATCH_GET_CHILD
			);
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

		public function core_files_folder_list() {
			return array(
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

		public function get_domain( $url = '' ) {
			$url = empty( $url ) ? site_url() : $url;
			$url = esc_url( $url );
			$protocols = array( 'http://', 'https://', 'http://www.', 'https://www.', 'www.' );
			return str_replace( $protocols, '', $url );
		 }

		/**
		 * Get checksums for all files in a directory
		 *
		 * @param array $settings
		 * @param bool $include_dir_checksums
		 * 
		 * @return array|false Array of filepath => checksum pairs or false on failure
		 */
		public function get_files_checksum( $settings = array(), $include_dir_checksums = true ) {
			if ( ! defined( 'ABSPATH' ) || ! is_dir( ABSPATH ) ) {
				$this->print_message( 'ABSPATH not defined or invalid directory' );
				return false;
			}
			
			$checksums = array();
			// Saved settings
			$checksum_repo =  get_option( $this->vars['file_checksum_repo'], array() );
			
			$abspath = wp_normalize_path( ABSPATH );
			$excluded_subpaths = array(
				'node_modules',
				'.github',
				'.git',
				'.gitignore',
				'.gitattributes',
				'.distignore',
				'.vscode',
				'.wordpress-org',
			);
			$files = $this->get_iterator_items( 
				$abspath,
				$settings['excluded_paths'],
				$excluded_subpaths
			);

			$total = iterator_count( $files );
			// should be multiple of 1000
			$limit = empty( $settings['files_limit'] ) ? 0 : absint( $settings['files_limit'] );
			$is_limit = $limit > 0;
			if ( $is_limit ) {
				$limit_processed_count = get_option( $this->vars['file_checksum_processed_count'], 0 );
				if ( $limit_processed_count >= $total || 'completed' === $limit_processed_count ) {
					$this->print_message( 'Checksum processing completed' );
					return false;
				}
			}
			// Number of items actually processed
			$processed_files = 0;
			// Batch size to save checksum
			$batch = $is_limit ? $limit : 1000;
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
					$relative_path_hash = md5( $relative_path ); // File path hash

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

					//Get checksum from transient
					if ( ! empty( $checksum_repo[ $relative_path_hash ] ) && $checksum_repo[ $relative_path_hash ]['filetime'] === $filetime ) {
						$checksums[ $relative_path_hash ] = $checksum_repo[ $relative_path_hash ];
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

					$checksum_repo[ $relative_path_hash ] = array(
						'checksum' 		=> $file_checksum,
						'size'     		=> $filesize,
						'filepath'		=> $filepath,
						'filepath_hash'	=> md5( $filepath ),
						'relative_path_hash' => $relative_path_hash,
						'relative_path' => $relative_path,
						'filetime' 		=> $filetime,
					);

					// Add to settings
					$checksums[ $relative_path_hash ] = $checksum_repo[ $relative_path_hash ];

					$processed_files++;
					if ( $is_save_checksums ) {
						// Update checksum repo
						update_option( $this->vars['file_checksum_repo'], $checksum_repo );
						if ( $is_limit ) {
							update_option( $this->vars['file_checksum_processed_count'], $processed );
							break;
						}
					}
				}
			}

			if ( 0 === $processed_files ) {
				if ( $is_limit ) {
					update_option( $this->vars['file_checksum_processed_count'], 'completed' );
				}
			} else {
				// Update checksum repo
				update_option( $this->vars['file_checksum_repo'], $checksum_repo );
				if ( $is_limit ) {
					update_option( $this->vars['file_checksum_processed_count'], $processed );
				}
			}
			
			$total_missed = count( $missed );
			// Output progress
			$this->progress_bar( $total - $total_missed, $total );
			if ( $total_missed > 0 ) {
				$this->print_message( 'Missed files: ' . json_encode( $missed ) );
			}
			// Get directory checksum
			if ( $include_dir_checksums ) {
				$this->get_directory_checksum( 
					$checksums, 
					$abspath,
					$settings['excluded_paths'],
					$excluded_subpaths 
				);
			}
			
			return $checksums;
		}

		/**
		 * Get directory checksum
		 * 
		 * @param array $checksums
		 * @param string $root_dir_path
		 * @param array $excluded_paths
		 * @param array $excluded_subpaths
		 * 
		 * @return void
		 */
		public function get_directory_checksum( &$checksums, $root_dir_path, $excluded_paths = array(), $excluded_subpaths = array() ) {
			try{
				// Folder iterator
				$directory_iterator = $this->get_directories_only(
					$root_dir_path,
					$excluded_paths,
					$excluded_subpaths
				);

				$root_dir_path = trailingslashit( $root_dir_path );
		
				foreach ( $directory_iterator as $directory ) {
					// Get path
					$directory_path  = $directory->getPathname();
					// Skip if not a directory
					if ( ! file_exists( $directory )  || ! is_dir( $directory_path ) ) {
						continue;
					}
					$relative_path = str_replace( $root_dir_path, '', $directory_path );
					// Skip if path contains dot
					if ( false !== strpos( $relative_path, '.' ) ) {
						continue;
					}
					$relative_path_hash = md5( $relative_path );
					$checksums[ $relative_path_hash ] = array(
						'is_dir' => true,
						'relative_path_hash' => $relative_path_hash,
						'relative_path' => $relative_path,
					);
				}
			} catch ( Exception $e ) {
				$this->print_message( 'Error: checking directories ' . $e->getMessage(), true );
			}
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
			$disallowed = array( '/', '\\', ':', '*', '?', '"', '<', '>', '|', '.log', '.zip' );
			foreach ( $disallowed as $char ) {
				if ( strpos( $filename, $char ) !== false ) {
					return false;
				}
			}

			// Check for disallowed characters and file start with dot
			if ( $filename[0] === '.' || $filename === '.' || $filename === '..' ) {
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
			
			$db_meta = get_option( $this->vars['db_meta_repo'], array() );
			$meta = array();
			$table = $tables[0];
			try {
				// Get last run data
				if ( $is_api_call ) {
					// Set transient for 30 minutes
					$last_transient = get_transient( $this->vars['db_last_run_transient'] );
					if ( ! empty( $last_transient ) && $last_transient['table'] === $table ) {
						$last_run_data = $last_transient;
					}
				} else {
					// Get last run data
					$last_run_data = get_option( $this->vars['db_last_run_data'] );
					if ( isset( $last_run_data['table'] ) ) {
						$table = $last_run_data['table'];
					}
					$key = array_search( $table, $tables );
					$total_tables = count( $tables );
					$exclude_tables = $this->exclude_tables();
					if ( in_array( $table, $exclude_tables ) ) {
						while ( $key < $total_tables ) {
							$key = $key + 1;
							$table = $tables[ $key ];
							if ( ! in_array( $table, $exclude_tables ) ) {
								break;
							}
						}	
					}
				}

				$last_run_data = empty( $last_run_data ) ? array(): $last_run_data;
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
					// Get table columns meta
					$table_schema = $wpdb->get_results( 'SHOW FULL COLUMNS FROM ' . $table, ARRAY_A );
					if ( empty( $table_schema ) || ! isset( $table_schema[0]['Field'] ) ) {
						return $meta;
					}
					$primary_key = '';
					$modified_at_field = '';
					$maybe_url_fields = array();
					$non_url_fields = array();
					// Loop through table meta
					foreach ( $table_schema as $meta_key => $meta_value ) {

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

					$table_fields = array_column( $table_schema, 'Field' );
					$meta = array(
						'table' => $table,
						'table_schema' => $table_schema,
						'indexes' => $wpdb->get_results( 'SHOW INDEX FROM ' . $table, ARRAY_A ),
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
				} else {
					$meta = $last_run_data['meta'];
				}

				$meta['next_start_id'] = 1;
				$last_run_data = array();
				// Get last id
				$meta['last_id'] = empty( $meta['primary_key'] ) ? '' : $wpdb->get_var( 'SELECT ' . $meta['primary_key'] . ' FROM ' . $table . ' ORDER BY ' . $meta['primary_key'] . ' DESC LIMIT 1' );
				// Get last modified
				$meta['last_modified_at'] = empty( $meta['modified_at_field'] ) ? '' : $wpdb->get_var( 'SELECT MAX(' . $meta['modified_at_field'] . ') FROM ' . $table );
				if ( 0 === $start_id ) {	
					// Get checksum
					$meta['checksum'] = $this->get_table_checksum( $table, $meta, $home_url );
				}
				
				if ( ! empty( $meta['primary_key'] ) && 0 < $start_id ) {
					// Get query
					$query = $this->prepare_url_non_url_query( $meta['maybe_url_fields'], $meta['non_url_fields'], $home_url );
					// Get checksum
					$end_id = $this->get_end_id( $start_id );
					// Last ID to process
					$last_id_to_process = $last_id_to_process > 0 ? $last_id_to_process: $meta['last_id'];
					if ( $last_id_to_process > 0 && $last_id_to_process < $end_id ) {
						// End id should be less than equal to last id
						$end_id = $last_id_to_process;
						$meta['next_start_id'] = 0;
					} else {
						// Next start
						$meta['next_start_id'] = $end_id + 1;
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
									$start_id,
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
								$start_id,
								$end_id
							);

							$results = $wpdb->get_row( $query, ARRAY_A );

							if ( ! empty( $results ) ) {
								$meta['rows_checksum'][ $checksum_key ] = $results;
							}
						}
						
					} else if ( 0 < $meta['rows_count'] ) {
						$query = $wpdb->prepare(
							"SELECT COUNT(*) AS rows_count, GROUP_CONCAT(".$meta['primary_key'].") AS ids, $query FROM $table WHERE ".$meta['primary_key']." BETWEEN %d AND %d",
							$start_id,
							$end_id
						);

						$results = $wpdb->get_row( $query, ARRAY_A );
						
						if ( ! empty( $results ) ) {
							$meta['rows_checksum'][ $checksum_key ] = $results;
						}
					}

					if ( $is_api_call && ! empty( $meta['rows_checksum'] ) ) {
						$meta['checksum'] = $meta['rows_checksum'][ $checksum_key ];
					}
					
				} 
				

				if ( ! $is_api_call ) {
					if ( 0 === $meta['next_start_id'] ) {
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
							'start_id' => $meta['next_start_id'],
							'home_url' => $home_url,
							'meta' => $meta
						);
					}
					update_option( $this->vars['db_last_run_data'], $last_run_data );
				} else {
					// Set transient for 30 minutes
					set_transient( $this->vars['db_last_run_transient'], array(
						'table' => $table,
						'home_url' => $home_url,
						'meta' => $meta
					), 1800 );
				}
			} catch (\Exception $th) {
				error_log( "IWP IPP Helper: get_table_meta: " . $th->getMessage() );
			}
			
			return $meta;
		}

		/**
		 * Exclude tables.
		 */
		public function exclude_tables() {
			global $wpdb;
			return array(
				$wpdb->prefix . 'actionscheduler_actions',
				$wpdb->prefix . 'actionscheduler_claims',
				$wpdb-> prefix . 'actionscheduler_groups',
				$wpdb->prefix . 'actionscheduler_logs',
				$wpdb->prefix . 'instawp_events',
				$wpdb->prefix . 'instawp_event_sites',
				$wpdb->prefix . 'instawp_event_sync_logs',
				$wpdb->prefix . 'instawp_sync_history',
				'iwp_db_sent',
				'iwp_files_sent',
				'iwp_options'
			);
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
	}

	global $iwp_ipp_helper;
	$iwp_ipp_helper = new INSTAWP_IPP_HELPER();
}



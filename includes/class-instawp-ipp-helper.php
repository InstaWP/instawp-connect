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
		private $db_checksum_name = 'iwp_ipp_db_checksums_repo';

		private $is_cli = false;


		public function __construct( $is_cli = false ) {
			$this->is_cli = $is_cli;
		}

		/**
		 * Print message
		 * 
		 * @param string $message
		 */
		public function print_message( $message ) {
			if ( $this->is_cli ) {
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

			// Use MD5 for final hash
			return md5( $checksum );
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
			if ( ! is_file( $filepath ) ) {
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
		 * Get the checksum of all tables in the database.
		 *
		 * @return array The checksums of all tables in the database.
		 */
		public function get_tables_checksum() {
			$table_checksums = array();
			$tables = $this->get_tables();
			foreach ( $tables as $table ) {
				$table_checksums[ $table ] = $this->get_table_checksum( $table );
			}
			return $table_checksums;
		}

		/**
		 * Update the database checksum.
		 *
		 * @param string $key   The key of the checksum.
		 * @param string $value The value of the checksum.
		 */
		private function update_db_checksum( $key, $value ) {
			$db_checksums = get_option( $this->db_checksum_name );
			if ( empty( $db_checksums ) ) {
				$db_checksums = array();
			}
			$db_checksums[$key] = $value;
			$db_checksums['time'] = time();
			update_option( $this->db_checksum_name, $db_checksums );
		}

		/**
		 * Save the checksum of all tables in the database.
		 */
		public function save_tables_checksum() {
			$this->update_db_checksum( 'table_checksums', $this->get_tables_checksum() );
		}

		/**
		 * Get the checksum of a table.
		 *
		 * @param string $table The name of the table to get the checksum of.
		 *
		 * @return string The checksum of the table.
		 */
		public function get_table_checksum( $table ) {
			global $wpdb;
			// Sanitize table name
			$table = sprintf( '`%s`', $table );
			$table_fields = $wpdb->get_results( 'SHOW COLUMNS FROM ' . $table );
			if ( empty( $table_fields ) ) {
				return '';
			}
			$table_fields = array_column( $table_fields, 'Field' );
			$table_fields = array_map( function( $field ) {
				return sprintf( "IFNULL(`%s`, '')", $field );
			}, $table_fields );
			$table_fields = implode( ',', $table_fields );
			$table_checksum = "SELECT BIT_XOR(CAST(CRC32(CONCAT_WS('#', $table_fields)) AS UNSIGNED)) AS checksum FROM " . $table;
			// Get checksum
			$table_checksum = $wpdb->get_var( $table_checksum );
			return $table_checksum;
		}

		/**
		 * Get a list of tables in the database.
		 */
		public function get_tables() {
			global $wpdb;
			return $wpdb->get_col( "SHOW TABLES" );
		}
		
	}
}



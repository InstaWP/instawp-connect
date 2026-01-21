<?php

use InstaWP\Connect\Helpers\Activator;
use InstaWP\Connect\Helpers\Deactivator;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Installer;
use InstaWP\Connect\Helpers\Uninstaller;
use InstaWP\Connect\Helpers\Updater;
use InstaWP\Connect\Helpers\Option;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Plugin_Theme {

	/**
	 * Option name used to store copied zip metadata for sync
	 */
	const ZIP_STORAGE_OPTION = 'instawp_sync_custom_zip_urls';

	public function __construct() {
		// Plugin and Theme actions
		add_filter( 'upgrader_source_selection', array( $this, 'copy_uploaded_plugin_zip' ), 5, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'install_update_action' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'activate_plugin' ), 10, 2 );
		add_action( 'deactivated_plugin', array( $this, 'deactivate_plugin' ), 10, 2 );
		add_action( 'deleted_plugin', array( $this, 'delete_plugin' ), 10, 2 );
		add_action( 'switch_theme', array( $this, 'switch_theme' ), 10, 3 );
		add_action( 'deleted_theme', array( $this, 'delete_theme' ), 10, 2 );

		// Process event
		add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
		
		// Hook into event status update to delete zip files when events are marked as completed
		add_action( 'instawp_sync_event_completed', array( $this, 'handle_completed_event' ), 10, 2 );
	}

	/**
	 * Function for `upgrader_source_selection` filter-hook.
	 * Copy the original uploaded zip file before WordPress deletes it.
	 *
	 * @param string      $source        File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 *
	 * @return string
	 */
	public function copy_uploaded_plugin_zip( $source, $remote_source, $upgrader, $hook_extra ) {
		// Check if user has permission to upload plugins
		if ( ! instawp_is_admin( 'upload_plugins' ) ) {
			return $source;
		}

		// Only process plugin/theme installations and updates
		if ( empty( $hook_extra['type'] ) || ! in_array( $hook_extra['type'], array( 'plugin', 'theme' ), true ) ) {
			return $source;
		}
		
		// Only process install and update actions
		if ( empty( $hook_extra['action'] ) || ( $hook_extra['action'] !== 'install' && $hook_extra['action'] !== 'update' ) ) {
			return $source;
		}

		// Get the package path from WordPress attachment ID
		// WordPress stores uploaded plugin zip files as media attachments
		$package = null;
		
		if ( isset( $upgrader->skin->options['url'] ) ) {
			$url = $upgrader->skin->options['url'];
			
			// Check if URL contains package parameter (WordPress attachment ID)
			// Format: update.php?action=upload-plugin&package=21
			if ( strpos( $url, 'package=' ) !== false ) {
				parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
				if ( ! empty( $params['package'] ) ) {
					$attachment_id = intval( $params['package'] );
					
					// Get the file path from the attachment
					$file_path = get_attached_file( $attachment_id );
					if ( ! empty( $file_path ) && file_exists( $file_path ) && is_file( $file_path ) && pathinfo( $file_path, PATHINFO_EXTENSION ) === 'zip' ) {
						$package = $file_path;
					} 
				}
			}
		}

		if ( empty( $package ) ) {
			return $source;
		}

		// Check if it's a local file (uploaded zip) vs remote URL (WordPress.org)
		// Local files will have a file path, remote URLs will start with http:// or https://
		if ( filter_var( $package, FILTER_VALIDATE_URL ) && ( strpos( $package, 'http://' ) === 0 || strpos( $package, 'https://' ) === 0 ) ) {
			// It's a remote URL (likely WordPress.org), skip copying
			return $source;
		}

		// Check if it's a valid local zip file
		if ( ! file_exists( $package ) || ! is_file( $package ) ) {
			return $source;
		}

		// Validate that the package path is within expected directories (prevent path traversal)
		// Normalize the path to prevent path traversal
		$normalized_package = wp_normalize_path( $package );
		$real_package = realpath( $normalized_package );

		// Check if realpath resolved (file exists and is accessible)
		if ( $real_package === false ) {
			return $source;
		}

		// Get WordPress uploads directory
		$upload_dir = wp_upload_dir();
		$upload_basedir = wp_normalize_path( $upload_dir['basedir'] );

		// Ensure the file is within the uploads directory
		if ( strpos( $real_package . DIRECTORY_SEPARATOR, $upload_basedir . DIRECTORY_SEPARATOR ) !== 0 ) {
			// File is outside uploads directory - reject for security
			return $source;
		}

		// Additional check: Validate file path doesn't contain path traversal
		if ( validate_file( $normalized_package ) !== 0 ) {
			// Path contains traversal sequences - reject
			return $source;
		}

		// Verify it's a zip file - check both extension and actual file content (MIME type)
		$file_extension = strtolower( pathinfo( $package, PATHINFO_EXTENSION ) );
		if ( $file_extension !== 'zip' ) {
			return $source;
		}
			
		// Validate MIME type using WordPress function that checks actual file content
		$file_type = wp_check_filetype_and_ext( $package, basename( $package ), array( 'zip' => 'application/zip' ) );
		
		// Check if file type validation passed
		if ( empty( $file_type['type'] ) || $file_type['type'] !== 'application/zip' ) {
			// Fallback: Use finfo_file for more accurate MIME detection from file content
			if ( function_exists( 'finfo_file' ) ) {
				$finfo = finfo_open( FILEINFO_MIME_TYPE );
				$mime_type = finfo_file( $finfo, $package );
				finfo_close( $finfo );
				
				// Accept common zip MIME types
				$valid_zip_mimes = array( 'application/zip', 'application/x-zip-compressed', 'application/x-zip' );
				if ( ! in_array( $mime_type, $valid_zip_mimes, true ) ) {
					return $source;
				}
			} else {
				// If finfo_file is not available and wp_check_filetype_and_ext failed, reject the file
				return $source;
			}
		}

		// ZIP bomb protection: Validate ZIP file structure and size limits
		if ( ! $this->validate_zip_file_security( $package ) ) {
			Helper::add_error_log( array(
				'message' => 'ZIP file size validation failed - file exceeds 50 MB limit',
				'package' => $package,
				'file_size' => file_exists( $package ) ? filesize( $package ) : 'unknown',
			) );
			return $source;
		}

		// Create main backup directory if it doesn't exist 
		if ( ! file_exists( INSTAWP_BACKUP_DIR ) ) {
			$mkdir_result = wp_mkdir_p( INSTAWP_BACKUP_DIR );
			
			if ( ! $mkdir_result || ! file_exists( INSTAWP_BACKUP_DIR ) || ! is_dir( INSTAWP_BACKUP_DIR ) ) {
				Helper::add_error_log( array(
					'message' => 'Failed to create backup directory',
					'backup_dir' => INSTAWP_BACKUP_DIR,
				) );
				return $source;
			}
		}
		
		// Verify main backup directory is writable
		if ( ! is_writable( INSTAWP_BACKUP_DIR ) ) {
			Helper::add_error_log( array(
				'message' => 'Backup directory is not writable',
				'backup_dir' => INSTAWP_BACKUP_DIR,
			) );
			return $source;
		}

		// Determine type (plugin or theme) from hook_extra
		$type = isset( $hook_extra['type'] ) ? $hook_extra['type'] : 'plugin';
		
		// Create subdirectory based on type: plugins/ or themes/
		$subdirectory = ( $type === 'theme' ) ? 'themes' : 'plugins';
		$type_backup_dir = INSTAWP_BACKUP_DIR . $subdirectory . DIRECTORY_SEPARATOR;
		
		// Create type-specific subdirectory if it doesn't exist
		if ( ! file_exists( $type_backup_dir ) ) {
			$mkdir_result = wp_mkdir_p( $type_backup_dir );
			
			if ( ! $mkdir_result || ! file_exists( $type_backup_dir ) || ! is_dir( $type_backup_dir ) ) {
				Helper::add_error_log( array(
					'message' => 'Failed to create type-specific backup directory',
					'backup_dir' => $type_backup_dir,
					'type' => $type,
				) );
				return $source;
			}
		}

		$slug = basename( $source );
			
		// Always use slug-based naming
		$zip_filename = sanitize_file_name( $slug . '.zip' );

		$copied_zip_path = $type_backup_dir . $zip_filename;

		$backup_dir_relative = INSTAWP_DEFAULT_BACKUP_DIR . '/';
		$copied_zip_url      = content_url( $backup_dir_relative . $subdirectory . '/' . $zip_filename );

		// Copy the file using WordPress Filesystem API
		try {
			global $wp_filesystem;
			
			if ( ! function_exists( 'request_filesystem_credentials' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			
			WP_Filesystem();
			
			if ( ! $wp_filesystem->copy( $package, $copied_zip_path, true ) ) {
				Helper::add_error_log( array(
					'message' => 'Failed to copy ZIP file using WP_Filesystem',
					'package' => $package,
					'copied_zip_path' => $copied_zip_path,
					'type' => $type,
					'source_exists' => file_exists( $package ),
				) );
				return $source;
			}
			
		$this->store_zip_record( $slug, $type, $copied_zip_url );
	} catch ( \Throwable $e ) {
		Helper::add_error_log( array(
			'message' => 'Error occurred while copying ZIP file',
			'package' => $package,
			'copied_zip_path' => $copied_zip_path,
			'type' => $type,
		), $e );
		return $source;
	}

		return $source;
	}

	/**
	 * Function for `upgrader_process_complete` action-hook.
	 *
	 * @param WP_Upgrader $upgrader   WP_Upgrader instance. In other contexts this might be a Theme_Upgrader, Plugin_Upgrader, Core_Upgrade, or Language_Pack_Upgrader instance.
	 * @param array       $hook_extra Array of bulk item update data.
	 *
	 * @return void
	 */
	public function install_update_action( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || empty( $hook_extra['action'] ) ) {
			return;
		}

		$event_slug = $hook_extra['type'] . '_' . $hook_extra['action'];
		$event_name = sprintf( esc_html__('%1$s %2$s%3$s', 'instawp-connect' ), ucfirst( $hook_extra['type'] ), $hook_extra['action'], $hook_extra['action'] === 'update' ? 'd' : 'ed' );

		// hooks for plugins and record the plugin.
		if ( InstaWP_Sync_Helpers::can_sync( 'plugin' ) && $hook_extra['type'] === 'plugin' ) {

			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}

			if ( 'install' === $hook_extra['action'] ) {
				$path = $upgrader->plugin_info();
				if ( ! $path ) {
					return;
				}

				$data    = get_plugin_data( $upgrader->skin->result['local_destination'] . '/' . $path, true, false );
				$slug    = explode( '/', $path );
				$details = array(
					'name' => $data['Name'],
					'slug' => $slug[0],
					'path' => $path,
					'data' => $data,
				);

				// Check if it's a custom plugin (not on WordPress.org)
				// If custom, try to use the copied original zip file
				if ( ! Helper::is_on_wordpress_org( $slug[0], $hook_extra['type'] ) ) {
					$zip_url = $this->get_copied_plugin_zip( $slug[0], $hook_extra['type'] );
					
					if ( $zip_url ) {
						$details['zip_url'] = $zip_url;
						$details['is_custom'] = true;
					}
				}
				// Allow both WordPress.org and custom uploaded plugins to sync
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, $hook_extra['type'] );
			}

			if ( 'update' === $hook_extra['action'] ) {
				if ( isset( $hook_extra['bulk'] ) && $hook_extra['bulk'] ) {
					$paths = $hook_extra['plugins'];
				} else {
					$plugin_slug = isset( $upgrader->skin->plugin ) ? $upgrader->skin->plugin : $hook_extra['plugin'];
					if ( empty( $plugin_slug ) ) {
						return;
					}

					$paths = array( $plugin_slug );
				}

				foreach ( $paths as $path ) {
					$data    = get_plugin_data( WP_PLUGIN_DIR . '/' . $path, true, false );
					$slug    = explode( '/', $path );
					$details = array(
						'name' => $data['Name'],
						'slug' => $slug[0],
						'path' => $path,
						'data' => $data,
					);

					// Check if it's a custom plugin (not on WordPress.org)
					// If custom, try to use the copied original zip file
					if ( ! Helper::is_on_wordpress_org( $slug[0], $hook_extra['type'] ) ) {
						$zip_url = $this->get_copied_plugin_zip( $slug[0], $hook_extra['type'] );
						
						if ( $zip_url ) {
							$details['zip_url'] = $zip_url;
							$details['is_custom'] = true;
						}
					}
					// Allow both WordPress.org and custom uploaded plugins to sync
					$this->parse_plugin_theme_event( $event_name, $event_slug, $details, $hook_extra['type'] );
				}
			}
		}

		// hooks for theme and record the event
		if ( InstaWP_Sync_Helpers::can_sync( 'theme' ) && $hook_extra['type'] === 'theme' ) {

			if ( 'install' === $hook_extra['action'] ) {
				wp_clean_themes_cache();
				$details = array(
					'name'       => ! empty( $upgrader->new_theme_data['Name'] ) ? $upgrader->new_theme_data['Name'] : ucfirst( $upgrader->result['destination_name'] ),
					'stylesheet' => $upgrader->result['destination_name'],
					'data'       => isset( $upgrader->new_theme_data ) ? $upgrader->new_theme_data : array(),
				); 

				// Check if custom theme (not on WordPress.org)
				if ( ! Helper::is_on_wordpress_org( $upgrader->result['destination_name'], 'theme' ) ) {
					$zip_url = $this->get_copied_plugin_zip( $upgrader->result['destination_name'], 'theme' );
					if ( $zip_url ) {
						$details['zip_url'] = $zip_url;
						$details['is_custom'] = true;
					}
				}

				// ALWAYS sync theme install (custom or WP.org)
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, 'theme' );
			}

			if ( 'update' === $hook_extra['action'] ) {
				if ( isset( $hook_extra['bulk'] ) && $hook_extra['bulk'] ) {
					$slugs = $hook_extra['themes'];
				} else {
					$slugs = array( $upgrader->skin->theme );
				}

				foreach ( $slugs as $slug ) {
					$theme   = wp_get_theme( $slug );
					$details = array(
						'name'       => $theme->display( 'Name' ),
						'stylesheet' => $theme->get_stylesheet(),
					);

					if ( ! Helper::is_on_wordpress_org( $slug, 'theme' ) ) {
						$zip_url = $this->get_copied_plugin_zip( $slug, 'theme' );
						if ( $zip_url ) {
							$details['zip_url'] = $zip_url;
							$details['is_custom'] = true;
						}
					}

					$this->parse_plugin_theme_event( $event_name, $event_slug, $details, $hook_extra['type'] );
				}
			}
		}
	}

	/**
	 * Function for `deactivated_plugin` action-hook.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 * @param $network_wide
	 *
	 * @return void
	 */
	public function deactivate_plugin( $plugin, $network_wide ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'plugin' ) ) {
			return;
		}

		if ( $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __('Plugin deactivated', 'instawp-connect' ), 'deactivate_plugin', $plugin, 'plugin' );
		}
	}
	/**
	 * Function for `activated_plugin` action-hook.
	 *
	 * @param string $plugin       Path to the plugin file relative to the plugins directory.
	 * @param bool   $network_wide Whether to enable the plugin for all sites in the network or just the current site. Multisite only.
	 *
	 * @return void
	 */
	public function activate_plugin( $plugin, $network_wide ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'plugin' ) ) {
			return;
		}

		if ( $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __('Plugin activated', 'instawp-connect' ), 'activate_plugin', $plugin, 'plugin' );
		}
	}

	/**
	 * Function for `deleted_plugin` action-hook.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 *
	 * @return void
	 */
	public function delete_plugin( $plugin, $deleted ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'plugin' ) ) {
			return;
		}

		if ( $deleted && $plugin !== 'instawp-connect/instawp-connect.php' ) {
			$this->parse_plugin_theme_event( __( 'Plugin deleted', 'instawp-connect' ), 'deleted_plugin', $plugin, 'plugin' );
		}
	}

	/**
	 * Function for `switch_theme` action-hook.
	 *
	 * @param string   $new_name  Name of the new theme.
	 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
	 * @param WP_Theme $old_theme WP_Theme instance of the old theme.
	 *
	 * @return void
	 */
	public function switch_theme( $new_name, $new_theme, $old_theme ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'theme' ) ) {
			return;
		}

		$details    = array(
			'name'       => $new_name,
			'stylesheet' => $new_theme->get_stylesheet(),
		);
		$event_name = __('Theme switched', 'instawp-connect' );
		$this->parse_plugin_theme_event( $event_name, 'switch_theme', $details, 'theme' );
	}

	/**
	 * Function for `deleted_theme` action-hook.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @param bool   $deleted    Whether the theme deletion was successful.
	 *
	 * @return void
	 */
	public function delete_theme( $stylesheet, $deleted ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'theme' ) ) {
			return;
		}

		$theme   = wp_get_theme( $stylesheet );
		$details = array(
			'name'       => $theme->display( 'Name' ),
			'stylesheet' => $stylesheet,
		);

		if ( $deleted ) {
			$this->parse_plugin_theme_event( __( 'Theme deleted', 'instawp-connect' ), 'deleted_theme', $details, 'theme' );
		}
	}

	public function parse_event( $response, $v ) {
		if ( strpos( $v->event_type, 'plugin' ) === false && strpos( $v->event_type, 'theme' ) === false ) {
			return $response;
		}

		$logs = array();

		// plugin install
		if ( $v->event_slug === 'plugin_install' ) {
			if ( ! empty( $v->details->slug ) ) {
				// Check if zip_url is available for custom plugins
				$zip_url = isset( $v->details->zip_url ) ? $v->details->zip_url : null;
				$response = $this->install_item( $v->details->slug, 'plugin', false, $zip_url )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}

				// Delete zip file after successful installation
				// This will delete on source site (where file exists) and skip on destination site
				if ( ! empty( $zip_url ) ) {
					$this->delete_zip_file( $zip_url );
				}
			} else {
				$logs[ $v->id ] = __( 'Slug missing.', 'instawp-connect' );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// plugin activate
		if ( $v->event_slug === 'activate_plugin' ) {
			// try to install plugin if not exists.
			if ( ! $this->is_plugin_installed( $v->details ) ) {
				$slug = explode( '/', $v->details );

				if ( Helper::is_on_wordpress_org( $slug[0], 'plugin' ) ) {
					$response = $this->install_item( $slug[0], 'plugin', true )[0];

					if ( ! $response['success'] ) {
						$logs[ $v->id ] = $response['message'];

						return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
							'status'  => 'error',
							'message' => $logs[ $v->id ],
						) );
					}
				}
			}

			if ( $this->is_plugin_installed( $v->details ) ) {
				$response = $this->activate_item( $v->details, 'plugin' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Plugin %s not found at destination.', 'instawp-connect' ), $v->details );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// plugin deactivate
		if ( $v->event_slug === 'deactivate_plugin' ) {
			if ( $this->is_plugin_installed( $v->details ) ) {
				$response = $this->deactivate_item( $v->details );

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Plugin %s not found at destination.', 'instawp-connect' ), $v->details );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// plugin update
		if ( $v->event_slug === 'plugin_update' ) {
			if ( ! empty( $v->details->path ) ) {
				if ( $this->is_plugin_installed( $v->details->path ) ) {
					// Check if zip_url is available for custom plugins
					$zip_url = isset( $v->details->zip_url ) ? $v->details->zip_url : null;
					$response = $this->update_item( $v->details->path, 'plugin', $zip_url );
					
					if ( isset( $response[ $v->details->path ] ) ) {
						$response = $response[ $v->details->path ];
					} elseif ( isset( $response[0] ) ) {
						$response = $response[0];
					}

					if ( isset( $response['success'] ) && false === $response['success'] ) {
						$logs[ $v->id ] = $response['message'];

						return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
							'status'  => 'error',
							'message' => $logs[ $v->id ],
						) );
					}

					// Delete zip file after successful update
					// This will delete on source site (where file exists) and skip on destination site
					if ( ! empty( $zip_url ) ) {
						$this->delete_zip_file( $zip_url );
					}
				} else {
					$logs[ $v->id ] = sprintf( __( 'Plugin %s not found at destination.', 'instawp-connect' ), $v->details->path );

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'invalid',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = __( 'Plugin file missing.', 'instawp-connect' );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// plugin delete
		if ( $v->event_slug === 'deleted_plugin' ) {
			if ( $this->is_plugin_installed( $v->details ) ) {
				$response = $this->uninstall_item( $v->details, 'plugin' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Plugin %s not found at destination.', 'instawp-connect' ), $v->details );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// theme install
		if ( $v->event_slug === 'theme_install' ) { 
			if ( ! empty( $v->details->stylesheet ) ) {
				// Support custom theme installation using zip_url if available
				$zip_url = $v->details->zip_url ?? null;

				$response = $this->install_item( $v->details->stylesheet, 'theme', false, $zip_url )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}

				// Delete ZIP file on source site after successful theme install
				if ( ! empty( $zip_url ) ) {
					$this->delete_zip_file( $zip_url );
				}
			} else {
				$logs[ $v->id ] = __( 'Stylesheet missing.', 'instawp-connect' );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'error',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// theme switch
		if ( $v->event_slug === 'switch_theme' ) {
			$stylesheet = $v->details->stylesheet;
			$theme      = wp_get_theme( $stylesheet );

			if ( ! $theme->exists() ) {
				$response = $this->install_item( $stylesheet, 'theme' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			}

			$theme = wp_get_theme( $stylesheet );
			if ( $theme->exists() ) {
				$response = $this->activate_item( $stylesheet, 'theme' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Theme %s not found at destination.', 'instawp-connect' ), $stylesheet );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// theme update
		if ( $v->event_slug === 'theme_update' ) {
			$stylesheet = $v->details->stylesheet;
			$theme      = wp_get_theme( $stylesheet );

			if ( $theme->exists() ) {
				$response = $this->update_item( $stylesheet, 'theme' );

				if ( isset( $response[ $stylesheet ] ) ) {
					$response = $response[ $stylesheet ];
				} elseif ( isset( $response[0] ) ) {
					$response = $response[0];
				}

				if ( isset( $response['success'] ) && false === $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Theme %s not found at destination.', 'instawp-connect' ), $stylesheet );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		// theme delete
		if ( $v->event_slug === 'deleted_theme' ) {
			$stylesheet = $v->details->stylesheet;
			$theme      = wp_get_theme( $stylesheet );

			if ( $theme->exists() ) {
				$response = $this->uninstall_item( $stylesheet, 'theme' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
				}
			} else {
				$logs[ $v->id ] = sprintf( __( 'Theme %s not found at destination.', 'instawp-connect' ), $stylesheet );

				return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
					'status'  => 'invalid',
					'message' => $logs[ $v->id ],
				) );
			}
		}

		return InstaWP_Sync_Helpers::sync_response( $v, $logs );
	}

	/**
	 * Function parse_plugin_theme_event
	 * @param $event_name
	 * @param $event_slug
	 * @param $details
	 * @param $type
	 * @return void
	 */
	private function parse_plugin_theme_event( $event_name, $event_slug, $details, $type ) {
		switch ( $type ) {
			case 'plugin':
				if ( ! empty( $details ) && is_array( $details ) ) {
					$title     = $details['name'];
					$source_id = $details['slug'];
				} else {
					$source_id = basename( $details, '.php' );

					if ( ! function_exists( 'get_plugin_data' ) ) {
						require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
					}

					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $details, true, false );

					if ( $plugin_data['Name'] !== '' ) {
						$title = $plugin_data['Name'];
					} elseif ( $plugin_data['TextDomain'] !== '' ) {
						$title = $plugin_data['TextDomain'];
					} else {
						$title = $details;
					}
				}
				break;
			default:
				$title     = $details['name'];
				$source_id = $details['stylesheet'];
		}
		
		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, $type, $source_id, $title, $details );
	}

	/**
	 * Plugin or Theme activate
	 */
	public function activate_item( $item, $type ) {
		$activator = new Activator( array(
			array(
				'asset' => $item,
				'type'  => $type,
			),
		) );

		return $activator->activate();
	}

	/**
	 * Plugin deactivate
	 */
	public function deactivate_item( $plugin ) {
		$deactivator = new Deactivator( array( $plugin ) );

		return $deactivator->deactivate();
	}

	/**
	 * Plugin or Theme update
	 */
	public function update_item( $item, $type, $zip_url = null ) {
		// If zip_url is provided, use it as the source (custom plugin/theme)
		// Otherwise, try to update from WordPress.org
		$source = ( ! empty( $zip_url ) ) ? 'url' : 'wp.org';
		$slug = ( ! empty( $zip_url ) ) ? $zip_url : $item;

		$updater = new Updater( array(
			array(
				'slug' => $slug,
				'source' => $source,
				'type' => $type,
			),
		) );

		return $updater->update();
	}

	/**
	 * Plugin or Theme install
	 */
	public function install_item( $item, $type, $activate = false, $zip_url = null ) {
		// If zip_url is provided, use it as the source (custom plugin/theme)
		// Otherwise, try to install from WordPress.org
		$source = ( ! empty( $zip_url ) ) ? 'url' : 'wp.org';
		$slug = ( ! empty( $zip_url ) ) ? $zip_url : $item;

		$installer = new Installer( array(
			array(
				'slug'     => $slug,
				'source'   => $source,
				'type'     => $type,
				'activate' => $activate,
			),
		) );

		return $installer->start();
	}

	/**
	 * Plugin or Theme uninstall
	 */
	public function uninstall_item( $item, $type ) {
		$uninstaller = new Uninstaller( array(
			array(
				'asset' => $item,
				'type'  => $type,
			),
		) );

		return $uninstaller->uninstall();
	}

	/**
	 * Check if plugin is installed by getting all plugins from the plugins dir
	 *
	 * @param $plugin_slug
	 *
	 * @return bool
	 */
	public function is_plugin_installed( $plugin_slug ) {
		$installed_plugins = get_plugins();

		return array_key_exists( $plugin_slug, $installed_plugins ) || in_array( $plugin_slug, $installed_plugins, true );
	}

	/**
	 * Get the copied original zip file URL if available
	 *
	 * @param string $slug
	 * @param string $type
	 *
	 * @return string|false
	 */
	private function get_copied_plugin_zip( $slug, $type ) {
		$records = $this->get_zip_records();

		if ( isset( $records[ $type ][ $slug ] ) ) {
			$zip_url = $records[ $type ][ $slug ]['zip_url'];

			if ( $this->verify_copied_zip_exists( $zip_url ) ) {
				return $zip_url;
			}

			$this->remove_zip_record( $slug, $type );
		}

		return false;
	}

	/**
	 * Store copied zip metadata inside persistent option
	 *
	 * @param string $slug
	 * @param string $type
	 * @param string $zip_url
	 *
	 * @return void
	 */
	private function store_zip_record( $slug, $type, $zip_url ) {
		if ( empty( $slug ) || empty( $zip_url ) ) {
			return;
		}

		$records = $this->get_zip_records();
		if ( ! isset( $records[ $type ] ) || ! is_array( $records[ $type ] ) ) {
			$records[ $type ] = array();
		}

		$records[ $type ][ $slug ] = array(
			'zip_url'   => $zip_url,
			'stored_at' => time(),
		);

		Option::update_option( self::ZIP_STORAGE_OPTION, $records );
	}

	/**
	 * Remove copied zip metadata by slug/type
	 *
	 * @param string $slug
	 * @param string $type
	 *
	 * @return void
	 */
	private function remove_zip_record( $slug, $type ) {
		$records = $this->get_zip_records();

		if ( isset( $records[ $type ][ $slug ] ) ) {
			unset( $records[ $type ][ $slug ] );
			Option::update_option( self::ZIP_STORAGE_OPTION, $records );
		}
	}

	/**
	 * Remove copied zip metadata by url
	 *
	 * @param string $zip_url
	 *
	 * @return void
	 */
	private function remove_zip_record_by_url( $zip_url ) {
		if ( empty( $zip_url ) ) {
			return;
		}

		$records   = $this->get_zip_records();
		$modified  = false;

		foreach ( $records as $type => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $slug => $data ) {
				if ( isset( $data['zip_url'] ) && $data['zip_url'] === $zip_url ) {
					unset( $records[ $type ][ $slug ] );
					$modified = true;
				}
			}
		}

		if ( $modified ) {
			Option::update_option( self::ZIP_STORAGE_OPTION, $records );
		}
	}

	/**
	 * Fetch stored zip metadata
	 *
	 * @return array
	 */
	private function get_zip_records() {
		$records = Option::get_option( self::ZIP_STORAGE_OPTION, array() );

		return is_array( $records ) ? $records : array();
	}

	/**
	 * Verify that a copied zip file still exists
	 *
	 * @param string $zip_url The zip file URL
	 *
	 * @return bool True if file exists, false otherwise
	 */
	private function verify_copied_zip_exists( $zip_url ) {  
		if ( ! defined( 'INSTAWP_BACKUP_DIR' ) ) {
			return false;
		}

		// Convert URL to path
		// The URL format is: content_url()/instawpbackups/plugins/filename.zip or content_url()/instawpbackups/themes/filename.zip
		$content_url = content_url();
		if ( strpos( $zip_url, $content_url ) === 0 ) {
			$relative_path = str_replace( $content_url, '', $zip_url );
			// Normalize path separators
			$relative_path = str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			$zip_path = WP_CONTENT_DIR . $relative_path;
			return file_exists( $zip_path ) && is_file( $zip_path );
		}

		return false;
	}

	/**
	 * Handle completed event - delete zip file if it's a plugin_install or plugin_update event with zip_url
	 *
	 * @param int    $event_id The event ID
	 * @param string $status   The event status
	 *
	 * @return void
	 */
	public function handle_completed_event( $event_id, $status ) {
		if ( $status !== 'completed' ) {
			return;
		}

		// Get event details from database
		$event_rows = InstaWP_Sync_DB::getRowById( INSTAWP_DB_TABLE_EVENTS, $event_id );
		if ( empty( $event_rows ) || ! isset( $event_rows[0] ) ) {
			return;
		}

		$event = $event_rows[0];

		// Only process plugin_install and plugin_update events
		if ( $event->event_type !== 'plugin' || ( $event->event_slug !== 'plugin_install' && $event->event_slug !== 'plugin_update' ) ) {
			return;
		}

		// Get event details
		$details = json_decode( $event->details, true );
		if ( empty( $details ) || ! is_array( $details ) ) {
			return;
		}

		// Check if zip_url exists and delete the zip file
		if ( ! empty( $details['zip_url'] ) ) {
			$this->delete_zip_file( $details['zip_url'] );
		}
	}

	/**
	 * Delete a zip file by URL after successful sync
	 *
	 * @param string $zip_url The zip file URL
	 *
	 * @return bool True if file was deleted, false otherwise
	 */
	private function delete_zip_file( $zip_url ) {
		if ( ! instawp_is_admin( 'upload_plugins' ) ) {
			return false;
		}

		if ( empty( $zip_url ) ) {
			return false;
		}
		
		// Check if zip_url is from the current site (source site) or different site (destination site)
		$parsed_url = parse_url( $zip_url );
		$current_site_url = home_url();
		$current_site_parsed = parse_url( $current_site_url );
		
		// Compare domains to see if we're on the source site
		$zip_domain = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$current_domain = isset( $current_site_parsed['host'] ) ? $current_site_parsed['host'] : '';
		
		// Only proceed if we're on the source site (same domain)
		if ( $zip_domain !== $current_domain ) {
			return false;
		}
		
		// Extract relative path from URL
		if ( empty( $parsed_url['path'] ) ) {
			return false;
		}
		
		// Get the path from the URL
		$url_path = $parsed_url['path'];
		// Remove leading slash
		$url_path = ltrim( $url_path, '/' );
		
		// Extract the relative path after wp-content/
		// Format: wp-content/instawpbackups/plugins/filename.zip or wp-content/instawpbackups/themes/filename.zip
		if ( strpos( $url_path, 'wp-content/' ) === 0 ) {
			$relative_path = substr( $url_path, strlen( 'wp-content/' ) );
		} else {
			// Try to find instawpbackups in the path
			$instawpbackups_pos = strpos( $url_path, 'instawpbackups/' );
			if ( $instawpbackups_pos !== false ) {
				$relative_path = substr( $url_path, $instawpbackups_pos );
			} else {
				return false;
			}
		}
		
		// Normalize path separators
		$relative_path = str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
		$zip_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $relative_path;
		
		// Check if file exists locally
		if ( ! file_exists( $zip_path ) || ! is_file( $zip_path ) ) {
			return false;
		}

		// Only delete files in the backup directory for security
		// Normalize backup_dir path for comparison
		$backup_dir_normalized = rtrim( str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, INSTAWP_BACKUP_DIR ), DIRECTORY_SEPARATOR );
		$zip_path_normalized = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $zip_path );
		
		if ( strpos( $zip_path_normalized, $backup_dir_normalized ) === 0 ) {
			$deleted = wp_delete_file( $zip_path );

			if ( $deleted ) {
				$this->remove_zip_record_by_url( $zip_url );
			}

			return $deleted;
		}

		return false;
	}

	/**
	 * Validate ZIP file size limit
	 * Maximum ZIP file size: 50 MB (no plugin should exceed this size)
	 *
	 * @param string $zip_path Path to the ZIP file
	 * @return bool True if ZIP size is within limit, false otherwise
	 */
	private function validate_zip_file_security( $zip_path ) {
		try {
			if ( ! file_exists( $zip_path ) || ! is_file( $zip_path ) ) {
				return false;
			}

			// Maximum ZIP file size: 50 MB (no plugin should exceed this size)
			$max_zip_size = 50 * 1024 * 1024; // 50 MB

			$file_size = filesize( $zip_path );
			if ( $file_size === false ) {
				Helper::add_error_log( array(
					'message' => 'Failed to get ZIP file size - filesize() returned false',
					'zip_path' => $zip_path,
				) );
				return false;
			}

			if ( $file_size > $max_zip_size ) {
				Helper::add_error_log( array(
					'message'    => 'ZIP file exceeds maximum allowed size',
					'zip_path'   => $zip_path,
					'file_size'  => $file_size,
					'max_size'   => $max_zip_size,
				) );
				return false;
			}

			return true;
		} catch ( \Throwable $e ) {
			Helper::add_error_log( array(
				'message' => ( $e instanceof \Error ? 'Fatal error' : 'Exception' ) . ' occurred during ZIP file size validation',
				'zip_path' => $zip_path,
			), $e );
			return false;
		}
	}

}

new InstaWP_Sync_Plugin_Theme();
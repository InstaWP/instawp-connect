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

	/**
	 * How long an UNRECORDED copy may survive on disk before the daily sweep removes it.
	 *
	 * A copy with no record behind it can never be consumed — nothing holds its URL — so it
	 * is pure residue. Filterable via `instawp_sync_zip_retention`.
	 */
	const ZIP_RETENTION = 86400; // DAY_IN_SECONDS

	/**
	 * Hard cap on a RECORDED copy, whose sync event may still be pending.
	 *
	 * Deliberately far longer than ZIP_RETENTION. A pending event whose zip has been swept
	 * is not merely delayed: `generate_pending_sync_events()` excludes any event that has an
	 * `event_sites` row with status completed/invalid/error, and a 404 on the copy retires
	 * the event as `error` — so the destination never receives that plugin and the user has
	 * to re-upload it on the source. Sweeping a live copy therefore costs the sync
	 * permanently, which is why the guessable-name purge below is keyed on the NAME rather
	 * than on age. Filterable via `instawp_sync_zip_max_lifetime`.
	 */
	const ZIP_MAX_LIFETIME = 2592000; // 30 * DAY_IN_SECONDS

	/**
	 * A copy written by this version: `<slug>-<32 alphanumerics>.zip`.
	 *
	 * Anything in these directories that does NOT match carries a guessable name from an
	 * earlier version and is the exposure being remediated.
	 *
	 * The leading separator is optional because a slug that sanitizes to empty leaves the
	 * random part alone. Note wp_generate_password() is pluggable and its result passes
	 * through the `random_password` filter, so a site overriding either with non-alphanumeric
	 * output would make a fresh copy fail this test and be purged as legacy.
	 */
	const ZIP_UNGUESSABLE_PATTERN = '/(^|-)[A-Za-z0-9]{32}\.zip$/';

	/**
	 * Bumped whenever the one-time purge below needs to run again on existing sites.
	 */
	const ZIP_PURGE_VERSION = '1';

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

		// Backstop for copies whose event never completes. Rides the existing daily
		// Action Scheduler action rather than registering a second recurring action.
		add_action( 'instawp_clean_migrate_files', array( $this, 'purge_stale_zip_copies' ) );

		// Remediation for copies ALREADY on disk under a guessable name. Deliberately on
		// both hooks: admin_init reaches a site whose cron is disabled (DISABLE_WP_CRON with
		// no system cron, where the daily action never runs at all), and the daily action
		// reaches a site nobody logs into. An option flag keeps it to one probe per site.
		add_action( 'admin_init', array( $this, 'purge_guessable_zip_copies_once' ) );
		add_action( 'instawp_clean_migrate_files', array( $this, 'purge_guessable_zip_copies_once' ) );
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

		// Sync creates these directories with wp_mkdir_p() rather than through
		// InstaWP_Tools::create_instawpbackups_dir(), so the listing guard has to be
		// asserted here too — otherwise a site that only ever syncs (and never migrates)
		// would leave the backups tree listable.
		InstaWP_Tools::protect_instawpbackups_dir();

		$slug = basename( $source );

		// A slug-based name made the copy publicly guessable: anyone who knows a premium
		// plugin's folder name could fetch the licensed archive straight out of this
		// directory. The copy has to stay reachable over HTTP (the destination site
		// downloads it by URL during sync), so the URL itself has to carry the secret.
		$zip_filename = sanitize_file_name( $slug . '-' . wp_generate_password( 32, false ) . '.zip' );

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

		// Each copy now lands on its own random path instead of overwriting a fixed one, so
		// the previous copy for this slug has to be removed explicitly or it is orphaned by
		// the record update below. Done only after the new copy is safely in place, so a
		// failed copy never leaves a pending sync event pointing at a file we just deleted.
		$this->delete_recorded_zip( $slug, $type );

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
				$response = $this->get_item_response( $this->activate_item( $v->details, 'plugin' ), $v->details );

				if ( empty( $response['success'] ) ) {
					$logs[ $v->id ] = $response['message'] ?? '';

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
				$response = $this->get_item_response( $this->deactivate_item( $v->details ), $v->details );

				if ( empty( $response['success'] ) ) {
					$logs[ $v->id ] = $response['message'] ?? '';

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
				$response = $this->get_item_response( $this->uninstall_item( $v->details, 'plugin' ), $v->details );

				if ( empty( $response['success'] ) ) {
					$logs[ $v->id ] = $response['message'] ?? '';

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
				$response = $this->get_item_response( $this->activate_item( $stylesheet, 'theme' ), $stylesheet );

				if ( empty( $response['success'] ) ) {
					$logs[ $v->id ] = $response['message'] ?? '';

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
				$response = $this->get_item_response( $this->uninstall_item( $stylesheet, 'theme' ), $stylesheet );

				if ( empty( $response['success'] ) ) {
					$logs[ $v->id ] = $response['message'] ?? '';

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
		// Deactivator expects each item as an [ 'asset' => ..., 'type' => ... ] pair.
		// Passing a bare string skipped deactivation and returned "Required parameters are missing!".
		$deactivator = new Deactivator( array(
			array(
				'asset' => $plugin,
				'type'  => 'plugin',
			),
		) );

		return $deactivator->deactivate();
	}

	/**
	 * Normalize an item-action response (activate / deactivate / uninstall).
	 *
	 * Activator/Deactivator/Uninstaller return results keyed by asset slug, while
	 * Installer keys them numerically. Resolves the single result for the given
	 * asset, falling back to the first numeric entry, then the response itself.
	 *
	 * @param mixed  $response Raw response from the helper class.
	 * @param string $asset    Asset slug used as the array key (plugin path / theme stylesheet).
	 * @return array
	 */
	private function get_item_response( $response, $asset ) {
		if ( ! is_array( $response ) ) {
			return array();
		}
		if ( isset( $response[ $asset ] ) ) {
			return $response[ $asset ];
		}
		if ( isset( $response[0] ) ) {
			return $response[0];
		}
		return $response;
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

		// Convert URL to path, comparing on the PATH only.
		//
		// Comparing the absolute URLs would be wrong: content_url() is
		// set_url_scheme( WP_CONTENT_URL ) with no explicit scheme, and set_url_scheme()
		// rewrites the scheme from is_ssl() on the CURRENT request. So a URL recorded over
		// https fails an absolute prefix test in any http context — WP-CLI cron invoked
		// without --url (which is the DISABLE_WP_CRON population this feature's sweeps were
		// added for), a site that has since moved http→https, or a loopback behind
		// proxy-terminated TLS. The file would be reported missing while sitting on disk,
		// which would make prune_missing_zip_records() discard the whole record set — and
		// purge_stale_zip_copies() treats an unrecorded copy as residue, so the next sweep
		// would delete live copies whose events are still pending and retire those events
		// permanently. The path is scheme- and host-independent, and is the same join
		// recorded_zip_basenames() uses.
		$url_path     = wp_parse_url( $zip_url, PHP_URL_PATH );
		$content_path = wp_parse_url( content_url(), PHP_URL_PATH );

		if ( empty( $url_path ) ) {
			return false;
		}

		$content_path = is_string( $content_path ) ? untrailingslashit( $content_path ) : '';

		if ( '' !== $content_path && strpos( $url_path, $content_path . '/' ) !== 0 ) {
			return false;
		}

		$relative_path = substr( $url_path, strlen( $content_path ) );
		$relative_path = str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
		$zip_path      = WP_CONTENT_DIR . $relative_path;

		return file_exists( $zip_path ) && is_file( $zip_path );
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

		// Only process install/update events, for plugins AND themes. Theme events carry a
		// zip_url exactly like plugin events do (see install_update_action()), so excluding
		// them left every custom theme copy on disk for good.
		$copying_events = array( 'plugin_install', 'plugin_update', 'theme_install', 'theme_update' );

		if ( ! in_array( $event->event_type, array( 'plugin', 'theme' ), true ) || ! in_array( $event->event_slug, $copying_events, true ) ) {
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
	 * Delete the copy currently recorded for a slug, then drop its record.
	 *
	 * Called before a new copy is stored: each copy now has a random name rather than
	 * overwriting a fixed one, so without this the previous file would be left on disk
	 * with nothing pointing at it.
	 *
	 * @param string $slug
	 * @param string $type
	 *
	 * @return void
	 */
	private function delete_recorded_zip( $slug, $type ) {
		$records = $this->get_zip_records();

		if ( empty( $records[ $type ][ $slug ]['zip_url'] ) ) {
			return;
		}

		$this->delete_zip_file( $records[ $type ][ $slug ]['zip_url'] );

		// delete_zip_file() prunes the record only on a truthy return from wp_delete_file(),
		// which older WordPress does not give, so its pruning cannot be relied on. The record
		// is dropped here unconditionally — the copy it names is superseded either way.
		$this->remove_zip_record( $slug, $type );
	}

	/**
	 * Remove copied zips that have outlived the sync event carrying their URL.
	 *
	 * Deletion is otherwise driven by `instawp_sync_event_completed`, which never fires
	 * when there is no destination connected, sync is paused, or the push failed — the
	 * copy then stays on disk indefinitely. Runs on the existing daily
	 * `instawp_clean_migrate_files` action.
	 *
	 * Sweeps the directory rather than the stored records so that copies with no record
	 * (including fixed-name copies written by earlier versions) are cleaned up too.
	 *
	 * @return void
	 */
	public function purge_stale_zip_copies() {
		// Both sweeps sit on instawp_clean_migrate_files at the same priority and this one
		// runs first, so an uncaught throw here would abort the rest of the chain — taking
		// purge_guessable_zip_copies_once() with it on exactly the sites where its admin_init
		// leg never fires, i.e. the ones nobody logs into.
		try {
			$this->sweep_stale_zip_copies();
		} catch ( \Throwable $th ) {
			Helper::add_error_log( array( 'title' => 'instawp: purge_stale_zip_copies failed' ), $th );
		}
	}

	/**
	 * The age-based sweep itself. See purge_stale_zip_copies() for why it is wrapped.
	 *
	 * @return void
	 */
	private function sweep_stale_zip_copies() {
		if ( ! defined( 'INSTAWP_BACKUP_DIR' ) ) {
			return;
		}

		$retention = $this->bounded_age( 'instawp_sync_zip_retention', self::ZIP_RETENTION );
		$lifetime  = $this->bounded_age( 'instawp_sync_zip_max_lifetime', self::ZIP_MAX_LIFETIME );

		// The record IS the pending marker: a copy is recorded from the moment it is written
		// until the event that carries its URL completes (handle_completed_event) or is
		// superseded (delete_recorded_zip). So a recorded copy may still be needed and gets
		// the long cap, while an unrecorded one can never be consumed by anything and is
		// residue. No query against the events table is needed to tell them apart.
		$recorded = $this->recorded_zip_basenames();
		$now      = time();

		foreach ( $this->zip_copies() as $zip_file ) {
			$modified = filemtime( $zip_file );

			if ( false === $modified ) {
				continue;
			}

			$age     = $now - $modified;
			$max_age = isset( $recorded[ basename( $zip_file ) ] ) ? $lifetime : $retention;

			if ( $age <= $max_age ) {
				continue;
			}

			$this->delete_zip_path( $zip_file );
		}

		// Unconditional: wp_delete_file() returns void on older WordPress, so nothing here
		// can reliably report whether a file was removed. This is one option read.
		$this->prune_missing_zip_records();
	}

	/**
	 * Remove every copy whose filename is guessable, once per site.
	 *
	 * This is the remediation half, and it is deliberately NOT age-based: the exposure is
	 * the predictable name, so a copy carrying one is removed on sight rather than after a
	 * grace period. Copies written by this version are never touched, because their name
	 * is not guessable and sweeping a live one would cost the sync permanently (see
	 * ZIP_MAX_LIFETIME).
	 *
	 * @return void
	 */
	public function purge_guessable_zip_copies_once() {
		try {
			if ( Option::get_option( 'instawp_sync_zip_purged' ) === self::ZIP_PURGE_VERSION ) {
				return;
			}

			if ( ! defined( 'INSTAWP_BACKUP_DIR' ) ) {
				return;
			}

			$remaining = false;
			$recorded  = $this->recorded_zip_basenames();
			$pending   = 0;

			foreach ( $this->zip_copies() as $zip_file ) {
				$name = basename( $zip_file );

				if ( preg_match( self::ZIP_UNGUESSABLE_PATTERN, $name ) ) {
					continue;
				}

				// A legacy copy can still be recorded, i.e. its sync event has not completed.
				// Purging it ends that sync rather than delaying it (see ZIP_MAX_LIFETIME), and
				// the exposure wins — but the user's next sync then fails at the destination
				// with nothing to explain it, so the count is logged for support.
				if ( isset( $recorded[ $name ] ) ) {
					$pending++;
				}

				$this->delete_zip_path( $zip_file );

				if ( file_exists( $zip_file ) ) {
					$remaining = true;
				}
			}

			if ( $pending > 0 ) {
				Helper::add_error_log( array(
					'title'   => 'instawp: purged predictably-named sync copies with a pending event',
					'message' => 'Those plugins/themes must be re-uploaded on the source to sync again.',
					'count'   => $pending,
				) );
			}

			$this->prune_missing_zip_records();

			// Recorded only once nothing guessable is left, so a transient permission problem
			// is retried on a later request instead of latching the site as remediated. Same
			// reasoning as the directory guard in InstaWP_Hooks::protect_backups_dir().
			if ( ! $remaining ) {
				// Autoloaded: this is read on every admin_init once latched, and a
				// non-autoloaded read is a query per request on a site with no object cache.
				Option::update_option( 'instawp_sync_zip_purged', self::ZIP_PURGE_VERSION, true );
			}
		} catch ( \Throwable $th ) {
			// Runs on admin_init; a filesystem edge case must never fatal the request. The
			// flag is not recorded, so the purge is retried on a later request.
			Helper::add_error_log( array( 'title' => 'instawp: purge_guessable_zip_copies_once failed' ), $th );
		}
	}

	/**
	 * Every copied zip on disk, across both sub-directories.
	 *
	 * Only `*.zip` is matched, so the index.php and .htaccess guards are never candidates.
	 *
	 * @return array
	 */
	private function zip_copies() {
		$copies = array();

		foreach ( array( 'plugins', 'themes' ) as $subdirectory ) {
			$zip_files = glob( INSTAWP_BACKUP_DIR . $subdirectory . DIRECTORY_SEPARATOR . '*.zip' );

			if ( empty( $zip_files ) || ! is_array( $zip_files ) ) {
				continue;
			}

			foreach ( $zip_files as $zip_file ) {
				if ( is_file( $zip_file ) ) {
					$copies[] = $zip_file;
				}
			}
		}

		return $copies;
	}

	/**
	 * Filenames of the copies currently named by a stored record, keyed by basename.
	 *
	 * Matched on basename rather than full path on purpose: the record holds a URL and the
	 * sweep holds a glob() path, and reconciling those two into one comparable string
	 * depends on WP_CONTENT_DIR and on separator handling. A basename needs neither. Names
	 * carry 32 random characters so a collision across the two sub-directories is not a
	 * practical concern, and the failure direction is safe either way — a mismatch keeps a
	 * file longer, it never deletes a live one.
	 *
	 * @return array
	 */
	private function recorded_zip_basenames() {
		$names = array();

		foreach ( $this->get_zip_records() as $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $data ) {
				if ( empty( $data['zip_url'] ) ) {
					continue;
				}

				$path = wp_parse_url( $data['zip_url'], PHP_URL_PATH );

				if ( ! empty( $path ) ) {
					$names[ basename( $path ) ] = true;
				}
			}
		}

		return $names;
	}

	/**
	 * Delete one copied zip, refusing anything outside the backups directory.
	 *
	 * The sweeps deliberately do not route through delete_zip_file(), which is gated on
	 * instawp_is_admin() — that requires is_admin() AND a logged-in user, neither of which
	 * holds under Action Scheduler, so a gated sweep would be a silent no-op.
	 *
	 * @param string $zip_path
	 *
	 * @return void
	 */
	private function delete_zip_path( $zip_path ) {
		$backup_dir = rtrim( str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, INSTAWP_BACKUP_DIR ), DIRECTORY_SEPARATOR );
		$normalized = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $zip_path );

		if ( strpos( $normalized, $backup_dir . DIRECTORY_SEPARATOR ) !== 0 ) {
			return;
		}

		wp_delete_file( $zip_path );
	}

	/**
	 * Read an age in seconds from a filter, falling back to the default when unusable.
	 *
	 * A non-positive value would delete copies the moment they are written, which breaks
	 * sync rather than securing it.
	 *
	 * @param string $filter
	 * @param int    $default
	 *
	 * @return int
	 */
	private function bounded_age( $filter, $default ) {
		$value = (int) apply_filters( $filter, $default );

		return $value > 0 ? $value : $default;
	}

	/**
	 * Drop records whose file is no longer on disk.
	 *
	 * @return void
	 */
	private function prune_missing_zip_records() {
		$records  = $this->get_zip_records();
		$modified = false;

		foreach ( $records as $type => $items ) {
			if ( ! is_array( $items ) ) {
				continue;
			}

			foreach ( $items as $slug => $data ) {
				// A record with no URL names nothing and can never be resolved, so it is
				// dropped alongside the ones whose file has gone.
				if ( ! empty( $data['zip_url'] ) && $this->verify_copied_zip_exists( $data['zip_url'] ) ) {
					continue;
				}

				unset( $records[ $type ][ $slug ] );
				$modified = true;
			}
		}

		if ( $modified ) {
			Option::update_option( self::ZIP_STORAGE_OPTION, $records );
		}
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
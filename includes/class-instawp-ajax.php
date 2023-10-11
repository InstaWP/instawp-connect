<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_AJAX {
	public $instawp_log;

	public function __construct() {

		add_action( 'wp_ajax_instawp_settings_call', array( $this, 'instawp_settings_call' ) );
		add_action( 'wp_ajax_instawp_connect', array( $this, 'connect' ) );
		add_action( 'init', array( $this, 'deleter_folder_handle' ) );
		add_action( 'admin_notices', array( $this, 'instawp_connect_reset_admin_notices' ) );

		// Management
		add_action( 'wp_ajax_instawp_save_management_settings', array( $this, 'save_management_settings' ) );
		add_action( 'wp_ajax_instawp_disconnect_plugin', array( $this, 'disconnect_api' ) );
		add_action( 'wp_ajax_instawp_clear_staging_sites', array( $this, 'clear_staging_sites' ) );
		add_action( 'wp_ajax_instawp_get_dir_contents', array( $this, 'get_dir_contents' ) );
		add_action( 'wp_ajax_instawp_get_database_tables', array( $this, 'get_database_tables' ) );
		add_action( 'wp_ajax_instawp_get_large_files', array( $this, 'get_large_files' ) );

		// Go Live redirect
		add_action( 'wp_ajax_instawp_go_live', array( $this, 'go_live_redirect_url' ) );

		add_action( 'wp_ajax_instawp_migrate_init', array( $this, 'instawp_migrate_init' ) );
		add_action( 'wp_ajax_instawp_migrate_progress', array( $this, 'instawp_migrate_progress' ) );
	}


	function instawp_migrate_progress() {

		$progress          = array(
			'progress_files'   => 0,
			'progress_db'      => 0,
			'progress_restore' => 0,
		);
		$migration_details = InstaWP_Setting::get_option( 'instawp_migration_details', [] );
		$migrate_id        = InstaWP_Setting::get_args_option( 'migrate_id', $migration_details );
		$migrate_key       = InstaWP_Setting::get_args_option( 'migrate_key', $migration_details );

		if ( empty( $migrate_id ) || empty( $migrate_key ) ) {
			wp_send_json_success( $progress );
		}

		$response = InstaWP_Curl::do_curl( "migrates-v3/{$migrate_id}", [ 'migrate_key' => $migrate_key ] );

		if ( isset( $response['success'] ) && $response['success'] !== true ) {
			error_log( json_encode( $response ) );
			wp_send_json_error( [ 'message' => $response['message'] ?? '' ] );
		}

		$response_data                     = InstaWP_Setting::get_args_option( 'data', $response, [] );
		$auto_login_hash                   = $response_data['dest_wp']['auto_login_hash'] ?? '';
		$response_data['progress_files']   = $response_data['progress_files'] ?? 0;
		$response_data['progress_db']      = $response_data['progress_db'] ?? 0;
		$response_data['progress_restore'] = $response_data['progress_restore'] ?? 0;
		$migration_finished                = isset( $response_data['stage']['migration-finished'] ) && (bool) $response_data['stage']['migration-finished'];
		$migration_failed                  = isset( $response_data['stage']['failed'] ) && (bool) $response_data['stage']['failed'];

		if ( $migration_failed ) {
			instawp_reset_running_migration();
			wp_send_json_error( [ 'message' => esc_html__( 'Migration failed.' ) ] );
		}

		if ( $migration_finished ) {
			instawp_reset_running_migration();
		}

		if ( ! empty( $auto_login_hash ) ) {
			$response_data['dest_wp']['auto_login_url'] = sprintf( '%s/wordpress-auto-login?site=%s', InstaWP_Setting::get_api_domain(), $auto_login_hash );
		} else {
			$response_data['dest_wp']['auto_login_url'] = $response_data['dest_wp']['url'] ?? '';
		}

		wp_send_json_success( $response_data );
	}


	function instawp_migrate_init() {

//		wp_send_json_error( [ 'message' => esc_html__( 'Could not create migrate id' ) ] );

		global $wp_version, $wpdb;

		$settings_str = isset( $_POST['settings'] ) ? $_POST['settings'] : '';

		parse_str( $settings_str, $settings_arr );

		$source_domain       = site_url();
		$is_website_on_local = instawp_is_website_on_local();
		$migrate_settings    = InstaWP_Setting::get_args_option( 'migrate_settings', $settings_arr, [] );

		// remove unnecessary settings
		if ( isset( $migrate_settings['screen'] ) ) {
			unset( $migrate_settings['screen'] );
		}

		// Remove instawp connect options
		$migrate_settings['excluded_tables_rows'] = array(
			"{$wpdb->prefix}options" => array(
				'option_name:instawp_api_options',
				'option_name:instawp_connect_id_options',
				'option_name:instawp_sync_parent_connect_data',
				'option_name:instawp_migration_details',
				'option_name:instawp_api_key_config_completed',
			),
		);

		$migrate_args = array(
			'source_domain'       => $source_domain,
			'source_connect_id'   => instawp_get_connect_id(),
			'php_version'         => PHP_VERSION,
			'wp_version'          => $wp_version,
			'plugin_version'      => INSTAWP_PLUGIN_VERSION,
			'is_website_on_local' => $is_website_on_local,
			'settings'            => $migrate_settings
		);

		$migrate_response         = InstaWP_Curl::do_curl( 'migrates-v3', $migrate_args );
		$migrate_response_status  = (bool) InstaWP_Setting::get_args_option( 'success', $migrate_response, true );
		$migrate_response_message = InstaWP_Setting::get_args_option( 'message', $migrate_response );

		if ( $migrate_response_status === false ) {
			error_log( json_encode( $migrate_response ) );

			$migrate_response_message = empty( $migrate_response_message ) ? esc_html__( 'Could not create migrate id.' ) : $migrate_response_message;

			wp_send_json_error( [ 'message' => $migrate_response_message ] );
		}

		$migrate_response_data = InstaWP_Setting::get_args_option( 'data', $migrate_response, [] );
		$migrate_id            = InstaWP_Setting::get_args_option( 'migrate_id', $migrate_response_data );
		$migrate_key           = InstaWP_Setting::get_args_option( 'migrate_key', $migrate_response_data );
		$tracking_url          = InstaWP_Setting::get_args_option( 'tracking_url', $migrate_response_data );
		$migration_details     = array(
			'migrate_id'   => $migrate_id,
			'migrate_key'  => $migrate_key,
			'tracking_url' => $tracking_url,
			'started_at'   => current_time( 'mysql' ),
		);

		update_option( 'instawp_migration_details', $migration_details );

		wp_send_json_success( $migration_details );
	}


	function go_live_redirect_url() {

		$response      = InstaWP_Curl::do_curl( 'get-waas-redirect-url', array( 'source_domain' => site_url() ) );
		$response_data = InstaWP_Setting::get_args_option( 'data', $response, [] );
		$redirect_url  = InstaWP_Setting::get_args_option( 'url', $response_data );

		if ( ! empty( $redirect_url ) ) {
			wp_send_json_success( [ 'redirect_url' => $redirect_url ] );
		}

		wp_send_json_error();
	}

	public function get_dir_contents() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$path                = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( $_POST['path'] ) ) : '/';
		$active_plugins_only = isset( $_POST['active_plugins'] ) ? filter_var( $_POST['active_plugins'], FILTER_VALIDATE_BOOLEAN ) : false;
		$active_themes_only  = isset( $_POST['active_themes'] ) ? filter_var( $_POST['active_themes'], FILTER_VALIDATE_BOOLEAN ) : false;
		$skip_media_folder   = isset( $_POST['skip_media_folder'] ) ? filter_var( $_POST['skip_media_folder'], FILTER_VALIDATE_BOOLEAN ) : false;
		$is_item_checked     = isset( $_POST['is_checked'] ) ? filter_var( $_POST['is_checked'], FILTER_VALIDATE_BOOLEAN ) : false;
		$is_select_all       = isset( $_POST['select_all'] ) ? filter_var( $_POST['select_all'], FILTER_VALIDATE_BOOLEAN ) : false;
		$sort_by             = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : false;

		if ( ! $path ) {
			wp_send_json_error();
		}
		$dir_data = instawp_get_dir_contents( $path, $sort_by );
		if ( empty( $dir_data ) ) {
			wp_send_json_success( [ 'content' => __( 'Empty folder!', 'instawp-connect' ) ] );
		}

		$list_data      = get_option( 'instawp_large_files_list', [] ) ?? [];
		$paths          = wp_list_pluck( $list_data, 'realpath' );
		$upload_dir     = wp_upload_dir();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$current_theme  = wp_get_theme();
		$themes_dir     = wp_normalize_path( $current_theme->get_theme_root() );
		$theme_path     = wp_normalize_path( $current_theme->get_stylesheet_directory() );
		$template_path  = wp_normalize_path( $current_theme->get_template_directory() );
		$total_size     = 0;
		$total_files    = 0;

		ob_start();
		foreach ( $dir_data as $key => $data ) {
			if ( $data['name'] == "." || $data['name'] == ".." || strpos( $data['full_path'], 'instawp-connect' ) !== false ) {
				continue;
			}
			$total_size  += $data['size'];
			$total_files += $data['count'];
			$skip_media  = ( $skip_media_folder && strpos( $data['full_path'], wp_normalize_path( $upload_dir['basedir'] ) ) !== false );

			$theme_item_checked      = false;
			$can_perform_theme_check = ( $active_themes_only && strpos( $data['full_path'], $themes_dir ) !== false );
			if ( $can_perform_theme_check ) {
				$theme_item_checked = true;

				if ( in_array( $data['full_path'], [ $theme_path, $template_path, $themes_dir, $themes_dir . '/index.php' ] )
				     || strpos( $data['full_path'], $theme_path ) !== false
				     || strpos( $data['full_path'], $template_path ) !== false ) {

					$theme_item_checked = false;
				}
			}

			$plugin_item_checked      = false;
			$can_perform_plugin_check = ( $active_plugins_only && strpos( $data['full_path'], wp_normalize_path( WP_PLUGIN_DIR ) ) !== false );
			if ( $can_perform_plugin_check ) {
				$plugin_item_checked = true;

				if ( in_array( $data['full_path'], [ wp_normalize_path( WP_PLUGIN_DIR ), wp_normalize_path( WP_PLUGIN_DIR ) . '/index.php' ] )
				     || in_array( basename( $data['relative_path'] ), array_map( 'dirname', $active_plugins ) ) ) {

					$plugin_item_checked = false;
				}
			}

			$is_checked  = ( in_array( $data['full_path'], $paths ) || $skip_media || $theme_item_checked || $plugin_item_checked );
			$is_disabled = ( $is_checked || $can_perform_theme_check || $can_perform_plugin_check );
			$element_id  = wp_generate_uuid4(); ?>

            <div class="flex flex-col gap-5 item">
                <div class="flex justify-between items-center">
                    <div class="flex items-center cursor-pointer" style="transform: translate(0em);">
						<?php if ( $data['type'] === 'folder' ) : ?>
                            <div class="p-2 pl-0 expand-folder" data-expand-folder="<?php echo esc_attr( $data['relative_path'] ); ?>">
                                <svg width="8" height="5" viewBox="0 0 8 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="rotate-icon">
                                    <path d="M4.75504 4.09984L5.74004 3.11484L7.34504 1.50984C7.68004 1.16984 7.44004 0.589844 6.96004 0.589844L3.84504 0.589844L1.04004 0.589843C0.560037 0.589843 0.320036 1.16984 0.660037 1.50984L3.25004 4.09984C3.66004 4.51484 4.34004 4.51484 4.75504 4.09984Z" fill="#4F4F4F"/>
                                </svg>
                            </div>
						<?php endif; ?>
                        <input name="migrate_settings[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" type="checkbox" class="instawp-checkbox exclude-file-item !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" <?php checked( $is_checked || $is_item_checked || $is_select_all, true ); ?> <?php disabled( $is_disabled, true ); ?> data-size="<?php echo esc_html( $data['size'] ); ?>">
                        <label for="<?php echo esc_attr( $element_id ); ?>" class="text-sm font-medium text-grayCust-800 truncate"<?php echo ( $data['type'] === 'file' ) ? ' style="width: calc(400px - 1em);"' : ''; ?>><?php echo esc_html( $data['name'] ); ?></label>
                    </div>
                    <div class="flex items-center" style="width: 105px;">
                        <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.33333 6.49984H11.6667M2.33333 6.49984C1.59695 6.49984 1 5.90288 1 5.1665V2.49984C1 1.76346 1.59695 1.1665 2.33333 1.1665H11.6667C12.403 1.1665 13 1.76346 13 2.49984V5.1665C13 5.90288 12.403 6.49984 11.6667 6.49984M2.33333 6.49984C1.59695 6.49984 1 7.09679 1 7.83317V10.4998C1 11.2362 1.59695 11.8332 2.33333 11.8332H11.6667C12.403 11.8332 13 11.2362 13 10.4998V7.83317C13 7.09679 12.403 6.49984 11.6667 6.49984M10.3333 3.83317H10.34M10.3333 9.1665H10.34" stroke="#111827" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <div class="text-sm font-medium text-grayCust-800 ml-2"><?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?></div>
                    </div>
                </div>
            </div>
		<?php }

		$content = ob_get_clean();
		$data    = [
			'content' => '<div class="flex flex-col gap-5">' . $content . '</div>',
			'size'    => instawp()->get_file_size_with_unit( $total_size ),
			'count'   => $total_files,
		];
		wp_send_json_success( $data );
	}

	public function get_database_tables() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$sort_by    = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : false;
		$tables     = instawp_get_database_details( $sort_by );
		$table_size = array_sum( wp_list_pluck( $tables, 'size' ) );

		ob_start();
		if ( ! empty( $tables ) ) { ?>
            <div class="flex flex-col gap-5">
				<?php foreach ( $tables as $table ) {
					$element_id = wp_generate_uuid4(); ?>
                    <div class="flex flex-col gap-5 item">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center cursor-pointer" style="transform: translate(0em);">
                                <input name="instawp_migrate[excluded_tables][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $table['name'] ); ?>" type="checkbox" class="instawp-checkbox exclude-database-item !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" data-size="<?php echo esc_html( $table['size'] ); ?>">
                                <label for="<?php echo esc_attr( $element_id ); ?>" class="text-sm font-medium text-grayCust-800 truncate" style="width: calc(400px - 1em);"><?php echo esc_html( $table['name'] ); ?> (<?php printf( __( '%s rows', 'instawp-connect' ), $table['rows'] ); ?>)</label>
                            </div>
                            <div class="flex items-center" style="width: 105px;">
                                <svg width="14" height="13" viewBox="0 0 14 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.33333 6.49984H11.6667M2.33333 6.49984C1.59695 6.49984 1 5.90288 1 5.1665V2.49984C1 1.76346 1.59695 1.1665 2.33333 1.1665H11.6667C12.403 1.1665 13 1.76346 13 2.49984V5.1665C13 5.90288 12.403 6.49984 11.6667 6.49984M2.33333 6.49984C1.59695 6.49984 1 7.09679 1 7.83317V10.4998C1 11.2362 1.59695 11.8332 2.33333 11.8332H11.6667C12.403 11.8332 13 11.2362 13 10.4998V7.83317C13 7.09679 12.403 6.49984 11.6667 6.49984M10.3333 3.83317H10.34M10.3333 9.1665H10.34" stroke="#111827" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                <div class="text-sm font-medium text-grayCust-800 ml-2"><?php echo esc_html( instawp()->get_file_size_with_unit( $table['size'] ) ); ?></div>
                            </div>
                        </div>
                    </div>
				<?php } ?>
            </div>
		<?php }

		$content = ob_get_clean();
		$data    = [
			'content' => $content,
			'size'    => instawp()->get_file_size_with_unit( $table_size ),
			'count'   => count( $tables ),
		];

		wp_send_json_success( $data );
	}

	public function get_large_files() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$skip     = isset( $_POST['skip'] ) ? filter_var( $_POST['skip'], FILTER_VALIDATE_BOOLEAN ) : false;
		$generate = isset( $_POST['generate'] ) ? filter_var( $_POST['generate'], FILTER_VALIDATE_BOOLEAN ) : false;

		if ( $generate ) {
			delete_option( 'instawp_large_files_list' );
			as_enqueue_async_action( 'instawp_prepare_large_files_list_async', [], 'instawp-connect', true );
			delete_transient( 'instawp_generate_large_files' );
		}

		$list      = get_option( 'instawp_large_files_list' );
		$list_data = ( ! empty( $list ) && is_array( $list ) ) ? $list : [];

		ob_start();
		if ( ! empty( $list_data ) ) { ?>
            <div class="bg-yellow-50 border border-2 border-r-0 border-y-0 border-l-orange-400 rounded-lg text-sm text-orange-700 p-4 flex flex-col items-start gap-3">
                <div class="flex items-center gap-3">
                    <div class="text-sm font-medium"><?php esc_html_e( 'We have identified following large files in your installation:', 'instawp-connect' ); ?></div>
                </div>
                <div class="flex flex-col items-start gap-3">
					<?php foreach ( $list_data as $data ) {
						$element_id = wp_generate_uuid4(); ?>
                        <div class="flex justify-between items-center text-xs">
                            <input type="checkbox" name="instawp_migrate[migrate_settings][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" class="instawp-checkbox exclude-file-item large-file !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" <?php checked( $skip, true ); ?>>
                            <label for="<?php echo esc_attr( $element_id ); ?>"><?php echo esc_html( $data['relative_path'] ); ?> (<?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?>)</label>
                        </div>
					<?php } ?>
                </div>
            </div>
			<?php
		}

		$content = ob_get_clean();
		wp_send_json_success( [
			'content'  => $content,
			'has_data' => boolval( get_transient( 'instawp_generate_large_files' ) ),
		] );
	}

	public function save_management_settings() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$option_name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$option_value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		if ( ! $option_name || ! $option_value ) {
			wp_send_json_error();
		}

		update_option( $option_name, $option_value );
		wp_send_json_success();
	}

	public function disconnect_api() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$check_api    = isset( $_POST['api'] ) ? filter_var( $_POST['api'], FILTER_VALIDATE_BOOLEAN ) : false;
		$api_response = InstaWP_Curl::do_curl( 'connect/' . instawp_get_connect_id() . '/disconnect' );

		if ( $check_api && ( empty( $api_response['success'] ) || ! $api_response['success'] ) ) {
			wp_send_json_error( [
				'message' => $api_response['message'],
			] );
		}

		instawp_reset_running_migration( 'hard', false );

		wp_send_json_success( [
			'message' => esc_html__( 'Plugin reset successfully.' ) . $api_response['message']
		] );
	}

	public function clear_staging_sites() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		delete_transient( 'instawp_staging_sites' );

		wp_send_json_success();
	}

	// Set transient admin notice function
	public function instawp_connect_reset_admin_notices() {
		if ( isset( $_GET['page'] ) && $_GET['page'] === "instawp-settings" ) {
			$plugins_reset_notice = get_transient( 'instawp_connect_plugin_reset_notice' );
			if ( false !== $plugins_reset_notice ) {
				$html = '<div class="notice notice-warning is-dismissible">';
				$html .= '<p>';
				$html .= $plugins_reset_notice;
				$html .= '</p>';
				$html .= '</div>';
				echo $html;
				delete_transient( 'instawp_connect_plugin_reset_notice' );
			}
		}
	}

	// Remove From settings internal
	public static function deleter_folder_handle() {
		if ( isset( $_REQUEST['delete_wpnonce'] ) && wp_verify_nonce( $_REQUEST['delete_wpnonce'], 'delete_wpnonce' ) ) {

			/* Delete Instawp related Options Start */
			global $wpdb;
			$options_table = $wpdb->prefix . "options";
			$sql           = "DELETE FROM $options_table WHERE option_name LIKE '%instawp%' AND option_name !='instawp_api_url'";
			$query         = $wpdb->query( $sql );
			/* Delete Instawp related Options End */

			self::instawp_folder_remover_handle();
			//After Delete Option Set API Domain
			InstaWP_Setting::set_api_domain();

			$transient_message = __( "InstaWP Connect Settings has been reset successfully.", 'instawp-connect' );

			set_transient( 'instawp_connect_plugin_reset_notice', $transient_message, MINUTE_IN_SECONDS );

			$redirect_url = admin_url( "admin.php?page=instawp-settings" );
			wp_redirect( $redirect_url );
			exit();
		}
	}

	public function instawp_settings_call() {

		$connect_ids = get_option( 'instawp_connect_id_options', '' );

		if ( isset( $_POST['instawp_api_url_internal'] ) ) {
			$instawp_api_url_internal = $_POST['instawp_api_url_internal'];
			InstaWP_Setting::set_api_domain( $instawp_api_url_internal );
		}

		$message           = '';
		$resType           = false;
		$connect_options   = get_option( 'instawp_api_options', '' );
		$instawp_db_method = isset( $_POST['instawp_db_method'] ) ? sanitize_text_field( $_POST['instawp_db_method'] ) : '';

		update_option( 'instawp_db_method', $instawp_db_method );

		if (
			isset( $connect_ids['data']['id'] ) &&
			! empty( $connect_ids['data']['id'] ) &&
			isset( $_POST['api_heartbeat'] ) &&
			! empty( $_POST['api_heartbeat'] ) &&
			! empty( $connect_options ) &&
			! empty( $connect_options['api_key'] )
		) {
			$api_heartbeat = intval( trim( $_REQUEST['api_heartbeat'] ) );

			$resType = true;
			$message = 'Settings saved successfully';
			update_option( 'instawp_heartbeat_option', $api_heartbeat );

			$heartbeat_option_val = (int) get_option( "instawp_heartbeat_option" );

			if ( (int) $heartbeat_option_val !== intval( $api_heartbeat ) ) {
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );
			} else {
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );

				if ( ! wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {
					wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action' );
				}
			}
		} else {
			$resType = false;
			$message = 'something wrong';
		}

		$res_array            = array();
		$res_array['message'] = $message;
		$res_array['resType'] = $resType;
		echo json_encode( $res_array );
		wp_die();
	}

	public function connect() {

		global $InstaWP_Curl;
		$this->ajax_check_security();
		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/connects';

		// $connect_options = get_option('instawp_api_options', '');
		// if (!isset($connect_options['api_key']) && empty($connect_options['api_key'])) {
		//    $res['message'] = 'API Key is required';
		//    echo json_encode($res);
		//    wp_die();
		// }
		// $api_key = $connect_options['api_key'];
		$php_version = substr( phpversion(), 0, 3 );

		/*Get username*/
		$username    = null;
		$admin_users = get_users(
			array(
				'role__in' => array( 'administrator' ),
				'fields'   => array( 'user_login' )
			)
		);

		if ( ! empty( $admin_users ) ) {
			if ( is_null( $username ) ) {
				foreach ( $admin_users as $admin ) {
					$username = $admin->user_login;
				}
			}
		}
		/*Get username closes*/
		$body = json_encode(
			array(
				"url"         => get_site_url(),
				"php_version" => $php_version,
				"username"    => ! is_null( $username ) ? base64_encode( $username ) : "",
			)
		);

		/*Debugging*/
		error_log( strtoupper( "on connect call sent data ---> " ) . print_r( json_decode( $body, true ), true ) );
		/*Debugging*/

		$curl_response = $InstaWP_Curl->curl( $url, $body );
		update_option( 'instawp_connect_id_options_err', $curl_response );
		if ( $curl_response['error'] == false ) {

			$response = (array) json_decode( $curl_response['curl_res'], true );

			if ( $response['status'] == true ) {
				$connect_options                = InstaWP_Setting::get_option( 'instawp_connect_options', array() );
				$connect_id                     = $response['data']['id'];
				$connect_options[ $connect_id ] = $response;
				update_option( 'instawp_connect_id_options', $response ); // old
				//InstaWP_Setting::update_connect_option('instawp_connect_options',$connect_options,$connect_id);

				/* RUN CRON ON CONNECT START */
				$timestamp = wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' );
				wp_unschedule_event( $timestamp, 'instwp_handle_heartbeat_cron_action' );

				if ( ! wp_next_scheduled( 'instwp_handle_heartbeat_cron_action' ) ) {
					wp_schedule_event( time(), 'instawp_heartbeat_interval', 'instwp_handle_heartbeat_cron_action' );
				}
				/* RUN CRON ON CONNECT END */

				$res['message'] = $response['message'];
				$res['error']   = false;
			} else {
				update_option( 'instawp_connect_id_options_err', $response );
				$res['message'] = 'Something Went Wrong. Please try again';
				$res['error']   = true;
			}
		} else {
			$res['message'] = 'Something Went Wrong. Please try again';
			$res['error']   = true;
		}

		echo json_encode( $res );
		wp_die();
	}

	public function test_connect() {

		$this->ajax_check_security();
		$res        = array(
			'error'   => true,
			'message' => '',
		);
		$api_doamin = InstaWP_Setting::get_api_domain();
		$url        = $api_doamin . INSTAWP_API_URL . '/connects/';

		$connect_options = get_option( 'instawp_api_options', '' );
		if ( ! isset( $connect_options['api_key'] ) && empty( $connect_options['api_key'] ) ) {
			$res['message'] = 'API Key is required';
			echo json_encode( $res );
			wp_die();
		}
		$api_key = $connect_options['api_key'];
		$header  = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Accept'        => 'application/json',
			'Content-Type'  => 'application/json;charset=UTF-8',

		);
		$body    = json_encode( array( 'url' => get_site_url() ) );

		print_r( $body );

		$response      = wp_remote_post( $url, array(
			'headers' => $header,
			'body'    => json_encode( $body ),

		) );
		$response_code = wp_remote_retrieve_response_code( $response );
		print_r( $response );
		if ( ! is_wp_error( $response ) && $response_code == 200 ) {
			$body = (array) json_decode( wp_remote_retrieve_body( $response ), true );
		}
		echo json_encode( $res );
		wp_die();
	}

	public function ajax_check_security( $role = 'administrator' ) {
		check_ajax_referer( 'instawp_ajax', 'nonce' );
		$check = is_admin() && current_user_can( $role );
		$check = apply_filters( 'instawp_ajax_check_security', $check );
		if ( ! $check ) {
			wp_die();
		}
	}
}

new InstaWP_AJAX();

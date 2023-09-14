<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

class InstaWP_AJAX {
	public $instawp_log;

	public function __construct() {
		$this->instawp_log = new InstaWP_Log();

		add_action( 'wp_ajax_instawp_settings_call', array( $this, 'instawp_settings_call' ) );
		add_action( 'wp_ajax_instawp_connect', array( $this, 'connect' ) );
		add_action( 'wp_ajax_instawp_check_staging', array( $this, 'instawp_check_staging' ) );
		add_action( 'wp_ajax_instawp_logger', array( $this, 'instawp_logger_handle' ) );
		add_action( 'init', array( $this, 'deleter_folder_handle' ) );
		add_action( 'admin_notices', array( $this, 'instawp_connect_reset_admin_notices' ) );

		// Management
		add_action( 'wp_ajax_instawp_save_management_settings', array( $this, 'save_management_settings' ) );
		add_action( 'wp_ajax_instawp_disconnect_plugin', array( $this, 'disconnect_api' ) );
		add_action( 'wp_ajax_instawp_clear_staging_sites', array( $this, 'clear_staging_sites' ) );
		add_action( 'wp_ajax_instawp_get_dir_contents', array( $this, 'get_dir_contents' ) );
		add_action( 'wp_ajax_instawp_get_large_files', array( $this, 'get_large_files' ) );

		// Go Live redirect
		add_action( 'wp_ajax_instawp_go_live', array( $this, 'go_live_redirect_url' ) );
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
			wp_send_json_success( 'Empty folder!' );
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

		ob_start();
		foreach ( $dir_data as $key => $data ) {
			if ( $data['name'] == "." || $data['name'] == ".." ) {
				continue;
			}
			$total_size = $total_size + $data['size'];
			$skip_media = ( $skip_media_folder && strpos( $data['full_path'], wp_normalize_path( $upload_dir['basedir'] ) ) !== false );

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
						<input name="instawp_migrate[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" type="checkbox" class="instawp-checkbox exclude-item !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" <?php checked( $is_checked  || $is_item_checked || $is_select_all, true ); ?> <?php disabled( $is_disabled, true ); ?> data-size="<?php echo esc_html( $data['size'] ); ?>">
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
		wp_send_json_success( '<div class="flex flex-col gap-5">' . $content . '</div>' );
	}

	public function get_large_files() {
		check_ajax_referer( 'instawp-migrate', 'security' );

		$skip      = isset( $_POST['skip'] ) ? filter_var( $_POST['skip'], FILTER_VALIDATE_BOOLEAN ) : false;
		$list_data = get_option( 'instawp_large_files_list', [] ) ?? [];

		ob_start();
		if ( ! empty( $list_data ) ) { 
			foreach ( $list_data as $data ) {
				$element_id = wp_generate_uuid4(); ?>
				<div class="flex justify-between items-center text-xs">
					<input type="checkbox" name="instawp_migrate[excluded_paths][]" id="<?php echo esc_attr( $element_id ); ?>" value="<?php echo esc_attr( $data['relative_path'] ); ?>" class="instawp-checkbox exclude-item large-file !mt-0 !mr-3 rounded border-gray-300 text-primary-900 focus:ring-primary-900" <?php checked( $skip, true ); ?>>
					<label for="<?php echo esc_attr( $element_id ); ?>"><?php echo esc_html( $data['relative_path'] ); ?> (<?php echo esc_html( instawp()->get_file_size_with_unit( $data['size'] ) ); ?>)</label>
				</div>
			<?php }
		}

		$content = ob_get_clean();
		wp_send_json_success( $content );
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

	/*Remove un-usable data after our staging creation process is done*/
	public static function instawp_folder_remover_handle() {
		$folder_name        = 'instawpbackups';
		$dirPath            = WP_CONTENT_DIR . '/' . $folder_name;
		$dirPathLogFolder   = $dirPath . '/instawp_log';
		$dirPathErrorFolder = $dirPathLogFolder . '/error';

		if ( substr( $dirPath, strlen( $dirPath ) - 1, 1 ) != '/' ) {
			$dirPath .= '/';
		}
		if ( file_exists( $dirPath ) && is_dir( $dirPath ) ) {
			$instawpbackups_zip_files = glob( $dirPath . "*.zip", GLOB_MARK | GLOB_BRACE );
			foreach ( $instawpbackups_zip_files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}

		//log folder
		if ( substr( $dirPathLogFolder, strlen( $dirPathLogFolder ) - 1, 1 ) != '/' ) {
			$dirPathLogFolder .= '/';
		}
		if ( file_exists( $dirPathLogFolder ) && is_dir( $dirPathLogFolder ) ) {
			$instawp_log_txt_files = glob( $dirPathLogFolder . "*.txt", GLOB_MARK | GLOB_BRACE );
			foreach ( $instawp_log_txt_files as $lfile ) {
				if ( is_file( $lfile ) ) {
					unlink( $lfile );
				}
			}
		}

		//error folder
		if ( substr( $dirPathErrorFolder, strlen( $dirPathErrorFolder ) - 1, 1 ) != '/' ) {
			$dirPathErrorFolder .= '/';
		}
		if ( file_exists( $dirPathErrorFolder ) && is_dir( $dirPathErrorFolder ) ) {
			$errorfiles = glob( $dirPathErrorFolder . '{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE );
			foreach ( $errorfiles as $efile ) {
				if ( is_file( $efile ) ) {
					unlink( $efile );
				}
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

	/*Handle Js call to remove option*/
	public function instawp_logger_handle() {
		$res_array = array();
		if (
			! empty( $_POST['n'] ) &&
			wp_verify_nonce( $_POST['n'], 'instawp_nlogger_update_option_by-nlogger' ) &&
			! empty( $_POST['l'] )
		) {
			$l = $_POST['l'];
			update_option( 'instawp_finish_upload', array() );
			update_option( 'instawp_staging_list', array() );
			//self::instawp_folder_remover_handle();

			$res_array['message'] = 'success';
			$res_array['status']  = 1;
		} else {
			$res_array['message'] = 'failed';
			$res_array['status']  = 0;
		}

		wp_send_json( $res_array );
		wp_die();
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

	public function instawp_check_staging() {

		//$this->ajax_check_security();

		global $InstaWP_Curl;

		$api_domain        = InstaWP_Setting::get_api_domain();
		$response          = array( 'progress' => 5, 'message' => 'New site creation in progress', );
		$connect_ids       = get_option( 'instawp_connect_id_options', '' );
		$bkp_init_opt      = get_option( 'instawp_backup_init_options', '' );
		$backup_status_opt = get_option( 'instawp_backup_status_options', '' );
		$connect_id        = $connect_ids['data']['id'] ?? '';
		$task_id           = $bkp_init_opt['task_info']['task_id'] ?? '';
		$site_id           = $backup_status_opt['data']['site_id'] ?? '';

		if ( empty( $connect_id ) || empty( $task_id ) || empty( $site_id ) ) {
			echo json_encode( $response );
			wp_die();
		}

		$url_restore_status           = $api_domain . INSTAWP_API_URL . '/connects/get_restore_status';
		$curl_response_restore_status = $InstaWP_Curl->curl( $url_restore_status, json_encode( array( 'connect_id' => $connect_id, 'task_id' => $task_id, 'site_id' => $site_id, ) ) );
		$curl_rd_restore_status       = $curl_response_restore_status['curl_res'] ?? '';
		$curl_rd_restore_status       = json_decode( $curl_rd_restore_status, true );

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['progress'] ) ) {
			$response['progress'] = $curl_rd_restore_status['data']['progress'];
		}

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['message'] ) ) {
			$response['message'] = $curl_rd_restore_status['data']['message'];
		}

		if ( ! isset( $curl_rd_restore_status['status'] ) || $curl_rd_restore_status['status'] == 0 ) {
			echo json_encode( $response );
			wp_die();
		}

		if ( isset( $curl_rd_restore_status['data'] ) && isset( $curl_rd_restore_status['data']['progress'] ) && $curl_rd_restore_status['data']['progress'] == 100 ) {

			$connect_id      = $curl_rd_restore_status['data']['wp'][0]['connect_id'];
			$site_name       = $curl_rd_restore_status['data']['wp'][0]['site_name'];
			$wp_admin_url    = $curl_rd_restore_status['data']['wp'][0]['wp_admin_url'];
			$wp_admin_email  = $curl_rd_restore_status['data']['wp'][0]['wp_admin_email'];
			$wp_username     = $curl_rd_restore_status['data']['wp'][0]['wp_username'];
			$wp_password     = $curl_rd_restore_status['data']['wp'][0]['wp_password'];
			$auto_login_hash = $curl_rd_restore_status['data']['wp'][0]['auto_login_hash'];

			// instawp_staging_insert_site( array(
			// 	'task_id'         => $task_id,
			// 	'connect_id'      => $connect_id,
			// 	'site_name'       => $site_name,
			// 	'site_url'        => str_replace( '/wp-admin', '', $wp_admin_url ),
			// 	'admin_email'     => $wp_admin_email,
			// 	'username'        => $wp_username,
			// 	'password'        => $wp_password,
			// 	'auto_login_hash' => $auto_login_hash,
			// ) );

			$response = array(
				"progress"        => 100,
				"result"          => "success",
				"status"          => 1,
				"details"         => array(
					"name"  => $site_name,
					"url"   => "https://" . str_replace( '/wp-admin', '', $wp_admin_url ),
					"user"  => $wp_username,
					"code"  => $wp_password,
					"login" => add_query_arg( array( 'site' => $auto_login_hash ), $api_domain . '/wordpress-auto-login' ),
				),
				"server_response" => $curl_rd_restore_status,
			);
		}

		echo json_encode( $response );

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

			print_r( $body );
			// $connect_options = array();
			// if ($body['status'] == true) {
			//    $connect_options['api_key']   = $connect_options['api_key'];
			//    $connect_options['connected'] = true;
			//    $connect_options['response']  = $body;
			//    update_option('instawp_connect_id_options', $connect_options);
			//    $res = array(
			//       'error'   => false,
			//       'message' => 'Connected',

			//    );
			// }
			// else {
			//    $res = array(
			//       'error'   => true,
			//       'message' => 'Api Key Not Valid'

			//    );
			// }

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

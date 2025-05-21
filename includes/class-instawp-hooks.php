<?php
/**
 * Class for all hooks
 */

use InstaWP\Connect\Helpers\Curl;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Option;
use InstaWP\Connect\Helpers\Updater;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'InstaWP_Hooks' ) ) {
	class InstaWP_Hooks {

		public function __construct() {
			add_action( 'instawp_connect_connected', array( $this, 'handle_connected' ) );
			add_action( 'load-tools_page_instawp', array( $this, 'handle_connection_state' ) );
			add_action( 'admin_init', array( $this, 'generate_api_key' ) );
			add_action( 'update_option', array( $this, 'manage_update_option' ), 10, 3 );
			add_action( 'init', array( $this, 'handle_hard_disable_seo_visibility' ) );
			add_action( 'admin_init', array( $this, 'handle_clear_all' ) );

			if ( ! is_multisite() || is_main_site() ) {
				add_action( 'admin_bar_menu', array( $this, 'add_instawp_menu_icon' ), 999 );
			}

			add_action( 'admin_bar_menu', array( $this, 'add_staging_indicator' ), 5 );
			add_action( 'wp_enqueue_scripts', array( $this, 'front_enqueue_scripts' ) );
			add_action( 'init', array( $this, 'handle_auto_login_request' ) );
			add_action( 'init', array( $this, 'handle_temporary_login_request' ) );
			add_action( 'admin_notices', array( $this, 'admin_notice' ) );
			add_filter( 'all_plugins', array( $this, 'plugins_data' ) );
			add_filter( 'plugins_api_result', array( $this, 'plugins_api_result' ), 10, 3 );
			add_filter( 'edge_cache_user_can_manage_edge_cache', array( $this, 'remove_edge_cache_submenu' ), 999, 2 );
			add_action( 'instawp_create_update_task', array( $this, 'perform_update_task' ) );

			add_action( 'init', array( $this, 'add_rewrite_rules' ), 1 );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
			add_action( 'parse_request', array( $this, 'handle_migration_through_wp' ) );
		}

		public function handle_connected( $connect_id ) {
			instawp_send_heartbeat( $connect_id );
			instawp_set_staging_sites_list();
		}

		public function handle_connection_state() {
			if ( ! instawp_is_connected_origin_valid() ) {
				instawp_reset_running_migration( 'hard' );
			}
		}

		public function handle_migration_through_wp( $wp ) {
			if ( isset( $wp->query_vars['instawp_serve'] ) ) {
				$serve_file = INSTAWP_PLUGIN_DIR . 'serve.php';

				if ( file_exists( $serve_file ) ) {
					// Prevent WordPress from loading further
					defined( 'SHORTINIT' ) || define( 'SHORTINIT', true );

					include_once $serve_file;
					exit;
				} else {
					header( 'HTTP/1.0 404 Not Found' );
					exit( 'File not found' );
				}
			}
		}

		public function add_query_vars( $query_vars ) {
			$query_vars[] = 'instawp_serve';

			return $query_vars;
		}

		public function add_rewrite_rules() {
			add_rewrite_rule( '^serve-instawp/?$', 'index.php?instawp_serve=1', 'top' );
		}

		public function generate_api_key() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$access_token   = isset( $_REQUEST['access_token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['access_token'] ) ) : '';
			$jwt            = isset( $_REQUEST['jwt'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['jwt'] ) ) : '';
			$success_status = isset( $_REQUEST['success'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['success'] ) ) : '';
			$instawp_nonce  = isset( $_REQUEST['instawp-nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['instawp-nonce'] ) ) : '';

			if ( ! empty( $access_token ) && ! empty( $jwt ) && 'true' === $success_status && wp_verify_nonce( $instawp_nonce, 'instawp_connect_nonce' ) ) {
				$api_key = Helper::get_api_key();

				if ( empty( $api_key ) && $api_key !== $access_token ) {
					Helper::generate_api_key( $access_token, $jwt );

					wp_safe_redirect( admin_url( 'tools.php?page=instawp' ) );
					exit();
				}
			}
		}

		public function handle_auto_login_request() {

			$url_args       = array_map( 'sanitize_text_field', $_GET );
			$redirect_path  = Helper::get_args_option( 'redir', $url_args );
			$reauth         = Helper::get_args_option( 'r', $url_args );
			$login_code     = Helper::get_args_option( 'c', $url_args );
			$login_username = Helper::get_args_option( 's', $url_args );
			$login_username = base64_decode( $login_username ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( empty( $reauth ) || empty( $login_code ) || empty( $login_username ) ) {
				return;
			}

			if ( empty( Helper::get_api_key() ) ) {
				return;
			}

			$instawp_login_code = Option::get_option( 'instawp_login_code', array() );
			$saved_login_code   = Helper::get_args_option( 'code', $instawp_login_code );
			$saved_updated_at   = Helper::get_args_option( 'updated_at', $instawp_login_code );
			$redirect           = wp_login_url();

			if ( $saved_login_code && $saved_updated_at && ( time() - intval( $saved_updated_at ) <= 30 ) && $saved_login_code === $login_code && username_exists( $login_username ) ) {
				$redirect   = ! empty( $redirect_path ) ? home_url( rawurldecode( $redirect_path ) ) : admin_url();
				$login_user = get_user_by( 'login', $login_username );

				wp_set_current_user( $login_user->ID, $login_user->user_login );
				wp_set_auth_cookie( $login_user->ID );

				do_action( 'wp_login', $login_user->user_login, $login_user );
			}

			delete_option( 'instawp_login_code' );
			wp_safe_redirect( $redirect );
			exit();
		}

		public function handle_temporary_login_request() {

			$url_args    = array_map( 'sanitize_text_field', $_GET );
			$login_token = Helper::get_args_option( 'iwp-temp-login', $url_args );

			if ( empty( $login_token ) || instawp_is_bot_request() ) {
				return;
			}

			if ( empty( Helper::get_api_key() ) ) {
				return;
			}

			$user = instawp_get_user_by_token( $login_token );
			if ( ! $user || instawp_is_user_login_expired( $user->ID ) || instawp_is_user_attempt_expired( $user->ID ) ) {
				wp_safe_redirect( home_url() );
				exit();
			}

			if ( is_user_logged_in() ) {
				$current_user_id = get_current_user_id();
				if ( $user->ID !== $current_user_id ) {
					wp_logout();
				}
			}

			instawp_reduce_login_attempt( $user->ID );

			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID );

			do_action( 'wp_login', $user->user_login, $user );

			wp_safe_redirect( admin_url() );
			exit();
		}

		public function front_enqueue_scripts() {
			if ( $this->can_show_navbar() ) {
				wp_enqueue_style( 'instawp-common', instaWP::get_asset_url( 'assets/css/common.min.css' ), array(), INSTAWP_PLUGIN_VERSION );
				wp_enqueue_script( 'instawp-common', instaWP::get_asset_url( 'assets/js/common.js' ), array( 'jquery' ), INSTAWP_PLUGIN_VERSION, true );
				wp_localize_script( 'instawp-common', 'instawp_common', InstaWP_Tools::get_localize_data() );
			}
		}

		public function add_staging_indicator( WP_Admin_Bar $admin_bar ) {

			if ( $this->can_show_navbar() && instawp()->is_staging ) {
				$admin_bar->add_node(
					array(
						'id'     => 'instawp_staging_site',
						'title'  => __( 'Staging Site', 'instawp-connect' ),
						'href'   => admin_url( 'tools.php?page=instawp' ),
						'meta'   => array(
							'class' => 'instawp-staging-site',
						),
						'parent' => 'top-secondary',
					)
				);
			}

			$migration_details = Option::get_option( 'instawp_migration_details' );
			$migration_status  = InstaWP_Setting::get_args_option( 'status', $migration_details );
			$is_end_to_end     = (bool) InstaWP_Setting::get_args_option( 'is_end_to_end', $migration_details );

			if ( $migration_status === 'initiated' && $is_end_to_end ) {

				$e2e_tracking_url = InstaWP_Setting::get_args_option( 'e2e_tracking_url', $migration_details );
				$e2e_tracking_url = empty( $e2e_tracking_url ) ? '#' : $e2e_tracking_url;

				$admin_bar->add_node(
					array(
						'id'     => 'instawp_mig_in_progress',
						'title'  => __( 'Migration in Progress', 'instawp-connect' ),
						'href'   => $e2e_tracking_url,
						'meta'   => array(
							'class' => 'instawp-mig-in-progress',
						),
						'parent' => 'top-secondary',
					)
				);

				add_action( 'admin_footer', array( $this, 'deactivation_warning_modal' ) );
			}
		}

		public function deactivation_warning_modal() {
			?>
            <div id="deactivate-modal" class="deactivate-modal">
                <div class="deactivate-modal-content">
                    <h3><?php esc_html_e( 'Are you sure?', 'instawp-connect' ); ?></h3>
                    <p><?php esc_html_e( 'An active migration is in progress. Deactivating the plugin will stop the migration. Do you want to proceed?', 'instawp-connect' ); ?></p>
                    <div class="deactivate-modal-actions">
                        <button id="confirm-deactivate" class="deactivate-modal-confirm"><?php esc_html_e( 'Yes, Deactivate', 'instawp-connect' ); ?></button>
                        <button id="cancel-deactivate" class="deactivate-modal-cancel"><?php esc_html_e( 'No, Continue Migration', 'instawp-connect' ); ?></button>
                    </div>
                </div>
            </div>
			<?php
		}

		public function add_instawp_menu_icon( WP_Admin_Bar $admin_bar ) {
			if ( defined( 'IWP_PLUGIN_TOPBAR_HIDE' ) && IWP_PLUGIN_TOPBAR_HIDE === true ) {
				return;
			}

			$meta_classes = array( 'instawp-sync-recording' );

			if ( 1 === (int) Option::get_option( 'instawp_is_event_syncing', '0' ) ) {
				$meta_classes[] = 'recording-on';
			}

			if ( defined( 'IWP_PLUGIN_TOPBAR_ICON' ) ) {
				$nav_icon = '<img src="' . IWP_PLUGIN_TOPBAR_ICON . '" style="width: 22px; height: 22px;" alt="" />';
			} else {
				$nav_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="17" viewBox="0 0 111 91" fill="none"><path d="M91.6343 33.7798H106.992C107.252 33.779 107.508 33.8467 107.734 33.9759C107.96 34.1053 108.15 34.2919 108.28 34.5167C108.413 34.7416 108.481 34.9971 108.483 35.2576C108.484 35.5181 108.417 35.7744 108.29 36.0008L80.6527 85.0362C80.2323 85.7832 79.6209 86.4049 78.8806 86.838C78.1405 87.2707 77.2998 87.4993 76.4418 87.5001H64.6848C63.7056 87.5001 62.7489 87.202 61.9432 86.6456C61.1361 86.0891 60.5186 85.3007 60.1714 84.3847L57.293 76.8213L57.4954 76.9577C58.4154 77.5532 59.4472 77.9533 60.5281 78.1332C61.6106 78.3131 62.7172 78.269 63.7806 78.0035C64.8439 77.7379 65.8405 77.2568 66.7114 76.5899C67.5807 75.923 68.3049 75.0846 68.8368 74.1263L85.4206 44.429H72.1895C71.9062 44.4348 71.6275 44.3592 71.3854 44.2118C71.1434 44.0641 70.9476 43.8503 70.8234 43.5959C70.6993 43.3412 70.6501 43.0563 70.6818 42.7747C70.7138 42.4933 70.8266 42.2267 71.005 42.0066L104.256 0.653075C104.424 0.471365 104.651 0.354008 104.896 0.321172C105.143 0.288336 105.391 0.342074 105.601 0.473146C105.811 0.604218 105.97 0.804439 106.048 1.0394C106.126 1.27436 106.121 1.52939 106.032 1.76065L91.6343 33.7798Z" fill="url(#paint0_linear_3855_54426)"></path><path d="M37.6351 84.5326C37.6351 84.9216 37.5587 85.3065 37.4092 85.6659C37.261 86.0252 37.043 86.3515 36.7677 86.6269C36.4937 86.9016 36.1658 87.1199 35.8075 87.2686C35.4479 87.4174 35.0624 87.494 34.674 87.494H22.0638C21.4524 87.494 20.857 87.3054 20.3571 86.9532C19.8572 86.6012 19.4799 86.1032 19.2746 85.5277L17.7765 81.2692L2.39451 37.7775C2.23371 37.3254 2.18436 36.8413 2.24963 36.3663C2.31649 35.8909 2.49481 35.4386 2.77182 35.047C3.04883 34.6552 3.41499 34.3357 3.84165 34.1151C4.2667 33.8945 4.73952 33.7793 5.21872 33.7795H16.7401C17.4947 33.779 18.2318 34.0115 18.8511 34.4448C19.4704 34.8784 19.94 35.4917 20.1979 36.2019L36.6337 81.2159L37.476 83.5138C37.5875 83.8414 37.6414 84.1862 37.6351 84.5326Z" fill="#fff"></path><path d="M50.4217 58.8161L40.946 85.5993C40.747 86.1584 40.3792 86.6422 39.8952 86.9847C39.4114 87.3271 38.8317 87.5115 38.2396 87.5126H22.0632C21.4503 87.5112 20.8532 87.3197 20.3534 86.9643C19.8535 86.6089 19.4762 86.1072 19.274 85.5283L17.7695 81.2698C18.0656 81.7555 20.5348 85.546 23.609 81.2165L39.4114 37.9321C39.4114 37.9321 41.3074 36.783 42.4679 37.9321L50.4217 58.8161Z" fill="#fff"></path><path d="M71.7917 68.8607L64.6437 81.6363C64.3507 82.1589 63.9161 82.5875 63.3892 82.871C62.8605 83.1547 62.2636 83.2818 61.6665 83.238C61.0696 83.1936 60.4965 82.9798 60.0172 82.6218C59.5382 82.2635 59.1704 81.7758 58.9569 81.2159L50.4223 58.8155L42.7583 38.6777C42.5927 38.2549 42.2838 37.9043 41.8843 37.6874C41.4864 37.4707 41.0229 37.4018 40.5788 37.4932C40.1634 37.5801 39.7621 37.7318 39.3945 37.9434C39.7861 36.8474 40.4802 35.8846 41.3955 35.1654C42.5131 34.2697 43.9029 33.7811 45.3342 33.7796H55.902C56.9988 33.7772 58.0686 34.113 58.9683 34.7416C59.8661 35.3702 60.5491 36.2608 60.9232 37.2918L71.8984 67.3446C71.9922 67.5898 72.0305 67.8527 72.0131 68.1142C71.9941 68.3761 71.919 68.6308 71.7917 68.8607Z" fill="#fff"></path><defs><linearGradient id="paint0_linear_3855_54426" x1="80.6992" y1="74.2935" x2="103.158" y2="0.311767" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"></stop><stop offset="1" stop-color="#fff"></stop></linearGradient></defs></svg>';
			}

			if ( $this->can_show_navbar() ) {
				$admin_bar->add_menu( array(
					'id'    => 'instawp',
					'title' => $nav_icon,
					'href'  => admin_url( 'tools.php?page=instawp' ),
					'meta'  => array(
						'class' => implode( ' ', $meta_classes ),
					),
				) );

				if ( defined( 'IWP_PLUGIN_TOPBAR_ICON' ) ) {
					return;
				}

				if ( current_user_can( 'manage_options' ) ) {
					$admin_bar->add_menu( array(
						'parent' => 'instawp',
						'id'     => 'instawp-clear-cache',
						'title'  => __( 'Purge All Cache', 'instawp-connect' ),
						'href'   => '#',
						'meta'   => array(
							'class'  => 'instawp-tools',
							'target' => 'cache',
						),
					) );

					$admin_bar->add_menu( array(
						'parent' => 'instawp',
						'id'     => 'instawp-tools',
						'title'  => __( 'Tools', 'instawp-connect' ),
						'href'   => '#',
					) );

					$admin_bar->add_menu( array(
						'parent' => 'instawp-tools',
						'id'     => 'instawp-database-manager',
						'title'  => __( 'Database Manager', 'instawp-connect' ),
						'href'   => '#',
						'meta'   => array(
							'class'  => 'instawp-tools',
							'target' => 'database',
						),
					) );
				}

				$admin_bar->add_menu( array(
					'parent' => 'instawp',
					'id'     => 'instawp-shortcuts',
					'title'  => __( 'Shortcuts', 'instawp-connect' ),
					'href'   => '#',
				) );

				if ( ! instawp()->is_staging ) {
					$admin_bar->add_menu( array(
						'parent' => 'instawp-shortcuts',
						'id'     => 'instawp-create-staging',
						'title'  => __( 'Create Staging', 'instawp-connect' ),
						'href'   => admin_url( 'tools.php?page=instawp&step=1' ),
						'meta'   => array(
							'class'  => 'instawp-shortcuts',
							'target' => 'create',
						),
					) );
				}

				$admin_bar->add_menu( array(
					'parent' => 'instawp-shortcuts',
					'id'     => 'instawp-staging-sites',
					'title'  => __( 'Staging Sites', 'instawp-connect' ),
					'href'   => admin_url( 'tools.php?page=instawp' ),
					'meta'   => array(
						'class'  => 'instawp-shortcuts',
						'target' => 'sites',
					),
				) );

				if ( ! instawp()->is_staging ) {
					$admin_bar->add_menu( array(
						'parent' => 'instawp-shortcuts',
						'id'     => 'instawp-manage',
						'title'  => __( 'Manage', 'instawp-connect' ),
						'href'   => admin_url( 'tools.php?page=instawp' ),
						'meta'   => array(
							'class'  => 'instawp-shortcuts',
							'target' => 'manage',
						),
					) );
				}

				$admin_bar->add_menu( array(
					'parent' => 'instawp-shortcuts',
					'id'     => 'instawp-sync',
					'title'  => __( 'Sync (Beta)', 'instawp-connect' ),
					'href'   => admin_url( 'tools.php?page=instawp' ),
					'meta'   => array(
						'class'  => 'instawp-shortcuts',
						'target' => 'sync',
					),
				) );

				$admin_bar->add_menu( array(
					'parent' => 'instawp-shortcuts',
					'id'     => 'instawp-settings',
					'title'  => __( 'Settings', 'instawp-connect' ),
					'href'   => admin_url( 'tools.php?page=instawp' ),
					'meta'   => array(
						'class'  => 'instawp-shortcuts',
						'target' => 'settings',
					),
				) );

				$connect_id = Helper::get_connect_id();

				if ( ! empty( $connect_id ) && current_user_can( 'manage_options' ) ) {
					$app_domain = Helper::get_api_domain();
					$admin_bar->add_menu( array(
						'parent' => 'instawp',
						'id'     => 'instawp-app-dashboard',
						'title'  => __( 'Go to InstaWP ➝', 'instawp-connect' ),
						'href'   => "$app_domain/connects/$connect_id/dashboard",
						'meta'   => array(
							'target' => '_blank',
						),
					) );
				}

				if ( current_user_can( 'manage_options' ) ) {
					$admin_bar->add_menu( array(
						'parent' => 'instawp',
						'id'     => 'instawp-support',
						'title'  => __( 'Contact Support', 'instawp-connect' ),
						'href'   => 'https://instawp.com/support?utm_source=plugin_settings',
						'meta'   => array(
							'target' => '_blank',
						),
					) );
				}
			}
		}

		private function can_show_navbar() {
			if ( ! apply_filters( 'instawp/filters/display_menu_bar_icon', true ) ) {
				return false;
			}

			global $current_user;

			$can_show = 'off' === Option::get_option( 'instawp_hide_plugin_icon_topbar', 'off' );
			
			if ( $can_show ) {
				$sync_tab_roles = Option::get_option( 'instawp_sync_tab_roles', array( 'administrator' ) );
				$sync_tab_roles = ! is_array( $sync_tab_roles ) || empty( $sync_tab_roles ) ? array( 'administrator' ) : $sync_tab_roles;

				if ( empty( array_intersect( $sync_tab_roles, $current_user->roles ) ) ) {
					$can_show = false;
				}
			}

			if ( $can_show ) {
				$selected_users = Option::get_option( 'instawp_hide_plugin_to_users' );
				if ( ! empty( $selected_users ) && is_array( $selected_users ) && in_array( get_current_user_id(), $selected_users ) ) {
					$can_show = false;
				}
			}

			return $can_show;
		}

		public function handle_hard_disable_seo_visibility() {

			if (
				instawp()->is_staging &&
				empty( Option::get_option( 'instawp_changed_option_blog_public' ) ) &&
				(int) Option::get_option( 'blog_public' ) === 1
			) {
				update_option( 'blog_public', '0' );
			}
		}

		public function manage_update_option( $option_name, $old_value, $new_value ) {

			if ( 'blog_public' === $option_name && intval( $old_value ) === 0 && intval( $new_value ) === 1 ) {
				Option::update_option( 'instawp_changed_option_blog_public', time() );
			}
		}

		public function handle_clear_all() {
			$admin_page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
			$clear_action = isset( $_GET['clear'] ) ? sanitize_text_field( wp_unslash( $_GET['clear'] ) ) : '';
			$connect_id   = isset( $_GET['connect_id'] ) ? intval( $_GET['connect_id'] ) : 0;

			if ( ! empty( $connect_id ) ) {
				Helper::set_connect_id( $connect_id );
			}

			if ( 'instawp' === $admin_page && 'all' === $clear_action ) {
				instawp_reset_running_migration( 'soft', true );

				wp_safe_redirect( admin_url( 'tools.php?page=instawp' ) );
				exit();
			}
		}

		public function admin_notice() {
			if ( ! isset( $_GET['instawp-cache-cleared'] ) ) {
				return;
			}

			$cache_cleared = get_transient( 'instawp_cache_purged' );
			if ( ! $cache_cleared ) {
				return;
			} ?>
            <div class="notice notice-success is-dismissible">
                <p><?php printf( esc_html__( 'Cache cleared for %s.', 'instawp-connect' ), esc_html( join( ', ', wp_list_pluck( $cache_cleared, 'name' ) ) ) ); ?></p>
            </div>
			<?php
			delete_transient( 'instawp_cache_purged' );
		}

		public function plugins_data( $plugins ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return $plugins;
			}

			$key = INSTAWP_PLUGIN_FILE;

			if ( ! isset( $plugins[ $key ] ) ) {
				return $plugins;
			}

			if ( defined( 'IWP_PLUGIN_HIDE' ) && IWP_PLUGIN_HIDE === true ) {
				unset( $plugins[ $key ] );

				return $plugins;
			}

			if ( defined( 'IWP_PLUGIN_NAME' ) ) {
				$plugins[ $key ]['Name'] = IWP_PLUGIN_NAME;
			}

			if ( defined( 'IWP_PLUGIN_DESCRIPTION' ) ) {
				$plugins[ $key ]['Description'] = IWP_PLUGIN_DESCRIPTION;
			}

			if ( defined( 'IWP_PLUGIN_AUTHOR' ) ) {
				$plugins[ $key ]['Author']     = IWP_PLUGIN_AUTHOR;
				$plugins[ $key ]['AuthorName'] = IWP_PLUGIN_AUTHOR;
			}

			if ( defined( 'IWP_PLUGIN_AUTHOR_URL' ) ) {
				$plugins[ $key ]['AuthorURI'] = IWP_PLUGIN_AUTHOR_URL;
			}

			return $plugins;
		}

		public function plugins_api_result( $result, $action, $args ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				return $result;
			}

			if ( ! isset( $result->slug ) ) {
				return $result;
			}

			if ( $result->slug !== INSTAWP_PLUGIN_SLUG ) {
				return $result;
			}

			if ( defined( 'IWP_PLUGIN_NAME' ) ) {
				$result->name = IWP_PLUGIN_NAME;
			}

			if ( defined( 'IWP_PLUGIN_DESCRIPTION' ) ) {
				$result->sections['description'] = IWP_PLUGIN_DESCRIPTION;
			}

			if ( defined( 'IWP_PLUGIN_AUTHOR' ) ) {
				$result->author = IWP_PLUGIN_AUTHOR;
			}

			return $result;
		}

		public function remove_edge_cache_submenu( $can_show, $current_user ) {
			$selected_users = Option::get_option( 'instawp_hide_plugin_to_users' );
			if ( ! empty( $selected_users ) && is_array( $selected_users ) ) {
				$can_show = ! in_array( $current_user->ID, $selected_users );
			}

			return $can_show;
		}

		public function perform_update_task( $items ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';

			$response_data = array();

			if ( in_array( 'plugins', $items ) ) {
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$update_info = get_plugin_updates();

				if ( ! empty( $update_info ) ) {
					$plugins                  = array_chunk( array_keys( $update_info ), 5 );
					$response_data['plugins'] = array();

					foreach ( $plugins as $plugin_chunk ) {
						$update_data = array();

						foreach ( $plugin_chunk as $plugin ) {
							$update_data[] = array(
								'type' => 'plugin',
								'slug' => $plugin,
							);
						}

						$installer                = new Updater( $update_data );
						$response_data['plugins'] = array_merge( $installer->update(), $response_data['plugins'] );
					}
				}
			}

			if ( in_array( 'themes', $items ) ) {
				$update_info = get_theme_updates();

				if ( ! empty( $update_info ) ) {
					$themes                  = array_chunk( array_keys( $update_info ), 5 );
					$response_data['themes'] = array();

					foreach ( $themes as $theme_chunk ) {
						$update_data = array();

						foreach ( $theme_chunk as $theme ) {
							$update_data[] = array(
								'type' => 'theme',
								'slug' => $theme,
							);
						}

						$installer               = new Updater( $update_data );
						$response_data['themes'] = array_merge( $installer->update(), $response_data['themes'] );
					}
				}
			}

			if ( in_array( 'core', $items ) ) {
				$update_info = get_core_updates();

				if ( ! empty( $update_info ) && $update_info[0]->response === 'upgrade' ) {
					$installer             = new Updater( array(
						array(
							'type'    => 'core',
							'slug'    => 'wordpress',
							'version' => $update_info[0]->version,
						),
					) );
					$response              = $installer->update();
					$response_data['core'] = $response['wordpress'];
				}
			}

			as_enqueue_async_action( 'instawp_send_heartbeat', array(), 'instawp-connect' );

			$connect_id = instawp_get_connect_id();
			if ( ! empty( $connect_id ) ) {
				Curl::do_curl( "connects/{$connect_id}/scheduled-updates", array( 'logs' => $response_data ) );
			}
		}
	}
}

new InstaWP_Hooks();

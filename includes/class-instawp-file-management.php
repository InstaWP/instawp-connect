<?php

if ( ! defined( 'INSTAWP_PLUGIN_DIR' ) ) {
	die;
}

if ( ! class_exists( 'InstaWP_File_Management' ) ) {
	class InstaWP_File_Management {
		
		protected static $_instance = null;

		/**
		 * @return InstaWP_File_Management
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		public function __construct() {
			add_action( 'init', [ $this, 'add_endpoint' ] );
			add_action( 'wp', [ $this, 'filter_redirect' ] );
			add_action( 'template_redirect', [ $this, 'redirect' ] );
			add_action( 'instawp_clean_file_manager', [ $this, 'clean' ] );
			add_action( 'admin_post_instawp-file-manager-auto-login', [ $this, 'auto_login' ] );
			add_action( 'admin_post_nopriv_instawp-file-manager-auto-login', [ $this, 'auto_login' ] );
			add_filter( 'query_vars', [ $this, 'query_vars' ] );
			add_filter( 'template_include', [ $this, 'load_template' ] );
		}

		public function add_endpoint() {
			add_rewrite_endpoint( 'instawp-file-manager', EP_ROOT | EP_PAGES );
		}
		
		public function filter_redirect() {
			if ( $this->get_template() ) {
				remove_action( 'template_redirect', 'redirect_canonical' );
				add_filter( 'redirect_canonical', '__return_false' );
			}
		}

		public function redirect() {
			$template_name = get_query_var( 'instawp-file-manager', false );
			if ( $template_name && ! $this->get_template() ) {
				wp_safe_redirect( home_url() );
				exit();
			}
		}

		public function clean( $file_name ) {
			$file_path = self::get_file_path( $file_name );
			if ( file_exists( $file_path ) ) {
				@unlink( $file_path );
			}

			InstaWP_Setting::delete_option( 'instawp_file_manager_name' );

			$config_file = InstaWP_Tools::get_config_file();
			$constants   = [ 'INSTAWP_FILE_MANAGER_USERNAME', 'INSTAWP_FILE_MANAGER_PASSWORD', 'INSTAWP_FILE_MANAGER_SELF_URL', 'INSTAWP_FILE_MANAGER_SESSION_ID' ];

			if ( ! class_exists( 'InstaWP_WP_Config' ) ) {
				require_once INSTAWP_PLUGIN_DIR . '/includes/class-instawp-wp-config.php';
			}
	
			$config = new InstaWP_WP_Config( $config_file );
			foreach ( $constants as $constant ) {
				$config->remove( 'constant', $constant );
			}

			flush_rewrite_rules();
		}

		public function auto_login() {
			$file_name = InstaWP_Setting::get_option( 'instawp_file_manager_name', '' );
			if ( ! $file_name ) {
				wp_die( esc_html__( 'File Manager file not found!', 'instawp-connect' ) );
			}

			$token = get_transient( 'instawp_file_manager_login_token' );
			if ( empty( $_GET['token'] ) || ! $token ) {
				wp_die( esc_html__( 'Auto Login token expired or missing!', 'instawp-connect' ) );
			}

			if ( ! hash_equals( sanitize_text_field( wp_unslash( $_GET['token'] ) ), hash( 'sha256', $token ) ) ) {
				wp_die( esc_html__( 'InstaWP File Manager: Token mismatch or not valid!', 'instawp-connect' ) );
			}

			@ini_set( 'session.gc_maxlifetime', 3600 ); // 1 hour
			session_cache_limiter( 'nocache' ); // Prevent logout issue after page was cached
			session_name( INSTAWP_FILE_MANAGER_SESSION_ID );
			function session_error_handling_function( $code, $msg, $file, $line ) {
				// Permission denied for default session, try to create a new one
				if ( $code == 2 ) {
					session_abort();
					session_id( session_create_id() );
					@session_start();
				}
			}
			set_error_handler( 'session_error_handling_function' );
			session_start();
			restore_error_handler();

			if ( empty( $_SESSION['token'] ) ) {
				$_SESSION['token'] = InstaWP_Tools::get_random_string( 64 );
			}
			
			$file_manager_url = self::get_file_manager_url( $file_name ); ?>

			<!DOCTYPE html>
				<html lang="en">
				<head>
					<meta charset="UTF-8">
					<meta name="viewport" content="width=device-width, initial-scale=1.0">
					<meta http-equiv="X-UA-Compatible" content="ie=edge">
					<link href="https://cdn.jsdelivr.net/npm/reset-css@5.0.1/reset.min.css" rel="stylesheet">
					<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
					<title>Launch InstaWP File Manager - InstaWP</title>
					<style>
						body {
							background-color: #f3f4f6;
							width: calc(100vw + 0px);
							overflow-x: hidden;
							font-family: Inter, ui-sans-serif, system-ui, -apple-system,BlinkMacSystemFont, Segoe UI, Roboto, Helvetica Neue, Arial, Noto Sans, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", Segoe UI Symbol, "Noto Color Emoji";
						}
						.instawp-fm-auto-login-container {
							display: flex;
							flex-direction: column;
							align-items: center;
    						justify-content: center;
							min-height: 100vh;
						}
						.instawp-logo svg {
							width: 100%;
						}
						.instawp-details {
							padding: 5rem;
							border-radius: 0.5rem;
							max-width: 42rem;
							box-shadow: 0 0 #0000, 0 0 #0000, 0 4px 6px -1px rgb(0 0 0 / .1), 0 2px 4px -2px rgb(0 0 0 / .1);
							background-color: rgb(255 255 255 / 1);
							margin-top: 1.5rem;
							display: flex;
							flex-direction: column;
							align-items: center;
    						justify-content: center;
							gap: 2.75rem;
						}
						.instawp-details-fm {
							font-weight: 600;
							text-align: center;
							line-height: 1.75;
						}
						.instawp-details-info {
							text-align: center;
							font-size: 1.125rem;
    						line-height: 1.75rem;
							font-size: 1rem;
						}
						.instawp-details-info svg {
							height: 1.5rem;
							width: 1.5rem;
							display: inline;
							vertical-align: middle;
							animation: spin 1s linear infinite;
						}
						@keyframes spin {
							100% {
								transform: rotate(360deg);
							}
						}
					</style>
				</head>
				<body>
					<div class="instawp-fm-auto-login-container">
						<div class="instawp-logo">
							<svg width="146" height="34" viewBox="0 0 146 34" class="w-full" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.29689 12.6626V33.139H0.599365V12.6626H6.29689Z" fill="#0D4943"></path><path d="M20.2851 16.7695C22.1554 16.7695 23.6207 17.3962 24.6812 18.6494C25.7416 19.8834 26.2718 21.5705 26.2718 23.7107V33.1391H20.6322V24.4337C20.6322 23.4889 20.3815 22.7563 19.8802 22.2357C19.3982 21.6958 18.7426 21.4259 17.9136 21.4259C17.0652 21.4259 16.3904 21.7055 15.8891 22.2646C15.3877 22.8045 15.1371 23.5564 15.1371 24.5205V33.1391H9.43958V16.9141H15.1371V19.6328C15.5998 18.7651 16.2747 18.071 17.1616 17.5504C18.0485 17.0298 19.0897 16.7695 20.2851 16.7695Z" fill="#0D4943"></path><path d="M35.4551 16.7119C37.5953 16.7119 39.3017 17.2325 40.5742 18.2737C41.8468 19.3148 42.6469 20.6934 42.9747 22.4094H37.6532C37.5182 21.831 37.2386 21.3972 36.8144 21.108C36.4095 20.8188 35.9082 20.6741 35.3105 20.6741C34.8863 20.6741 34.5586 20.7706 34.3272 20.9634C34.1151 21.1369 34.0091 21.3875 34.0091 21.7153C34.0091 22.1202 34.2404 22.4287 34.7032 22.6408C35.1852 22.8336 35.9661 23.0553 37.0458 23.306C38.2991 23.5759 39.3306 23.8651 40.1404 24.1736C40.9695 24.4821 41.6829 24.9834 42.2806 25.6776C42.8976 26.3717 43.2061 27.3068 43.2061 28.4829C43.2061 29.4277 42.9458 30.2664 42.4252 30.9991C41.9046 31.7318 41.143 32.3102 40.1404 32.7344C39.1571 33.1393 37.9906 33.3417 36.6409 33.3417C34.3658 33.3417 32.5533 32.8501 31.2037 31.8667C29.854 30.8834 29.0346 29.4759 28.7454 27.6442H34.2115C34.2886 28.2419 34.5393 28.6854 34.9635 28.9746C35.4069 29.2638 35.9564 29.4084 36.612 29.4084C37.0555 29.4084 37.3929 29.312 37.6242 29.1192C37.8556 28.9264 37.9713 28.6757 37.9713 28.3673C37.9713 27.9045 37.7206 27.5671 37.2193 27.355C36.7373 27.1429 35.9564 26.9308 34.8767 26.7187C33.6427 26.4681 32.6305 26.1981 31.84 25.9089C31.0494 25.6004 30.3553 25.1184 29.7576 24.4629C29.1792 23.788 28.89 22.8625 28.89 21.6864C28.89 20.221 29.4588 19.0256 30.5963 18.1001C31.7339 17.1747 33.3535 16.7119 35.4551 16.7119Z" fill="#0D4943"></path><path d="M55.364 28.2804V33.1392H53.0214C51.0354 33.1392 49.4833 32.6475 48.365 31.6642C47.2467 30.6809 46.6876 29.0516 46.6876 26.7765V21.6574H44.6052V16.9143H46.6876V12.981H52.3851V16.9143H55.3062V21.6574H52.3851V26.8922C52.3851 27.3935 52.4911 27.7502 52.7032 27.9623C52.9346 28.1743 53.3106 28.2804 53.8312 28.2804H55.364Z" fill="#0D4943"></path><path d="M63.9776 16.7119C65.1152 16.7119 66.0985 16.9626 66.9276 17.4639C67.776 17.9459 68.4026 18.6207 68.8075 19.4884V16.9144H74.4761V33.1393H68.8075V30.5653C68.4026 31.4329 67.776 32.1174 66.9276 32.6187C66.0985 33.1007 65.1152 33.3417 63.9776 33.3417C62.6858 33.3417 61.5193 33.014 60.4782 32.3584C59.437 31.6836 58.6176 30.7195 58.0199 29.4663C57.4221 28.1937 57.1233 26.7091 57.1233 25.0124C57.1233 23.3156 57.4221 21.8406 58.0199 20.5874C58.6176 19.3341 59.437 18.3797 60.4782 17.7242C61.5193 17.0493 62.6858 16.7119 63.9776 16.7119ZM65.8575 21.6864C64.9706 21.6864 64.2475 21.9853 63.6884 22.583C63.1485 23.1614 62.8786 23.9712 62.8786 25.0124C62.8786 26.0728 63.1485 26.9019 63.6884 27.4996C64.2475 28.078 64.9706 28.3673 65.8575 28.3673C66.7059 28.3673 67.4096 28.0684 67.9688 27.4707C68.5279 26.873 68.8075 26.0535 68.8075 25.0124C68.8075 23.9905 68.5279 23.1807 67.9688 22.583C67.4096 21.9853 66.7059 21.6864 65.8575 21.6864Z" fill="#0D4943"></path><path d="M123.188 26.2846V33.139H117.49V12.6626H125.993C128.461 12.6626 130.341 13.2892 131.633 14.5425C132.944 15.7765 133.599 17.4346 133.599 19.517C133.599 20.8088 133.31 21.9657 132.732 22.9875C132.153 24.0094 131.286 24.8192 130.129 25.4169C128.991 25.9954 127.613 26.2846 125.993 26.2846H123.188ZM125.357 21.8018C127.015 21.8018 127.844 21.0402 127.844 19.517C127.844 18.0131 127.015 17.2611 125.357 17.2611H123.188V21.8018H125.357Z" fill="#0D4943"></path><path d="M109.738 12.6667H115.551C115.649 12.6664 115.746 12.692 115.831 12.7409C115.917 12.7899 115.989 12.8605 116.038 12.9456C116.088 13.0307 116.114 13.1274 116.115 13.226C116.115 13.3246 116.09 13.4216 116.042 13.5073L105.582 32.0658C105.423 32.3485 105.191 32.5838 104.911 32.7477C104.631 32.9115 104.313 32.998 103.988 32.9983H99.5384C99.1678 32.9983 98.8057 32.8855 98.5008 32.6749C98.1953 32.4643 97.9616 32.1659 97.8302 31.8192L96.7408 28.9567L96.8174 29.0083C97.1656 29.2337 97.5561 29.3851 97.9652 29.4532C98.3749 29.5213 98.7937 29.5046 99.1962 29.4041C99.5986 29.3036 99.9758 29.1215 100.305 28.8691C100.634 28.6167 100.909 28.2994 101.11 27.9367L107.386 16.6971H102.379C102.271 16.6993 102.166 16.6707 102.074 16.6149C101.983 16.559 101.909 16.4781 101.862 16.3818C101.815 16.2854 101.796 16.1776 101.808 16.071C101.82 15.9645 101.863 15.8636 101.93 15.7803L114.515 0.129175C114.579 0.060403 114.664 0.0159868 114.757 0.00355936C114.85 -0.00886809 114.944 0.0114701 115.024 0.0610772C115.104 0.110684 115.164 0.186462 115.193 0.275389C115.223 0.364315 115.221 0.460836 115.187 0.548362L109.738 12.6667Z" fill="url(#paint0_linear_6_2)"></path><path d="M89.3008 31.8751C89.3008 32.0223 89.2719 32.168 89.2153 32.304C89.1592 32.44 89.0767 32.5635 88.9725 32.6677C88.8688 32.7717 88.7447 32.8543 88.6091 32.9106C88.473 32.9669 88.3271 32.9959 88.1801 32.9959H83.4075C83.1761 32.9959 82.9508 32.9245 82.7616 32.7912C82.5724 32.658 82.4296 32.4695 82.3519 32.2517L81.7849 30.64L75.9632 14.1796C75.9024 14.0085 75.8837 13.8253 75.9084 13.6455C75.9337 13.4656 76.0012 13.2944 76.106 13.1462C76.2109 12.9979 76.3495 12.877 76.5109 12.7935C76.6718 12.71 76.8508 12.6664 77.0321 12.6665H81.3926C81.6782 12.6663 81.9572 12.7543 82.1916 12.9183C82.426 13.0824 82.6037 13.3145 82.7013 13.5833L88.9218 30.6198L89.2406 31.4895C89.2828 31.6135 89.3032 31.744 89.3008 31.8751Z" fill="#056960"></path><path d="M94.1404 22.1422L90.5541 32.2789C90.4788 32.4905 90.3396 32.6736 90.1564 32.8032C89.9733 32.9328 89.7539 33.0026 89.5298 33.003H83.4075C83.1755 33.0025 82.9496 32.93 82.7604 32.7955C82.5712 32.661 82.4284 32.4711 82.3519 32.252L81.7825 30.6403C81.8945 30.8241 82.8291 32.2587 83.9925 30.6201L89.9733 14.2382C89.9733 14.2382 90.6909 13.8033 91.1301 14.2382L94.1404 22.1422Z" fill="#0D4943"></path><path d="M102.228 25.9437L99.5227 30.7789C99.4118 30.9767 99.2473 31.1389 99.0479 31.2462C98.8478 31.3536 98.6219 31.4017 98.3959 31.3851C98.17 31.3683 97.9531 31.2874 97.7717 31.1519C97.5904 31.0163 97.4512 30.8317 97.3704 30.6198L94.1403 22.1419L91.2397 14.5203C91.177 14.3603 91.0601 14.2276 90.9089 14.1455C90.7583 14.0635 90.5829 14.0374 90.4148 14.072C90.2576 14.1049 90.1057 14.1623 89.9666 14.2424C90.1148 13.8276 90.3775 13.4632 90.7239 13.191C91.1469 12.852 91.6729 12.6671 92.2146 12.6665H96.2142C96.6293 12.6656 97.0342 12.7927 97.3747 13.0306C97.7145 13.2685 97.973 13.6056 98.1146 13.9958L102.268 25.3699C102.304 25.4627 102.318 25.5622 102.312 25.6612C102.305 25.7603 102.276 25.8567 102.228 25.9437Z" fill="#056960"></path><defs><linearGradient id="paint0_linear_6_2" x1="105.599" y1="28" x2="114.099" y2="-3.29879e-07" gradientUnits="userSpaceOnUse"><stop stop-color="#FE8551"></stop><stop offset="1" stop-color="#ED618E"></stop></linearGradient></defs></svg>
						</div>
						<div class="instawp-details">
							<h3 class="instawp-details-fm"><?php echo esc_url( $file_manager_url ); ?></h3>
							<p class="instawp-details-info">
								<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 animate-spin inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> You are being redirected to the InstaWP File Manager.
							</p>
						</div>
					</div>
					<form id="instawp-fm-auto-login" action="<?php echo esc_url( $file_manager_url ); ?>" method="POST">
						<input type="hidden" name="fm_usr" required="required" autofocus="" value="<?php echo esc_attr( INSTAWP_FILE_MANAGER_USERNAME ); ?>">
						<input type="hidden" name="fm_pwd" required="required" autofocus="" value="<?php echo esc_attr( INSTAWP_FILE_MANAGER_PASSWORD ); ?>">
						<input type="hidden" name="token" required="required" autofocus="" value="<?php echo esc_attr( $_SESSION['token'] ); ?>">
					</form>
					<script type="text/javascript">
						window.onload= function() {
							setTimeout( function() {
								document.getElementById( 'instawp-fm-auto-login' ).submit();
							}, 2000 );
						}
					</script>
				</body>
			</html>
			<?php
		}

		public function query_vars( $query_vars ) {
			$query_vars[] = 'instawp-file-manager';
			return $query_vars;
		}
		
		public function load_template( $template ) {
			return $this->get_template( $template );
		}

		private function get_template( $template = false ) {
			$template_name = get_query_var( 'instawp-file-manager', false );
			if ( $template_name ) {
				$template_path = self::get_file_path( $template_name );
				if ( file_exists( $template_path ) ) {
					$template = $template_path;
				}
			}
			return $template;
		}

		public static function get_file_path( $file_name ) {
			$file_path = trailingslashit( INSTAWP_PLUGIN_DIR ) . 'includes/file-manager/' . $file_name . '.php';

			return $file_path;
		}

		public static function get_file_manager_url( $file_name ) {
			$permalink_structure = get_option( 'permalink_structure' );
			if ( ! empty( $permalink_structure ) ) {
				$file_manager_url = trailingslashit( home_url() ) . 'instawp-file-manager/' . $file_name;
			} else {
				$file_manager_url = untrailingslashit( home_url() ) . '?instawp-file-manager=' . $file_name;
			}

			return $file_manager_url;
		}
	}
}

InstaWP_File_Management::instance();
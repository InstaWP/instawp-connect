<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Plugin_Theme {

    public function __construct() {
	    // Plugin and Theme actions
	    add_action( 'upgrader_process_complete', [ $this,'install_update_action' ], 10, 2 );
	    add_action( 'activated_plugin', [ $this,'activate_plugin' ], 10, 2 );
	    add_action( 'deactivated_plugin', [ $this,'deactivate_plugin' ] ,10, 2 );
	    add_action( 'deleted_plugin', [ $this,'delete_plugin' ] ,10, 2 );
	    add_action( 'switch_theme', [ $this,'switch_theme' ], 10, 3 );
	    add_action( 'deleted_theme', [ $this,'delete_theme' ], 10, 2 );
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

		if ( ! in_array( $hook_extra['action'], [ 'install', 'update' ] ) ) {
			return;
		}

		$event_slug = $hook_extra['type'] . '_' . $hook_extra['action'];
		$event_name = sprintf( esc_html__('%s %s%s', 'instawp-connect'), ucfirst( $hook_extra['type'] ), $hook_extra['action'], $hook_extra['action'] == 'update'? 'd' : 'ed' );

		// hooks for theme and record the event
		if ( $upgrader instanceof Theme_Upgrader && $hook_extra['type'] === 'theme' ) {
			$destination_name = $upgrader->result['destination_name'];
			$theme            = wp_get_theme( $destination_name );

			if ( $theme->exists() ) {
				$details = [
					'name'       => $theme->display( 'Name' ),
					'stylesheet' => $theme->get_stylesheet(),
					'data'       => $upgrader->new_theme_data ?? [],
				];
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, 'theme' );
			}
		}

		// hooks for plugins and record the plugin.
		if ( $upgrader instanceof Plugin_Upgrader && $hook_extra['type'] === 'plugin' ) {
			if ( $hook_extra['action'] === 'install' && ! empty( $upgrader->new_plugin_data ) ) {
				$plugin_data = $upgrader->new_plugin_data;
			} else if ( $hook_extra['action'] === 'update' && ! empty( $upgrader->skin->plugin_info ) ) {
				$plugin_data = $upgrader->skin->plugin_info;
			}

			if ( ! empty( $plugin_data ) ) {
				$post_slug = ! empty( $_POST['slug'] ) ? sanitize_text_field( $_POST['slug'] ) : null;
				$slug      = empty( $plugin_data['TextDomain'] ) ? ( $post_slug ?? $plugin_data['TextDomain'] ) : $plugin_data['TextDomain'];
				$details   = [
					'name' => $plugin_data['Name'],
					'slug' => $slug,
					'data' => $plugin_data
				];
				$this->parse_plugin_theme_event( $event_name, $event_slug, $details, 'plugin' );
			}
		}
	}

	/**
	 * Function for `deactivated_plugin` action-hook.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 * @param bool   $network_deactivating Whether the plugin is deactivated for all sites in the network or just the current site. Multisite only.
	 *
	 * @return void
	 */
	public function deactivate_plugin( $plugin, $network_wide ) {
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
		$details    = [
			'name'       => $new_name,
			'stylesheet' => $new_theme->get_stylesheet(),
			'Paged'      => ''
		];
		$event_name = sprintf( __('Theme switched from %s to %s', 'instawp-connect' ), $old_theme->get_stylesheet(), $new_theme->get_stylesheet() );
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
		$details = [
			'name'       => ucfirst( $stylesheet ),
			'stylesheet' => $stylesheet,
			'Paged'      => ''
		];
		if ( $deleted ) {
			$this->parse_plugin_theme_event( __( 'Theme deleted', 'instawp-connect' ), 'deleted_theme', $details, 'theme' );
		}
	}

	/**
	 * Function parse_plugin_theme_event
	 * @param $event_name
	 * @param $event_slug
	 * @param $details
	 * @param $type
	 * @param $source_id
	 * @return void
	 */
	private function parse_plugin_theme_event( $event_name, $event_slug, $details, $type, $source_id = '', $event_id = null ) {
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
					$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $details );
					if ( $plugin_data['Name'] != '' ) {
						$title     = $plugin_data['Name'];
					} else if ( $plugin_data['TextDomain'] != '' ) {
						$title = $plugin_data['TextDomain'];
					} else {
						$title = $details;
					}
				}

//				if ( $event_slug === 'deleted_plugin' ) {
//					$statement = $this->wpdb->prepare( "SELECT * FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE source_id=%s AND status=%s", $source_id, 'pending' );
//					$events    = $this->wpdb->get_results( $statement );
//
//					if ( ! empty( $events ) ) {
//						foreach ( $events as $event ) {
//							$this->wpdb->query( $this->wpdb->prepare( "DELETE FROM " . INSTAWP_DB_TABLE_EVENTS . " WHERE id=%d", $event->id ) );
//						}
//						return;
//					}
//				}
				break;
			case 'woocommerce_attribute':
			case 'woocommerce_attribute_updated':
				$title = $details['attribute_label'];
				break;
			case 'woocommerce_attribute_deleted':
				$title = 'WooCommerce Attribute Deleted (' . $details . ')';
				break;
			default:
				$title = $details['name'];
		}
		InstaWP_Sync_DB::insert_update_event( $event_name, $event_slug, $type, $source_id, $title, $details, $event_id );
	}
}

new InstaWP_Sync_Plugin_Theme();
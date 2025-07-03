<?php

use InstaWP\Connect\Helpers\Activator;
use InstaWP\Connect\Helpers\Deactivator;
use InstaWP\Connect\Helpers\Helper;
use InstaWP\Connect\Helpers\Installer;
use InstaWP\Connect\Helpers\Uninstaller;
use InstaWP\Connect\Helpers\Updater;

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Plugin_Theme {

    public function __construct() {
	    // Plugin and Theme actions
	    add_action( 'upgrader_process_complete', array( $this, 'install_update_action' ), 10, 2 );
	    add_action( 'activated_plugin', array( $this, 'activate_plugin' ), 10, 2 );
	    add_action( 'deactivated_plugin', array( $this, 'deactivate_plugin' ), 10, 2 );
	    add_action( 'deleted_plugin', array( $this, 'delete_plugin' ), 10, 2 );
	    add_action( 'switch_theme', array( $this, 'switch_theme' ), 10, 3 );
	    add_action( 'deleted_theme', array( $this, 'delete_theme' ), 10, 2 );

	    // Process event
	    add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
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

				if ( Helper::is_on_wordpress_org( $slug[0], $hook_extra['type'] ) ) {
					$this->parse_plugin_theme_event( $event_name, $event_slug, $details, $hook_extra['type'] );
				}
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

				if ( Helper::is_on_wordpress_org( $upgrader->result['destination_name'], $hook_extra['type'] ) ) {
					$this->parse_plugin_theme_event( $event_name, $event_slug, $details, $hook_extra['type'] );
				}
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
				$response = $this->install_item( $v->details->slug, 'plugin' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
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
					$response = $this->update_item( $v->details->path, 'plugin' );
					
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
				$response = $this->install_item( $v->details->stylesheet, 'theme' )[0];

				if ( ! $response['success'] ) {
					$logs[ $v->id ] = $response['message'];

					return InstaWP_Sync_Helpers::sync_response( $v, $logs, array(
						'status'  => 'error',
						'message' => $logs[ $v->id ],
					) );
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
		// Exclude uploaded plugin or theme slug
		$exclude_slugs = get_set_sync_config_data( 'exclude_upload_plugin_theme_slugs' );
		if ( ! empty( $exclude_slugs ) && is_array( $exclude_slugs ) && is_string( $source_id ) && in_array( $source_id, $exclude_slugs ) ) {
			return;
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
	public function update_item( $item, $type ) {
		$updater = new Updater( array(
			array(
				'slug' => $item,
				'type' => $type,
			),
		) );

		return $updater->update();
	}

	/**
	 * Plugin or Theme install
	 */
	public function install_item( $item, $type, $activate = false ) {
		$installer = new Installer( array(
			array(
				'slug'     => $item,
				'source'   => 'wp.org',
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
}

new InstaWP_Sync_Plugin_Theme();
<?php
namespace InstaWP\Connect\Helpers;

class Installer {

    public $defaults = [
        'slug'     => '',
        'source'   => 'wp.org',
        'type'     => 'plugin',
        'activate' => false
    ];
    
    public $args;
    public $slug;
    public $source;
    public $type;
    public $activate;
    public $url;

    public function __construct( array $args = [] ) {
        $this->args = $args;
    }

    public function start() {
		if ( count( $this->args ) < 1 || count( $this->args ) > 5 ) {
			return [
				'success' => false,
				'message' => esc_html( 'Minimum 1 and Maximum 5 installations are allowed!' ),
            ];
		}

        $results = [];

        foreach( $this->args as $index => $args ) {
            $args           = wp_parse_args( $args, $this->defaults );
            $this->slug     = $args['slug'];
            $this->source   = $args['source'];
            $this->type     = $args['type'];
            $this->activate = filter_var( $args['activate'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
            $this->url      = ( 'url' === $this->source ) ? $this->slug : '';

            $results[ $index ] = $this->install();
        }

        return $results;
    }

	private function install() {
        $data = [];

        try {
            if ( ! class_exists( 'WP_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }

            if ( ! class_exists( 'Plugin_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            }

            if ( ! class_exists( 'Theme_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
            }

            if ( ! class_exists( 'Automatic_Upgrader_Skin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
            }

            if ( ! function_exists( 'request_filesystem_credentials' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $skin = new \Automatic_Upgrader_Skin();

            if ( 'plugin' === $this->type ) {
                $upgrader = new \Plugin_Upgrader( $skin );

                if ( 'wp.org' === $this->source ) {
                    if ( ! function_exists( 'plugins_api' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                    }

                    $api = \plugins_api( 'plugin_information', [
                        'slug'   => $this->slug,
                        'fields' => [
                            'short_description' => false,
                            'screenshots'       => false,
                            'sections'          => false,
                            'contributors'      => false,
                            'versions'          => false,
                            'banners'           => false,
                            'requires'          => true,
                            'rating'            => false,
                            'ratings'           => false,
                            'downloaded'        => false,
                            'last_updated'      => false,
                            'added'             => false,
                            'tags'              => false,
                            'compatibility'     => false,
                            'homepage'          => false,
                            'donate_link'       => false,
                            'downloadlink'      => true,
                        ],
                    ] );

                    if ( is_wp_error( $api ) ) {
                        $error_message = $api->get_error_message();
                    } else if ( isset( $api->requires ) && ! is_wp_version_compatible( $api->requires ) ) {
                        $error_message = sprintf( esc_html( 'Minimum required WordPress Version of this plugin is %s!' ), $api->requires );
                    }
                    
                    if ( empty( $error_message ) && ! empty( $api->download_link ) ) {
                        $this->url = $api->download_link;
                    }
                }
            } elseif ( 'theme' === $this->type ) {
                $upgrader = new \Theme_Upgrader( $skin );

                if ( 'wp.org' === $this->source ) {
                    if ( ! function_exists( 'themes_api' ) ) {
                        require_once ABSPATH . 'wp-admin/includes/theme.php';
                    }

                    $api = \themes_api( 'theme_information', [
                        'slug'   => $this->slug,
                        'fields' => [
                            'screenshot_count' => 0,
                            'contributors'     => false,
                            'sections'         => false,
                            'tags'             => false,
                            'downloadlink'     => true,
                        ],
                    ] );
                    if ( is_wp_error( $api ) ) {
                        $error_message = $api->get_error_message();
                    } else if ( ! empty( $api->download_link ) ) {
                        $this->url = $api->download_link;
                    }
                }
            }

            if ( empty( $error_message ) && $this->is_link_valid() ) {
                $result = $upgrader->install( $this->url, [
                    'overwrite_package' => true,
                ] );

                if ( ! $result || is_wp_error( $result ) ) {
                    $error_message = is_wp_error( $result ) ? $result->get_error_message() : sprintf( esc_html( 'Installation failed! Please check minimum supported WordPress version of the %s' ), $this->type );
                } else {
                    if ( ! function_exists( 'wp_clean_update_cache' ) || ! function_exists( 'wp_update_themes' ) || ! function_exists( 'wp_update_plugins' ) ) {
                        require_once ABSPATH . 'wp-includes/update.php';
                    }

                    wp_clean_update_cache();
                    wp_update_themes();
                    wp_update_plugins();

                    if ( 'plugin' === $this->type ) {
                        $plugin_file = $upgrader->plugin_info();

                        if ( $plugin_file ) {
                            if ( true === $this->activate ) {
                                if ( ! function_exists( 'activate_plugin' ) ) {
                                    require_once ABSPATH . 'wp-admin/includes/plugin.php';
                                }

                                activate_plugin( $plugin_file );
                            }

                            if ( ! function_exists( 'get_plugins' ) ) {
                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                            }

                            $active_plugins     = ( array ) get_option( 'active_plugins', [] );
                            $auto_updates       = ( array ) get_site_option( 'auto_update_plugins', [] );
                            $plugin_update_data = get_site_transient( 'update_plugins' );
                            $plugin_update_data = isset( $plugin_update_data->response ) ? $plugin_update_data->response : [];
                            $plugins_data       = get_plugins();
                            $plugin_data        = $plugins_data[ $plugin_file ];
                            $slug               = explode( '/', $plugin_file );
                            $data = [
                                'slug'             => $slug[0],
                                'name'             => $plugin_file,
                                'version'          => $plugin_data['Version'],
                                'activated'        => in_array( $plugin_file, $active_plugins, true ),
                                'update_available' => array_key_exists( $plugin_file, $plugin_update_data ),
                                'update_version'   => array_key_exists( $plugin_file, $plugin_update_data ) ? $plugin_update_data[ $plugin_file ]->new_version : '',
                                'update_enabled'   => in_array( $plugin_file, $auto_updates, true ),
                                'icon_url'         => 'https://ps.w.org/' . $slug[0] . '/assets/icon-128x128.png',
                                'data'             => $plugin_data,
                            ];
                        }
                    } elseif ( 'theme' === $this->type ) {
                        $theme_info = $upgrader->theme_info();

                        if ( $theme_info ) {
                            if ( true === $this->activate ) {
                                if ( ! function_exists( 'switch_theme' ) ) {
                                    require_once ABSPATH . 'wp-includes/theme.php';
                                }

                                switch_theme( $theme_info->get_stylesheet() );
                            }

                            if ( ! function_exists( 'wp_get_themes' ) || ! function_exists( 'wp_get_theme' ) ) {
                                require_once ABSPATH . 'wp-includes/theme.php';
                            }

                            $current_theme     = wp_get_theme();
                            $auto_updates      = ( array ) get_site_option( 'auto_update_themes', [] );
                            $theme_update_data = get_site_transient( 'update_themes' );
                            $theme_update_data = isset( $theme_update_data->response ) ? $theme_update_data->response : [];
                            $theme_object      = wp_get_theme( $theme_info->get_stylesheet() );
                            $stylesheet        = $theme_object->get_stylesheet();

                            $data = [
                                'slug'             => $stylesheet,
                                'version'          => $theme_object->get( 'Version' ),
                                'parent'           => $stylesheet !== $current_theme->get_template() ? $theme_object->get_template() : '',
                                'activated'        => $stylesheet === $current_theme->get_stylesheet(),
                                'update_available' => array_key_exists( $stylesheet, $theme_update_data ),
                                'update_version'   => array_key_exists( $stylesheet, $theme_update_data ) ? $theme_update_data[ $stylesheet ]['new_version'] : '',
                                'update_enabled'   => in_array( $stylesheet, $auto_updates, true ),
                            ];
                        }
                    }
                }
            } else {
                $error_message = esc_html( 'Provided URL is not valid!' );
            }
        } catch( \Exception $e ) {
            $error_message = $e->getMessage();
        }

        $message = isset( $error_message ) ? trim( $error_message ) : '';

        return [
            'success' => empty( $message ),
            'message' => empty( $message ) ? esc_html( 'Success!' ) : $message,
            'data'    => $data,
        ];
    }

    /**
	 * Verify the plugin or theme download url.
     * 
	 * @return bool
	 */
	private function is_link_valid() {
		$is_valid = false;
		if ( $this->url && filter_var( $this->url, FILTER_VALIDATE_URL ) ) {
			$response = wp_remote_get( $this->url, [
				'timeout' => 60,
			] );
			$is_valid = 200 === wp_remote_retrieve_response_code( $response );
		}

		return $is_valid;
	}
}
<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

class Installer {

    public array $defaults = [
        'slug'     => '',
        'source'   => 'wp.org',
        'type'     => 'plugin',
        'activate' => false
    ];
    
    public array $args;
    public string $slug;
    public string $source;
    public string $type;
    public bool $activate;
    public string $url;
    public string $error;

    public function __construct( array $args = [] ) {
        $this->args = $args;
    }

    public function start(): array {
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
            $this->error    = '';

            $response = $this->install();

            $results[ $index ] = array_merge( [
				'slug' => $this->slug,
			], $response );
        }

        return $results;
    }

	private function install(): array {
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

            if ( ! class_exists( 'WP_Ajax_Upgrader_Skin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';
            }

            if ( 'plugin' === $this->type ) {
                $upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );

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
                        $this->error = $api->get_error_message();
                    } else if ( isset( $api->requires ) && ! is_wp_version_compatible( $api->requires ) ) {
                        $this->error = sprintf( esc_html( 'Minimum required WordPress Version of this plugin is %s!' ), $api->requires );
                    }
                    
                    if ( empty( $this->error ) && ! empty( $api->download_link ) ) {
                        $this->url = $api->download_link;
                    }
                }
            } elseif ( 'theme' === $this->type ) {
                $upgrader = new \Theme_Upgrader( new \WP_Ajax_Upgrader_Skin() );

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
                        $this->error = $api->get_error_message();
                    } else if ( ! empty( $api->download_link ) ) {
                        $this->url = $api->download_link;
                    }
                }
            }

            if ( empty( $this->error ) && $this->is_link_valid() ) {
                $result = $upgrader->install( $this->url, [
                    'overwrite_package' => true,
                ] );

                if ( ! $result || is_wp_error( $result ) ) {
                    $this->error = is_wp_error( $result ) ? $result->get_error_message() : sprintf( esc_html( 'Installation failed! Please check minimum supported WordPress version of the %s' ), $this->type );
                } else {
                    if ( true === $this->activate ) {
                        if ( 'plugin' === $this->type ) {
                            if ( ! function_exists( 'activate_plugin' ) ) {
                                require_once ABSPATH . 'wp-admin/includes/plugin.php';
                            }

                            activate_plugin( $upgrader->plugin_info(), '', false, true );
                        } elseif ( 'theme' === $this->type ) {
                            if ( ! function_exists( 'switch_theme' ) ) {
                                require_once ABSPATH . 'wp-includes/theme.php';
                            }

                            switch_theme( $upgrader->theme_info()->get_stylesheet() );
                        }
                    }
                }
            } else {
                $this->error = esc_html( 'Provided URL is not valid!' );
            }
        } catch( \Exception $e ) {
            $this->error = $e->getMessage();
        }

        $message = trim( $this->error );

        return [
            'message' => empty( $message ) ? esc_html( 'Success!' ) : $message,
            'status'  => empty( $message ),
        ];
    }

    /**
	 * Verify the plugin or theme download url.
     * 
	 * @return bool
	 */
	private function is_link_valid(): bool {
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
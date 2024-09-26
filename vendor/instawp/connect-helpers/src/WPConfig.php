<?php
namespace InstaWP\Connect\Helpers;

class WPConfig extends \WPConfigTransformer {

    protected $config_data;
    protected $is_cli;
    protected $blacklisted = [
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
        'DB_HOST',
        'DB_CHARSET',
        'DB_COLLATE',
        'AUTH_KEY',
        'SECURE_AUTH_KEY',
        'LOGGED_IN_KEY',
        'NONCE_KEY',
        'AUTH_SALT',
        'SECURE_AUTH_SALT',
        'LOGGED_IN_SALT',
        'NONCE_SALT',
        'ABSPATH',
        'WP_HOME',
        'WP_SITEURL',
        'WP_CACHE_KEY_SALT',
        'COOKIE_DOMAIN',
        'DOMAIN_CURRENT_SITE',
    ];

    public function __construct( array $constants = [], $is_cli = false, $read_only = false ) {
        $file = ABSPATH . 'wp-config.php';
        if ( ! file_exists( $file ) ) {
            if ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) ) {
                $file = dirname( ABSPATH ) . '/wp-config.php';
            }
        }

        parent::__construct( $file, $read_only );

        $this->config_data = $constants;
        $this->is_cli      = $is_cli;
    }

    public function get() {
        $wp_config_src = file_get_contents( $this->wp_config_path );

        if ( ! trim( $wp_config_src ) ) {
            throw new \Exception( 'Config file is empty.' );
        }

        $this->wp_config_src = $wp_config_src;
        $this->wp_configs    = $this->parse_wp_config( $this->wp_config_src );

        if ( ! isset( $this->wp_configs['constant'] ) ) {
            throw new \Exception( "Config type constant does not exist." );
        }

        $results = [
            'wp-config' => [],
        ];

        foreach ( $this->wp_configs['constant'] as $constant => $data ) {
            if ( ! $this->is_cli && ( preg_match( '/[a-z]/', $constant ) || in_array( $constant, $this->blacklisted, true ) ) ) {
                continue;
            }

            if ( ! empty( $this->config_data ) && ! in_array( $constant, $this->config_data, true ) ) {
                continue;
            }

            $value = trim( $data['value'], "'" );
            if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) !== null ) {
                $value = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            } elseif ( filter_var( $value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) !== null ) {
                $value = intval( $value );
            }

            $results['wp-config'][ $constant ] = $value;
        }

        return $results;
    }

    public function set() {
        $args    = [
            'normalize' => true,
            'add'       => true,
        ];
        $content = file_get_contents( $this->wp_config_path );

        if ( ! trim( $content ) ) {
            throw new \Exception( 'Config file is empty.' );
        }

        if ( false === strpos( $content, "/* That's all, stop editing!" ) ) {
            preg_match( '@\$table_prefix = (.*);@', $content, $matches );
            $args['anchor']    = isset( $matches[0] ) ? $matches[0] : '';
            $args['placement'] = 'after';
        }

        foreach ( $this->config_data as $key => $value ) {
            if ( empty( $key ) ) {
                continue;
            }

            if ( ! $this->is_cli && ( preg_match( '/[a-z]/', $key ) || in_array( $key, $this->blacklisted, true ) ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                if ( ! array_key_exists( 'value', $value ) ) {
                    continue;
                }

                $params = [ 'separator', 'add' ];
                foreach ( $params as $param ) {
                    if ( array_key_exists( $param, $value ) ) {
                        $args[ $param ] = $value[ $param ];
                    }
                }
                $args['raw'] = array_key_exists( 'raw', $value ) ? $value['raw'] : true;
                $value       = $value['value'];
            } elseif ( is_bool( $value ) ) {
                $value       = $value ? 'true' : 'false';
                $args['raw'] = true;
            } elseif ( is_numeric( $value ) ) {
                $value       = strval( $value );
                $args['raw'] = true;
            } elseif ( in_array( $value, [ 'true', 'false' ] ) ) {
                $value       = strval( $value );
                $args['raw'] = true;
            } else {
                $value       = sanitize_text_field( wp_unslash( $value ) );
                $args['raw'] = false;
            }

            try {
                $this->update( 'constant', $key, $value, $args );
            } catch ( \Exception $e ) {
                throw new \Exception( $e->getMessage() );
            }
        }

        return [ 'success' => true ];
    }

    public function delete() {
        $constants = array_filter( $this->config_data );

        if ( empty( $constants ) ) {
            throw new \Exception( 'No constants provided!' );
        }

        foreach ( $constants as $constant ) {
            try {
                $this->remove( 'constant', $constant );
            } catch ( \Exception $e ) {
                throw new \Exception( $e->getMessage() );
            }
        }

        return [ 'success' => true ];
    }
}
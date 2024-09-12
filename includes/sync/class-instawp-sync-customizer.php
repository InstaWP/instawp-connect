<?php

defined( 'ABSPATH' ) || exit;

class InstaWP_Sync_Customizer {

	public $parsable_page_options = array(
		'page_on_front',
		'page_for_posts',
		'wp_page_for_privacy_policy',
		'woocommerce_terms_page_id',
	);

	public $parsable_attachment_options = array(
		'site_icon',
		'custom_logo',
	);

	public function __construct() {
		// User actions
	    add_action( 'customize_save_after', array( $this, 'track_changes' ), 999 );

		// Process event
	    add_filter( 'instawp/filters/2waysync/process_event', array( $this, 'parse_event' ), 10, 2 );
    }

	/**
	 * Function for `user_register` action-hook.
	 */
	public function track_changes( $manager ) {
		if ( ! InstaWP_Sync_Helpers::can_sync( 'customizer' ) ) {
			return;
		}

		$theme      = get_stylesheet();
		$template   = get_template();
		$charset    = get_option( 'blog_charset' );
		$mods       = get_theme_mods();

		foreach ( $mods as $key => $value ) {
			if ( in_array( $key, $this->parsable_attachment_options ) ) {
				$value = InstaWP_Sync_Parser::generate_attachment_data( $value );
			}

            if ( $this->is_image_url( $value ) ) {
                $attachment_id = InstaWP_Sync_Parser::url_to_attachment( $value );
                $value         = array_merge( InstaWP_Sync_Parser::generate_attachment_data( $attachment_id ), array( 'url_output' => true ) );
            }

			$mods[ $key ] = $value;
		}

		$data = array(
			'template' => $template,
			'mods'     => isset( $mods ) ? $mods : array(),
			'options'  => array(),
		);

		// Get options from the Customizer API.
		$settings = $manager->settings();

		foreach ( $settings as $key => $setting ) {
			if ( 'option' === $setting->type ) {
				if ( 'widget_' === substr( strtolower( $key ), 0, 7 ) ) {
					continue;
				}

				if ( 'sidebars_' === substr( strtolower( $key ), 0, 9 ) ) {
					continue;
				}

				$value = $setting->value();

				if ( in_array( $key, $this->parsable_page_options ) ) {
					$value = InstaWP_Sync_Parser::parse_post_data( $value );
				}

				$data['options'][ $key ] = $value;
			}
		}

		if ( function_exists( 'wp_get_custom_css_post' ) ) {
			$data['wp_css'] = wp_get_custom_css();
		}

		$reference_id = $template . '_customizer';

		$event_name = __('WP Customizer Changes', 'instawp-connect' );
		InstaWP_Sync_DB::insert_update_event( $event_name, 'customizer_changes', 'customizer', $reference_id, $event_name, $data );
	}

	public function parse_event( $response, $v ) {
		if ( $v->event_type !== 'customizer' ) {
			return $response;
		}
		global $wp_customize;

		$data = InstaWP_Sync_Helpers::object_to_array( $v->details );

		if ( isset( $data['options'] ) ) {
			foreach ( $data['options'] as $option_key => $option_value ) {
				$option = new \InstaWP_Sync_Customize_Setting( $wp_customize, $option_key, array(
					'default'    => '',
					'type'       => 'option',
					'capability' => 'edit_theme_options',
				) );

				if ( in_array( $option_key, $this->parsable_page_options ) ) {
					$option_value = InstaWP_Sync_Parser::parse_post_events( $option_value );
				}

				$option->import( $option_value );
			}
		}

		if ( function_exists( 'wp_update_custom_css_post' ) && isset( $data['wp_css'] ) && '' !== $data['wp_css'] ) {
			wp_update_custom_css_post( $data['wp_css'] );
		}

		foreach ( $data['mods'] as $key => $value ) {

			if ( in_array( $key, $this->parsable_attachment_options ) ) {
				$value = InstaWP_Sync_Parser::process_attachment_data( $value );
			}

			if ( is_array( $value ) && ! empty( $value['url_output'] ) ) {
				$value = wp_get_attachment_url( InstaWP_Sync_Parser::process_attachment_data( $value ) );
			}

			// Call the customize_save_ dynamic action.
			do_action( 'customize_save_' . $key, $wp_customize );

			// Save the mod.
			set_theme_mod( $key, $value );
		}

		remove_action( 'customize_save_after', array( $this, 'track_changes' ), 999 );

		// Call the customize_save_after action.
		do_action( 'customize_save_after', $wp_customize );

		add_action( 'customize_save_after', array( $this, 'track_changes' ), 999 );

		return InstaWP_Sync_Helpers::sync_response( $v );
	}

	private function is_image_url( $item = '' ) {
		if ( is_string( $item ) ) {
			if ( preg_match( '/\.(jpg|jpeg|png|gif)/i', $item ) ) {
				return true;
			}
		}
		return false;
	}
}

new InstaWP_Sync_Customizer();
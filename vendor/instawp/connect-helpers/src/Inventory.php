<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

class Inventory {

    public function fetch(): array {
        $results = [];

        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$wp_plugins         = get_plugins();
		$active_plugins     = ( array ) get_option( 'active_plugins', [] );
		$plugin_update_data = get_site_transient( 'update_plugins' )->response ?? [];
		$plugins        = [];

		foreach ( $wp_plugins as $name => $plugin ) {
			$slug      = explode( '/', $name );
			$plugins[] = [
				'slug'             => $slug[0],
				'version'          => $plugin['Version'],
				'activated'        => in_array( $name, $active_plugins, true ),
				'update_available' => array_key_exists( $name, $plugin_update_data ),
				'update_version'   => array_key_exists( $name, $plugin_update_data ) ? $plugin_update_data[ $name ]->new_version : '',
				'icon_url'         => 'https://ps.w.org/' . $slug[0] . '/assets/icon-128x128.png',
				'data'             => $plugin,
			];
		}

		$wp_mu_plugins = get_mu_plugins();
		$mu_plugins    = [];

		foreach ( $wp_mu_plugins as $name => $plugin ) {
			$slug         = explode( '/', $name );
			$mu_plugins[] = [
				'slug'    => $slug[0],
				'version' => $plugin['Version'],
				'data'    => $plugin,
			];
		}

		if ( ! function_exists( 'wp_get_themes' ) || ! function_exists( 'wp_get_theme' ) ) {
			require_once ABSPATH . 'wp-includes/theme.php';
		}

		$wp_themes         = wp_get_themes();
		$current_theme     = wp_get_theme();
		$theme_update_data = get_site_transient( 'update_themes' )->response ?? [];
		$themes            = [];

		foreach ( $wp_themes as $theme ) {
			$stylesheet = $theme->get_stylesheet();

			$themes[] = [
				'slug'             => $stylesheet,
				'version'          => $theme->get( 'Version' ),
				'parent'           => $stylesheet !== $current_theme->get_template() ? $theme->get_template() : '',
				'activated'        => $stylesheet === $current_theme->get_stylesheet(),
				'update_available' => array_key_exists( $stylesheet, $theme_update_data ),
				'update_version'   => array_key_exists( $stylesheet, $theme_update_data ) ? $theme_update_data[ $stylesheet ]['new_version'] : '',
			];
		}

		$results = [
			'theme'     => $themes,
			'plugin'    => $plugins,
			'mu_plugin' => $mu_plugins,
			'core'      => [
				[ 'version' => get_bloginfo( 'version' ) ],
			],
		];

        return $results;
    }
}
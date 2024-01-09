<?php
declare( strict_types=1 );

namespace InstaWP\Connect\Helpers;

class Inventory {

    public function fetch(): array {
        $results = [];

        if ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_mu_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$wp_plugins     = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$plugins        = [];

		foreach ( $wp_plugins as $name => $plugin ) {
			$slug      = explode( '/', $name );
			$plugins[] = [
				'slug'      => $slug[0],
				'version'   => $plugin['Version'],
				'activated' => in_array( $name, $active_plugins, true ),
			];
		}

		$wp_mu_plugins = get_mu_plugins();
		$mu_plugins    = [];

		foreach ( $wp_mu_plugins as $name => $plugin ) {
			$slug         = explode( '/', $name );
			$mu_plugins[] = [
				'slug'    => $slug[0],
				'version' => $plugin['Version'],
			];
		}

		if ( ! function_exists( 'wp_get_themes' ) || ! function_exists( 'wp_get_theme' ) ) {
			require_once ABSPATH . 'wp-includes/theme.php';
		}

		$wp_themes     = wp_get_themes();
		$current_theme = wp_get_theme();
		$themes        = [];

		foreach ( $wp_themes as $theme ) {
			$themes[] = [
				'slug'      => $theme->get_stylesheet(),
				'version'   => $theme->get( 'Version' ),
				'parent'    => $theme->get_stylesheet() !== $current_theme->get_template() ? $theme->get_template() : '',
				'activated' => $theme->get_stylesheet() === $current_theme->get_stylesheet(),
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
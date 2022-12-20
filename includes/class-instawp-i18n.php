<?php
if ( ! defined('INSTAWP_PLUGIN_DIR') ) {
    die;
}
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://instawp.com/
 * @since      1.0
 *
 * @package    instawp
 * @subpackage instawp/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0
 * @package    instawp
 * @subpackage instawp/includes
 * @author     instawp team
 */
class InstaWP_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * 
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'instawp-connect',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}

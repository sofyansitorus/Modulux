<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/sofyansitorus
 * @since             1.0.0
 * @package           Modulux
 *
 * @wordpress-plugin
 * Plugin Name:       Modulux
 * Plugin URI:        https://github.com/sofyansitorus/Modulux
 * Description:       Modular Addons for Beaver Builder WordPress plugin. Drag and drop page builder for WordPress.
 * Version:           1.1.4
 * Author:            Sofyan Sitorus
 * Author URI:        https://github.com/sofyansitorus
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       modulux
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'modulux_init' ) ) {

	// Defines plugin named constants.
	define( 'MODULUX_FILE', __FILE__ );
	define( 'MODULUX_PATH', plugin_dir_path( MODULUX_FILE ) );
	define( 'MODULUX_URL', plugin_dir_url( MODULUX_FILE ) );
	define( 'MODULUX_SLUG', 'modulux' );
	define( 'MODULUX_MODULES_DIR', 'modules' );
	define( 'MODULUX_MIN_VC_VERSION', '4.0' );

	// Include plugin dependencies.
	require_once MODULUX_PATH . 'lib/class-modulux-base.php';
	require_once MODULUX_PATH . 'lib/class-modulux-setting.php';
	require_once MODULUX_PATH . 'lib/class-modulux-module.php';
	require_once MODULUX_PATH . 'lib/class-modulux.php';

	/**
	 * Initialize the Woomizer class.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	function modulux_init() {
		// Initialize main class.
		Modulux\Modulux::loader();
	}
	modulux_init();
}// End if().

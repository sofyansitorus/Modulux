<?php
/**
 * The file that defines the abstract base class
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.0.0
 *
 * @package    Modulux
 * @subpackage Modulux/lib
 */

namespace Modulux;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The abstract base class.
 *
 * @since      1.0.0
 * @package    Modulux
 * @subpackage Modulux/lib
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
abstract class Modulux_Base {

	/**
	 * Notices for admin
	 *
	 * @var array
	 */
	protected $_admin_notices = array();

	/**
	 * Print notices for admin
	 *
	 * @return void
	 */
	public function print_admin_notices() {

		$notices = get_option( 'modulux_admin_notices', array() );

		if ( empty( $notices ) ) {
			return;
		}

		// Iterate through our notices to be displayed and print them.
		foreach ( $notices as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				wp_kses( $notice['notice'], wp_kses_allowed_html() )
			);
		}

		delete_option( 'modulux_admin_notices', array() );
	}

	/**
	 * Add admin notice
	 *
	 * @param string $notice Notice message.
	 * @param string $type Notice type. This can be "info", "warning", "error" or "success", "warning" as default.
	 */
	protected function add_admin_notice( $notice, $type = 'success' ) {

		$notices = get_option( 'modulux_admin_notices', array() );

		// We add our new notice.
		array_push(
			$notices, array(
				'notice'      => $notice,
				'type'        => $type,
				'dismissible' => 'is-dismissible',
			)
		);

		// Store the notices to DB.
		update_option( 'modulux_admin_notices', $notices );

		$notices = get_option( 'modulux_admin_notices', array() );
	}

	/**
	 * Get modules directory
	 *
	 * @since 1.0.0
	 *
	 * @param string $append Path that will be appended.
	 *
	 * @return string
	 */
	protected function get_module_dir( $append = '' ) {

		$dir = MODULUX_PATH . MODULUX_MODULES_DIR;

		if ( $append ) {
			if ( is_array( $append ) ) {
				$append = implode( '/', $append );
			}
			$dir .= '/' . ltrim( $append, '/' );
		}

		return $dir;
	}

	/**
	 * Get modules URL
	 *
	 * @since 1.0.0
	 *
	 * @param string $append Path that will be appended.
	 *
	 * @return string
	 */
	protected function get_module_url( $append = '' ) {

		$dir = MODULUX_URL . MODULUX_MODULES_DIR;

		if ( $append ) {
			if ( is_array( $append ) ) {
				$append = implode( '/', $append );
			}
			$dir .= '/' . ltrim( $append, '/' );
		}

		return $dir;
	}

	/**
	 * Get module readme file
	 *
	 * @since 1.0.0
	 *
	 * @param string $module_id Module ID.
	 *
	 * @return string|bool Readme file path or flase if not found.
	 */
	protected function get_module_readme( $module_id ) {

		$files = array(
			'readme.md',
			'README.md',
			'readme.txt',
			'README.txt',
		);

		foreach ( $files as $file ) {
			if ( file_exists( $this->get_module_dir( $module_id . '/' . $file ) ) ) {
				return $this->get_module_dir( $module_id . '/' . $file );
			}
		}

		return false;
	}

	/**
	 * Get admin URL
	 *
	 * @since 1.0.0
	 * @param array  $args URL query string.
	 * @param string $base Base file for the admin URL.
	 * @param string $nonce Action name for the nonce. Set false to disable the nonce.
	 * @return string Admin menu URL.
	 */
	protected function admin_url( $args = array(), $base = 'admin.php', $nonce = MODULUX_SLUG ) {
		$args = wp_parse_args( $args, array( 'page' => MODULUX_SLUG ) );

		// Remove "page" key from $args if it was set to false.
		if ( ! $args['page'] && is_bool( $args['page'] ) ) {
			unset( $args['page'] );
		}

		$url = admin_url( $base );

		if ( $nonce ) {
			$url = wp_nonce_url( $url, $nonce );
		}

		$url = add_query_arg( $args, $url );

		return $url;
	}

}

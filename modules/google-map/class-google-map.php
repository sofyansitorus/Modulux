<?php
/**
 * The module bootstrap file
 *
 * @link              https://github.com/sofyansitorus
 * @since             1.0.0
 * @package           Modulux
 *
 * Module Name: Google Map
 * Description: Advanced google map module.
 * Author Name: Sofyan Sitorus
 * Author URL: https://github.com/sofyansitorus/
 * Version: 1.0.0
 */

namespace Modulux\Modules;

use Modulux\Modulux_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * GoogleMap class
 *
 * @since 1.0.0
 */
class Google_Map extends Modulux_Module {

	/**
	 * Initialise the modules.
	 *
	 * This method is entry point for modules.
	 *
	 * @since  1.0.0
	 */
	protected function init() {
		$this->init_setting_fields();
	}

	/**
	 * Initialize the module setting fields
	 *
	 * @return void
	 */
	private function init_setting_fields() {
		$this->setting_fields = array(
			'api_key' => array(
				'label'    => __( 'RajaOngkir API Key', 'woongkir' ),
				'type'     => 'text',
				'required' => true,
			),
		);
	}
}

<?php
/**
 * The file that defines the base class abstraction
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
 * The abstract module class.
 *
 * @since      1.0.0
 * @package    Modulux
 * @subpackage Modulux/lib
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
abstract class Modulux_Module extends Modulux_Base {

	/**
	 * Module Data
	 *
	 * @var array
	 */
	protected $module_data;

	/**
	 * Module setting fields.
	 *
	 * @var array
	 */
	protected $setting_fields = array();

	/**
	 * Module setting fields.
	 *
	 * @var Modulux_Setting
	 */
	protected $setting;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @param array $module_data Module data.
	 */
	final public function __construct( $module_data ) {
		$this->module_data = $module_data;

		add_action( 'modulux_module_installed_' . $this->get_id(), array( $this, 'on_installed' ) );
		add_action( 'modulux_module_activated_' . $this->get_id(), array( $this, 'on_activated' ) );
		add_action( 'modulux_module_deactivated_' . $this->get_id(), array( $this, 'on_deactivated' ) );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'modulux_module_row_actions', array( $this, 'add_settings_link' ), 10, 2 );

		// Initialize subclass.
		if ( $this->is_enabled() ) {
			$this->boot();
		}
	}

	/**
	 * Get module ID
	 *
	 * @return string
	 */
	final protected function get_id() {
		return $this->get_data( 'id' );
	}

	/**
	 * Get module info
	 *
	 * @param string $key Module data key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	final protected function get_data( $key = null, $default = null ) {
		$data = $this->module_data;
		if ( $key ) {
			return isset( $data ) ? $data[ $key ] : $default;
		}
		return $data;
	}

	/**
	 * Initialise the modules.
	 *
	 * This method is entry point for modules. Must be overridden in subclass.
	 *
	 * @since  1.0.0
	 */
	protected function boot() {
		// Must be overriden by subclass.
	}

	/**
	 * Module status wheter module is enabled or not.
	 *
	 * If return false, user will not allowed to activate the module. Default: true.
	 * Can be overridden by subclass.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_enabled() {
		return true;
	}

	/**
	 * Module status wheter module is required or not.
	 *
	 * If return true, user will not allowed to deactivate the module. Default: false.
	 * Can be overridden by subclass.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_required() {
		return false;
	}

	/**
	 * Callback method will be executed when module installed.
	 *
	 * This method hooked into "'modulux_module_installed_' . $this->get_id()" action.
	 * Can be overridden by subclass.
	 *
	 * @since  1.0.0
	 * @param array $module_data Module data.
	 */
	public function on_installed( $module_data ) {
		// Must be overriden in subclass.
	}

	/**
	 * Callback method will be executed when module activated.
	 *
	 * This method hooked into "'modulux_module_activated_' . $this->get_id()" action.
	 * Must be overridden in subclass.
	 *
	 * @since  1.0.0
	 * @param array $module_data Module data.
	 */
	public function on_activated( $module_data ) {
		// Must be overriden in subclass.
	}

	/**
	 * Callback method will be executed when module deactivated.
	 *
	 * This method hooked into "'modulux_module_deactivated_' . $this->get_id()" action.
	 * Must be overridden in subclass.
	 *
	 * @since  1.0.0
	 * @param array $module_data Module data.
	 */
	public function on_deactivated( $module_data ) {
		// Must be overriden in subclass.
	}

	/**
	 * Register module setting fields.
	 *
	 * This method is hooked into "admin_init" action.
	 *
	 * @since  1.0.0
	 */
	public function register_settings() {
		if ( ! $this->has_settings() ) {
			return;
		}

		$has_tab     = false;
		$has_section = false;

		$setting_fields = array();

		$this->setting = new Modulux_Setting( $this->module_data );

		foreach ( $this->setting_fields as $key => $field ) {
			$field_type  = isset( $field['type'] ) ? $field['type'] : false;
			$field['id'] = $this->get_setting_name( $key );

			switch ( $field_type ) {
				case 'tab':
					$this->setting->add_tab( $field );
					break;

				case 'section':
					$this->setting->add_section( $field );
					break;

				default:
					$this->setting->add_field( $field );
					break;
			}
		}

		$this->setting->build();
	}

	/**
	 * Add settings link for the module.
	 *
	 * @param array $actions Current module row actions.
	 * @param array $module Module data.
	 * @return array
	 */
	public function add_settings_link( $actions, $module ) {
		if ( $this->setting_fields && get_class( $this ) === $module['class'] ) {
			$actions['setting'] = array(
				'url'   => $this->admin_url(
					array(
						'module_setting' => $module['id'],
					)
				),
				'label' => __( 'Settings', 'modulux' ),
			);
		}
		return $actions;
	}

	/**
	 * Check if module has settings
	 *
	 * @return boolean
	 */
	public function has_settings() {
		return count( $this->setting_fields );
	}

	/**
	 * Get setting name
	 *
	 * @param string $id Raw setting ID.
	 * @return string
	 */
	private function get_setting_name( $id ) {
		return 'modulux_setting_' . str_replace( '-', '_', $this->get_id() ) . '_' . trim( $id, '_' );
	}

	/**
	 * Get setting value
	 *
	 * @param string $id Raw setting ID.
	 * @param mixed  $default Default value as fallback.
	 * @return string
	 */
	protected function get_setting( $id, $default = null ) {
		return get_option( $this->get_setting_name( $id ), $default );
	}

	/**
	 * Render module settings form
	 *
	 * @return void
	 */
	public function render_settings_form() {
		if ( ! $this->has_settings() ) {
			return;
		}
		$this->setting->render_form();
	}
}

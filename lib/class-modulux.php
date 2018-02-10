<?php
/**
 * The file that defines the core plugin class
 *
 * @link       https://github.com/sofyansitorus
 * @since      1.0.0
 *
 * @package    Modulux
 * @subpackage Modulux/lib
 */

namespace Modulux;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The core plugin class.
 *
 * This is used as entry point of the plugin.
 *
 * @since      1.0.0
 * @package    Modulux
 * @subpackage Modulux/lib
 * @author     Sofyan Sitorus <sofyansitorus@gmail.com>
 */
final class Modulux extends Modulux_Base {

	/**
	 * Singleton instance
	 *
	 * @var Modulux
	 */
	private static $instance = null;

	/**
	 * Activated modules
	 *
	 * @var array
	 */
	private $mod_activated = array();

	/**
	 * Installed modules
	 *
	 * @var array
	 */
	private $mod_installed = array();

	/**
	 * Plugin data
	 *
	 * @var array
	 */
	private $plugin_data = array();

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {

		// Check dependecies.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Register activation hook.
		register_activation_hook( MODULUX_FILE, array( $this, 'activation_hook' ) );

		// Run plugins.
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

	}

	/**
	 * Load the instance of the class
	 *
	 * @since 1.0.0
	 * @return Modulux
	 */
	public static function loader() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin entry point
	 *
	 * This method hooked into "plugins_loaded" action.
	 *
	 * @since 1.0.0
	 */
	public function plugins_loaded() {

		// Check dependency and compatibility.
		if ( ! $this->is_vc_activated() || ! $this->is_vc_version_compatible() ) {
			return false;
		}

		// Set plugin data.
		$this->plugin_data = get_plugin_data( MODULUX_FILE );

		load_plugin_textdomain( 'modulux', false, dirname( MODULUX_PATH ) . '/lang' );

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );

		// Filter to add plugin setting links.
		add_filter( 'plugin_action_links_' . plugin_basename( MODULUX_FILE ), array( $this, 'plugin_action_links' ), 99, 2 );

		add_action( 'admin_notices', array( $this, 'print_admin_notices' ) );

		$this->load_modules( true );

	}

	/**
	 * Load modules
	 *
	 * @since 1.0.0
	 * @param boolean $init_modules Wether to initilize module instance or not.
	 * @return void
	 */
	private function load_modules( $init_modules = false ) {

		$this->mod_activated = get_option( 'modulux_modules_activated', array() );

		$mod_installed = get_transient( 'modulux_modules_installed' );

		if ( false === $mod_installed ) {
			$mod_installed = $this->load_modules_from_disk();
			set_transient( 'modulux_modules_installed', $mod_installed, DAY_IN_SECONDS );
		}

		$this->mod_installed = $mod_installed;

		$flush_active_modules = false;

		if ( $init_modules && $this->mod_activated ) {
			foreach ( $this->mod_activated as $module_key => $module ) {
				$module_instance = $this->init_module( $module );
				if ( $module_instance ) {
					$this->mod_activated[ $module_key ]['instance'] = $module_instance;
				} else {
					unset( $this->mod_activated[ $module_key ] );
					$flush_active_modules = true;
				}
			}
		}

		if ( $flush_active_modules ) {
			$this->save_active_modules();
		}

	}

	/**
	 * Load installed modules from disk
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function load_modules_from_disk() {
		$mod_installed = array();

		foreach ( glob( $this->get_module_dir( '*' ), GLOB_ONLYDIR ) as $dir ) {

			$module_dir = basename( $dir );

			foreach ( glob( $dir . '/*.php' ) as $module_file ) {

				$file_data = get_file_data(
					$module_file,
					array(
						'name'        => 'Module Name',
						'description' => 'Description',
						'version'     => 'Version',
						'author_name' => 'Author Name',
						'author_url'  => 'Author URL',
					)
				);

				$module_class = $this->get_module_class( $module_file );

				if ( ! empty( $file_data ) && $module_class ) {
					$mod_installed[ $module_dir ] = array(
						'data'  => wp_parse_args(
							$file_data, array(
								'name'        => basename( $module_file ),
								'description' => __( 'No description available', 'modulux' ),
								'version'     => '1.0.0',
								'author_name' => '',
								'author_url'  => '#',
							)
						),
						'file'  => $module_file,
						'id'    => $module_dir,
						'class' => $module_class,
					);
					break;
				}
			}
		}

		return $mod_installed;
	}

	/**
	 * Get module Class name.
	 *
	 * @since 1.0.0
	 * @param string $file File path need to be camel cased.
	 * @return string Camel cased class name.
	 */
	private function get_module_class( $file ) {

		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'r' );

		// Pull only the first 8kiB of the file in.
		$contents = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$contents = str_replace( "\r", "\n", $contents );

		// Start with a blank namespace and class.
		$namespace = '';
		$class     = '';

		// Set helper values to know that we have found the namespace/class token and need to collect the string values after them.
		$getting_namespace = false;
		$getting_class     = false;

		// Go through each token and evaluate it as necessary.
		foreach ( token_get_all( $contents ) as $token ) {

			// If this token is the namespace declaring, then flag that the next tokens will be the namespace name.
			if ( is_array( $token ) && T_NAMESPACE === $token[0] ) {
				$getting_namespace = true;
			}

			// If this token is the class declaring, then flag that the next tokens will be the class name.
			if ( is_array( $token ) && T_CLASS === $token[0] ) {
				$getting_class = true;
			}

			// While we're grabbing the namespace name.
			if ( true === $getting_namespace ) {

				// If the token is a string or the namespace separator...
				if ( is_array( $token ) && in_array( $token[0], [ T_STRING, T_NS_SEPARATOR ], true ) ) {

					// Append the token's value to the name of the namespace.
					$namespace .= $token[1];

				} elseif ( ';' === $token ) {

					// If the token is the semicolon, then we're done with the namespace declaration.
					$getting_namespace = false;

				}
			}

			// While we're grabbing the class name.
			if ( true === $getting_class ) {

				// If the token is a string, it's the name of the class.
				if ( is_array( $token ) && T_STRING === $token[0] ) {

					// Store the token's value as the class name.
					$class = $token[1];

					// Got what we need, stope here.
					break;
				}
			}
		}

		// Build the fully-qualified class name and return it.
		return $namespace ? $namespace . '\\' . $class : $class;

	}

	/**
	 * Initialize a module
	 *
	 * @param array $module Module data.
	 * @return Modulux_Module
	 */
	private function init_module( $module ) {

		// Check module file exists.
		if ( ! file_exists( $module['file'] ) ) {
			return;
		}

		// Include active module file dependencies.
		require_once $module['file'];

		if ( ! class_exists( $module['class'] ) ) {
			return;
		}

		if ( version_compare( PHP_VERSION, '5.6.0', '>=' ) ) {
			$instance = new $module['class']( $module );
		} else {
			$reflect  = new \ReflectionClass( $module['class'] );
			$instance = $reflect->newInstance( $module );
		}

		if ( ! $instance instanceof Modulux_Module ) {
			return;
		}

		return $instance;
	}

	/**
	 * Get module data.
	 *
	 * @since 1.0.0
	 * @param string $module Module directory.
	 * @return array|bool Array of module property data. False if module not exists.
	 */
	private function get_module_data( $module ) {
		if ( ! isset( $this->mod_installed[ $module ] ) ) {
			return false;
		}
		return $this->mod_installed[ $module ];
	}

	/**
	 * Check if module is activated
	 *
	 * @param string $module Module directory as module ID.
	 * @return boolean
	 */
	private function is_module_active( $module ) {
		return $module ? isset( $this->mod_activated[ $module ] ) : false;
	}

	/**
	 * Save all activated modules data to database
	 */
	private function save_active_modules() {
		$modules = array();
		foreach ( $this->mod_activated as $key => $module ) {
			unset( $module['instance'] );
			$modules[ $key ] = $module;
		}
		return update_option( 'modulux_modules_activated', $modules );
	}

	/**
	 * Add admin menu page
	 *
	 * This method hooked into "admin_menu" action.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_page() {
		$admin_page = add_menu_page(
			$this->plugin_data['Name'],
			'Modulux',
			'manage_options',
			MODULUX_SLUG,
			array( $this, 'render_admin_page' )
		);

		add_action( "load-$admin_page", array( $this, 'modules_controller' ) );
	}

	/**
	 * Modules activation and deactivation controllers.
	 *
	 * This method hooked into "load-$admin_page" action.
	 *
	 * @since 1.0.0
	 * @throws Exception If the module is invalid or disabled.
	 */
	public function modules_controller() {

		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : false;
		$nonce  = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : false;
		$module = isset( $_REQUEST['module'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['module'] ) ) : false;

		if ( empty( $action ) ) {
			return;
		}

		try {
			// Validate nonce.
			if ( ! wp_verify_nonce( $nonce, MODULUX_SLUG ) ) {
				throw new Exception( esc_html__( 'Invalid nonce!', 'modulux' ) );
			}

			if ( 'activate_module' === $action ) {

				// Check if module already active.
				if ( $this->is_module_active( $module ) ) {
					throw new Exception( esc_html__( 'Module is already active!', 'modulux' ) );
				}

				// Get module data.
				$module_data = $this->get_module_data( $module );

				if ( ! $module_data ) {
					throw new Exception( esc_html__( 'Module is invalid or not exists!', 'modulux' ) );
				}

				$module_instance = $this->init_module( $module_data );

				if ( ! $module_instance ) {
					throw new Exception( esc_html__( 'Fail to load module instance!', 'modulux' ) );
				}

				if ( ! $module_instance->is_enabled() ) {
					// Translators: %s Module name.
					throw new Exception( sprintf( __( 'Failed to activate module %s due to an error: Module is disabled.', 'modulux' ), $module_data['data']['name'] ) );
				}

				do_action( 'modulux_module_activated_' . $module, $module_data );

				$this->mod_activated[ $module ] = $module_data;

				$this->save_active_modules();

				$this->add_admin_notice( __( 'Module activated.', 'modulux' ) );

				wp_safe_redirect( $this->admin_url() );
				exit;
			} elseif ( 'deactivate_module' === $action ) {

				// Check if module already inactive.
				if ( ! $this->is_module_active( $module ) ) {
					throw new Exception( esc_html__( 'Module is already inactive!', 'modulux' ) );
				}

				// Get module data.
				$module_data = $this->get_module_data( $module );

				if ( ! $module_data ) {
					throw new Exception( esc_html__( 'Module is invalid!', 'modulux' ) );
				}

				$module_instance = $this->mod_activated[ $module ]['instance'];

				if ( ! $module_instance ) {
					throw new Exception( esc_html__( 'Fail to load module instance!', 'modulux' ) );
				}

				if ( $module_instance->is_required() ) {
					// Translators: %s Module name.
					throw new Exception( sprintf( __( 'Failed to deactivate module %s due to an error: Module is required.', 'modulux' ), $module_data['data']['name'] ) );
				}

				do_action( 'modulux_module_deactivated_' . $module, $module_data );

				unset( $this->mod_activated[ $module ] );

				$this->save_active_modules();

				$this->add_admin_notice( __( 'Module deactivated.', 'modulux' ) );

				wp_safe_redirect( $this->admin_url() );
				exit;

			} elseif ( 'reload_modules' === $action ) {

				// Load the modules from the disk.
				$modules = $this->load_modules_from_disk();

				if ( empty( $modules ) ) {
					throw new Exception( esc_html__( 'Failed to load the modules data!', 'modulux' ) );
				}

				// Delete existing transient.
				delete_transient( 'modulux_modules_installed' );

				if ( false === set_transient( 'modulux_modules_installed', $modules, DAY_IN_SECONDS ) ) {
					throw new Exception( esc_html__( 'Failed to store the modules data!', 'modulux' ) );
				}

				$this->add_admin_notice( __( 'Modules data reloaded.', 'modulux' ) );

				wp_safe_redirect( $this->admin_url() );
				exit;
			}
		} catch ( Exception $e ) {

			$this->add_admin_notice( $e->getMessage(), 'error' );

			wp_safe_redirect( $this->admin_url() );

			exit;
		}
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {

		$module_setting = isset( $_GET['module_setting'] ) ? sanitize_text_field( wp_unslash( $_GET['module_setting'] ) ) : false;

		if ( $module_setting && isset( $this->mod_activated[ $module_setting ]['instance'] ) && $this->mod_activated[ $module_setting ]['instance'] instanceof Modulux_Module ) {
			$this->mod_activated[ $module_setting ]['instance']->render_settings_form();
		} else {
			$this->modules_list_table();
		}
	}

	/**
	 * Render admin page
	 */
	public function modules_list_table() {
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $this->plugin_data['Name'] ); ?></h1>
			<a href="<?php echo esc_url( $this->admin_url( array( 'action' => 'reload_modules' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Reload Modules', 'modulux' ); ?></a>
			<hr class="wp-header-end">
			<ul class="subsubsub">
				<li class="all">
					<a href="<?php echo esc_url( $this->admin_url() ); ?>">
						<?php esc_html_e( 'All', 'lkr' ); ?> <span class="count">(<?php echo count( $this->mod_installed ); ?>)</span>
					</a>
				</li>
				<?php if ( count( $this->mod_activated ) ) : ?>
				<li class="active">
					&nbsp;|&nbsp;<a href="<?php echo esc_url( $this->admin_url( array( 'filter' => 'active' ) ) ); ?>">
						<?php esc_html_e( 'Active', 'lkr' ); ?> <span class="count">(<?php echo count( $this->mod_activated ); ?>)</span>
					</a>
				</li>
				<?php endif; ?>
				<?php if ( 0 < ( count( $this->mod_installed ) - count( $this->mod_activated ) ) ) : ?>
				<li class="active">
					&nbsp;|&nbsp;<a href="<?php echo esc_url( $this->admin_url( array( 'filter' => 'inactive' ) ) ); ?>">
						<?php esc_html_e( 'Inactive', 'lkr' ); ?> <span class="count">(<?php echo( count( $this->mod_installed ) - count( $this->mod_activated ) ); ?>)</span>
					</a>
				</li>
				<?php endif; ?>
			</ul>

			<table class="wp-list-table widefat plugins">
				<thead>
					<tr>
						<th scope="col" id="name" class="manage-column column-name" style=""><?php esc_html_e( 'Module', 'modulux' ); ?></th>
						<th scope="col" id="description" class="manage-column column-description" style=""><?php esc_html_e( 'Description', 'modulux' ); ?></th>
					</tr>
				</thead>
				<tbody id="the-list">
				<?php

				$filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : false;

				foreach ( $this->mod_installed as $key => $module ) {

					$is_module_active = $this->is_module_active( $key );

					if ( $filter && 'active' === $filter && ! $is_module_active ) {
						continue;
					}

					if ( $filter && 'inactive' === $filter && $is_module_active ) {
						continue;
					}

					$row_class = $is_module_active ? 'active' : 'inactive';
					?>
					<tr id="advanced-visual-composer-addons" class="<?php echo esc_attr( $row_class ); ?>" data-slug="<?php echo esc_attr( $key ); ?>">
						<td class="plugin-title"><strong><?php echo esc_html( $module['data']['name'] ); ?></strong>
							<div class="row-actions visible">
								<?php
								$row_actions = array();

								if ( $is_module_active ) {
									$row_actions['deactivate'] = array(
										'url'   => $this->admin_url(
											array(
												'module' => $key,
												'action' => 'deactivate_module',
											)
										),
										'label' => __( 'Deactivate', 'modulux' ),
									);
								} else {
									$row_actions['activate'] = array(
										'url'   => $this->admin_url(
											array(
												'module' => $key,
												'action' => 'activate_module',
											)
										),
										'label' => __( 'Activate', 'modulux' ),
									);
								}

								$row_actions = apply_filters( 'modulux_module_row_actions', $row_actions, $module );

								$last_action = ( 1 < count( $row_actions ) ) ? array_keys( $row_actions )[ ( count( $row_actions ) - 1 ) ] : false;
								?>
								<?php foreach ( $row_actions as $row_action_key => $row_action ) : ?>
									<span class="row-action <?php echo esc_attr( $row_action_key ); ?>">
										<a href="<?php echo esc_url( $row_action['url'] ); ?>"><?php echo esc_html( $row_action['label'] ); ?></a>
									</span>
									<?php if ( $last_action && $last_action !== $row_action_key ) : ?>
									&nbsp;|&nbsp;
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</td>
						<td class="column-description desc">
							<div class="plugin-description"><p><?php echo esc_html( $module['data']['description'] ); ?></p></div>
							<div class="row-metas visible">
								<?php if ( $module['data']['version'] ) : ?>
									<span class="row-meta version">
										<?php esc_html_e( 'Version' ); ?> <?php echo esc_html( $module['data']['version'] ); ?>
									</span>
								<?php endif; ?>
								<?php if ( $module['data']['author_name'] ) : ?>
									&nbsp;|&nbsp;
									<span class="row-meta author">
										<?php esc_html_e( 'By' ); ?> <a href="<?php echo esc_url( $module['data']['author_url'] ); ?>"><?php echo esc_html( $module['data']['author_name'] ); ?></a>
									</span>
								<?php endif; ?>
							</div>
						</td>
					</tr>
					<?php
				}
				?>
				</tbody>
				<tfoot>
					<tr>
						<th scope="col" class="manage-column column-name" style=""><?php esc_html_e( 'Module', 'modulux' ); ?></th>
						<th scope="col" class="manage-column column-description" style=""><?php esc_html_e( 'Description', 'modulux' ); ?></th>
					</tr>
				</tfoot>
			</table>
		</div>
		<?php
	}

	/**
	 * Add settings link on the plugins.php.
	 *
	 * This method hooken into "plugin_action_links_' . plugin_basename( MODULUX_FILE )" filter.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $links List of existing plugin action links.
	 * @return array         List of modified plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$links = array_merge(
			array(
				'<a href="' . esc_url( $this->admin_url() ) . '">' . __( 'Settings', 'modulux' ) . '</a>',
			),
			$links
		);
		return $links;
	}

	/**
	 * Runs when the plugin is activated
	 */
	public function activation_hook() {

		// Check dependencies.
		if ( ! $this->is_vc_activated() ) {
			die( esc_html__( 'You must install and activate WPBakery Page Builder plugin before activating this plugin.', 'modulux' ) );
		}

		// Check compatibility.
		if ( ! $this->is_vc_version_compatible() ) {
			// translators: %s WPBakery version.
			die( sprintf( esc_html__( 'This plugin requires WPBakery Page Builder plugin version %s or greater', 'modulux' ), esc_html( MODULUX_MIN_VC_VERSION ) ) );
		}

		if ( false === get_option( 'modulux_install_time' ) ) {

			$this->load_modules();

			if ( $this->mod_installed ) {
				foreach ( $this->mod_installed as $module => $module_data ) {
					$module_instance = $this->init_module( $module_data );
					if ( $module_instance ) {
						do_action( 'modulux_module_installed_' . $module, $module_data );
						do_action( 'modulux_module_activated_' . $module, $module_data );
						$this->mod_activated[ $module ] = $module_data;
						$this->save_active_modules();
					}
				}
			}

			update_option( 'modulux_install_time', current_time( 'timestamp' ) );
		}
	}

	/**
	 * Check if VC plugin is activated
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_vc_activated() {
		return is_plugin_active( 'js_composer/js_composer.php' );
	}

	/**
	 * Check if VC plugin version is compatible
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function is_vc_version_compatible() {
		if ( ! defined( 'WPB_VC_VERSION' ) ) {
			return false;
		}

		return version_compare( WPB_VC_VERSION, MODULUX_MIN_VC_VERSION, '>' );
	}

}

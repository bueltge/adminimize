<?php
namespace Inpsyde\Adminimize;

/**
 * WordPress Options Page "Settings > Adminimize 2"
 *
 * @since   2012.08.13 2.0
 * @version 2012.08.13
 * @author  eteubert
 */
class Options_Page {

	private static $instance = NULL;
	private static $option_string;
	public static $pagehook;

	/**
	 * Instance of main plugin
	 *
	 * @var Adminimize
	 */
	private $plugin;

	/**
	 * Method for ensuring that only one instance of this object is used.
	 *
	 * @since	0.1
	 * @access	public
	 * @static
	 * @return	Options_Page
	 */
	public static function get_instance() {

		if ( ! self::$instance )
			self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Setting up some data, initialize translations and start the hooks.
	 *
	 * @since	0.1
	 * @access	public
	 * @uses	is_admin, add_filter
	 * @return	void
	 */
	public function __construct() {

		if ( ! is_admin() )
			return;

		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {

		self::$option_string = 'adminimize';

		if ( $this->plugin->is_active_for_multisite() ) {
			add_action( 'network_admin_menu',    array( $this, 'add_network_options_page' ) );
			// add settings link
			add_filter( 'network_admin_plugin_action_links', array( $this, 'network_admin_plugin_action_links' ), 10, 2 );
			// save settings on network
			add_action( 'network_admin_edit_' . self::$option_string, array( $this, 'save_network_settings_page' ) );
			// return message for update settings
			add_action( 'network_admin_notices', array( $this, 'get_network_admin_notices' ) );
		} else {
			add_action( 'admin_menu',            array( $this, 'add_options_page' ) );
			// add settings link
			add_filter( 'plugin_action_links',   array( $this, 'plugin_action_links' ), 10, 2 );
			// use settings API
			add_action( 'admin_init',            array( $this, 'register_settings' ) );
		}
	}

	/**
	 * Set instance of main plugin.
	 *
	 * @param Adminimize $plugin
	 */
	public function set_plugin( \Inpsyde\Adminimize\Adminimize $plugin ) {
		$this->plugin = $plugin;
	}

	public function add_network_options_page( $value='' ) {
		self::$pagehook = add_submenu_page(
			'settings.php',
			$this->plugin->get_plugin_header( 'Name' ) . ' ' . __( 'Settings' ),
			$this->plugin->get_plugin_header( 'Name' ),
			'manage_options',
			'adminimize2', //$this->plugin->get_plugin_basename(), /////// DO NEVER EVER USE THE PLUGIN FILE PATH AS MENUSLUG AGAIN!!!!!
			array( $this, 'get_settings_page' )
		);

		add_action( 'load-' . self::$pagehook, array( $this, 'prepare_dragndrop' ) );
	}

	public function add_options_page() {

		self::$pagehook = add_options_page(
			$this->plugin->get_plugin_header( 'Name' ) . ' ' . __( 'Settings' ),
			$this->plugin->get_plugin_header( 'Name' ),
			'manage_options',
			'adminimize2', //$this->plugin->get_plugin_basename(), /////// DO NEVER EVER USE THE PLUGIN FILE PATH AS MENUSLUG AGAIN!!!!!
			array( $this, 'get_settings_page' )
		);

		add_action( 'load-' . self::$pagehook, array( $this, 'prepare_dragndrop' ) );
	}

	/**
	 * Tell WordPress to embed all metabox related code.
	 */
	public function prepare_dragndrop() {

		$script_debug = ( defined( 'SCRIPT_DEBUG' ) && true == SCRIPT_DEBUG ) ?
			'.dev' : '';

		$script = sprintf( 'js/admin%s.js', $script_debug );

		wp_register_script(
			'adminimize-admin-js',
			plugins_url( $script, __FILE__ ),
			array( 'jquery', 'postbox' ),
			false,
			true
		);

		add_screen_option( 'layout_columns', array( 'max' => 3, 'default' => 2 ) );

		do_action( 'adminimize_register_metabox' );

	}

	public function test_widget(){ echo '<p>Test Widget</p>'; }

	public function network_admin_plugin_action_links( $links, $file ) {
		$plugin_basename = $this->plugin->get_plugin_basename();

		if ( $plugin_basename == $file  )
			$links[] = '<a href="settings.php?page=' . $plugin_basename . '">' . __( 'Settings' ) . '</a>';

		return $links;
	}

	public function save_network_settings_page() {

		if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'], self::$pagehook ) )
			wp_die( 'Sorry, you failed the nonce test.' );

		// update options
		update_site_option( 'adminimize', $_POST['adminimize'] );

		// redirect to settings page in network
		wp_redirect(
			add_query_arg(
				array( 'page' => $this->plugin->get_plugin_basename(), 'updated' => 'true' ),
				network_admin_url( 'settings.php' )
			)
		);
		exit();
	}

	public function get_network_admin_notices() {
		if ( isset( $_GET['updated'] ) && stripos( $GLOBALS['current_screen']->id, 'adminimize' ) ) {
			?>
			<div id="message" class="updated">
				<p>
					<strong><?php echo __( 'Settings saved.', 'adminimize' ); ?></strong>
				</p>
			</div>
			<?php
		}
	}

	public function plugin_action_links( $links, $file ) {

		$plugin_basename = $this->plugin->get_plugin_basename();

		if ( $plugin_basename == $file  )
			$links[] = '<a href="options-general.php?page=' . $plugin_basename . '">' . __( 'Settings' ) . '</a>';

		return $links;
	}

	public function register_settings() {

		register_setting( Options_Page::$pagehook, 'adminimize' );

		do_action( 'adminimize_register_settings' );
	}

	public function get_settings_page() {

		if ( $this->plugin->is_active_for_multisite() )
			$form_action = 'edit.php?action=' . self::$option_string;
		else
			$form_action = 'options.php';

		wp_enqueue_script( 'adminimize-admin-js' );

		?>
		<div class="wrap">
			<?php screen_icon('options-general'); ?>
			<h2><?php echo $this->plugin->get_plugin_header( 'Name' ); ?></h2>

			<form method="post" action="<?php echo $form_action; ?>">

				<?php
					wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
        			wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
        		?>

				<?php
					if ( $this->plugin->is_active_for_multisite() ):
						wp_nonce_field( self::$pagehook );
					else:
						settings_fields( self::$pagehook );
					endif;

					do_settings_fields( self::$pagehook, 'default' );

					$screen = get_current_screen();
				?>

			<div id="dashboard-widgets-wrap">
				<?php printf( '<div id="dashboard-widgets" class="metabox-holder columns-%d">', $screen->get_columns() ); ?>

					<div id="postbox-container-1" class="postbox-container">
						<?php do_meta_boxes( self::$pagehook, 'normal', '' ); ?>
					</div>

					<div id="postbox-container-2" class="postbox-container">
						<?php do_meta_boxes( self::$pagehook, 'advanced', array() ); ?>
					</div>

				</div> <!-- .end dashboard-widgets -->
				<div class="clear"></div>
			</div> <!-- .end dashboard-widgets-wrap -->

			</form>

		</div>
		<?php
	}
}

<?php
/**
 * Admin menu and pages.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the admin menu, leads page and settings page.
 */
class Admin {

	/**
	 * Leads page hook suffix.
	 *
	 * @var string
	 */
	private string $leads_hook = '';

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private string $settings_hook = '';

	/**
	 * Constructor: hook registration.
	 */
	public function __construct() {
		new Settings();

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register top-level menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Lead Capture', 'wp-lead-capture-pro' ),
			__( 'Lead Capture', 'wp-lead-capture-pro' ),
			'manage_options',
			'wplcp-leads',
			[ $this, 'render_leads_page' ],
			'dashicons-email-alt',
			26
		);

		$this->leads_hook = add_submenu_page(
			'wplcp-leads',
			__( 'All Leads', 'wp-lead-capture-pro' ),
			__( 'All Leads', 'wp-lead-capture-pro' ),
			'manage_options',
			'wplcp-leads',
			[ $this, 'render_leads_page' ]
		);

		$this->settings_hook = add_submenu_page(
			'wplcp-leads',
			__( 'Settings', 'wp-lead-capture-pro' ),
			__( 'Settings', 'wp-lead-capture-pro' ),
			'manage_options',
			Settings::PAGE_SLUG,
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Enqueue minimal inline CSS on plugin admin pages only.
	 *
	 * @param string $hook Current page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ $this->leads_hook, $this->settings_hook ], true ) ) {
			return;
		}

		$css = '.wplcp-admin-wrap h1{margin-bottom:16px}.wplcp-admin-wrap .wp-list-table td{vertical-align:middle}';

		wp_register_style( 'wplcp-admin', false, [], WPLCP_VERSION );
		wp_enqueue_style( 'wplcp-admin' );
		wp_add_inline_style( 'wplcp-admin', $css );
	}

	/**
	 * Render the leads list page.
	 *
	 * @return void
	 */
	public function render_leads_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$table = new Leads_List_Table();
		$table->prepare_items();
		?>
		<div class="wrap wplcp-admin-wrap">
			<h1><?php esc_html_e( 'Leads', 'wp-lead-capture-pro' ); ?></h1>
			<form method="post">
				<?php
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap wplcp-admin-wrap">
			<h1><?php esc_html_e( 'Lead Capture Settings', 'wp-lead-capture-pro' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( Settings::PAGE_SLUG );
				do_settings_sections( Settings::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

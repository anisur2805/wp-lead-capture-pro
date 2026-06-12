<?php
/**
 * Plugin bootstrap.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro;

use ClanDevs\LeadCapturePro\Admin\Admin;
use ClanDevs\LeadCapturePro\Frontend\Form;
use ClanDevs\LeadCapturePro\Frontend\Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class. Singleton.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Boot plugin components and register core hooks.
	 *
	 * @return void
	 */
	private function init(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		Installer::maybe_upgrade();

		new Admin();
		new Form();
		new Handler();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wp-lead-capture-pro',
			false,
			dirname( plugin_basename( WPLCP_PLUGIN_FILE ) ) . '/languages'
		);
	}
}

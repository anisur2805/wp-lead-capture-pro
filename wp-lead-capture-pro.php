<?php
/**
 * Plugin Name:       WP Lead Capture Pro
 * Description:       Lead capture form with Stripe Payment Intent and Zapier webhook integration.
 * Version:           1.0.0
 * Author:            ClanDevs
 * Text Domain:       wp-lead-capture-pro
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * License:           GPL-2.0-or-later
 *
 * @package ClanDevs\LeadCapturePro
 */

defined( 'ABSPATH' ) || exit;

define( 'WPLCP_VERSION', '1.0.0' );
define( 'WPLCP_MIN_PHP', '8.0' );
define( 'WPLCP_MIN_WP', '6.0' );
define( 'WPLCP_PLUGIN_FILE', __FILE__ );
define( 'WPLCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPLCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * PHP version gate.
 */
if ( version_compare( PHP_VERSION, WPLCP_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'WP Lead Capture Pro requires PHP %1$s or higher. You are running %2$s.', 'wp-lead-capture-pro' ),
						WPLCP_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

/**
 * WordPress version gate.
 */
if ( version_compare( get_bloginfo( 'version' ), WPLCP_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required WordPress version, 2: current WordPress version */
						__( 'WP Lead Capture Pro requires WordPress %1$s or higher. You are running %2$s.', 'wp-lead-capture-pro' ),
						WPLCP_MIN_WP,
						get_bloginfo( 'version' )
					)
				)
			);
		}
	);
	return;
}

if ( ! file_exists( WPLCP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'WP Lead Capture Pro: missing Composer autoloader. Run "composer install" in the plugin directory.', 'wp-lead-capture-pro' )
			);
		}
	);
	return;
}

require_once WPLCP_PLUGIN_DIR . 'vendor/autoload.php';

register_activation_hook( __FILE__, [ ClanDevs\LeadCapturePro\Installer::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ ClanDevs\LeadCapturePro\Installer::class, 'deactivate' ] );

add_action( 'plugins_loaded', [ ClanDevs\LeadCapturePro\Plugin::class, 'get_instance' ] );

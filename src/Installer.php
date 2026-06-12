<?php
/**
 * Database installer and version migration.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro;

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation, deactivation and DB schema upgrades.
 */
class Installer {

	/**
	 * Option key storing the installed DB version.
	 */
	const DB_VERSION_OPTION = 'wplcp_db_version';

	/**
	 * Plugin activation: create table, store DB version.
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_table();
		update_option( self::DB_VERSION_OPTION, WPLCP_VERSION );
	}

	/**
	 * Plugin deactivation: flush rewrite rules only. Table is preserved.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Create the leads table via dbDelta().
	 *
	 * @return void
	 */
	public static function create_table(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'wplcp_leads';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			email VARCHAR(150) NOT NULL,
			phone VARCHAR(30) DEFAULT '',
			message TEXT,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			stripe_intent_id VARCHAR(100) DEFAULT '',
			stripe_status VARCHAR(50) DEFAULT '',
			zapier_sent TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run pending schema upgrades when the stored DB version is older
	 * than the current plugin version.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( self::DB_VERSION_OPTION, '' );

		if ( '' === $stored ) {
			// Activated before versioning existed or fresh install without
			// activation hook (e.g. manual copy). Create table to be safe.
			self::create_table();
			update_option( self::DB_VERSION_OPTION, WPLCP_VERSION );
			return;
		}

		if ( version_compare( $stored, WPLCP_VERSION, '>=' ) ) {
			return;
		}

		if ( version_compare( $stored, '1.1.0', '<' ) && version_compare( WPLCP_VERSION, '1.1.0', '>=' ) ) {
			self::v1_1_upgrade();
		}

		update_option( self::DB_VERSION_OPTION, WPLCP_VERSION );
	}

	/**
	 * v1.1.0 upgrade: add `source` column if it does not exist.
	 *
	 * @return void
	 */
	public static function v1_1_upgrade(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wplcp_leads';

		$column = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				$table_name,
				'source'
			)
		);

		if ( empty( $column ) ) {
			// Table name comes from $wpdb->prefix, not user input.
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN source VARCHAR(100) DEFAULT ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}

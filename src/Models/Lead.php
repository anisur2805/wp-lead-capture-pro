<?php
/**
 * Lead model.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for the {prefix}wplcp_leads table.
 */
class Lead {

	/**
	 * Full table name with prefix.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wplcp_leads';
	}

	/**
	 * Insert a lead.
	 *
	 * @param array $data Column => value pairs.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$allowed = [
			'name'             => '%s',
			'email'            => '%s',
			'phone'            => '%s',
			'message'          => '%s',
			'status'           => '%s',
			'stripe_intent_id' => '%s',
			'stripe_status'    => '%s',
			'zapier_sent'      => '%d',
			'created_at'       => '%s',
		];

		$row     = array_intersect_key( $data, $allowed );
		$formats = array_values( array_intersect_key( $allowed, $row ) );

		if ( empty( $row ) ) {
			return false;
		}

		$result = $wpdb->insert( self::table(), $row, $formats );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Get a single lead by ID.
	 *
	 * @param int $id Lead ID.
	 * @return object|null
	 */
	public static function get( int $id ): ?object {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/**
	 * Get a paginated list of leads, newest first.
	 *
	 * @param int $per_page Items per page.
	 * @param int $page     Page number (1-based).
	 * @return array
	 */
	public static function get_all( int $per_page = 20, int $page = 1 ): array {
		global $wpdb;

		$table    = self::table();
		$per_page = max( 1, $per_page );
		$offset   = max( 0, ( max( 1, $page ) - 1 ) * $per_page );

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Total lead count.
	 *
	 * @return int
	 */
	public static function count(): int {
		global $wpdb;

		$table = self::table();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Update lead fields.
	 *
	 * @param int   $id   Lead ID.
	 * @param array $data Column => value pairs.
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = [
			'name'             => '%s',
			'email'            => '%s',
			'phone'            => '%s',
			'message'          => '%s',
			'status'           => '%s',
			'stripe_intent_id' => '%s',
			'stripe_status'    => '%s',
			'zapier_sent'      => '%d',
		];

		$row     = array_intersect_key( $data, $allowed );
		$formats = array_values( array_intersect_key( $allowed, $row ) );

		if ( empty( $row ) ) {
			return false;
		}

		$result = $wpdb->update( self::table(), $row, [ 'id' => $id ], $formats, [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Delete a single lead.
	 *
	 * @param int $id Lead ID.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		return (bool) $wpdb->delete( self::table(), [ 'id' => $id ], [ '%d' ] );
	}

	/**
	 * Delete multiple leads.
	 *
	 * @param array $ids Lead IDs.
	 * @return bool
	 */
	public static function delete_bulk( array $ids ): bool {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( empty( $ids ) ) {
			return false;
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$result = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return false !== $result;
	}
}

<?php
/**
 * Leads list table.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Admin;

use ClanDevs\LeadCapturePro\Models\Lead;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * WP_List_Table for captured leads.
 */
class Leads_List_Table extends \WP_List_Table {

	/**
	 * Items per page.
	 */
	const PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'lead',
				'plural'   => 'leads',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Column definitions.
	 *
	 * @return array
	 */
	public function get_columns(): array {
		return [
			'cb'            => '<input type="checkbox" />',
			'id'            => __( 'ID', 'wp-lead-capture-pro' ),
			'name'          => __( 'Name', 'wp-lead-capture-pro' ),
			'email'         => __( 'Email', 'wp-lead-capture-pro' ),
			'phone'         => __( 'Phone', 'wp-lead-capture-pro' ),
			'status'        => __( 'Status', 'wp-lead-capture-pro' ),
			'stripe_status' => __( 'Stripe Status', 'wp-lead-capture-pro' ),
			'zapier_sent'   => __( 'Zapier Sent', 'wp-lead-capture-pro' ),
			'created_at'    => __( 'Date', 'wp-lead-capture-pro' ),
		];
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions(): array {
		return [
			'delete' => __( 'Delete', 'wp-lead-capture-pro' ),
		];
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Lead row.
	 * @return string
	 */
	public function column_cb( $item ): string {
		return sprintf( '<input type="checkbox" name="lead_ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Status column with color-coded badge.
	 *
	 * @param object $item Lead row.
	 * @return string
	 */
	public function column_status( $item ): string {
		$colors = [
			'new'       => '#2271b1',
			'contacted' => '#00a32a',
			'closed'    => '#787c82',
		];

		$status = (string) $item->status;
		$color  = $colors[ $status ] ?? '#787c82';

		return sprintf(
			'<span style="display:inline-block;padding:2px 10px;border-radius:10px;color:#fff;font-size:12px;background:%s">%s</span>',
			esc_attr( $color ),
			esc_html( $status )
		);
	}

	/**
	 * Zapier sent column.
	 *
	 * @param object $item Lead row.
	 * @return string
	 */
	public function column_zapier_sent( $item ): string {
		return $item->zapier_sent
			? esc_html__( 'Yes', 'wp-lead-capture-pro' )
			: esc_html__( 'No', 'wp-lead-capture-pro' );
	}

	/**
	 * Default column renderer.
	 *
	 * @param object $item        Lead row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return esc_html( (string) ( $item->$column_name ?? '' ) );
	}

	/**
	 * Process bulk delete, then load paginated items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->process_bulk_action();

		$this->_column_headers = [ $this->get_columns(), [], [] ];

		$current_page = $this->get_pagenum();
		$total_items  = Lead::count();

		$this->items = Lead::get_all( self::PER_PAGE, $current_page );

		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total_items / self::PER_PAGE ),
			]
		);
	}

	/**
	 * Handle the bulk delete action with nonce check.
	 *
	 * @return void
	 */
	protected function process_bulk_action(): void {
		if ( 'delete' !== $this->current_action() ) {
			return;
		}

		check_admin_referer( 'bulk-' . $this->_args['plural'] );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$ids = isset( $_REQUEST['lead_ids'] ) ? array_map( 'absint', (array) $_REQUEST['lead_ids'] ) : [];

		if ( ! empty( $ids ) ) {
			Lead::delete_bulk( $ids );
		}
	}

	/**
	 * Message shown when no leads exist.
	 *
	 * @return void
	 */
	public function no_items(): void {
		esc_html_e( 'No leads captured yet.', 'wp-lead-capture-pro' );
	}
}

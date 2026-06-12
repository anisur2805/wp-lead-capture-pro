<?php
/**
 * Integration contract.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Contract implemented by all third-party integrations (Stripe, Zapier).
 */
interface Integration_Interface {

	/**
	 * Whether the integration is configured and enabled in settings.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool;

	/**
	 * Send lead data to the integration.
	 *
	 * @param array $lead_data Lead fields.
	 * @return bool True on success.
	 */
	public function send( array $lead_data ): bool;

	/**
	 * Human-readable integration name.
	 *
	 * @return string
	 */
	public function get_name(): string;
}

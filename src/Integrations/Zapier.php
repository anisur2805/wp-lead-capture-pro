<?php
/**
 * Zapier webhook integration.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Integrations;

use ClanDevs\LeadCapturePro\Admin\Settings;
use ClanDevs\LeadCapturePro\Contracts\Integration_Interface;
use ClanDevs\LeadCapturePro\Traits\Webhook_Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Sends lead data to a Zapier catch-hook URL.
 */
class Zapier implements Integration_Interface {

	use Webhook_Trait;

	/**
	 * Whether Zapier is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) Settings::get( 'zapier_enabled' ) && '' !== (string) Settings::get( 'zapier_webhook_url' );
	}

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Zapier';
	}

	/**
	 * POST lead data to the Zapier webhook as JSON.
	 *
	 * @param array $lead_data Lead fields.
	 * @return bool True if the webhook returned HTTP 200.
	 */
	public function send( array $lead_data ): bool {
		$url = (string) Settings::get( 'zapier_webhook_url' );

		if ( '' === $url ) {
			return false;
		}

		$payload = [
			'name'             => (string) ( $lead_data['name'] ?? '' ),
			'email'            => (string) ( $lead_data['email'] ?? '' ),
			'phone'            => (string) ( $lead_data['phone'] ?? '' ),
			'message'          => (string) ( $lead_data['message'] ?? '' ),
			'status'           => (string) ( $lead_data['status'] ?? 'new' ),
			'stripe_intent_id' => (string) ( $lead_data['stripe_intent_id'] ?? '' ),
			'stripe_status'    => (string) ( $lead_data['stripe_status'] ?? '' ),
			'created_at'       => (string) ( $lead_data['created_at'] ?? current_time( 'mysql' ) ),
			'source'           => 'wp-lead-capture-pro',
		];

		$response = $this->dispatch_request(
			$url,
			$payload,
			[ 'Content-Type' => 'application/json' ]
		);

		return 200 === $response['code'];
	}
}

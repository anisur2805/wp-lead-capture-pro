<?php
/**
 * Stripe Payment Intent integration.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Integrations;

use ClanDevs\LeadCapturePro\Admin\Settings;
use ClanDevs\LeadCapturePro\Contracts\Integration_Interface;
use ClanDevs\LeadCapturePro\Traits\Webhook_Trait;

defined( 'ABSPATH' ) || exit;

/**
 * Creates Stripe Payment Intents via the WordPress HTTP API.
 * No Stripe PHP SDK — direct REST calls.
 */
class Stripe implements Integration_Interface {

	use Webhook_Trait;

	/**
	 * Stripe Payment Intents endpoint.
	 */
	const API_URL = 'https://api.stripe.com/v1/payment_intents';

	/**
	 * Whether Stripe is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) Settings::get( 'stripe_enabled' ) && '' !== (string) Settings::get( 'stripe_secret_key' );
	}

	/**
	 * Integration name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'Stripe';
	}

	/**
	 * Interface alias: create a payment intent from lead data.
	 *
	 * @param array $lead_data Lead fields including payment_method_id.
	 * @return bool
	 */
	public function send( array $lead_data ): bool {
		$result = $this->create_payment_intent( (string) ( $lead_data['payment_method_id'] ?? '' ) );

		return $result['success'];
	}

	/**
	 * Create and confirm a Payment Intent.
	 *
	 * @param string $payment_method_id Stripe payment method ID from the frontend.
	 * @return array { success: bool, intent_id: string, status: string, error: string }
	 */
	public function create_payment_intent( string $payment_method_id ): array {
		if ( '' === $payment_method_id ) {
			return [
				'success'   => false,
				'intent_id' => '',
				'status'    => '',
				'error'     => __( 'Missing payment method.', 'wp-lead-capture-pro' ),
			];
		}

		$response = $this->dispatch_request(
			self::API_URL,
			[
				'amount'                                      => absint( Settings::get( 'stripe_amount', 5000 ) ),
				'currency'                                    => (string) Settings::get( 'stripe_currency', 'usd' ),
				'payment_method'                              => $payment_method_id,
				'confirm'                                     => 'true',
				'automatic_payment_methods[enabled]'          => 'true',
				'automatic_payment_methods[allow_redirects]'  => 'never',
			],
			[
				'Authorization' => 'Bearer ' . (string) Settings::get( 'stripe_secret_key' ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			]
		);

		$data = json_decode( $response['body'], true );
		$data = is_array( $data ) ? $data : [];

		if ( ! $response['success'] ) {
			return [
				'success'   => false,
				'intent_id' => (string) ( $data['error']['payment_intent']['id'] ?? '' ),
				'status'    => (string) ( $data['error']['payment_intent']['status'] ?? '' ),
				'error'     => (string) ( $data['error']['message'] ?? __( 'Stripe request failed.', 'wp-lead-capture-pro' ) ),
			];
		}

		return [
			'success'   => true,
			'intent_id' => (string) ( $data['id'] ?? '' ),
			'status'    => (string) ( $data['status'] ?? '' ),
			'error'     => '',
		];
	}

	/**
	 * Process a payment for a lead submission.
	 *
	 * @param string $payment_method_id Stripe payment method ID.
	 * @return array See create_payment_intent().
	 */
	public function process( string $payment_method_id ): array {
		return $this->create_payment_intent( $payment_method_id );
	}
}

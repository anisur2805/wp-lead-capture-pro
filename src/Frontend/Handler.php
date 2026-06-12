<?php
/**
 * AJAX form submission handler.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Frontend;

use ClanDevs\LeadCapturePro\Integrations\Stripe;
use ClanDevs\LeadCapturePro\Integrations\Zapier;
use ClanDevs\LeadCapturePro\Models\Lead;

defined( 'ABSPATH' ) || exit;

/**
 * Validates, saves and dispatches integrations for form submissions.
 */
class Handler {

	/**
	 * Constructor: hook registration.
	 */
	public function __construct() {
		add_action( 'wp_ajax_wplcp_submit', [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_wplcp_submit', [ $this, 'handle' ] );
	}

	/**
	 * Handle the AJAX form submission.
	 *
	 * @return void
	 */
	public function handle(): void {
		if ( ! check_ajax_referer( 'wplcp_submit', 'nonce', false ) ) {
			wp_send_json_error(
				[ 'message' => __( 'Security check failed. Please reload the page and try again.', 'wp-lead-capture-pro' ) ],
				403
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$name              = sanitize_text_field( wp_unslash( $_POST['wplcp_name'] ?? '' ) );
		$email             = sanitize_email( wp_unslash( $_POST['wplcp_email'] ?? '' ) );
		$phone             = sanitize_text_field( wp_unslash( $_POST['wplcp_phone'] ?? '' ) );
		$message           = sanitize_textarea_field( wp_unslash( $_POST['wplcp_message'] ?? '' ) );
		$payment_method_id = sanitize_text_field( wp_unslash( $_POST['payment_method_id'] ?? '' ) );
		// phpcs:enable

		$errors = [];

		if ( mb_strlen( $name ) < 2 ) {
			$errors['wplcp_name'] = __( 'Name is required (minimum 2 characters).', 'wp-lead-capture-pro' );
		}

		if ( ! is_email( $email ) ) {
			$errors['wplcp_email'] = __( 'A valid email address is required.', 'wp-lead-capture-pro' );
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				[
					'message' => __( 'Please correct the errors below.', 'wp-lead-capture-pro' ),
					'errors'  => $errors,
				],
				400
			);
		}

		$lead_data = [
			'name'             => $name,
			'email'            => $email,
			'phone'            => $phone,
			'message'          => $message,
			'status'           => 'new',
			'stripe_intent_id' => '',
			'stripe_status'    => '',
			'zapier_sent'      => 0,
			'created_at'       => current_time( 'mysql' ),
		];

		$stripe = new Stripe();

		if ( $stripe->is_enabled() ) {
			$result = $stripe->process( $payment_method_id );

			if ( ! $result['success'] ) {
				wp_send_json_error(
					[
						'message' => $result['error'] ?: __( 'Payment could not be processed.', 'wp-lead-capture-pro' ),
					],
					402
				);
			}

			$lead_data['stripe_intent_id'] = $result['intent_id'];
			$lead_data['stripe_status']    = $result['status'];
		}

		$lead_id = Lead::insert( $lead_data );

		if ( ! $lead_id ) {
			wp_send_json_error(
				[ 'message' => __( 'Could not save your submission. Please try again.', 'wp-lead-capture-pro' ) ],
				500
			);
		}

		$zapier = new Zapier();

		if ( $zapier->is_enabled() && $zapier->send( $lead_data ) ) {
			Lead::update( $lead_id, [ 'zapier_sent' => 1 ] );
		}

		wp_send_json_success(
			[ 'message' => __( 'Thank you, we will be in touch.', 'wp-lead-capture-pro' ) ]
		);
	}
}

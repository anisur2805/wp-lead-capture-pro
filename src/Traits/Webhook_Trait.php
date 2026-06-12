<?php
/**
 * Shared webhook dispatch logic.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Shared HTTP POST with retry logic for integrations.
 */
trait Webhook_Trait {

	/**
	 * Dispatch an HTTP POST request with up to 3 attempts.
	 *
	 * Retries on WP_Error or HTTP status >= 500, waiting 1 second
	 * between attempts.
	 *
	 * @param string $url     Target URL.
	 * @param array  $body    Request body.
	 * @param array  $headers Optional request headers.
	 * @return array { success: bool, code: int, body: string }
	 */
	protected function dispatch_request( string $url, array $body, array $headers = [] ): array {
		$max_attempts  = 3;
		$code          = 0;
		$response_body = '';
		$request_body  = $body;

		// wp_remote_post() form-encodes array bodies; JSON endpoints need
		// the payload encoded up front.
		foreach ( $headers as $header => $value ) {
			if ( 'content-type' === strtolower( $header ) && false !== stripos( $value, 'application/json' ) ) {
				$request_body = wp_json_encode( $body );
				break;
			}
		}

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$response = wp_remote_post(
				$url,
				[
					'timeout' => 15,
					'headers' => $headers,
					'body'    => $request_body,
				]
			);

			if ( is_wp_error( $response ) ) {
				$code          = 0;
				$response_body = $response->get_error_message();
			} else {
				$code          = (int) wp_remote_retrieve_response_code( $response );
				$response_body = (string) wp_remote_retrieve_body( $response );

				if ( $code < 500 ) {
					break;
				}
			}

			if ( $attempt < $max_attempts ) {
				sleep( 1 );
			}
		}

		return [
			'success' => $code >= 200 && $code < 300,
			'code'    => $code,
			'body'    => $response_body,
		];
	}
}

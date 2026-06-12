<?php
/**
 * Settings registration and access.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers plugin settings via the WordPress Settings API.
 */
class Settings {

	/**
	 * Option key holding all plugin settings.
	 */
	const OPTION_KEY = 'wplcp_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'wplcp-settings';

	/**
	 * Constructor: hook registration.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register' ] );
	}

	/**
	 * Field definitions: key => [ label, type ].
	 *
	 * @return array
	 */
	public static function get_fields(): array {
		return [
			'form_title'             => [
				'label' => __( 'Form Title', 'wp-lead-capture-pro' ),
				'type'  => 'text',
			],
			'submit_button_text'     => [
				'label' => __( 'Submit Button Text', 'wp-lead-capture-pro' ),
				'type'  => 'text',
			],
			'stripe_enabled'         => [
				'label' => __( 'Enable Stripe Payment Intent', 'wp-lead-capture-pro' ),
				'type'  => 'checkbox',
			],
			'stripe_secret_key'      => [
				'label' => __( 'Stripe Secret Key (test key)', 'wp-lead-capture-pro' ),
				'type'  => 'text',
			],
			'stripe_publishable_key' => [
				'label' => __( 'Stripe Publishable Key (test key)', 'wp-lead-capture-pro' ),
				'type'  => 'text',
			],
			'stripe_amount'          => [
				'label' => __( 'Deposit Amount (in cents, e.g. 5000 = $50.00)', 'wp-lead-capture-pro' ),
				'type'  => 'number',
			],
			'stripe_currency'        => [
				'label' => __( 'Currency', 'wp-lead-capture-pro' ),
				'type'  => 'text',
			],
			'zapier_enabled'         => [
				'label' => __( 'Enable Zapier Webhook', 'wp-lead-capture-pro' ),
				'type'  => 'checkbox',
			],
			'zapier_webhook_url'     => [
				'label' => __( 'Zapier Webhook URL', 'wp-lead-capture-pro' ),
				'type'  => 'url',
			],
		];
	}

	/**
	 * Default values for settings.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return [
			'form_title'             => __( 'Request a Demo', 'wp-lead-capture-pro' ),
			'submit_button_text'     => __( 'Submit', 'wp-lead-capture-pro' ),
			'stripe_enabled'         => 0,
			'stripe_secret_key'      => '',
			'stripe_publishable_key' => '',
			'stripe_amount'          => 5000,
			'stripe_currency'        => 'usd',
			'zapier_enabled'         => 0,
			'zapier_webhook_url'     => '',
		];
	}

	/**
	 * Register the setting, section and fields.
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => self::get_defaults(),
			]
		);

		add_settings_section(
			'wplcp_main',
			__( 'Lead Capture Settings', 'wp-lead-capture-pro' ),
			'__return_false',
			self::PAGE_SLUG
		);

		foreach ( self::get_fields() as $key => $field ) {
			add_settings_field(
				$key,
				$field['label'],
				[ $this, 'render_field' ],
				self::PAGE_SLUG,
				'wplcp_main',
				[
					'key'       => $key,
					'type'      => $field['type'],
					'label_for' => 'wplcp_' . $key,
				]
			);
		}
	}

	/**
	 * Render a single settings field.
	 *
	 * @param array $args Field args: key, type.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$key   = $args['key'];
		$type  = $args['type'];
		$value = self::get( $key );
		$id    = 'wplcp_' . $key;
		$name  = self::OPTION_KEY . '[' . $key . ']';

		switch ( $type ) {
			case 'checkbox':
				printf(
					'<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false )
				);
				break;

			case 'number':
				printf(
					'<input type="number" min="0" step="1" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'url':
				printf(
					'<input type="url" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_url( $value )
				);
				break;

			default:
				printf(
					'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value )
				);
		}
	}

	/**
	 * Sanitize all settings on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$input  = is_array( $input ) ? $input : [];
		$output = self::get_defaults();

		$output['form_title']             = sanitize_text_field( $input['form_title'] ?? $output['form_title'] );
		$output['submit_button_text']     = sanitize_text_field( $input['submit_button_text'] ?? $output['submit_button_text'] );
		$output['stripe_enabled']         = empty( $input['stripe_enabled'] ) ? 0 : 1;
		$output['stripe_secret_key']      = sanitize_text_field( $input['stripe_secret_key'] ?? '' );
		$output['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] ?? '' );
		$output['stripe_amount']          = absint( $input['stripe_amount'] ?? $output['stripe_amount'] );
		$output['stripe_currency']        = sanitize_text_field( $input['stripe_currency'] ?? $output['stripe_currency'] );
		$output['zapier_enabled']         = empty( $input['zapier_enabled'] ) ? 0 : 1;
		$output['zapier_webhook_url']     = esc_url_raw( $input['zapier_webhook_url'] ?? '' );

		return $output;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( string $key, $default = '' ) {
		$settings = wp_parse_args( (array) get_option( self::OPTION_KEY, [] ), self::get_defaults() );

		return $settings[ $key ] ?? $default;
	}
}

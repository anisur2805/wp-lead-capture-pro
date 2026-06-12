<?php
/**
 * Frontend form shortcode and assets.
 *
 * @package ClanDevs\LeadCapturePro
 */

namespace ClanDevs\LeadCapturePro\Frontend;

use ClanDevs\LeadCapturePro\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the [wplcp_form] shortcode and enqueues frontend assets.
 */
class Form {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'wplcp_form';

	/**
	 * Constructor: hook registration.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode(): void {
		add_shortcode( self::SHORTCODE, [ $this, 'render' ] );
	}

	/**
	 * Enqueue assets only when the shortcode is present on the page.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( ! $post || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		wp_enqueue_style(
			'wplcp-frontend',
			WPLCP_PLUGIN_URL . 'assets/dist/css/frontend.min.css',
			[],
			WPLCP_VERSION
		);

		wp_enqueue_script(
			'wplcp-frontend',
			WPLCP_PLUGIN_URL . 'assets/dist/js/frontend.min.js',
			[],
			WPLCP_VERSION,
			true
		);

		wp_localize_script(
			'wplcp-frontend',
			'wplcp_ajax',
			[
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wplcp_submit' ),
				'stripe_enabled' => (bool) Settings::get( 'stripe_enabled' ),
				'stripe_pk'      => (string) Settings::get( 'stripe_publishable_key' ),
			]
		);
	}

	/**
	 * Render the form HTML.
	 *
	 * @return string
	 */
	public function render(): string {
		$form_title  = (string) Settings::get( 'form_title', __( 'Request a Demo', 'wp-lead-capture-pro' ) );
		$button_text = (string) Settings::get( 'submit_button_text', __( 'Submit', 'wp-lead-capture-pro' ) );

		ob_start();
		?>
		<div class="wplcp-form-wrap">
			<h3 class="wplcp-title"><?php echo esc_html( $form_title ); ?></h3>
			<div class="wplcp-notice" style="display:none"></div>
			<form id="wplcp-form" novalidate>
				<div class="wplcp-field">
					<label for="wplcp_name"><?php esc_html_e( 'Full Name', 'wp-lead-capture-pro' ); ?> <span>*</span></label>
					<input type="text" id="wplcp_name" name="wplcp_name" required>
					<span class="wplcp-error"></span>
				</div>
				<div class="wplcp-field">
					<label for="wplcp_email"><?php esc_html_e( 'Email Address', 'wp-lead-capture-pro' ); ?> <span>*</span></label>
					<input type="email" id="wplcp_email" name="wplcp_email" required>
					<span class="wplcp-error"></span>
				</div>
				<div class="wplcp-field">
					<label for="wplcp_phone"><?php esc_html_e( 'Phone Number', 'wp-lead-capture-pro' ); ?></label>
					<input type="tel" id="wplcp_phone" name="wplcp_phone">
					<span class="wplcp-error"></span>
				</div>
				<div class="wplcp-field">
					<label for="wplcp_message"><?php esc_html_e( 'Message', 'wp-lead-capture-pro' ); ?></label>
					<textarea id="wplcp_message" name="wplcp_message" rows="4"></textarea>
				</div>
				<div id="wplcp-stripe-container" style="display:none">
					<div id="wplcp-card-element"></div>
					<span class="wplcp-error" id="wplcp-card-errors"></span>
				</div>
				<button type="submit" class="wplcp-submit"><?php echo esc_html( $button_text ); ?></button>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

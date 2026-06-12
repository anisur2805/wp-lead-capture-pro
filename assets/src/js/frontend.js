/**
 * WP Lead Capture Pro — frontend form validation and AJAX submit.
 * Vanilla JS, no jQuery.
 */
(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('wplcp-form');

		if (!form || typeof wplcp_ajax === 'undefined') {
			return;
		}

		var notice = document.querySelector('.wplcp-notice');
		var submitBtn = form.querySelector('.wplcp-submit');
		var stripe = null;
		var cardElement = null;

		var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
		var PHONE_RE = /^[0-9+\-\s()]+$/;

		/**
		 * Load Stripe.js dynamically and mount the card element.
		 */
		function initStripe() {
			if (!wplcp_ajax.stripe_enabled || !wplcp_ajax.stripe_pk) {
				return;
			}

			var script = document.createElement('script');
			script.src = 'https://js.stripe.com/v3/';
			script.onload = function () {
				stripe = window.Stripe(wplcp_ajax.stripe_pk);
				var elements = stripe.elements();
				cardElement = elements.create('card');
				cardElement.mount('#wplcp-card-element');

				document.getElementById('wplcp-stripe-container').style.display = 'block';

				cardElement.on('change', function (event) {
					var errorEl = document.getElementById('wplcp-card-errors');
					errorEl.textContent = event.error ? event.error.message : '';
				});
			};
			document.head.appendChild(script);
		}

		/**
		 * Show an inline error next to a field.
		 */
		function setFieldError(input, message) {
			var field = input.closest('.wplcp-field');
			var errorEl = field ? field.querySelector('.wplcp-error') : null;

			if (errorEl) {
				errorEl.textContent = message;
			}
		}

		/**
		 * Clear all inline field errors.
		 */
		function clearErrors() {
			form.querySelectorAll('.wplcp-error').forEach(function (el) {
				el.textContent = '';
			});
		}

		/**
		 * Show the top-level notice box.
		 */
		function showNotice(message, type) {
			if (!notice) {
				return;
			}

			notice.textContent = message;
			notice.className = 'wplcp-notice wplcp-notice-' + type;
			notice.style.display = 'block';
		}

		/**
		 * Validate the form. Returns true when valid.
		 */
		function validate() {
			clearErrors();

			var valid = true;
			var name = form.querySelector('#wplcp_name');
			var email = form.querySelector('#wplcp_email');
			var phone = form.querySelector('#wplcp_phone');

			if (name.value.trim().length < 2) {
				setFieldError(name, 'Please enter your name (minimum 2 characters).');
				valid = false;
			}

			if (!EMAIL_RE.test(email.value.trim())) {
				setFieldError(email, 'Please enter a valid email address.');
				valid = false;
			}

			if (phone.value.trim() !== '' && !PHONE_RE.test(phone.value.trim())) {
				setFieldError(phone, 'Phone may only contain digits, spaces, dashes and +.');
				valid = false;
			}

			return valid;
		}

		/**
		 * POST the form data to admin-ajax.php.
		 */
		function submitForm(paymentMethodId) {
			var data = new FormData();

			data.append('action', 'wplcp_submit');
			data.append('nonce', wplcp_ajax.nonce);
			data.append('wplcp_name', form.querySelector('#wplcp_name').value.trim());
			data.append('wplcp_email', form.querySelector('#wplcp_email').value.trim());
			data.append('wplcp_phone', form.querySelector('#wplcp_phone').value.trim());
			data.append('wplcp_message', form.querySelector('#wplcp_message').value.trim());

			if (paymentMethodId) {
				data.append('payment_method_id', paymentMethodId);
			}

			fetch(wplcp_ajax.ajax_url, {
				method: 'POST',
				credentials: 'same-origin',
				body: data
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					if (json.success) {
						form.style.display = 'none';
						showNotice(json.data.message, 'success');
					} else {
						showNotice(
							(json.data && json.data.message) || 'Something went wrong. Please try again.',
							'error'
						);

						if (json.data && json.data.errors) {
							Object.keys(json.data.errors).forEach(function (fieldId) {
								var input = form.querySelector('#' + fieldId);
								if (input) {
									setFieldError(input, json.data.errors[fieldId]);
								}
							});
						}

						submitBtn.disabled = false;
					}
				})
				.catch(function () {
					showNotice('Network error. Please try again.', 'error');
					submitBtn.disabled = false;
				});
		}

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			if (!validate()) {
				return;
			}

			submitBtn.disabled = true;

			if (stripe && cardElement) {
				stripe
					.createPaymentMethod({ type: 'card', card: cardElement })
					.then(function (result) {
						if (result.error) {
							document.getElementById('wplcp-card-errors').textContent = result.error.message;
							submitBtn.disabled = false;
							return;
						}

						submitForm(result.paymentMethod.id);
					});
			} else {
				submitForm('');
			}
		});

		initStripe();
	});
})();

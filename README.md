# WP Lead Capture Pro

A WordPress lead capture plugin with Stripe Payment Intent and Zapier webhook
integration. Built as a portfolio demo: PSR-4 architecture, WordPress APIs
only, zero external PHP dependencies, vanilla JS frontend.

> **Note:** portfolio demo plugin — not for production deployment without a
> security audit.

## Features

- `[wplcp_form]` shortcode — lead capture form (name, email, phone, message)
- Client-side validation (vanilla JS) + server-side validation
- AJAX submission with nonce verification
- Optional Stripe Payment Intent (deposit) on submission — REST API, no SDK
- Optional Zapier webhook (JSON POST with retry, max 3 attempts)
- Admin leads table (`WP_List_Table`) with pagination, bulk delete, status badges
- Settings page built on the WordPress Settings API
- Custom DB table with versioned migration path

## Requirements

- PHP 8.0+
- WordPress 6.0+
- Node 20+ and npm (build only)
- Composer (autoloading)

## Installation

1. Clone or copy this plugin into `wp-content/plugins/wp-lead-capture-pro`.
2. Build it:

   ```bash
   composer install
   npm install
   npm run build
   ```

3. Activate **WP Lead Capture Pro** in **Plugins**.

## Usage

Add the shortcode to any page or post:

```
[wplcp_form]
```

## Settings

**Lead Capture → Settings** in wp-admin:

| Setting | Notes |
| --- | --- |
| Form Title / Submit Button Text | Displayed on the frontend form |
| Enable Stripe Payment Intent | Toggles the card element on the form |
| Stripe Secret Key | Use a **test** key (`sk_test_...`) from the [Stripe dashboard](https://dashboard.stripe.com/test/apikeys) |
| Stripe Publishable Key | Test key (`pk_test_...`) — required for the card element |
| Deposit Amount | In cents, e.g. `5000` = $50.00 |
| Currency | ISO code, e.g. `usd` |
| Enable Zapier Webhook | Toggles the webhook on submission |
| Zapier Webhook URL | A "Catch Hook" URL from a Zapier Webhooks trigger |

## Development

```bash
npm run watch   # rebuild on change
npm run dev     # build, then watch
composer dump-autoload   # after adding classes under src/
```

Source assets live in `assets/src/`; Gulp outputs minified files to
`assets/dist/`.

## Repository

GitHub: `https://github.com/anisur2805/wp-lead-capture-pro`

## License

GPL-2.0-or-later

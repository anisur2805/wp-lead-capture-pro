# WP Lead Capture Pro — Scope & Feature Ownership

## 1. Problem Statement

Small agencies and freelancers need a lightweight way to capture demo/booking
requests on WordPress, optionally take a deposit via Stripe, and push the lead
into their automation stack (Zapier) — without installing a heavyweight forms
plugin plus two paid add-ons. This plugin demonstrates that flow end-to-end as
a portfolio piece: clean PSR-4 architecture, WordPress APIs only, no external
PHP dependencies.

## 2. Feature Scope (v1.0.0)

- `[wplcp_form]` shortcode rendering a lead capture form (name, email, phone, message)
- Vanilla JS client-side validation with inline errors
- AJAX submission via `admin-ajax.php` with nonce verification
- Leads stored in custom table `{prefix}wplcp_leads`
- Stripe Payment Intent created and confirmed server-side (REST API, no SDK)
- Zapier webhook POST (JSON) with retry logic (max 3 attempts)
- Admin: top-level "Lead Capture" menu, leads list table (`WP_List_Table`) with
  pagination, bulk delete and color-coded status badges
- Settings page (Settings API): Stripe keys/amount/currency, Zapier URL,
  form title, button text, per-integration enable toggles
- Gulp 4 build pipeline: SCSS → minified CSS, JS → minified JS, sourcemaps

## 3. Out of Scope (v1.0.0)

- Stripe webhooks / payment status sync after submission
- 3D Secure (SCA) challenge flows requiring redirect or extra confirmation
- Email notifications to admin or lead
- CSV export of leads
- Multiple forms / form builder
- GDPR tooling (consent checkbox, data erasure hooks)
- REST API endpoints (admin-ajax only)
- Uninstall cleanup (`uninstall.php` deliberately omitted — portfolio demo)
- Unit tests

## 4. Technical Decisions

- **PSR-4 via Composer** — autoloading without manual `require` chains; standard
  for modern WP plugin architecture; enables clean namespacing under
  `ClanDevs\LeadCapturePro`.
- **No Stripe PHP SDK** — the SDK pulls in many files for what is a single
  endpoint call. `wp_remote_post()` against `/v1/payment_intents` keeps the
  plugin dependency-free and shows command of both the Stripe REST API and the
  WordPress HTTP API.
- **Vanilla JS** — no jQuery dependency; smaller payload, works regardless of
  theme deregistering jQuery; modern `fetch` + `FormData`.
- **Custom DB table** — leads are high-volume flat records; postmeta would be
  slow and awkward. Custom table with `dbDelta()` plus a versioned migration
  path demonstrates schema evolution (`v1.1.0` adds `source` column).
- **Shared `Webhook_Trait`** — Stripe and Zapier both need HTTP POST with
  retry; trait avoids duplication while `Integration_Interface` keeps both
  integrations swappable.

## 5. Development Checklist

- [x] DB table created — verified via `DESCRIBE wp_wplcp_leads`, all 10 columns match spec
- [x] Form renders via shortcode — verified via `do_shortcode('[wplcp_form]')`
- [ ] Stripe intent created on submit — code path implemented; live verification needs a Stripe test key in settings + Stripe dashboard check
- [x] Zapier webhook fires — verified against a local mock endpoint (JSON body, 200 response, retry trait); live Zap history check needs a real catch-hook URL
- [x] Admin leads table lists entries — `Lead::get_all()`/`count()` verified with real rows
- [x] Settings save correctly — defaults and `Settings::get()` verified; option round-trip tested via `update_option`
- [x] v1.1.0 migration runs on upgrade — `v1_1_upgrade()` verified: adds `source` column, preserves existing rows, idempotent on rerun

## 6. Testing Steps

1. `composer install && npm install && npm run build` in the plugin directory.
2. Activate the plugin in **Plugins**. Confirm no PHP notices and that the
   `{prefix}wplcp_leads` table exists.
3. Go to **Lead Capture → Settings**. Fill in form title and button text,
   save, reload — values persist.
4. Create a page containing `[wplcp_form]`, view it logged out. Form renders
   with styles; CSS/JS only load on that page.
5. Submit empty form — inline errors appear for name/email; no request fires.
6. Submit valid data — success message replaces the form; lead appears under
   **Lead Capture → All Leads** with status `new`.
7. Enable Stripe, add test secret + publishable keys, set amount (e.g. 5000).
   Reload form — card element appears. Pay with `4242 4242 4242 4242`.
   Confirm intent in the Stripe test dashboard and `stripe_intent_id` /
   `stripe_status` on the lead row.
8. Enable Zapier with a catch-hook URL; submit a lead; check Zap history and
   that the lead row shows Zapier Sent = Yes.
9. Bulk-select leads in the admin table, apply **Delete**, confirm rows are gone.
10. Migration test: set option `wplcp_db_version` to `1.0.0`, bump
    `WPLCP_VERSION` to `1.1.0`, reload admin — `source` column added, no data
    loss, option updated.

## 7. Known Limitations

- Stripe flow does not handle `requires_action` (3DS) intents — those report
  their status but the card is not charged until confirmed elsewhere.
- Retry logic uses blocking `sleep(1)` inside the request cycle; a production
  build would queue retries via cron/Action Scheduler.
- Stripe secret key is stored in `wp_options` in plain text.
- Single hardcoded form; no per-instance shortcode attributes.
- `admin-ajax.php` endpoint, not REST — fine at this scale, less cacheable.
- No automated tests; manual test plan only.
- Not security-audited for production use (stated portfolio constraint).

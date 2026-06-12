# WP Lead Capture Pro — Architecture Guide

This document explains what the plugin does, what every file is responsible
for, why Stripe and Zapier are integrated, and how data flows through the
system from form render to webhook delivery.

---

## 1. What the Plugin Does (30-second version)

A visitor fills out a lead form on the frontend (`[wplcp_form]` shortcode).
On submit:

1. JavaScript validates the input and sends it via AJAX.
2. If Stripe is enabled, a card deposit is charged through a Stripe
   **Payment Intent** before the lead is saved.
3. The lead is stored in a custom database table (`{prefix}wplcp_leads`).
4. If Zapier is enabled, the lead is pushed to a Zapier **webhook** so it can
   flow into a CRM, email list, Slack, Google Sheets — whatever the site owner
   has wired up.
5. The admin can browse, paginate and bulk-delete leads in wp-admin.

Everything is configured on a single settings page; both integrations are
optional and independently toggleable.

---

## 2. File Map — What Lives Where and Why

### Entry point

| File | Responsibility |
| --- | --- |
| `wp-lead-capture-pro.php` | The only file WordPress reads directly. Declares the plugin header, defines constants (`WPLCP_VERSION`, paths), gates on minimum PHP 8.0 / WP 6.0 (shows an admin notice and bails instead of fataling), loads the Composer autoloader, registers activation/deactivation hooks, and boots `Plugin` on `plugins_loaded`. Contains **no business logic** — it is a launcher. |

### Core (`src/`)

| File | Responsibility |
| --- | --- |
| `src/Plugin.php` | Singleton bootstrap. `get_instance()` builds the object graph: `Admin\Admin`, `Frontend\Form`, `Frontend\Handler`, and runs `Installer::maybe_upgrade()`. Also loads the textdomain. Think of it as the composition root — the one place that knows which components exist. |
| `src/Installer.php` | Owns the database schema lifecycle. `activate()` creates the leads table via `dbDelta()` and stores the schema version in the `wplcp_db_version` option. `maybe_upgrade()` runs on every load and compares stored vs. current version — when the plugin is updated to 1.1.0, `v1_1_upgrade()` adds the `source` column via `ALTER TABLE` (checked against `INFORMATION_SCHEMA` first, so it is idempotent). `deactivate()` only flushes rewrite rules — **leads are never deleted on deactivation**. |

### Contracts and shared behavior

| File | Responsibility |
| --- | --- |
| `src/Contracts/Integration_Interface.php` | The contract every third-party integration must satisfy: `is_enabled()`, `send(array $lead_data)`, `get_name()`. This is what makes Stripe and Zapier interchangeable from the handler's point of view — and what a future integration (Mailchimp, Slack, …) would implement. |
| `src/Traits/Webhook_Trait.php` | Shared HTTP machinery: `dispatch_request($url, $body, $headers)`. Wraps `wp_remote_post()` with a 15-second timeout, **up to 3 attempts** (retrying on `WP_Error` or HTTP 5xx, sleeping 1s between tries), and JSON-encodes the body automatically when the `Content-Type` header is `application/json`. Both integrations use it, so retry logic lives in exactly one place. |

### Admin (`src/Admin/`)

| File | Responsibility |
| --- | --- |
| `src/Admin/Admin.php` | Registers the "Lead Capture" top-level menu (dashicon `dashicons-email-alt`) with two submenus — **All Leads** and **Settings** — and renders both pages. Enqueues a small inline stylesheet only on those two screens. |
| `src/Admin/Settings.php` | Everything settings: field definitions, defaults, Settings API registration on `admin_init`, field rendering, and `sanitize_settings()` (each field sanitized by type — `absint` for amount, `esc_url_raw` for the webhook URL, etc.). All values live in **one option**, `wplcp_settings`. Other classes read config through the static helper `Settings::get('key')` — nobody touches `get_option()` directly. |
| `src/Admin/Leads_List_Table.php` | Extends `WP_List_Table`. Columns (ID, Name, Email, Phone, Status, Stripe Status, Zapier Sent, Date), pagination at 20 rows, bulk **Delete** with nonce verification, and color-coded status badges (new = blue, contacted = green, closed = grey). Pulls data exclusively from the `Lead` model. |

### Frontend (`src/Frontend/`)

| File | Responsibility |
| --- | --- |
| `src/Frontend/Form.php` | Registers the `[wplcp_form]` shortcode and renders the form HTML (title and button text come from settings). Enqueues the built CSS/JS **only when the current page actually contains the shortcode** (`has_shortcode()` check). Passes runtime config to JS via `wp_localize_script` as the `wplcp_ajax` global: AJAX URL, nonce, whether Stripe is on, and the Stripe publishable key. |
| `src/Frontend/Handler.php` | The AJAX endpoint (`wp_ajax_wplcp_submit` + `nopriv`). This is the **orchestrator** — the only place where form, model, and both integrations meet. Pipeline: verify nonce → sanitize → validate → Stripe (if enabled) → `Lead::insert()` → Zapier (if enabled) → JSON response. Details in §4. |

### Data (`src/Models/`)

| File | Responsibility |
| --- | --- |
| `src/Models/Lead.php` | The only file that talks to the `{prefix}wplcp_leads` table. Static CRUD: `insert`, `get`, `get_all` (paginated), `count`, `update`, `delete`, `delete_bulk`. Column whitelists keep arbitrary keys out of queries; every query with user input goes through `$wpdb->prepare()`. If the storage ever changes, this is the only file to touch. |

### Integrations (`src/Integrations/`)

| File | Responsibility |
| --- | --- |
| `src/Integrations/Stripe.php` | Implements `Integration_Interface`, uses `Webhook_Trait`. `create_payment_intent()` POSTs directly to `https://api.stripe.com/v1/payment_intents` (form-encoded, `Authorization: Bearer <secret key>`) with `confirm=true` — create and charge in one call. No Stripe PHP SDK. Returns a normalized array: `success`, `intent_id`, `status`, `error`. |
| `src/Integrations/Zapier.php` | Implements `Integration_Interface`, uses `Webhook_Trait`. `send()` POSTs the lead as JSON to the user-configured "Catch Hook" URL, always stamping `"source": "wp-lead-capture-pro"` so the receiving Zap can identify origin. Success = HTTP 200. |

### Assets and build

| File | Responsibility |
| --- | --- |
| `assets/src/js/frontend.js` | Vanilla JS (no jQuery). Client-side validation (name ≥ 2 chars, email regex, optional phone format), inline error display, `fetch()` + `FormData` submission, submit-button locking. If Stripe is enabled it lazy-loads `https://js.stripe.com/v3/`, mounts the Card Element, and calls `stripe.createPaymentMethod()` on submit (see §4). |
| `assets/src/scss/frontend.scss` | Form styling: white card, 400px max-width, red errors, green/red notices, full-width under 480px. |
| `assets/dist/` | Gulp output — minified CSS/JS plus sourcemaps. These are what WordPress actually enqueues; never edit them by hand. |
| `gulpfile.js` | Gulp 4 pipeline: `scss` (Sass → compressed CSS), `js` (uglify), `watch`, `build` (both in parallel). `npm run build`. |
| `composer.json` | PSR-4 map: `ClanDevs\LeadCapturePro\` → `src/`. Zero runtime dependencies — autoloading only. |
| `package.json` | Node dev-dependencies for the Gulp pipeline. Nothing ships to the browser from npm. |

### Docs

| File | Responsibility |
| --- | --- |
| `README.md` | Install, usage, settings reference. |
| `scope.md` | Problem statement, in/out of scope, technical decisions, verified development checklist, manual test plan, known limitations. |
| `CHANGELOG.md` | Release notes (1.0.0 initial, 1.1.0 `source` column migration). |
| `languages/wp-lead-capture-pro.pot` | Translation template generated by `wp i18n make-pot`. |

---

## 3. Why Stripe? Why Zapier? Why Both?

They solve **different halves of the lead problem**:

**Stripe = lead qualification.** A free "request a demo" form attracts
tire-kickers. Requiring a small deposit (e.g. $50, refundable against the
engagement) filters for serious prospects. The plugin uses a **Payment
Intent** — Stripe's modern primitive for "charge this card once" — created
*server-side* so the amount and currency can never be tampered with from the
browser.

**Zapier = lead distribution.** A lead sitting in a WordPress table helps
nobody. Zapier is the lowest-friction way to fan a lead out to thousands of
apps (CRM, Mailchimp, Slack, Sheets) **without writing an integration for
each one**. The plugin sends one JSON POST to a Zapier "Catch Hook"; the site
owner decides what happens next inside Zapier. The plugin stays small.

**Why not the Stripe PHP SDK?** One endpoint is needed
(`POST /v1/payment_intents`). The SDK adds hundreds of files for that single
call. Direct `wp_remote_post()` keeps the plugin dependency-free and uses
WordPress's own HTTP layer (proxies, filters, timeouts respected).

**Why both behind the same interface?** `Handler` doesn't care *what* an
integration does — it only asks `is_enabled()` and acts on the result. Stripe
and Zapier are peers behind `Integration_Interface`, sharing retry logic
through `Webhook_Trait`. Adding a third integration = one new class, zero
handler rewrites.

---

## 4. How It All Integrates — Data Flow

### Render phase

```
Page contains [wplcp_form]
        │
Form.php renders HTML ── enqueues frontend.min.css / frontend.min.js
        │
wp_localize_script injects wplcp_ajax = {
    ajax_url, nonce, stripe_enabled, stripe_pk
}
        │
frontend.js boots. If stripe_enabled:
    lazy-load js.stripe.com/v3 → mount Card Element into #wplcp-card-element
```

### Submit phase

```
User clicks Submit
        │
frontend.js validates (name ≥ 2, email format, phone charset)
        │  invalid → inline errors, stop. No network call.
        │
   Stripe ON?
   ├─ yes: stripe.createPaymentMethod(card)  ← card number never leaves
   │       └─ returns payment_method_id (pm_xxx token)   Stripe's iframe
   └─ no:  skip
        │
fetch POST → admin-ajax.php?action=wplcp_submit
   (FormData: nonce, name, email, phone, message, payment_method_id?)
```

### Server phase (`Handler::handle()` — the orchestrator)

```
1. check_ajax_referer('wplcp_submit')      fail → 403 JSON
2. sanitize every field                    (sanitize_text_field, sanitize_email, …)
3. validate name + email server-side       fail → 400 JSON + per-field errors
4. Stripe::is_enabled()?
   └─ yes → Stripe::process(payment_method_id)
            POST api.stripe.com/v1/payment_intents
            (amount + currency from settings, confirm=true)
            ├─ fail → 402 JSON with Stripe's error message.
            │         LEAD IS NOT SAVED — payment is a gate.
            └─ ok   → lead_data += intent_id, status ('succeeded', …)
5. Lead::insert(lead_data)                 fail → 500 JSON
6. Zapier::is_enabled()?
   └─ yes → Zapier::send(lead_data)        JSON POST, 3 attempts via trait
            └─ HTTP 200 → Lead::update(id, zapier_sent = 1)
            └─ failure  → lead is KEPT, zapier_sent stays 0
                          (payment blocks, webhook never does)
7. wp_send_json_success("Thank you, we will be in touch.")
```

### Key ordering decisions ("vice-versa")

- **Stripe runs *before* the insert** — it is a *gate*. A declined card means
  no lead row, and the visitor sees Stripe's decline message. The Stripe
  outcome (`stripe_intent_id`, `stripe_status`) is written *into* the lead
  row, so the admin table shows payment state per lead.
- **Zapier runs *after* the insert** — it is a *side effect*. The lead is
  already safe in the database; a dead webhook must never lose a paying
  lead. The `zapier_sent` flag records whether delivery succeeded, which the
  admin table surfaces as Yes/No.
- **Stripe data flows into the Zapier payload.** The webhook JSON includes
  `stripe_intent_id` and `stripe_status`, so downstream Zaps can branch on
  payment state (e.g. paid leads → sales Slack channel, others → nurture
  list). That is the integrations talking to *each other*, mediated by the
  lead record.
- **Card data never touches the server.** The browser exchanges the card for
  a `pm_xxx` token inside Stripe's iframe; PHP only ever sees the token.
  That is what keeps a plugin like this out of PCI scope.

### Read phase (admin)

```
wp-admin → Lead Capture → All Leads
        │
Leads_List_Table::prepare_items()
        ├─ Lead::get_all(20, page)   paginated, newest first
        └─ Lead::count()             pagination totals
        │
Columns show per-lead integration state:
    Stripe Status (from intent) · Zapier Sent (yes/no) · Status badge
```

---

## 5. Mental Model Summary

```
                  ┌─────────────── reads config ───────────────┐
                  │                                            │
frontend.js ──► Handler ──► Stripe ──► api.stripe.com          │
 (validate,       │  (gate: pay before save)               Settings
  tokenize        ▼                                        (wplcp_settings,
  card)         Lead model ──► {prefix}wplcp_leads          one option)
                  │
                  └────► Zapier ──► hooks.zapier.com ──► CRM/Slack/Sheets
                         (side effect: deliver after save)
```

One orchestrator (`Handler`), one data owner (`Lead`), one config owner
(`Settings`), two pluggable integrations behind one interface, one shared
HTTP trait. Each file has a single reason to change.

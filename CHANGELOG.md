# Changelog

All notable changes to this project are documented in this file.

## [1.1.0]

### Added

- `source` column on the leads table, added via the backwards-compatible
  migration routine (`Installer::v1_1_upgrade()`). Runs automatically on
  upgrade; no data loss.

## [1.0.0]

### Added

- Initial release.
- Lead capture form via `[wplcp_form]` shortcode with vanilla JS validation
  and AJAX submission.
- Stripe Payment Intent integration (WordPress HTTP API, no SDK).
- Zapier webhook integration with retry logic.
- Admin leads table with pagination, bulk delete and status badges.
- Settings page (Stripe keys, amount, currency, Zapier URL, form text).
- Custom `{prefix}wplcp_leads` table with versioned installer.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Riwa Booking** (v1.1.2) is a custom WordPress plugin for villa reservation management. It is embedded in a WordPress installation at the parent `public/` directory, but the plugin itself lives in `wp-content/plugins/riwa-booking/`.

The WordPress environment runs locally via LocalWP (database: `local`, credentials: root/root, debug mode enabled).

## Plugin Architecture

The plugin follows a single-class architecture with the `RiwaBooking` class in `riwa-booking.php` as the entry point. All WordPress hooks, AJAX handlers, and shortcodes are registered in the constructor.

**Key files:**
- `riwa-booking.php` — Main plugin class; registers all hooks, AJAX actions, shortcode `[riwa_booking]`, and activation/deactivation callbacks
- `production-config.php` — All configuration constants (pricing defaults, security settings, guest limits, messages, colors)
- `admin/admin-page.php` — Bookings dashboard (128KB; the largest file)
- `admin/pricing-page.php` — Seasonal pricing CRUD interface
- `includes/class-riwa-pdf-admin.php` — PDF settings UI in admin
- `includes/class-riwa-pdf-ajax.php` — AJAX handlers for PDF generation and download
- `includes/class-riwa-pdf-generator.php` — PDF rendering logic using TCPDF
- `templates/booking-form.php` — Frontend booking form rendered by the shortcode
- `templates/booking-pdf.php` — PDF confirmation document template
- `assets/js/riwa-booking.js` — Frontend: Flatpickr calendar, form submission, price calculation
- `assets/js/riwa-booking-admin.js` — Admin dashboard interactions
- `assets/js/riwa-pdf-admin.js` — PDF settings UI behavior

## Database Schema

Two custom tables are created on plugin activation:

**`wp_riwa_bookings`** — Guest reservations
Columns: `id`, `guest_name`, `guest_email`, `guest_phone`, `check_in_date`, `check_out_date`, `adults_count`, `children_count`, `babies_count`, `pets_count`, `special_requests`, `total_price`, `price_per_night`, `status`, `created_at`
Status values: `pending`, `confirmed`, `cancelled`

**`wp_riwa_pricing`** — Seasonal pricing periods
Columns: `id`, `season_name`, `start_date`, `end_date`, `price_per_night`, `min_stay`, `is_active`, `created_at`
Indexed on `(start_date, end_date)`

## AJAX Endpoints

All registered via `wp_ajax_` / `wp_ajax_nopriv_` hooks:

| Action | Access | Purpose |
|---|---|---|
| `riwa_submit_booking` | Public | Submit a new reservation |
| `riwa_get_booked_dates` | Public | Fetch unavailable dates for the calendar |
| `riwa_download_pdf` | Public | Generate and download booking PDF |
| `riwa_reinstall_tcpdf` | Admin | Reinstall the TCPDF library |
| `riwa_test_client_email` | Admin | Send a test confirmation email |
| `riwa_test_admin_email` | Admin | Send a test admin notification email |

## Key Configuration (production-config.php)

- Guest limits: 1–7 guests, 1–30 night stays
- Default pricing: €150/night for 2 base guests; €20/night per additional guest
- Caching: 1-hour transient cache for booked dates
- Security: WordPress nonces (24h expiry), email/phone validation, 5MB file size limit
- PDF: High quality, compression enabled

## Frontend Integration

Embed the booking form on any page using the shortcode:
```
[riwa_booking title="Réserver votre villa" show_calendar="true"]
```

The calendar uses **Flatpickr** loaded from CDN. PDF generation uses **TCPDF** bundled at `includes/tcpdf/`.

## No Build System

There is no webpack, npm, or any build tool. CSS and JS files are plain files enqueued directly by WordPress. Edit `assets/css/` and `assets/js/` files directly.

## Development Environment

- WordPress debug mode is ON (`WP_DEBUG = true` in `wp-config.php`)
- PHP errors and the WordPress debug log are active in local development
- To test the plugin: activate it via the WordPress admin (Extensions > Riwa Booking), place the shortcode on a page, and test bookings manually
- Test emails and PDF generation via the plugin's admin settings page

## Language

All code comments, documentation, admin UI strings, and user-facing messages are in **French**.

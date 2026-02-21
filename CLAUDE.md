# CLAUDE.md — Riwa Booking Plugin

Guide de référence pour Claude Code lors du travail sur ce projet.

## Vue d'ensemble

**Riwa Booking** (v2.0.0) est un plugin WordPress sur-mesure pour la gestion complète des réservations de villas. Il couvre le cycle entier : formulaire frontend → réservation → paiement → notification WhatsApp → génération PDF.

Environnement local : **LocalWP** — base `local`, credentials `root/root`, `WP_DEBUG=true`.

---

## Architecture des fichiers

```
riwa-booking/
├── riwa-booking.php                   — Bootstrap : hooks, AJAX, shortcode, migrations DB
├── production-config.php              — Constantes (limites, sécurité, messages)
│
├── includes/
│   ├── class-riwa-installer.php       — Installation TCPDF
│   ├── class-riwa-emails.php          — Emails client/admin + handlers AJAX test
│   ├── class-riwa-pricing.php         — Calcul prix, données tarifaires
│   ├── class-riwa-booking-ajax.php    — submit_booking, get_booked_dates, download_pdf
│   ├── class-riwa-payments.php        — Module paiements & acomptes (CRUD, KPIs, CSV)
│   ├── class-riwa-notifications.php   — WhatsApp semi-auto + log notifications
│   ├── class-riwa-pdf-generator.php   — Rendu PDF via TCPDF
│   ├── class-riwa-pdf-admin.php       — UI paramètres PDF (ancien formulaire)
│   ├── class-riwa-pdf-ajax.php        — Handlers AJAX PDF
│   └── class-riwa-pdf-studio.php      — Doc Studio : layouts JSON, rendu, aperçu
│
├── admin/
│   ├── class-riwa-admin.php           — Dispatcher : menus, enqueue, sections
│   ├── class-riwa-bookings-table.php  — CRUD réservations + badge paiement
│   ├── class-riwa-pricing-table.php   — CRUD tarification (check_date_overlap ici)
│   ├── class-riwa-email-settings.php  — Save/get options email
│   ├── class-riwa-notif-settings.php  — Save/get options notifications
│   ├── class-riwa-stats.php           — Calculs statistiques + handler AJAX
│   └── partials/
│       ├── dashboard.php              — Tableau de bord (KPIs, réservations récentes)
│       ├── bookings.php               — Liste réservations (filtres, pagination)
│       ├── bookings-list.php          — idem (alias)
│       ├── planning.php               — Calendrier planning mensuel
│       ├── payments.php               — Module paiements (3 onglets)
│       ├── notifications.php          — Centre notifications
│       ├── statistics.php             — Statistiques (Pulse / Analyse / Prévision)
│       ├── settings.php               — Paramètres (6 onglets)
│       ├── email-form.php             — Formulaire config email
│       ├── pricing.php                — Formulaire + liste tarification
│       ├── notif-settings-form.php    — Formulaire templates WhatsApp
│       ├── debug.php                  — Diagnostic système
│       ├── pdf-studio.php             — Éditeur PDF drag & drop
│       ├── pdf-studio-preview.php     — Template aperçu iframe PDF
│       └── booking-detail-popup.php   — Popup détail réservation
│
├── templates/
│   ├── booking-form.php               — Frontend : formulaire shortcode
│   └── booking-pdf.php                — Template PDF (conservé, non utilisé)
│
└── assets/
    ├── css/
    │   ├── riwa-booking.css            — Frontend
    │   ├── riwa-booking-admin.css      — Admin (toutes sections)
    │   └── riwa-pdf-studio.css         — Éditeur Doc Studio
    └── js/
        ├── riwa-booking.js             — Frontend : Flatpickr, formulaire, calcul prix
        ├── riwa-booking-admin.js       — Admin : popup, filtres, tabs paramètres
        ├── riwa-pdf-admin.js           — PDF admin (ancien formulaire)
        ├── riwa-pdf-studio.js          — Doc Studio : SortableJS + interactions
        ├── riwa-payments.js            — Module paiements
        ├── riwa-notifications.js       — WhatsApp boutons + prévisualisation
        └── riwa-stats.js               — Charts Chart.js + rendu stats
```

---

## Base de données

### Tables existantes

**`wp_riwa_bookings`** — Réservations
```
id, guest_name, guest_email, guest_phone,
check_in_date, check_out_date,
adults_count, children_count, babies_count, pets_count,
special_requests, total_price, price_per_night,
deposit_percent, deposit_amount, balance_due_date,   ← ajoutés v2.0
housekeeping_status,                                  ← ménage
status, created_at
```
Statuts : `pending` | `confirmed` | `cancelled`
Housekeeping : `pending` | `in_progress` | `ready`

**`wp_riwa_pricing`** — Saisons tarifaires
```
id, season_name, start_date, end_date, price_per_night, min_stay, is_active, created_at
```

**`wp_riwa_payments`** — Paiements (v2.0)
```
id, booking_id, amount, method, payment_date, reference, note, created_at
```
Méthodes : `cash` | `transfer` | `card` | `mobile` | `platform` | `other`

**`wp_riwa_notification_log`** — Log notifications (v2.0)
```
id, booking_id, type, channel, sent_at
```
Types : `confirmation` | `reminder` | `checkin` | `review` | `custom`
Canaux : `whatsapp` | `email`

---

## Patterns de code

- **Toutes les classes sont statiques** — pas d'instanciation, appel direct `Riwa_Payments::ajax_add_payment()`
- **Nonce admin** : `riwa_admin_action`, passé via `riwa_admin_ajax.admin_nonce` en `wp_localize_script`
- **Migrations DB** : gérées dans `check_table_updates()` de `riwa-booking.php` (pattern `SHOW COLUMNS` / `SHOW TABLES`)
- **Options WordPress** : préfixe `riwa_setting_*` (général), `riwa_pdf_*` (PDF studio), `riwa_notif_*` (notifications)
- **Langue** : tout en français (commentaires, messages, UI)
- **Pas de build system** : CSS/JS édités directement

---

## Sections admin (menu latéral)

| Section | Statut | Partial |
|---|---|---|
| Tableau de bord | Actif | `dashboard.php` |
| Réservations | Actif | `bookings.php` |
| Planning | Actif | `planning.php` |
| Paiements | Actif | `payments.php` |
| Notifications | Actif | `notifications.php` |
| Statistiques | Actif | `statistics.php` |
| Factures / PDF | Actif | `riwa-pdf-settings` (sous-menu WP) |
| Paramètres | Actif | `settings.php` |

---

## Endpoints AJAX

### Public (frontend)
| Action | Handler |
|---|---|
| `riwa_submit_booking` | `Riwa_Booking_Ajax::submit_booking` |
| `riwa_get_booked_dates` | `Riwa_Booking_Ajax::get_booked_dates` |
| `riwa_download_pdf` | `Riwa_Booking_Ajax::download_pdf` |

### Admin — Réservations
| Action | Handler |
|---|---|
| `riwa_update_status` | `Riwa_Bookings_Table::handle_status_update` |
| `riwa_delete_booking` | `Riwa_Bookings_Table::handle_delete` |

### Admin — Paiements
| Action | Handler |
|---|---|
| `riwa_payments_add` | `Riwa_Payments::ajax_add_payment` |
| `riwa_payments_delete` | `Riwa_Payments::ajax_delete_payment` |
| `riwa_payments_save_deposit` | `Riwa_Payments::ajax_save_deposit_info` |
| `riwa_payments_dashboard` | `Riwa_Payments::ajax_get_dashboard` |
| `riwa_payments_get_booking` | `Riwa_Payments::ajax_get_booking_payments` |
| `riwa_payments_list` | `Riwa_Payments::ajax_get_bookings_list` |
| `riwa_payments_export_csv` | `Riwa_Payments::ajax_export_csv` |

### Admin — Notifications
| Action | Handler |
|---|---|
| `riwa_notif_get_log` | `Riwa_Notifications::ajax_get_log` |
| `riwa_notif_log_sent` | `Riwa_Notifications::ajax_log_sent` |
| `riwa_notif_preview` | `Riwa_Notifications::ajax_preview` |

### Admin — Statistiques
| Action | Handler |
|---|---|
| `riwa_stats_get_data` | `Riwa_Stats::ajax_get_stats_data` |

### Admin — PDF Studio
| Action | Handler |
|---|---|
| `riwa_studio_save_layout` | `Riwa_PDF_Studio::ajax_save_layout` |
| `riwa_studio_preview` | `Riwa_PDF_Studio::ajax_preview` |
| `riwa_studio_save_settings` | `Riwa_PDF_Studio::ajax_save_settings` |

### Admin — Emails & Divers
| Action | Handler |
|---|---|
| `riwa_test_client_email` | `Riwa_Emails::test_client_email` |
| `riwa_test_admin_email` | `Riwa_Emails::test_admin_email` |
| `riwa_reinstall_tcpdf` | `Riwa_Installer::reinstall_tcpdf` |

---

## Options WordPress importantes

```
riwa_setting_language         — fr / en
riwa_setting_currency         — EUR / USD / CHF / MAD
riwa_setting_timezone         — Europe/Paris …
riwa_setting_logo_url         — URL logo
riwa_setting_primary_color    — #couleur

riwa_email_*                  — config email (from_name, from_address, admin_address, etc.)

riwa_notif_whatsapp_enabled   — bool
riwa_notif_admin_phone        — numéro WA admin
riwa_notif_tpl_*              — templates messages WhatsApp

riwa_pdf_settings             — JSON config globale PDF (company, logo, couleurs, police)
riwa_pdf_layout_{type}        — JSON layout par type de doc (confirmation, facture, devis, contrat, rapport)
riwa_pdf_numbering            — JSON numérotation séquentielle par type
```

---

## Frontend

Shortcode : `[riwa_booking]`

Paramètres optionnels :
- `title` — Titre affiché (défaut : "Réserver votre villa")
- `show_calendar` — Affiche le calendrier (défaut : "true")

Dépendances front : **Flatpickr** (CDN), **jQuery** (WordPress)

---

## Débogage

- `WP_DEBUG = true` actif en local
- Onglet **Diagnostic** dans Paramètres : version plugin, PHP, WP, MySQL, état des tables
- Données démo injectables : onglet **Données démo** dans Paramètres (24 réservations fictives, préfixées `[DEMO]`)

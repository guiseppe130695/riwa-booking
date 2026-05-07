# Riwa Booking — Plugin de Gestion de Réservations de Villa

**Version 2.0.0** — Février 2026

Plugin WordPress sur-mesure pour la gestion complète du cycle de réservation d'une villa : du formulaire client jusqu'à la génération des documents PDF, en passant par le suivi des paiements et les notifications WhatsApp.

---

## Fonctionnalités

### Réservations (frontend)
- Formulaire responsive avec calendrier Flatpickr
- Sélection des voyageurs : adultes, enfants, bébés, animaux
- Calcul automatique du prix selon les saisons tarifaires
- Validation des disponibilités en temps réel
- Email de confirmation automatique au client et à l'administrateur
- Téléchargement du bon de réservation PDF

### Tableau de bord admin
- KPIs : total réservations, taux d'occupation, revenus du mois
- Liste des 5 dernières réservations
- Alertes : réservations en attente de confirmation
- Statistiques de statut (en attente / confirmées / annulées)

### Gestion des réservations
- Liste complète avec filtres (statut, mois, recherche texte)
- Pagination
- Actions rapides par ligne : confirmer, annuler, PDF, détails
- Popup détail : infos client, dates, prix, statut ménage, paiements, notifications WhatsApp, timeline

### Planning
- Vue calendrier mensuel (navigation mois par mois)
- Couleurs par statut : jaune (en attente), vert (confirmée), rouge (annulée)
- Blocages manuels de dates
- Prix spéciaux ponctuels
- Statistiques occupation / taux de remplissage

### Paiements & Acomptes
- KPIs temps réel : encaissé ce mois, en attente, en retard, acomptes reçus, prévision 30j
- Alertes paiements en retard avec lien direct
- Enregistrement rapide de paiement (formulaire inline)
- Liste filtrée des réservations avec statut de paiement
- 6 modes : espèces, virement, carte, mobile, plateforme, autre
- Panel détail glissant : timeline des paiements + ajout de paiement
- Modal d'ajout depuis la liste
- Gestion des acomptes : pourcentage configurable + date limite solde
- Export CSV compatible Excel/LibreOffice (encodage UTF-8, séparateur `;`)

### Notifications WhatsApp
- Boutons d'envoi semi-auto depuis le popup de chaque réservation
- 4 templates éditables : confirmation, rappel, infos check-in, demande d'avis
- 13 variables dynamiques : `{nom_client}`, `{date_arrivee}`, `{prix_total}`, etc.
- Prévisualisation du message rendu avant envoi
- Historique des notifications envoyées (timeline par réservation)
- Centre de notifications : vue du jour (arrivées / départs / séjours en cours)

### Statistiques — Pulse Board
- **Pulse** : score de santé 0-100 (occupation, confirmations, annulations, ménage) + alertes actionnables
- **Analyse** : KPIs annuels, graphique CA mensuel (Chart.js), graphique taux d'occupation, profil voyageurs
- **Prévision** : projection fin d'année, opportunités tarifaires détectées

### Paramètres
Organisés en 6 onglets :
- **Général** : langue, devise, fuseau horaire, logo, couleur principale
- **Tarification** : ajout/suppression de périodes tarifaires saisonnières
- **Email** : configuration expéditeur, templates client et admin, test d'envoi
- **Notifications** : activation WhatsApp, numéro admin, templates messages
- **Diagnostic** : infos système (PHP, WP, MySQL, version plugin, état des tables)
- **Données démo** : injection de 24 réservations fictives pour tester le planning

### Factures / PDF — Doc Studio
- Éditeur visuel drag & drop (SortableJS)
- 5 types de documents : confirmation, facture, devis, contrat, rapport
- 10 types de blocs : header, infos société, client, séjour, voyageurs, tarifs, texte, signature, QR code, pied de page
- Layout JSON par type de document, sauvegardé indépendamment
- Aperçu iframe temps réel mis à jour au drop
- Numérotation séquentielle par type (FAC-2026-001, etc.)
- Génération PDF via TCPDF

---

## Installation

### Prérequis
- WordPress 5.8+
- PHP 8.0+ (recommandé 8.1+)
- MySQL 5.7+ ou MariaDB 10.3+
- Extensions PHP : `curl`, `zip`, `gd`, `mbstring`

### Étapes
1. Copier le dossier `riwa-booking/` dans `/wp-content/plugins/`
2. Activer via **Extensions > Extensions installées**
3. Le plugin crée automatiquement les tables et insère les tarifs par défaut
4. Placer le shortcode `[riwa_booking]` sur la page de réservation

### Shortcode
```
[riwa_booking]
[riwa_booking title="Réserver votre villa" show_calendar="true"]
```

---

## Structure de la base de données

### `wp_riwa_bookings`
| Colonne | Type | Description |
|---|---|---|
| `id` | int | Clé primaire |
| `guest_name` | varchar | Nom du client |
| `guest_email` | varchar | Email |
| `guest_phone` | varchar | Téléphone |
| `check_in_date` | date | Date d'arrivée |
| `check_out_date` | date | Date de départ |
| `adults_count` | int | Adultes |
| `children_count` | int | Enfants |
| `babies_count` | int | Bébés |
| `pets_count` | int | Animaux |
| `special_requests` | text | Demandes spéciales |
| `total_price` | decimal | Prix total |
| `price_per_night` | decimal | Prix/nuit appliqué |
| `deposit_percent` | decimal | % acompte requis |
| `deposit_amount` | decimal | Montant acompte |
| `balance_due_date` | date | Échéance solde |
| `housekeeping_status` | varchar | `pending`/`in_progress`/`ready` |
| `status` | varchar | `pending`/`confirmed`/`cancelled` |
| `created_at` | datetime | Date de création |

### `wp_riwa_pricing`
| Colonne | Type | Description |
|---|---|---|
| `season_name` | varchar | Nom de la saison |
| `start_date` / `end_date` | date | Période |
| `price_per_night` | decimal | Prix/nuit |
| `min_stay` | int | Séjour minimum (nuits) |
| `is_active` | tinyint | 0 ou 1 |

### `wp_riwa_payments`
| Colonne | Type | Description |
|---|---|---|
| `booking_id` | int | Référence réservation |
| `amount` | decimal | Montant |
| `method` | varchar | `cash`/`transfer`/`card`/`mobile`/`platform`/`other` |
| `payment_date` | date | Date du paiement |
| `reference` | varchar | Référence / N° virement |
| `note` | text | Note libre |

### `wp_riwa_notification_log`
| Colonne | Type | Description |
|---|---|---|
| `booking_id` | int | Réservation concernée |
| `type` | varchar | `confirmation`/`reminder`/`checkin`/`review`/`custom` |
| `channel` | varchar | `whatsapp`/`email` |
| `sent_at` | datetime | Date/heure d'envoi |

---

## Dépendances

| Librairie | Version | Usage | Chargement |
|---|---|---|---|
| Flatpickr | Latest CDN | Calendrier frontend | CDN |
| TCPDF | Bundled | Génération PDF | Local (`includes/tcpdf/`) |
| Chart.js | 4.4.0 | Graphiques statistiques | CDN |
| SortableJS | 1.15.0 | Drag & drop PDF Studio | CDN |
| jQuery | WordPress | JS général | WordPress core |

---

## Structure REST API

```
includes/rest/
├── class-riwa-rest-api.php                      — Bootstrapper (CORS, chargement controllers)
├── class-riwa-rest-bookings-controller.php      — /bookings
├── class-riwa-rest-planning-controller.php      — /planning
├── class-riwa-rest-payments-controller.php      — /payments + /bookings/{id}/payments
├── class-riwa-rest-stats-controller.php         — /stats
├── class-riwa-rest-notifications-controller.php — /notifications
└── class-riwa-rest-pricing-controller.php       — /pricing
```

## Pas de build system

Aucun webpack, npm ou outil de compilation. Les fichiers CSS et JS sont édités directement dans `assets/` et enqueués par WordPress.

---

## REST API

Le plugin expose une WP REST API complète sous le namespace `/wp-json/riwa/v1/`.

### Authentification
- **Endpoints publics** (disponibilités, création de réservation, pricing) : aucune authentification requise
- **Endpoints admin** : WordPress Application Passwords (natif WP 5.6+)

```
Authorization: Basic base64(username:app_password)
```

Générer un mot de passe d'application dans **WordPress Admin → Utilisateurs → ton profil → Mots de passe d'application**.

### Endpoints disponibles

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/pricing` | Public | Liste des saisons tarifaires |
| POST | `/pricing/calculate` | Public | Calcul du prix pour des dates |
| GET | `/planning/availability` | Public | Dates occupées |
| POST | `/bookings` | Public | Créer une réservation |
| GET | `/bookings` | Admin | Liste filtrée des réservations |
| GET | `/bookings/{id}` | Admin | Détail d'une réservation |
| PATCH | `/bookings/{id}` | Admin | Modifier le statut |
| DELETE | `/bookings/{id}` | Admin | Supprimer |
| PATCH | `/bookings/{id}/housekeeping` | Admin | Statut ménage |
| GET | `/planning` | Admin | Données calendrier |
| POST | `/planning/blocked` | Admin | Bloquer une période |
| DELETE | `/planning/blocked/{id}` | Admin | Débloquer |
| POST | `/planning/overrides` | Admin | Override de prix |
| GET | `/payments/dashboard` | Admin | KPIs financiers |
| GET | `/bookings/{id}/payments` | Admin | Paiements d'une réservation |
| POST | `/payments` | Admin | Ajouter un paiement |
| DELETE | `/payments/{id}` | Admin | Supprimer un paiement |
| PATCH | `/bookings/{id}/deposit` | Admin | Infos acompte |
| GET | `/stats/health` | Admin | Score de santé |
| GET | `/stats/kpis` | Admin | KPIs annuels |
| GET | `/stats/forecast` | Admin | Prévisions |
| GET | `/stats/profile` | Admin | Profil voyageurs |
| GET | `/stats/alerts` | Admin | Alertes actionnables |
| GET | `/notifications/log` | Admin | Log récent |
| GET | `/bookings/{id}/notifications` | Admin | Log d'une réservation |
| POST | `/bookings/{id}/notifications` | Admin | Logger un envoi |
| POST | `/bookings/{id}/notifications/preview` | Admin | Aperçu message WhatsApp |

### CORS
Les headers CORS (`Access-Control-Allow-Origin: *`) sont ajoutés automatiquement sur toutes les routes `/riwa/*` pour permettre les appels depuis un frontend découplé (Next.js, Nuxt, SvelteKit).

---

## Changelog

### v2.1.0 — Mai 2026
- REST API complète (27 endpoints, namespace `riwa/v1`)
- Authentification via Application Passwords WordPress
- CORS activé pour les frontends découplés
- Controllers : `Riwa_REST_Bookings_Controller`, `Riwa_REST_Planning_Controller`, `Riwa_REST_Payments_Controller`, `Riwa_REST_Stats_Controller`, `Riwa_REST_Notifications_Controller`, `Riwa_REST_Pricing_Controller`

### v2.0.0 — Février 2026
- Module Paiements & Acomptes complet (KPIs, timeline, CSV export)
- Module Notifications WhatsApp semi-auto (4 templates, historique)
- Statistiques Pulse Board avec Chart.js (3 onglets actionnables)
- Planning calendrier mensuel avec blocages et prix spéciaux
- PDF Doc Studio — éditeur drag & drop (SortableJS, 5 types, 10 blocs)
- Refactorisation complète en classes statiques modulaires
- Menu admin restructuré en 8 sections
- Données démo injectables depuis les Paramètres
- Migrations DB automatiques (deposit_percent, balance_due_date, riwa_payments, riwa_notification_log)

### v1.1.2 — Décembre 2024
- Gestion séjours minimum par saison
- Amélioration calcul prix automatique
- Correction bugs validation dates

### v1.0.0
- Version initiale : formulaire de réservation, calendrier, tarification saisonnière

---

Développé pour **Riwa Villa** — Tous droits réservés.

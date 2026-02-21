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

## Pas de build system

Aucun webpack, npm ou outil de compilation. Les fichiers CSS et JS sont édités directement dans `assets/` et enqueués par WordPress.

---

## Changelog

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

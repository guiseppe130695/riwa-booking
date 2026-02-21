# Riwa Booking — Guide de Production

**Version 2.0.0** — Février 2026

---

## Prérequis serveur

| Composant | Version minimale | Recommandé |
|---|---|---|
| WordPress | 5.8 | 6.4+ |
| PHP | 8.0 | 8.2 |
| MySQL | 5.7 | 8.0 |
| MariaDB | 10.3 | 10.6 |

**Extensions PHP requises :** `curl`, `zip`, `gd`, `mbstring`, `json`

---

## Installation

### 1. Upload
```bash
cp -r riwa-booking/ /chemin/vers/wordpress/wp-content/plugins/
```
Ou compresser le dossier et l'uploader via **Extensions > Ajouter > Téléverser un plugin**.

### 2. Activation
**Extensions > Extensions installées > Riwa Booking > Activer**

Au premier démarrage, le plugin :
- Crée les tables `wp_riwa_bookings`, `wp_riwa_pricing`, `wp_riwa_payments`, `wp_riwa_notification_log`
- Insère des tarifs par défaut (saison standard)
- Installe TCPDF si absent

### 3. Shortcode frontend
Ajouter `[riwa_booking]` sur la page de réservation.

---

## Configuration initiale

### Paramètres généraux
**Riwa Bookings > Paramètres > Général**
- Langue, devise, fuseau horaire
- URL du logo (utilisé dans les PDFs et emails)
- Couleur principale

### Emails
**Riwa Bookings > Paramètres > Email**
- Email administrateur (reçoit les nouvelles réservations)
- Nom et adresse de l'expéditeur
- Templates email client et admin
- Test d'envoi depuis l'interface

### Tarification
**Riwa Bookings > Paramètres > Tarification**
- Ajouter les saisons (nom, période, prix/nuit, séjour minimum)
- Les saisons sont prioritaires sur la date (la plus spécifique s'applique)

### Notifications WhatsApp
**Riwa Bookings > Paramètres > Notifications**
- Activer les boutons WhatsApp
- Renseigner le numéro WhatsApp admin (format international : `+212661234567`)
- Personnaliser les 4 templates (confirmation, rappel, check-in, avis)

### PDF — Doc Studio
**Riwa Bookings > Factures / PDF**
- Configurer les infos société (nom, adresse, ICE, RC, email, téléphone)
- Personnaliser le layout de chaque type de document (drag & drop)
- Tester la génération PDF

---

## Sécurité en production

### wp-config.php
```php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

### Mesures intégrées dans le plugin
- Vérification de nonce WordPress sur toutes les actions admin et AJAX
- Sanitisation des entrées : `sanitize_text_field()`, `esc_url_raw()`, `sanitize_hex_color()`
- Protection SQL : requêtes préparées via `$wpdb->prepare()`
- Vérification `current_user_can('manage_options')` sur toutes les opérations admin
- Headers de sécurité via WordPress

### Permissions fichiers recommandées
```
wp-content/plugins/riwa-booking/    755
wp-content/plugins/riwa-booking/includes/tcpdf/    755
*.php    644
```

---

## Sauvegarde

### Base de données (minimum)
```bash
mysqldump -u user -p database \
  wp_riwa_bookings \
  wp_riwa_pricing \
  wp_riwa_payments \
  wp_riwa_notification_log \
  > riwa_backup_$(date +%Y%m%d).sql
```

### Options WordPress (layouts, config)
Les options du plugin sont dans la table `wp_options` avec les préfixes :
- `riwa_setting_*`
- `riwa_email_*`
- `riwa_notif_*`
- `riwa_pdf_*`

```bash
mysqldump -u user -p database wp_options \
  --where="option_name LIKE 'riwa_%'" \
  > riwa_options_backup.sql
```

---

## Mise à jour

1. Sauvegarder la base de données
2. Sauvegarder les fichiers du plugin
3. Désactiver le plugin
4. Remplacer les fichiers (ne pas supprimer `includes/tcpdf/` si déjà installé)
5. Réactiver — les migrations DB s'exécutent automatiquement au démarrage

Les migrations sont idempotentes (`SHOW COLUMNS LIKE '...'` avant chaque `ALTER TABLE`).

---

## Performance

### Optimisations incluses
- Cache transient 1h pour les dates réservées (`riwa_booked_dates`)
- Requêtes SQL avec `LEFT JOIN` pour éviter les N+1 (badges paiement dans la liste)
- Pagination côté serveur (20 réservations par page)
- JS chargé en `footer: true`

### CDN externes chargés
- Flatpickr (calendrier frontend)
- Chart.js 4.4.0 (statistiques admin)
- SortableJS 1.15.0 (PDF Studio admin)
- Google Fonts — Roboto (admin)

Si l'accès aux CDN est limité sur le serveur, ces librairies peuvent être hébergées localement — modifier les `wp_enqueue_script` dans `admin/class-riwa-admin.php`.

---

## Dépannage

### PDF ne se génère pas
1. Vérifier la présence de `includes/tcpdf/` — utiliser le bouton "Réinstaller TCPDF" dans le Diagnostic
2. Vérifier les extensions PHP : `gd`, `zip`
3. Vérifier les permissions en écriture sur le dossier temporaire PHP

### Emails non envoyés
1. Vérifier la configuration SMTP de WordPress (plugin WP Mail SMTP recommandé)
2. Tester depuis **Paramètres > Email > Test d'envoi**
3. Consulter les logs WordPress si `WP_DEBUG_LOG = true`

### Calendrier front ne s'affiche pas
1. Vérifier l'accès CDN Flatpickr depuis le serveur
2. Vérifier la console navigateur pour les erreurs JS
3. Désactiver temporairement les plugins de cache

### Tables manquantes après activation
Aller dans **Paramètres > Diagnostic** — vérifier l'état des tables. Si une table est absente, désactiver puis réactiver le plugin pour relancer les migrations.

### Sections admin vides / erreur PHP
Activer temporairement `WP_DEBUG = true` et `WP_DEBUG_LOG = true`, reproduire l'erreur, consulter `wp-content/debug.log`.

---

## Informations de contact support

- Onglet **Diagnostic** dans Paramètres : affiche les versions PHP/WP/MySQL/plugin et l'état des tables
- Données démo disponibles pour les tests : **Paramètres > Données démo > Injecter**

---

Développé pour **Riwa Villa** — Tous droits réservés.

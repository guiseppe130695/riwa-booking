# Riwa Booking — Documentation Technique Interne

Guide de compréhension approfondie du code. À lire avant une revue technique.

---

## Table des matières

1. [Bootstrap et cycle de vie](#1-bootstrap-et-cycle-de-vie)
2. [Système de réservation frontend](#2-système-de-réservation-frontend)
3. [Module Paiements](#3-module-paiements)
4. [Module Notifications WhatsApp](#4-module-notifications-whatsapp)
5. [Module Statistiques](#5-module-statistiques)
6. [PDF Doc Studio](#6-pdf-doc-studio)
7. [Sécurité et nonces](#7-sécurité-et-nonces)
8. [Migrations de base de données](#8-migrations-de-base-de-données)
9. [Calcul des prix](#9-calcul-des-prix)
10. [Questions / Réponses attendues en revue](#10-questions--réponses-attendues-en-revue)

---

## 1. Bootstrap et cycle de vie

### Point d'entrée : `riwa-booking.php`

WordPress charge le plugin via `plugins_loaded`. Le fichier `riwa-booking.php` fait trois choses dans l'ordre :

```
1. Définit les constantes (RIWA_BOOKING_VERSION, RIWA_BOOKING_PLUGIN_PATH, etc.)
2. Require_once toutes les classes (includes/ puis admin/)
3. Instancie RiwaBooking et enregistre tous les hooks via le constructeur
```

La classe `RiwaBooking` n'est instanciée qu'une fois via `add_action('plugins_loaded', ...)`. Son constructeur enregistre :
- Les hooks AJAX publics (`wp_ajax_nopriv_*`) pour le frontend
- Les hooks AJAX admin (`wp_ajax_*`) pour l'admin
- Le shortcode `[riwa_booking]`
- Les hooks d'activation/désactivation

### Activation (`register_activation_hook`)

Lors de la première activation du plugin :
```
activate()
  → create_booking_table()      — CREATE TABLE IF NOT EXISTS wp_riwa_bookings
  → create_pricing_table()      — CREATE TABLE IF NOT EXISTS wp_riwa_pricing
  → Riwa_Payments::create_table()     — CREATE TABLE IF NOT EXISTS wp_riwa_payments
  → Riwa_Notifications::create_table() — CREATE TABLE IF NOT EXISTS wp_riwa_notification_log
  → insert_default_pricing()    — INSERT saisons par défaut si table vide
```

### Mises à jour (`check_table_updates`)

Exécuté à chaque chargement via `add_action('plugins_loaded', ...)` avec une priorité tardive. Vérifie si des colonnes ou tables manquent et les ajoute. Voir section 8.

---

## 2. Système de réservation frontend

### Flux complet d'une réservation

```
Utilisateur sélectionne des dates (Flatpickr)
    → riwa-booking.js écoute onChange de Flatpickr
    → Calcule le prix via calculatePrice() en JS (appel AJAX riwa_get_booked_dates au chargement)
    → Affiche le prix estimé dans le formulaire

Utilisateur soumet le formulaire
    → riwa-booking.js intercepte le submit (preventDefault)
    → POST AJAX vers riwa_submit_booking
    → Riwa_Booking_Ajax::submit_booking() côté PHP :
        1. Vérifie nonce
        2. Sanitise toutes les entrées
        3. Vérifie que les dates ne chevauchent pas une réservation existante
        4. Calcule le prix exact via Riwa_Pricing::calculate_total_price()
        5. INSERT dans wp_riwa_bookings (statut : pending)
        6. Riwa_Emails::send_confirmation_email() → email au client
        7. Riwa_Emails::send_admin_notification_email() → email à l'admin
        8. wp_send_json_success(['booking_id' => $id])
    → JS affiche le message de succès
```

### Calcul de prix (JS vs PHP)

Le calcul JS dans `riwa-booking.js` est **approximatif** — il sert uniquement à l'affichage en temps réel pour l'UX. Le calcul autoritaire est celui de `Riwa_Pricing::calculate_total_price()` côté PHP, qui est celui enregistré en base. Les deux utilisent la même logique (chercher la saison couvrant chaque nuit), mais seul le PHP fait foi.

### Dates indisponibles

Au chargement de la page, `riwa-booking.js` appelle `riwa_get_booked_dates` qui retourne un tableau de dates au format `YYYY-MM-DD`. Ces dates sont passées à Flatpickr via `disable: [array]` pour les griser dans le calendrier. La liste inclut les réservations `pending` et `confirmed` (pas `cancelled`).

---

## 3. Module Paiements

### Modèle de données

Deux couches complémentaires :

**Couche 1 — `wp_riwa_bookings`** (colonnes ajoutées v2.0) :
- `deposit_percent` — pourcentage d'acompte requis (ex: 30)
- `deposit_amount` — montant calculé de l'acompte (ex: 450.00)
- `balance_due_date` — date limite pour payer le solde

**Couche 2 — `wp_riwa_payments`** (nouvelle table v2.0) :
- Un enregistrement par paiement effectivement reçu
- `method` : cash / transfer / card / mobile / platform / other
- Permet un historique multi-paiements par réservation

### Calcul du statut de paiement

Le statut n'est **jamais stocké** — il est toujours calculé à la volée dans `Riwa_Payments::get_payment_status($booking)` :

```
total_paid = SUM(amount) FROM wp_riwa_payments WHERE booking_id = X

Si total_paid >= total_price         → 'paid'
Si total_paid >= deposit_amount
   ET balance_due_date < aujourd'hui → 'deposit_paid' (solde en retard)
Si total_paid >= deposit_amount      → 'deposit_paid'
Si total_paid > 0                    → 'partial'
Si total_price > 0 ET balance_due_date < aujourd'hui → 'overdue'
Sinon                                → 'unpaid'
```

Ce calcul à la volée garantit que le statut est toujours cohérent avec les paiements réels, sans risque de désynchronisation.

### Optimisation N+1 dans la liste des réservations

La liste des réservations (`Riwa_Bookings_Table::get_filtered_bookings()`) aurait pu faire une requête paiement par ligne — N+1 requêtes. Pour éviter ça, la query principale fait un `LEFT JOIN` avec une sous-requête agrégée :

```sql
LEFT JOIN (
    SELECT booking_id, COALESCE(SUM(amount), 0) as amount_paid
    FROM wp_riwa_payments
    GROUP BY booking_id
) pay ON b.id = pay.booking_id
```

Une seule requête donne toutes les réservations avec leur total payé. Le statut est ensuite calculé en PHP pour chaque ligne.

### KPIs du dashboard paiements

`Riwa_Payments::get_dashboard_kpis()` retourne 6 métriques calculées en SQL :

- **Encaissé ce mois** : `SUM(amount) WHERE MONTH(payment_date) = mois courant`
- **En attente** : `SUM(total_price - amount_paid) WHERE statut != cancelled AND pas encore payé intégralement`
- **En retard** : `COUNT + SUM des réservations où balance_due_date < NOW() et solde non réglé`
- **Acomptes reçus** : `COUNT des réservations avec au moins un paiement mais pas soldées`
- **Prévision 30j** : `SUM des soldes restants sur réservations dont check_in_date < NOW() + 30 jours`

### Export CSV

`Riwa_Payments::ajax_export_csv()` envoie directement les headers HTTP pour forcer le téléchargement :
```php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="paiements-riwa-2026-02.csv"');
```
Le BOM UTF-8 (`\xEF\xBB\xBF`) est ajouté en début de fichier pour que Excel détecte correctement l'encodage. Le séparateur est `;` (standard européen, compatible LibreOffice et Excel FR).

---

## 4. Module Notifications WhatsApp

### Principe : semi-automatique sans API

Le module n'utilise **aucune API WhatsApp**. Il génère des liens `wa.me` avec le message pré-rempli encodé en URL. L'utilisateur clique, WhatsApp Web/Mobile s'ouvre avec le message prêt à envoyer. L'envoi reste manuel (un clic "Envoyer" dans WhatsApp).

Ce choix évite :
- Les coûts d'API WhatsApp Business
- La complexité d'une intégration API (webhooks, tokens, rate limits)
- La dépendance à un service tiers payant

### Rendu des templates

`Riwa_Notifications::render_template($tpl, $vars)` fait un simple `str_replace()` :

```php
// $vars = ['{nom_client}' => 'Jean Dupont', '{date_arrivee}' => '15/03/2026', ...]
return str_replace(array_keys($vars), array_values($vars), $tpl);
```

Les variables sont récupérées depuis la DB dans `get_variables($booking_id)` qui fait une seule query sur `wp_riwa_bookings` et formate les valeurs (dates en `d/m/Y`, prix avec `number_format`, etc.).

### Normalisation des numéros

`Riwa_Notifications::normalize_phone($phone)` :
1. Supprime espaces, tirets, points
2. Si commence par `0` → remplace par l'indicatif pays configuré (`riwa_notif_country_code`)
3. Si commence par `+` → garde tel quel
4. Résultat : `+212661234567` → utilisé dans `wa.me/212661234567?text=...` (le `+` est retiré)

### Log des notifications

Chaque fois qu'un utilisateur clique "Ouvrir WhatsApp" dans la modal de prévisualisation, le JS appelle `riwa_notif_log_sent` qui INSERT dans `wp_riwa_notification_log`. Ce n'est pas un accusé de réception (WhatsApp ne le permet pas sans API) — c'est un log d'intention d'envoi.

---

## 5. Module Statistiques

### Architecture : un seul endpoint AJAX

Tous les calculs passent par `riwa_stats_get_data` avec un paramètre `tab` (`pulse` / `analysis` / `forecast`) et optionnellement `year`. Cela évite de multiplier les endpoints et permet de charger uniquement les données de l'onglet actif.

### Score de santé (Pulse)

Calculé dans `Riwa_Stats::get_health_score()` sur 100 points :

| Critère | Poids | Calcul |
|---|---|---|
| Taux d'occupation du mois | 40 pts | `(nuits_reservees / jours_du_mois) × 40` |
| Taux de confirmation | 30 pts | `(confirmed / total_non_cancelled) × 30` |
| Taux de non-annulation | 20 pts | `(1 - cancelled / total) × 20` |
| Ménage à jour | 10 pts | `(ready / total_checkouts_passes) × 10` |

Grade : A (90-100) / B (75-89) / C (50-74) / D (0-49)

### Graphiques Chart.js

`riwa-stats.js` crée et détruit les instances Chart.js via `RiwaStats.buildChart()` et `RiwaStats.destroyChart()`. La destruction avant recréation est essentielle — Chart.js conserve les instances dans le DOM et une double création sur le même `<canvas>` provoque une erreur silencieuse et un graphique superposé.

---

## 6. PDF Doc Studio

### Modèle de données : layouts JSON

Chaque type de document (`confirmation`, `facture`, `devis`, `contrat`, `rapport`) a son layout stocké dans une option WordPress `riwa_pdf_layout_{type}`.

Structure du layout :
```json
{
  "rows": [
    { "id": "r1", "blocks": [{ "id": "b1", "type": "header", "span": 2 }] },
    { "id": "r2", "blocks": [
        { "id": "b2", "type": "company", "span": 1 },
        { "id": "b3", "type": "client",  "span": 1 }
    ]}
  ]
}
```

`span: 2` = pleine largeur, `span: 1` = demi-largeur. Le canvas est une grille de 2 colonnes.

### Drag & drop (SortableJS)

Deux instances Sortable distinctes :
1. **Palette → Canvas** : `group: { name: 'blocks', pull: 'clone', put: false }` — on clone le bloc depuis la palette (elle reste intacte)
2. **Canvas interne** : `group: 'rows', animation: 150` — réordonne les lignes

À chaque `onEnd` / `onAdd`, `saveLayout()` sérialise le DOM du canvas en JSON et POST vers `riwa_studio_save_layout`.

### Aperçu iframe

L'iframe utilise `srcdoc` plutôt qu'un `src` URL pour injecter directement le HTML rendu sans créer une route publique. Le HTML est généré par `Riwa_PDF_Studio::ajax_preview()` côté PHP, qui appelle `render_layout_html()`.

L'attribut `sandbox` a été **volontairement retiré** de l'iframe car il bloquait le chargement des images externes (logo). La page étant exclusivement admin WordPress, il n'y a pas de risque XSS à gérer ici.

### Génération PDF

`class-riwa-pdf-generator.php` utilise TCPDF. Le flux :
```
riwa_download_pdf (AJAX)
  → Riwa_Booking_Ajax::download_pdf()
  → Récupère la réservation depuis la DB
  → Si Riwa_PDF_Studio existe → Riwa_PDF_Studio::render_layout_html()
  → Sinon → fallback HTML statique legacy
  → TCPDF::writeHTML($html)
  → Output('booking-CONF-2026-001.pdf', 'D') — force le téléchargement
```

---

## 7. Sécurité et nonces

### Principe des nonces WordPress

Un nonce WordPress est un token à usage limité lié à une action et à l'utilisateur connecté. Il expire après 24h (deux fenêtres de 12h). `wp_verify_nonce()` retourne `false` si le token est invalide ou expiré.

### Nonces utilisés dans le plugin

| Nonce | Action protégée | Où vérifié |
|---|---|---|
| `riwa_admin_action` | Changement de statut réservation | `Riwa_Bookings_Table::handle_status_update()` |
| `riwa_pricing_nonce` | Ajout/suppression saison | `Riwa_Pricing_Table` |
| `riwa_email_nonce` | Sauvegarde config email | `Riwa_Email_Settings::handle_save()` |
| `riwa_general_nonce` | Sauvegarde paramètres généraux | `partials/settings.php` |
| `riwa_payments_nonce` | Toutes actions paiements | `Riwa_Payments::ajax_*` |
| `riwa_notif_nonce` | Actions notifications | `Riwa_Notifications::ajax_*` |
| `riwa_studio_nonce` | PDF Studio (save/preview) | `Riwa_PDF_Studio::ajax_*` |
| `riwa_stats_nonce` | Chargement stats | `Riwa_Stats::ajax_get_stats_data()` |

### Sanitisation des entrées

| Type de donnée | Fonction utilisée |
|---|---|
| Texte libre | `sanitize_text_field()` |
| Email | `sanitize_email()` |
| URL | `esc_url_raw()` |
| Couleur hex | `sanitize_hex_color()` |
| Entier | `intval()` |
| Décimal | `floatval()` |
| HTML (output) | `esc_html()`, `esc_attr()`, `esc_url()` |
| SQL | `$wpdb->prepare()` avec placeholders `%d`, `%s`, `%f` |

---

## 8. Migrations de base de données

### Pourquoi pas `register_activation_hook` pour les mises à jour ?

`register_activation_hook` ne se déclenche que lors de la **première activation**. Si le plugin est déjà installé et qu'une mise à jour ajoute une colonne, ce hook ne s'exécutera jamais.

La solution est `check_table_updates()`, appelée via `add_action('plugins_loaded', ...)`. Elle s'exécute à chaque chargement de WordPress mais ne fait des `ALTER TABLE` que si nécessaire.

### Pattern de migration idempotente

```php
// Vérifier avant d'agir — jamais de ALTER TABLE aveugle
if (empty($wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'deposit_percent'"))) {
    $wpdb->query("ALTER TABLE $table ADD COLUMN deposit_percent decimal(5,2) DEFAULT 0.00");
}

// Pour les nouvelles tables
if ($wpdb->get_var("SHOW TABLES LIKE '$payments_table'") !== $payments_table) {
    Riwa_Payments::create_table(); // utilise dbDelta()
}
```

`dbDelta()` (fonction WordPress) est utilisée pour la création de tables. Elle compare la structure existante avec la structure désirée et n'applique que les différences — idempotente par nature.

---

## 9. Calcul des prix

### `Riwa_Pricing::calculate_total_price($check_in, $check_out, $adults, $children)`

Algorithme nuit par nuit :

```
Pour chaque nuit du séjour (de check_in à check_out - 1 jour) :
    Chercher la saison active qui couvre cette date
        → SELECT * FROM wp_riwa_pricing
           WHERE is_active = 1
           AND start_date <= $nuit
           AND end_date >= $nuit
           ORDER BY start_date DESC
           LIMIT 1
    Si saison trouvée → prix = season.price_per_night
    Sinon → prix = RIWA_DEFAULT_PRICE_PER_NIGHT (production-config.php)

    Si (adults + children) > RIWA_BASE_GUESTS :
        prix += (guests - RIWA_BASE_GUESTS) × RIWA_EXTRA_GUEST_PRICE

Total = somme de toutes les nuits
```

Le `ORDER BY start_date DESC LIMIT 1` assure que la saison la plus spécifique (la plus récente dans le temps) gagne en cas de chevauchement.

### `Riwa_Pricing_Table::check_date_overlap($start, $end, $exclude_id)`

Vérifie qu'une nouvelle saison ne chevauche pas une existante :

```sql
SELECT COUNT(*) FROM wp_riwa_pricing
WHERE is_active = 1
AND id != $exclude_id
AND NOT (end_date < $start OR start_date > $end)
```

La condition `NOT (B finit avant A OU B commence après A)` est équivalente à "A et B se chevauchent". C'est le test d'intersection d'intervalles standard.

---

## 10. Questions / Réponses attendues en revue

**Q : Pourquoi les classes sont toutes statiques ?**
R : Pattern adapté au contexte WordPress. Joue le rôle de namespace organisationnel. Pas d'état à maintenir entre les appels — les données viennent de la DB à chaque requête. L'injection de dépendances serait over-engineered pour un plugin mono-tenant sans tests unitaires à isoler.

**Q : Pourquoi le statut de paiement n'est pas stocké en base ?**
R : Pour garantir la cohérence. Si on stocke `payment_status = 'paid'` et qu'on supprime ensuite un paiement, le statut devient faux. En calculant à la volée depuis la table `riwa_payments`, le statut est toujours juste sans logique de synchronisation.

**Q : Le LEFT JOIN dans get_filtered_bookings() est-il optimisé ?**
R : Oui. La sous-requête agrégée `SELECT booking_id, SUM(amount) GROUP BY booking_id` est calculée une fois pour toutes les réservations, pas une fois par ligne. L'index `KEY booking_id (booking_id)` sur `riwa_payments` assure que l'agrégation est rapide.

**Q : Pourquoi `str_replace()` pour les templates WhatsApp et pas un moteur de templates ?**
R : Les templates sont simples (13 variables, texte plat). Un moteur Twig ou Mustache serait une dépendance externe pour rendre service de substitution de variables. `str_replace()` avec des tableaux de clés/valeurs est lisible, maintenable et sans dépendance.

**Q : Comment est géré le cas où un prix spécial et une saison couvrent la même date ?**
R : Les prix spéciaux ponctuels (créés depuis le Planning) ont la priorité — ils sont vérifiés en premier dans `calculate_total_price()`. Si un prix spécial existe pour la date, il est utilisé directement sans consulter les saisons.

**Q : Le nonce `riwa_admin_action` est-il assez spécifique ?**
R : Oui. Un nonce WordPress est lié à l'action ET à l'utilisateur connecté via son session cookie. Même si le nom est générique, le token est unique par utilisateur et par fenêtre de 12h. Les autres actions sensibles ont leurs propres nonces nommés explicitement.

**Q : Pourquoi `plugins_loaded` et pas `init` pour enregistrer les hooks AJAX ?**
R : Les hooks `wp_ajax_*` doivent être enregistrés avant que WordPress traite la requête `admin-ajax.php`. `plugins_loaded` s'exécute avant `init` dans le cycle WordPress — c'est le moment le plus précoce et le plus sûr pour enregistrer ces hooks.

**Q : Comment fonctionne la migration si deux instances du plugin tournent en parallèle (ex: déploiement) ?**
R : `SHOW COLUMNS LIKE '...'` suivi de `ALTER TABLE` n'est pas atomique, mais en pratique WordPress est mono-process par requête. Le risque de double ALTER sur une colonne existante est nul car MySQL ignore `ADD COLUMN` si la colonne existe déjà avec `IF NOT EXISTS` (ou géré par le SHOW COLUMNS préalable).

---

*Documentation technique interne — Riwa Booking v2.0.0 — Février 2026*

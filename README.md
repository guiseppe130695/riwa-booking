# Riwa Booking - Plugin de Réservation de Villas

## 📋 Description

**Riwa Booking** est un plugin WordPress sur-mesure développé pour la gestion des réservations de villas. Il offre un système complet de réservation avec calendrier interactif, gestion des tarifs saisonniers et interface d'administration intuitive.

## ✨ Fonctionnalités Principales

### 🏠 Système de Réservation
- **Formulaire de réservation responsive** avec validation en temps réel
- **Calendrier interactif** avec Flatpickr pour la sélection des dates
- **Gestion des voyageurs** : adultes, enfants, bébés et animaux de compagnie
- **Validation automatique** des disponibilités et des dates
- **Calcul automatique des prix** selon les saisons tarifaires

### 💰 Gestion des Tarifs
- **Tarification saisonnière** avec périodes personnalisables
- **Prix par nuit** configurables pour chaque saison
- **Séjour minimum** par saison
- **Calcul automatique** du prix total selon la durée du séjour

### 🎛️ Interface d'Administration
- **Tableau de bord** pour visualiser toutes les réservations
- **Gestion des statuts** : en attente, confirmée, annulée
- **Page de tarification** pour configurer les saisons et prix
- **Filtres et recherche** dans les réservations

### 📧 Notifications
- **Email de confirmation** automatique aux clients
- **Gestion des erreurs** avec logs détaillés
- **Mode debug** pour le développement

## 🚀 Installation

### Prérequis
- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur

### Étapes d'Installation

1. **Télécharger le plugin**
   ```bash
   # Cloner le repository ou télécharger les fichiers
   git clone [url-du-repo]
   ```

2. **Installer dans WordPress**
   - Copier le dossier `riwa-booking` dans `/wp-content/plugins/`
   - Ou compresser le dossier et l'uploader via l'interface WordPress

3. **Activer le plugin**
   - Aller dans **Extensions > Extensions installées**
   - Activer **Riwa Booking**

4. **Configuration initiale**
   - Le plugin crée automatiquement les tables de base de données
   - Des tarifs par défaut sont insérés automatiquement

## 📖 Utilisation

### Shortcode Principal
Utilisez le shortcode `[riwa_booking]` sur n'importe quelle page ou article :

```php
[riwa_booking title="Réserver votre villa" show_calendar="true"]
```

**Paramètres disponibles :**
- `title` : Titre du formulaire (défaut : "Réserver votre villa")
- `show_calendar` : Afficher le calendrier (défaut : "true")

### Interface d'Administration

#### Tableau de Bord des Réservations
- Accès via **Riwa Bookings** dans le menu WordPress
- Visualisation de toutes les réservations
- Filtrage par statut et dates
- Actions : confirmer, annuler, supprimer

#### Gestion des Tarifs
- Accès via **Riwa Bookings > Tarification**
- Ajout/modification des saisons tarifaires
- Configuration des prix par nuit
- Définition des séjours minimum

## 🗄️ Structure de la Base de Données

### Table `wp_riwa_bookings`
```sql
- id (clé primaire)
- guest_name (nom du client)
- guest_email (email)
- guest_phone (téléphone)
- check_in_date (date d'arrivée)
- check_out_date (date de départ)
- adults_count (nombre d'adultes)
- children_count (nombre d'enfants)
- babies_count (nombre de bébés)
- pets_count (nombre d'animaux)
- special_requests (demandes spéciales)
- total_price (prix total)
- price_per_night (prix par nuit)
- status (statut de la réservation)
- created_at (date de création)
```

### Table `wp_riwa_pricing`
```sql
- id (clé primaire)
- season_name (nom de la saison)
- start_date (date de début)
- end_date (date de fin)
- price_per_night (prix par nuit)
- min_stay (séjour minimum)
- is_active (actif/inactif)
- created_at (date de création)
```

## 🎨 Personnalisation

### CSS Personnalisé
Le plugin utilise des classes CSS modulaires pour faciliter la personnalisation :

```css
/* Formulaire de réservation */
.riwa-booking-form { }
.riwa-booking-calendar { }
.riwa-booking-submit { }

/* Calendrier */
.flatpickr-calendar { }
.flatpickr-day.booked { }
.flatpickr-day.available { }
```

### JavaScript
Le plugin expose plusieurs hooks JavaScript pour les développeurs :

```javascript
// Événements disponibles
riwa_booking_submitted // Après soumission d'une réservation
riwa_booking_error     // En cas d'erreur
riwa_dates_loaded      // Après chargement des dates réservées
```

## 🔧 Configuration Avancée

### Variables d'Environnement
```php
// Activer le mode debug
define('WP_DEBUG', true);

// Logs détaillés
error_log('Riwa Booking: Message de debug');
```

### Hooks WordPress
Le plugin expose plusieurs hooks pour les développeurs :

```php
// Avant soumission d'une réservation
do_action('riwa_before_booking_submit', $booking_data);

// Après soumission d'une réservation
do_action('riwa_after_booking_submit', $booking_id, $booking_data);

// Personnalisation du calcul de prix
add_filter('riwa_calculate_price', 'custom_price_calculation', 10, 3);
```

## 🐛 Dépannage

### Problèmes Courants

1. **Le calendrier ne s'affiche pas**
   - Vérifier que jQuery est chargé
   - Contrôler la console pour les erreurs JavaScript

2. **Les réservations ne se sauvegardent pas**
   - Vérifier les permissions de la base de données
   - Contrôler les logs d'erreur PHP

3. **Les emails ne s'envoient pas**
   - Vérifier la configuration SMTP de WordPress
   - Tester avec un plugin d'email comme WP Mail SMTP

### Mode Debug
Activez le mode debug pour obtenir plus d'informations :

```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📝 Changelog

### Version 1.1.2
- ✅ Ajout de la gestion des séjours minimum par saison
- ✅ Amélioration du calcul automatique des prix
- ✅ Correction des bugs de validation des dates
- ✅ Optimisation des performances

### Version 1.1.0
- ✅ Ajout de la gestion des animaux de compagnie
- ✅ Interface d'administration améliorée
- ✅ Système de tarification saisonnière

### Version 1.0.0
- ✅ Version initiale du plugin
- ✅ Formulaire de réservation basique
- ✅ Calendrier interactif

## 🤝 Support et Contribution

### Support Technique
Pour toute question ou problème :
- Créer une issue sur le repository
- Contacter l'équipe de développement

### Contribution
Les contributions sont les bienvenues ! Pour contribuer :
1. Fork le projet
2. Créer une branche pour votre fonctionnalité
3. Commiter vos changements
4. Pousser vers la branche
5. Ouvrir une Pull Request

## 📄 Licence

Ce plugin est développé sur-mesure pour Riwa. Tous droits réservés.

## 👥 Équipe de Développement

- **Développeur** : Équipe Riwa
- **Version** : 1.1.2
- **Dernière mise à jour** : 2024

---

**Riwa Booking** - Simplifiez la gestion de vos réservations de villas avec WordPress ! 🏖️ 
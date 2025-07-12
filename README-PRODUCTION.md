# Riwa Booking - Guide de Production

## 🚀 Version Production 1.1.2

Ce guide décrit l'installation et la configuration du plugin Riwa Booking pour un environnement de production.

## 📋 Prérequis

- WordPress 5.0 ou supérieur
- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Extensions PHP requises :
  - `curl`
  - `zip`
  - `gd` (pour la génération PDF)
  - `mbstring`

## 🔧 Installation

### 1. Upload du plugin
```bash
# Copier le dossier riwa-booking dans wp-content/plugins/
cp -r riwa-booking /path/to/wordpress/wp-content/plugins/
```

### 2. Activation
- Aller dans l'administration WordPress
- Naviguer vers **Extensions > Extensions installées**
- Activer **Riwa Booking**

### 3. Configuration initiale
- Aller dans **Riwa Bookings** dans le menu principal
- Configurer les paramètres de base :
  - Informations de l'entreprise
  - Tarification par saison
  - Personnalisation PDF

## ⚙️ Configuration

### Configuration de la base de données
Le plugin crée automatiquement deux tables :
- `wp_riwa_bookings` : Réservations
- `wp_riwa_pricing` : Tarification par saison

### Configuration des emails
Modifier dans `production-config.php` :
```php
define('RIWA_BOOKING_EMAIL_FROM_NAME', 'Votre Nom');
define('RIWA_BOOKING_EMAIL_FROM_ADDRESS', 'noreply@votredomaine.com');
define('RIWA_BOOKING_ADMIN_EMAIL', 'admin@votredomaine.com');
```

### Configuration de la sécurité
- Nonces configurés pour 24h
- Validation renforcée des emails et téléphones
- Headers de sécurité automatiques
- Limites de taille de fichiers : 5MB max

## 📊 Fonctionnalités

### Réservations
- ✅ Système de réservation en 3 étapes
- ✅ Calendrier interactif avec disponibilités
- ✅ Gestion des voyageurs (adultes, enfants, bébés, animaux)
- ✅ Calcul automatique des prix
- ✅ Validation en temps réel

### Administration
- ✅ Tableau de bord avec statistiques
- ✅ Gestion des réservations (CRUD)
- ✅ Système de statuts (En attente, Confirmée, Annulée, Terminée)
- ✅ Tarification par saison
- ✅ Personnalisation PDF

### PDF
- ✅ Génération automatique de confirmations
- ✅ Personnalisation complète (logo, couleurs, polices)
- ✅ Aperçu en temps réel
- ✅ Téléchargement sécurisé

## 🔒 Sécurité

### Mesures implémentées
- ✅ Validation des nonces WordPress
- ✅ Sanitisation des données
- ✅ Validation des emails et téléphones
- ✅ Limites de taille de fichiers
- ✅ Headers de sécurité
- ✅ Protection contre les injections SQL

### Recommandations supplémentaires
```php
// Dans wp-config.php
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
```

## 📈 Performance

### Optimisations incluses
- ✅ Cache des données de tarification
- ✅ Requêtes SQL optimisées
- ✅ Scripts minifiés
- ✅ Images optimisées
- ✅ Nettoyage automatique des anciennes données

### Monitoring
- Logs d'erreurs critiques uniquement
- Métriques de performance disponibles
- Nettoyage automatique des réservations annulées

## 🛠️ Maintenance

### Nettoyage automatique
Le plugin supprime automatiquement :
- Réservations annulées de plus de 6 mois
- Fichiers temporaires PDF
- Cache expiré

### Sauvegarde recommandée
```bash
# Tables du plugin
mysqldump -u user -p database wp_riwa_bookings wp_riwa_pricing > riwa_backup.sql

# Fichiers de configuration
cp production-config.php production-config.php.backup
```

## 🐛 Dépannage

### Problèmes courants

#### PDF ne se génère pas
1. Vérifier les extensions PHP : `curl`, `zip`, `gd`
2. Vérifier les permissions du dossier `includes/tcpdf/`
3. Tester la réinstallation TCPDF dans l'admin

#### Emails non envoyés
1. Vérifier la configuration SMTP WordPress
2. Tester avec un plugin comme WP Mail SMTP
3. Vérifier les logs d'erreur

#### Calendrier ne s'affiche pas
1. Vérifier que jQuery est chargé
2. Vérifier la console pour les erreurs JavaScript
3. Désactiver les plugins de cache temporairement

### Logs d'erreur
En cas de problème, activer temporairement les logs :
```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📞 Support

### Informations de version
- **Version** : 1.1.2
- **Dernière mise à jour** : Décembre 2024
- **Compatibilité WordPress** : 5.0+
- **Compatibilité PHP** : 7.4+

### Contact
Pour le support technique :
- Email : support@riwa-villa.com
- Documentation : [URL de la documentation]

## 🔄 Mises à jour

### Procédure de mise à jour
1. Sauvegarder les données
2. Désactiver le plugin
3. Remplacer les fichiers
4. Réactiver le plugin
5. Vérifier la configuration

### Changelog
- **1.1.2** : Optimisations production, suppression logs, sécurité renforcée
- **1.1.1** : Amélioration interface admin, gestion des voyageurs
- **1.1.0** : Système PDF, personnalisation avancée
- **1.0.0** : Version initiale

## 📝 Licence

Ce plugin est développé spécifiquement pour Riwa Villa.
Tous droits réservés.

---

**Note** : Ce plugin est optimisé pour la production avec toutes les fonctionnalités de debug désactivées pour de meilleures performances. 
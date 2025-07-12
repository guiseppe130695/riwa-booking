<?php
/**
 * Configuration de production pour Riwa Booking
 * 
 * Ce fichier contient les paramètres optimisés pour la production
 * Il doit être inclus dans le fichier principal du plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Configuration de production
define('RIWA_BOOKING_PRODUCTION', true);

// Désactiver les fonctionnalités de debug en production
if (RIWA_BOOKING_PRODUCTION) {
    // Supprimer les logs de debug
    if (!function_exists('riwa_production_error_log')) {
        function riwa_production_error_log($message) {
            // Ne rien faire en production
            return;
        }
    }
    
    // Remplacer error_log par notre fonction silencieuse
    if (!function_exists('riwa_override_error_log')) {
        function riwa_override_error_log($message) {
            // Seulement logger les erreurs critiques
            if (strpos($message, 'CRITICAL') !== false) {
                error_log($message);
            }
        }
    }
}

// Optimisations de performance
define('RIWA_BOOKING_CACHE_ENABLED', true);
define('RIWA_BOOKING_CACHE_DURATION', 3600); // 1 heure

// Configuration de sécurité
define('RIWA_BOOKING_SECURITY_NONCE_EXPIRY', 24 * 60 * 60); // 24 heures
define('RIWA_BOOKING_MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('RIWA_BOOKING_ALLOWED_FILE_TYPES', array('jpg', 'jpeg', 'png', 'gif'));

// Configuration des emails
define('RIWA_BOOKING_EMAIL_FROM_NAME', 'Riwa Villa');
define('RIWA_BOOKING_EMAIL_FROM_ADDRESS', 'noreply@riwa-villa.com');

// Configuration PDF
define('RIWA_BOOKING_PDF_QUALITY', 'high');
define('RIWA_BOOKING_PDF_COMPRESSION', true);

// Configuration de la base de données
define('RIWA_BOOKING_DB_CHARSET', 'utf8mb4');
define('RIWA_BOOKING_DB_COLLATE', 'utf8mb4_unicode_ci');

// Configuration des limites
define('RIWA_BOOKING_MAX_GUESTS', 12);
define('RIWA_BOOKING_MAX_PETS', 2);
define('RIWA_BOOKING_MIN_STAY', 1);
define('RIWA_BOOKING_MAX_STAY', 30);

// Configuration des prix
define('RIWA_BOOKING_DEFAULT_PRICE', 150.00);
define('RIWA_BOOKING_GUEST_SURCHARGE', 20.00);
define('RIWA_BOOKING_BASE_GUESTS', 2);

// Configuration des notifications
define('RIWA_BOOKING_ADMIN_EMAIL', 'admin@riwa-villa.com');
define('RIWA_BOOKING_NOTIFICATION_ENABLED', true);

// Configuration du cache
if (RIWA_BOOKING_CACHE_ENABLED) {
    // Fonction pour mettre en cache les données
    function riwa_cache_set($key, $data, $duration = RIWA_BOOKING_CACHE_DURATION) {
        $cache_key = 'riwa_booking_' . $key;
        set_transient($cache_key, $data, $duration);
    }
    
    // Fonction pour récupérer les données du cache
    function riwa_cache_get($key) {
        $cache_key = 'riwa_booking_' . $key;
        return get_transient($cache_key);
    }
    
    // Fonction pour supprimer le cache
    function riwa_cache_delete($key) {
        $cache_key = 'riwa_booking_' . $key;
        delete_transient($cache_key);
    }
}

// Configuration de sécurité renforcée
function riwa_booking_security_headers() {
    if (!is_admin()) {
        // Headers de sécurité pour les pages publiques
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
    }
}

// Configuration de nettoyage automatique
function riwa_booking_cleanup_old_data() {
    global $wpdb;
    
    // Supprimer les réservations annulées de plus de 6 mois
    $table_name = $wpdb->prefix . 'riwa_bookings';
    $six_months_ago = date('Y-m-d', strtotime('-6 months'));
    
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE status = 'cancelled' AND created_at < %s",
        $six_months_ago
    ));
}

// Configuration de validation renforcée
function riwa_booking_validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

function riwa_booking_validate_phone($phone) {
    // Validation basique du téléphone français
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

// Configuration de formatage
function riwa_booking_format_price($price) {
    return number_format($price, 2, ',', ' ') . ' €';
}

function riwa_booking_format_date($date) {
    return date('d/m/Y', strtotime($date));
}

// Configuration des messages d'erreur
define('RIWA_BOOKING_ERROR_MESSAGES', array(
    'invalid_email' => 'Adresse email invalide',
    'invalid_phone' => 'Numéro de téléphone invalide',
    'invalid_dates' => 'Dates de séjour invalides',
    'dates_unavailable' => 'Les dates sélectionnées ne sont pas disponibles',
    'too_many_guests' => 'Le nombre maximum de voyageurs est de ' . RIWA_BOOKING_MAX_GUESTS,
    'too_many_pets' => 'Le nombre maximum d\'animaux est de ' . RIWA_BOOKING_MAX_PETS,
    'min_stay_required' => 'Séjour minimum de ' . RIWA_BOOKING_MIN_STAY . ' nuit(s) requis',
    'max_stay_exceeded' => 'Séjour maximum de ' . RIWA_BOOKING_MAX_STAY . ' nuits',
    'booking_failed' => 'Erreur lors de la création de la réservation',
    'pdf_generation_failed' => 'Erreur lors de la génération du PDF'
));

// Configuration des messages de succès
define('RIWA_BOOKING_SUCCESS_MESSAGES', array(
    'booking_created' => 'Votre réservation a été créée avec succès !',
    'pdf_downloaded' => 'PDF téléchargé avec succès',
    'settings_saved' => 'Paramètres sauvegardés avec succès',
    'pricing_updated' => 'Tarification mise à jour avec succès'
));

// Configuration de l'interface utilisateur
define('RIWA_BOOKING_UI_CONFIG', array(
    'show_prices' => true,
    'show_availability' => true,
    'show_calendar' => true,
    'show_travelers' => true,
    'show_special_requests' => true,
    'show_pdf_download' => true,
    'show_email_confirmation' => true
));

// Configuration des saisons par défaut
define('RIWA_BOOKING_DEFAULT_SEASONS', array(
    array(
        'name' => 'Basse saison',
        'start_date' => '2024-01-01',
        'end_date' => '2024-12-31',
        'price_per_night' => 150.00,
        'min_stay' => 1
    )
));

// Configuration des polices PDF
define('RIWA_BOOKING_PDF_FONTS', array(
    'helvetica' => 'Helvetica',
    'times' => 'Times',
    'courier' => 'Courier',
    'dejavusans' => 'DejaVu Sans',
    'dejavuserif' => 'DejaVu Serif'
));

// Configuration des couleurs par défaut
define('RIWA_BOOKING_DEFAULT_COLORS', array(
    'primary' => '#000000',
    'secondary' => '#666666',
    'success' => '#27ae60',
    'warning' => '#f39c12',
    'error' => '#e74c3c',
    'info' => '#3498db'
));

// Configuration des icônes
define('RIWA_BOOKING_ICONS', array(
    'calendar' => 'dashicons-calendar-alt',
    'users' => 'dashicons-groups',
    'pets' => 'dashicons-heart',
    'email' => 'dashicons-email',
    'phone' => 'dashicons-phone',
    'pdf' => 'dashicons-pdf',
    'settings' => 'dashicons-admin-generic',
    'pricing' => 'dashicons-money-alt',
    'bookings' => 'dashicons-calendar',
    'stats' => 'dashicons-chart-bar'
));

// Configuration des statuts de réservation
define('RIWA_BOOKING_STATUSES', array(
    'pending' => array(
        'label' => 'En attente',
        'color' => '#f39c12',
        'icon' => 'dashicons-clock'
    ),
    'confirmed' => array(
        'label' => 'Confirmée',
        'color' => '#27ae60',
        'icon' => 'dashicons-yes-alt'
    ),
    'cancelled' => array(
        'label' => 'Annulée',
        'color' => '#e74c3c',
        'icon' => 'dashicons-no-alt'
    ),
    'completed' => array(
        'label' => 'Terminée',
        'color' => '#3498db',
        'icon' => 'dashicons-yes'
    )
));

// Configuration des permissions
define('RIWA_BOOKING_CAPABILITIES', array(
    'manage_bookings' => 'manage_riwa_bookings',
    'manage_pricing' => 'manage_riwa_pricing',
    'manage_settings' => 'manage_riwa_settings',
    'view_reports' => 'view_riwa_reports'
));

// Configuration des hooks de nettoyage
add_action('wp_scheduled_delete', 'riwa_booking_cleanup_old_data');

// Configuration des headers de sécurité
add_action('send_headers', 'riwa_booking_security_headers');

// Configuration de l'optimisation des performances
if (RIWA_BOOKING_PRODUCTION) {
    // Désactiver les scripts de debug
    add_action('wp_enqueue_scripts', function() {
        wp_dequeue_script('wp-embed');
    }, 100);
    
    // Optimiser les requêtes de base de données
    add_action('pre_get_posts', function($query) {
        if (!is_admin() && $query->is_main_query()) {
            $query->set('no_found_rows', true);
        }
    });
} 
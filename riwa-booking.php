<?php
/**
 * Plugin Name: Riwa Booking
 * Plugin URI: https://votresite.com
 * Description: Plugin de réservation de villas, développé sur-mesure pour Riwa.
 * Version: 1.1.2
 * Author: Ton Nom ou Riwa Team
 * Author URI: https://votresite.com
 * Text Domain: riwavilla-booking
 * Domain Path: /languages
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Définition des constantes
define('RIWA_BOOKING_VERSION', '1.1.2');
define('RIWA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RIWA_BOOKING_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Classe principale du plugin
class RiwaBooking {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_riwa_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_riwa_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_riwa_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('wp_ajax_nopriv_riwa_get_booked_dates', array($this, 'get_booked_dates'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('riwa_booking', array($this, 'booking_shortcode'));
        
        // Activation et désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Vérifier et mettre à jour la structure des tables si nécessaire
        $this->check_table_updates();
    }
    
    public function activate() {
        // Création des tables
        $this->create_booking_table();
        $this->create_pricing_table();
        
        // Vider le cache des permaliens
        flush_rewrite_rules();
        
        // Ajouter une option pour vérifier l'activation
        add_option('riwa_booking_activated', true);
        
        // Log d'activation
        error_log('Riwa Booking: Plugin activé avec succès');
    }
    
    public function deactivate() {
        // Nettoyage lors de la désactivation
        // Supprimer l'option d'activation
        delete_option('riwa_booking_activated');
        
        // Vider le cache des permaliens
        flush_rewrite_rules();
        
        // Log de désactivation
        error_log('Riwa Booking: Plugin désactivé');
    }
    
    /**
     * Mettre à jour la structure des tables si nécessaire
     */
    public function check_table_updates() {
        global $wpdb;
        
        // Vérifier si la colonne min_stay existe dans la table de tarification
        $pricing_table = $wpdb->prefix . 'riwa_pricing';
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'min_stay'");
        
        if (empty($column_exists)) {
            // Ajouter la colonne min_stay si elle n'existe pas
            $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN min_stay int(3) DEFAULT 1 AFTER price_per_night");
            error_log('Riwa Booking: Colonne min_stay ajoutée à la table de tarification');
        }
        
        // Vérifier si les colonnes de prix existent dans la table de réservations
        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        $price_columns = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'total_price'");
        
        if (empty($price_columns)) {
            // Ajouter les colonnes de prix si elles n'existent pas
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN total_price decimal(10,2) DEFAULT 0.00 AFTER special_requests");
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN price_per_night decimal(10,2) DEFAULT 0.00 AFTER total_price");
            error_log('Riwa Booking: Colonnes de prix ajoutées à la table de réservations');
        }
    }
    
    private function create_booking_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'riwa_bookings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            guest_name varchar(100) NOT NULL,
            guest_email varchar(100) NOT NULL,
            guest_phone varchar(20) NOT NULL,
            check_in_date date NOT NULL,
            check_out_date date NOT NULL,
            adults_count int(3) NOT NULL DEFAULT 1,
            children_count int(3) NOT NULL DEFAULT 0,
            babies_count int(3) NOT NULL DEFAULT 0,
            pets_count int(3) NOT NULL DEFAULT 0,
            special_requests text,
            total_price decimal(10,2) DEFAULT 0.00,
            price_per_night decimal(10,2) DEFAULT 0.00,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function create_pricing_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'riwa_pricing';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            season_name varchar(100) NOT NULL,
            start_date date NOT NULL,
            end_date date NOT NULL,
            price_per_night decimal(10,2) NOT NULL,
            min_stay int(3) DEFAULT 1,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date_range (start_date, end_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insérer des prix par défaut si la table est vide
        $existing_prices = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($existing_prices == 0) {
            $this->insert_default_pricing();
        }
    }
    
    private function insert_default_pricing() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_pricing';
        
        // Prix par défaut
        $default_prices = array(
            array(
                'season_name' => 'Basse saison',
                'start_date' => '2024-01-01',
                'end_date' => '2024-12-31',
                'price_per_night' => 150.00,
                'min_stay' => 1
            )
        );
        
        foreach ($default_prices as $price) {
            $wpdb->insert($table_name, $price);
        }
    }
    
    public function enqueue_scripts() {
        // S'assurer que jQuery est chargé
        wp_enqueue_script('jquery');
        
        // Inclusion de Flatpickr pour le calendrier
        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', array('flatpickr'), '4.6.13', true);
        
        // Scripts personnalisés
        wp_enqueue_style('riwa-booking-style', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking.css', array(), RIWA_BOOKING_VERSION);
        wp_enqueue_script('riwa-booking-script', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking.js', array('jquery', 'flatpickr'), RIWA_BOOKING_VERSION, true);
        
        // Localisation pour AJAX
        $pricing_data = $this->get_pricing_data();
        wp_localize_script('riwa-booking-script', 'riwa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('riwa_booking_nonce'),
            'debug' => WP_DEBUG, // Ajouter le mode debug
            'pricing' => $pricing_data // Données de tarification
        ));
        
        // Log pour le débogage
        if (WP_DEBUG) {
            error_log('Riwa Booking: Données de tarification passées au JS: ' . print_r($pricing_data, true));
        }
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('toplevel_page_riwa-bookings' !== $hook) {
            return;
        }
        
        wp_enqueue_style('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking-admin.css', array(), RIWA_BOOKING_VERSION);
        wp_enqueue_script('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking-admin.js', array('jquery'), RIWA_BOOKING_VERSION, true);
    }
    
    public function admin_menu() {
        add_menu_page(
            'Riwa Bookings',
            'Riwa Bookings',
            'manage_options',
            'riwa-bookings',
            array($this, 'admin_page'),
            'dashicons-calendar-alt',
            30
        );
        
        // Sous-menu pour la tarification
        add_submenu_page(
            'riwa-bookings',
            'Tarification',
            'Tarification',
            'manage_options',
            'riwa-pricing',
            array($this, 'pricing_page')
        );
    }
    
    public function admin_page() {
        include RIWA_BOOKING_PLUGIN_PATH . 'admin/admin-page.php';
    }
    
    public function pricing_page() {
        include RIWA_BOOKING_PLUGIN_PATH . 'admin/pricing-page.php';
    }
    
    public function booking_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Réserver votre villa',
            'show_calendar' => 'true'
        ), $atts);
        
        ob_start();
        include RIWA_BOOKING_PLUGIN_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }
    
    public function submit_booking() {
        // Log de débogage pour voir les données reçues
        if (WP_DEBUG) {
            error_log('Riwa Booking: Données POST reçues: ' . print_r($_POST, true));
        }
        
        // Vérification des données POST
        if (!isset($_POST['nonce'])) {
            if (WP_DEBUG) {
                error_log('Riwa Booking: Nonce manquant dans les données POST');
            }
            wp_send_json_error('Données manquantes');
            return;
        }

        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
            if (WP_DEBUG) {
                error_log('Riwa Booking: Échec de vérification du nonce');
            }
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        // Validation des données
        $guest_first_name = isset($_POST['guest_first_name']) ? sanitize_text_field($_POST['guest_first_name']) : '';
        $guest_last_name = isset($_POST['guest_last_name']) ? sanitize_text_field($_POST['guest_last_name']) : '';
        $guest_name = trim($guest_first_name . ' ' . $guest_last_name);
        $guest_email = isset($_POST['guest_email']) ? sanitize_email($_POST['guest_email']) : '';
        $guest_phone = isset($_POST['guest_phone']) ? sanitize_text_field($_POST['guest_phone']) : '';
        $check_in_date = isset($_POST['check_in_date']) ? sanitize_text_field($_POST['check_in_date']) : '';
        $check_out_date = isset($_POST['check_out_date']) ? sanitize_text_field($_POST['check_out_date']) : '';
        
        // Nouveaux champs pour les voyageurs
        $adults_count = isset($_POST['adults_count']) ? intval($_POST['adults_count']) : 1;
        $children_count = isset($_POST['children_count']) ? intval($_POST['children_count']) : 0;
        $babies_count = isset($_POST['babies_count']) ? intval($_POST['babies_count']) : 0;
        $pets_count = isset($_POST['pets_count']) ? intval($_POST['pets_count']) : 0;
        
        $special_requests = isset($_POST['special_requests']) ? sanitize_textarea_field($_POST['special_requests']) : '';
        
        // Validation basique
        if (empty($guest_first_name) || empty($guest_last_name) || empty($guest_email) || empty($guest_phone) || 
            empty($check_in_date) || empty($check_out_date)) {
            wp_send_json_error('Veuillez remplir tous les champs obligatoires.');
            return;
        }
        
        // Validation du nombre de voyageurs
        $total_travelers = $adults_count + $children_count + $babies_count;
        if ($adults_count < 1) {
            wp_send_json_error('Il doit y avoir au moins un adulte.');
            return;
        }
        if ($total_travelers > 12) {
            wp_send_json_error('Le nombre total de voyageurs ne peut pas dépasser 12 personnes.');
            return;
        }
        if ($pets_count > 2) {
            wp_send_json_error('Le nombre maximum d\'animaux de compagnie est de 2.');
            return;
        }
        
        // Vérification des dates
        try {
            $check_in = new DateTime($check_in_date);
            $check_out = new DateTime($check_out_date);
            $today = new DateTime();
            
            if ($check_in < $today) {
                wp_send_json_error('La date d\'arrivée ne peut pas être dans le passé.');
                return;
            }
            
            if ($check_out <= $check_in) {
                wp_send_json_error('La date de départ doit être après la date d\'arrivée.');
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('Format de date invalide.');
            return;
        }
        
        // Log des données avant insertion
        if (WP_DEBUG) {
            error_log('Riwa Booking: Données à insérer: ' . print_r(array(
                'guest_name' => $guest_name,
                'guest_email' => $guest_email,
                'guest_phone' => $guest_phone,
                'check_in_date' => $check_in_date,
                'check_out_date' => $check_out_date,
                'adults_count' => $adults_count,
                'children_count' => $children_count,
                'babies_count' => $babies_count,
                'pets_count' => $pets_count,
                'special_requests' => $special_requests,
                'status' => 'pending'
            ), true));
        }
        
        // Sauvegarde en base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_bookings';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'guest_name' => $guest_name,
                'guest_email' => $guest_email,
                'guest_phone' => $guest_phone,
                'check_in_date' => $check_in_date,
                'check_out_date' => $check_out_date,
                'adults_count' => $adults_count,
                'children_count' => $children_count,
                'babies_count' => $babies_count,
                'pets_count' => $pets_count,
                'special_requests' => $special_requests,
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            $error_message = 'Erreur lors de la sauvegarde de la réservation.';
            if ($wpdb->last_error) {
                error_log('Riwa Booking DB Error: ' . $wpdb->last_error);
                $error_message .= ' Erreur technique: ' . $wpdb->last_error;
            }
            wp_send_json_error($error_message);
            return;
        }
        
        // Log de succès
        if (WP_DEBUG) {
            error_log('Riwa Booking: Réservation insérée avec succès. ID: ' . $wpdb->insert_id);
        }
        
        // Envoi d'email de confirmation
        try {
            $this->send_confirmation_email($guest_email, $guest_name, $check_in_date, $check_out_date);
        } catch (Exception $e) {
            error_log('Riwa Booking Email Error: ' . $e->getMessage());
        }
        
        // Calculer le prix total
        $total_price = $this->calculate_total_price($check_in_date, $check_out_date, $total_travelers);
        
        // Mettre à jour la réservation avec les prix
        $wpdb->update(
            $table_name,
            array(
                'total_price' => $total_price['total'],
                'price_per_night' => $total_price['per_night']
            ),
            array('id' => $wpdb->insert_id),
            array('%f', '%f'),
            array('%d')
        );
        
        $success_message = 'Votre réservation a été enregistrée avec succès !';
        if ($total_price['total'] > 0) {
            $success_message .= ' Prix total : ' . number_format($total_price['total'], 2, ',', ' ') . ' €';
        }
        
        wp_send_json_success($success_message);
    }
    
    /**
     * Récupérer les dates réservées pour le calendrier
     */
    public function get_booked_dates() {
        // Log de débogage
        if (WP_DEBUG) {
            error_log('Riwa Booking: get_booked_dates appelée');
        }
        
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
            if (WP_DEBUG) {
                error_log('Riwa Booking: Échec de vérification du nonce dans get_booked_dates');
            }
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_bookings';
        
        // Récupérer toutes les réservations confirmées ou en attente
        $bookings = $wpdb->get_results("
            SELECT check_in_date, check_out_date 
            FROM $table_name 
            WHERE status IN ('pending', 'confirmed')
            ORDER BY check_in_date ASC
        ");
        
        if (WP_DEBUG) {
            error_log('Riwa Booking: Réservations trouvées: ' . print_r($bookings, true));
        }
        
        $booked_dates = array();
        
        foreach ($bookings as $booking) {
            $start = new DateTime($booking->check_in_date);
            $end = new DateTime($booking->check_out_date);
            $current = clone $start;
            
            // Ajouter toutes les dates de la réservation
            while ($current < $end) {
                $booked_dates[] = $current->format('Y-m-d');
                $current->add(new DateInterval('P1D'));
            }
        }
        
        // Supprimer les doublons
        $booked_dates = array_unique($booked_dates);
        sort($booked_dates);
        
        if (WP_DEBUG) {
            error_log('Riwa Booking: Dates réservées calculées: ' . print_r($booked_dates, true));
        }
        
        wp_send_json_success($booked_dates);
    }
    
    private function send_confirmation_email($email, $name, $check_in, $check_out) {
        $subject = 'Confirmation de votre réservation - Riwa';
        $message = "Bonjour $name,\n\n";
        $message .= "Nous avons bien reçu votre réservation pour les dates suivantes :\n";
        $message .= "Arrivée : $check_in\n";
        $message .= "Départ : $check_out\n\n";
        $message .= "Nous vous contacterons bientôt pour confirmer votre réservation.\n\n";
        $message .= "Cordialement,\nL'équipe Riwa";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Récupérer les données de tarification pour le JavaScript
     */
    private function get_pricing_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_pricing';
        
        $pricing = $wpdb->get_results("
            SELECT * FROM $table_name 
            WHERE is_active = 1 
            ORDER BY start_date ASC
        ");
        
        $pricing_data = array();
        foreach ($pricing as $price) {
            $pricing_data[] = array(
                'id' => $price->id,
                'name' => $price->season_name,
                'start_date' => $price->start_date,
                'end_date' => $price->end_date,
                'price_per_night' => floatval($price->price_per_night),
                'min_stay' => intval($price->min_stay)
            );
        }
        
        return $pricing_data;
    }
    
    /**
     * Générer une couleur unique pour chaque saison
     */
    private function get_season_color($season_id) {
        $colors = array(
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
            '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3'
        );
        
        return $colors[$season_id % count($colors)];
    }
    
    /**
     * Calculer le prix total pour une période donnée
     */
    private function calculate_total_price($check_in_date, $check_out_date, $guests_count) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_pricing';
        
        $check_in = new DateTime($check_in_date);
        $check_out = new DateTime($check_out_date);
        $nights = $check_in->diff($check_out)->days;
        
        if ($nights <= 0) {
            return array('total' => 0, 'per_night' => 0);
        }
        
        $total_price = 0;
        $current_date = clone $check_in;
        
        // Calculer le prix pour chaque nuit
        for ($i = 0; $i < $nights; $i++) {
            $date_str = $current_date->format('Y-m-d');
            
            // Trouver le prix pour cette date
            $price = $wpdb->get_var($wpdb->prepare("
                SELECT price_per_night 
                FROM $table_name 
                WHERE start_date <= %s 
                AND end_date >= %s 
                AND is_active = 1
                ORDER BY price_per_night DESC 
                LIMIT 1
            ", $date_str, $date_str));
            
            if ($price) {
                $total_price += floatval($price);
            } else {
                // Prix par défaut si aucune saison trouvée
                $total_price += 150.00;
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        $price_per_night = $total_price / $nights;
        
        return array(
            'total' => $total_price,
            'per_night' => $price_per_night
        );
    }
}

// Initialisation du plugin
new RiwaBooking();

<?php
/**
 * Plugin Name: Riwa Booking
 * Plugin URI: https://www.brioguiseppe.fr/
 * Description: Plugin de réservation de villas, développé sur-mesure pour Riwa.
 * Version: 1.1.2
 * Author: Brio Guiseppe
 * Author URI: https://www.brioguiseppe.fr/
 * Text Domain: riwavilla-booking
 * Domain Path: /languages
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Inclure la configuration de production
require_once plugin_dir_path(__FILE__) . 'production-config.php';

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
        add_action('wp_ajax_riwa_download_pdf', array($this, 'download_pdf'));
        add_action('wp_ajax_nopriv_riwa_download_pdf', array($this, 'download_pdf'));
        add_action('wp_ajax_riwa_reinstall_tcpdf', array($this, 'reinstall_tcpdf'));
        add_action('wp_ajax_riwa_test_client_email', array($this, 'test_client_email'));
        add_action('wp_ajax_riwa_test_admin_email', array($this, 'test_admin_email'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_shortcode('riwa_booking', array($this, 'booking_shortcode'));
        
        // Initialiser l'interface d'administration PDF
        $this->init_pdf_admin();
        
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
        
        // Installation automatique de TCPDF
        $this->install_tcpdf_automatically();
        
        // Vider le cache des permaliens
        flush_rewrite_rules();
        
        // Ajouter une option pour vérifier l'activation
        add_option('riwa_booking_activated', true);
        
        // Plugin activé avec succès
    }
    
    public function deactivate() {
        // Nettoyage lors de la désactivation
        // Supprimer l'option d'activation
        delete_option('riwa_booking_activated');
        
        // Vider le cache des permaliens
        flush_rewrite_rules();
        
        // Plugin désactivé
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
            // Colonne min_stay ajoutée à la table de tarification
        }
        
        // Vérifier si les colonnes de prix existent dans la table de réservations
        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        $price_columns = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'total_price'");
        
        if (empty($price_columns)) {
            // Ajouter les colonnes de prix si elles n'existent pas
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN total_price decimal(10,2) DEFAULT 0.00 AFTER special_requests");
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN price_per_night decimal(10,2) DEFAULT 0.00 AFTER total_price");
            // Colonnes de prix ajoutées à la table de réservations
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
            'pricing' => $pricing_data // Données de tarification
        ));
        
        // Données de tarification passées au JavaScript
    }
    
    public function admin_enqueue_scripts($hook) {
        // Scripts pour la page principale d'administration
        if ('toplevel_page_riwa-bookings' === $hook) {
            wp_enqueue_style('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking-admin.css', array(), RIWA_BOOKING_VERSION);
            wp_enqueue_script('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking-admin.js', array('jquery'), RIWA_BOOKING_VERSION, true);
        }
        
        // Scripts pour la page de personnalisation PDF
        if ('riwa-bookings_page_riwa-pdf-settings' === $hook) {
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();
            
            wp_enqueue_script(
                'riwa-pdf-admin',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-pdf-admin.js',
                array('jquery', 'wp-color-picker'),
                '1.0.0',
                true
            );
            
            wp_localize_script('riwa-pdf-admin', 'riwa_pdf_admin', array(
                'nonce' => wp_create_nonce('riwa_pdf_admin_nonce'),
                'test_url' => admin_url('admin-ajax.php?action=riwa_test_pdf&nonce=' . wp_create_nonce('riwa_pdf_admin_nonce')),
                'ajaxurl' => admin_url('admin-ajax.php')
            ));
        }
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
        
        // Sous-menu pour la personnalisation PDF
        add_submenu_page(
            'riwa-bookings',
            'Personnalisation PDF',
            'Personnalisation PDF',
            'manage_options',
            'riwa-pdf-settings',
            array($this, 'pdf_settings_page')
        );
        

    }
    
    /**
     * Initialise l'interface d'administration PDF
     */
    private function init_pdf_admin() {
        // Inclure les classes nécessaires
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-admin.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-ajax.php';
        
        // Initialiser l'interface d'administration
        Riwa_PDF_Admin::init();
        
        // Initialiser les actions AJAX
        Riwa_PDF_Ajax::init();
    }
    
    public function admin_page() {
        include RIWA_BOOKING_PLUGIN_PATH . 'admin/admin-page.php';
    }
    
    public function pricing_page() {
        include RIWA_BOOKING_PLUGIN_PATH . 'admin/pricing-page.php';
    }
    
    public function pdf_settings_page() {
        // Inclure les classes nécessaires
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-admin.php';
        
        // Afficher la page d'administration PDF
        Riwa_PDF_Admin::admin_page();
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
        // Vérification des données POST
        if (!isset($_POST['nonce'])) {
            wp_send_json_error('Données manquantes');
            return;
        }

        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
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
        
        // Préparation des données pour insertion
        
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
                'special_requests' => $special_requests,
                'status' => 'pending'
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
        );
        
        if ($result === false) {
            $error_message = 'Erreur lors de la sauvegarde de la réservation.';
            if ($wpdb->last_error) {
                $error_message .= ' Erreur technique: ' . $wpdb->last_error;
            }
            wp_send_json_error($error_message);
            return;
        }
        
        // Envoi d'email de confirmation au client
        try {
            $this->send_confirmation_email($guest_email, $guest_name, $check_in_date, $check_out_date);
        } catch (Exception $e) {
            // Erreur lors de l'envoi d'email
        }
        
        // Envoi d'email de notification à l'administrateur
        try {
            $this->send_admin_notification_email($guest_name, $guest_email, $guest_phone, $check_in_date, $check_out_date, $adults_count, $children_count, $babies_count, $special_requests);
        } catch (Exception $e) {
            // Erreur lors de l'envoi d'email admin
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
        
        // Retourner l'ID de la réservation pour permettre la génération du PDF
        wp_send_json_success(array(
            'message' => $success_message,
            'booking_id' => $wpdb->insert_id
        ));
    }
    
    /**
     * Récupérer les dates réservées pour le calendrier
     */
    public function get_booked_dates() {
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
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
        
        // Récupération des réservations terminée
        
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
        
        // Calcul des dates réservées terminé
        
        wp_send_json_success($booked_dates);
    }
    
    private function send_confirmation_email($email, $name, $check_in, $check_out) {
        // Récupérer les paramètres configurables
        $from_name = get_option('riwa_email_from_name', 'Riwa Villa');
        $from_address = get_option('riwa_email_from_address', 'noreply@riwa-villa.com');
        $subject = get_option('riwa_email_client_subject', 'Confirmation de votre réservation - Riwa');
        $message_template = get_option('riwa_email_client_message', "Bonjour {guest_name},\n\nNous avons bien reçu votre réservation pour les dates suivantes :\nArrivée : {check_in}\nDépart : {check_out}\n\nNous vous contacterons bientôt pour confirmer votre réservation.\n\nCordialement,\nL'équipe Riwa");
        
        // Remplacer les variables dans le message
        $message = str_replace(
            array('{guest_name}', '{check_in}', '{check_out}'),
            array($name, $check_in, $check_out),
            $message_template
        );
        
        // Configuration des headers pour l'email
        $headers = array(
            'From: ' . $from_name . ' <' . $from_address . '>',
            'Content-Type: text/plain; charset=UTF-8'
        );
        
        wp_mail($email, $subject, $message, $headers);
    }
    
    private function send_admin_notification_email($guest_name, $guest_email, $guest_phone, $check_in, $check_out, $adults_count, $children_count, $babies_count, $special_requests) {
        // Vérifier si les notifications sont activées
        if (!get_option('riwa_email_notification_enabled', 1)) {
            return;
        }
        
        // Récupérer les paramètres configurables
        $admin_email = get_option('riwa_email_admin_address', get_option('admin_email'));
        $from_name = get_option('riwa_email_from_name', 'Riwa Villa');
        $from_address = get_option('riwa_email_from_address', 'noreply@riwa-villa.com');
        $subject = get_option('riwa_email_admin_subject', 'Nouvelle réservation - Riwa Villa');
        $message_template = get_option('riwa_email_admin_message', "Une nouvelle réservation a été effectuée sur le site.\n\nDétails de la réservation :\nNom : {guest_name}\nEmail : {guest_email}\nTéléphone : {guest_phone}\nDate d'arrivée : {check_in}\nDate de départ : {check_out}\nNombre d'adultes : {adults_count}\nNombre d'enfants : {children_count}\nNombre de bébés : {babies_count}\n\nDemandes spéciales : {special_requests}\n\nConnectez-vous à l'administration pour gérer cette réservation.\nLien d'administration : {admin_url}\n\nCordialement,\nSystème de réservation Riwa");
        
        // Remplacer les variables dans le message
        $message = str_replace(
            array(
                '{guest_name}', '{guest_email}', '{guest_phone}', '{check_in}', '{check_out}',
                '{adults_count}', '{children_count}', '{babies_count}', '{special_requests}', '{admin_url}'
            ),
            array(
                $guest_name, $guest_email, $guest_phone, $check_in, $check_out,
                $adults_count, $children_count, $babies_count, $special_requests, admin_url('admin.php?page=riwa-booking')
            ),
            $message_template
        );
        
        // Configuration des headers pour l'email
        $headers = array(
            'From: ' . $from_name . ' <' . $from_address . '>',
            'Content-Type: text/plain; charset=UTF-8'
        );
        
        wp_mail($admin_email, $subject, $message, $headers);
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
    
    /**
     * Installation automatique de TCPDF lors de l'activation du plugin
     */
    private function install_tcpdf_automatically() {
        // Vérifier si TCPDF est déjà installé
        if (class_exists('TCPDF')) {
            return;
        }
        
        // Vérifier si notre configuration existe déjà
        $config_file = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf-config.php';
        if (file_exists($config_file)) {
            return;
        }
        
        // Vérifier les extensions PHP nécessaires
        if (!extension_loaded('zip') || !extension_loaded('curl')) {
            return;
        }
        
        try {
            // Créer le dossier TCPDF
            $tcpdf_dir = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf/';
            if (!file_exists($tcpdf_dir)) {
                if (!mkdir($tcpdf_dir, 0755, true)) {
                    return;
                }
            }
            
            // Télécharger TCPDF
            $tcpdf_url = 'https://github.com/tecnickcom/TCPDF/archive/refs/tags/6.6.5.zip';
            $zip_file = $tcpdf_dir . 'tcpdf.zip';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tcpdf_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            
            $zip_content = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code !== 200 || empty($zip_content)) {
                return;
            }
            
            // Sauvegarder le ZIP
            if (!file_put_contents($zip_file, $zip_content)) {
                return;
            }
            
            // Extraire le ZIP
            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== TRUE) {
                return;
            }
            
            $zip->extractTo($tcpdf_dir);
            $zip->close();
            
            // Trouver le dossier extrait
            $extracted_dir = null;
            $files = scandir($tcpdf_dir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_dir($tcpdf_dir . $file) && strpos($file, 'TCPDF') !== false) {
                    $extracted_dir = $tcpdf_dir . $file;
                    break;
                }
            }
            
            if (!$extracted_dir) {
                return;
            }
            
            // Déplacer les fichiers au bon endroit
            $final_dir = $tcpdf_dir . 'tcpdf/';
            if (file_exists($final_dir)) {
                // Supprimer l'ancien dossier
                $this->delete_directory($final_dir);
            }
            
            if (!rename($extracted_dir, $final_dir)) {
                return;
            }
            
            // Nettoyer le fichier ZIP
            unlink($zip_file);
            
            // Créer le fichier de configuration
            $config_content = "<?php
/**
 * Configuration TCPDF pour Riwa Booking
 * Généré automatiquement lors de l'activation du plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

// Définir le chemin vers TCPDF
define('K_TCPDF_EXTERNAL_CONFIG', true);
define('K_PATH_MAIN', __DIR__ . '/tcpdf/');
define('K_PATH_URL', __DIR__ . '/tcpdf/');
define('K_PATH_FONTS', K_PATH_MAIN . 'fonts/');
define('K_PATH_CACHE', K_PATH_MAIN . 'cache/');
define('K_PATH_URL_CACHE', K_PATH_URL . 'cache/');
define('K_PATH_IMAGES', K_PATH_MAIN . 'images/');
define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');
define('PDF_PAGE_FORMAT', 'A4');
define('PDF_PAGE_ORIENTATION', 'P');
define('PDF_CREATOR', 'Riwa Booking');
define('PDF_AUTHOR', 'Riwa Villa');
define('PDF_UNIT', 'mm');
define('PDF_MARGIN_HEADER', 5);
define('PDF_MARGIN_FOOTER', 10);
define('PDF_MARGIN_TOP', 27);
define('PDF_MARGIN_BOTTOM', 25);
define('PDF_MARGIN_LEFT', 15);
define('PDF_MARGIN_RIGHT', 15);
define('PDF_FONT_NAME_MAIN', 'helvetica');
define('PDF_FONT_SIZE_MAIN', 10);

// Inclure TCPDF
require_once(K_PATH_MAIN . 'tcpdf.php');
";
            
            if (file_put_contents($config_file, $config_content)) {
                // Tester l'installation
                require_once($config_file);
                if (class_exists('TCPDF')) {
                    // TCPDF installé avec succès
                }
            }
            
        } catch (Exception $e) {
            // Erreur lors de l'installation TCPDF
        }
    }
    
    /**
     * Supprimer un dossier et son contenu
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!$this->delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Télécharger le PDF de confirmation de réservation
     */
    public function download_pdf() {
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error('ID de réservation invalide');
            return;
        }
        
        // Inclure la classe PDF Generator
        require_once(RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-generator.php');
        
        try {
            // Générer et télécharger le PDF
            Riwa_PDF_Generator::download_pdf($booking_id);
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de la génération du PDF');
        }
    }
    
    /**
     * Réinstaller TCPDF via AJAX (pour l'admin)
     */
    public function reinstall_tcpdf() {
        // Vérifier les permissions admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }
        
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        // Supprimer l'ancienne installation
        $tcpdf_dir = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf/';
        $config_file = RIWA_BOOKING_PLUGIN_PATH . 'includes/tcpdf-config.php';
        
        if (file_exists($tcpdf_dir)) {
            $this->delete_directory($tcpdf_dir);
        }
        
        if (file_exists($config_file)) {
            unlink($config_file);
        }
        
        // Réinstaller TCPDF
        $this->install_tcpdf_automatically();
        
        // Vérifier le résultat
        if (class_exists('TCPDF')) {
            wp_send_json_success('TCPDF réinstallé avec succès');
        } else {
            wp_send_json_error('Échec de la réinstallation de TCPDF');
        }
    }
    
    /**
     * Test d'envoi d'email client
     */
    public function test_client_email() {
        // Vérifier les permissions admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }
        
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_test_email')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        $test_email = sanitize_email($_POST['email']);
        if (empty($test_email)) {
            wp_send_json_error('Email de test invalide');
            return;
        }
        
        try {
            // Envoyer un email de test
            $this->send_confirmation_email($test_email, 'Test Client', '2024-01-15', '2024-01-20');
            wp_send_json_success('Email de test client envoyé avec succès');
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }
    
    /**
     * Test d'envoi d'email administrateur
     */
    public function test_admin_email() {
        // Vérifier les permissions admin
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }
        
        // Vérification du nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_test_email')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }
        
        $test_email = sanitize_email($_POST['email']);
        if (empty($test_email)) {
            wp_send_json_error('Email de test invalide');
            return;
        }
        
        try {
            // Envoyer un email de test admin
            $this->send_admin_notification_email(
                'Test Client',
                $test_email,
                '0123456789',
                '2024-01-15',
                '2024-01-20',
                2,
                1,
                0,
                'Demande de test'
            );
            wp_send_json_success('Email de test administrateur envoyé avec succès');
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }
}

// Initialisation du plugin
new RiwaBooking();

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

if (!defined('ABSPATH')) {
    exit;
}

// Configuration et constantes
require_once plugin_dir_path(__FILE__) . 'production-config.php';

define('RIWA_BOOKING_VERSION', '1.1.2');
define('RIWA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RIWA_BOOKING_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Classes métier
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-installer.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-emails.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pricing.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-booking-ajax.php';

// Classes admin
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-admin.php';

/**
 * Classe principale — Bootstrap du plugin
 */
class RiwaBooking {

    public function __construct() {
        // Hooks cycle de vie WordPress
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('riwa_booking', array($this, 'booking_shortcode'));

        // AJAX public (frontend)
        add_action('wp_ajax_riwa_submit_booking',        array('Riwa_Booking_Ajax', 'submit_booking'));
        add_action('wp_ajax_nopriv_riwa_submit_booking', array('Riwa_Booking_Ajax', 'submit_booking'));
        add_action('wp_ajax_riwa_get_booked_dates',        array('Riwa_Booking_Ajax', 'get_booked_dates'));
        add_action('wp_ajax_nopriv_riwa_get_booked_dates', array('Riwa_Booking_Ajax', 'get_booked_dates'));
        add_action('wp_ajax_riwa_download_pdf',        array('Riwa_Booking_Ajax', 'download_pdf'));
        add_action('wp_ajax_nopriv_riwa_download_pdf', array('Riwa_Booking_Ajax', 'download_pdf'));

        // AJAX admin
        add_action('wp_ajax_riwa_reinstall_tcpdf',    array('Riwa_Installer', 'reinstall_tcpdf'));
        add_action('wp_ajax_riwa_test_client_email',  array('Riwa_Emails', 'ajax_test_client_email'));
        add_action('wp_ajax_riwa_test_admin_email',   array('Riwa_Emails', 'ajax_test_admin_email'));

        // Interface admin
        Riwa_Admin::init();

        // Activation / Désactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        $this->check_table_updates();
    }

    public function activate() {
        $this->create_booking_table();
        $this->create_pricing_table();
        Riwa_Installer::install_tcpdf();
        flush_rewrite_rules();
        add_option('riwa_booking_activated', true);
    }

    public function deactivate() {
        delete_option('riwa_booking_activated');
        flush_rewrite_rules();
    }

    /**
     * Migrations de tables (colonnes ajoutées en v1.1.x)
     */
    private function check_table_updates() {
        global $wpdb;

        $pricing_table = $wpdb->prefix . 'riwa_pricing';
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'min_stay'"))) {
            $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN min_stay int(3) DEFAULT 1 AFTER price_per_night");
        }

        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'total_price'"))) {
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN total_price decimal(10,2) DEFAULT 0.00 AFTER special_requests");
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN price_per_night decimal(10,2) DEFAULT 0.00 AFTER total_price");
        }
    }

    private function create_booking_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'riwa_bookings';
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function create_pricing_table() {
        global $wpdb;
        $table_name      = $wpdb->prefix . 'riwa_pricing';
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        if ($wpdb->get_var("SELECT COUNT(*) FROM $table_name") == 0) {
            $this->insert_default_pricing();
        }
    }

    private function insert_default_pricing() {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'riwa_pricing', array(
            'season_name'    => 'Basse saison',
            'start_date'     => '2024-01-01',
            'end_date'       => '2024-12-31',
            'price_per_night' => 150.00,
            'min_stay'       => 1,
        ));
    }

    /**
     * Scripts et styles frontend
     */
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');

        wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
        wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);
        wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', array('flatpickr'), '4.6.13', true);

        wp_enqueue_style('riwa-booking-style', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking.css', array(), RIWA_BOOKING_VERSION);
        wp_enqueue_script('riwa-booking-script', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking.js', array('jquery', 'flatpickr'), RIWA_BOOKING_VERSION, true);

        wp_localize_script('riwa-booking-script', 'riwa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('riwa_booking_nonce'),
            'pricing'  => Riwa_Pricing::get_pricing_data(),
        ));
    }

    /**
     * Rendu du shortcode [riwa_booking]
     */
    public function booking_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title'         => 'Réserver votre villa',
            'show_calendar' => 'true',
        ), $atts);

        ob_start();
        include RIWA_BOOKING_PLUGIN_PATH . 'templates/booking-form.php';
        return ob_get_clean();
    }
}

new RiwaBooking();

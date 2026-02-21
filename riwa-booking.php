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

define('RIWA_BOOKING_VERSION', '1.1.3');
define('RIWA_BOOKING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RIWA_BOOKING_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Classes métier
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-installer.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-emails.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pricing.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-booking-ajax.php';

// Classes admin
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-admin.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-upsells-table.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-planning.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-stats.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-notifications.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-notif-settings.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-studio.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-payments.php';

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
        add_action('wp_ajax_riwa_get_booking_upsells',  array($this, 'ajax_get_booking_upsells'));
        add_action('wp_ajax_riwa_bookings_load_more',   array('Riwa_Bookings_Table', 'ajax_load_more'));

        // AJAX Planning
        add_action('wp_ajax_riwa_planning_get_data',           array('Riwa_Planning', 'ajax_get_planning_data'));
        add_action('wp_ajax_riwa_planning_add_blocked',        array('Riwa_Planning', 'ajax_add_blocked'));
        add_action('wp_ajax_riwa_planning_delete_blocked',     array('Riwa_Planning', 'ajax_delete_blocked'));
        add_action('wp_ajax_riwa_planning_save_price_override',array('Riwa_Planning', 'ajax_save_price_override'));
        add_action('wp_ajax_riwa_planning_update_housekeeping',array('Riwa_Planning', 'ajax_update_housekeeping'));
        add_action('wp_ajax_riwa_planning_seed_demo',          array('Riwa_Planning', 'ajax_seed_demo'));
        add_action('wp_ajax_riwa_planning_clear_demo',         array('Riwa_Planning', 'ajax_clear_demo'));
        add_action('wp_ajax_riwa_planning_demo_status',        array('Riwa_Planning', 'ajax_demo_status'));

        // AJAX Stats
        add_action('wp_ajax_riwa_stats_get_data', array('Riwa_Stats', 'ajax_get_stats_data'));

        // AJAX PDF Studio
        add_action('wp_ajax_riwa_studio_save_layout',  array('Riwa_PDF_Studio', 'ajax_save_layout'));
        add_action('wp_ajax_riwa_studio_preview',       array('Riwa_PDF_Studio', 'ajax_preview'));
        add_action('wp_ajax_riwa_studio_save_settings', array('Riwa_PDF_Studio', 'ajax_save_settings'));
        add_action('wp_ajax_riwa_studio_get_layouts',   array('Riwa_PDF_Studio', 'ajax_get_all_layouts'));
        add_action('wp_ajax_riwa_studio_reset_layout',  array('Riwa_PDF_Studio', 'ajax_reset_layout'));

        // AJAX Paiements
        add_action('wp_ajax_riwa_payments_add_payment',       array('Riwa_Payments', 'ajax_add_payment'));
        add_action('wp_ajax_riwa_payments_delete_payment',    array('Riwa_Payments', 'ajax_delete_payment'));
        add_action('wp_ajax_riwa_payments_save_deposit_info', array('Riwa_Payments', 'ajax_save_deposit_info'));
        add_action('wp_ajax_riwa_payments_get_dashboard',     array('Riwa_Payments', 'ajax_get_dashboard'));
        add_action('wp_ajax_riwa_payments_get_booking_payments', array('Riwa_Payments', 'ajax_get_booking_payments'));
        add_action('wp_ajax_riwa_payments_get_bookings_list', array('Riwa_Payments', 'ajax_get_bookings_list'));
        add_action('wp_ajax_riwa_payments_export_csv',        array('Riwa_Payments', 'ajax_export_csv'));

        // AJAX Notifications
        add_action('wp_ajax_riwa_notif_get_log',    array('Riwa_Notifications', 'ajax_get_log'));
        add_action('wp_ajax_riwa_notif_log_sent',   array('Riwa_Notifications', 'ajax_log_sent'));
        add_action('wp_ajax_riwa_notif_preview',    array('Riwa_Notifications', 'ajax_preview'));
        add_action('wp_ajax_riwa_notif_recent_log', array('Riwa_Notifications', 'ajax_recent_log'));

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
        Riwa_Upsells_Table::create_tables();
        Riwa_Planning::create_tables();
        Riwa_Notifications::create_table();
        Riwa_Payments::create_table();
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

        // Migration v1.2 : tables upsells (créées si inexistantes)
        $upsells_table = $wpdb->prefix . 'riwa_upsells';
        if ($wpdb->get_var("SHOW TABLES LIKE '$upsells_table'") !== $upsells_table) {
            Riwa_Upsells_Table::create_tables();
        }

        // Migration v1.3 : tables planning (créées si inexistantes)
        $blocked_table = $wpdb->prefix . 'riwa_blocked_dates';
        if ($wpdb->get_var("SHOW TABLES LIKE '$blocked_table'") !== $blocked_table) {
            Riwa_Planning::create_tables();
        }

        // Migration v1.4 : table notifications log (créée si inexistante)
        $notif_table = $wpdb->prefix . 'riwa_notification_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$notif_table'") !== $notif_table) {
            Riwa_Notifications::create_table();
        }

        // Migration v1.5 : module paiements
        $payments_table = $wpdb->prefix . 'riwa_payments';
        if ($wpdb->get_var("SHOW TABLES LIKE '$payments_table'") !== $payments_table) {
            Riwa_Payments::create_table();
        }
        // Nouvelles colonnes paiement dans riwa_bookings
        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'deposit_percent'"))) {
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN deposit_percent decimal(5,2) DEFAULT 0.00 AFTER total_price");
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN deposit_amount decimal(10,2) DEFAULT 0.00 AFTER deposit_percent");
            $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN balance_due_date date DEFAULT NULL AFTER deposit_amount");
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

        wp_enqueue_style('dashicons');
        wp_enqueue_style('riwa-booking-style', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking.css', array('dashicons'), RIWA_BOOKING_VERSION);
        wp_enqueue_script('riwa-booking-script', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking.js', array('jquery', 'flatpickr'), RIWA_BOOKING_VERSION, true);

        wp_localize_script('riwa-booking-script', 'riwa_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('riwa_booking_nonce'),
            'pricing'  => Riwa_Pricing::get_pricing_data(),
            'upsells'  => Riwa_Upsells_Table::get_active(),
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

    /**
     * AJAX admin : récupérer les upsells d'une réservation
     */
    public function ajax_get_booking_upsells() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_admin_action')) {
            wp_send_json_error('Nonce invalide');
        }
        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error('ID invalide');
        }
        $upsells = Riwa_Upsells_Table::get_for_booking($booking_id);
        wp_send_json_success($upsells);
    }
}

new RiwaBooking();

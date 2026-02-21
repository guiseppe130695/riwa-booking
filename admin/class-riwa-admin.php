<?php
/**
 * Initialisation de l'interface admin : menus, scripts, dispatch des sections
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-bookings-table.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-pricing-table.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-email-settings.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-upsells-table.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-planning.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-stats.php';
require_once RIWA_BOOKING_PLUGIN_PATH . 'admin/class-riwa-notif-settings.php';

class Riwa_Admin {

    /** Navigation : définition des 9 sections */
    private static function get_nav_items() {
        return [
            'dashboard'     => ['icon' => 'dashicons-chart-bar',     'label' => 'Tableau de bord',  'soon' => false],
            'bookings'      => ['icon' => 'dashicons-calendar-alt',  'label' => 'Réservations',      'soon' => false],
            'services'      => ['icon' => 'dashicons-store',         'label' => 'Services',          'soon' => false],
            'planning'      => ['icon' => 'dashicons-calendar',      'label' => 'Planning',          'soon' => false],
            'payments'      => ['icon' => 'dashicons-money-alt',     'label' => 'Paiements',         'soon' => false],
            'notifications' => ['icon' => 'dashicons-bell',          'label' => 'Notifications',     'soon' => false],
            'stats'         => ['icon' => 'dashicons-chart-line',    'label' => 'Statistiques',      'soon' => false],
            'pdf'           => ['icon' => 'dashicons-pdf',           'label' => 'Factures / PDF',    'soon' => false],
            'settings'      => ['icon' => 'dashicons-admin-settings','label' => 'Paramètres',        'soon' => false],
        ];
    }

    public static function init() {
        add_action('admin_menu',            array(__CLASS__, 'register_menus'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));

        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-admin.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-ajax.php';
        Riwa_PDF_Admin::init();
        Riwa_PDF_Ajax::init();
    }

    public static function register_menus() {
        add_menu_page(
            'Riwa Bookings',
            'Riwa Bookings',
            'manage_options',
            'riwa-bookings',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-calendar-alt',
            30
        );

        // Page PDF accessible via la section "Factures / PDF" dans le panneau principal
        add_submenu_page(
            null,
            'Personnalisation PDF',
            'Personnalisation PDF',
            'manage_options',
            'riwa-pdf-settings',
            array(__CLASS__, 'render_pdf_settings_page')
        );
    }

    public static function enqueue_scripts($hook) {
        if ('toplevel_page_riwa-bookings' === $hook && (($_GET['section'] ?? '') === 'pdf')) {
            // PDF Studio — scripts chargés en priorité si section=pdf
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script('sortablejs',
                'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js',
                array(), '1.15.3', true);
            wp_enqueue_style('riwa-pdf-studio',
                RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-pdf-studio.css',
                array(), RIWA_BOOKING_VERSION);
            wp_enqueue_script('riwa-pdf-studio',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-pdf-studio.js',
                array('jquery', 'sortablejs', 'wp-color-picker'), RIWA_BOOKING_VERSION, true);
            wp_localize_script('riwa-pdf-studio', 'riwa_studio_config', array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'nonce'       => wp_create_nonce('riwa_studio_nonce'),
                'doc_types'   => Riwa_PDF_Studio::$doc_types,
                'doc_labels'  => Riwa_PDF_Studio::$doc_labels,
                'block_labels'=> Riwa_PDF_Studio::$block_labels,
                'layouts'     => array_combine(
                    Riwa_PDF_Studio::$doc_types,
                    array_map([Riwa_PDF_Studio::class, 'get_layout'], Riwa_PDF_Studio::$doc_types)
                ),
                'settings'    => Riwa_PDF_Studio::get_settings(),
                'test_url'    => admin_url('admin-ajax.php?action=riwa_test_pdf&nonce=' . wp_create_nonce('riwa_pdf_admin_nonce')),
            ));
        }

        if ('toplevel_page_riwa-bookings' === $hook) {
            // Module Notifications
            wp_enqueue_script('riwa-notifications',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-notifications.js',
                array('jquery'), RIWA_BOOKING_VERSION, true);
            wp_localize_script('riwa-notifications', 'riwa_notif_config', array(
                'ajax_url'  => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('riwa_notif_nonce'),
                'enabled'   => (bool) get_option('riwa_notif_whatsapp_enabled', false),
                'templates' => Riwa_Notif_Settings::get_template_names(),
            ));
            wp_enqueue_style('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking-admin.css', array(), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-bookings',      RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-bookings.css',      array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-filters',       RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-filters.css',       array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-services',      RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-services.css',      array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-planning',      RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-planning.css',      array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-stats',         RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-stats.css',         array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-notifications', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-notifications.css', array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_style('riwa-admin-payments',      RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-admin-payments.css',      array('riwa-booking-admin'), RIWA_BOOKING_VERSION);
            wp_enqueue_script('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking-admin.js', array('jquery'), RIWA_BOOKING_VERSION, true);

            wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
            wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);
            wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', array('flatpickr'), '4.6.13', true);

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();

            wp_enqueue_script(
                'riwa-planning',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-planning.js',
                array('jquery'),
                RIWA_BOOKING_VERSION,
                true
            );

            wp_localize_script('riwa-planning', 'riwa_planning_config', array(
                'ajax_url'  => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('riwa_planning_nonce'),
                'admin_url' => admin_url(),
            ));

            wp_localize_script('riwa-booking-admin', 'riwa_admin_ajax', array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'admin_nonce' => wp_create_nonce('riwa_admin_action'),
                'admin_url'   => admin_url(),
            ));

            // Module Paiements
            wp_enqueue_script('riwa-payments',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-payments.js',
                array('jquery'), RIWA_BOOKING_VERSION, true);
            wp_localize_script('riwa-payments', 'riwa_payments_config', array(
                'ajax_url'   => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('riwa_payments_nonce'),
                'export_url' => admin_url('admin-ajax.php?action=riwa_payments_export_csv&nonce=' . wp_create_nonce('riwa_payments_nonce')),
                'currency'   => get_option('riwa_setting_currency', '€'),
                'methods'    => Riwa_Payments::get_methods(),
            ));

            // Chart.js + module stats
            wp_enqueue_script('chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js/dist/chart.umd.min.js',
                array(), '4.4.0', true);
            wp_enqueue_script('riwa-stats',
                RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-stats.js',
                array('jquery', 'chart-js'), RIWA_BOOKING_VERSION, true);
            wp_localize_script('riwa-stats', 'riwa_stats_config', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('riwa_stats_nonce'),
            ));
        }

    }

    /**
     * Page principale : traiter les actions POST puis afficher les sections
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé.');
        }

        // Section active (GET param, défaut : dashboard)
        $section = sanitize_key($_GET['section'] ?? 'dashboard');
        $nav_items = self::get_nav_items();
        if (!array_key_exists($section, $nav_items)) {
            $section = 'dashboard';
        }

        // PDF Studio — rendu full-page direct, sans header/nav
        if ($section === 'pdf') {
            include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/pdf-studio.php';
            return;
        }

        // Traitement des actions POST (uniquement pour les sections qui en ont besoin)
        Riwa_Pricing_Table::handle_post_actions();
        Riwa_Bookings_Table::handle_post_actions();
        Riwa_Email_Settings::handle_save();
        Riwa_Notif_Settings::handle_save();

        // Données partagées
        $pricing_seasons = Riwa_Pricing_Table::get_all_seasons();
        $email_options   = Riwa_Email_Settings::get_options();

        ?>
        <div class="wrap riwa-pdf-admin">
            <div class="riwa-admin-header">
                <div class="riwa-header-content">
                    <h1>Riwa Booking</h1>
                    <p class="riwa-subtitle">Plateforme de gestion des réservations de votre villa</p>
                    <p class="riwa-header-desc">
                        Suivez vos réservations, gérez vos tarifs saisonniers, personnalisez vos emails et documents PDF —
                        tout ce dont vous avez besoin pour gérer votre hébergement en un seul endroit.
                    </p>
                </div>
            </div>

            <div class="riwa-pdf-admin-container">
                <!-- Panneau de navigation -->
                <div class="riwa-nav-panel">
                    <div class="riwa-nav-header"><h3>Navigation</h3></div>
                    <nav class="riwa-nav-menu">
                        <?php foreach ($nav_items as $key => $item): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=riwa-bookings&section=' . $key)); ?>"
                               class="riwa-nav-item <?php echo $section === $key ? 'active' : ''; ?>"
                               data-section="<?php echo esc_attr($key); ?>">
                                <span class="dashicons <?php echo esc_attr($item['icon']); ?>"></span>
                                <?php echo esc_html($item['label']); ?>
                                <?php if ($item['soon']): ?>
                                    <span class="riwa-badge-soon">Bientôt</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>

                <!-- Panneau de contenu -->
                <div class="riwa-content-panel">
                    <?php self::render_section($section, $pricing_seasons, $email_options); ?>
                </div>
            </div>
        </div>

        <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/booking-detail-popup.php'; ?>
        <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/blocked-detail-popup.php'; ?>
        <?php
    }

    /**
     * Dispatcher : inclure le partial correspondant à la section active
     */
    private static function render_section($section, $pricing_seasons, $email_options) {
        $coming_soon_sections = [];

        if (isset($coming_soon_sections[$section])) {
            $cs = $coming_soon_sections[$section];
            $section_title       = $cs['title'];
            $section_description = $cs['description'];
            $section_id          = $section;
            include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/coming-soon.php';
            return;
        }

        switch ($section) {
            case 'dashboard':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/dashboard.php';
                break;

            case 'bookings':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/bookings-list.php';
                break;

            case 'services':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/services.php';
                break;

            case 'planning':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/planning.php';
                break;

            case 'payments':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/payments.php';
                break;

            case 'notifications':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/notifications.php';
                break;

            case 'stats':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/statistics.php';
                break;

            case 'pdf':
                ?>
                <div class="riwa-section" id="pdf-section">
                    <div class="riwa-section-header">
                        <h2>Factures / PDF</h2>
                        <p>Personnalisez l'apparence de vos documents PDF</p>
                    </div>
                    <div class="riwa-section-content">
                        <div style="padding:2rem;">
                            <p>La personnalisation du PDF dispose d'une page dédiée.</p>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=riwa-pdf-settings')); ?>" class="riwa-btn riwa-btn-primary">
                                <span class="dashicons dashicons-pdf"></span>
                                Ouvrir les paramètres PDF
                            </a>
                        </div>
                    </div>
                </div>
                <?php
                break;

            case 'settings':
                include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/settings.php';
                break;
        }
    }

    /**
     * Page de personnalisation PDF (sous-menu dédié)
     */
    public static function render_pdf_settings_page() {
        // Riwa Doc Studio — éditeur visuel
        include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/pdf-studio.php';
    }
}

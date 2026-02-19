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

class Riwa_Admin {

    /** Navigation : définition des 9 sections */
    private static function get_nav_items() {
        return [
            'dashboard'     => ['icon' => 'dashicons-chart-bar',     'label' => 'Tableau de bord',  'soon' => false],
            'bookings'      => ['icon' => 'dashicons-calendar-alt',  'label' => 'Réservations',      'soon' => false],
            'services'      => ['icon' => 'dashicons-store',         'label' => 'Services',          'soon' => true],
            'planning'      => ['icon' => 'dashicons-calendar',      'label' => 'Planning',          'soon' => true],
            'payments'      => ['icon' => 'dashicons-money-alt',     'label' => 'Paiements',         'soon' => true],
            'notifications' => ['icon' => 'dashicons-bell',          'label' => 'Notifications',     'soon' => true],
            'stats'         => ['icon' => 'dashicons-chart-line',    'label' => 'Statistiques',      'soon' => true],
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

        add_submenu_page(
            'riwa-bookings',
            'Personnalisation PDF',
            'Personnalisation PDF',
            'manage_options',
            'riwa-pdf-settings',
            array(__CLASS__, 'render_pdf_settings_page')
        );
    }

    public static function enqueue_scripts($hook) {
        if ('toplevel_page_riwa-bookings' === $hook) {
            wp_enqueue_style('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-booking-admin.css', array(), RIWA_BOOKING_VERSION);
            wp_enqueue_script('riwa-booking-admin', RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-booking-admin.js', array('jquery'), RIWA_BOOKING_VERSION, true);

            wp_enqueue_style('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css', array(), '4.6.13');
            wp_enqueue_script('flatpickr', 'https://cdn.jsdelivr.net/npm/flatpickr', array('jquery'), '4.6.13', true);
            wp_enqueue_script('flatpickr-fr', 'https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js', array('flatpickr'), '4.6.13', true);

            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_media();

            wp_localize_script('riwa-booking-admin', 'riwa_admin_ajax', array(
                'ajax_url'    => admin_url('admin-ajax.php'),
                'admin_nonce' => wp_create_nonce('riwa_admin_action'),
            ));
        }

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
                'nonce'    => wp_create_nonce('riwa_pdf_admin_nonce'),
                'test_url' => admin_url('admin-ajax.php?action=riwa_test_pdf&nonce=' . wp_create_nonce('riwa_pdf_admin_nonce')),
                'ajaxurl'  => admin_url('admin-ajax.php'),
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

        // Traitement des actions POST (uniquement pour les sections qui en ont besoin)
        Riwa_Pricing_Table::handle_post_actions();
        Riwa_Bookings_Table::handle_post_actions();
        Riwa_Email_Settings::handle_save();

        // Données partagées
        $pricing_seasons = Riwa_Pricing_Table::get_all_seasons();
        $email_options   = Riwa_Email_Settings::get_options();
        ?>
        <div class="wrap riwa-pdf-admin">
            <div class="riwa-admin-header">
                <div class="riwa-header-content">
                    <h1>Riwa Booking</h1>
                    <p class="riwa-subtitle">Gestion des réservations</p>
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
        <?php
    }

    /**
     * Dispatcher : inclure le partial correspondant à la section active
     */
    private static function render_section($section, $pricing_seasons, $email_options) {
        $coming_soon_sections = [
            'services'      => ['title' => 'Services / Événements',   'description' => 'Gérez vos services additionnels et événements spéciaux.'],
            'planning'      => ['title' => 'Disponibilités / Planning','description' => 'Visualisez et gérez vos disponibilités sur un calendrier.'],
            'payments'      => ['title' => 'Paiements',               'description' => 'Suivez vos paiements et acomptes en temps réel.'],
            'notifications' => ['title' => 'Notifications',           'description' => 'Configurez vos notifications WhatsApp et email automatiques.'],
            'stats'         => ['title' => 'Statistiques / Reporting', 'description' => 'Analysez vos performances et générez des rapports détaillés.'],
        ];

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
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-admin.php';
        Riwa_PDF_Admin::admin_page();
    }
}

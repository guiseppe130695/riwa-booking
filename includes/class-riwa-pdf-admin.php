<?php
/**
 * Riwa_PDF_Admin — Interface d'administration du PDF
 *
 * Gère la page de configuration (onglets Général, Design, Contenu, Aperçu)
 * et l'accès aux options riwa_pdf_options.
 * Les styles sont dans assets/css/riwa-pdf-admin.css
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-generator.php';

class Riwa_PDF_Admin {

    public static function init() {
        add_action('admin_init',             [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts',  [__CLASS__, 'enqueue_admin_scripts']);
    }

    public static function register_settings() {
        register_setting('riwa_pdf_settings', 'riwa_pdf_options');

        add_settings_section('riwa_pdf_general', 'Paramètres généraux', [__CLASS__, 'general_section_callback'], 'riwa_pdf_settings');
        add_settings_section('riwa_pdf_design',  'Design et mise en page', [__CLASS__, 'design_section_callback'],  'riwa_pdf_settings');
        add_settings_section('riwa_pdf_content', 'Contenu du PDF',         [__CLASS__, 'content_section_callback'], 'riwa_pdf_settings');

        // Champs généraux
        $general_fields = [
            ['pdf_title',       'Titre du PDF',          'text',     'Confirmation de Réservation'],
            ['company_name',    'Nom de l\'entreprise',  'text',     'Riwa Villa'],
            ['company_address', 'Adresse',               'textarea', ''],
            ['company_phone',   'Téléphone',             'text',     ''],
            ['company_email',   'Email',                 'text',     ''],
        ];
        foreach ($general_fields as [$id, $label, $type, $default]) {
            add_settings_field($id, $label, [__CLASS__, $type . '_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_general', ['field' => $id, 'default' => $default]);
        }

        // Champs design
        add_settings_field('logo_url',      'Logo (URL)',                  [__CLASS__, 'text_field_callback'],   'riwa_pdf_settings', 'riwa_pdf_design', ['field' => 'logo_url',      'default' => '']);
        add_settings_field('primary_color', 'Couleur principale',          [__CLASS__, 'color_field_callback'],  'riwa_pdf_settings', 'riwa_pdf_design', ['field' => 'primary_color', 'default' => '#000000']);
        add_settings_field('secondary_color','Couleur secondaire',         [__CLASS__, 'color_field_callback'],  'riwa_pdf_settings', 'riwa_pdf_design', ['field' => 'secondary_color','default' => '#666666']);
        add_settings_field('font_family',   'Police principale',           [__CLASS__, 'select_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_design', [
            'field' => 'font_family', 'default' => 'helvetica',
            'options' => ['helvetica' => 'Helvetica', 'times' => 'Times', 'courier' => 'Courier', 'dejavusans' => 'DejaVu Sans', 'dejavuserif' => 'DejaVu Serif'],
        ]);
        add_settings_field('font_size',     'Taille de police par défaut', [__CLASS__, 'number_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_design', ['field' => 'font_size', 'default' => '10', 'min' => '8', 'max' => '16']);

        // Champs contenu
        $content_fields = [
            ['header_text',      'Texte d\'en-tête',        'textarea', 'Merci pour votre réservation !'],
            ['footer_text',      'Texte de pied de page',   'textarea', 'Pour toute question, contactez-nous.'],
            ['terms_conditions', 'Conditions générales',    'textarea', ''],
        ];
        foreach ($content_fields as [$id, $label, $type, $default]) {
            add_settings_field($id, $label, [__CLASS__, $type . '_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_content', ['field' => $id, 'default' => $default]);
        }
        add_settings_field('show_qr_code',  'Afficher le QR Code',   [__CLASS__, 'checkbox_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_content', ['field' => 'show_qr_code',  'default' => '1']);
        add_settings_field('show_signature','Afficher la signature',  [__CLASS__, 'checkbox_field_callback'], 'riwa_pdf_settings', 'riwa_pdf_content', ['field' => 'show_signature', 'default' => '1']);
    }

    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'riwa-bookings_page_riwa-pdf-settings') return;

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style('riwa-pdf-admin-css',
            RIWA_BOOKING_PLUGIN_URL . 'assets/css/riwa-pdf-admin.css',
            [], RIWA_BOOKING_VERSION
        );
        wp_enqueue_script('riwa-pdf-admin',
            RIWA_BOOKING_PLUGIN_URL . 'assets/js/riwa-pdf-admin.js',
            ['jquery', 'wp-color-picker'], RIWA_BOOKING_VERSION, true
        );
    }

    public static function admin_page() {
        $options = get_option('riwa_pdf_options', []);
        ?>
        <div class="wrap riwa-pdf-admin">
            <div class="riwa-admin-header">
                <div class="riwa-header-content">
                    <h1>Personnalisation PDF</h1>
                    <p class="riwa-subtitle">Configurez l'apparence de vos confirmations de réservation</p>
                </div>
                <div class="riwa-header-actions">
                    <button type="button" class="riwa-btn riwa-btn-primary" id="save-all-settings">
                        <span class="dashicons dashicons-saved"></span> Enregistrer
                    </button>
                    <button type="button" class="riwa-btn riwa-btn-secondary" id="test-pdf">
                        <span class="dashicons dashicons-pdf"></span> Tester PDF
                    </button>
                </div>
            </div>

            <div class="riwa-pdf-admin-container">
                <div class="riwa-nav-panel">
                    <div class="riwa-nav-header"><h3>Configuration</h3></div>
                    <nav class="riwa-nav-menu">
                        <a href="#general" class="riwa-nav-item active" data-section="general"><span class="dashicons dashicons-admin-generic"></span> Général</a>
                        <a href="#design"  class="riwa-nav-item" data-section="design"><span class="dashicons dashicons-art"></span> Design</a>
                        <a href="#content" class="riwa-nav-item" data-section="content"><span class="dashicons dashicons-edit"></span> Contenu</a>
                        <a href="#preview" class="riwa-nav-item" data-section="preview"><span class="dashicons dashicons-visibility"></span> Aperçu</a>
                    </nav>
                </div>

                <div class="riwa-content-panel">
                    <form method="post" action="options.php" id="riwa-pdf-form">
                        <?php settings_fields('riwa_pdf_settings'); ?>

                        <div class="riwa-section active" id="general-section">
                            <div class="riwa-section-header"><h2>Informations générales</h2><p>Configurez les informations de base de votre entreprise</p></div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <?php self::render_field('text',     'pdf_title',       'Titre du PDF',         'Confirmation de Réservation', $options); ?>
                                    <?php self::render_field('text',     'company_name',    'Nom de l\'entreprise', 'Riwa Villa',                  $options); ?>
                                    <?php self::render_field('text',     'company_phone',   'Téléphone',            '',                            $options); ?>
                                    <?php self::render_field('email',    'company_email',   'Email',                '',                            $options); ?>
                                    <?php self::render_field('textarea', 'company_address', 'Adresse',              '',                            $options, 'riwa-form-group-full'); ?>
                                </div>
                            </div>
                        </div>

                        <div class="riwa-section" id="design-section">
                            <div class="riwa-section-header"><h2>Design et mise en page</h2><p>Personnalisez l'apparence visuelle du PDF</p></div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <?php self::render_field('url',    'logo_url',       'Logo (URL)',              '', $options, '', 'https://…'); ?>
                                    <?php self::render_field('color',  'primary_color',  'Couleur principale',      '#000000', $options); ?>
                                    <?php self::render_field('color',  'secondary_color','Couleur secondaire',      '#666666', $options); ?>
                                    <div class="riwa-form-group">
                                        <label for="font_family">Police principale</label>
                                        <select id="font_family" name="riwa_pdf_options[font_family]">
                                            <?php $fv = $options['font_family'] ?? 'helvetica';
                                            foreach (['helvetica' => 'Helvetica', 'times' => 'Times', 'courier' => 'Courier', 'dejavusans' => 'DejaVu Sans', 'dejavuserif' => 'DejaVu Serif'] as $k => $l): ?>
                                                <option value="<?php echo esc_attr($k); ?>" <?php selected($fv, $k); ?>><?php echo esc_html($l); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <?php self::render_field('number', 'font_size', 'Taille de police', '10', $options, '', '', ['min' => 8, 'max' => 16]); ?>
                                </div>
                            </div>
                        </div>

                        <div class="riwa-section" id="content-section">
                            <div class="riwa-section-header"><h2>Contenu du PDF</h2><p>Personnalisez les textes et éléments affichés</p></div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <?php self::render_field('textarea', 'header_text',      'Texte d\'en-tête',      'Merci pour votre réservation !', $options, 'riwa-form-group-full'); ?>
                                    <?php self::render_field('textarea', 'footer_text',      'Texte de pied de page', 'Pour toute question, contactez-nous.', $options, 'riwa-form-group-full'); ?>
                                    <?php self::render_field('textarea', 'terms_conditions', 'Conditions générales',  '', $options, 'riwa-form-group-full'); ?>
                                    <div class="riwa-form-group">
                                        <label class="riwa-checkbox-label">
                                            <input type="checkbox" name="riwa_pdf_options[show_qr_code]" value="1" <?php checked($options['show_qr_code'] ?? '1', '1'); ?> />
                                            <span class="riwa-checkbox-text">Afficher le QR Code</span>
                                        </label>
                                    </div>
                                    <div class="riwa-form-group">
                                        <label class="riwa-checkbox-label">
                                            <input type="checkbox" name="riwa_pdf_options[show_signature]" value="1" <?php checked($options['show_signature'] ?? '1', '1'); ?> />
                                            <span class="riwa-checkbox-text">Afficher la signature</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="riwa-section" id="preview-section">
                            <div class="riwa-section-header"><h2>Aperçu en temps réel</h2><p>Visualisez vos modifications instantanément</p></div>
                            <div class="riwa-section-content">
                                <div class="riwa-preview-container">
                                    <div class="riwa-preview-header">
                                        <button type="button" class="riwa-btn riwa-btn-secondary" id="generate-preview"><span class="dashicons dashicons-update"></span> Actualiser l'aperçu</button>
                                        <button type="button" class="riwa-btn riwa-btn-primary"   id="test-pdf-compact"><span class="dashicons dashicons-pdf"></span> Tester PDF Compact</button>
                                        <button type="button" class="riwa-btn riwa-btn-secondary" id="show-diagnostic"><span class="dashicons dashicons-info"></span> Diagnostic</button>
                                    </div>
                                    <div class="riwa-preview-content">
                                        <div id="pdf-preview" class="riwa-preview-frame">
                                            <div class="riwa-preview-placeholder">
                                                <span class="dashicons dashicons-media-document"></span>
                                                <p>Aperçu du PDF</p>
                                                <small>Cliquez sur "Actualiser l'aperçu" pour voir le résultat</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="diagnostic-section" style="display:none;margin-top:2rem;padding:1.5rem;background:#f8f9fa;border-radius:6px;">
                                        <h3>Diagnostic des changements PDF</h3>
                                        <div id="diagnostic-content"><p>Cliquez sur "Diagnostic" pour vérifier les changements appliqués…</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers de rendu de champ                                        */
    /* ---------------------------------------------------------------- */

    /** Rendu générique d'un champ de formulaire dans admin_page() */
    private static function render_field($type, $id, $label, $default, $options, $extra_class = '', $placeholder = '', $attrs = []) {
        $value = $options[$id] ?? $default;
        $name  = 'riwa_pdf_options[' . $id . ']';
        echo '<div class="riwa-form-group ' . esc_attr($extra_class) . '">';
        echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
        if ($type === 'textarea') {
            echo '<textarea id="' . esc_attr($id) . '" name="' . $name . '" rows="3">' . esc_textarea($value) . '</textarea>';
        } else {
            $ph = $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '';
            $extra_attrs = '';
            foreach ($attrs as $k => $v) {
                $extra_attrs .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
            }
            echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . $name . '" value="' . esc_attr($value) . '"' . $ph . $extra_attrs . '>';
        }
        echo '</div>';
    }

    /* ---------------------------------------------------------------- */
    /*  Callbacks WordPress Settings API (conservés pour register_settings) */
    /* ---------------------------------------------------------------- */

    public static function general_section_callback() {
        echo '<p>Configurez les informations générales de votre entreprise qui apparaîtront sur le PDF.</p>';
    }
    public static function design_section_callback() {
        echo '<p>Personnalisez l\'apparence visuelle du PDF (couleurs, polices, logo).</p>';
    }
    public static function content_section_callback() {
        echo '<p>Personnalisez le contenu et les éléments affichés dans le PDF.</p>';
    }

    public static function text_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '');
        echo '<input type="text" name="riwa_pdf_options[' . $args['field'] . ']" value="' . esc_attr($value) . '" class="regular-text">';
    }
    public static function textarea_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '');
        echo '<textarea name="riwa_pdf_options[' . $args['field'] . ']" rows="4" cols="50">' . esc_textarea($value) . '</textarea>';
    }
    public static function color_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '#000000');
        echo '<input type="text" name="riwa_pdf_options[' . $args['field'] . ']" value="' . esc_attr($value) . '" class="color-picker">';
    }
    public static function select_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '');
        echo '<select name="riwa_pdf_options[' . $args['field'] . ']">';
        foreach ($args['options'] as $k => $l) {
            echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select>';
    }
    public static function number_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '10');
        echo '<input type="number" name="riwa_pdf_options[' . $args['field'] . ']" value="' . esc_attr($value) . '" min="' . ($args['min'] ?? 1) . '" max="' . ($args['max'] ?? 100) . '">';
    }
    public static function checkbox_field_callback($args) {
        $options = get_option('riwa_pdf_options', []);
        $value   = $options[$args['field']] ?? ($args['default'] ?? '0');
        echo '<input type="checkbox" name="riwa_pdf_options[' . $args['field'] . ']" value="1"' . checked('1', $value, false) . '>';
        echo '<span class="description">Activer cette option</span>';
    }

    /* ---------------------------------------------------------------- */
    /*  Options PDF (compatibilité ascendante)                           */
    /* ---------------------------------------------------------------- */

    public static function get_pdf_options() {
        return wp_parse_args(get_option('riwa_pdf_options', []), [
            'pdf_title'        => 'Confirmation de Réservation',
            'company_name'     => 'Riwa Villa',
            'company_address'  => '',
            'company_phone'    => '',
            'company_email'    => '',
            'logo_url'         => '',
            'primary_color'    => '#000000',
            'secondary_color'  => '#666666',
            'font_family'      => 'helvetica',
            'font_size'        => '10',
            'header_text'      => 'Merci pour votre réservation !',
            'footer_text'      => 'Pour toute question, contactez-nous.',
            'terms_conditions' => '',
            'show_qr_code'     => '1',
            'show_signature'   => '1',
        ]);
    }
}

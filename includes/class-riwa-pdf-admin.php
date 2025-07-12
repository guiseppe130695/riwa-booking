<?php
/**
 * Classe pour l'interface d'administration de personnalisation du PDF
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclure la classe PDF Generator
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-generator.php';

class Riwa_PDF_Admin {
    
    /**
     * Initialise l'interface d'administration
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    
    /**
     * Enregistre les paramètres
     */
    public static function register_settings() {
        register_setting('riwa_pdf_settings', 'riwa_pdf_options');
        
        add_settings_section(
            'riwa_pdf_general',
            'Paramètres généraux',
            array(__CLASS__, 'general_section_callback'),
            'riwa_pdf_settings'
        );
        
        add_settings_section(
            'riwa_pdf_design',
            'Design et mise en page',
            array(__CLASS__, 'design_section_callback'),
            'riwa_pdf_settings'
        );
        
        add_settings_section(
            'riwa_pdf_content',
            'Contenu du PDF',
            array(__CLASS__, 'content_section_callback'),
            'riwa_pdf_settings'
        );
        
        // Champs généraux
        add_settings_field(
            'pdf_title',
            'Titre du PDF',
            array(__CLASS__, 'text_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_general',
            array('field' => 'pdf_title', 'default' => 'Confirmation de Réservation')
        );
        
        add_settings_field(
            'company_name',
            'Nom de l\'entreprise',
            array(__CLASS__, 'text_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_general',
            array('field' => 'company_name', 'default' => 'Riwa Villa')
        );
        
        add_settings_field(
            'company_address',
            'Adresse de l\'entreprise',
            array(__CLASS__, 'textarea_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_general',
            array('field' => 'company_address', 'default' => '')
        );
        
        add_settings_field(
            'company_phone',
            'Téléphone',
            array(__CLASS__, 'text_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_general',
            array('field' => 'company_phone', 'default' => '')
        );
        
        add_settings_field(
            'company_email',
            'Email',
            array(__CLASS__, 'text_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_general',
            array('field' => 'company_email', 'default' => '')
        );
        
        // Champs de design
        add_settings_field(
            'logo_url',
            'Logo (URL)',
            array(__CLASS__, 'text_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_design',
            array('field' => 'logo_url', 'default' => '')
        );
        
        add_settings_field(
            'primary_color',
            'Couleur principale',
            array(__CLASS__, 'color_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_design',
            array('field' => 'primary_color', 'default' => '#000000')
        );
        
        add_settings_field(
            'secondary_color',
            'Couleur secondaire',
            array(__CLASS__, 'color_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_design',
            array('field' => 'secondary_color', 'default' => '#666666')
        );
        
        add_settings_field(
            'font_family',
            'Police principale',
            array(__CLASS__, 'select_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_design',
            array(
                'field' => 'font_family',
                'default' => 'helvetica',
                'options' => array(
                    'helvetica' => 'Helvetica',
                    'times' => 'Times',
                    'courier' => 'Courier',
                    'dejavusans' => 'DejaVu Sans',
                    'dejavuserif' => 'DejaVu Serif'
                )
            )
        );
        
        add_settings_field(
            'font_size',
            'Taille de police par défaut',
            array(__CLASS__, 'number_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_design',
            array('field' => 'font_size', 'default' => '10', 'min' => '8', 'max' => '16')
        );
        
        // Champs de contenu
        add_settings_field(
            'header_text',
            'Texte d\'en-tête',
            array(__CLASS__, 'textarea_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_content',
            array('field' => 'header_text', 'default' => 'Merci pour votre réservation !')
        );
        
        add_settings_field(
            'footer_text',
            'Texte de pied de page',
            array(__CLASS__, 'textarea_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_content',
            array('field' => 'footer_text', 'default' => 'Pour toute question, contactez-nous.')
        );
        
        add_settings_field(
            'terms_conditions',
            'Conditions générales',
            array(__CLASS__, 'textarea_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_content',
            array('field' => 'terms_conditions', 'default' => '')
        );
        
        add_settings_field(
            'show_qr_code',
            'Afficher le QR Code',
            array(__CLASS__, 'checkbox_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_content',
            array('field' => 'show_qr_code', 'default' => '1')
        );
        
        add_settings_field(
            'show_signature',
            'Afficher la signature',
            array(__CLASS__, 'checkbox_field_callback'),
            'riwa_pdf_settings',
            'riwa_pdf_content',
            array('field' => 'show_signature', 'default' => '1')
        );
    }
    
    /**
     * Charge les scripts d'administration
     */
    public static function enqueue_admin_scripts($hook) {
        if ($hook !== 'riwa-bookings_page_riwa-pdf-settings') {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();
        
        wp_enqueue_script(
            'riwa-pdf-admin',
            plugin_dir_url(__FILE__) . '../assets/js/riwa-pdf-admin.js',
            array('jquery', 'wp-color-picker'),
            '1.0.0',
            true
        );
    }
    
    /**
     * Page d'administration
     */
    public static function admin_page() {
        $options = get_option('riwa_pdf_options', array());
        ?>
        <div class="wrap riwa-pdf-admin">
            <div class="riwa-admin-header">
                <div class="riwa-header-content">
                    <h1>Personnalisation PDF</h1>
                    <p class="riwa-subtitle">Configurez l'apparence de vos confirmations de réservation</p>
                </div>
                <div class="riwa-header-actions">
                    <button type="button" class="riwa-btn riwa-btn-primary" id="save-all-settings">
                        <span class="dashicons dashicons-saved"></span>
                        Enregistrer
                    </button>
                    <button type="button" class="riwa-btn riwa-btn-secondary" id="test-pdf">
                        <span class="dashicons dashicons-pdf"></span>
                        Tester PDF
                    </button>
                </div>
            </div>
            
            <div class="riwa-pdf-admin-container">
                <!-- Panneau de navigation -->
                <div class="riwa-nav-panel">
                    <div class="riwa-nav-header">
                        <h3>Configuration</h3>
                    </div>
                    <nav class="riwa-nav-menu">
                        <a href="#general" class="riwa-nav-item active" data-section="general">
                            <span class="dashicons dashicons-admin-generic"></span>
                            Général
                        </a>
                        <a href="#design" class="riwa-nav-item" data-section="design">
                            <span class="dashicons dashicons-art"></span>
                            Design
                        </a>
                        <a href="#content" class="riwa-nav-item" data-section="content">
                            <span class="dashicons dashicons-edit"></span>
                            Contenu
                        </a>
                        <a href="#preview" class="riwa-nav-item" data-section="preview">
                            <span class="dashicons dashicons-visibility"></span>
                            Aperçu
                        </a>
                    </nav>
                </div>
                
                <!-- Panneau de contenu -->
                <div class="riwa-content-panel">
                    <form method="post" action="options.php" id="riwa-pdf-form">
                        <?php settings_fields('riwa_pdf_settings'); ?>
                        
                        <!-- Section Général -->
                        <div class="riwa-section active" id="general-section">
                            <div class="riwa-section-header">
                                <h2>Informations générales</h2>
                                <p>Configurez les informations de base de votre entreprise</p>
                            </div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <div class="riwa-form-group">
                                        <label for="pdf_title">Titre du PDF</label>
                                        <input type="text" id="pdf_title" name="riwa_pdf_options[pdf_title]" value="<?php echo esc_attr(isset($options['pdf_title']) ? $options['pdf_title'] : 'Confirmation de Réservation'); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="company_name">Nom de l'entreprise</label>
                                        <input type="text" id="company_name" name="riwa_pdf_options[company_name]" value="<?php echo esc_attr(isset($options['company_name']) ? $options['company_name'] : 'Riwa Villa'); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="company_phone">Téléphone</label>
                                        <input type="text" id="company_phone" name="riwa_pdf_options[company_phone]" value="<?php echo esc_attr(isset($options['company_phone']) ? $options['company_phone'] : ''); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="company_email">Email</label>
                                        <input type="email" id="company_email" name="riwa_pdf_options[company_email]" value="<?php echo esc_attr(isset($options['company_email']) ? $options['company_email'] : ''); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group riwa-form-group-full">
                                        <label for="company_address">Adresse</label>
                                        <textarea id="company_address" name="riwa_pdf_options[company_address]" rows="3"><?php echo esc_textarea(isset($options['company_address']) ? $options['company_address'] : ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section Design -->
                        <div class="riwa-section" id="design-section">
                            <div class="riwa-section-header">
                                <h2>Design et mise en page</h2>
                                <p>Personnalisez l'apparence visuelle du PDF</p>
                            </div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <div class="riwa-form-group">
                                        <label for="logo_url">Logo (URL)</label>
                                        <input type="url" id="logo_url" name="riwa_pdf_options[logo_url]" value="<?php echo esc_attr(isset($options['logo_url']) ? $options['logo_url'] : ''); ?>" placeholder="https://..." />
                                        <small>URL de votre logo (max 50px de hauteur)</small>
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="primary_color">Couleur principale</label>
                                        <input type="color" id="primary_color" name="riwa_pdf_options[primary_color]" value="<?php echo esc_attr(isset($options['primary_color']) ? $options['primary_color'] : '#000000'); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="secondary_color">Couleur secondaire</label>
                                        <input type="color" id="secondary_color" name="riwa_pdf_options[secondary_color]" value="<?php echo esc_attr(isset($options['secondary_color']) ? $options['secondary_color'] : '#666666'); ?>" />
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="font_family">Police principale</label>
                                        <select id="font_family" name="riwa_pdf_options[font_family]">
                                            <option value="helvetica" <?php selected(isset($options['font_family']) ? $options['font_family'] : 'helvetica', 'helvetica'); ?>>Helvetica</option>
                                            <option value="times" <?php selected(isset($options['font_family']) ? $options['font_family'] : 'helvetica', 'times'); ?>>Times</option>
                                            <option value="courier" <?php selected(isset($options['font_family']) ? $options['font_family'] : 'helvetica', 'courier'); ?>>Courier</option>
                                            <option value="dejavusans" <?php selected(isset($options['font_family']) ? $options['font_family'] : 'helvetica', 'dejavusans'); ?>>DejaVu Sans</option>
                                            <option value="dejavuserif" <?php selected(isset($options['font_family']) ? $options['font_family'] : 'helvetica', 'dejavuserif'); ?>>DejaVu Serif</option>
                                        </select>
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label for="font_size">Taille de police</label>
                                        <input type="number" id="font_size" name="riwa_pdf_options[font_size]" value="<?php echo esc_attr(isset($options['font_size']) ? $options['font_size'] : '10'); ?>" min="8" max="16" />
                                        <small>Taille par défaut (8-16)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section Contenu -->
                        <div class="riwa-section" id="content-section">
                            <div class="riwa-section-header">
                                <h2>Contenu du PDF</h2>
                                <p>Personnalisez les textes et éléments affichés</p>
                            </div>
                            <div class="riwa-section-content">
                                <div class="riwa-form-grid">
                                    <div class="riwa-form-group riwa-form-group-full">
                                        <label for="header_text">Texte d'en-tête</label>
                                        <textarea id="header_text" name="riwa_pdf_options[header_text]" rows="2"><?php echo esc_textarea(isset($options['header_text']) ? $options['header_text'] : 'Merci pour votre réservation !'); ?></textarea>
                                    </div>
                                    
                                    <div class="riwa-form-group riwa-form-group-full">
                                        <label for="footer_text">Texte de pied de page</label>
                                        <textarea id="footer_text" name="riwa_pdf_options[footer_text]" rows="2"><?php echo esc_textarea(isset($options['footer_text']) ? $options['footer_text'] : 'Pour toute question, contactez-nous.'); ?></textarea>
                                    </div>
                                    
                                    <div class="riwa-form-group riwa-form-group-full">
                                        <label for="terms_conditions">Conditions générales</label>
                                        <textarea id="terms_conditions" name="riwa_pdf_options[terms_conditions]" rows="4"><?php echo esc_textarea(isset($options['terms_conditions']) ? $options['terms_conditions'] : ''); ?></textarea>
                                        <small>Optionnel - Affiché en petit texte</small>
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label class="riwa-checkbox-label">
                                            <input type="checkbox" name="riwa_pdf_options[show_qr_code]" value="1" <?php checked(isset($options['show_qr_code']) ? $options['show_qr_code'] : '1', '1'); ?> />
                                            <span class="riwa-checkbox-text">Afficher le QR Code</span>
                                        </label>
                                    </div>
                                    
                                    <div class="riwa-form-group">
                                        <label class="riwa-checkbox-label">
                                            <input type="checkbox" name="riwa_pdf_options[show_signature]" value="1" <?php checked(isset($options['show_signature']) ? $options['show_signature'] : '1', '1'); ?> />
                                            <span class="riwa-checkbox-text">Afficher la signature</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section Aperçu -->
                        <div class="riwa-section" id="preview-section">
                            <div class="riwa-section-header">
                                <h2>Aperçu en temps réel</h2>
                                <p>Visualisez vos modifications instantanément</p>
                            </div>
                            <div class="riwa-section-content">
                                <div class="riwa-preview-container">
                                    <div class="riwa-preview-header">
                                        <button type="button" class="riwa-btn riwa-btn-secondary" id="generate-preview">
                                            <span class="dashicons dashicons-update"></span>
                                            Actualiser l'aperçu
                                        </button>
                                        <button type="button" class="riwa-btn riwa-btn-primary" id="test-pdf-compact">
                                            <span class="dashicons dashicons-pdf"></span>
                                            Tester PDF Compact
                                        </button>
                                        <button type="button" class="riwa-btn riwa-btn-secondary" id="show-diagnostic">
                                            <span class="dashicons dashicons-info"></span>
                                            Diagnostic
                                        </button>
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
                                </div>
                                
                                <!-- Section de diagnostic -->
                                <div id="diagnostic-section" style="display: none; margin-top: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 6px;">
                                    <h3>Diagnostic des changements PDF</h3>
                                    <div id="diagnostic-content">
                                        <p>Cliquez sur "Diagnostic" pour vérifier les changements appliqués...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
            /* Design moderne et minimaliste */
            .riwa-pdf-admin {
                background: #f8f9fa;
                min-height: 100vh;
                margin: -20px -20px 0 -20px;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
            
            /* En-tête principal */
            .riwa-admin-header {
                background: white;
                padding: 1.5rem 2rem;
                border-bottom: 1px solid #e1e5e9;
                display: flex;
                justify-content: space-between;
                align-items: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            
            .riwa-header-content h1 {
                margin: 0 0 0.25rem 0;
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .riwa-subtitle {
                margin: 0;
                color: #646970;
                font-size: 14px;
                font-weight: 400;
            }
            
            .riwa-header-actions {
                display: flex;
                gap: 0.75rem;
            }
            
            /* Container principal */
            .riwa-pdf-admin-container {
                display: flex;
                height: calc(100vh - 100px);
            }
            
            /* Panneau de navigation */
            .riwa-nav-panel {
                width: 225px;
                background: white;
                border-right: 1px solid #e1e5e9;
                display: flex;
                flex-direction: column;
            }
            
            .riwa-nav-header {
                padding: 1.5rem;
                border-bottom: 1px solid #e1e5e9;
            }
            
            .riwa-nav-header h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .riwa-nav-menu {
                flex: 1;
                padding: 1rem 0;
            }
            
            .riwa-nav-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.875rem 1.5rem;
                color: #646970;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
                border-left: 3px solid transparent;
            }
            
            .riwa-nav-item:hover {
                background: #f6f7f7;
                color: #1d2327;
                border-left-color: #2271b1;
            }
            
            .riwa-nav-item.active {
                background: #f0f6fc;
                color: #2271b1;
                border-left-color: #2271b1;
            }
            
            .riwa-nav-item .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            
            /* Panneau de contenu */
            .riwa-content-panel {
                flex: 1;
                overflow-y: auto;
                background: #f8f9fa;
            }
            
            .riwa-section {
                display: none;
                padding: 2rem;
            }
            
            .riwa-section.active {
                display: block;
            }
            
            .riwa-section-header {
                margin-bottom: 2rem;
            }
            
            .riwa-section-header h2 {
                margin: 0 0 0.5rem 0;
                font-size: 20px;
                font-weight: 600;
                color: #1d2327;
            }
            
            .riwa-section-header p {
                margin: 0;
                color: #646970;
                font-size: 14px;
            }
            
            .riwa-section-content {
                background: white;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            /* Grille de formulaire */
            .riwa-form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
                padding: 2rem;
            }
            
            .riwa-form-group-full {
                grid-column: 1 / -1;
            }
            
            .riwa-form-group {
                display: flex;
                flex-direction: column;
            }
            
            .riwa-form-group label {
                font-size: 14px;
                font-weight: 500;
                color: #1d2327;
                margin-bottom: 0.5rem;
            }
            
            .riwa-form-group input,
            .riwa-form-group select,
            .riwa-form-group textarea {
                padding: 0.75rem;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                font-size: 14px;
                transition: all 0.2s ease;
                background: white;
            }
            
            .riwa-form-group input:focus,
            .riwa-form-group select:focus,
            .riwa-form-group textarea:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: none;
            }
            
            .riwa-form-group small {
                margin-top: 0.25rem;
                font-size: 12px;
                color: #646970;
            }
            
            /* Checkbox personnalisé */
            .riwa-checkbox-label {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                cursor: pointer;
                padding: 0.75rem;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                transition: all 0.2s ease;
            }
            
            .riwa-checkbox-label:hover {
                background: #f6f7f7;
                border-color: #2271b1;
            }
            
            .riwa-checkbox-label input[type="checkbox"] {
                margin: 0;
                width: 18px;
                height: 18px;
            }
            
            .riwa-checkbox-text {
                font-size: 14px;
                font-weight: 500;
                color: #1d2327;
            }
            
            /* Boutons */
            .riwa-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.25rem;
                border: 1px solid #dcdcde;
                border-radius: 6px;
                background: white;
                color: #1d2327;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .riwa-btn:hover {
                background: #f6f7f7;
                border-color: #8c8f94;
            }
            
            .riwa-btn-primary {
                background: #2271b1;
                border-color: #2271b1;
                color: white;
            }
            
            .riwa-btn-primary:hover {
                background: #135e96;
                border-color: #135e96;
            }
            
            .riwa-btn-secondary {
                background: #f6f7f7;
                border-color: #dcdcde;
            }
            
            .riwa-btn-secondary:hover {
                background: #f0f0f1;
                border-color: #8c8f94;
            }
            
            /* Aperçu */
            .riwa-preview-container {
                padding: 2rem;
            }
            
            .riwa-preview-header {
                margin-bottom: 1.5rem;
            }
            
            .riwa-preview-content {
                background: white;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .riwa-preview-frame {
                min-height: 500px;
                max-height: 600px;
                overflow-y: auto;
                padding: 2rem;
            }
            
            .riwa-preview-placeholder {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 400px;
                color: #646970;
                text-align: center;
            }
            
            .riwa-preview-placeholder .dashicons {
                font-size: 48px;
                margin-bottom: 1rem;
                opacity: 0.5;
            }
            
            .riwa-preview-placeholder p {
                margin: 0 0 0.5rem 0;
                font-weight: 500;
                font-size: 16px;
            }
            
            .riwa-preview-placeholder small {
                opacity: 0.7;
                font-size: 14px;
            }
            
            /* Responsive */
            @media (max-width: 1200px) {
                .riwa-pdf-admin-container {
                    flex-direction: column;
                }
                
                .riwa-nav-panel {
                    width: 100%;
                    border-right: none;
                    border-bottom: 1px solid #e1e5e9;
                }
                
                .riwa-nav-menu {
                    display: flex;
                    overflow-x: auto;
                    padding: 1rem;
                }
                
                .riwa-nav-item {
                    flex-shrink: 0;
                    border-left: none;
                    border-bottom: 3px solid transparent;
                    padding: 0.75rem 1rem;
                }
                
                .riwa-nav-item.active {
                    border-bottom-color: #2271b1;
                }
            }
            
            @media (max-width: 768px) {
                .riwa-admin-header {
                    flex-direction: column;
                    gap: 1rem;
                    align-items: flex-start;
                }
                
                .riwa-header-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
                
                .riwa-form-grid {
                    grid-template-columns: 1fr;
                    gap: 1rem;
                    padding: 1.5rem;
                }
                
                .riwa-section {
                    padding: 1rem;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Callbacks pour les sections
     */
    public static function general_section_callback() {
        echo '<p>Configurez les informations générales de votre entreprise qui apparaîtront sur le PDF.</p>';
    }
    
    public static function design_section_callback() {
        echo '<p>Personnalisez l\'apparence visuelle du PDF (couleurs, polices, logo).</p>';
    }
    
    public static function content_section_callback() {
        echo '<p>Personnalisez le contenu et les éléments affichés dans le PDF.</p>';
    }
    
    /**
     * Callbacks pour les champs
     */
    public static function text_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '';
        $value = isset($options[$field]) ? $options[$field] : $default;
        
        echo '<input type="text" name="riwa_pdf_options[' . $field . ']" value="' . esc_attr($value) . '" class="regular-text" />';
    }
    
    public static function textarea_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '';
        $value = isset($options[$field]) ? $options[$field] : $default;
        
        echo '<textarea name="riwa_pdf_options[' . $field . ']" rows="4" cols="50">' . esc_textarea($value) . '</textarea>';
    }
    
    public static function color_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '#000000';
        $value = isset($options[$field]) ? $options[$field] : $default;
        
        echo '<input type="text" name="riwa_pdf_options[' . $field . ']" value="' . esc_attr($value) . '" class="color-picker" />';
    }
    
    public static function select_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '';
        $value = isset($options[$field]) ? $options[$field] : $default;
        $select_options = $args['options'];
        
        echo '<select name="riwa_pdf_options[' . $field . ']">';
        foreach ($select_options as $key => $label) {
            $selected = ($value === $key) ? 'selected' : '';
            echo '<option value="' . $key . '" ' . $selected . '>' . $label . '</option>';
        }
        echo '</select>';
    }
    
    public static function number_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '10';
        $value = isset($options[$field]) ? $options[$field] : $default;
        $min = isset($args['min']) ? $args['min'] : '1';
        $max = isset($args['max']) ? $args['max'] : '100';
        
        echo '<input type="number" name="riwa_pdf_options[' . $field . ']" value="' . esc_attr($value) . '" min="' . $min . '" max="' . $max . '" />';
    }
    
    public static function checkbox_field_callback($args) {
        $options = get_option('riwa_pdf_options', array());
        $field = $args['field'];
        $default = isset($args['default']) ? $args['default'] : '0';
        $value = isset($options[$field]) ? $options[$field] : $default;
        
        echo '<input type="checkbox" name="riwa_pdf_options[' . $field . ']" value="1" ' . checked('1', $value, false) . ' />';
        echo '<span class="description">Activer cette option</span>';
    }
    
    /**
     * Récupère les options du PDF
     */
    public static function get_pdf_options() {
        $defaults = array(
            'pdf_title' => 'Confirmation de Réservation',
            'company_name' => 'Riwa Villa',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'logo_url' => '',
            'primary_color' => '#000000',
            'secondary_color' => '#666666',
            'font_family' => 'helvetica',
            'font_size' => '10',
            'header_text' => 'Merci pour votre réservation !',
            'footer_text' => 'Pour toute question, contactez-nous.',
            'terms_conditions' => '',
            'show_qr_code' => '1',
            'show_signature' => '1'
        );
        
        $options = get_option('riwa_pdf_options', array());
        return wp_parse_args($options, $defaults);
    }
} 
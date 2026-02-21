<?php
/**
 * Riwa_PDF_Studio — Noyau du Doc Studio
 *
 * Gère les layouts JSON, les settings globaux, la numérotation séquentielle
 * et les handlers AJAX. Le rendu HTML est délégué à Riwa_PDF_Studio_Renderer.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_PDF_Studio {

    /** Types de documents supportés */
    public static $doc_types = ['confirmation', 'facture', 'devis', 'contrat', 'rapport'];

    /** Labels des types de documents */
    public static $doc_labels = [
        'confirmation' => 'Confirmation de réservation',
        'facture'      => 'Facture client',
        'devis'        => 'Devis',
        'contrat'      => 'Contrat de location',
        'rapport'      => 'Rapport propriétaire',
    ];

    /** Préfixes de numérotation */
    public static $doc_prefixes = [
        'confirmation' => 'CONF',
        'facture'      => 'FAC',
        'devis'        => 'DEV',
        'contrat'      => 'CTR',
        'rapport'      => 'RPT',
    ];

    /** Labels des blocs */
    public static $block_labels = [
        'header'    => 'En-tête',
        'company'   => 'Entreprise',
        'client'    => 'Client',
        'stay'      => 'Séjour',
        'travelers' => 'Voyageurs',
        'pricing'   => 'Tarifs',
        'text'      => 'Texte libre',
        'signature' => 'Signature',
        'qr'        => 'QR Code',
        'footer'    => 'Pied de page',
    ];

    /* ---------------------------------------------------------------- */
    /*  Layouts JSON                                                      */
    /* ---------------------------------------------------------------- */

    public static function get_layout($type) {
        $saved = get_option('riwa_pdf_layout_' . $type, null);
        if ($saved && is_string($saved)) {
            $decoded = json_decode($saved, true);
            if (is_array($decoded) && !empty($decoded['rows'])) {
                return $decoded;
            }
        }
        return self::get_default_layout($type);
    }

    public static function save_layout($type, $layout) {
        if (!in_array($type, self::$doc_types, true)) return false;
        $json = is_string($layout) ? $layout : wp_json_encode($layout);
        return update_option('riwa_pdf_layout_' . $type, $json);
    }

    public static function get_default_layout($type) {
        $base = [
            'rows' => [
                ['id' => 'r1', 'blocks' => [['id' => 'b1', 'type' => 'header',    'span' => 2]]],
                ['id' => 'r2', 'blocks' => [['id' => 'b2', 'type' => 'company',   'span' => 1], ['id' => 'b3', 'type' => 'client',  'span' => 1]]],
                ['id' => 'r3', 'blocks' => [['id' => 'b4', 'type' => 'stay',      'span' => 1], ['id' => 'b5', 'type' => 'travelers','span' => 1]]],
                ['id' => 'r4', 'blocks' => [['id' => 'b6', 'type' => 'pricing',   'span' => 2]]],
                ['id' => 'r5', 'blocks' => [['id' => 'b7', 'type' => 'footer',    'span' => 2]]],
            ],
        ];

        $contrat = [
            'rows' => [
                ['id' => 'r1', 'blocks' => [['id' => 'b1', 'type' => 'header',    'span' => 2]]],
                ['id' => 'r2', 'blocks' => [['id' => 'b2', 'type' => 'company',   'span' => 1], ['id' => 'b3', 'type' => 'client', 'span' => 1]]],
                ['id' => 'r3', 'blocks' => [['id' => 'b4', 'type' => 'stay',      'span' => 2]]],
                ['id' => 'r4', 'blocks' => [['id' => 'b5', 'type' => 'pricing',   'span' => 2]]],
                ['id' => 'r5', 'blocks' => [['id' => 'b6', 'type' => 'text',      'span' => 2, 'config' => ['content' => "Conditions générales de location :\n1. Le locataire s'engage à respecter le règlement intérieur.\n2. Caution à verser à l'arrivée.\n3. L'hébergement est non-fumeur."]]]],
                ['id' => 'r6', 'blocks' => [['id' => 'b7', 'type' => 'signature', 'span' => 1], ['id' => 'b8', 'type' => 'qr', 'span' => 1]]],
                ['id' => 'r7', 'blocks' => [['id' => 'b9', 'type' => 'footer',    'span' => 2]]],
            ],
        ];

        $rapport = [
            'rows' => [
                ['id' => 'r1', 'blocks' => [['id' => 'b1', 'type' => 'header',  'span' => 2, 'config' => ['subtitle' => 'Rapport propriétaire mensuel']]]],
                ['id' => 'r2', 'blocks' => [['id' => 'b2', 'type' => 'company', 'span' => 2]]],
                ['id' => 'r3', 'blocks' => [['id' => 'b3', 'type' => 'pricing', 'span' => 2]]],
                ['id' => 'r4', 'blocks' => [['id' => 'b4', 'type' => 'text',    'span' => 2, 'config' => ['content' => 'Détail des séjours du mois à renseigner.']]]],
                ['id' => 'r5', 'blocks' => [['id' => 'b5', 'type' => 'footer',  'span' => 2]]],
            ],
        ];

        switch ($type) {
            case 'contrat': return $contrat;
            case 'rapport': return $rapport;
            default:        return $base;
        }
    }

    /* ---------------------------------------------------------------- */
    /*  Settings globaux                                                  */
    /* ---------------------------------------------------------------- */

    public static function get_settings() {
        $legacy  = get_option('riwa_pdf_options', []);
        $defaults = [
            'company_name'    => $legacy['company_name']    ?? 'Riwa Villa',
            'company_address' => $legacy['company_address'] ?? '',
            'company_phone'   => $legacy['company_phone']   ?? '',
            'company_email'   => $legacy['company_email']   ?? '',
            'company_ice'     => '',
            'company_rc'      => '',
            'company_if'      => '',
            'company_patente' => '',
            'logo_url'        => $legacy['logo_url']        ?? '',
            'primary_color'   => $legacy['primary_color']   ?? '#1a1a2e',
            'font_family'     => $legacy['font_family']     ?? 'helvetica',
            'font_size'       => $legacy['font_size']       ?? '10',
            'footer_text'     => $legacy['footer_text']     ?? 'Pour toute question, contactez-nous.',
        ];
        $saved = get_option('riwa_pdf_settings', []);
        if (!is_array($saved)) $saved = [];
        return array_merge($defaults, $saved);
    }

    public static function save_settings($data) {
        $allowed = [
            'company_name', 'company_address', 'company_phone', 'company_email',
            'company_ice', 'company_rc', 'company_if', 'company_patente',
            'logo_url', 'primary_color', 'font_family', 'font_size', 'footer_text',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            if (!isset($data[$key])) continue;
            $clean[$key] = ($key === 'logo_url')
                ? esc_url_raw($data[$key])
                : sanitize_text_field($data[$key]);
        }
        if (isset($clean['primary_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $clean['primary_color'])) {
            $clean['primary_color'] = '#1a1a2e';
        }
        return update_option('riwa_pdf_settings', $clean);
    }

    /* ---------------------------------------------------------------- */
    /*  Numérotation séquentielle                                        */
    /* ---------------------------------------------------------------- */

    public static function get_next_number($type) {
        $numbering = get_option('riwa_pdf_numbering', []);
        $year = (int) date('Y');

        if (empty($numbering[$type]) || ($numbering[$type]['year'] ?? 0) !== $year) {
            $numbering[$type] = [
                'prefix'  => self::$doc_prefixes[$type] ?? strtoupper(substr($type, 0, 3)),
                'counter' => 1,
                'year'    => $year,
            ];
        }

        $num = $numbering[$type];
        $ref = $num['prefix'] . '-' . $year . '-' . str_pad($num['counter'], 3, '0', STR_PAD_LEFT);
        $numbering[$type]['counter']++;
        update_option('riwa_pdf_numbering', $numbering);
        return $ref;
    }

    /* ---------------------------------------------------------------- */
    /*  Rendu HTML — délégué au renderer                                 */
    /* ---------------------------------------------------------------- */

    /**
     * Alias vers Riwa_PDF_Studio_Renderer::render() pour la compatibilité ascendante.
     */
    public static function render_layout_html($type, $booking, $settings, $preview = false) {
        return Riwa_PDF_Studio_Renderer::render($type, $booking, $settings, $preview);
    }

    /**
     * Alias vers Riwa_PDF_Studio_Renderer::render_block() pour la compatibilité ascendante.
     */
    public static function render_block_html($block, $booking, $settings, $doc_label = '', $preview = false) {
        return Riwa_PDF_Studio_Renderer::render_block($block, $booking, $settings, $doc_label, $preview);
    }

    /* ---------------------------------------------------------------- */
    /*  AJAX handlers                                                    */
    /* ---------------------------------------------------------------- */

    public static function ajax_save_layout() {
        Riwa_Security::check_admin('riwa_studio_nonce');

        $type   = sanitize_key($_POST['doc_type'] ?? '');
        $layout = stripslashes($_POST['layout'] ?? '');

        if (!in_array($type, self::$doc_types, true)) {
            wp_send_json_error('Type de document invalide');
        }

        $decoded = json_decode($layout, true);
        if (!$decoded || !isset($decoded['rows'])) {
            wp_send_json_error('Layout JSON invalide');
        }

        self::save_layout($type, $layout);
        wp_send_json_success(['saved' => true, 'type' => $type]);
    }

    public static function ajax_preview() {
        Riwa_Security::check_admin('riwa_studio_nonce');

        $type        = sanitize_key($_POST['doc_type'] ?? 'confirmation');
        $settings    = self::get_settings();
        $layout_json = stripslashes($_POST['layout'] ?? '');

        if ($layout_json) {
            $decoded = json_decode($layout_json, true);
            if ($decoded && isset($decoded['rows'])) {
                update_option('riwa_pdf_layout_' . $type . '_preview', $layout_json);
            }
        }

        $html = self::render_layout_html($type, null, $settings, true);
        wp_send_json_success(['html' => $html]);
    }

    public static function ajax_save_settings() {
        Riwa_Security::check_admin('riwa_studio_nonce');

        $data = $_POST['settings'] ?? [];
        if (is_string($data)) {
            $decoded = json_decode(stripslashes($data), true);
            if (is_array($decoded)) $data = $decoded;
        }

        if (is_array($data)) {
            self::save_settings($data);
            wp_send_json_success(['saved' => true]);
        } else {
            wp_send_json_error('Données invalides');
        }
    }

    public static function ajax_get_all_layouts() {
        Riwa_Security::check_admin('riwa_studio_nonce');

        $layouts = [];
        foreach (self::$doc_types as $type) {
            $layouts[$type] = self::get_layout($type);
        }

        wp_send_json_success([
            'layouts'  => $layouts,
            'settings' => self::get_settings(),
        ]);
    }

    public static function ajax_reset_layout() {
        Riwa_Security::check_admin('riwa_studio_nonce');

        $type = sanitize_key($_POST['doc_type'] ?? '');
        if (!in_array($type, self::$doc_types, true)) {
            wp_send_json_error('Type invalide');
        }

        delete_option('riwa_pdf_layout_' . $type);
        wp_send_json_success(['layout' => self::get_default_layout($type)]);
    }
}

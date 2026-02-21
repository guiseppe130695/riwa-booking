<?php
/**
 * Riwa Doc Studio — Éditeur PDF Canva-like
 * Gère les layouts JSON, les settings globaux, la numérotation et le rendu HTML/PDF
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
        $uid = function($prefix) {
            return $prefix . '-' . substr(md5(uniqid()), 0, 6);
        };

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
                ['id' => 'r2', 'blocks' => [['id' => 'b2', 'type' => 'company',   'span' => 1], ['id' => 'b3', 'type' => 'client',  'span' => 1]]],
                ['id' => 'r3', 'blocks' => [['id' => 'b4', 'type' => 'stay',      'span' => 2]]],
                ['id' => 'r4', 'blocks' => [['id' => 'b5', 'type' => 'pricing',   'span' => 2]]],
                ['id' => 'r5', 'blocks' => [['id' => 'b6', 'type' => 'text',      'span' => 2, 'config' => ['content' => "Conditions générales de location :\n1. Le locataire s'engage à respecter le règlement intérieur.\n2. Caution à verser à l'arrivée.\n3. L'hébergement est non-fumeur."]], ]],
                ['id' => 'r6', 'blocks' => [['id' => 'b7', 'type' => 'signature', 'span' => 1], ['id' => 'b8', 'type' => 'qr', 'span' => 1]]],
                ['id' => 'r7', 'blocks' => [['id' => 'b9', 'type' => 'footer',    'span' => 2]]],
            ],
        ];

        $rapport = [
            'rows' => [
                ['id' => 'r1', 'blocks' => [['id' => 'b1', 'type' => 'header',    'span' => 2, 'config' => ['subtitle' => 'Rapport propriétaire mensuel']]]],
                ['id' => 'r2', 'blocks' => [['id' => 'b2', 'type' => 'company',   'span' => 2]]],
                ['id' => 'r3', 'blocks' => [['id' => 'b3', 'type' => 'pricing',   'span' => 2]]],
                ['id' => 'r4', 'blocks' => [['id' => 'b4', 'type' => 'text',      'span' => 2, 'config' => ['content' => 'Détail des séjours du mois à renseigner.']]]],
                ['id' => 'r5', 'blocks' => [['id' => 'b5', 'type' => 'footer',    'span' => 2]]],
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
        $defaults = [
            'company_name'    => get_option('riwa_pdf_options', [])['company_name'] ?? 'Riwa Villa',
            'company_address' => get_option('riwa_pdf_options', [])['company_address'] ?? '',
            'company_phone'   => get_option('riwa_pdf_options', [])['company_phone'] ?? '',
            'company_email'   => get_option('riwa_pdf_options', [])['company_email'] ?? '',
            'company_ice'     => '',
            'company_rc'      => '',
            'company_if'      => '',
            'company_patente' => '',
            'logo_url'        => get_option('riwa_pdf_options', [])['logo_url'] ?? '',
            'primary_color'   => get_option('riwa_pdf_options', [])['primary_color'] ?? '#1a1a2e',
            'font_family'     => get_option('riwa_pdf_options', [])['font_family'] ?? 'helvetica',
            'font_size'       => get_option('riwa_pdf_options', [])['font_size'] ?? '10',
            'footer_text'     => get_option('riwa_pdf_options', [])['footer_text'] ?? 'Pour toute question, contactez-nous.',
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
            if (isset($data[$key])) {
                $clean[$key] = ($key === 'logo_url')
                    ? esc_url_raw($data[$key])
                    : sanitize_text_field($data[$key]);
            }
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

        $num    = $numbering[$type];
        $ref    = $num['prefix'] . '-' . $year . '-' . str_pad($num['counter'], 3, '0', STR_PAD_LEFT);
        $numbering[$type]['counter']++;
        update_option('riwa_pdf_numbering', $numbering);
        return $ref;
    }

    /* ---------------------------------------------------------------- */
    /*  Rendu HTML à partir du layout JSON                               */
    /* ---------------------------------------------------------------- */

    public static function render_layout_html($type, $booking, $settings, $preview = false) {
        $layout   = self::get_layout($type);
        $color    = $settings['primary_color'] ?? '#1a1a2e';
        $rgb      = self::hex_to_rgb($color);
        $font     = $settings['font_family'] ?? 'helvetica';
        $size     = intval($settings['font_size'] ?? 10);
        $doc_label = self::$doc_labels[$type] ?? $type;

        // CSS de base (TCPDF-compatible)
        $html = '<style>
            body { font-family: ' . esc_html($font) . '; font-size: ' . $size . 'px; color: #1a1a2e; margin: 0; }
            .studio-row { display: table; width: 100%; margin-bottom: 6px; border-spacing: 0; }
            .studio-col-1 { display: table-cell; width: 48%; vertical-align: top; padding-right: 8px; }
            .studio-col-2 { display: table-cell; width: 48%; vertical-align: top; padding-left: 8px; }
            .studio-col-full { display: table-cell; width: 100%; vertical-align: top; }
            .block-title { font-size: ' . ($size + 1) . 'px; font-weight: bold;
                           color: rgb(' . $rgb . '); border-bottom: 1px solid rgb(' . $rgb . ');
                           padding-bottom: 2px; margin-bottom: 4px; }
            .info-row { font-size: ' . ($size - 1) . 'px; margin: 1px 0; }
            .info-label { font-weight: bold; color: rgb(' . $rgb . '); }
            .info-value { color: #333; }
            .price-table { width: 100%; border-collapse: collapse; font-size: ' . ($size - 1) . 'px; }
            .price-table td { padding: 2px 4px; }
            .price-table .price-total { font-weight: bold; font-size: ' . $size . 'px;
                                        border-top: 2px solid rgb(' . $rgb . ');
                                        color: rgb(' . $rgb . '); }
            .doc-header { text-align: center; margin-bottom: 4px; }
            .doc-title { font-size: ' . ($size + 8) . 'px; font-weight: bold; color: rgb(' . $rgb . '); margin: 2px 0; }
            .doc-subtitle { font-size: ' . ($size + 1) . 'px; color: #666; margin: 0; }
            .doc-ref { font-size: ' . ($size - 1) . 'px; color: #999; margin: 2px 0; }
            .signature-box { border: 1px dashed #ccc; height: 50px; margin-top: 4px;
                             text-align: center; color: #999; font-size: 9px; padding-top: 18px; }
            .qr-placeholder { border: 1px solid #eee; width: 60px; height: 60px; margin: 4px auto;
                               text-align: center; font-size: 8px; color: #ccc; padding-top: 22px; }
            .text-block { font-size: ' . ($size - 1) . 'px; color: #444; line-height: 1.5; white-space: pre-wrap; }
            .footer-block { text-align: center; font-size: ' . ($size - 2) . 'px; color: #888;
                            border-top: 1px solid #eee; padding-top: 4px; margin-top: 4px; }
        </style>';

        foreach ($layout['rows'] as $row) {
            $blocks = $row['blocks'] ?? [];
            if (empty($blocks)) continue;

            $html .= '<div class="studio-row">';
            $count = count($blocks);

            foreach ($blocks as $i => $block) {
                $span  = intval($block['span'] ?? 1);
                $col_class = ($count === 1 || $span === 2) ? 'studio-col-full' : 'studio-col-' . ($i + 1);
                $html .= '<div class="' . $col_class . '">';
                $html .= self::render_block_html($block, $booking, $settings, $doc_label, $preview);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    public static function render_block_html($block, $booking, $settings, $doc_label = '', $preview = false) {
        $type   = $block['type'] ?? 'text';
        $config = $block['config'] ?? [];
        $color  = $settings['primary_color'] ?? '#1a1a2e';
        $rgb    = self::hex_to_rgb($color);
        $out    = '';

        // Données booking (objet ou tableau, preview ou réel)
        $is_preview = ($booking === null);
        $g = function($key, $fallback = '—') use ($booking, $is_preview) {
            if ($is_preview) {
                $sample = [
                    'id' => 'PREVIEW',
                    'guest_name' => 'Jean Dupont',
                    'guest_email' => 'jean@exemple.com',
                    'guest_phone' => '+33 6 12 34 56 78',
                    'check_in_date' => '2026-03-15',
                    'check_out_date' => '2026-03-20',
                    'adults_count' => 2,
                    'children_count' => 1,
                    'babies_count' => 0,
                    'total_price' => 1500,
                    'price_per_night' => 300,
                    'special_requests' => '',
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                return $sample[$key] ?? $fallback;
            }
            if (is_object($booking)) return $booking->$key ?? $fallback;
            if (is_array($booking))  return $booking[$key]  ?? $fallback;
            return $fallback;
        };

        switch ($type) {

            case 'header':
                $logo      = $settings['logo_url'] ?? '';
                $name      = $settings['company_name'] ?? 'Riwa Villa';
                $subtitle  = $config['subtitle'] ?? ($doc_label ?: 'Confirmation de réservation');
                $show_ref  = !isset($config['show_ref'])  || $config['show_ref']  === true || $config['show_ref']  === 'true';
                $show_date = !isset($config['show_date']) || $config['show_date'] === true || $config['show_date'] === 'true';
                $ref_num   = $is_preview ? 'CONF-2026-001' : ('RIWA-' . str_pad($g('id'), 6, '0', STR_PAD_LEFT));
                $out = '<div class="doc-header">';
                if ($logo) {
                    $out .= '<img src="' . esc_url($logo) . '" style="max-height:45px;max-width:120px;margin-bottom:4px;" alt="logo">';
                }
                $out .= '<div class="doc-title">' . esc_html($name) . '</div>';
                $out .= '<div class="doc-subtitle">' . esc_html($subtitle) . '</div>';
                if ($show_ref || $show_date) {
                    $ref_part  = $show_ref  ? esc_html($ref_num) : '';
                    $date_part = $show_date ? date_i18n('d/m/Y') : '';
                    $sep       = ($show_ref && $show_date) ? ' &nbsp;·&nbsp; ' : '';
                    $out .= '<div class="doc-ref">' . $ref_part . $sep . $date_part . '</div>';
                }
                $out .= '</div>';
                break;

            case 'company':
                $title = $config['title'] ?? 'Hébergeur';
                $out = '<div class="block-title">' . esc_html($title) . '</div>';
                // Visibilité (true par défaut sauf ICE/RC/IF/Patente)
                $cv = function($key, $def) use ($config) {
                    if (!isset($config[$key])) return $def;
                    return $config[$key] === true || $config[$key] === 'true';
                };
                $fields = [
                    ['show' => $cv('show_name',    true),  'label' => $config['lbl_name']    ?? 'Société', 'val' => $settings['company_name']    ?? ''],
                    ['show' => $cv('show_address',  true),  'label' => $config['lbl_address']  ?? 'Adresse', 'val' => $settings['company_address'] ?? ''],
                    ['show' => $cv('show_phone',    true),  'label' => $config['lbl_phone']    ?? 'Tél',     'val' => $settings['company_phone']   ?? ''],
                    ['show' => $cv('show_email',    true),  'label' => $config['lbl_email']    ?? 'Email',   'val' => $settings['company_email']   ?? ''],
                    ['show' => $cv('show_ice',      false), 'label' => 'ICE',     'val' => $settings['company_ice']     ?? ''],
                    ['show' => $cv('show_rc',       false), 'label' => 'RC',      'val' => $settings['company_rc']      ?? ''],
                    ['show' => $cv('show_if',       false), 'label' => 'IF',      'val' => $settings['company_if']      ?? ''],
                    ['show' => $cv('show_patente',  false), 'label' => 'Patente', 'val' => $settings['company_patente'] ?? ''],
                ];
                foreach ($fields as $f) {
                    if (!$f['show'] || !$f['val']) continue;
                    $out .= '<div class="info-row"><span class="info-label">' . esc_html($f['label']) . ' :</span> <span class="info-value">' . esc_html($f['val']) . '</span></div>';
                }
                break;

            case 'client':
                $cv2 = function($key, $def) use ($config) {
                    if (!isset($config[$key])) return $def;
                    return $config[$key] === true || $config[$key] === 'true';
                };
                $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Client') . '</div>';
                if ($cv2('show_name',  true))  $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_name']  ?? 'Nom')   . ' :</span> <span class="info-value">' . esc_html($g('guest_name'))  . '</span></div>';
                if ($cv2('show_email', true))  $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_email'] ?? 'Email') . ' :</span> <span class="info-value">' . esc_html($g('guest_email')) . '</span></div>';
                if ($cv2('show_phone', true))  $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_phone'] ?? 'Tél')   . ' :</span> <span class="info-value">' . esc_html($g('guest_phone')) . '</span></div>';
                break;

            case 'stay':
                $cin  = $g('check_in_date');
                $cout = $g('check_out_date');
                $nights = 0;
                if ($cin && $cout && $cin !== '—' && $cout !== '—') {
                    $nights = max(0, (int)(new DateTime($cout))->diff(new DateTime($cin))->days);
                }
                $cv3 = function($key, $def) use ($config) {
                    if (!isset($config[$key])) return $def;
                    return $config[$key] === true || $config[$key] === 'true';
                };
                $night_unit = $config['lbl_nights_unit'] ?? 'nuit(s)';
                $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Séjour') . '</div>';
                if ($cv3('show_checkin',  true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_checkin']  ?? 'Arrivée') . ' :</span> <span class="info-value">' . esc_html($cin  !== '—' ? date_i18n('d/m/Y', strtotime($cin))  : '—') . '</span></div>';
                if ($cv3('show_checkout', true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_checkout'] ?? 'Départ')  . ' :</span> <span class="info-value">' . esc_html($cout !== '—' ? date_i18n('d/m/Y', strtotime($cout)) : '—') . '</span></div>';
                if ($cv3('show_nights',   true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_nights']   ?? 'Durée')   . ' :</span> <span class="info-value">' . esc_html($nights . ' ' . $night_unit) . '</span></div>';
                break;

            case 'travelers':
                $cv4 = function($key, $def) use ($config) {
                    if (!isset($config[$key])) return $def;
                    return $config[$key] === true || $config[$key] === 'true';
                };
                $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Voyageurs') . '</div>';
                $ch = intval($g('children_count', 0));
                $bb = intval($g('babies_count', 0));
                if ($cv4('show_adults',   true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_adults']   ?? 'Adultes') . ' :</span> <span class="info-value">' . intval($g('adults_count', 0)) . '</span></div>';
                if ($cv4('show_children', true) && $ch) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_children'] ?? 'Enfants') . ' :</span> <span class="info-value">' . $ch . '</span></div>';
                if ($cv4('show_babies',   true) && $bb) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_babies']   ?? 'Bébés')   . ' :</span> <span class="info-value">' . $bb . '</span></div>';
                break;

            case 'pricing':
                $cin  = $g('check_in_date');
                $cout = $g('check_out_date');
                $nights = 0;
                if ($cin && $cout && $cin !== '—' && $cout !== '—') {
                    $nights = max(0, (int)(new DateTime($cout))->diff(new DateTime($cin))->days);
                }
                $currency = $config['currency'] ?? '€';
                $ppn   = number_format((float)$g('price_per_night', 0), 0, ',', ' ');
                $total = number_format((float)$g('total_price', 0), 0, ',', ' ');
                $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Tarifs') . '</div>';
                $out .= '<table class="price-table">';
                $lbl_night = $config['lbl_night'] ?? 'nuit(s)';
                $out .= '<tr><td>' . esc_html($nights) . ' ' . esc_html($lbl_night) . ' × ' . esc_html($ppn) . ' ' . esc_html($currency) . '</td><td style="text-align:right;">' . esc_html($total) . ' ' . esc_html($currency) . '</td></tr>';

                // Upsells si disponibles
                if (!$is_preview && class_exists('Riwa_Upsells_Table')) {
                    $booking_id = is_object($booking) ? $booking->id : ($booking['id'] ?? 0);
                    $upsells = Riwa_Upsells_Table::get_for_booking($booking_id);
                    foreach ($upsells as $u) {
                        $out .= '<tr><td>' . esc_html($u->upsell_name ?? '') . '</td><td style="text-align:right;">' . number_format((float)($u->total_price ?? 0), 0, ',', ' ') . ' €</td></tr>';
                    }
                }

                $lbl_total = $config['lbl_total'] ?? 'Total';
                $out .= '<tr class="price-total"><td><strong>' . esc_html($lbl_total) . '</strong></td><td style="text-align:right;"><strong>' . esc_html($total) . ' ' . esc_html($currency) . '</strong></td></tr>';
                $out .= '</table>';
                break;

            case 'text':
                $content = $config['content'] ?? 'Texte libre à personnaliser.';
                $out = '<div class="text-block">' . esc_html($content) . '</div>';
                break;

            case 'signature':
                $sig_label    = $config['label']        ?? 'Signature du client';
                $sig_vendor   = $config['label_vendor'] ?? '';
                $show_sig_date = !isset($config['show_date']) || $config['show_date'] === true || $config['show_date'] === 'true';
                $two_cols     = isset($config['two_cols']) && ($config['two_cols'] === true || $config['two_cols'] === 'true');
                $out = '<div class="block-title">Signature</div>';
                if ($two_cols && $sig_vendor) {
                    $out .= '<div style="display:table;width:100%;table-layout:fixed;">';
                    $out .= '<div style="display:table-cell;width:50%;padding-right:8px;"><div class="signature-box">' . esc_html($sig_label) . '</div></div>';
                    $out .= '<div style="display:table-cell;width:50%;padding-left:8px;"><div class="signature-box">' . esc_html($sig_vendor) . '</div></div>';
                    $out .= '</div>';
                } else {
                    $out .= '<div class="signature-box">' . esc_html($sig_label) . '</div>';
                }
                if ($show_sig_date) {
                    $out .= '<div class="info-row" style="margin-top:4px;font-size:9px;color:#999;">Date : _______________</div>';
                }
                break;

            case 'qr':
                $ref = $is_preview ? 'PREVIEW' : ('RIWA-' . str_pad($g('id'), 6, '0', STR_PAD_LEFT));
                $qr_label = $config['label'] ?? 'Référence';
                $out = '<div class="block-title">' . esc_html($qr_label) . '</div>';
                $out .= '<div class="qr-placeholder">QR<br>' . esc_html($ref) . '</div>';
                break;

            case 'footer':
                $footer = $config['text'] ?? ($settings['footer_text'] ?? 'Pour toute question, contactez-nous.');
                $show_generated = !isset($config['show_generated']) || $config['show_generated'] === true || $config['show_generated'] === 'true';
                $out = '<div class="footer-block">' . esc_html($footer) . '</div>';
                if ($show_generated) {
                    $out .= '<div class="footer-block" style="color:#bbb;">Document généré par Riwa Booking · ' . date_i18n('d/m/Y') . '</div>';
                }
                break;
        }

        return $out;
    }

    /* ---------------------------------------------------------------- */
    /*  Utilitaires                                                      */
    /* ---------------------------------------------------------------- */

    public static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return "$r,$g,$b";
    }

    /* ---------------------------------------------------------------- */
    /*  AJAX handlers                                                    */
    /* ---------------------------------------------------------------- */

    public static function ajax_save_layout() {
        check_ajax_referer('riwa_studio_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

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
        check_ajax_referer('riwa_studio_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $type     = sanitize_key($_POST['doc_type'] ?? 'confirmation');
        $settings = self::get_settings();

        // Layout optionnel transmis depuis JS (pas encore sauvegardé)
        $layout_json = stripslashes($_POST['layout'] ?? '');
        if ($layout_json) {
            $decoded = json_decode($layout_json, true);
            if ($decoded && isset($decoded['rows'])) {
                // Sauvegarde temporaire pour le rendu
                update_option('riwa_pdf_layout_' . $type . '_preview', $layout_json);
            }
        }

        $html = self::render_layout_html($type, null, $settings, true);

        wp_send_json_success(['html' => $html]);
    }

    public static function ajax_save_settings() {
        check_ajax_referer('riwa_studio_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $data = $_POST['settings'] ?? [];
        if (is_string($data)) {
            $decoded = json_decode(stripslashes($data), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (is_array($data)) {
            self::save_settings($data);
            wp_send_json_success(['saved' => true]);
        } else {
            wp_send_json_error('Données invalides');
        }
    }

    public static function ajax_get_all_layouts() {
        check_ajax_referer('riwa_studio_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

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
        check_ajax_referer('riwa_studio_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $type = sanitize_key($_POST['doc_type'] ?? '');
        if (!in_array($type, self::$doc_types, true)) {
            wp_send_json_error('Type invalide');
        }

        delete_option('riwa_pdf_layout_' . $type);
        $default = self::get_default_layout($type);
        wp_send_json_success(['layout' => $default]);
    }
}

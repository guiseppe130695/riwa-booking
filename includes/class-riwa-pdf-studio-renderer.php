<?php
/**
 * Riwa_PDF_Studio_Renderer — Moteur de rendu HTML du Doc Studio
 *
 * Transforme un layout JSON en HTML TCPDF-compatible.
 * Séparé de Riwa_PDF_Studio pour isoler la logique de présentation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_PDF_Studio_Renderer {

    /**
     * Génère le HTML complet d'un document à partir de son layout JSON.
     *
     * @param  string     $type     Type de document (confirmation, facture…)
     * @param  object|array|null $booking  Réservation (null = mode aperçu)
     * @param  array      $settings Settings globaux (couleur, police…)
     * @param  bool       $preview  true = données fictives de démo
     * @return string     HTML prêt pour TCPDF ou iframe
     */
    public static function render($type, $booking, $settings, $preview = false) {
        $layout    = Riwa_PDF_Studio::get_layout($type);
        $color     = $settings['primary_color'] ?? '#1a1a2e';
        $rgb       = self::hex_to_rgb($color);
        $font      = $settings['font_family'] ?? 'helvetica';
        $size      = intval($settings['font_size'] ?? 10);
        $doc_label = Riwa_PDF_Studio::$doc_labels[$type] ?? $type;

        $html = self::build_css($rgb, $font, $size);

        foreach ($layout['rows'] as $row) {
            $blocks = $row['blocks'] ?? [];
            if (empty($blocks)) continue;

            $html .= '<div class="studio-row">';
            $count = count($blocks);

            foreach ($blocks as $i => $block) {
                $span      = intval($block['span'] ?? 1);
                $col_class = ($count === 1 || $span === 2) ? 'studio-col-full' : 'studio-col-' . ($i + 1);
                $html .= '<div class="' . $col_class . '">';
                $html .= self::render_block($block, $booking, $settings, $doc_label, $preview);
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        return $html;
    }

    /* ------------------------------------------------------------------ */
    /*  CSS de base                                                         */
    /* ------------------------------------------------------------------ */

    private static function build_css($rgb, $font, $size) {
        return '<style>
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
    }

    /* ------------------------------------------------------------------ */
    /*  Rendu d'un bloc individuel                                          */
    /* ------------------------------------------------------------------ */

    public static function render_block($block, $booking, $settings, $doc_label = '', $preview = false) {
        $type      = $block['type'] ?? 'text';
        $config    = $block['config'] ?? [];
        $color     = $settings['primary_color'] ?? '#1a1a2e';
        $rgb       = self::hex_to_rgb($color);
        $is_preview = ($booking === null);

        // Accesseur générique booking (objet, tableau ou données fictives)
        $g = self::make_getter($booking, $is_preview);

        switch ($type) {
            case 'header':    return self::block_header($config, $settings, $g, $is_preview, $doc_label);
            case 'company':   return self::block_company($config, $settings, $rgb);
            case 'client':    return self::block_client($config, $g);
            case 'stay':      return self::block_stay($config, $g);
            case 'travelers': return self::block_travelers($config, $g);
            case 'pricing':   return self::block_pricing($config, $g, $booking, $is_preview);
            case 'text':      return self::block_text($config);
            case 'signature': return self::block_signature($config);
            case 'qr':        return self::block_qr($config, $g, $is_preview);
            case 'footer':    return self::block_footer($config, $settings);
            default:          return '';
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Getter générique                                                     */
    /* ------------------------------------------------------------------ */

    private static function make_getter($booking, $is_preview) {
        return function($key, $fallback = '—') use ($booking, $is_preview) {
            if ($is_preview) {
                $sample = [
                    'id' => 'PREVIEW', 'guest_name' => 'Jean Dupont',
                    'guest_email' => 'jean@exemple.com', 'guest_phone' => '+33 6 12 34 56 78',
                    'check_in_date' => '2026-03-15', 'check_out_date' => '2026-03-20',
                    'adults_count' => 2, 'children_count' => 1, 'babies_count' => 0,
                    'total_price' => 1500, 'price_per_night' => 300,
                    'special_requests' => '', 'created_at' => date('Y-m-d H:i:s'),
                ];
                return $sample[$key] ?? $fallback;
            }
            if (is_object($booking)) return $booking->$key ?? $fallback;
            if (is_array($booking))  return $booking[$key]  ?? $fallback;
            return $fallback;
        };
    }

    /* ------------------------------------------------------------------ */
    /*  Blocs individuels                                                    */
    /* ------------------------------------------------------------------ */

    private static function block_header($config, $settings, $g, $is_preview, $doc_label) {
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
            $ref_part  = $show_ref  ? esc_html($ref_num)       : '';
            $date_part = $show_date ? date_i18n('d/m/Y')        : '';
            $sep       = ($show_ref && $show_date) ? ' &nbsp;·&nbsp; ' : '';
            $out .= '<div class="doc-ref">' . $ref_part . $sep . $date_part . '</div>';
        }
        return $out . '</div>';
    }

    private static function block_company($config, $settings, $rgb) {
        $cv = fn($key, $def) => isset($config[$key]) ? ($config[$key] === true || $config[$key] === 'true') : $def;

        $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Hébergeur') . '</div>';
        $fields = [
            [$cv('show_name',    true),  $config['lbl_name']    ?? 'Société', $settings['company_name']    ?? ''],
            [$cv('show_address', true),  $config['lbl_address'] ?? 'Adresse', $settings['company_address'] ?? ''],
            [$cv('show_phone',   true),  $config['lbl_phone']   ?? 'Tél',     $settings['company_phone']   ?? ''],
            [$cv('show_email',   true),  $config['lbl_email']   ?? 'Email',   $settings['company_email']   ?? ''],
            [$cv('show_ice',     false), 'ICE',     $settings['company_ice']     ?? ''],
            [$cv('show_rc',      false), 'RC',      $settings['company_rc']      ?? ''],
            [$cv('show_if',      false), 'IF',      $settings['company_if']      ?? ''],
            [$cv('show_patente', false), 'Patente', $settings['company_patente'] ?? ''],
        ];
        foreach ($fields as [$show, $label, $val]) {
            if (!$show || !$val) continue;
            $out .= '<div class="info-row"><span class="info-label">' . esc_html($label) . ' :</span> <span class="info-value">' . esc_html($val) . '</span></div>';
        }
        return $out;
    }

    private static function block_client($config, $g) {
        $cv = fn($key, $def) => isset($config[$key]) ? ($config[$key] === true || $config[$key] === 'true') : $def;

        $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Client') . '</div>';
        if ($cv('show_name',  true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_name']  ?? 'Nom')   . ' :</span> <span class="info-value">' . esc_html($g('guest_name'))  . '</span></div>';
        if ($cv('show_email', true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_email'] ?? 'Email') . ' :</span> <span class="info-value">' . esc_html($g('guest_email')) . '</span></div>';
        if ($cv('show_phone', true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_phone'] ?? 'Tél')   . ' :</span> <span class="info-value">' . esc_html($g('guest_phone')) . '</span></div>';
        return $out;
    }

    private static function block_stay($config, $g) {
        $cv = fn($key, $def) => isset($config[$key]) ? ($config[$key] === true || $config[$key] === 'true') : $def;

        $cin  = $g('check_in_date');
        $cout = $g('check_out_date');
        $nights = ($cin && $cout && $cin !== '—' && $cout !== '—')
            ? max(0, (int)(new DateTime($cout))->diff(new DateTime($cin))->days)
            : 0;

        $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Séjour') . '</div>';
        if ($cv('show_checkin',  true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_checkin']  ?? 'Arrivée') . ' :</span> <span class="info-value">' . esc_html($cin  !== '—' ? date_i18n('d/m/Y', strtotime($cin))  : '—') . '</span></div>';
        if ($cv('show_checkout', true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_checkout'] ?? 'Départ')  . ' :</span> <span class="info-value">' . esc_html($cout !== '—' ? date_i18n('d/m/Y', strtotime($cout)) : '—') . '</span></div>';
        if ($cv('show_nights',   true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_nights']   ?? 'Durée')   . ' :</span> <span class="info-value">' . esc_html($nights . ' ' . ($config['lbl_nights_unit'] ?? 'nuit(s)')) . '</span></div>';
        return $out;
    }

    private static function block_travelers($config, $g) {
        $cv = fn($key, $def) => isset($config[$key]) ? ($config[$key] === true || $config[$key] === 'true') : $def;

        $ch  = intval($g('children_count', 0));
        $bb  = intval($g('babies_count', 0));
        $out = '<div class="block-title">' . esc_html($config['title'] ?? 'Voyageurs') . '</div>';
        if ($cv('show_adults',   true)) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_adults']   ?? 'Adultes') . ' :</span> <span class="info-value">' . intval($g('adults_count', 0)) . '</span></div>';
        if ($cv('show_children', true) && $ch) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_children'] ?? 'Enfants') . ' :</span> <span class="info-value">' . $ch . '</span></div>';
        if ($cv('show_babies',   true) && $bb) $out .= '<div class="info-row"><span class="info-label">' . esc_html($config['lbl_babies']   ?? 'Bébés')   . ' :</span> <span class="info-value">' . $bb . '</span></div>';
        return $out;
    }

    private static function block_pricing($config, $g, $booking, $is_preview) {
        $cin  = $g('check_in_date');
        $cout = $g('check_out_date');
        $nights = ($cin && $cout && $cin !== '—' && $cout !== '—')
            ? max(0, (int)(new DateTime($cout))->diff(new DateTime($cin))->days)
            : 0;

        $currency  = $config['currency'] ?? '€';
        $ppn       = number_format((float)$g('price_per_night', 0), 0, ',', ' ');
        $total     = number_format((float)$g('total_price', 0), 0, ',', ' ');
        $lbl_night = $config['lbl_night'] ?? 'nuit(s)';
        $lbl_total = $config['lbl_total'] ?? 'Total';

        $out  = '<div class="block-title">' . esc_html($config['title'] ?? 'Tarifs') . '</div>';
        $out .= '<table class="price-table">';
        $out .= '<tr><td>' . esc_html($nights) . ' ' . esc_html($lbl_night) . ' × ' . esc_html($ppn) . ' ' . esc_html($currency) . '</td><td style="text-align:right;">' . esc_html($total) . ' ' . esc_html($currency) . '</td></tr>';

        if (!$is_preview && class_exists('Riwa_Upsells_Table')) {
            $booking_id = is_object($booking) ? $booking->id : ($booking['id'] ?? 0);
            foreach (Riwa_Upsells_Table::get_for_booking($booking_id) as $u) {
                $out .= '<tr><td>' . esc_html($u->upsell_name ?? '') . '</td><td style="text-align:right;">' . number_format((float)($u->total_price ?? 0), 0, ',', ' ') . ' €</td></tr>';
            }
        }

        $out .= '<tr class="price-total"><td><strong>' . esc_html($lbl_total) . '</strong></td><td style="text-align:right;"><strong>' . esc_html($total) . ' ' . esc_html($currency) . '</strong></td></tr>';
        $out .= '</table>';
        return $out;
    }

    private static function block_text($config) {
        return '<div class="text-block">' . esc_html($config['content'] ?? 'Texte libre à personnaliser.') . '</div>';
    }

    private static function block_signature($config) {
        $sig_label  = $config['label']        ?? 'Signature du client';
        $sig_vendor = $config['label_vendor'] ?? '';
        $two_cols   = isset($config['two_cols']) && ($config['two_cols'] === true || $config['two_cols'] === 'true');
        $show_date  = !isset($config['show_date']) || $config['show_date'] === true || $config['show_date'] === 'true';

        $out = '<div class="block-title">Signature</div>';
        if ($two_cols && $sig_vendor) {
            $out .= '<div style="display:table;width:100%;table-layout:fixed;">';
            $out .= '<div style="display:table-cell;width:50%;padding-right:8px;"><div class="signature-box">' . esc_html($sig_label) . '</div></div>';
            $out .= '<div style="display:table-cell;width:50%;padding-left:8px;"><div class="signature-box">' . esc_html($sig_vendor) . '</div></div>';
            $out .= '</div>';
        } else {
            $out .= '<div class="signature-box">' . esc_html($sig_label) . '</div>';
        }
        if ($show_date) {
            $out .= '<div class="info-row" style="margin-top:4px;font-size:9px;color:#999;">Date : _______________</div>';
        }
        return $out;
    }

    private static function block_qr($config, $g, $is_preview) {
        $ref = $is_preview ? 'PREVIEW' : ('RIWA-' . str_pad($g('id'), 6, '0', STR_PAD_LEFT));
        $out  = '<div class="block-title">' . esc_html($config['label'] ?? 'Référence') . '</div>';
        $out .= '<div class="qr-placeholder">QR<br>' . esc_html($ref) . '</div>';
        return $out;
    }

    private static function block_footer($config, $settings) {
        $footer         = $config['text'] ?? ($settings['footer_text'] ?? 'Pour toute question, contactez-nous.');
        $show_generated = !isset($config['show_generated']) || $config['show_generated'] === true || $config['show_generated'] === 'true';

        $out = '<div class="footer-block">' . esc_html($footer) . '</div>';
        if ($show_generated) {
            $out .= '<div class="footer-block" style="color:#bbb;">Document généré par Riwa Booking · ' . date_i18n('d/m/Y') . '</div>';
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  Utilitaire couleur                                                   */
    /* ------------------------------------------------------------------ */

    public static function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return hexdec(substr($hex,0,2)) . ',' . hexdec(substr($hex,2,2)) . ',' . hexdec(substr($hex,4,2));
    }
}

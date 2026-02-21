<?php
/**
 * Notifications WhatsApp semi-auto + log en DB
 * Pattern identique à class-riwa-emails.php (str_replace, options WP, nonces)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Notifications {

    /* ------------------------------------------------------------------ */
    /*  Création de la table de log                                        */
    /* ------------------------------------------------------------------ */

    public static function create_table() {
        global $wpdb;
        $table    = $wpdb->prefix . 'riwa_notification_log';
        $charset  = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id         mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            type       varchar(30)  NOT NULL DEFAULT 'confirmation',
            channel    varchar(10)  NOT NULL DEFAULT 'whatsapp',
            sent_at    datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ------------------------------------------------------------------ */
    /*  Variables dynamiques à partir d'une réservation                   */
    /* ------------------------------------------------------------------ */

    /**
     * Retourne un tableau {variable} => valeur pour une réservation donnée
     */
    public static function get_variables($booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        $b = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d LIMIT 1", intval($booking_id)
        ));
        if (!$b) return [];

        $nights = 0;
        if ($b->check_in_date && $b->check_out_date) {
            $a      = new DateTime($b->check_in_date);
            $z      = new DateTime($b->check_out_date);
            $nights = max(0, (int) $a->diff($z)->days);
        }

        $statut_labels = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'];
        $ref = 'RIWA-' . str_pad($b->id, 6, '0', STR_PAD_LEFT);

        return [
            '{nom_client}'     => $b->guest_name   ?? '',
            '{email_client}'   => $b->guest_email  ?? '',
            '{telephone_client}' => $b->guest_phone ?? '',
            '{date_arrivee}'   => $b->check_in_date  ? date_i18n('d/m/Y', strtotime($b->check_in_date))  : '—',
            '{date_depart}'    => $b->check_out_date ? date_i18n('d/m/Y', strtotime($b->check_out_date)) : '—',
            '{nuits}'          => $nights,
            '{adultes}'        => (int) ($b->adults_count   ?? 0),
            '{enfants}'        => (int) ($b->children_count ?? 0),
            '{bebes}'          => (int) ($b->babies_count   ?? 0),
            '{prix_total}'     => number_format((float)($b->total_price ?? 0), 0, ',', ' '),
            '{prix_nuit}'      => number_format((float)($b->price_per_night ?? 0), 0, ',', ' '),
            '{statut}'         => $statut_labels[$b->status] ?? $b->status,
            '{reference}'      => $ref,
        ];
    }

    /**
     * Remplace les {variables} dans un template
     */
    public static function render_template($template, array $vars) {
        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    /* ------------------------------------------------------------------ */
    /*  WhatsApp                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Normalise un numéro de téléphone pour wa.me (chiffres uniquement, avec indicatif)
     * Ex: "06 12 34 56 78" + "+33" → "33612345678"
     * Ex: "+212661234567" → "212661234567"
     */
    public static function normalize_phone($phone, $country_code = '') {
        // Supprimer tout sauf chiffres et +
        $clean = preg_replace('/[^\d+]/', '', $phone);

        // Si commence par +, retirer le +
        if (substr($clean, 0, 1) === '+') {
            return substr($clean, 1);
        }

        // Si commence par 00, retirer les 00
        if (substr($clean, 0, 2) === '00') {
            return substr($clean, 2);
        }

        // Ajouter l'indicatif pays si fourni (ex: +33 → retirer 0 initial)
        if ($country_code) {
            $cc = preg_replace('/[^\d]/', '', $country_code);
            // Retirer le 0 initial si le numéro local commence par 0
            if (substr($clean, 0, 1) === '0') {
                $clean = substr($clean, 1);
            }
            return $cc . $clean;
        }

        return $clean;
    }

    /**
     * Construit le lien wa.me avec message encodé
     */
    public static function build_wa_link($phone, $message) {
        $clean = self::normalize_phone(
            $phone,
            get_option('riwa_notif_country_code', '+33')
        );
        return 'https://wa.me/' . $clean . '?text=' . rawurlencode($message);
    }

    /**
     * Récupère le template d'un type de notification, avec variables remplacées
     */
    public static function get_rendered_template($type, $booking_id) {
        $defaults = self::get_default_templates();
        $template = get_option('riwa_notif_tpl_' . $type, $defaults[$type] ?? '');
        $vars     = self::get_variables($booking_id);
        return self::render_template($template, $vars);
    }

    /**
     * Templates par défaut (utilisés à la première activation)
     */
    public static function get_default_templates() {
        return [
            'confirmation' => "Bonjour {nom_client} 👋\n\nVotre réservation a bien été enregistrée !\n\n📅 Arrivée : {date_arrivee}\n📅 Départ : {date_depart}\n🌙 Durée : {nuits} nuit(s)\n💰 Prix total : {prix_total} €\n\nNous vous contacterons pour confirmer. À bientôt !",
            'reminder'     => "Bonjour {nom_client} 👋\n\nRappel : votre séjour commence le {date_arrivee} ({nuits} nuit(s)).\n\n💳 Montant total : {prix_total} €\n\nN'hésitez pas à nous contacter pour toute question.",
            'checkin'      => "Bonjour {nom_client} ! 🏡\n\nVotre arrivée est demain, le {date_arrivee}.\n\nVoici quelques informations importantes pour votre séjour. N'hésitez pas à nous appeler si vous avez besoin d'aide.",
            'review'       => "Bonjour {nom_client} 😊\n\nVotre séjour du {date_arrivee} au {date_depart} est terminé.\n\nNous espérons que vous avez passé un excellent moment !\n\nSi vous avez quelques minutes, votre avis nous serait très précieux. Merci pour votre confiance !",
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Log des notifications envoyées                                     */
    /* ------------------------------------------------------------------ */

    public static function log($booking_id, $type, $channel = 'whatsapp') {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'riwa_notification_log',
            [
                'booking_id' => intval($booking_id),
                'type'       => sanitize_key($type),
                'channel'    => sanitize_key($channel),
                'sent_at'    => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );
    }

    public static function get_log($booking_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_notification_log';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT type, channel, sent_at FROM $table WHERE booking_id = %d ORDER BY sent_at DESC",
            intval($booking_id)
        ));
    }

    /* ------------------------------------------------------------------ */
    /*  Handlers AJAX                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Récupère le log de notifications pour une réservation (depuis la popup)
     * Action : riwa_notif_get_log
     */
    public static function ajax_get_log() {
        check_ajax_referer('riwa_notif_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error('ID manquant');
        }

        $log = self::get_log($booking_id);

        $type_labels = [
            'confirmation' => 'Confirmation',
            'reminder'     => 'Rappel',
            'checkin'      => 'Infos arrivée',
            'review'       => 'Demande avis',
            'custom'       => 'Message personnalisé',
        ];

        $formatted = array_map(function ($row) use ($type_labels) {
            return [
                'type'    => $type_labels[$row->type] ?? $row->type,
                'channel' => $row->channel,
                'sent_at' => date_i18n('d/m/Y à H:i', strtotime($row->sent_at)),
            ];
        }, $log);

        wp_send_json_success(['log' => $formatted]);
    }

    /**
     * Enregistre qu'un message WhatsApp a été ouvert (clic sur le lien)
     * Action : riwa_notif_log_sent
     */
    public static function ajax_log_sent() {
        check_ajax_referer('riwa_notif_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $type       = sanitize_key($_POST['type'] ?? 'custom');

        if (!$booking_id) {
            wp_send_json_error('ID manquant');
        }

        self::log($booking_id, $type, 'whatsapp');
        wp_send_json_success(['logged' => true]);
    }

    /**
     * Renvoie le message rendu (variables remplacées) pour prévisualisation
     * Action : riwa_notif_preview
     */
    public static function ajax_preview() {
        check_ajax_referer('riwa_notif_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $type       = sanitize_key($_POST['type'] ?? 'confirmation');
        $target     = sanitize_key($_POST['target'] ?? 'client'); // 'client' | 'admin'

        if (!$booking_id) {
            wp_send_json_error('ID manquant');
        }

        $message = self::get_rendered_template($type, $booking_id);

        // Déterminer le numéro cible
        if ($target === 'admin') {
            $raw_phone  = get_option('riwa_notif_admin_phone', '');
        } else {
            global $wpdb;
            $raw_phone = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT guest_phone FROM {$wpdb->prefix}riwa_bookings WHERE id = %d", $booking_id
            ));
        }

        $wa_link = $raw_phone
            ? self::build_wa_link($raw_phone, $message)
            : '';

        wp_send_json_success([
            'message' => $message,
            'wa_link' => $wa_link,
            'phone'   => $raw_phone,
        ]);
    }

    /**
     * Récupère le log global récent (section Notifications)
     * Action : riwa_notif_recent_log
     */
    public static function ajax_recent_log() {
        check_ajax_referer('riwa_notif_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        global $wpdb;
        $log_table = $wpdb->prefix . 'riwa_notification_log';
        $bk_table  = $wpdb->prefix . 'riwa_bookings';

        $rows = $wpdb->get_results(
            "SELECT nl.type, nl.channel, nl.sent_at, b.guest_name, nl.booking_id
             FROM $log_table nl
             LEFT JOIN $bk_table b ON b.id = nl.booking_id
             ORDER BY nl.sent_at DESC LIMIT 30"
        );

        $type_labels = [
            'confirmation' => 'Confirmation',
            'reminder'     => 'Rappel',
            'checkin'      => 'Infos arrivée',
            'review'       => 'Demande avis',
            'custom'       => 'Personnalisé',
        ];

        $formatted = array_map(function ($r) use ($type_labels) {
            return [
                'booking_id' => (int) $r->booking_id,
                'guest_name' => $r->guest_name ?? '—',
                'type'       => $type_labels[$r->type] ?? $r->type,
                'channel'    => $r->channel,
                'sent_at'    => date_i18n('d/m/Y à H:i', strtotime($r->sent_at)),
            ];
        }, $rows);

        wp_send_json_success(['log' => $formatted]);
    }
}

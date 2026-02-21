<?php
/**
 * Gestion du Planning : blocked_dates, date_overrides, housekeeping
 * Tables : riwa_blocked_dates + riwa_date_overrides
 * Colonne supplémentaire sur riwa_bookings : housekeeping_status
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Planning {

    /* ------------------------------------------------------------------ */
    /*  Création des tables                                                 */
    /* ------------------------------------------------------------------ */

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Table des blocages manuels
        $blocked_table = $wpdb->prefix . 'riwa_blocked_dates';
        $sql1 = "CREATE TABLE $blocked_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date_start date NOT NULL,
            date_end   date NOT NULL,
            reason     varchar(30) NOT NULL DEFAULT 'private',
            note       varchar(255) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY date_start (date_start)
        ) $charset;";

        // Table des overrides de prix par date
        $overrides_table = $wpdb->prefix . 'riwa_date_overrides';
        $sql2 = "CREATE TABLE $overrides_table (
            id         mediumint(9) NOT NULL AUTO_INCREMENT,
            override_date date NOT NULL,
            price      decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY override_date (override_date)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);

        // Colonne housekeeping_status sur riwa_bookings (migration douce)
        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        if (empty($wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'housekeeping_status'"))) {
            $wpdb->query("ALTER TABLE $bookings_table
                ADD COLUMN housekeeping_status varchar(20) DEFAULT 'ready' AFTER status");
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Vérification de disponibilité                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Vérifie si une plage check_in / check_out chevauche une réservation existante.
     * @param string $check_in   YYYY-MM-DD
     * @param string $check_out  YYYY-MM-DD
     * @param int    $exclude_id ID à ignorer (pour une modif future)
     * @return bool  true = chevauchement détecté
     */
    public static function has_overlap($check_in, $check_out, $exclude_id = 0) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        $sql   = $wpdb->prepare(
            "SELECT id FROM $table
             WHERE status IN ('pending','confirmed')
               AND check_in_date  < %s
               AND check_out_date > %s
               AND id != %d
             LIMIT 1",
            $check_out, $check_in, $exclude_id
        );
        return (bool) $wpdb->get_var($sql);
    }

    /* ------------------------------------------------------------------ */
    /*  Données pour le rendu du calendrier                                */
    /* ------------------------------------------------------------------ */

    /**
     * Retourne toutes les réservations actives dans une plage de dates
     */
    public static function get_bookings_for_range($date_start, $date_end) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, guest_name, check_in_date, check_out_date,
                    adults_count, children_count, babies_count,
                    total_price, status, housekeeping_status
             FROM $table
             WHERE status IN ('pending','confirmed')
               AND check_in_date  < %s
               AND check_out_date > %s
             ORDER BY check_in_date ASC",
            $date_end, $date_start
        ));
    }

    /**
     * Retourne tous les blocages dans une plage de dates
     */
    public static function get_blocked_for_range($date_start, $date_end) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_blocked_dates';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE date_start < %s AND date_end > %s
             ORDER BY date_start ASC",
            $date_end, $date_start
        ));
    }

    /**
     * Retourne les overrides de prix dans une plage
     */
    public static function get_overrides_for_range($date_start, $date_end) {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_date_overrides';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table
             WHERE override_date >= %s AND override_date < %s
             ORDER BY override_date ASC",
            $date_start, $date_end
        ));
    }

    /**
     * Stats d'occupation pour une plage donnée
     * Retourne : total_nights, occupied_nights, occupation_rate, revenue, potential_revenue
     */
    public static function get_occupation_stats($date_start, $date_end) {
        global $wpdb;

        $start_dt = new DateTime($date_start);
        $end_dt   = new DateTime($date_end);
        $total_nights = (int) $start_dt->diff($end_dt)->days;
        if ($total_nights <= 0) return [];

        $bookings_table = $wpdb->prefix . 'riwa_bookings';
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT check_in_date, check_out_date, total_price, status
             FROM $bookings_table
             WHERE status IN ('pending','confirmed')
               AND check_in_date < %s AND check_out_date > %s",
            $date_end, $date_start
        ));

        // Compter les nuits réellement occupées dans la plage
        $occupied_dates = [];
        foreach ($bookings as $b) {
            $cur = new DateTime(max($b->check_in_date, $date_start));
            $end = new DateTime(min($b->check_out_date, $date_end));
            while ($cur < $end) {
                $occupied_dates[$cur->format('Y-m-d')] = true;
                $cur->modify('+1 day');
            }
        }
        $occupied_nights = count($occupied_dates);

        // Revenue confirmées
        $revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price),0) FROM $bookings_table
             WHERE status = 'confirmed'
               AND check_in_date < %s AND check_out_date > %s",
            $date_end, $date_start
        ));

        // Prix moyen par nuit (depuis tarification)
        $pricing_table = $wpdb->prefix . 'riwa_pricing';
        $avg_price = (float) $wpdb->get_var(
            "SELECT COALESCE(AVG(price_per_night), 150) FROM $pricing_table WHERE is_active = 1"
        );
        $empty_nights     = $total_nights - $occupied_nights;
        $potential_revenue = round($empty_nights * $avg_price, 2);
        $occupation_rate   = $total_nights > 0 ? round($occupied_nights / $total_nights * 100) : 0;

        return [
            'total_nights'      => $total_nights,
            'occupied_nights'   => $occupied_nights,
            'empty_nights'      => $empty_nights,
            'occupation_rate'   => $occupation_rate,
            'revenue'           => $revenue,
            'potential_revenue' => $potential_revenue,
        ];
    }

    /**
     * Arrivées et départs du jour
     */
    public static function get_today_movements() {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        $today = date('Y-m-d');

        $arrivals = $wpdb->get_results($wpdb->prepare(
            "SELECT id, guest_name, adults_count, children_count, babies_count, total_price, status
             FROM $table WHERE check_in_date = %s AND status IN ('pending','confirmed')", $today
        ));
        $departures = $wpdb->get_results($wpdb->prepare(
            "SELECT id, guest_name, adults_count, children_count, babies_count, total_price, housekeeping_status
             FROM $table WHERE check_out_date = %s AND status IN ('pending','confirmed')", $today
        ));

        return ['arrivals' => $arrivals, 'departures' => $departures];
    }

    /* ------------------------------------------------------------------ */
    /*  Handlers AJAX                                                       */
    /* ------------------------------------------------------------------ */

    /** AJAX : données du calendrier pour une plage */
    public static function ajax_get_planning_data() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }

        $date_start = sanitize_text_field($_POST['date_start'] ?? date('Y-m-01'));
        $date_end   = sanitize_text_field($_POST['date_end']   ?? date('Y-m-t'));

        // Validation format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_end)) {
            wp_send_json_error('Format de date invalide');
        }

        // Mode démo : si aucune réservation en DB, retourner des données simulées
        global $wpdb;
        $has_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}riwa_bookings");
        if ($has_bookings === 0) {
            wp_send_json_success(self::get_demo_data($date_start, $date_end));
            return;
        }

        wp_send_json_success([
            'bookings'  => self::get_bookings_for_range($date_start, $date_end),
            'blocked'   => self::get_blocked_for_range($date_start, $date_end),
            'overrides' => self::get_overrides_for_range($date_start, $date_end),
            'stats'     => self::get_occupation_stats($date_start, $date_end),
            'today'     => self::get_today_movements(),
            'demo'      => false,
        ]);
    }

    /**
     * Données de démonstration — injectées quand la DB est vide
     * Toutes les dates sont relatives à aujourd'hui (pas hardcodées)
     */
    public static function get_demo_data($date_start, $date_end) {
        $today     = new DateTime('today');
        $start_win = new DateTime($date_start);
        $end_win   = new DateTime($date_end);

        // ── Générer des réservations fictives réalistes ─────────────────
        $bookings = [];
        $id = 1;

        $scenarios = [
            // [offset_from_today, duration, guests_adults, status, total_price, housekeeping]
            [-8,  5, 2, 'confirmed', 1850.00, 'ready'],
            [ 2,  4, 3, 'confirmed', 2400.00, 'ready'],
            [ 7,  2, 2, 'pending',    960.00, 'ready'],
            [10,  7, 4, 'confirmed', 4900.00, 'ready'],
            [20,  3, 2, 'confirmed', 1350.00, 'ready'],
            [25,  1, 2, 'pending',    480.00, 'ready'],
        ];

        // Arrivée aujourd'hui (pour tester les alertes)
        $scenarios[] = [0, 3, 2, 'confirmed', 1200.00, 'ready'];
        // Départ aujourd'hui (pour tester ménage)
        $scenarios[] = [-3, 3, 2, 'confirmed', 1200.00, 'pending'];

        $names = [
            'Sophie Martin', 'Jean-Pierre Dupont', 'Amira Benali',
            'Carlos Rodriguez', 'Fatima Zahra Idrissi', 'Thomas Leroy',
            'Nadia Chraibi', 'Pierre Moreau',
        ];

        foreach ($scenarios as $i => $s) {
            $check_in  = (clone $today)->modify(($s[0] >= 0 ? '+' : '') . $s[0] . ' days');
            $check_out = (clone $check_in)->modify('+' . $s[1] . ' days');

            // Ne garder que ce qui intersecte la fenêtre visible
            if ($check_out <= $start_win || $check_in >= $end_win) continue;

            $bookings[] = (object)[
                'id'                 => $id++,
                'guest_name'         => $names[$i % count($names)],
                'check_in_date'      => $check_in->format('Y-m-d'),
                'check_out_date'     => $check_out->format('Y-m-d'),
                'adults_count'       => $s[2],
                'children_count'     => 0,
                'babies_count'       => 0,
                'total_price'        => $s[4],
                'status'             => $s[3],
                'housekeeping_status'=> $s[5],
            ];
        }

        // ── Blocages fictifs ────────────────────────────────────────────
        $blocked = [];
        $block_scenarios = [
            [15, 2, 'maintenance', 'Révision chaudière'],
            [28, 3, 'private',     'Visite propriétaire'],
        ];
        $bid = 10;
        foreach ($block_scenarios as $b) {
            $bs = (clone $today)->modify('+' . $b[0] . ' days');
            $be = (clone $bs)->modify('+' . $b[1] . ' days');
            if ($be <= $start_win || $bs >= $end_win) continue;
            $blocked[] = (object)[
                'id'         => $bid++,
                'date_start' => $bs->format('Y-m-d'),
                'date_end'   => $be->format('Y-m-d'),
                'reason'     => $b[2],
                'note'       => $b[3],
            ];
        }

        // ── Overrides de prix fictifs (week-ends +20%) ──────────────────
        $overrides  = [];
        $cursor     = clone $start_win;
        while ($cursor < $end_win) {
            $dow = (int) $cursor->format('N'); // 1=Lun, 7=Dim
            if ($dow === 5 || $dow === 6) {   // Ven + Sam
                $overrides[] = (object)[
                    'id'            => 100 + (int) $cursor->format('Ymd'),
                    'override_date' => $cursor->format('Y-m-d'),
                    'price'         => 280.00,
                ];
            }
            $cursor->modify('+1 day');
        }

        // ── Stats d'occupation simulées ─────────────────────────────────
        $total_nights    = (int) $start_win->diff($end_win)->days;
        $occupied_nights = 0;
        $occupied_map    = [];
        foreach ($bookings as $b) {
            $cur = new DateTime(max($b->check_in_date, $date_start));
            $end = new DateTime(min($b->check_out_date, $date_end));
            while ($cur < $end) {
                $occupied_map[$cur->format('Y-m-d')] = true;
                $cur->modify('+1 day');
            }
        }
        $occupied_nights   = count($occupied_map);
        $empty_nights      = $total_nights - $occupied_nights;
        $occupation_rate   = $total_nights > 0 ? round($occupied_nights / $total_nights * 100) : 0;
        $confirmed_revenue = 0;
        foreach ($bookings as $b) {
            if ($b->status === 'confirmed') $confirmed_revenue += $b->total_price;
        }
        $stats = [
            'total_nights'      => $total_nights,
            'occupied_nights'   => $occupied_nights,
            'empty_nights'      => $empty_nights,
            'occupation_rate'   => $occupation_rate,
            'revenue'           => $confirmed_revenue,
            'potential_revenue' => round($empty_nights * 220, 2),
        ];

        // ── Mouvements du jour ──────────────────────────────────────────
        $today_str  = $today->format('Y-m-d');
        $arrivals   = array_values(array_filter($bookings, fn($b) => $b->check_in_date  === $today_str));
        $departures = array_values(array_filter($bookings, fn($b) => $b->check_out_date === $today_str));

        return [
            'bookings'  => $bookings,
            'blocked'   => $blocked,
            'overrides' => $overrides,
            'stats'     => $stats,
            'today'     => ['arrivals' => $arrivals, 'departures' => $departures],
            'demo'      => true,
        ];
    }

    /** AJAX : ajouter un blocage */
    public static function ajax_add_blocked() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }

        $date_start = sanitize_text_field($_POST['date_start'] ?? '');
        $date_end   = sanitize_text_field($_POST['date_end']   ?? '');
        $reason     = sanitize_key($_POST['reason'] ?? 'private');
        $note       = sanitize_text_field($_POST['note'] ?? '');

        $allowed_reasons = ['maintenance', 'private', 'seasonal', 'event'];
        if (!in_array($reason, $allowed_reasons, true)) $reason = 'private';
        if (empty($date_start) || empty($date_end) || $date_end <= $date_start) {
            wp_send_json_error('Dates invalides');
        }

        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'riwa_blocked_dates', [
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'reason'     => $reason,
            'note'       => $note,
        ], ['%s','%s','%s','%s']);

        wp_send_json_success(['id' => $wpdb->insert_id]);
    }

    /** AJAX : supprimer un blocage */
    public static function ajax_delete_blocked() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }
        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('ID invalide');

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'riwa_blocked_dates', ['id' => $id], ['%d']);
        wp_send_json_success();
    }

    /** AJAX : sauvegarder un override de prix */
    public static function ajax_save_price_override() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }

        $date  = sanitize_text_field($_POST['date']  ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $price < 0) {
            wp_send_json_error('Données invalides');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'riwa_date_overrides';

        if ($price === 0.0) {
            // Prix à 0 = supprimer l'override
            $wpdb->delete($table, ['override_date' => $date], ['%s']);
        } else {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE override_date = %s", $date
            ));
            if ($existing) {
                $wpdb->update($table, ['price' => $price], ['override_date' => $date], ['%f'], ['%s']);
            } else {
                $wpdb->insert($table, ['override_date' => $date, 'price' => $price], ['%s','%f']);
            }
        }

        wp_send_json_success();
    }

    /** AJAX : mettre à jour le statut ménage d'une réservation */
    public static function ajax_update_housekeeping() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        $status     = sanitize_key($_POST['housekeeping_status'] ?? '');
        $allowed    = ['pending', 'cleaning', 'ready'];

        if (!$booking_id || !in_array($status, $allowed, true)) {
            wp_send_json_error('Données invalides');
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'riwa_bookings',
            ['housekeeping_status' => $status],
            ['id' => $booking_id],
            ['%s'], ['%d']
        );

        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /*  Labels utilitaires                                                  */
    /* ------------------------------------------------------------------ */

    public static function get_reason_labels() {
        return [
            'maintenance' => 'Maintenance',
            'private'     => 'Usage privé',
            'seasonal'    => 'Fermeture saisonnière',
            'event'       => 'Événement privé',
        ];
    }

    public static function get_housekeeping_labels() {
        return [
            'pending'  => 'À nettoyer',
            'cleaning' => 'En cours',
            'ready'    => 'Prêt',
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Seed de données de démo (injection directe en base)                */
    /* ------------------------------------------------------------------ */

    /**
     * Injecte ~25 réservations + blocages + overrides réalistes en base.
     * Toutes les dates sont relatives à aujourd'hui.
     * Marquées avec le préfixe [DEMO] dans guest_name pour identification.
     */
    public static function seed_demo_data() {
        global $wpdb;
        $bookings_table  = $wpdb->prefix . 'riwa_bookings';
        $blocked_table   = $wpdb->prefix . 'riwa_blocked_dates';
        $overrides_table = $wpdb->prefix . 'riwa_date_overrides';
        $today           = new DateTime('today');

        // ── Réservations ────────────────────────────────────────────────
        // [offset_start, duration_nights, adults, children, babies, status, hk_status, total_price, ppn, special_requests]
        // Séquence sans aucun chevauchement (gap ≥ 1 jour entre chaque séjour).
        // Chaque offset_start = offset_start_précédent + durée_précédente + gap.
        $booking_scenarios = [
            // Passé lointain
            [-88, 7, 4, 0, 0, 'confirmed', 'ready',   5200.00, 742.85, 'Vue mer demandée'],
            // gap 1 j → -88+7+1 = -80
            [-80, 4, 2, 0, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            // -80+4+2 = -74
            [-74, 5, 3, 1, 0, 'confirmed', 'ready',   2750.00, 550.00, 'Lit bébé souhaité'],
            // -74+5+2 = -67
            [-67, 6, 2, 0, 0, 'confirmed', 'ready',   3720.00, 620.00, 'Anniversaire de mariage'],
            // -67+6+3 = -58
            [-58, 4, 4, 2, 0, 'confirmed', 'ready',   2480.00, 620.00, 'Arrivée tardive 22h'],
            // -58+4+2 = -52
            [-52, 5, 2, 0, 0, 'confirmed', 'ready',   3100.00, 620.00, ''],
            // -52+5+3 = -44
            [-44, 4, 3, 1, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            // -44+4+3 = -37
            [-37, 6, 2, 0, 0, 'confirmed', 'ready',   3720.00, 620.00, 'Groupe familial'],
            // Départ J-4 (check_out = aujourd'hui J+0) — ménage à faire
            // -37+6+27 = -4, durée 4 → check_out = J+0
            [ -4, 4, 3, 0, 0, 'confirmed', 'pending', 2480.00, 620.00, ''],
            // Arrivée aujourd'hui (check_in = J+0)
            [  0, 5, 2, 1, 0, 'confirmed', 'ready',   3100.00, 620.00, 'Enfant 6 ans, chaise haute'],
            // 0+5+2 = 7
            [  7, 3, 2, 0, 0, 'confirmed', 'ready',   1860.00, 620.00, ''],
            // 7+3+1 = 11
            [ 11, 7, 2, 0, 0, 'confirmed', 'ready',   4340.00, 620.00, 'Lune de miel — surprise'],
            // 11+7+1 = 19
            [ 19, 4, 4, 2, 1, 'confirmed', 'ready',   2480.00, 620.00, 'Pack famille demandé'],
            // 19+4+2 = 25
            [ 25, 2, 2, 0, 0, 'confirmed', 'ready',   1400.00, 700.00, 'Week-end romantique'],
            // 25+2+2 = 29
            [ 29, 5, 3, 1, 0, 'confirmed', 'ready',   3100.00, 620.00, ''],
            // 29+5+2 = 36
            [ 36, 6, 2, 0, 0, 'confirmed', 'ready',   4200.00, 700.00, 'Week-end + semaine'],
            // 36+6+2 = 44
            [ 44, 7, 2, 0, 0, 'confirmed', 'ready',   4900.00, 700.00, 'Anniversaire 50 ans'],
            // 44+7+2 = 53
            [ 53, 4, 2, 0, 0, 'pending',   'ready',   2480.00, 620.00, ''],
            // 53+4+2 = 59
            [ 59, 2, 3, 1, 0, 'pending',   'ready',   1240.00, 620.00, 'Week-end long'],
            // 59+2+2 = 63
            [ 63, 5, 4, 0, 0, 'pending',   'ready',   3100.00, 620.00, 'Groupe entreprise'],
            // 63+5+2 = 70
            [ 70, 3, 2, 0, 0, 'pending',   'ready',   2100.00, 700.00, ''],
            // Lointain
            // 70+3+5 = 78
            [ 78, 7, 2, 0, 0, 'confirmed', 'ready',   4900.00, 700.00, 'Réservation anticipée'],
            // 78+7+2 = 87
            [ 87, 4, 3, 1, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            // 87+4+2 = 93
            [ 93, 10, 6, 2, 0, 'confirmed', 'ready',  7000.00, 700.00, 'Grande villa — groupe'],
        ];

        $names_data = [
            ['Sophie', 'Martin',       'sophie.martin@gmail.com',     '+33 6 12 34 56 78'],
            ['Jean-Pierre', 'Dupont',  'jp.dupont@outlook.fr',        '+33 6 23 45 67 89'],
            ['Amira', 'Benali',        'amira.benali@gmail.com',      '+212 6 61 23 45 67'],
            ['Carlos', 'Rodriguez',    'c.rodriguez@gmail.com',       '+34 612 345 678'],
            ['Fatima Zahra', 'Idrissi','fz.idrissi@hotmail.com',      '+212 6 62 34 56 78'],
            ['Thomas', 'Leroy',        'thomas.leroy@gmail.com',      '+33 6 45 67 89 01'],
            ['Nadia', 'Chraibi',       'nadia.chraibi@gmail.com',     '+212 6 63 45 67 89'],
            ['Pierre', 'Moreau',       'pierre.moreau@yahoo.fr',      '+33 6 56 78 90 12'],
            ['Yasmine', 'El Fassi',    'yasmine.elfassi@gmail.com',   '+212 6 64 56 78 90'],
            ['Marc', 'Dubois',         'marc.dubois@gmail.com',       '+33 6 67 89 01 23'],
            ['Layla', 'Amrani',        'layla.amrani@hotmail.com',    '+212 6 65 67 89 01'],
            ['Antoine', 'Bernard',     'a.bernard@gmail.com',         '+33 6 78 90 12 34'],
            ['Sara', 'Tazi',           'sara.tazi@gmail.com',         '+212 6 66 78 90 12'],
            ['Julien', 'Petit',        'julien.petit@gmail.com',      '+33 6 89 01 23 45'],
            ['Hind', 'Benkiran',       'hind.benkiran@gmail.com',     '+212 6 67 89 01 23'],
            ['Nicolas', 'Simon',       'nicolas.simon@outlook.fr',    '+33 6 90 12 34 56'],
            ['Rim', 'Alaoui',          'rim.alaoui@gmail.com',        '+212 6 68 90 12 34'],
            ['David', 'Laurent',       'david.laurent@gmail.com',     '+33 6 01 23 45 67'],
            ['Kenza', 'Berrada',       'kenza.berrada@gmail.com',     '+212 6 69 01 23 45'],
            ['Christophe', 'Michel',   'c.michel@gmail.com',          '+33 6 12 34 56 79'],
            ['Salma', 'Ouazzani',      'salma.ouazzani@gmail.com',    '+212 6 70 12 34 56'],
            ['François', 'Garcia',     'f.garcia@gmail.com',          '+33 6 23 45 67 90'],
            ['Hajar', 'Tahiri',        'hajar.tahiri@gmail.com',      '+212 6 71 23 45 67'],
            ['Emmanuel', 'Roux',       'e.roux@gmail.com',            '+33 6 34 56 78 91'],
        ];

        $inserted_booking_ids = [];

        foreach ($booking_scenarios as $i => $s) {
            list($offset, $duration, $adults, $children, $babies, $status, $hk, $total, $ppn, $requests) = $s;
            $check_in  = (clone $today)->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
            $check_out = (clone $check_in)->modify('+' . $duration . ' days');
            $ci_str    = $check_in->format('Y-m-d');
            $co_str    = $check_out->format('Y-m-d');

            // Ignorer si chevauchement avec une réservation déjà insérée
            if (self::has_overlap($ci_str, $co_str)) continue;

            $nd        = $names_data[$i % count($names_data)];
            $full_name = $nd[0] . ' ' . $nd[1];

            $wpdb->insert($bookings_table, [
                'guest_name'         => '[DEMO] ' . $full_name,
                'guest_email'        => $nd[2],
                'guest_phone'        => $nd[3],
                'check_in_date'      => $ci_str,
                'check_out_date'     => $co_str,
                'adults_count'       => $adults,
                'children_count'     => $children,
                'babies_count'       => $babies,
                'special_requests'   => $requests,
                'total_price'        => $total,
                'price_per_night'    => $ppn,
                'status'             => $status,
                'housekeeping_status'=> $hk,
            ], ['%s','%s','%s','%s','%s','%d','%d','%d','%s','%f','%f','%s','%s']);

            $inserted_booking_ids[] = $wpdb->insert_id;
        }

        // ── Blocages ────────────────────────────────────────────────────
        $block_scenarios = [
            [ 3, 2,  'maintenance', 'Révision chaudière + climatisation'],
            [45, 3,  'private',     'Visite propriétaire'],
            [55, 5,  'seasonal',    'Fermeture annuelle'],
            [72, 1,  'maintenance', 'Nettoyage piscine'],
            [88, 4,  'event',       'Tournage photo'],
        ];
        foreach ($block_scenarios as $b) {
            $bs = (clone $today)->modify('+' . $b[0] . ' days');
            $be = (clone $bs)->modify('+' . $b[1] . ' days');
            $wpdb->insert($blocked_table, [
                'date_start' => $bs->format('Y-m-d'),
                'date_end'   => $be->format('Y-m-d'),
                'reason'     => $b[2],
                'note'       => $b[3],
            ], ['%s','%s','%s','%s']);
        }

        // ── Overrides de prix : tous les week-ends sur 90 jours à venir ─
        $cursor = clone $today;
        $limit  = (clone $today)->modify('+90 days');
        while ($cursor < $limit) {
            $dow = (int) $cursor->format('N');
            if ($dow === 5 || $dow === 6 || $dow === 7) { // Ven Sam Dim
                // Ne pas écraser si déjà existant
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $overrides_table WHERE override_date = %s", $cursor->format('Y-m-d')
                ));
                if (!$exists) {
                    $wpdb->insert($overrides_table, [
                        'override_date' => $cursor->format('Y-m-d'),
                        'price'         => 280.00,
                    ], ['%s','%f']);
                }
            }
            $cursor->modify('+1 day');
        }

        return count($inserted_booking_ids);
    }

    /**
     * Supprime toutes les données de démo ([DEMO] dans guest_name)
     * et les blocages/overrides insérés par le seed
     */
    public static function clear_demo_data() {
        global $wpdb;
        $bookings_table  = $wpdb->prefix . 'riwa_bookings';
        $blocked_table   = $wpdb->prefix . 'riwa_blocked_dates';
        $overrides_table = $wpdb->prefix . 'riwa_date_overrides';

        // Récupérer les IDs demo avant suppression
        $demo_ids = $wpdb->get_col(
            "SELECT id FROM $bookings_table WHERE guest_name LIKE '[DEMO]%'"
        );

        // Supprimer les upsells liés
        if (!empty($demo_ids)) {
            $placeholders = implode(',', array_map('intval', $demo_ids));
            $wpdb->query("DELETE FROM {$wpdb->prefix}riwa_booking_upsells WHERE booking_id IN ($placeholders)");
        }

        // Supprimer les réservations demo
        $wpdb->query("DELETE FROM $bookings_table WHERE guest_name LIKE '[DEMO]%'");

        // Vider tous les blocages et overrides (insérés par le seed)
        $wpdb->query("TRUNCATE TABLE $blocked_table");
        $wpdb->query("TRUNCATE TABLE $overrides_table");
    }

    /** AJAX : injecter les données de démo */
    public static function ajax_seed_demo() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }
        $count = self::seed_demo_data();
        wp_send_json_success(['count' => $count]);
    }

    /** AJAX : vérifier si des données démo existent */
    public static function ajax_demo_status() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}riwa_bookings WHERE guest_name LIKE '[DEMO]%'"
        );
        wp_send_json_success(['has_demo' => $count > 0, 'count' => $count]);
    }

    /** AJAX : effacer les données de démo */
    public static function ajax_clear_demo() {
        if (!current_user_can('manage_options') ||
            !wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_planning_nonce')) {
            wp_send_json_error('Non autorisé');
        }
        self::clear_demo_data();
        wp_send_json_success();
    }

}

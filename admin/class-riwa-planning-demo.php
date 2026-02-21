<?php
/**
 * Riwa_Planning_Demo — Données de démonstration du planning
 *
 * Gère la génération, l'injection et la suppression des données
 * de démonstration (réservations + blocages + overrides fictifs).
 * Séparé de Riwa_Planning pour garder chaque fichier sous 350 lignes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Planning_Demo {

    /* ------------------------------------------------------------------ */
    /*  Données démo en mémoire (calendrier vide)                          */
    /* ------------------------------------------------------------------ */

    /**
     * Génère des données fictives pour l'affichage du calendrier quand la DB est vide.
     * Toutes les dates sont relatives à aujourd'hui.
     */
    public static function get_demo_data($date_start, $date_end) {
        $today     = new DateTime('today');
        $start_win = new DateTime($date_start);
        $end_win   = new DateTime($date_end);

        // ── Réservations fictives ────────────────────────────────────────
        $bookings = [];
        $id = 1;

        $scenarios = [
            [-8,  5, 2, 'confirmed', 1850.00, 'ready'],
            [ 2,  4, 3, 'confirmed', 2400.00, 'ready'],
            [ 7,  2, 2, 'pending',    960.00, 'ready'],
            [10,  7, 4, 'confirmed', 4900.00, 'ready'],
            [20,  3, 2, 'confirmed', 1350.00, 'ready'],
            [25,  1, 2, 'pending',    480.00, 'ready'],
            [  0, 3, 2, 'confirmed', 1200.00, 'ready'],   // Arrivée aujourd'hui
            [ -3, 3, 2, 'confirmed', 1200.00, 'pending'], // Départ aujourd'hui → ménage
        ];

        $names = [
            'Sophie Martin', 'Jean-Pierre Dupont', 'Amira Benali',
            'Carlos Rodriguez', 'Fatima Zahra Idrissi', 'Thomas Leroy',
            'Nadia Chraibi', 'Pierre Moreau',
        ];

        foreach ($scenarios as $i => $s) {
            $check_in  = (clone $today)->modify(($s[0] >= 0 ? '+' : '') . $s[0] . ' days');
            $check_out = (clone $check_in)->modify('+' . $s[1] . ' days');

            if ($check_out <= $start_win || $check_in >= $end_win) continue;

            $bookings[] = (object)[
                'id'                  => $id++,
                'guest_name'          => $names[$i % count($names)],
                'check_in_date'       => $check_in->format('Y-m-d'),
                'check_out_date'      => $check_out->format('Y-m-d'),
                'adults_count'        => $s[2],
                'children_count'      => 0,
                'babies_count'        => 0,
                'total_price'         => $s[4],
                'status'              => $s[3],
                'housekeeping_status' => $s[5],
            ];
        }

        // ── Blocages fictifs ─────────────────────────────────────────────
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

        // ── Overrides de prix fictifs (week-ends +20%) ───────────────────
        $overrides = [];
        $cursor    = clone $start_win;
        while ($cursor < $end_win) {
            $dow = (int) $cursor->format('N');
            if ($dow === 5 || $dow === 6) {
                $overrides[] = (object)[
                    'id'            => 100 + (int) $cursor->format('Ymd'),
                    'override_date' => $cursor->format('Y-m-d'),
                    'price'         => 280.00,
                ];
            }
            $cursor->modify('+1 day');
        }

        // ── Stats d'occupation simulées ──────────────────────────────────
        $total_nights = (int) $start_win->diff($end_win)->days;
        $occupied_map = [];
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

        // ── Mouvements du jour ───────────────────────────────────────────
        $today_str  = $today->format('Y-m-d');
        $arrivals   = array_values(array_filter($bookings, fn($b) => $b->check_in_date  === $today_str));
        $departures = array_values(array_filter($bookings, fn($b) => $b->check_out_date === $today_str));

        return [
            'bookings'  => $bookings,
            'blocked'   => $blocked,
            'overrides' => $overrides,
            'stats'     => [
                'total_nights'      => $total_nights,
                'occupied_nights'   => $occupied_nights,
                'empty_nights'      => $empty_nights,
                'occupation_rate'   => $occupation_rate,
                'revenue'           => $confirmed_revenue,
                'potential_revenue' => round($empty_nights * 220, 2),
            ],
            'today'     => ['arrivals' => $arrivals, 'departures' => $departures],
            'demo'      => true,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Seed en base (injection permanente)                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Injecte ~24 réservations + blocages + overrides réalistes en base.
     * Marquées avec le préfixe [DEMO] dans guest_name.
     */
    public static function seed_demo_data() {
        global $wpdb;
        $bookings_table  = $wpdb->prefix . 'riwa_bookings';
        $blocked_table   = $wpdb->prefix . 'riwa_blocked_dates';
        $overrides_table = $wpdb->prefix . 'riwa_date_overrides';
        $today           = new DateTime('today');

        $booking_scenarios = [
            [-88, 7, 4, 0, 0, 'confirmed', 'ready',   5200.00, 742.85, 'Vue mer demandée'],
            [-80, 4, 2, 0, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            [-74, 5, 3, 1, 0, 'confirmed', 'ready',   2750.00, 550.00, 'Lit bébé souhaité'],
            [-67, 6, 2, 0, 0, 'confirmed', 'ready',   3720.00, 620.00, 'Anniversaire de mariage'],
            [-58, 4, 4, 2, 0, 'confirmed', 'ready',   2480.00, 620.00, 'Arrivée tardive 22h'],
            [-52, 5, 2, 0, 0, 'confirmed', 'ready',   3100.00, 620.00, ''],
            [-44, 4, 3, 1, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            [-37, 6, 2, 0, 0, 'confirmed', 'ready',   3720.00, 620.00, 'Groupe familial'],
            [ -4, 4, 3, 0, 0, 'confirmed', 'pending', 2480.00, 620.00, ''],
            [  0, 5, 2, 1, 0, 'confirmed', 'ready',   3100.00, 620.00, 'Enfant 6 ans, chaise haute'],
            [  7, 3, 2, 0, 0, 'confirmed', 'ready',   1860.00, 620.00, ''],
            [ 11, 7, 2, 0, 0, 'confirmed', 'ready',   4340.00, 620.00, 'Lune de miel — surprise'],
            [ 19, 4, 4, 2, 1, 'confirmed', 'ready',   2480.00, 620.00, 'Pack famille demandé'],
            [ 25, 2, 2, 0, 0, 'confirmed', 'ready',   1400.00, 700.00, 'Week-end romantique'],
            [ 29, 5, 3, 1, 0, 'confirmed', 'ready',   3100.00, 620.00, ''],
            [ 36, 6, 2, 0, 0, 'confirmed', 'ready',   4200.00, 700.00, 'Week-end + semaine'],
            [ 44, 7, 2, 0, 0, 'confirmed', 'ready',   4900.00, 700.00, 'Anniversaire 50 ans'],
            [ 53, 4, 2, 0, 0, 'pending',   'ready',   2480.00, 620.00, ''],
            [ 59, 2, 3, 1, 0, 'pending',   'ready',   1240.00, 620.00, 'Week-end long'],
            [ 63, 5, 4, 0, 0, 'pending',   'ready',   3100.00, 620.00, 'Groupe entreprise'],
            [ 70, 3, 2, 0, 0, 'pending',   'ready',   2100.00, 700.00, ''],
            [ 78, 7, 2, 0, 0, 'confirmed', 'ready',   4900.00, 700.00, 'Réservation anticipée'],
            [ 87, 4, 3, 1, 0, 'confirmed', 'ready',   2480.00, 620.00, ''],
            [ 93,10, 6, 2, 0, 'confirmed', 'ready',   7000.00, 700.00, 'Grande villa — groupe'],
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

        $inserted = 0;
        foreach ($booking_scenarios as $i => $s) {
            list($offset, $duration, $adults, $children, $babies, $status, $hk, $total, $ppn, $requests) = $s;
            $check_in  = (clone $today)->modify(($offset >= 0 ? '+' : '') . $offset . ' days');
            $check_out = (clone $check_in)->modify('+' . $duration . ' days');
            $ci_str    = $check_in->format('Y-m-d');
            $co_str    = $check_out->format('Y-m-d');

            if (Riwa_Planning::has_overlap($ci_str, $co_str)) continue;

            $nd = $names_data[$i % count($names_data)];
            $wpdb->insert($bookings_table, [
                'guest_name'          => '[DEMO] ' . $nd[0] . ' ' . $nd[1],
                'guest_email'         => $nd[2],
                'guest_phone'         => $nd[3],
                'check_in_date'       => $ci_str,
                'check_out_date'      => $co_str,
                'adults_count'        => $adults,
                'children_count'      => $children,
                'babies_count'        => $babies,
                'special_requests'    => $requests,
                'total_price'         => $total,
                'price_per_night'     => $ppn,
                'status'              => $status,
                'housekeeping_status' => $hk,
            ], ['%s','%s','%s','%s','%s','%d','%d','%d','%s','%f','%f','%s','%s']);
            $inserted++;
        }

        // ── Blocages ─────────────────────────────────────────────────────
        $block_scenarios = [
            [ 3, 2, 'maintenance', 'Révision chaudière + climatisation'],
            [45, 3, 'private',     'Visite propriétaire'],
            [55, 5, 'seasonal',    'Fermeture annuelle'],
            [72, 1, 'maintenance', 'Nettoyage piscine'],
            [88, 4, 'event',       'Tournage photo'],
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

        // ── Overrides week-ends sur 90 jours ─────────────────────────────
        $cursor = clone $today;
        $limit  = (clone $today)->modify('+90 days');
        while ($cursor < $limit) {
            $dow = (int) $cursor->format('N');
            if ($dow === 5 || $dow === 6 || $dow === 7) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $overrides_table WHERE override_date = %s",
                    $cursor->format('Y-m-d')
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

        return $inserted;
    }

    /* ------------------------------------------------------------------ */
    /*  Suppression des données démo                                        */
    /* ------------------------------------------------------------------ */

    public static function clear_demo_data() {
        global $wpdb;

        $demo_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}riwa_bookings WHERE guest_name LIKE %s",
                '[DEMO]%'
            )
        );

        if (!empty($demo_ids)) {
            $placeholders = implode(',', array_fill(0, count($demo_ids), '%d'));
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->prefix}riwa_booking_upsells WHERE booking_id IN ($placeholders)",
                    ...$demo_ids
                )
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}riwa_bookings WHERE guest_name LIKE %s",
                '[DEMO]%'
            )
        );

        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}riwa_blocked_dates");
        $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}riwa_date_overrides");
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX handlers démo                                                  */
    /* ------------------------------------------------------------------ */

    public static function ajax_seed_demo() {
        Riwa_Security::check_admin('riwa_planning_nonce');
        $count = self::seed_demo_data();
        wp_send_json_success(['count' => $count]);
    }

    public static function ajax_demo_status() {
        Riwa_Security::check_admin('riwa_planning_nonce');
        global $wpdb;
        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}riwa_bookings WHERE guest_name LIKE '[DEMO]%'"
        );
        wp_send_json_success(['has_demo' => $count > 0, 'count' => $count]);
    }

    public static function ajax_clear_demo() {
        Riwa_Security::check_admin('riwa_planning_nonce');
        self::clear_demo_data();
        wp_send_json_success();
    }
}

<?php
/**
 * Riwa_Planning — Noyau du module Planning
 *
 * Création des tables, vérification de disponibilité, requêtes calendrier,
 * statistiques d'occupation et labels utilitaires.
 *
 * Les handlers AJAX sont dans class-riwa-planning-ajax.php
 * Les données de démonstration sont dans class-riwa-planning-demo.php
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

        $overrides_table = $wpdb->prefix . 'riwa_date_overrides';
        $sql2 = "CREATE TABLE $overrides_table (
            id            mediumint(9) NOT NULL AUTO_INCREMENT,
            override_date date NOT NULL,
            price         decimal(10,2) NOT NULL,
            created_at    datetime DEFAULT CURRENT_TIMESTAMP,
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
    /*  Requêtes calendrier                                                 */
    /* ------------------------------------------------------------------ */

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

    /* ------------------------------------------------------------------ */
    /*  Statistiques d'occupation                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Stats d'occupation pour une plage donnée.
     * Retourne : total_nights, occupied_nights, empty_nights,
     *            occupation_rate, revenue, potential_revenue
     */
    public static function get_occupation_stats($date_start, $date_end) {
        global $wpdb;

        $start_dt     = new DateTime($date_start);
        $end_dt       = new DateTime($date_end);
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

        // Nuits réellement occupées dans la plage
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

        // Revenue des réservations confirmées
        $revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price),0) FROM $bookings_table
             WHERE status = 'confirmed'
               AND check_in_date < %s AND check_out_date > %s",
            $date_end, $date_start
        ));

        $pricing_table = $wpdb->prefix . 'riwa_pricing';
        $avg_price = (float) $wpdb->get_var(
            "SELECT COALESCE(AVG(price_per_night), 150) FROM $pricing_table WHERE is_active = 1"
        );
        $empty_nights      = $total_nights - $occupied_nights;
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
     * Arrivées et départs du jour.
     */
    public static function get_today_movements() {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        $today = date('Y-m-d');

        return [
            'arrivals'   => $wpdb->get_results($wpdb->prepare(
                "SELECT id, guest_name, adults_count, children_count, babies_count, total_price, status
                 FROM $table WHERE check_in_date = %s AND status IN ('pending','confirmed')", $today
            )),
            'departures' => $wpdb->get_results($wpdb->prepare(
                "SELECT id, guest_name, adults_count, children_count, babies_count, total_price, housekeeping_status
                 FROM $table WHERE check_out_date = %s AND status IN ('pending','confirmed')", $today
            )),
        ];
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
}

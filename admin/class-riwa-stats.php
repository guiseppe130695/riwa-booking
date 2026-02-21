<?php
/**
 * Statistiques & Pulse Board
 * Calculs SQL + handler AJAX unique pour les 3 onglets (pulse / analysis / forecast)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Stats {

    /* ------------------------------------------------------------------ */
    /*  Utilitaires internes                                                */
    /* ------------------------------------------------------------------ */

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'riwa_bookings';
    }

    /** Nuits entre deux dates YYYY-MM-DD */
    private static function nights($start, $end) {
        $a = new DateTime($start);
        $b = new DateTime($end);
        return max(0, (int) $a->diff($b)->days);
    }

    /* ------------------------------------------------------------------ */
    /*  PULSE — Score de santé                                             */
    /* ------------------------------------------------------------------ */

    public static function get_health_score() {
        global $wpdb;
        $table  = self::table();

        // ── Taux d'occupation du mois courant (40 pts) ────────────────
        $y  = date('Y');
        $m  = date('m');
        $month_start = "$y-$m-01";
        $month_end   = date('Y-m-t');

        $occ = Riwa_Planning::get_occupation_stats($month_start, date('Y-m-d', strtotime($month_end . ' +1 day')));
        $occ_rate  = isset($occ['occupation_rate']) ? (int) $occ['occupation_rate'] : 0;
        $occ_pts   = round($occ_rate * 0.40); // max 40

        // ── Taux de confirmation du mois (30 pts) ─────────────────────
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(status='confirmed') as confirmed,
                SUM(status='cancelled') as cancelled
             FROM $table
             WHERE MONTH(created_at) = %d AND YEAR(created_at) = %d",
            $m, $y
        ));
        $total     = max(1, (int) $counts->total);
        $confirmed = (int) $counts->confirmed;
        $cancelled = (int) $counts->cancelled;
        $conf_pts  = round($confirmed / $total * 30);          // max 30
        $canc_rate = round($cancelled / $total * 100);
        $canc_pts  = round((1 - $cancelled / $total) * 20);    // max 20

        // ── Ménage à jour : départs ≤ aujourd'hui non-ready (10 pts) ──
        $total_dep = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE status='confirmed' AND check_out_date <= %s",
            date('Y-m-d')
        ));
        $ready_dep = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE status='confirmed' AND check_out_date <= %s AND housekeeping_status='ready'",
            date('Y-m-d')
        ));
        $hk_pts = $total_dep > 0 ? round($ready_dep / $total_dep * 10) : 10;

        $score = $occ_pts + $conf_pts + $canc_pts + $hk_pts;
        $score = min(100, max(0, $score));

        if ($score >= 90)      { $grade = 'A'; $color = '#16a34a'; $label = 'Excellente'; }
        elseif ($score >= 75)  { $grade = 'B'; $color = '#2563eb'; $label = 'Bonne'; }
        elseif ($score >= 50)  { $grade = 'C'; $color = '#d97706'; $label = 'Correcte'; }
        else                   { $grade = 'D'; $color = '#dc2626'; $label = 'À améliorer'; }

        return [
            'score'     => $score,
            'grade'     => $grade,
            'color'     => $color,
            'label'     => $label,
            'breakdown' => [
                'occupation'   => $occ_pts,
                'confirmation' => $conf_pts,
                'annulation'   => $canc_pts,
                'menage'       => $hk_pts,
            ],
            'occ_rate'  => $occ_rate,
            'canc_rate' => $canc_rate,
        ];
    }

    /**
     * CA jour par jour sur les 7 derniers jours glissants
     * Retourne ['labels' => [...], 'values' => [...], 'total' => float]
     */
    public static function get_week_revenue() {
        global $wpdb;
        $table = self::table();

        $labels = [];
        $values = [];
        $jours  = ['Di','Lu','Ma','Me','Je','Ve','Sa'];

        for ($i = 6; $i >= 0; $i--) {
            $d   = date('Y-m-d', strtotime("-{$i} days"));
            $day = (int) date('w', strtotime($d));
            $rev = (float) $wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(total_price), 0) FROM $table
                 WHERE status='confirmed' AND check_in_date = %s",
                $d
            ));
            $labels[] = $jours[$day] . ' ' . date('j', strtotime($d));
            $values[] = $rev;
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'total'  => array_sum($values),
        ];
    }

    /**
     * Alertes actionnables
     * Retourne tableau de ['type' => 'warning|danger|info', 'message' => '', 'link' => '']
     */
    public static function get_alerts() {
        global $wpdb;
        $table  = self::table();
        $alerts = [];

        // Réservations en attente
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='pending'");
        if ($pending > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'dashicons-clock',
                'message' => $pending . ' réservation' . ($pending > 1 ? 's' : '') . ' en attente de confirmation',
                'action'  => 'Confirmer',
                'link'    => admin_url('admin.php?page=riwa-bookings&section=bookings'),
            ];
        }

        // Ménage en retard
        $hk_late = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE status='confirmed' AND check_out_date <= %s AND housekeeping_status != 'ready'",
            date('Y-m-d')
        ));
        if ($hk_late > 0) {
            $alerts[] = [
                'type'    => 'danger',
                'icon'    => 'dashicons-cleaning',
                'message' => $hk_late . ' départ' . ($hk_late > 1 ? 's' : '') . ' avec ménage en attente',
                'action'  => 'Voir le planning',
                'link'    => admin_url('admin.php?page=riwa-bookings&section=planning'),
            ];
        }

        // Taux d'annulation élevé ce mois
        $m = date('m'); $y = date('Y');
        $total_m    = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE MONTH(created_at)=%d AND YEAR(created_at)=%d", $m, $y
        ));
        $cancelled_m = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status='cancelled' AND MONTH(created_at)=%d AND YEAR(created_at)=%d", $m, $y
        ));
        if ($total_m > 0 && ($cancelled_m / $total_m) > 0.20) {
            $pct = round($cancelled_m / $total_m * 100);
            $alerts[] = [
                'type'    => 'danger',
                'icon'    => 'dashicons-dismiss',
                'message' => "Taux d'annulation élevé ce mois : {$pct}%",
                'action'  => null,
                'link'    => null,
            ];
        }

        // Nuits libres cette semaine
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end   = date('Y-m-d', strtotime('sunday this week'));
        $occ = Riwa_Planning::get_occupation_stats($week_start, $week_end);
        $empty = isset($occ['empty_nights']) ? (int) $occ['empty_nights'] : 0;
        if ($empty > 0) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'dashicons-calendar-alt',
                'message' => "{$empty} nuit" . ($empty > 1 ? 's' : '') . ' libre' . ($empty > 1 ? 's' : '') . ' cette semaine',
                'action'  => 'Voir le planning',
                'link'    => admin_url('admin.php?page=riwa-bookings&section=planning'),
            ];
        }

        return $alerts;
    }

    /* ------------------------------------------------------------------ */
    /*  ANALYSE — KPIs annuels + mensuel + profil voyageurs               */
    /* ------------------------------------------------------------------ */

    public static function get_yearly_stats($year) {
        global $wpdb;
        $table = self::table();
        $y     = intval($year);

        // CA confirmé
        $revenue = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d", $y
        ));

        // Nombre de réservations (hors annulées)
        $bookings_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table
             WHERE status IN ('confirmed','pending') AND YEAR(check_in_date)=%d", $y
        ));

        // Durée moyenne de séjour
        $avg_nights = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(AVG(DATEDIFF(check_out_date, check_in_date)), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d", $y
        ));

        // Revenu moyen par réservation
        $avg_revenue = $bookings_count > 0 ? round($revenue / $bookings_count, 2) : 0;

        // Taux d'annulation
        $total_all  = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE YEAR(created_at)=%d", $y
        ));
        $total_canc = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE status='cancelled' AND YEAR(created_at)=%d", $y
        ));
        $canc_rate = $total_all > 0 ? round($total_canc / $total_all * 100) : 0;

        // Taux d'occupation annuel
        $occ = Riwa_Planning::get_occupation_stats("{$y}-01-01", ($y + 1) . "-01-01");
        $occ_rate = isset($occ['occupation_rate']) ? $occ['occupation_rate'] : 0;

        // Délai moyen réservation → arrivée (jours)
        $avg_lead_time = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(AVG(DATEDIFF(check_in_date, created_at)), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d", $y
        ));

        return [
            'revenue'       => $revenue,
            'bookings'      => $bookings_count,
            'avg_nights'    => round($avg_nights, 1),
            'avg_revenue'   => $avg_revenue,
            'canc_rate'     => $canc_rate,
            'occ_rate'      => $occ_rate,
            'avg_lead_time' => round($avg_lead_time),
        ];
    }

    /**
     * CA + occupation par mois pour une année
     * Retourne tableau indexé 1-12 : ['month', 'revenue', 'bookings', 'occ_rate']
     */
    public static function get_monthly_breakdown($year) {
        global $wpdb;
        $table = self::table();
        $y     = intval($year);

        // CA + nb réservations par mois
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT MONTH(check_in_date) as mo,
                    COALESCE(SUM(total_price), 0) as revenue,
                    COUNT(*) as bookings
             FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d
             GROUP BY mo ORDER BY mo",
            $y
        ));

        $by_month = [];
        foreach ($rows as $r) {
            $by_month[(int)$r->mo] = [
                'revenue'  => (float) $r->revenue,
                'bookings' => (int)   $r->bookings,
            ];
        }

        $months_fr = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $result    = [];

        for ($mo = 1; $mo <= 12; $mo++) {
            $start   = sprintf('%04d-%02d-01', $y, $mo);
            $end_day = date('t', mktime(0,0,0,$mo,1,$y));
            $end     = sprintf('%04d-%02d-%02d', $y, $mo, $end_day);
            $occ     = Riwa_Planning::get_occupation_stats($start, date('Y-m-d', strtotime($end . ' +1 day')));

            $result[] = [
                'month'    => $months_fr[$mo - 1],
                'revenue'  => isset($by_month[$mo]) ? $by_month[$mo]['revenue']  : 0,
                'bookings' => isset($by_month[$mo]) ? $by_month[$mo]['bookings'] : 0,
                'occ_rate' => isset($occ['occupation_rate']) ? $occ['occupation_rate'] : 0,
            ];
        }

        return $result;
    }

    /**
     * Profil des voyageurs : répartition groupes, durées, jours d'arrivée
     */
    public static function get_traveler_profile() {
        global $wpdb;
        $table = self::table();

        // Répartition adultes / enfants / bébés
        $totals = $wpdb->get_row(
            "SELECT COALESCE(SUM(adults_count),0) adults,
                    COALESCE(SUM(children_count),0) children,
                    COALESCE(SUM(babies_count),0) babies
             FROM $table WHERE status='confirmed'"
        );

        // Répartition durées (tranches)
        $durations_raw = $wpdb->get_results(
            "SELECT DATEDIFF(check_out_date, check_in_date) as nights, COUNT(*) as cnt
             FROM $table WHERE status='confirmed'
             GROUP BY nights ORDER BY nights"
        );
        $dur = ['1-3' => 0, '4-7' => 0, '8+' => 0];
        foreach ($durations_raw as $d) {
            $n = (int) $d->nights;
            if ($n <= 3)      $dur['1-3'] += (int) $d->cnt;
            elseif ($n <= 7)  $dur['4-7'] += (int) $d->cnt;
            else              $dur['8+']  += (int) $d->cnt;
        }

        // Jours d'arrivée dominants (0=Dim … 6=Sam)
        $arrival_days = $wpdb->get_results(
            "SELECT DAYOFWEEK(check_in_date) - 1 as dow, COUNT(*) as cnt
             FROM $table WHERE status='confirmed'
             GROUP BY dow ORDER BY cnt DESC LIMIT 7"
        );
        $jours = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
        $days_formatted = [];
        foreach ($arrival_days as $row) {
            $days_formatted[] = [
                'day' => $jours[(int)$row->dow],
                'cnt' => (int) $row->cnt,
            ];
        }

        return [
            'travelers'    => [
                'adults'   => (int) $totals->adults,
                'children' => (int) $totals->children,
                'babies'   => (int) $totals->babies,
            ],
            'durations'    => $dur,
            'arrival_days' => $days_formatted,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  PRÉVISION — Projection fin d'année + Opportunités                 */
    /* ------------------------------------------------------------------ */

    /**
     * Projection CA fin d'année basée sur les mois passés de l'année précédente
     */
    public static function get_forecast() {
        global $wpdb;
        $table   = self::table();
        $year    = (int) date('Y');
        $cur_mo  = (int) date('n');

        // CA réalisé cette année (mois 1 à cur_mo)
        $realized = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d AND MONTH(check_in_date) <= %d",
            $year, $cur_mo
        ));

        // CA des mois restants basé sur l'année précédente (mois cur_mo+1 à 12)
        $prev_rest = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d AND MONTH(check_in_date) > %d",
            $year - 1, $cur_mo
        ));

        // CA réalisé l'année précédente (pour comparer)
        $prev_year_total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total_price), 0) FROM $table
             WHERE status='confirmed' AND YEAR(check_in_date)=%d",
            $year - 1
        ));

        $projected_total = $realized + $prev_rest;
        $vs_prev = $prev_year_total > 0
            ? round(($projected_total - $prev_year_total) / $prev_year_total * 100)
            : null;

        // Données mois par mois pour le graphique empilé
        $months_fr = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $monthly   = [];
        for ($mo = 1; $mo <= 12; $mo++) {
            if ($mo <= $cur_mo) {
                // Réalisé
                $rev = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(total_price), 0) FROM $table
                     WHERE status='confirmed' AND YEAR(check_in_date)=%d AND MONTH(check_in_date)=%d",
                    $year, $mo
                ));
                $monthly[] = ['month' => $months_fr[$mo-1], 'realized' => $rev, 'estimated' => 0];
            } else {
                // Estimé depuis N-1
                $rev = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(total_price), 0) FROM $table
                     WHERE status='confirmed' AND YEAR(check_in_date)=%d AND MONTH(check_in_date)=%d",
                    $year - 1, $mo
                ));
                $monthly[] = ['month' => $months_fr[$mo-1], 'realized' => 0, 'estimated' => $rev];
            }
        }

        return [
            'year'            => $year,
            'cur_month'       => $cur_mo,
            'realized'        => $realized,
            'estimated_rest'  => $prev_rest,
            'projected_total' => $projected_total,
            'prev_year_total' => $prev_year_total,
            'vs_prev_pct'     => $vs_prev,
            'monthly'         => $monthly,
        ];
    }

    /**
     * Opportunités : trous < 3 nuits entre réservations + impact +10% prix
     */
    public static function get_opportunities() {
        global $wpdb;
        $table = self::table();

        // ── Trous rentables ───────────────────────────────────────────
        $bookings_ordered = $wpdb->get_results(
            "SELECT check_in_date, check_out_date FROM $table
             WHERE status='confirmed' AND check_out_date >= CURDATE()
             ORDER BY check_in_date ASC"
        );

        $gaps = [];
        for ($i = 0; $i < count($bookings_ordered) - 1; $i++) {
            $out  = $bookings_ordered[$i]->check_out_date;
            $in   = $bookings_ordered[$i+1]->check_in_date;
            $diff = self::nights($out, $in);
            if ($diff > 0 && $diff <= 3) {
                $gaps[] = [
                    'from'   => $out,
                    'to'     => $in,
                    'nights' => $diff,
                ];
            }
        }

        // ── Impact +10% prix ──────────────────────────────────────────
        // Revenu actuel confirmé sur les 12 prochains mois
        $future_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_price), 0) FROM $table
             WHERE status='confirmed' AND check_in_date > CURDATE()
               AND check_in_date <= DATE_ADD(CURDATE(), INTERVAL 12 MONTH)"
        );
        $impact_10pct = round($future_revenue * 0.10);

        // Mois les plus rentables (top 3) pour suggestion de prix
        $top_months = $wpdb->get_results(
            "SELECT MONTH(check_in_date) as mo,
                    COALESCE(AVG(price_per_night), 0) as avg_ppn,
                    COUNT(*) as cnt
             FROM $table WHERE status='confirmed'
             GROUP BY mo ORDER BY avg_ppn DESC LIMIT 3"
        );
        $months_fr = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $top = [];
        foreach ($top_months as $r) {
            $top[] = [
                'month'  => $months_fr[(int)$r->mo],
                'avg_ppn' => round((float)$r->avg_ppn),
                'cnt'    => (int) $r->cnt,
            ];
        }

        return [
            'gaps'          => $gaps,
            'impact_10pct'  => $impact_10pct,
            'top_months'    => $top,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Handler AJAX unique                                                 */
    /* ------------------------------------------------------------------ */

    public static function ajax_get_stats_data() {
        check_ajax_referer('riwa_stats_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé');
        }

        $tab  = sanitize_key($_POST['tab'] ?? 'pulse');
        $year = (int) ($_POST['year'] ?? date('Y'));

        switch ($tab) {
            case 'pulse':
                wp_send_json_success([
                    'health'  => self::get_health_score(),
                    'week'    => self::get_week_revenue(),
                    'alerts'  => self::get_alerts(),
                ]);
                break;

            case 'analysis':
                wp_send_json_success([
                    'kpis'     => self::get_yearly_stats($year),
                    'monthly'  => self::get_monthly_breakdown($year),
                    'profile'  => self::get_traveler_profile(),
                ]);
                break;

            case 'forecast':
                wp_send_json_success([
                    'forecast'      => self::get_forecast(),
                    'opportunities' => self::get_opportunities(),
                ]);
                break;

            default:
                wp_send_json_error('Onglet inconnu');
        }
    }
}

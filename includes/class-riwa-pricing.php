<?php
/**
 * Logique de tarification saisonnière
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Pricing {

    /**
     * Récupérer les périodes tarifaires actives (pour le JS frontend)
     */
    public static function get_pricing_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_pricing';

        $pricing      = $wpdb->get_results("SELECT * FROM $table_name WHERE is_active = 1 ORDER BY start_date ASC");
        $pricing_data = array();

        foreach ($pricing as $price) {
            $pricing_data[] = array(
                'id'            => $price->id,
                'name'          => $price->season_name,
                'start_date'    => $price->start_date,
                'end_date'      => $price->end_date,
                'price_per_night' => floatval($price->price_per_night),
                'min_stay'      => intval($price->min_stay),
            );
        }

        return $pricing_data;
    }

    /**
     * Générer une couleur unique pour chaque saison
     */
    public static function get_season_color($season_id) {
        $colors = array(
            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
            '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3',
        );

        return $colors[$season_id % count($colors)];
    }

    /**
     * Calculer le prix total avec détail par saison
     *
     * @return array { total, per_night, season_breakdown }
     */
    public static function calculate_total($check_in_date, $check_out_date, $guests_count) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_pricing';

        $check_in  = new DateTime($check_in_date);
        $check_out = new DateTime($check_out_date);
        $nights    = $check_in->diff($check_out)->days;

        if ($nights <= 0) {
            return array('total' => 0, 'per_night' => 0, 'season_breakdown' => array());
        }

        $total_price      = 0;
        $current_date     = clone $check_in;
        $season_breakdown = array();

        for ($i = 0; $i < $nights; $i++) {
            $date_str = $current_date->format('Y-m-d');

            $price_result = $wpdb->get_row($wpdb->prepare("
                SELECT price_per_night, season_name
                FROM $table_name
                WHERE start_date <= %s
                AND end_date >= %s
                AND is_active = 1
                ORDER BY price_per_night DESC
                LIMIT 1
            ", $date_str, $date_str));

            if ($price_result) {
                $night_price = floatval($price_result->price_per_night);
                $season_name = $price_result->season_name;
            } else {
                $night_price = 150.00;
                $season_name = 'Prix par défaut';
            }

            if (!isset($season_breakdown[$season_name])) {
                $season_breakdown[$season_name] = array(
                    'nights'        => 0,
                    'price_per_night' => $night_price,
                    'total'         => 0,
                );
            }

            $season_breakdown[$season_name]['nights']++;
            $season_breakdown[$season_name]['total'] += $night_price;
            $total_price += $night_price;

            $current_date->add(new DateInterval('P1D'));
        }

        $price_per_night = $total_price / $nights;

        return array(
            'total'            => $total_price,
            'per_night'        => $price_per_night,
            'season_breakdown' => $season_breakdown,
        );
    }
}

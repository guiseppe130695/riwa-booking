<?php
/**
 * Gestion CRUD des périodes tarifaires
 * Contient l'unique définition de check_date_overlap()
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Pricing_Table {

    /**
     * Vérifier s'il y a des chevauchements de dates avec les périodes existantes
     */
    public static function check_date_overlap($start_date, $end_date, $exclude_id = null) {
        global $wpdb;
        $pricing_table = $wpdb->prefix . 'riwa_pricing';

        $query = $wpdb->prepare("
            SELECT id, season_name, start_date, end_date
            FROM $pricing_table
            WHERE (
                (start_date <= %s AND end_date >= %s) OR
                (start_date <= %s AND end_date >= %s) OR
                (start_date >= %s AND end_date <= %s)
            )
            AND is_active = 1
        ", $start_date, $start_date, $end_date, $end_date, $start_date, $end_date);

        if ($exclude_id) {
            $query .= $wpdb->prepare(' AND id != %d', $exclude_id);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Traiter les actions POST (add_pricing, delete_pricing, toggle_pricing)
     */
    public static function handle_post_actions() {
        if (!isset($_POST['action'])) {
            return;
        }

        $action        = sanitize_text_field($_POST['action']);
        $pricing_table = $GLOBALS['wpdb']->prefix . 'riwa_pricing';

        if ($action === 'add_pricing') {
            self::handle_add($pricing_table);
        }

        if ($action === 'delete_pricing' && isset($_POST['pricing_id'])) {
            self::handle_delete($pricing_table);
        }

        if ($action === 'toggle_pricing' && isset($_POST['pricing_id'])) {
            self::handle_toggle($pricing_table);
        }
    }

    private static function handle_add($pricing_table) {
        if (!wp_verify_nonce($_POST['pricing_nonce'] ?? '', 'riwa_pricing_nonce')) {
            echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
            return;
        }

        global $wpdb;
        $season_name    = sanitize_text_field($_POST['season_name'] ?? '');
        $start_date     = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date       = sanitize_text_field($_POST['end_date'] ?? '');
        $price_per_night = floatval($_POST['price_per_night'] ?? 0);
        $min_stay       = intval($_POST['min_stay'] ?? 1);

        if (empty($season_name) || empty($start_date) || empty($end_date) || $price_per_night <= 0) {
            echo '<div class="notice notice-error"><p>Veuillez remplir tous les champs obligatoires.</p></div>';
            return;
        }

        if ($end_date <= $start_date) {
            echo '<div class="notice notice-error"><p>La date de fin doit être après la date de début.</p></div>';
            return;
        }

        $overlaps = self::check_date_overlap($start_date, $end_date);
        if (!empty($overlaps)) {
            $msg = '<p>Cette période chevauche avec les périodes suivantes :</p><ul>';
            foreach ($overlaps as $overlap) {
                $s    = (new DateTime($overlap->start_date))->format('d/m/Y');
                $e    = (new DateTime($overlap->end_date))->format('d/m/Y');
                $msg .= '<li><strong>' . esc_html($overlap->season_name) . '</strong> : ' . $s . ' - ' . $e . '</li>';
            }
            $msg .= '</ul><p>Veuillez ajuster les dates pour éviter les conflits.</p>';
            echo '<div class="notice notice-error">' . $msg . '</div>';
            return;
        }

        $result = $wpdb->insert(
            $pricing_table,
            array(
                'season_name'    => $season_name,
                'start_date'     => $start_date,
                'end_date'       => $end_date,
                'price_per_night' => $price_per_night,
                'min_stay'       => $min_stay,
                'is_active'      => 1,
            ),
            array('%s', '%s', '%s', '%f', '%d', '%d')
        );

        if ($result) {
            echo '<div class="notice notice-success"><p>Période tarifaire ajoutée avec succès !</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout de la période tarifaire.</p></div>';
        }
    }

    private static function handle_delete($pricing_table) {
        global $wpdb;
        $pricing_id = intval($_POST['pricing_id']);
        $wpdb->delete($pricing_table, array('id' => $pricing_id), array('%d'));
        echo '<div class="notice notice-success"><p>Période tarifaire supprimée avec succès !</p></div>';
    }

    private static function handle_toggle($pricing_table) {
        global $wpdb;
        $pricing_id     = intval($_POST['pricing_id']);
        $current_status = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $pricing_table WHERE id = %d", $pricing_id));
        $new_status     = $current_status ? 0 : 1;

        $wpdb->update(
            $pricing_table,
            array('is_active' => $new_status),
            array('id' => $pricing_id),
            array('%d'),
            array('%d')
        );

        $status_text = $new_status ? 'activée' : 'désactivée';
        echo '<div class="notice notice-success"><p>Période tarifaire ' . $status_text . ' avec succès !</p></div>';
    }

    /**
     * Récupérer toutes les périodes tarifaires triées par date
     */
    public static function get_all_seasons() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}riwa_pricing ORDER BY start_date ASC"
        );
    }
}

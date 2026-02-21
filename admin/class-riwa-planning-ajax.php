<?php
/**
 * Riwa_Planning_Ajax — Handlers AJAX du planning
 *
 * Tous les endpoints AJAX liés au calendrier (données, blocages,
 * overrides de prix, ménage). Séparé de Riwa_Planning pour la clarté.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Planning_Ajax {

    /* ------------------------------------------------------------------ */
    /*  Données calendrier                                                  */
    /* ------------------------------------------------------------------ */

    /** AJAX : données du calendrier pour une plage */
    public static function ajax_get_planning_data() {
        Riwa_Security::check_admin('riwa_planning_nonce');

        $date_start = sanitize_text_field($_POST['date_start'] ?? date('Y-m-01'));
        $date_end   = sanitize_text_field($_POST['date_end']   ?? date('Y-m-t'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_start) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_end)) {
            wp_send_json_error('Format de date invalide');
        }

        // Mode démo : si aucune réservation en DB
        global $wpdb;
        $has_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}riwa_bookings");
        if ($has_bookings === 0) {
            wp_send_json_success(Riwa_Planning_Demo::get_demo_data($date_start, $date_end));
            return;
        }

        wp_send_json_success([
            'bookings'  => Riwa_Planning::get_bookings_for_range($date_start, $date_end),
            'blocked'   => Riwa_Planning::get_blocked_for_range($date_start, $date_end),
            'overrides' => Riwa_Planning::get_overrides_for_range($date_start, $date_end),
            'stats'     => Riwa_Planning::get_occupation_stats($date_start, $date_end),
            'today'     => Riwa_Planning::get_today_movements(),
            'demo'      => false,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Blocages                                                            */
    /* ------------------------------------------------------------------ */

    /** AJAX : ajouter un blocage */
    public static function ajax_add_blocked() {
        Riwa_Security::check_admin('riwa_planning_nonce');

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
        Riwa_Security::check_admin('riwa_planning_nonce');

        $id = intval($_POST['id'] ?? 0);
        if (!$id) wp_send_json_error('ID invalide');

        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'riwa_blocked_dates', ['id' => $id], ['%d']);
        wp_send_json_success();
    }

    /* ------------------------------------------------------------------ */
    /*  Overrides de prix                                                   */
    /* ------------------------------------------------------------------ */

    /** AJAX : sauvegarder un override de prix */
    public static function ajax_save_price_override() {
        Riwa_Security::check_admin('riwa_planning_nonce');

        $date  = sanitize_text_field($_POST['date']  ?? '');
        $price = (float) ($_POST['price'] ?? 0);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $price < 0) {
            wp_send_json_error('Données invalides');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'riwa_date_overrides';

        if ($price === 0.0) {
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

    /* ------------------------------------------------------------------ */
    /*  Ménage (housekeeping)                                               */
    /* ------------------------------------------------------------------ */

    /** AJAX : mettre à jour le statut ménage d'une réservation */
    public static function ajax_update_housekeeping() {
        Riwa_Security::check_admin('riwa_planning_nonce');

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
}

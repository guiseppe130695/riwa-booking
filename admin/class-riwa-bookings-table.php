<?php
/**
 * Gestion CRUD des réservations (mise à jour statut, suppression)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Bookings_Table {

    /**
     * Traiter les actions POST (update_status, delete_booking) et retourner les messages notice
     */
    public static function handle_post_actions() {
        if (!isset($_POST['action'])) {
            return;
        }

        $action = sanitize_text_field($_POST['action']);

        if ($action === 'update_status' && isset($_POST['booking_id'])) {
            // Fix sécurité : vérification du nonce (manquant dans l'original)
            if (!isset($_POST['riwa_admin_nonce']) || !wp_verify_nonce($_POST['riwa_admin_nonce'], 'riwa_admin_action')) {
                echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
                return;
            }

            if (!current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Permissions insuffisantes.</p></div>';
                return;
            }

            $booking_id = intval($_POST['booking_id']);
            $new_status = sanitize_text_field($_POST['new_status'] ?? '');

            if (in_array($new_status, array('pending', 'confirmed', 'cancelled'), true)) {
                global $wpdb;
                $wpdb->update(
                    $wpdb->prefix . 'riwa_bookings',
                    array('status' => $new_status),
                    array('id'     => $booking_id),
                    array('%s'),
                    array('%d')
                );
                echo '<div class="notice notice-success"><p>Statut mis à jour avec succès !</p></div>';
            }
        }

        if ($action === 'delete_booking' && isset($_POST['booking_id'])) {
            if (!wp_verify_nonce($_POST['delete_nonce'] ?? '', 'delete_booking_nonce')) {
                echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
                return;
            }

            if (!current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>Permissions insuffisantes.</p></div>';
                return;
            }

            global $wpdb;
            $booking_id = intval($_POST['booking_id']);
            $result     = $wpdb->delete(
                $wpdb->prefix . 'riwa_bookings',
                array('id' => $booking_id),
                array('%d')
            );

            if ($result) {
                echo '<div class="notice notice-success"><p>Réservation supprimée avec succès !</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors de la suppression de la réservation.</p></div>';
            }
        }
    }

    /**
     * Récupérer toutes les réservations triées par date de création
     */
    public static function get_all_bookings() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}riwa_bookings ORDER BY created_at DESC"
        );
    }

    /**
     * Calculer le nombre total de voyageurs d'une réservation
     */
    public static function get_total_guests($booking) {
        return ($booking->adults_count ?? 0)
             + ($booking->children_count ?? 0)
             + ($booking->babies_count ?? 0);
    }

    /**
     * Récupérer les réservations avec filtres et pagination
     *
     * @param array $args ['status', 'month', 'year', 'search', 'page', 'per_page']
     * @return array ['bookings' => array, 'total' => int]
     */
    public static function get_filtered_bookings($args = []) {
        global $wpdb;
        $table    = $wpdb->prefix . 'riwa_bookings';
        $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
        $page     = isset($args['page']) ? max(1, (int) $args['page']) : 1;
        $offset   = ($page - 1) * $per_page;

        $where  = ['1=1'];
        $params = [];

        if (!empty($args['status']) && in_array($args['status'], ['pending', 'confirmed', 'cancelled'], true)) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['month'])) {
            $month = (int) $args['month'];
            $year  = isset($args['year']) ? (int) $args['year'] : (int) date('Y');
            $where[]  = 'YEAR(created_at) = %d AND MONTH(created_at) = %d';
            $params[] = $year;
            $params[] = $month;
        }

        if (!empty($args['search'])) {
            $like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = '(guest_name LIKE %s OR guest_email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total     = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));

        $query_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$per_page, $offset]);
        $bookings  = $wpdb->get_results($wpdb->prepare($query_sql, $all_params));

        return ['bookings' => $bookings, 'total' => $total];
    }

    /**
     * Retourner le libellé et la classe CSS d'un statut
     */
    public static function get_status_badge($status) {
        $statuses = array(
            'pending'   => array('label' => 'En attente', 'class' => 'status-pending'),
            'confirmed' => array('label' => 'Confirmée',  'class' => 'status-confirmed'),
            'cancelled' => array('label' => 'Annulée',    'class' => 'status-cancelled'),
        );

        return $statuses[$status] ?? array('label' => ucfirst($status), 'class' => 'status-unknown');
    }
}

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

            if (Riwa_Enums::is_valid_booking_status($new_status)) {
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
     * @param array $args {
     *   status        string  pending|confirmed|cancelled
     *   period        string  today|this_week|this_month|arriving_today|arriving_week|departing_today|staying|upcoming|past
     *   date_from     string  Y-m-d
     *   date_to       string  Y-m-d
     *   duration      string  1|2-3|4+
     *   guests        string  1-2|3-5|6+
     *   price_min     float
     *   price_max     float
     *   orderby       string  created_at|check_in_date|total_price (default: created_at)
     *   order         string  ASC|DESC (default: DESC)
     *   search        string
     *   page          int
     *   per_page      int
     * }
     * @return array ['bookings' => array, 'total' => int]
     */
    public static function get_filtered_bookings($args = []) {
        global $wpdb;
        $table    = $wpdb->prefix . 'riwa_bookings';
        $per_page = isset($args['per_page']) ? max(1, (int) $args['per_page']) : 20;
        $page     = isset($args['page']) ? max(1, (int) $args['page']) : 1;
        $offset   = ($page - 1) * $per_page;
        $today    = date('Y-m-d');

        $where  = ['1=1'];
        $params = [];

        // ── Statut ─────────────────────────────────────────────
        if (!empty($args['status']) && in_array($args['status'], ['pending', 'confirmed', 'cancelled'], true)) {
            $where[]  = 'status = %s';
            $params[] = $args['status'];
        }

        // ── Période intelligente ────────────────────────────────
        if (!empty($args['period'])) {
            switch ($args['period']) {
                case 'today':
                    $where[]  = 'DATE(created_at) = %s';
                    $params[] = $today;
                    break;
                case 'this_week':
                    $where[]  = 'YEARWEEK(created_at, 1) = YEARWEEK(%s, 1)';
                    $params[] = $today;
                    break;
                case 'this_month':
                    $where[]  = 'YEAR(created_at) = %d AND MONTH(created_at) = %d';
                    $params[] = (int) date('Y');
                    $params[] = (int) date('n');
                    break;
                case 'arriving_today':
                    $where[]  = 'check_in_date = %s';
                    $params[] = $today;
                    break;
                case 'arriving_week':
                    $week_end = date('Y-m-d', strtotime('+6 days'));
                    $where[]  = 'check_in_date BETWEEN %s AND %s';
                    $params[] = $today;
                    $params[] = $week_end;
                    break;
                case 'departing_today':
                    $where[]  = 'check_out_date = %s';
                    $params[] = $today;
                    break;
                case 'staying':
                    $where[]  = 'check_in_date <= %s AND check_out_date > %s';
                    $params[] = $today;
                    $params[] = $today;
                    break;
                case 'upcoming':
                    $where[]  = 'check_in_date > %s';
                    $params[] = $today;
                    break;
                case 'past':
                    $where[]  = 'check_out_date < %s';
                    $params[] = $today;
                    break;
            }
        }

        // ── Période personnalisée (date début / fin sur check_in) ─
        if (!empty($args['date_from'])) {
            $where[]  = 'check_in_date >= %s';
            $params[] = sanitize_text_field($args['date_from']);
        }
        if (!empty($args['date_to'])) {
            $where[]  = 'check_in_date <= %s';
            $params[] = sanitize_text_field($args['date_to']);
        }

        // ── Durée du séjour ─────────────────────────────────────
        if (!empty($args['duration'])) {
            switch ($args['duration']) {
                case '1':
                    $where[] = 'DATEDIFF(check_out_date, check_in_date) = 1';
                    break;
                case '2-3':
                    $where[] = 'DATEDIFF(check_out_date, check_in_date) BETWEEN 2 AND 3';
                    break;
                case '4+':
                    $where[] = 'DATEDIFF(check_out_date, check_in_date) >= 4';
                    break;
            }
        }

        // ── Nombre de voyageurs ─────────────────────────────────
        if (!empty($args['guests'])) {
            switch ($args['guests']) {
                case '1-2':
                    $where[] = '(COALESCE(adults_count,0) + COALESCE(children_count,0) + COALESCE(babies_count,0)) BETWEEN 1 AND 2';
                    break;
                case '3-5':
                    $where[] = '(COALESCE(adults_count,0) + COALESCE(children_count,0) + COALESCE(babies_count,0)) BETWEEN 3 AND 5';
                    break;
                case '6+':
                    $where[] = '(COALESCE(adults_count,0) + COALESCE(children_count,0) + COALESCE(babies_count,0)) >= 6';
                    break;
            }
        }

        // ── Montant min / max ───────────────────────────────────
        if (isset($args['price_min']) && $args['price_min'] !== '') {
            $where[]  = 'total_price >= %f';
            $params[] = (float) $args['price_min'];
        }
        if (isset($args['price_max']) && $args['price_max'] !== '') {
            $where[]  = 'total_price <= %f';
            $params[] = (float) $args['price_max'];
        }

        // ── Recherche texte ─────────────────────────────────────
        if (!empty($args['search'])) {
            $like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = '(guest_name LIKE %s OR guest_email LIKE %s OR guest_phone LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql = implode(' AND ', $where);

        // ── Tri ─────────────────────────────────────────────────
        $allowed_orderby = ['created_at', 'check_in_date', 'total_price'];
        $orderby = in_array($args['orderby'] ?? '', $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order   = strtoupper($args['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        $count_sql  = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total      = (int) ($params ? $wpdb->get_var($wpdb->prepare($count_sql, $params)) : $wpdb->get_var($count_sql));

        $query_sql  = "SELECT b.*, COALESCE(p.amount_paid, 0) as amount_paid
                       FROM {$table} b
                       LEFT JOIN (SELECT booking_id, SUM(amount) as amount_paid FROM {$wpdb->prefix}riwa_payments GROUP BY booking_id) p ON p.booking_id = b.id
                       WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $all_params = array_merge($params, [$per_page, $offset]);
        $bookings   = $wpdb->get_results($wpdb->prepare($query_sql, $all_params));

        return ['bookings' => $bookings, 'total' => $total];
    }

    /**
     * Compteurs Smart View — stats rapides pour les badges d'accueil
     */
    public static function get_smart_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'riwa_bookings';
        $today = date('Y-m-d');
        $week_end = date('Y-m-d', strtotime('+6 days'));

        return [
            'arriving_today'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE check_in_date = %s AND status != 'cancelled'", $today)),
            'departing_today' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE check_out_date = %s AND status != 'cancelled'", $today)),
            'staying'         => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE check_in_date <= %s AND check_out_date > %s AND status != 'cancelled'", $today, $today)),
            'pending'         => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
            'arriving_week'   => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE check_in_date BETWEEN %s AND %s AND status != 'cancelled'", $today, $week_end)),
        ];
    }

    /**
     * AJAX : charger une page de réservations (infinite scroll)
     */
    public static function ajax_load_more() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accès non autorisé', 403);
        }
        check_ajax_referer('riwa_admin_action', 'nonce');

        $page    = max(1, (int) ($_POST['page']    ?? 1));
        $per_page = 20;

        $result   = self::get_filtered_bookings([
            'status'     => sanitize_text_field($_POST['filter_status']   ?? ''),
            'period'     => sanitize_text_field($_POST['filter_period']   ?? ''),
            'date_from'  => sanitize_text_field($_POST['filter_date_from'] ?? ''),
            'date_to'    => sanitize_text_field($_POST['filter_date_to']   ?? ''),
            'duration'   => sanitize_text_field($_POST['filter_duration'] ?? ''),
            'guests'     => sanitize_text_field($_POST['filter_guests']   ?? ''),
            'price_min'  => $_POST['filter_price_min'] ?? '',
            'price_max'  => $_POST['filter_price_max'] ?? '',
            'orderby'    => sanitize_text_field($_POST['filter_orderby']  ?? 'created_at'),
            'order'      => 'DESC',
            'search'     => sanitize_text_field($_POST['filter_search']   ?? ''),
            'page'       => $page,
            'per_page'   => $per_page,
        ]);

        $bookings    = $result['bookings'];
        $total       = $result['total'];
        $total_pages = (int) ceil($total / $per_page);
        $has_more    = $page < $total_pages;

        ob_start();
        foreach ($bookings as $booking) {
            $nights  = (int) (new DateTime($booking->check_in_date))->diff(new DateTime($booking->check_out_date))->days;
            $total_g = self::get_total_guests($booking);
            $badge   = self::get_status_badge($booking->status);
            ?>
            <tr class="riwa-booking-row"
                data-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                data-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                data-nights="<?php echo esc_attr($nights); ?>"
                data-guests="<?php echo esc_attr($total_g); ?>"
                data-status="<?php echo esc_attr($booking->status); ?>"
                data-price="<?php echo esc_attr($booking->total_price ?? 0); ?>">
                <td><span class="riwa-booking-reference">RIWA-<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></span></td>
                <td>
                    <div class="riwa-client-name"><?php echo esc_html($booking->guest_name); ?></div>
                    <span class="riwa-status-badge riwa-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($badge['label']); ?></span>
                </td>
                <td>
                    <div><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in_date))); ?> → <?php echo esc_html(date('d/m/Y', strtotime($booking->check_out_date))); ?></div>
                    <div class="riwa-nights-badge"><?php echo $nights; ?> nuit<?php echo $nights > 1 ? 's' : ''; ?></div>
                </td>
                <td>
                    <?php if ($total_g > 0): ?>
                        <span class="riwa-guests-compact"><span class="dashicons dashicons-groups"></span> <?php echo $total_g; ?></span>
                        <div class="riwa-guests-detail">
                            <?php if ($booking->adults_count):   echo esc_html($booking->adults_count) . ' adulte' . ($booking->adults_count > 1 ? 's' : ''); endif; ?>
                            <?php if ($booking->children_count): echo ' · ' . esc_html($booking->children_count) . ' enfant' . ($booking->children_count > 1 ? 's' : ''); endif; ?>
                            <?php if ($booking->babies_count):   echo ' · ' . esc_html($booking->babies_count) . ' bébé' . ($booking->babies_count > 1 ? 's' : ''); endif; ?>
                        </div>
                    <?php else: ?>
                        <span style="color:var(--riwa-gray-400);">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($booking->total_price > 0): ?>
                        <span class="riwa-price-total"><?php echo number_format($booking->total_price, 0, ',', ' '); ?> €</span>
                        <?php if ($booking->price_per_night > 0): ?>
                            <div style="font-size:11px;color:var(--riwa-gray-500);"><?php echo number_format($booking->price_per_night, 0, ',', ' '); ?> €/nuit</div>
                        <?php endif; ?>
                        <?php
                        $amount_paid    = floatval($booking->amount_paid ?? 0);
                        $pay_status     = Riwa_Payments::get_payment_status($booking);
                        $pay_label      = Riwa_Payments::get_status_label($pay_status);
                        $pay_color      = Riwa_Payments::get_status_color($pay_status);
                        ?>
                        <div style="margin-top:3px;">
                            <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;background:<?php echo esc_attr($pay_color); ?>20;color:<?php echo esc_attr($pay_color); ?>;border:1px solid <?php echo esc_attr($pay_color); ?>40;">
                                <?php echo esc_html($pay_label); ?>
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="riwa-price-unknown"><span class="dashicons dashicons-minus"></span></span>
                    <?php endif; ?>
                </td>
                <td>
                    <button type="button" class="riwa-btn riwa-btn-secondary button-small view-details-popup"
                            data-booking-id="<?php echo esc_attr($booking->id); ?>"
                            data-booking-name="<?php echo esc_attr($booking->guest_name); ?>"
                            data-booking-email="<?php echo esc_attr($booking->guest_email); ?>"
                            data-booking-phone="<?php echo esc_attr($booking->guest_phone); ?>"
                            data-booking-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                            data-booking-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                            data-booking-guests="<?php echo esc_attr(self::get_total_guests($booking)); ?>"
                            data-booking-adults="<?php echo esc_attr($booking->adults_count ?? 0); ?>"
                            data-booking-children="<?php echo esc_attr($booking->children_count ?? 0); ?>"
                            data-booking-babies="<?php echo esc_attr($booking->babies_count ?? 0); ?>"
                            data-booking-price="<?php echo esc_attr($booking->total_price); ?>"
                            data-booking-price-per-night="<?php echo esc_attr($booking->price_per_night); ?>"
                            data-booking-status="<?php echo esc_attr($booking->status); ?>"
                            data-booking-created="<?php echo esc_attr($booking->created_at); ?>"
                            data-booking-requests="<?php echo esc_attr($booking->special_requests); ?>"
                            title="Voir les détails">
                        <span class="dashicons dashicons-visibility"></span>
                    </button>
                </td>
            </tr>
            <?php
        }
        $html = ob_get_clean();

        wp_send_json_success([
            'html'     => $html,
            'has_more' => $has_more,
            'page'     => $page,
            'total'    => $total,
        ]);
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

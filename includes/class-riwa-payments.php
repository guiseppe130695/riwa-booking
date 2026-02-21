<?php
/**
 * Riwa Payments — Gestion des paiements et acomptes
 * Logique métier + handlers AJAX
 */

if (!defined('ABSPATH')) exit;

class Riwa_Payments {

    /* ---------------------------------------------------------------- */
    /*  Méthodes de paiement disponibles                                 */
    /* ---------------------------------------------------------------- */

    public static function get_methods() {
        return Riwa_Enums::PAYMENT_METHOD_LABELS;
    }

    /* ---------------------------------------------------------------- */
    /*  Statuts de paiement                                              */
    /* ---------------------------------------------------------------- */

    public static function get_payment_status($booking) {
        $total   = floatval($booking->total_price ?? 0);
        $paid    = floatval($booking->amount_paid ?? 0);
        $deposit = floatval($booking->deposit_amount ?? 0);

        if ($total <= 0) return Riwa_Enums::PAY_UNPAID;
        if ($paid >= $total) return Riwa_Enums::PAY_PAID;
        if ($paid > 0 && $paid >= $deposit && $deposit > 0) return Riwa_Enums::PAY_DEPOSIT_PAID;
        if ($paid > 0) return Riwa_Enums::PAY_PARTIAL;

        if (!empty($booking->balance_due_date) && strtotime($booking->balance_due_date) < time()) {
            return Riwa_Enums::PAY_OVERDUE;
        }

        return Riwa_Enums::PAY_UNPAID;
    }

    public static function get_status_label($status) {
        return Riwa_Enums::pay_status_label($status);
    }

    public static function get_status_color($status) {
        return Riwa_Enums::pay_status_color($status);
    }

    /* ---------------------------------------------------------------- */
    /*  Création de la table riwa_payments                               */
    /* ---------------------------------------------------------------- */

    public static function create_table() {
        global $wpdb;
        $table           = $wpdb->prefix . 'riwa_payments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            booking_id mediumint(9) NOT NULL,
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            method varchar(20) NOT NULL DEFAULT 'cash',
            payment_date date NOT NULL,
            reference varchar(100) DEFAULT '',
            note text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY payment_date (payment_date)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /* ---------------------------------------------------------------- */
    /*  Paiements d'une réservation                                      */
    /* ---------------------------------------------------------------- */

    public static function get_payments_for_booking($booking_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_payments WHERE booking_id = %d ORDER BY payment_date ASC",
            intval($booking_id)
        ));
    }

    public static function get_total_paid($booking_id) {
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}riwa_payments WHERE booking_id = %d",
            intval($booking_id)
        ));
        return floatval($result);
    }

    /* ---------------------------------------------------------------- */
    /*  KPIs Dashboard                                                   */
    /* ---------------------------------------------------------------- */

    public static function get_dashboard_kpis() {
        global $wpdb;
        $p = $wpdb->prefix;
        $now   = current_time('Y-m-d');
        $month = current_time('Y-m');

        // Encaissé ce mois
        $encaisse_mois = floatval($wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$p}riwa_payments
             WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month'"
        ));

        // Total encaissé (tous temps)
        $encaisse_total = floatval($wpdb->get_var(
            "SELECT COALESCE(SUM(amount), 0) FROM {$p}riwa_payments"
        ));

        // En attente = total_price - amount_paid sur réservations confirmées non annulées
        $bookings_actives = $wpdb->get_results(
            "SELECT b.id, b.total_price, b.deposit_amount, b.balance_due_date,
                    COALESCE(SUM(p.amount), 0) as amount_paid
             FROM {$p}riwa_bookings b
             LEFT JOIN {$p}riwa_payments p ON p.booking_id = b.id
             WHERE b.status IN ('pending', 'confirmed')
             GROUP BY b.id"
        );

        $en_attente   = 0;
        $en_retard    = 0;
        $acomptes     = 0;
        $retard_count = 0;

        foreach ($bookings_actives as $b) {
            $paid    = floatval($b->amount_paid);
            $total   = floatval($b->total_price);
            $deposit = floatval($b->deposit_amount ?? 0);
            $solde   = max(0, $total - $paid);

            $en_attente += $solde;

            if ($paid > 0 && $paid < $total && $deposit > 0 && $paid >= $deposit) {
                $acomptes++;
            }

            if ($solde > 0 && !empty($b->balance_due_date) && strtotime($b->balance_due_date) < strtotime($now)) {
                $en_retard += $solde;
                $retard_count++;
            }
        }

        // Prévision 30 prochains jours = total_price des réservations confirmées avec check_in dans 30j
        $prevision = floatval($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(b.total_price - COALESCE(p.paid, 0)), 0)
             FROM {$p}riwa_bookings b
             LEFT JOIN (
                 SELECT booking_id, SUM(amount) as paid FROM {$p}riwa_payments GROUP BY booking_id
             ) p ON p.booking_id = b.id
             WHERE b.status = 'confirmed'
               AND b.check_in_date BETWEEN %s AND DATE_ADD(%s, INTERVAL 30 DAY)",
            $now, $now
        )));

        return [
            'encaisse_mois'  => $encaisse_mois,
            'encaisse_total' => $encaisse_total,
            'en_attente'     => $en_attente,
            'en_retard'      => $en_retard,
            'retard_count'   => $retard_count,
            'acomptes_count' => $acomptes,
            'prevision_30j'  => max(0, $prevision),
        ];
    }

    /* ---------------------------------------------------------------- */
    /*  Liste réservations avec statut paiement                          */
    /* ---------------------------------------------------------------- */

    public static function get_bookings_with_payment($filter = 'all', $page = 1, $per_page = 20) {
        global $wpdb;
        $p = $wpdb->prefix;

        $where = "WHERE b.status != 'cancelled'";
        if ($filter === 'overdue') {
            $where .= " AND b.balance_due_date < CURDATE() AND b.total_price > COALESCE(paid_sub.paid, 0)";
        } elseif ($filter === 'unpaid') {
            $where .= " AND COALESCE(paid_sub.paid, 0) = 0";
        } elseif ($filter === 'partial') {
            $where .= " AND COALESCE(paid_sub.paid, 0) > 0 AND COALESCE(paid_sub.paid, 0) < b.total_price";
        } elseif ($filter === 'paid') {
            $where .= " AND COALESCE(paid_sub.paid, 0) >= b.total_price AND b.total_price > 0";
        }

        $offset = ($page - 1) * $per_page;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT b.id, b.guest_name, b.guest_email, b.guest_phone,
                    b.check_in_date, b.check_out_date, b.total_price,
                    b.deposit_amount, b.deposit_percent, b.balance_due_date,
                    b.status, b.created_at,
                    COALESCE(paid_sub.paid, 0) as amount_paid
             FROM {$p}riwa_bookings b
             LEFT JOIN (
                 SELECT booking_id, SUM(amount) as paid FROM {$p}riwa_payments GROUP BY booking_id
             ) paid_sub ON paid_sub.booking_id = b.id
             $where
             ORDER BY b.check_in_date DESC
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        $total = $wpdb->get_var(
            "SELECT COUNT(DISTINCT b.id)
             FROM {$p}riwa_bookings b
             LEFT JOIN (
                 SELECT booking_id, SUM(amount) as paid FROM {$p}riwa_payments GROUP BY booking_id
             ) paid_sub ON paid_sub.booking_id = b.id
             $where"
        );

        return ['bookings' => $rows, 'total' => intval($total)];
    }

    /* ---------------------------------------------------------------- */
    /*  Export CSV                                                        */
    /* ---------------------------------------------------------------- */

    public static function export_csv($month = '') {
        if (!current_user_can('manage_options')) {
            wp_die('Accès non autorisé');
        }

        global $wpdb;
        $p = $wpdb->prefix;

        // Valider le format YYYY-MM
        if ($month && !preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = '';
        }

        $where = '';
        if ($month) {
            $where = $wpdb->prepare(" AND DATE_FORMAT(py.payment_date, '%%Y-%%m') = %s", $month);
        }

        $rows = $wpdb->get_results(
            "SELECT b.guest_name, b.guest_email, b.check_in_date, b.check_out_date,
                    b.total_price, py.amount, py.method, py.payment_date, py.reference, py.note
             FROM {$p}riwa_payments py
             JOIN {$p}riwa_bookings b ON b.id = py.booking_id
             WHERE 1=1 $where
             ORDER BY py.payment_date DESC",
            ARRAY_A
        );

        $methods = self::get_methods();

        header('Content-Type: text/csv; charset=UTF-8');
        $filename = 'paiements-' . ($month ? preg_replace('/[^0-9-]/', '', $month) : date('Y')) . '.csv';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8 pour Excel

        fputcsv($out, ['Client', 'Email', 'Arrivée', 'Départ', 'Total séjour', 'Montant payé', 'Mode', 'Date paiement', 'Référence', 'Note'], ';');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['guest_name'],
                $row['guest_email'],
                $row['check_in_date'],
                $row['check_out_date'],
                number_format($row['total_price'], 2, ',', ' '),
                number_format($row['amount'], 2, ',', ' '),
                $methods[$row['method']] ?? $row['method'],
                $row['payment_date'],
                $row['reference'],
                $row['note'],
            ], ';');
        }

        fclose($out);
        exit;
    }

    /* ================================================================ */
    /*  Handlers AJAX                                                    */
    /* ================================================================ */

    /* Ajouter un paiement */
    public static function ajax_add_payment() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        $booking_id   = intval($_POST['booking_id'] ?? 0);
        $amount       = floatval($_POST['amount'] ?? 0);
        $method       = sanitize_key($_POST['method'] ?? 'cash');
        $payment_date = sanitize_text_field($_POST['payment_date'] ?? current_time('Y-m-d'));
        $reference    = sanitize_text_field($_POST['reference'] ?? '');
        $note         = sanitize_textarea_field($_POST['note'] ?? '');

        if (!$booking_id || $amount <= 0) {
            wp_send_json_error('Données invalides');
        }

        if (!array_key_exists($method, self::get_methods())) {
            $method = 'other';
        }

        global $wpdb;
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'riwa_payments',
            [
                'booking_id'   => $booking_id,
                'amount'       => $amount,
                'method'       => $method,
                'payment_date' => $payment_date,
                'reference'    => $reference,
                'note'         => $note,
            ],
            ['%d', '%f', '%s', '%s', '%s', '%s']
        );

        if (!$inserted) {
            wp_send_json_error('Erreur lors de l\'enregistrement');
        }

        $total_paid = self::get_total_paid($booking_id);
        $booking    = $wpdb->get_row($wpdb->prepare(
            "SELECT total_price, deposit_amount, balance_due_date FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        $booking->amount_paid = $total_paid;
        $status = self::get_payment_status($booking);

        wp_send_json_success([
            'payment_id'   => $wpdb->insert_id,
            'total_paid'   => $total_paid,
            'status'       => $status,
            'status_label' => self::get_status_label($status),
            'status_color' => self::get_status_color($status),
        ]);
    }

    /* Supprimer un paiement */
    public static function ajax_delete_payment() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) wp_send_json_error('ID manquant');

        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT booking_id FROM {$wpdb->prefix}riwa_payments WHERE id = %d", $payment_id
        ));
        if (!$payment) wp_send_json_error('Paiement introuvable');

        $wpdb->delete($wpdb->prefix . 'riwa_payments', ['id' => $payment_id], ['%d']);

        $total_paid = self::get_total_paid($payment->booking_id);
        wp_send_json_success(['total_paid' => $total_paid]);
    }

    /* Sauvegarder les infos acompte d'une réservation */
    public static function ajax_save_deposit_info() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        $booking_id      = intval($_POST['booking_id'] ?? 0);
        $deposit_percent = min(100, max(0, floatval($_POST['deposit_percent'] ?? 0)));
        $balance_due_raw = sanitize_text_field($_POST['balance_due_date'] ?? '');

        // Valider le format de date Y-m-d
        $balance_due = '';
        if ($balance_due_raw) {
            $d = DateTime::createFromFormat('Y-m-d', $balance_due_raw);
            if ($d && $d->format('Y-m-d') === $balance_due_raw) {
                $balance_due = $balance_due_raw;
            }
        }

        if (!$booking_id) wp_send_json_error('ID manquant');

        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT total_price FROM {$wpdb->prefix}riwa_bookings WHERE id = %d", $booking_id
        ));

        $deposit_amount = ($deposit_percent > 0)
            ? round(floatval($booking->total_price) * $deposit_percent / 100, 2)
            : 0;

        $wpdb->update(
            $wpdb->prefix . 'riwa_bookings',
            [
                'deposit_percent'  => $deposit_percent,
                'deposit_amount'   => $deposit_amount,
                'balance_due_date' => $balance_due ?: null,
            ],
            ['id' => $booking_id],
            ['%f', '%f', '%s'],
            ['%d']
        );

        wp_send_json_success([
            'deposit_amount' => $deposit_amount,
            'deposit_percent' => $deposit_percent,
        ]);
    }

    /* Dashboard KPIs */
    public static function ajax_get_dashboard() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        wp_send_json_success(self::get_dashboard_kpis());
    }

    /* Liste paiements d'une réservation */
    public static function ajax_get_booking_payments() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) wp_send_json_error('ID manquant');

        $payments   = self::get_payments_for_booking($booking_id);
        $total_paid = self::get_total_paid($booking_id);
        $methods    = self::get_methods();

        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT total_price, deposit_amount, deposit_percent, balance_due_date FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        $booking->amount_paid = $total_paid;

        $status = self::get_payment_status($booking);
        $result = [];
        foreach ($payments as $p) {
            $result[] = [
                'id'           => $p->id,
                'amount'       => floatval($p->amount),
                'method'       => $p->method,
                'method_label' => $methods[$p->method] ?? $p->method,
                'payment_date' => $p->payment_date,
                'reference'    => $p->reference,
                'note'         => $p->note,
            ];
        }

        wp_send_json_success([
            'payments'        => $result,
            'total_paid'      => $total_paid,
            'total_price'     => floatval($booking->total_price),
            'deposit_amount'  => floatval($booking->deposit_amount ?? 0),
            'deposit_percent' => floatval($booking->deposit_percent ?? 0),
            'balance_due_date'=> $booking->balance_due_date ?? '',
            'status'          => $status,
            'status_label'    => self::get_status_label($status),
            'status_color'    => self::get_status_color($status),
        ]);
    }

    /* Export CSV */
    public static function ajax_export_csv() {
        Riwa_Security::check_admin_get_download('riwa_payments_nonce');

        $month = sanitize_text_field($_GET['month'] ?? '');
        self::export_csv($month);
    }

    /* Liste des réservations avec filtres paiement */
    public static function ajax_get_bookings_list() {
        Riwa_Security::check_admin('riwa_payments_nonce');

        $filter   = sanitize_key($_POST['filter'] ?? 'all');
        $page     = intval($_POST['page'] ?? 1);
        $per_page = 20;

        $data     = self::get_bookings_with_payment($filter, $page, $per_page);
        $methods  = self::get_methods();
        $result   = [];

        foreach ($data['bookings'] as $b) {
            $b->amount_paid = floatval($b->amount_paid);
            $status = self::get_payment_status($b);
            $solde  = max(0, floatval($b->total_price) - $b->amount_paid);

            $result[] = [
                'id'             => $b->id,
                'guest_name'     => $b->guest_name,
                'guest_email'    => $b->guest_email,
                'check_in_date'  => $b->check_in_date,
                'check_out_date' => $b->check_out_date,
                'total_price'    => floatval($b->total_price),
                'amount_paid'    => $b->amount_paid,
                'solde'          => $solde,
                'deposit_amount' => floatval($b->deposit_amount ?? 0),
                'balance_due_date' => $b->balance_due_date ?? '',
                'status'         => $status,
                'status_label'   => self::get_status_label($status),
                'status_color'   => self::get_status_color($status),
                'booking_status' => $b->status,
            ];
        }

        wp_send_json_success([
            'bookings'   => $result,
            'total'      => $data['total'],
            'pages'      => ceil($data['total'] / $per_page),
            'page'       => $page,
        ]);
    }
}

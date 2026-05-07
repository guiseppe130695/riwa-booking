<?php
/**
 * REST Controller — Paiements
 * Routes : /wp-json/riwa/v1/payments
 *          /wp-json/riwa/v1/bookings/{id}/payments
 *          /wp-json/riwa/v1/bookings/{id}/deposit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Payments_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'payments';

    public function register_routes() {
        $admin = [Riwa_REST_API::class, 'permission_admin'];

        // GET /payments/dashboard — KPIs financiers
        register_rest_route($this->namespace, '/' . $this->rest_base . '/dashboard', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn($r) => new WP_REST_Response(Riwa_Payments::get_dashboard_kpis(), 200),
            'permission_callback' => $admin,
        ]);

        // POST /payments — ajouter un paiement
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'create_payment'],
            'permission_callback' => $admin,
            'args'                => $this->get_create_params(),
        ]);

        // DELETE /payments/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_payment'],
            'permission_callback' => $admin,
            'args'                => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // GET /bookings/{id}/payments — paiements d'une réservation
        register_rest_route($this->namespace, '/bookings/(?P<id>[\d]+)/payments', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_booking_payments'],
            'permission_callback' => $admin,
            'args'                => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // PATCH /bookings/{id}/deposit — infos acompte
        register_rest_route($this->namespace, '/bookings/(?P<id>[\d]+)/deposit', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'save_deposit'],
            'permission_callback' => $admin,
            'args'                => [
                'id'               => ['validate_callback' => 'is_numeric'],
                'deposit_percent'  => ['required' => true, 'type' => 'number', 'minimum' => 0, 'maximum' => 100],
                'balance_due_date' => ['type' => 'string', 'format' => 'date'],
            ],
        ]);
    }

    // ─── POST /payments ───────────────────────────────────────────────────────

    public function create_payment($request) {
        global $wpdb;

        $booking_id   = intval($request->get_param('booking_id'));
        $amount       = floatval($request->get_param('amount'));
        $method       = sanitize_text_field($request->get_param('method'));
        $payment_date = sanitize_text_field($request->get_param('payment_date'));
        $reference    = sanitize_text_field($request->get_param('reference') ?? '');
        $note         = sanitize_textarea_field($request->get_param('note') ?? '');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        $valid_methods = array_keys(Riwa_Payments::get_methods());
        if (!in_array($method, $valid_methods, true)) {
            return new WP_Error('invalid_method', 'Méthode de paiement invalide.', ['status' => 400]);
        }

        $result = $wpdb->insert(
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

        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la sauvegarde.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'id'           => $wpdb->insert_id,
            'booking_id'   => $booking_id,
            'amount'       => $amount,
            'method'       => $method,
            'payment_date' => $payment_date,
            'reference'    => $reference,
            'note'         => $note,
        ], 201);
    }

    // ─── DELETE /payments/{id} ────────────────────────────────────────────────

    public function delete_payment($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $result = $wpdb->delete(
            $wpdb->prefix . 'riwa_payments',
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return new WP_Error('not_found', 'Paiement introuvable.', ['status' => 404]);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
    }

    // ─── GET /bookings/{id}/payments ──────────────────────────────────────────

    public function get_booking_payments($request) {
        global $wpdb;
        $booking_id = intval($request['id']);

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id, total_price, deposit_percent, deposit_amount, balance_due_date
             FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        $payments    = Riwa_Payments::get_payments_for_booking($booking_id);
        $total_paid  = Riwa_Payments::get_total_paid($booking_id);
        $pay_status  = Riwa_Payments::get_payment_status($booking);

        return new WP_REST_Response([
            'booking_id'       => $booking_id,
            'total_price'      => (float) $booking->total_price,
            'deposit_percent'  => (float) $booking->deposit_percent,
            'deposit_amount'   => (float) $booking->deposit_amount,
            'balance_due_date' => $booking->balance_due_date,
            'total_paid'       => (float) $total_paid,
            'balance'          => (float) $booking->total_price - (float) $total_paid,
            'payment_status'   => $pay_status,
            'status_label'     => Riwa_Payments::get_status_label($pay_status),
            'payments'         => $payments,
        ], 200);
    }

    // ─── PATCH /bookings/{id}/deposit ─────────────────────────────────────────

    public function save_deposit($request) {
        global $wpdb;
        $booking_id       = intval($request['id']);
        $deposit_percent  = floatval($request->get_param('deposit_percent'));
        $balance_due_date = sanitize_text_field($request->get_param('balance_due_date') ?? '');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id, total_price FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        $deposit_amount = round((float) $booking->total_price * $deposit_percent / 100, 2);

        $data   = ['deposit_percent' => $deposit_percent, 'deposit_amount' => $deposit_amount];
        $format = ['%f', '%f'];
        if (!empty($balance_due_date)) {
            $data['balance_due_date'] = $balance_due_date;
            $format[]                 = '%s';
        }

        $wpdb->update($wpdb->prefix . 'riwa_bookings', $data, ['id' => $booking_id], $format, ['%d']);

        return new WP_REST_Response([
            'booking_id'       => $booking_id,
            'deposit_percent'  => $deposit_percent,
            'deposit_amount'   => $deposit_amount,
            'balance_due_date' => $balance_due_date ?: null,
        ], 200);
    }

    private function get_create_params() {
        return [
            'booking_id'   => ['required' => true, 'type' => 'integer'],
            'amount'       => ['required' => true, 'type' => 'number', 'minimum' => 0.01],
            'method'       => ['required' => true, 'type' => 'string'],
            'payment_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
            'reference'    => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'note'         => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
        ];
    }
}

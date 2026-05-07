<?php
/**
 * REST Controller — Réservations
 * Routes : /wp-json/riwa/v1/bookings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Bookings_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'bookings';

    public function register_routes() {
        // GET /bookings  — liste filtrée (admin)
        // POST /bookings — créer une réservation (public)
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_items'],
                'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
                'args'                => $this->get_collection_params(),
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'create_item'],
                'permission_callback' => '__return_true',
                'args'                => $this->get_create_params(),
            ],
        ]);

        // GET /bookings/{id} (admin)
        // PATCH /bookings/{id} — changer statut (admin)
        // DELETE /bookings/{id} (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_item'],
                'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
                'args'                => ['id' => ['validate_callback' => 'is_numeric']],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [$this, 'update_item'],
                'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
                'args'                => $this->get_update_params(),
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_item'],
                'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
                'args'                => ['id' => ['validate_callback' => 'is_numeric']],
            ],
        ]);

        // PATCH /bookings/{id}/housekeeping (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/housekeeping', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'update_housekeeping'],
            'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
            'args'                => [
                'id'                  => ['validate_callback' => 'is_numeric'],
                'housekeeping_status' => [
                    'required'          => true,
                    'type'              => 'string',
                    'enum'              => ['pending', 'in_progress', 'ready'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /bookings/{id}/payments (admin) — délégué au payments controller
        // GET /bookings/{id}/notifications (admin) — délégué au notifications controller
    }

    // ─── GET /bookings ────────────────────────────────────────────────────────

    public function get_items($request) {
        $args = [
            'status'    => sanitize_text_field($request->get_param('status') ?? ''),
            'period'    => sanitize_text_field($request->get_param('period') ?? ''),
            'date_from' => sanitize_text_field($request->get_param('date_from') ?? ''),
            'date_to'   => sanitize_text_field($request->get_param('date_to') ?? ''),
            'search'    => sanitize_text_field($request->get_param('search') ?? ''),
            'orderby'   => sanitize_text_field($request->get_param('orderby') ?? 'created_at'),
            'order'     => strtoupper(sanitize_text_field($request->get_param('order') ?? 'DESC')),
            'page'      => intval($request->get_param('page') ?? 1),
            'per_page'  => intval($request->get_param('per_page') ?? 20),
            'price_min' => $request->get_param('price_min') !== null ? floatval($request->get_param('price_min')) : null,
            'price_max' => $request->get_param('price_max') !== null ? floatval($request->get_param('price_max')) : null,
        ];

        // Supprimer les paramètres vides
        $args = array_filter($args, fn($v) => $v !== '' && $v !== null);

        $result   = Riwa_Bookings_Table::get_filtered_bookings($args);
        $bookings = array_map([$this, 'prepare_booking'], $result['bookings']);

        $response = new WP_REST_Response([
            'bookings' => $bookings,
            'total'    => $result['total'],
            'page'     => $args['page'] ?? 1,
            'per_page' => $args['per_page'] ?? 20,
        ], 200);

        return $response;
    }

    // ─── GET /bookings/{id} ───────────────────────────────────────────────────

    public function get_item($request) {
        global $wpdb;
        $id      = intval($request['id']);
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $id
        ));

        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        return new WP_REST_Response($this->prepare_booking($booking), 200);
    }

    // ─── POST /bookings ───────────────────────────────────────────────────────

    public function create_item($request) {
        $guest_first_name = sanitize_text_field($request->get_param('guest_first_name') ?? '');
        $guest_last_name  = sanitize_text_field($request->get_param('guest_last_name') ?? '');
        $guest_name       = trim($guest_first_name . ' ' . $guest_last_name);
        $guest_email      = sanitize_email($request->get_param('guest_email') ?? '');
        $guest_phone      = sanitize_text_field($request->get_param('guest_phone') ?? '');
        $check_in_date    = sanitize_text_field($request->get_param('check_in_date') ?? '');
        $check_out_date   = sanitize_text_field($request->get_param('check_out_date') ?? '');
        $adults_count     = intval($request->get_param('adults_count') ?? 1);
        $children_count   = intval($request->get_param('children_count') ?? 0);
        $babies_count     = intval($request->get_param('babies_count') ?? 0);
        $special_requests = sanitize_textarea_field($request->get_param('special_requests') ?? '');

        if (empty($guest_first_name) || empty($guest_last_name) || empty($guest_email) ||
            empty($guest_phone) || empty($check_in_date) || empty($check_out_date)) {
            return new WP_Error('missing_fields', 'Veuillez remplir tous les champs obligatoires.', ['status' => 400]);
        }

        $total_travelers = $adults_count + $children_count + $babies_count;
        if ($adults_count < 1) {
            return new WP_Error('invalid_guests', 'Il doit y avoir au moins un adulte.', ['status' => 400]);
        }
        if ($total_travelers > 12) {
            return new WP_Error('too_many_guests', 'Le nombre total de voyageurs ne peut pas dépasser 12.', ['status' => 400]);
        }

        try {
            $check_in  = new DateTime($check_in_date);
            $check_out = new DateTime($check_out_date);
            $today     = new DateTime();
            if ($check_in < $today) {
                return new WP_Error('past_date', "La date d'arrivée ne peut pas être dans le passé.", ['status' => 400]);
            }
            if ($check_out <= $check_in) {
                return new WP_Error('invalid_dates', "La date de départ doit être après la date d'arrivée.", ['status' => 400]);
            }
        } catch (Exception $e) {
            return new WP_Error('invalid_date_format', 'Format de date invalide.', ['status' => 400]);
        }

        if (Riwa_Planning::has_overlap($check_in_date, $check_out_date)) {
            return new WP_Error('dates_unavailable', 'Ces dates ne sont pas disponibles.', ['status' => 409]);
        }

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'riwa_bookings',
            [
                'guest_name'       => $guest_name,
                'guest_email'      => $guest_email,
                'guest_phone'      => $guest_phone,
                'check_in_date'    => $check_in_date,
                'check_out_date'   => $check_out_date,
                'adults_count'     => $adults_count,
                'children_count'   => $children_count,
                'babies_count'     => $babies_count,
                'special_requests' => $special_requests,
                'status'           => 'pending',
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la sauvegarde.', ['status' => 500]);
        }

        $booking_id      = $wpdb->insert_id;
        $total_price     = Riwa_Pricing::calculate_total($check_in_date, $check_out_date, $total_travelers);
        $grand_total     = $total_price['total'];

        $wpdb->update(
            $wpdb->prefix . 'riwa_bookings',
            ['total_price' => $grand_total, 'price_per_night' => $total_price['per_night']],
            ['id' => $booking_id],
            ['%f', '%f'],
            ['%d']
        );

        try {
            Riwa_Emails::send_confirmation($guest_email, $guest_name, $check_in_date, $check_out_date);
            Riwa_Emails::send_admin_notification(
                $guest_name, $guest_email, $guest_phone,
                $check_in_date, $check_out_date,
                $adults_count, $children_count, $babies_count, $special_requests
            );
        } catch (Exception $e) {
            // Erreur silencieuse — réservation déjà enregistrée
        }

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));

        return new WP_REST_Response($this->prepare_booking($booking), 201);
    }

    // ─── PATCH /bookings/{id} ─────────────────────────────────────────────────

    public function update_item($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $status = sanitize_text_field($request->get_param('status') ?? '');

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $id
        ));
        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        if (!empty($status)) {
            if (!Riwa_Enums::is_valid_booking_status($status)) {
                return new WP_Error('invalid_status', 'Statut invalide.', ['status' => 400]);
            }
            $wpdb->update(
                $wpdb->prefix . 'riwa_bookings',
                ['status' => $status],
                ['id'     => $id],
                ['%s'],
                ['%d']
            );
        }

        $updated = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $id
        ));

        return new WP_REST_Response($this->prepare_booking($updated), 200);
    }

    // ─── DELETE /bookings/{id} ────────────────────────────────────────────────

    public function delete_item($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $result = $wpdb->delete(
            $wpdb->prefix . 'riwa_bookings',
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
    }

    // ─── PATCH /bookings/{id}/housekeeping ────────────────────────────────────

    public function update_housekeeping($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $status = sanitize_text_field($request->get_param('housekeeping_status'));

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $id
        ));
        if (!$booking) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }

        $wpdb->update(
            $wpdb->prefix . 'riwa_bookings',
            ['housekeeping_status' => $status],
            ['id'                  => $id],
            ['%s'],
            ['%d']
        );

        return new WP_REST_Response(['id' => $id, 'housekeeping_status' => $status], 200);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function prepare_booking($booking) {
        return [
            'id'                  => (int) $booking->id,
            'guest_name'          => $booking->guest_name,
            'guest_email'         => $booking->guest_email,
            'guest_phone'         => $booking->guest_phone,
            'check_in_date'       => $booking->check_in_date,
            'check_out_date'      => $booking->check_out_date,
            'adults_count'        => (int) $booking->adults_count,
            'children_count'      => (int) $booking->children_count,
            'babies_count'        => (int) ($booking->babies_count ?? 0),
            'special_requests'    => $booking->special_requests,
            'total_price'         => (float) $booking->total_price,
            'price_per_night'     => (float) $booking->price_per_night,
            'deposit_percent'     => (float) ($booking->deposit_percent ?? 0),
            'deposit_amount'      => (float) ($booking->deposit_amount ?? 0),
            'balance_due_date'    => $booking->balance_due_date,
            'status'              => $booking->status,
            'housekeeping_status' => $booking->housekeeping_status ?? 'pending',
            'created_at'          => $booking->created_at,
        ];
    }

    private function get_collection_params() {
        return [
            'status'    => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'cancelled']],
            'period'    => ['type' => 'string'],
            'date_from' => ['type' => 'string', 'format' => 'date'],
            'date_to'   => ['type' => 'string', 'format' => 'date'],
            'search'    => ['type' => 'string'],
            'orderby'   => ['type' => 'string', 'enum' => ['created_at', 'check_in_date', 'total_price'], 'default' => 'created_at'],
            'order'     => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
            'page'      => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'per_page'  => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
            'price_min' => ['type' => 'number'],
            'price_max' => ['type' => 'number'],
        ];
    }

    private function get_create_params() {
        return [
            'guest_first_name' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'guest_last_name'  => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'guest_email'      => ['required' => true, 'type' => 'string', 'format' => 'email'],
            'guest_phone'      => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            'check_in_date'    => ['required' => true, 'type' => 'string', 'format' => 'date'],
            'check_out_date'   => ['required' => true, 'type' => 'string', 'format' => 'date'],
            'adults_count'     => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            'children_count'   => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'babies_count'     => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
            'special_requests' => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
        ];
    }

    private function get_update_params() {
        return [
            'id'     => ['validate_callback' => 'is_numeric'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'confirmed', 'cancelled']],
        ];
    }
}

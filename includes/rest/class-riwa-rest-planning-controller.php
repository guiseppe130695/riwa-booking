<?php
/**
 * REST Controller — Planning & Disponibilités
 * Routes : /wp-json/riwa/v1/planning
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Planning_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'planning';

    public function register_routes() {
        // GET /planning?date_start=&date_end= — données calendrier (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_planning'],
            'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
            'args'                => [
                'date_start' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'date_end'   => ['required' => true, 'type' => 'string', 'format' => 'date'],
            ],
        ]);

        // GET /planning/availability — dates occupées (public)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/availability', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_availability'],
            'permission_callback' => '__return_true',
        ]);

        // POST /planning/blocked — bloquer une période (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/blocked', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'add_blocked'],
            'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
            'args'                => [
                'date_start' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'date_end'   => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'reason'     => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['maintenance', 'private', 'seasonal', 'event'],
                ],
                'note'       => ['type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field'],
            ],
        ]);

        // DELETE /planning/blocked/{id} (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/blocked/(?P<id>[\d]+)', [
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => [$this, 'delete_blocked'],
            'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
            'args'                => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // POST /planning/overrides — override de prix pour une date (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/overrides', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'save_price_override'],
            'permission_callback' => [Riwa_REST_API::class, 'permission_admin'],
            'args'                => [
                'date'  => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'price' => ['required' => true, 'type' => 'number', 'minimum' => 0],
            ],
        ]);
    }

    // ─── GET /planning ────────────────────────────────────────────────────────

    public function get_planning($request) {
        $date_start = sanitize_text_field($request->get_param('date_start'));
        $date_end   = sanitize_text_field($request->get_param('date_end'));

        $bookings  = Riwa_Planning::get_bookings_for_range($date_start, $date_end);
        $blocked   = Riwa_Planning::get_blocked_for_range($date_start, $date_end);
        $overrides = Riwa_Planning::get_overrides_for_range($date_start, $date_end);
        $stats     = Riwa_Planning::get_occupation_stats($date_start, $date_end);

        return new WP_REST_Response([
            'bookings'   => $bookings,
            'blocked'    => $blocked,
            'overrides'  => $overrides,
            'stats'      => $stats,
            'date_start' => $date_start,
            'date_end'   => $date_end,
        ], 200);
    }

    // ─── GET /planning/availability ───────────────────────────────────────────

    public function get_availability($request) {
        global $wpdb;

        $bookings = $wpdb->get_results(
            "SELECT check_in_date, check_out_date
             FROM {$wpdb->prefix}riwa_bookings
             WHERE status IN ('pending', 'confirmed')
             ORDER BY check_in_date ASC"
        );

        $booked_dates = [];
        foreach ($bookings as $booking) {
            $current = new DateTime($booking->check_in_date);
            $end     = new DateTime($booking->check_out_date);
            while ($current < $end) {
                $booked_dates[] = $current->format('Y-m-d');
                $current->add(new DateInterval('P1D'));
            }
        }

        $booked_dates = array_values(array_unique($booked_dates));
        sort($booked_dates);

        return new WP_REST_Response(['booked_dates' => $booked_dates], 200);
    }

    // ─── POST /planning/blocked ───────────────────────────────────────────────

    public function add_blocked($request) {
        global $wpdb;

        $date_start = sanitize_text_field($request->get_param('date_start'));
        $date_end   = sanitize_text_field($request->get_param('date_end'));
        $reason     = sanitize_text_field($request->get_param('reason'));
        $note       = sanitize_textarea_field($request->get_param('note') ?? '');

        $result = $wpdb->insert(
            $wpdb->prefix . 'riwa_blocked_dates',
            ['date_start' => $date_start, 'date_end' => $date_end, 'reason' => $reason, 'note' => $note],
            ['%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Erreur lors de la sauvegarde.', ['status' => 500]);
        }

        return new WP_REST_Response([
            'id'         => $wpdb->insert_id,
            'date_start' => $date_start,
            'date_end'   => $date_end,
            'reason'     => $reason,
            'note'       => $note,
        ], 201);
    }

    // ─── DELETE /planning/blocked/{id} ────────────────────────────────────────

    public function delete_blocked($request) {
        global $wpdb;
        $id     = intval($request['id']);
        $result = $wpdb->delete(
            $wpdb->prefix . 'riwa_blocked_dates',
            ['id' => $id],
            ['%d']
        );

        if (!$result) {
            return new WP_Error('not_found', 'Période bloquée introuvable.', ['status' => 404]);
        }

        return new WP_REST_Response(['deleted' => true, 'id' => $id], 200);
    }

    // ─── POST /planning/overrides ─────────────────────────────────────────────

    public function save_price_override($request) {
        global $wpdb;

        $date  = sanitize_text_field($request->get_param('date'));
        $price = floatval($request->get_param('price'));
        $table = $wpdb->prefix . 'riwa_date_overrides';

        if ($price <= 0) {
            $wpdb->delete($table, ['override_date' => $date], ['%s']);
            return new WP_REST_Response(['deleted' => true, 'date' => $date], 200);
        }

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE override_date = %s",
            $date
        ));

        if ($existing) {
            $wpdb->update($table, ['price' => $price], ['override_date' => $date], ['%f'], ['%s']);
        } else {
            $wpdb->insert($table, ['override_date' => $date, 'price' => $price], ['%s', '%f']);
        }

        return new WP_REST_Response(['date' => $date, 'price' => $price], 200);
    }
}

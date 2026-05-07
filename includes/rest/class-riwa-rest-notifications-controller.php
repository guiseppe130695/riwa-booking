<?php
/**
 * REST Controller — Notifications
 * Routes : /wp-json/riwa/v1/notifications
 *          /wp-json/riwa/v1/bookings/{id}/notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Notifications_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'notifications';

    public function register_routes() {
        $admin = [Riwa_REST_API::class, 'permission_admin'];

        // GET /notifications/log — log récent (admin)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/log', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_recent_log'],
            'permission_callback' => $admin,
            'args'                => [
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 30],
            ],
        ]);

        // GET /bookings/{id}/notifications — log d'une réservation (admin)
        register_rest_route($this->namespace, '/bookings/(?P<id>[\d]+)/notifications', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_booking_log'],
            'permission_callback' => $admin,
            'args'                => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // POST /bookings/{id}/notifications — logger une notification envoyée (admin)
        register_rest_route($this->namespace, '/bookings/(?P<id>[\d]+)/notifications', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'log_notification'],
            'permission_callback' => $admin,
            'args'                => [
                'id'      => ['validate_callback' => 'is_numeric'],
                'type'    => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['confirmation', 'reminder', 'checkin', 'review', 'custom'],
                ],
                'channel' => [
                    'type'    => 'string',
                    'enum'    => ['whatsapp', 'email'],
                    'default' => 'whatsapp',
                ],
            ],
        ]);

        // POST /bookings/{id}/notifications/preview — aperçu du message (admin)
        register_rest_route($this->namespace, '/bookings/(?P<id>[\d]+)/notifications/preview', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'preview'],
            'permission_callback' => $admin,
            'args'                => [
                'id'     => ['validate_callback' => 'is_numeric'],
                'type'   => [
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => ['confirmation', 'reminder', 'checkin', 'review'],
                ],
                'target' => [
                    'type'    => 'string',
                    'enum'    => ['client', 'admin'],
                    'default' => 'client',
                ],
            ],
        ]);
    }

    // ─── GET /notifications/log ───────────────────────────────────────────────

    public function get_recent_log($request) {
        global $wpdb;
        $limit = intval($request->get_param('limit') ?? 30);

        $log = $wpdb->get_results($wpdb->prepare(
            "SELECT nl.booking_id, nl.type, nl.channel, nl.sent_at, b.guest_name
             FROM {$wpdb->prefix}riwa_notification_log nl
             LEFT JOIN {$wpdb->prefix}riwa_bookings b ON b.id = nl.booking_id
             ORDER BY nl.sent_at DESC
             LIMIT %d",
            $limit
        ));

        return new WP_REST_Response(['log' => $log], 200);
    }

    // ─── GET /bookings/{id}/notifications ─────────────────────────────────────

    public function get_booking_log($request) {
        $booking_id = intval($request['id']);
        $this->assert_booking_exists($booking_id);

        $log = Riwa_Notifications::get_log($booking_id);
        return new WP_REST_Response(['booking_id' => $booking_id, 'log' => $log], 200);
    }

    // ─── POST /bookings/{id}/notifications ────────────────────────────────────

    public function log_notification($request) {
        $booking_id = intval($request['id']);
        $error      = $this->assert_booking_exists($booking_id);
        if (is_wp_error($error)) {
            return $error;
        }

        $type    = sanitize_text_field($request->get_param('type'));
        $channel = sanitize_text_field($request->get_param('channel') ?? 'whatsapp');

        Riwa_Notifications::log($booking_id, $type, $channel);

        return new WP_REST_Response([
            'logged'     => true,
            'booking_id' => $booking_id,
            'type'       => $type,
            'channel'    => $channel,
        ], 201);
    }

    // ─── POST /bookings/{id}/notifications/preview ────────────────────────────

    public function preview($request) {
        $booking_id = intval($request['id']);
        $error      = $this->assert_booking_exists($booking_id);
        if (is_wp_error($error)) {
            return $error;
        }

        $type    = sanitize_text_field($request->get_param('type'));
        $target  = sanitize_text_field($request->get_param('target') ?? 'client');
        $message = Riwa_Notifications::get_rendered_template($type, $booking_id);

        $phone   = '';
        $wa_link = '';
        if ($target === 'client') {
            global $wpdb;
            $phone = $wpdb->get_var($wpdb->prepare(
                "SELECT guest_phone FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
                $booking_id
            ));
        } else {
            $phone = get_option('riwa_notif_admin_phone', '');
        }

        if ($phone) {
            $wa_link = Riwa_Notifications::build_wa_link($phone, $message);
        }

        return new WP_REST_Response([
            'type'    => $type,
            'target'  => $target,
            'message' => $message,
            'wa_link' => $wa_link,
            'phone'   => $phone,
        ], 200);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function assert_booking_exists($booking_id) {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        if (!$exists) {
            return new WP_Error('not_found', 'Réservation introuvable.', ['status' => 404]);
        }
        return true;
    }
}

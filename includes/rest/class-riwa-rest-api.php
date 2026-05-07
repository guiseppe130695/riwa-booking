<?php
/**
 * Bootstrapper REST API — charge et enregistre tous les controllers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_API {

    const NAMESPACE = 'riwa/v1';

    public static function init() {
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-bookings-controller.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-planning-controller.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-payments-controller.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-stats-controller.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-notifications-controller.php';
        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/rest/class-riwa-rest-pricing-controller.php';

        $controllers = [
            new Riwa_REST_Bookings_Controller(),
            new Riwa_REST_Planning_Controller(),
            new Riwa_REST_Payments_Controller(),
            new Riwa_REST_Stats_Controller(),
            new Riwa_REST_Notifications_Controller(),
            new Riwa_REST_Pricing_Controller(),
        ];

        foreach ($controllers as $controller) {
            $controller->register_routes();
        }

        // CORS pour frontend découplé (Next.js / Nuxt)
        add_filter('rest_pre_serve_request', function ($served, $result, $request) {
            if (strpos($request->get_route(), '/riwa/') === 0) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
                header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
            }
            return $served;
        }, 10, 3);
    }

    public static function permission_admin() {
        return current_user_can('manage_options');
    }
}

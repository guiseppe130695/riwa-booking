<?php
/**
 * REST Controller — Pricing
 * Routes : /wp-json/riwa/v1/pricing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Pricing_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'pricing';

    public function register_routes() {
        // GET /pricing — liste des saisons tarifaires (public)
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_items'],
            'permission_callback' => '__return_true',
        ]);

        // POST /pricing/calculate — calcul du prix pour des dates (public)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/calculate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'calculate'],
            'permission_callback' => '__return_true',
            'args'                => [
                'check_in_date'  => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'check_out_date' => ['required' => true, 'type' => 'string', 'format' => 'date'],
                'guests_count'   => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
            ],
        ]);
    }

    public function get_items($request) {
        $data = Riwa_Pricing::get_pricing_data();
        return new WP_REST_Response($data, 200);
    }

    public function calculate($request) {
        $check_in     = sanitize_text_field($request->get_param('check_in_date'));
        $check_out    = sanitize_text_field($request->get_param('check_out_date'));
        $guests_count = intval($request->get_param('guests_count') ?? 1);

        try {
            $cin  = new DateTime($check_in);
            $cout = new DateTime($check_out);
            if ($cout <= $cin) {
                return new WP_Error('invalid_dates', "La date de départ doit être après l'arrivée.", ['status' => 400]);
            }
        } catch (Exception $e) {
            return new WP_Error('invalid_date_format', 'Format de date invalide (Y-m-d attendu).', ['status' => 400]);
        }

        $result = Riwa_Pricing::calculate_total($check_in, $check_out, $guests_count);

        return new WP_REST_Response([
            'check_in_date'  => $check_in,
            'check_out_date' => $check_out,
            'guests_count'   => $guests_count,
            'total'          => $result['total'],
            'per_night'      => $result['per_night'],
        ], 200);
    }
}

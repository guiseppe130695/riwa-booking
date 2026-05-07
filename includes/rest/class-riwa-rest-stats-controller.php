<?php
/**
 * REST Controller — Statistiques
 * Routes : /wp-json/riwa/v1/stats
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_REST_Stats_Controller extends WP_REST_Controller {

    protected $namespace = 'riwa/v1';
    protected $rest_base = 'stats';

    public function register_routes() {
        $admin = [Riwa_REST_API::class, 'permission_admin'];

        register_rest_route($this->namespace, '/' . $this->rest_base . '/health', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn($r) => new WP_REST_Response(Riwa_Stats::get_health_score(), 200),
            'permission_callback' => $admin,
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/kpis', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_kpis'],
            'permission_callback' => $admin,
            'args'                => [
                'year' => ['type' => 'integer', 'default' => (int) date('Y')],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/forecast', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn($r) => new WP_REST_Response(Riwa_Stats::get_forecast(), 200),
            'permission_callback' => $admin,
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/profile', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn($r) => new WP_REST_Response(Riwa_Stats::get_traveler_profile(), 200),
            'permission_callback' => $admin,
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/alerts', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => fn($r) => new WP_REST_Response(Riwa_Stats::get_alerts(), 200),
            'permission_callback' => $admin,
        ]);
    }

    public function get_kpis($request) {
        $year    = intval($request->get_param('year') ?? date('Y'));
        $kpis    = Riwa_Stats::get_yearly_stats($year);
        $monthly = Riwa_Stats::get_monthly_breakdown($year);

        return new WP_REST_Response([
            'year'    => $year,
            'kpis'    => $kpis,
            'monthly' => $monthly,
        ], 200);
    }
}

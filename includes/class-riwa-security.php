<?php
if (!defined('ABSPATH')) exit;

/**
 * Riwa_Security — Vérifications de sécurité centralisées
 *
 * Standardise les vérifications nonce + permissions pour tous
 * les handlers AJAX du plugin. Remplace les duplications de
 * check_ajax_referer() + current_user_can() dans chaque handler.
 */
class Riwa_Security {

    /**
     * Vérifie nonce + permissions admin pour un handler AJAX.
     * Envoie wp_send_json_error et arrête l'exécution si invalide.
     *
     * @param string $nonce_action  Action du nonce (ex: 'riwa_payments_nonce')
     * @param string $nonce_field   Nom du champ POST/GET contenant le nonce (défaut: 'nonce')
     * @param string $capability    Capacité WordPress requise (défaut: 'manage_options')
     */
    public static function check_admin(
        string $nonce_action,
        string $nonce_field = 'nonce',
        string $capability  = 'manage_options'
    ): void {
        if (!current_user_can($capability)) {
            wp_send_json_error('Permissions insuffisantes', 403);
        }
        check_ajax_referer($nonce_action, $nonce_field);
    }

    /**
     * Vérifie uniquement le nonce pour les handlers publics (frontend).
     * Aucune vérification de permissions (accessible sans être connecté).
     *
     * @param string $nonce_action  Action du nonce
     * @param string $nonce_value   Valeur du nonce (depuis $_POST ou $_GET)
     */
    public static function check_public(string $nonce_action, string $nonce_value): void {
        if (!wp_verify_nonce($nonce_value, $nonce_action)) {
            wp_send_json_error('Erreur de sécurité', 403);
        }
    }

    /**
     * Vérifie nonce depuis $_GET (pour les téléchargements via <a href>).
     * Interrompt l'exécution avec wp_die() si invalide (pas de JSON car c'est un download).
     *
     * @param string $nonce_action  Action du nonce
     * @param string $capability    Capacité requise
     */
    public static function check_admin_get_download(
        string $nonce_action,
        string $capability = 'manage_options'
    ): void {
        if (!current_user_can($capability)) {
            wp_die('Accès non autorisé', 403);
        }
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], $nonce_action)) {
            wp_die('Erreur de sécurité', 403);
        }
    }

    /**
     * Sanitise un tableau de paramètres POST selon un schéma de types.
     *
     * Types supportés : 'int', 'float', 'string', 'email', 'url', 'key', 'bool', 'date'
     *
     * @param  array $schema  ['nom_champ' => 'type', ...]
     * @param  array $source  Source des données (défaut: $_POST)
     * @return array          Tableau sanitisé
     */
    public static function sanitize_params(array $schema, array $source = []): array {
        if (empty($source)) {
            $source = $_POST; // phpcs:ignore WordPress.Security.NonceVerification
        }

        $result = [];
        foreach ($schema as $key => $type) {
            $raw = $source[$key] ?? null;
            $result[$key] = self::sanitize_value($raw, $type);
        }
        return $result;
    }

    /**
     * Sanitise une valeur selon son type.
     */
    public static function sanitize_value($value, string $type) {
        if ($value === null) {
            return self::default_for_type($type);
        }

        switch ($type) {
            case 'int':
                return intval($value);
            case 'float':
                return floatval($value);
            case 'string':
                return sanitize_text_field($value);
            case 'email':
                return sanitize_email($value);
            case 'url':
                return esc_url_raw($value);
            case 'key':
                return sanitize_key($value);
            case 'bool':
                return (bool) $value;
            case 'date':
                $clean = sanitize_text_field($value);
                if (!$clean) return '';
                $d = DateTime::createFromFormat('Y-m-d', $clean);
                return ($d && $d->format('Y-m-d') === $clean) ? $clean : '';
            case 'textarea':
                return sanitize_textarea_field($value);
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * Valeur par défaut selon le type.
     */
    private static function default_for_type(string $type) {
        $defaults = [
            'int'      => 0,
            'float'    => 0.0,
            'string'   => '',
            'email'    => '',
            'url'      => '',
            'key'      => '',
            'bool'     => false,
            'date'     => '',
            'textarea' => '',
        ];
        return $defaults[$type] ?? '';
    }
}

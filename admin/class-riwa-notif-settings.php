<?php
/**
 * Gestion des options de notifications (WhatsApp semi-auto)
 * Miroir de class-riwa-email-settings.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Notif_Settings {

    /**
     * Traiter la sauvegarde POST des paramètres notifications
     */
    public static function handle_save() {
        if (!isset($_POST['save_notif_settings'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['riwa_notif_settings_nonce'] ?? '', 'riwa_notif_settings')) {
            echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
            return;
        }

        if (!current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p>Permissions insuffisantes.</p></div>';
            return;
        }

        update_option('riwa_notif_whatsapp_enabled', isset($_POST['notif_whatsapp_enabled']) ? 1 : 0);
        update_option('riwa_notif_admin_phone',    sanitize_text_field($_POST['notif_admin_phone']    ?? ''));
        update_option('riwa_notif_country_code',   sanitize_text_field($_POST['notif_country_code']   ?? '+33'));
        update_option('riwa_notif_tpl_confirmation', sanitize_textarea_field($_POST['notif_tpl_confirmation'] ?? ''));
        update_option('riwa_notif_tpl_reminder',     sanitize_textarea_field($_POST['notif_tpl_reminder']     ?? ''));
        update_option('riwa_notif_tpl_checkin',      sanitize_textarea_field($_POST['notif_tpl_checkin']      ?? ''));
        update_option('riwa_notif_tpl_review',       sanitize_textarea_field($_POST['notif_tpl_review']       ?? ''));

        echo '<div class="notice notice-success is-dismissible"><p>Paramètres de notifications sauvegardés avec succès !</p></div>';
    }

    /**
     * Récupérer toutes les options notifications avec leurs valeurs par défaut
     */
    public static function get_options() {
        $defaults = Riwa_Notifications::get_default_templates();
        return [
            'whatsapp_enabled'   => (bool) get_option('riwa_notif_whatsapp_enabled', false),
            'admin_phone'        => get_option('riwa_notif_admin_phone', ''),
            'country_code'       => get_option('riwa_notif_country_code', '+33'),
            'tpl_confirmation'   => get_option('riwa_notif_tpl_confirmation', $defaults['confirmation'] ?? ''),
            'tpl_reminder'       => get_option('riwa_notif_tpl_reminder',     $defaults['reminder']     ?? ''),
            'tpl_checkin'        => get_option('riwa_notif_tpl_checkin',       $defaults['checkin']      ?? ''),
            'tpl_review'         => get_option('riwa_notif_tpl_review',        $defaults['review']       ?? ''),
        ];
    }

    /**
     * Retourne les noms lisibles des types de templates (utilisé par wp_localize_script)
     */
    public static function get_template_names() {
        return [
            'confirmation' => 'Confirmation',
            'reminder'     => 'Rappel',
            'checkin'      => 'Infos arrivée',
            'review'       => 'Demande avis',
        ];
    }
}

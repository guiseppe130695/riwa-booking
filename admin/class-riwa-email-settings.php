<?php
/**
 * Gestion de la configuration des emails (sauvegarde + lecture des options)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Email_Settings {

    /**
     * Traiter la sauvegarde POST des paramètres email
     */
    public static function handle_save() {
        if (!isset($_POST['save_email_settings'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['riwa_email_nonce'] ?? '', 'riwa_email_settings')) {
            echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
            return;
        }

        if (!current_user_can('manage_options')) {
            echo '<div class="notice notice-error"><p>Permissions insuffisantes.</p></div>';
            return;
        }

        update_option('riwa_email_notification_enabled', isset($_POST['notification_enabled']) ? 1 : 0);
        update_option('riwa_email_admin_address',  sanitize_email($_POST['admin_email'] ?? ''));
        update_option('riwa_email_from_name',      sanitize_text_field($_POST['from_name'] ?? ''));
        update_option('riwa_email_from_address',   sanitize_email($_POST['from_address'] ?? ''));
        update_option('riwa_email_client_subject', sanitize_text_field($_POST['client_subject'] ?? ''));
        update_option('riwa_email_admin_subject',  sanitize_text_field($_POST['admin_subject'] ?? ''));
        update_option('riwa_email_client_message', wp_kses_post($_POST['client_message'] ?? ''));
        update_option('riwa_email_admin_message',  wp_kses_post($_POST['admin_message'] ?? ''));

        echo '<div class="notice notice-success is-dismissible"><p>Configuration des emails sauvegardée avec succès !</p></div>';
    }

    /**
     * Récupérer toutes les options email avec leurs valeurs par défaut
     */
    public static function get_options() {
        return array(
            'notification_enabled' => get_option('riwa_email_notification_enabled', 1),
            'admin_address'        => get_option('riwa_email_admin_address', get_option('admin_email')),
            'from_name'            => get_option('riwa_email_from_name', 'Riwa Villa'),
            'from_address'         => get_option('riwa_email_from_address', 'noreply@riwa-villa.com'),
            'client_subject'       => get_option('riwa_email_client_subject', 'Confirmation de votre réservation - Riwa'),
            'admin_subject'        => get_option('riwa_email_admin_subject', 'Nouvelle réservation - Riwa Villa'),
            'client_message'       => get_option(
                'riwa_email_client_message',
                "Bonjour {guest_name},\n\nNous avons bien reçu votre réservation pour les dates suivantes :\nArrivée : {check_in}\nDépart : {check_out}\n\nNous vous contacterons bientôt pour confirmer votre réservation.\n\nCordialement,\nL'équipe Riwa"
            ),
            'admin_message'        => get_option(
                'riwa_email_admin_message',
                "Une nouvelle réservation a été effectuée sur le site.\n\nDétails de la réservation :\nNom : {guest_name}\nEmail : {guest_email}\nTéléphone : {guest_phone}\nDate d'arrivée : {check_in}\nDate de départ : {check_out}\nNombre d'adultes : {adults_count}\nNombre d'enfants : {children_count}\nNombre de bébés : {babies_count}\n\nDemandes spéciales : {special_requests}\n\nConnectez-vous à l'administration pour gérer cette réservation.\nLien d'administration : {admin_url}\n\nCordialement,\nSystème de réservation Riwa"
            ),
        );
    }
}

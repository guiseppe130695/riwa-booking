<?php
/**
 * Gestion des emails de réservation (confirmation client + notification admin)
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Emails {

    /**
     * Envoyer un email de confirmation au client
     */
    public static function send_confirmation($email, $name, $check_in, $check_out) {
        $from_name       = get_option('riwa_email_from_name', 'Riwa Villa');
        $from_address    = get_option('riwa_email_from_address', 'noreply@riwa-villa.com');
        $subject         = get_option('riwa_email_client_subject', 'Confirmation de votre réservation - Riwa');
        $message_template = get_option(
            'riwa_email_client_message',
            "Bonjour {guest_name},\n\nNous avons bien reçu votre réservation pour les dates suivantes :\nArrivée : {check_in}\nDépart : {check_out}\n\nNous vous contacterons bientôt pour confirmer votre réservation.\n\nCordialement,\nL'équipe Riwa"
        );

        $message = str_replace(
            array('{guest_name}', '{check_in}', '{check_out}'),
            array($name, $check_in, $check_out),
            $message_template
        );

        $headers = array(
            'From: ' . $from_name . ' <' . $from_address . '>',
            'Content-Type: text/plain; charset=UTF-8',
        );

        wp_mail($email, $subject, $message, $headers);
    }

    /**
     * Envoyer une notification à l'administrateur
     */
    public static function send_admin_notification($guest_name, $guest_email, $guest_phone, $check_in, $check_out, $adults_count, $children_count, $babies_count, $special_requests) {
        if (!get_option('riwa_email_notification_enabled', 1)) {
            return;
        }

        $admin_email     = get_option('riwa_email_admin_address', get_option('admin_email'));
        $from_name       = get_option('riwa_email_from_name', 'Riwa Villa');
        $from_address    = get_option('riwa_email_from_address', 'noreply@riwa-villa.com');
        $subject         = get_option('riwa_email_admin_subject', 'Nouvelle réservation - Riwa Villa');
        $message_template = get_option(
            'riwa_email_admin_message',
            "Une nouvelle réservation a été effectuée sur le site.\n\nDétails de la réservation :\nNom : {guest_name}\nEmail : {guest_email}\nTéléphone : {guest_phone}\nDate d'arrivée : {check_in}\nDate de départ : {check_out}\nNombre d'adultes : {adults_count}\nNombre d'enfants : {children_count}\nNombre de bébés : {babies_count}\n\nDemandes spéciales : {special_requests}\n\nConnectez-vous à l'administration pour gérer cette réservation.\nLien d'administration : {admin_url}\n\nCordialement,\nSystème de réservation Riwa"
        );

        $message = str_replace(
            array(
                '{guest_name}', '{guest_email}', '{guest_phone}',
                '{check_in}', '{check_out}',
                '{adults_count}', '{children_count}', '{babies_count}',
                '{special_requests}', '{admin_url}',
            ),
            array(
                $guest_name, $guest_email, $guest_phone,
                $check_in, $check_out,
                $adults_count, $children_count, $babies_count,
                $special_requests, admin_url('admin.php?page=riwa-booking'),
            ),
            $message_template
        );

        $headers = array(
            'From: ' . $from_name . ' <' . $from_address . '>',
            'Content-Type: text/plain; charset=UTF-8',
        );

        wp_mail($admin_email, $subject, $message, $headers);
    }

    /**
     * AJAX : tester l'envoi d'un email client (admin uniquement)
     */
    public static function ajax_test_client_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_test_email')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        $test_email = sanitize_email($_POST['email'] ?? '');
        if (empty($test_email)) {
            wp_send_json_error('Email de test invalide');
            return;
        }

        try {
            self::send_confirmation($test_email, 'Test Client', '2024-01-15', '2024-01-20');
            wp_send_json_success('Email de test client envoyé avec succès');
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }

    /**
     * AJAX : tester l'envoi d'un email administrateur (admin uniquement)
     */
    public static function ajax_test_admin_email() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissions insuffisantes');
            return;
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_test_email')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        $test_email = sanitize_email($_POST['email'] ?? '');
        if (empty($test_email)) {
            wp_send_json_error('Email de test invalide');
            return;
        }

        try {
            self::send_admin_notification(
                'Test Client',
                $test_email,
                '0123456789',
                '2024-01-15',
                '2024-01-20',
                2, 1, 0,
                'Demande de test'
            );
            wp_send_json_success('Email de test administrateur envoyé avec succès');
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'envoi : ' . $e->getMessage());
        }
    }
}

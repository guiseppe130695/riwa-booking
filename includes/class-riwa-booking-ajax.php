<?php
/**
 * Gestionnaires AJAX publics : soumission de réservation, dates occupées, PDF
 */

if (!defined('ABSPATH')) {
    exit;
}

class Riwa_Booking_Ajax {

    /**
     * AJAX : soumettre une nouvelle réservation
     */
    public static function submit_booking() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        // Sanitisation des champs
        $guest_first_name = sanitize_text_field($_POST['guest_first_name'] ?? '');
        $guest_last_name  = sanitize_text_field($_POST['guest_last_name'] ?? '');
        $guest_name       = trim($guest_first_name . ' ' . $guest_last_name);
        $guest_email      = sanitize_email($_POST['guest_email'] ?? '');
        $guest_phone      = sanitize_text_field($_POST['guest_phone'] ?? '');
        $check_in_date    = sanitize_text_field($_POST['check_in_date'] ?? '');
        $check_out_date   = sanitize_text_field($_POST['check_out_date'] ?? '');
        $adults_count     = intval($_POST['adults_count'] ?? 1);
        $children_count   = intval($_POST['children_count'] ?? 0);
        $babies_count     = intval($_POST['babies_count'] ?? 0);
        $special_requests = sanitize_textarea_field($_POST['special_requests'] ?? '');

        // Validation des champs obligatoires
        if (empty($guest_first_name) || empty($guest_last_name) || empty($guest_email) ||
            empty($guest_phone) || empty($check_in_date) || empty($check_out_date)) {
            wp_send_json_error('Veuillez remplir tous les champs obligatoires.');
            return;
        }

        // Validation des voyageurs
        $total_travelers = $adults_count + $children_count + $babies_count;
        if ($adults_count < 1) {
            wp_send_json_error('Il doit y avoir au moins un adulte.');
            return;
        }
        if ($total_travelers > 12) {
            wp_send_json_error('Le nombre total de voyageurs ne peut pas dépasser 12 personnes.');
            return;
        }

        // Validation des dates
        try {
            $check_in  = new DateTime($check_in_date);
            $check_out = new DateTime($check_out_date);
            $today     = new DateTime();

            if ($check_in < $today) {
                wp_send_json_error('La date d\'arrivée ne peut pas être dans le passé.');
                return;
            }
            if ($check_out <= $check_in) {
                wp_send_json_error('La date de départ doit être après la date d\'arrivée.');
                return;
            }
        } catch (Exception $e) {
            wp_send_json_error('Format de date invalide.');
            return;
        }

        // Insertion en base de données
        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_bookings';

        $result = $wpdb->insert(
            $table_name,
            array(
                'guest_name'      => $guest_name,
                'guest_email'     => $guest_email,
                'guest_phone'     => $guest_phone,
                'check_in_date'   => $check_in_date,
                'check_out_date'  => $check_out_date,
                'adults_count'    => $adults_count,
                'children_count'  => $children_count,
                'babies_count'    => $babies_count,
                'special_requests' => $special_requests,
                'status'          => 'pending',
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            $error_message = 'Erreur lors de la sauvegarde de la réservation.';
            if ($wpdb->last_error) {
                $error_message .= ' Erreur technique: ' . $wpdb->last_error;
            }
            wp_send_json_error($error_message);
            return;
        }

        $booking_id = $wpdb->insert_id;

        // Envoi des emails
        try {
            Riwa_Emails::send_confirmation($guest_email, $guest_name, $check_in_date, $check_out_date);
        } catch (Exception $e) {
            // Erreur silencieuse — la réservation est enregistrée
        }
        try {
            Riwa_Emails::send_admin_notification(
                $guest_name, $guest_email, $guest_phone,
                $check_in_date, $check_out_date,
                $adults_count, $children_count, $babies_count,
                $special_requests
            );
        } catch (Exception $e) {
            // Erreur silencieuse
        }

        // Calcul et mise à jour du prix
        $total_price = Riwa_Pricing::calculate_total($check_in_date, $check_out_date, $total_travelers);

        $wpdb->update(
            $table_name,
            array(
                'total_price'    => $total_price['total'],
                'price_per_night' => $total_price['per_night'],
            ),
            array('id' => $booking_id),
            array('%f', '%f'),
            array('%d')
        );

        $success_message = 'Votre réservation a été enregistrée avec succès !';
        if ($total_price['total'] > 0) {
            $success_message .= ' Prix total : ' . number_format($total_price['total'], 2, ',', ' ') . ' €';
        }

        wp_send_json_success(array(
            'message'    => $success_message,
            'booking_id' => $booking_id,
        ));
    }

    /**
     * AJAX : récupérer les dates occupées pour le calendrier
     */
    public static function get_booked_dates() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'riwa_bookings';

        $bookings = $wpdb->get_results("
            SELECT check_in_date, check_out_date
            FROM $table_name
            WHERE status IN ('pending', 'confirmed')
            ORDER BY check_in_date ASC
        ");

        $booked_dates = array();

        foreach ($bookings as $booking) {
            $start   = new DateTime($booking->check_in_date);
            $end     = new DateTime($booking->check_out_date);
            $current = clone $start;

            while ($current < $end) {
                $booked_dates[] = $current->format('Y-m-d');
                $current->add(new DateInterval('P1D'));
            }
        }

        $booked_dates = array_unique($booked_dates);
        sort($booked_dates);

        wp_send_json_success($booked_dates);
    }

    /**
     * AJAX : télécharger le PDF de confirmation (public)
     */
    public static function download_pdf() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'riwa_booking_nonce')) {
            wp_send_json_error('Erreur de sécurité');
            return;
        }

        $booking_id = intval($_POST['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_send_json_error('ID de réservation invalide');
            return;
        }

        require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-generator.php';

        try {
            Riwa_PDF_Generator::download_pdf($booking_id);
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de la génération du PDF');
        }
    }
}

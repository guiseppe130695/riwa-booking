<?php
/**
 * Classe pour générer des PDF de confirmation de réservation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Forcer l'inclusion de TCPDF
if (!class_exists('TCPDF')) {
    $tcpdf_path = plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php';
    if (file_exists($tcpdf_path)) {
        require_once($tcpdf_path);
    }
}

class Riwa_PDF_Generator {
    
    /**
     * Génère un PDF de confirmation de réservation
     */
    public static function generate_booking_pdf($booking_data) {
        // Si on reçoit un ID, récupérer les données
        if (is_numeric($booking_data)) {
            global $wpdb;
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
                $booking_data
            ));
            if (!$booking) {
                throw new Exception('Réservation non trouvée');
            }
        } else {
            $booking = $booking_data;
        }
        
        // Forcer l'inclusion de TCPDF
        if (!class_exists('TCPDF')) {
            $tcpdf_path = plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            } else {
                throw new Exception('TCPDF non disponible - Impossible de générer le PDF');
            }
        }
        
        try {
            return self::generate_tcpdf($booking);
        } catch (Exception $e) {
            throw new Exception('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }
    
    /**
     * Génère un PDF avec TCPDF
     */
    private static function generate_tcpdf($booking) {
        // Récupérer les options de personnalisation
        $options = self::get_pdf_options();
        
        // Créer une nouvelle instance TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Définir les informations du document
        $pdf->SetCreator('Riwa Booking');
        $pdf->SetAuthor($options['company_name']);
        $pdf->SetTitle($options['pdf_title'] . ' #' . $booking->id);
        $pdf->SetSubject('Confirmation de Réservation');
        
        // Supprimer les en-têtes et pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Définir les marges
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(TRUE, 20);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir la police
        $pdf->SetFont($options['font_family'], '', $options['font_size']);
        
        // Forcer le respect des styles CSS pour les images
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Générer le contenu HTML personnalisé
        $html = self::get_pdf_content($booking, $options);
        
        // Écrire le contenu HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Générer le nom du fichier avec timestamp pour éviter le cache
        $filename = 'reservation-' . sanitize_title($options['company_name']) . '-' . $booking->id . '-' . time() . '.pdf';
        
        // Retourner le PDF en tant que chaîne
        return $pdf->Output($filename, 'S');
    }
    
    /**
     * Génère le contenu HTML pour le PDF avec personnalisation
     */
    private static function get_pdf_content($booking, $options) {
        // Calculer le nombre de nuits
        $nights = (strtotime($booking->check_out_date) - strtotime($booking->check_in_date)) / (60 * 60 * 24);
        
        // Préparer les variables pour le template
        $booking_id = $booking->id;
        $guest_name = $booking->guest_name;
        $guest_email = $booking->guest_email;
        $guest_phone = $booking->guest_phone;
        $check_in_date = $booking->check_in_date;
        $check_out_date = $booking->check_out_date;
        $adults_count = $booking->adults_count;
        $children_count = $booking->children_count;
        $babies_count = $booking->babies_count;
        $pets_count = $booking->pets_count;
        $special_requests = $booking->special_requests;
        $total_price = $booking->total_price;
        $price_per_night = $booking->price_per_night;
        $created_at = $booking->created_at;
        
        // Convertir les couleurs hex en RGB pour TCPDF
        $primary_color = self::hex_to_rgb($options['primary_color']);
        $secondary_color = self::hex_to_rgb($options['secondary_color']);
        
        // Générer le HTML personnalisé - Version ultra-compacte pour une seule page
        $html = '<style>
            .header { text-align: center; margin-bottom: 2px; }
            .logo { max-width: 40px; max-height: 40px; }
            .title { font-size: 18px; font-weight: bold; color: rgb(' . $primary_color . '); margin: 1px 0; }
            .subtitle { font-size: 12px; color: rgb(' . $secondary_color . '); margin: 1px 0; }
            .section { margin: 0; }
            .section-title { font-size: 12px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 1px; margin-bottom: 1px; }
            .info-row { margin: 0; font-size: 10px; }
            .label { font-weight: bold; color: rgb(' . $primary_color . '); }
            .value { color: rgb(' . $secondary_color . '); }
            .total { font-size: 12px; font-weight: bold; color: rgb(' . $primary_color . '); border-top: 1px solid rgb(' . $primary_color . '); padding-top: 1px; margin-top: 1px; }
            .footer { text-align: center; margin-top: 5px; font-size: 9px; color: rgb(' . $secondary_color . '); }
            .qr-code { text-align: center; margin: 5px 0; }
            .signature { margin-top: 10px; border-top: 1px solid rgb(' . $secondary_color . '); padding-top: 5px; }
            .compact-grid { display: table; width: 100%; }
            .compact-col { display: table-cell; width: 50%; vertical-align: top; padding-right: 8px; }
        </style>';
        
        // En-tête avec logo
        $html .= '<div class="header">';
        if (!empty($options['logo_url'])) {
            $html .= '<img src="' . esc_url($options['logo_url']) . '" class="logo" style="max-width: 40px; max-height: 40px; width: auto; height: auto;" alt="' . esc_attr($options['company_name']) . '" />';
        }
        $html .= '<div class="title">' . esc_html($options['pdf_title']) . '</div>';
        $html .= '<div class="subtitle">' . esc_html($options['header_text']) . '</div>';
        $html .= '</div>';
        
        // Layout compact en 2 colonnes
        $html .= '<div class="compact-grid">';
        
        // Colonne gauche
        $html .= '<div class="compact-col">';
        
        // Informations de l'entreprise
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Entreprise</div>';
        $html .= '<br><span class="label">Nom :</span> <span class="value">' . esc_html($options['company_name']) . '</span><br>';
        if (!empty($options['company_phone'])) {
            $html .= '<span class="label">Tél :</span> <span class="value">' . esc_html($options['company_phone']) . '</span><br>';
        }
        if (!empty($options['company_email'])) {
            $html .= '<span class="label">Email :</span> <span class="value">' . esc_html($options['company_email']) . '</span>';
        }
        $html .= '</div>';
        
        // Informations du client
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Client</div>';
        $html .= '<br><span class="label">Nom :</span> <span class="value">' . esc_html($guest_name) . '</span><br>';
        $html .= '<span class="label">Email :</span> <span class="value">' . esc_html($guest_email) . '</span><br>';
        if (!empty($guest_phone)) {
            $html .= '<span class="label">Tél :</span> <span class="value">' . esc_html($guest_phone) . '</span>';
        }
        $html .= '</div>';
        
        // Dates
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Séjour</div>';
        $html .= '<br><span class="label">Arrivée :</span> <span class="value">' . date('d/m/Y', strtotime($check_in_date)) . '</span><br>';
        $html .= '<span class="label">Départ :</span> <span class="value">' . date('d/m/Y', strtotime($check_out_date)) . '</span><br>';
        $html .= '<span class="label">Nuits :</span> <span class="value">' . $nights . '</span>';
        $html .= '</div>';
        
        $html .= '</div>'; // Fin colonne gauche
        
        // Colonne droite
        $html .= '<div class="compact-col">';
        
        // Informations de la réservation
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Réservation</div>';
        $html .= '<br><span class="label">N° :</span> <span class="value">#' . esc_html($booking_id) . '</span><br>';
        $html .= '<span class="label">Date :</span> <span class="value">' . date('d/m/Y H:i', strtotime($created_at)) . '</span>';
        $html .= '</div>';
        
        // Voyageurs
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Voyageurs</div>';
        $travelers = array();
        if ($adults_count > 0) $travelers[] = $adults_count . ' adulte' . ($adults_count > 1 ? 's' : '');
        if ($children_count > 0) $travelers[] = $children_count . ' enfant' . ($children_count > 1 ? 's' : '');
        if ($babies_count > 0) $travelers[] = $babies_count . ' bébé' . ($babies_count > 1 ? 's' : '');
        if ($pets_count > 0) $travelers[] = $pets_count . ' animal' . ($pets_count > 1 ? 'aux' : '');
        $html .= '<div class="info-row"><span class="value">' . implode(', ', $travelers) . '</span></div>';
        $html .= '</div>';
        
        // Tarifs
        $html .= '<div class="section">';
        $html .= '<div class="section-title">Tarifs</div>';
        $html .= '<div class="info-row"><span class="label">Prix/nuit :</span> <span class="value">' . number_format($price_per_night, 2, ',', ' ') . ' €</span></div>';
        $html .= '<div class="total"><span class="label">Total :</span> <span class="value">' . number_format($total_price, 2, ',', ' ') . ' €</span></div>';
        $html .= '</div>';
        
        $html .= '</div>'; // Fin colonne droite
        $html .= '</div>'; // Fin grid
        
        // Demandes spéciales (si importantes)
        if (!empty($special_requests)) {
            $html .= '<div class="section">';
            $html .= '<div class="section-title">Demandes spéciales</div>';
            $html .= '<div class="value" style="font-size: 9px;">' . esc_html($special_requests) . '</div>';
            $html .= '</div>';
        }
        
        // Pied de page compact
        $html .= '<div class="footer">';
        $html .= '<div>' . esc_html($options['footer_text']) . '</div>';
        $html .= '<div style="margin-top: 5px;">Généré le ' . date('d/m/Y H:i') . '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Convertit une couleur hex en RGB
     */
    private static function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return $r . ',' . $g . ',' . $b;
    }
    
    /**
     * Récupère les options du PDF
     */
    private static function get_pdf_options() {
        // Inclure la classe d'administration si elle existe
        if (class_exists('Riwa_PDF_Admin')) {
            return Riwa_PDF_Admin::get_pdf_options();
        }
        
        // Fallback vers les options par défaut
        return array(
            'pdf_title' => 'Confirmation de Réservation',
            'company_name' => 'Riwa Villa',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'logo_url' => '',
            'primary_color' => '#000000',
            'secondary_color' => '#666666',
            'font_family' => 'helvetica',
            'font_size' => '10',
            'header_text' => 'Merci pour votre réservation !',
            'footer_text' => 'Pour toute question, contactez-nous.',
            'terms_conditions' => '',
            'show_qr_code' => '1',
            'show_signature' => '1'
        );
    }
    
    /**
     * Envoie le PDF en tant que téléchargement (PDF uniquement)
     */
    public static function download_pdf($booking_id) {
        global $wpdb;
        
        // Récupérer les données de la réservation
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}riwa_bookings WHERE id = %d",
            $booking_id
        ));
        
        if (!$booking) {
            wp_die('Réservation non trouvée');
        }
        
        // Forcer l'inclusion de TCPDF
        if (!class_exists('TCPDF')) {
            $tcpdf_path = plugin_dir_path(__FILE__) . 'tcpdf/tcpdf.php';
            if (file_exists($tcpdf_path)) {
                require_once($tcpdf_path);
            } else {
                wp_die('Erreur: TCPDF non disponible - Impossible de générer le PDF');
            }
        }
        
        try {
            // Générer le PDF
            $pdf_content = self::generate_booking_pdf($booking_id);
            
            if ($pdf_content && substr($pdf_content, 0, 4) === '%PDF') {
                // Définir les en-têtes pour le téléchargement PDF
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="reservation-riwa-' . $booking_id . '.pdf"');
                header('Content-Length: ' . strlen($pdf_content));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                // Envoyer le PDF
                echo $pdf_content;
            } else {
                wp_die('Erreur: Impossible de générer le PDF - Contenu invalide');
            }
        } catch (Exception $e) {
            wp_die('Erreur: ' . $e->getMessage());
        }
        exit;
    }
} 
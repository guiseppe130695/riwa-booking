<?php
/**
 * Classe pour gérer les actions AJAX de l'interface d'administration PDF
 */

if (!defined('ABSPATH')) {
    exit;
}

// Inclure la classe PDF Generator
require_once RIWA_BOOKING_PLUGIN_PATH . 'includes/class-riwa-pdf-generator.php';

class Riwa_PDF_Ajax {
    
    /**
     * Initialise les actions AJAX
     */
    public static function init() {
        add_action('wp_ajax_riwa_generate_pdf_preview', array(__CLASS__, 'generate_pdf_preview'));
        add_action('wp_ajax_riwa_test_pdf', array(__CLASS__, 'test_pdf'));
        add_action('wp_ajax_riwa_test_pdf_compact', array(__CLASS__, 'test_pdf_compact'));
        add_action('wp_ajax_riwa_pdf_diagnostic', array(__CLASS__, 'pdf_diagnostic'));
        add_action('wp_ajax_riwa_save_pdf_settings', array(__CLASS__, 'save_pdf_settings'));
        add_action('wp_ajax_riwa_reset_pdf_settings', array(__CLASS__, 'reset_pdf_settings'));
        add_action('wp_ajax_riwa_export_pdf_settings', array(__CLASS__, 'export_pdf_settings'));
        add_action('wp_ajax_riwa_import_pdf_settings', array(__CLASS__, 'import_pdf_settings'));
    }
    
    /**
     * Génère un aperçu du PDF
     */
    public static function generate_pdf_preview() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Récupérer les options
            $options = Riwa_PDF_Admin::get_pdf_options();
            
            // Créer des données de test
            $test_booking = (object) [
                'id' => 'PREVIEW-' . time(),
                'guest_name' => 'Client Test',
                'guest_email' => 'client@test.com',
                'guest_phone' => '0123456789',
                'check_in_date' => '2024-01-15',
                'check_out_date' => '2024-01-20',
                'adults_count' => 2,
                'children_count' => 1,
                'babies_count' => 0,
                'pets_count' => 0,
                'special_requests' => 'Demande spéciale de test',
                'total_price' => '1500',
                'price_per_night' => '300',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Générer l'aperçu HTML
            $preview_html = self::generate_preview_html($test_booking, $options);
            
            wp_send_json_success(array(
                'preview' => $preview_html
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de la génération de l\'aperçu: ' . $e->getMessage());
        }
    }
    
    /**
     * Teste la génération PDF
     */
    public static function test_pdf() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce (peut être dans GET ou POST)
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
        if (!wp_verify_nonce($nonce, 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Créer des données de test
            $test_booking = (object) [
                'id' => 'TEST-' . time(),
                'guest_name' => 'Client Test',
                'guest_email' => 'client@test.com',
                'guest_phone' => '0123456789',
                'check_in_date' => '2024-01-15',
                'check_out_date' => '2024-01-20',
                'adults_count' => 2,
                'children_count' => 1,
                'babies_count' => 0,
                'pets_count' => 0,
                'special_requests' => 'Demande spéciale de test',
                'total_price' => '1500',
                'price_per_night' => '300',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Générer le PDF
            $pdf_content = Riwa_PDF_Generator::generate_booking_pdf($test_booking);
            
            if ($pdf_content && substr($pdf_content, 0, 4) === '%PDF') {
                // Définir les en-têtes
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="test-reservation.pdf"');
                header('Content-Length: ' . strlen($pdf_content));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                
                // Envoyer le PDF
                echo $pdf_content;
            } else {
                wp_die('Erreur: Contenu non-PDF généré');
            }
            
        } catch (Exception $e) {
            wp_die('Erreur: ' . $e->getMessage());
        }
        exit;
    }
    
    /**
     * Teste la génération PDF compact (nouvelle version)
     */
    public static function test_pdf_compact() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce (peut être dans GET ou POST)
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
        if (!wp_verify_nonce($nonce, 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Créer des données de test avec plus d'informations
            $test_booking = (object) [
                'id' => 'COMPACT-' . time(),
                'guest_name' => 'Marie Martin',
                'guest_email' => 'marie.martin@email.com',
                'guest_phone' => '0987654321',
                'check_in_date' => '2024-03-01',
                'check_out_date' => '2024-03-07',
                'adults_count' => 3,
                'children_count' => 2,
                'babies_count' => 1,
                'pets_count' => 2,
                'special_requests' => 'Demande spéciale : lit bébé, chaise haute, et nourriture pour animaux. Arrivée prévue vers 15h.',
                'total_price' => '2100',
                'price_per_night' => '350',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Générer le PDF avec la nouvelle version compacte
            $pdf_content = Riwa_PDF_Generator::generate_booking_pdf($test_booking);
            
            if ($pdf_content && substr($pdf_content, 0, 4) === '%PDF') {
                // Définir les en-têtes avec nom de fichier unique
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="test-pdf-compact-' . time() . '.pdf"');
                header('Content-Length: ' . strlen($pdf_content));
                header('Cache-Control: no-cache, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Envoyer le PDF
                echo $pdf_content;
            } else {
                wp_die('Erreur: Contenu non-PDF généré - Début: ' . substr($pdf_content, 0, 50));
            }
            
        } catch (Exception $e) {
            wp_die('Erreur: ' . $e->getMessage());
        }
        exit;
    }
    
    /**
     * Diagnostic des changements PDF
     */
    public static function pdf_diagnostic() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Récupérer les options actuelles
            $options = Riwa_PDF_Admin::get_pdf_options();
            
            // Créer des données de test
            $test_booking = (object) [
                'id' => 'DIAG-' . time(),
                'guest_name' => 'Test Diagnostic',
                'guest_email' => 'test@diagnostic.com',
                'guest_phone' => '0123456789',
                'check_in_date' => '2024-02-15',
                'check_out_date' => '2024-02-20',
                'adults_count' => 2,
                'children_count' => 1,
                'babies_count' => 0,
                'pets_count' => 1,
                'special_requests' => 'Test des demandes spéciales',
                'total_price' => '1500',
                'price_per_night' => '300',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Générer le contenu HTML pour analyse
            $html_content = Riwa_PDF_Generator::get_pdf_content($test_booking, $options);
            
            // Analyser les changements
            $diagnostic_html = '<div class="riwa-diagnostic">';
            $diagnostic_html .= '<h4>✅ Changements appliqués :</h4>';
            $diagnostic_html .= '<ul>';
            
            // Vérifier les espaces réduits
            if (strpos($html_content, 'margin-bottom: 2px') !== false) {
                $diagnostic_html .= '<li>✅ En-tête : margin-bottom réduit à 2px</li>';
            } else {
                $diagnostic_html .= '<li>❌ En-tête : margin-bottom non réduit</li>';
            }
            
            if (strpos($html_content, '.section { margin: 0; }') !== false) {
                $diagnostic_html .= '<li>✅ Sections : margin supprimé (0)</li>';
            } else {
                $diagnostic_html .= '<li>❌ Sections : margin non supprimé</li>';
            }
            
            if (strpos($html_content, '.info-row { margin: 0;') !== false) {
                $diagnostic_html .= '<li>✅ Lignes d\'info : margin supprimé (0)</li>';
            } else {
                $diagnostic_html .= '<li>❌ Lignes d\'info : margin non supprimé</li>';
            }
            
            if (strpos($html_content, 'max-width: 40px; max-height: 40px;') !== false) {
                $diagnostic_html .= '<li>✅ Logo : taille réduite à 40px max</li>';
            } else {
                $diagnostic_html .= '<li>❌ Logo : taille non réduite</li>';
            }
            
            if (strpos($html_content, '.compact-grid') !== false) {
                $diagnostic_html .= '<li>✅ Layout : grille compacte en 2 colonnes</li>';
            } else {
                $diagnostic_html .= '<li>❌ Layout : grille compacte non appliquée</li>';
            }
            
            if (strpos($html_content, 'margin-top: 5px') !== false) {
                $diagnostic_html .= '<li>✅ Pied de page : margin-top réduit à 5px</li>';
            } else {
                $diagnostic_html .= '<li>❌ Pied de page : margin-top non réduit</li>';
            }
            
            $diagnostic_html .= '</ul>';
            
            $diagnostic_html .= '<h4>📊 Statistiques :</h4>';
            $diagnostic_html .= '<ul>';
            $diagnostic_html .= '<li>Nombre de sections : ' . substr_count($html_content, 'class="section"') . '</li>';
            $diagnostic_html .= '<li>Nombre de lignes d\'info : ' . substr_count($html_content, 'class="info-row"') . '</li>';
            $diagnostic_html .= '<li>Taille du contenu HTML : ' . strlen($html_content) . ' caractères</li>';
            $diagnostic_html .= '</ul>';
            
            $diagnostic_html .= '<h4>🔧 Actions recommandées :</h4>';
            $diagnostic_html .= '<ol>';
            $diagnostic_html .= '<li>Cliquez sur "Tester PDF Compact" pour générer un nouveau PDF</li>';
            $diagnostic_html .= '<li>Videz le cache de votre navigateur (Ctrl+F5)</li>';
            $diagnostic_html .= '<li>Vérifiez que le PDF tient sur une seule page</li>';
            $diagnostic_html .= '<li>Comparez avec un ancien PDF si disponible</li>';
            $diagnostic_html .= '</ol>';
            
            $diagnostic_html .= '</div>';
            
            wp_send_json_success(array(
                'html' => $diagnostic_html
            ));
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors du diagnostic: ' . $e->getMessage());
        }
    }
    
    /**
     * Sauvegarde les paramètres PDF
     */
    public static function save_pdf_settings() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Parser les données du formulaire
            parse_str($_POST['formData'], $form_data);
            
            if (!isset($form_data['riwa_pdf_options'])) {
                throw new Exception('Données de formulaire invalides');
            }
            
            $options = $form_data['riwa_pdf_options'];
            
            // Valider et nettoyer les options
            $valid_options = self::validate_settings($options);
            
            // Sauvegarder les options
            update_option('riwa_pdf_options', $valid_options);
            
            wp_send_json_success('Paramètres enregistrés avec succès');
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    }
    
    /**
     * Réinitialise les paramètres PDF
     */
    public static function reset_pdf_settings() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Supprimer les options
            delete_option('riwa_pdf_options');
            
            wp_send_json_success('Paramètres réinitialisés avec succès');
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de la réinitialisation: ' . $e->getMessage());
        }
    }
    
    /**
     * Exporte les paramètres PDF
     */
    public static function export_pdf_settings() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Récupérer les options
            $options = get_option('riwa_pdf_options', array());
            
            wp_send_json_success($options);
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'export: ' . $e->getMessage());
        }
    }
    
    /**
     * Importe les paramètres PDF
     */
    public static function import_pdf_settings() {
        // Vérifier les permissions
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        
        // Vérifier le nonce
        if (!wp_verify_nonce($_POST['nonce'], 'riwa_pdf_admin_nonce')) {
            wp_die('Nonce invalide');
        }
        
        try {
            // Récupérer les paramètres
            $settings = $_POST['settings'];
            
            if (!is_array($settings)) {
                throw new Exception('Format de paramètres invalide');
            }
            
            // Valider les paramètres
            $valid_settings = self::validate_settings($settings);
            
            // Sauvegarder les options
            update_option('riwa_pdf_options', $valid_settings);
            
            wp_send_json_success('Paramètres importés avec succès');
            
        } catch (Exception $e) {
            wp_send_json_error('Erreur lors de l\'import: ' . $e->getMessage());
        }
    }
    
    /**
     * Génère l'aperçu HTML
     */
    private static function generate_preview_html($booking, $options) {
        // Calculer le nombre de nuits
        $nights = (strtotime($booking->check_out_date) - strtotime($booking->check_in_date)) / (60 * 60 * 24);
        
        // Convertir les couleurs hex en RGB
        $primary_color = self::hex_to_rgb($options['primary_color']);
        $secondary_color = self::hex_to_rgb($options['secondary_color']);
        
        $html = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; background: white;">';
        
        // En-tête avec logo
        $html .= '<div style="text-align: center; margin-bottom: 20px;">';
        if (!empty($options['logo_url'])) {
            $html .= '<img src="' . esc_url($options['logo_url']) . '" style="max-width: 50px; max-height: 50px;" alt="' . esc_attr($options['company_name']) . '" />';
        }
        $html .= '<h2 style="font-size: 24px; font-weight: bold; color: rgb(' . $primary_color . '); margin: 10px 0;">' . esc_html($options['pdf_title']) . '</h2>';
        $html .= '<p style="font-size: 16px; color: rgb(' . $secondary_color . '); margin: 5px 0;">' . esc_html($options['header_text']) . '</p>';
        $html .= '</div>';
        
        // Informations de l'entreprise
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Informations de l\'entreprise</h3>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Entreprise :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($options['company_name']) . '</span></p>';
        if (!empty($options['company_address'])) {
            $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Adresse :</strong> <span style="color: rgb(' . $secondary_color . ');">' . nl2br(esc_html($options['company_address'])) . '</span></p>';
        }
        if (!empty($options['company_phone'])) {
            $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Téléphone :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($options['company_phone']) . '</span></p>';
        }
        if (!empty($options['company_email'])) {
            $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Email :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($options['company_email']) . '</span></p>';
        }
        $html .= '</div>';
        
        // Informations de la réservation
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Détails de la réservation</h3>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Numéro de réservation :</strong> <span style="color: rgb(' . $secondary_color . ');">#' . esc_html($booking->id) . '</span></p>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Date de réservation :</strong> <span style="color: rgb(' . $secondary_color . ');">' . date('d/m/Y H:i', strtotime($booking->created_at)) . '</span></p>';
        $html .= '</div>';
        
        // Informations du client
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Informations du client</h3>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Nom :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($booking->guest_name) . '</span></p>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Email :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($booking->guest_email) . '</span></p>';
        if (!empty($booking->guest_phone)) {
            $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Téléphone :</strong> <span style="color: rgb(' . $secondary_color . ');">' . esc_html($booking->guest_phone) . '</span></p>';
        }
        $html .= '</div>';
        
        // Dates et voyageurs
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Séjour</h3>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Arrivée :</strong> <span style="color: rgb(' . $secondary_color . ');">' . date('d/m/Y', strtotime($booking->check_in_date)) . '</span></p>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Départ :</strong> <span style="color: rgb(' . $secondary_color . ');">' . date('d/m/Y', strtotime($booking->check_out_date)) . '</span></p>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Nombre de nuits :</strong> <span style="color: rgb(' . $secondary_color . ');">' . $nights . '</span></p>';
        
        $travelers = array();
        if ($booking->adults_count > 0) $travelers[] = $booking->adults_count . ' adulte' . ($booking->adults_count > 1 ? 's' : '');
        if ($booking->children_count > 0) $travelers[] = $booking->children_count . ' enfant' . ($booking->children_count > 1 ? 's' : '');
        if ($booking->babies_count > 0) $travelers[] = $booking->babies_count . ' bébé' . ($booking->babies_count > 1 ? 's' : '');
        if ($booking->pets_count > 0) $travelers[] = $booking->pets_count . ' animal' . ($booking->pets_count > 1 ? 'aux' : '');
        
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Voyageurs :</strong> <span style="color: rgb(' . $secondary_color . ');">' . implode(', ', $travelers) . '</span></p>';
        $html .= '</div>';
        
        // Tarifs
        $html .= '<div style="margin: 15px 0;">';
        $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Tarifs</h3>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Prix par nuit :</strong> <span style="color: rgb(' . $secondary_color . ');">' . number_format($booking->price_per_night, 2, ',', ' ') . ' €</span></p>';
        $html .= '<p><strong style="color: rgb(' . $primary_color . ');">Nombre de nuits :</strong> <span style="color: rgb(' . $secondary_color . ');">' . $nights . '</span></p>';
        $html .= '<p style="font-size: 16px; font-weight: bold; color: rgb(' . $primary_color . '); border-top: 2px solid rgb(' . $primary_color . '); padding-top: 10px; margin-top: 10px;"><strong>Total :</strong> <span style="color: rgb(' . $secondary_color . ');">' . number_format($booking->total_price, 2, ',', ' ') . ' €</span></p>';
        $html .= '</div>';
        
        // Demandes spéciales
        if (!empty($booking->special_requests)) {
            $html .= '<div style="margin: 15px 0;">';
            $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . '); border-bottom: 1px solid rgb(' . $secondary_color . '); padding-bottom: 5px; margin-bottom: 10px;">Demandes spéciales</h3>';
            $html .= '<p style="color: rgb(' . $secondary_color . ');">' . nl2br(esc_html($booking->special_requests)) . '</p>';
            $html .= '</div>';
        }
        
        // QR Code (si activé)
        if ($options['show_qr_code'] == '1') {
            $html .= '<div style="text-align: center; margin: 20px 0;">';
            $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . ');">QR Code de la réservation</h3>';
            $html .= '<p style="font-size: 10px; color: rgb(' . $secondary_color . ');">Scannez ce code pour accéder aux détails de votre réservation<br>';
            $html .= 'Réservation #' . $booking->id . ' - ' . $booking->guest_name . '</p>';
            $html .= '</div>';
        }
        
        // Signature (si activée)
        if ($options['show_signature'] == '1') {
            $html .= '<div style="margin-top: 40px; border-top: 1px solid rgb(' . $secondary_color . '); padding-top: 20px;">';
            $html .= '<div style="float: left; width: 45%;">';
            $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . ');">Signature du client</h3>';
            $html .= '<div style="border-bottom: 1px solid rgb(' . $secondary_color . '); height: 40px; margin-top: 20px;"></div>';
            $html .= '</div>';
            $html .= '<div style="float: right; width: 45%;">';
            $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . ');">Signature de l\'entreprise</h3>';
            $html .= '<div style="border-bottom: 1px solid rgb(' . $secondary_color . '); height: 40px; margin-top: 20px;"></div>';
            $html .= '</div>';
            $html .= '<div style="clear: both;"></div>';
            $html .= '</div>';
        }
        
        // Conditions générales (si définies)
        if (!empty($options['terms_conditions'])) {
            $html .= '<div style="margin: 15px 0;">';
            $html .= '<h3 style="font-size: 14px; font-weight: bold; color: rgb(' . $primary_color . ');">Conditions générales</h3>';
            $html .= '<p style="font-size: 10px; color: rgb(' . $secondary_color . ');">' . nl2br(esc_html($options['terms_conditions'])) . '</p>';
            $html .= '</div>';
        }
        
        // Pied de page
        $html .= '<div style="text-align: center; margin-top: 30px; font-size: 12px; color: rgb(' . $secondary_color . ');">';
        $html .= '<p>' . esc_html($options['footer_text']) . '</p>';
        $html .= '<p style="margin-top: 10px;">Document généré le ' . date('d/m/Y à H:i') . '</p>';
        $html .= '</div>';
        
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
     * Valide les paramètres importés
     */
    private static function validate_settings($settings) {
        $valid_settings = array();
        
        // Champs autorisés
        $allowed_fields = array(
            'pdf_title', 'company_name', 'company_address', 'company_phone', 'company_email',
            'logo_url', 'primary_color', 'secondary_color', 'font_family', 'font_size',
            'header_text', 'footer_text', 'terms_conditions', 'show_qr_code', 'show_signature'
        );
        
        foreach ($allowed_fields as $field) {
            if (isset($settings[$field])) {
                $valid_settings[$field] = sanitize_text_field($settings[$field]);
            }
        }
        
        // Validation spécifique
        if (isset($valid_settings['primary_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $valid_settings['primary_color'])) {
            $valid_settings['primary_color'] = '#000000';
        }
        
        if (isset($valid_settings['secondary_color']) && !preg_match('/^#[a-fA-F0-9]{6}$/', $valid_settings['secondary_color'])) {
            $valid_settings['secondary_color'] = '#666666';
        }
        
        if (isset($valid_settings['font_size']) && (!is_numeric($valid_settings['font_size']) || $valid_settings['font_size'] < 8 || $valid_settings['font_size'] > 16)) {
            $valid_settings['font_size'] = '10';
        }
        
        return $valid_settings;
    }
} 
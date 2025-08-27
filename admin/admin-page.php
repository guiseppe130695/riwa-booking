<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Récupération des réservations
global $wpdb;
$table_name = $wpdb->prefix . 'riwa_bookings';

// Gestion des actions (changer le statut et supprimer)
if (isset($_POST['action'])) {
    $action = sanitize_text_field($_POST['action']);
    
    if ($action === 'update_status' && isset($_POST['booking_id'])) {
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        if (in_array($new_status, array('pending', 'confirmed', 'cancelled'))) {
            $wpdb->update(
                $table_name,
                array('status' => $new_status),
                array('id' => $booking_id),
                array('%s'),
                array('%d')
            );
            echo '<div class="notice notice-success"><p>Statut mis à jour avec succès!</p></div>';
        }
    }
    
    if ($action === 'delete_booking' && isset($_POST['booking_id']) && wp_verify_nonce($_POST['delete_nonce'], 'delete_booking_nonce')) {
        $booking_id = intval($_POST['booking_id']);
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $booking_id),
            array('%d')
        );
        
        if ($result) {
            echo '<div class="notice notice-success"><p>Réservation supprimée avec succès!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Erreur lors de la suppression de la réservation.</p></div>';
        }
    }
}

// Traitement de la sauvegarde des paramètres email
if (isset($_POST['save_email_settings']) && wp_verify_nonce($_POST['riwa_email_nonce'], 'riwa_email_settings')) {
    // Sauvegarder les paramètres
    update_option('riwa_email_notification_enabled', isset($_POST['notification_enabled']) ? 1 : 0);
    update_option('riwa_email_admin_address', sanitize_email($_POST['admin_email']));
    update_option('riwa_email_from_name', sanitize_text_field($_POST['from_name']));
    update_option('riwa_email_from_address', sanitize_email($_POST['from_address']));
    update_option('riwa_email_client_subject', sanitize_text_field($_POST['client_subject']));
    update_option('riwa_email_admin_subject', sanitize_text_field($_POST['admin_subject']));
    update_option('riwa_email_client_message', wp_kses_post($_POST['client_message']));
    update_option('riwa_email_admin_message', wp_kses_post($_POST['admin_message']));
    
    echo '<div class="notice notice-success is-dismissible"><p>Configuration des emails sauvegardée avec succès !</p></div>';
}

// Récupération de toutes les réservations
$bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

// Debug temporaire pour vérifier les données
if (empty($bookings)) {
    echo '<div class="notice notice-warning"><p>Aucune réservation trouvée dans la base de données.</p></div>';
} else {
    // Vérifier la structure de la première réservation
    $first_booking = $bookings[0];
    if (!isset($first_booking->guests_count)) {
        echo '<div class="notice notice-error"><p>Attention : La colonne "guests_count" n\'existe pas dans la table des réservations.</p></div>';
    }
}
?>

<div class="wrap riwa-pdf-admin">
    <div class="riwa-admin-header">
        <div class="riwa-header-content">
            <h1>Gestion des Réservations</h1>
            <p class="riwa-subtitle">Consultez et gérez toutes vos réservations</p>
        </div>
        <div class="riwa-header-actions">
            <button type="button" class="riwa-btn riwa-btn-secondary" id="export-bookings">
                <span class="dashicons dashicons-download"></span>
                Exporter
            </button>
            <button type="button" class="riwa-btn riwa-btn-primary" id="refresh-bookings">
                <span class="dashicons dashicons-update"></span>
                Actualiser
            </button>
        </div>
    </div>
    
    <div class="riwa-pdf-admin-container">
        <!-- Panneau de navigation -->
        <div class="riwa-nav-panel">
            <div class="riwa-nav-header">
                <h3>Navigation</h3>
            </div>
            <nav class="riwa-nav-menu">
                <a href="#overview" class="riwa-nav-item active" data-section="overview">
                    <span class="dashicons dashicons-chart-bar"></span>
                    Vue d'ensemble
                </a>
                <a href="#bookings" class="riwa-nav-item" data-section="bookings">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    Réservations
                </a>
                <a href="#pricing" class="riwa-nav-item" data-section="pricing">
                    <span class="dashicons dashicons-money-alt"></span>
                    Tarification
                </a>
                <a href="#pdf" class="riwa-nav-item" data-section="pdf">
                    <span class="dashicons dashicons-pdf"></span>
                    Personnaliser PDF
                </a>
                <a href="#email" class="riwa-nav-item" data-section="email">
                    <span class="dashicons dashicons-email-alt"></span>
                    Configuration Email
                </a>
                <a href="#debug" class="riwa-nav-item" data-section="debug">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Diagnostic
                </a>
            </nav>
        </div>
        
        <!-- Panneau de contenu -->
        <div class="riwa-content-panel">
            <!-- Section Vue d'ensemble -->
            <div class="riwa-section active" id="overview-section">
                <div class="riwa-section-header">
                    <h2>Statistiques des réservations</h2>
                    <p>Vue d'ensemble de vos réservations</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-form-grid">
                        <div class="riwa-form-group">
                            <h3>Total des réservations</h3>
                            <span class="stat-number"><?php echo count($bookings); ?></span>
                        </div>
                        <div class="riwa-form-group">
                            <h3>En attente</h3>
                            <span class="stat-number pending"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'pending'; })); ?></span>
                        </div>
                        <div class="riwa-form-group">
                            <h3>Confirmées</h3>
                            <span class="stat-number confirmed"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'confirmed'; })); ?></span>
                        </div>
                        <div class="riwa-form-group">
                            <h3>Annulées</h3>
                            <span class="stat-number cancelled"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'cancelled'; })); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Réservations -->
            <div class="riwa-section" id="bookings-section">
                <div class="riwa-section-header">
                    <h2>Liste des réservations</h2>
                    <p>Gérez toutes vos réservations</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <?php if (empty($bookings)): ?>
                            <div class="riwa-empty-state">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <h3>Aucune réservation</h3>
                                <p>Aucune réservation n'a été trouvée dans le système.</p>
                            </div>
                        <?php else: ?>
                            <div class="riwa-bookings-table">
                                <div class="riwa-table-header">
                                    <div class="riwa-table-info">
                                        <span class="riwa-table-count"><?php echo count($bookings); ?> réservation<?php echo count($bookings) > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="riwa-table-actions">
                                        <button type="button" class="riwa-btn riwa-btn-secondary button-small" id="filter-bookings">
                                            <span class="dashicons dashicons-filter"></span>
                                            Filtrer
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="riwa-table-wrapper">
                                    <table class="riwa-modern-table">
                                        <thead>
                                            <tr>
                                                <th class="riwa-th-id">ID</th>
                                                <th class="riwa-th-reference">Référence</th>
                                                <th class="riwa-th-client">Client & Contact</th>
                                                <th class="riwa-th-price">Prix</th>
                                                <th class="riwa-th-status">Statut</th>
                                                <th class="riwa-th-date">Créée le</th>
                                                <th class="riwa-th-actions">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bookings as $booking): ?>
                                                <tr class="riwa-booking-row">
                                                    <td class="riwa-td-id">
                                                        <span class="riwa-booking-id">#<?php echo esc_html($booking->id); ?></span>
                                                    </td>
                                                    <td class="riwa-td-reference">
                                                        <span class="riwa-booking-reference">RIWA-<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></span>
                                                    </td>
                                                    <td class="riwa-td-client">
                                                        <div class="riwa-client-info">
                                                            <div class="riwa-client-name"><?php echo esc_html($booking->guest_name); ?></div>
                                                            <div class="riwa-client-contact">
                                                                <a href="mailto:<?php echo esc_attr($booking->guest_email); ?>"><?php echo esc_html($booking->guest_email); ?></a>
                                                                <span class="riwa-contact-separator">•</span>
                                                                <a href="tel:<?php echo esc_attr($booking->guest_phone); ?>"><?php echo esc_html($booking->guest_phone); ?></a>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="riwa-td-price">
                                                        <?php if ($booking->total_price > 0): ?>
                                                            <span class="riwa-price-total"><?php echo number_format($booking->total_price, 0, ',', ' '); ?> €</span>
                                                        <?php else: ?>
                                                            <span class="riwa-price-unknown">
                                                                <span class="dashicons dashicons-minus"></span>
                                                                Non calculé
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="riwa-td-status">
                                                        <span class="riwa-status-badge riwa-status-<?php echo esc_attr($booking->status); ?>">
                                                            <?php 
                                                            switch($booking->status) {
                                                                case 'pending': echo 'En attente'; break;
                                                                case 'confirmed': echo 'Confirmée'; break;
                                                                case 'cancelled': echo 'Annulée'; break;
                                                                default: echo ucfirst($booking->status);
                                                            }
                                                            ?>
                                                        </span>
                                                    </td>
                                                    <td class="riwa-td-date">
                                                        <span class="riwa-date-created"><?php echo date('d/m/Y', strtotime($booking->created_at)); ?></span>
                                                    </td>
                                                    <td class="riwa-td-actions">
                                                        <div class="riwa-actions-compact">
                                                            <select name="new_status" onchange="updateBookingStatus(<?php echo esc_attr($booking->id); ?>, this.value)" class="riwa-status-select-compact">
                                                                <option value="">Statut</option>
                                                                <option value="pending" <?php selected($booking->status, 'pending'); ?>>En attente</option>
                                                                <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmée</option>
                                                                <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Annulée</option>
                                                            </select>
                                                            
                                                            <button type="button" class="riwa-btn riwa-btn-secondary button-small view-details-popup" 
                                                                    data-booking-id="<?php echo esc_attr($booking->id); ?>"
                                                                    data-booking-name="<?php echo esc_attr($booking->guest_name); ?>"
                                                                    data-booking-email="<?php echo esc_attr($booking->guest_email); ?>"
                                                                    data-booking-phone="<?php echo esc_attr($booking->guest_phone); ?>"
                                                                    data-booking-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                                                                    data-booking-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                                                                    data-booking-guests="<?php echo esc_attr($booking->guests_count); ?>"
                                                                    data-booking-adults="<?php echo esc_attr($booking->adults_count ?? 0); ?>"
                                                                    data-booking-children="<?php echo esc_attr($booking->children_count ?? 0); ?>"
                                                                    data-booking-babies="<?php echo esc_attr($booking->babies_count ?? 0); ?>"

                                                                    data-booking-price="<?php echo esc_attr($booking->total_price); ?>"
                                                                    data-booking-price-per-night="<?php echo esc_attr($booking->price_per_night); ?>"
                                                                    data-booking-status="<?php echo esc_attr($booking->status); ?>"
                                                                    data-booking-created="<?php echo esc_attr($booking->created_at); ?>"
                                                                    data-booking-requests="<?php echo esc_attr($booking->special_requests); ?>">
                                                                Détails
                                                            </button>
                                                            
                                                            <button type="button" class="riwa-btn riwa-btn-danger button-small delete-booking-btn" 
                                                                    data-booking-id="<?php echo esc_attr($booking->id); ?>"
                                                                    data-booking-name="<?php echo esc_attr($booking->guest_name); ?>">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                                

                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Tarification -->
            <div class="riwa-section" id="pricing-section">
                <div class="riwa-section-header">
                    <h2>Gestion de la tarification</h2>
                    <p>Configurez vos tarifs par saison</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <div class="riwa-pricing-header">
                            <h3>Tarifs actuels</h3>
                            <button type="button" class="riwa-btn riwa-btn-primary" id="add-pricing-btn">
                                <span class="dashicons dashicons-plus-alt"></span>
                                Ajouter un tarif
                            </button>
                        </div>
                        
                        <?php
                        // Récupérer les données de tarification
                        $pricing_table = $wpdb->prefix . 'riwa_pricing';
                        $pricing_data = $wpdb->get_results("SELECT * FROM $pricing_table ORDER BY start_date ASC");
                        ?>
                        
                        <?php if (empty($pricing_data)): ?>
                            <div class="riwa-empty-state">
                                <span class="dashicons dashicons-money-alt"></span>
                                <h3>Aucun tarif configuré</h3>
                                <p>Commencez par ajouter vos premiers tarifs saisonniers.</p>
                            </div>
                        <?php else: ?>
                            <div class="riwa-pricing-grid">
                                <?php foreach ($pricing_data as $price): ?>
                                    <div class="riwa-pricing-card">
                                        <div class="riwa-pricing-header">
                                            <h4><?php echo esc_html($price->season_name); ?></h4>
                                            <span class="riwa-pricing-status <?php echo $price->is_active ? 'active' : 'inactive'; ?>">
                                                <?php echo $price->is_active ? 'Actif' : 'Inactif'; ?>
                                            </span>
                                        </div>
                                        <div class="riwa-pricing-details">
                                            <div class="riwa-pricing-price">
                                                <span class="price-amount"><?php echo esc_html($price->price_per_night); ?> €</span>
                                                <span class="price-unit">/nuit</span>
                                            </div>
                                            <div class="riwa-pricing-dates">
                                                <span class="date-start"><?php echo date('d/m/Y', strtotime($price->start_date)); ?></span>
                                                <span class="date-separator">→</span>
                                                <span class="date-end"><?php echo date('d/m/Y', strtotime($price->end_date)); ?></span>
                                            </div>
                                        </div>
                                        <div class="riwa-pricing-actions">
                                            <button type="button" class="riwa-btn riwa-btn-secondary button-small edit-pricing-btn" 
                                                    data-price-id="<?php echo esc_attr($price->id); ?>">
                                                Modifier
                                            </button>
                                            <button type="button" class="riwa-btn riwa-btn-danger button-small delete-pricing-btn"
                                                    data-price-id="<?php echo esc_attr($price->id); ?>"
                                                    data-price-name="<?php echo esc_attr($price->season_name); ?>">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Personnaliser PDF -->
            <div class="riwa-section" id="pdf-section">
                <div class="riwa-section-header">
                    <h2>Personnalisation du PDF</h2>
                    <p>Configurez l'apparence de vos documents PDF</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <div class="riwa-pdf-settings">
                            <div class="riwa-setting-group">
                                <h3>En-tête du document</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="pdf-title">Titre du document</label>
                                        <input type="text" id="pdf-title" name="pdf_title" value="Confirmation de réservation" class="riwa-input">
                                    </div>
                                    <div class="riwa-form-group">
                                        <label for="pdf-subtitle">Sous-titre</label>
                                        <input type="text" id="pdf-subtitle" name="pdf_subtitle" value="Merci pour votre réservation" class="riwa-input">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-group">
                                <h3>Informations de contact</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="pdf-company">Nom de l'établissement</label>
                                        <input type="text" id="pdf-company" name="pdf_company" value="Votre établissement" class="riwa-input">
                                    </div>
                                    <div class="riwa-form-group">
                                        <label for="pdf-address">Adresse</label>
                                        <textarea id="pdf-address" name="pdf_address" class="riwa-textarea" rows="3">123 Rue de la Paix&#10;75001 Paris, France</textarea>
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="pdf-phone">Téléphone</label>
                                        <input type="text" id="pdf-phone" name="pdf_phone" value="+33 1 23 45 67 89" class="riwa-input">
                                    </div>
                                    <div class="riwa-form-group">
                                        <label for="pdf-email">Email</label>
                                        <input type="email" id="pdf-email" name="pdf_email" value="contact@votre-etablissement.fr" class="riwa-input">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-group">
                                <h3>Pied de page</h3>
                                <div class="riwa-form-group">
                                    <label for="pdf-footer">Texte du pied de page</label>
                                    <textarea id="pdf-footer" name="pdf_footer" class="riwa-textarea" rows="2">Merci de votre confiance. Pour toute question, n'hésitez pas à nous contacter.</textarea>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-actions">
                                <button type="button" class="riwa-btn riwa-btn-primary" id="save-pdf-settings">
                                    <span class="dashicons dashicons-saved"></span>
                                    Enregistrer les paramètres
                                </button>
                                <button type="button" class="riwa-btn riwa-btn-secondary" id="preview-pdf">
                                    <span class="dashicons dashicons-visibility"></span>
                                    Aperçu PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Configuration Email -->
            <div class="riwa-section" id="email-section">
                <div class="riwa-section-header">
                    <h2>Configuration des Emails</h2>
                    <p>Configurez les notifications par email</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <?php
                        // Récupérer les valeurs actuelles
                        $notification_enabled = get_option('riwa_email_notification_enabled', 1);
                        $admin_email = get_option('riwa_email_admin_address', get_option('admin_email'));
                        $from_name = get_option('riwa_email_from_name', 'Riwa Villa');
                        $from_address = get_option('riwa_email_from_address', 'noreply@riwa-villa.com');
                        $client_subject = get_option('riwa_email_client_subject', 'Confirmation de votre réservation - Riwa');
                        $admin_subject = get_option('riwa_email_admin_subject', 'Nouvelle réservation - Riwa Villa');
                        $client_message = get_option('riwa_email_client_message', "Bonjour {guest_name},\n\nNous avons bien reçu votre réservation pour les dates suivantes :\nArrivée : {check_in}\nDépart : {check_out}\n\nNous vous contacterons bientôt pour confirmer votre réservation.\n\nCordialement,\nL'équipe Riwa");
                        $admin_message = get_option('riwa_email_admin_message', "Une nouvelle réservation a été effectuée sur le site.\n\nDétails de la réservation :\nNom : {guest_name}\nEmail : {guest_email}\nTéléphone : {guest_phone}\nDate d'arrivée : {check_in}\nDate de départ : {check_out}\nNombre d'adultes : {adults_count}\nNombre d'enfants : {children_count}\nNombre de bébés : {babies_count}\n\nDemandes spéciales : {special_requests}\n\nConnectez-vous à l'administration pour gérer cette réservation.\nLien d'administration : {admin_url}\n\nCordialement,\nSystème de réservation Riwa");
                        ?>
                        
                        <form method="post" action="">
                            <?php wp_nonce_field('riwa_email_settings', 'riwa_email_nonce'); ?>
                            
                            <div class="riwa-setting-group">
                                <h3>Paramètres généraux</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="notification_enabled">
                                            <input type="checkbox" id="notification_enabled" name="notification_enabled" value="1" <?php checked($notification_enabled, 1); ?>>
                                            Activer les notifications par email
                                        </label>
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="admin_email">Email administrateur</label>
                                        <input type="email" id="admin_email" name="admin_email" value="<?php echo esc_attr($admin_email); ?>" class="riwa-input">
                                        <p class="riwa-help-text">Email qui recevra les notifications de nouvelles réservations</p>
                                    </div>
                                    <div class="riwa-form-group">
                                        <label for="from_name">Nom de l'expéditeur</label>
                                        <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($from_name); ?>" class="riwa-input">
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="from_address">Email de l'expéditeur</label>
                                        <input type="email" id="from_address" name="from_address" value="<?php echo esc_attr($from_address); ?>" class="riwa-input">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-group">
                                <h3>Email de confirmation client</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="client_subject">Objet de l'email</label>
                                        <input type="text" id="client_subject" name="client_subject" value="<?php echo esc_attr($client_subject); ?>" class="riwa-input">
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="client_message">Message</label>
                                        <textarea id="client_message" name="client_message" rows="8" class="riwa-textarea"><?php echo esc_textarea($client_message); ?></textarea>
                                        <p class="riwa-help-text">Variables disponibles : {guest_name}, {check_in}, {check_out}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-group">
                                <h3>Email de notification administrateur</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="admin_subject">Objet de l'email</label>
                                        <input type="text" id="admin_subject" name="admin_subject" value="<?php echo esc_attr($admin_subject); ?>" class="riwa-input">
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="admin_message">Message</label>
                                        <textarea id="admin_message" name="admin_message" rows="12" class="riwa-textarea"><?php echo esc_textarea($admin_message); ?></textarea>
                                        <p class="riwa-help-text">Variables disponibles : {guest_name}, {guest_email}, {guest_phone}, {check_in}, {check_out}, {adults_count}, {children_count}, {babies_count}, {special_requests}, {admin_url}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-group">
                                <h3>Test d'envoi</h3>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <label for="test_email">Email de test</label>
                                        <input type="email" id="test_email" name="test_email" class="riwa-input" placeholder="votre-email@exemple.com">
                                    </div>
                                </div>
                                <div class="riwa-form-row">
                                    <div class="riwa-form-group">
                                        <button type="button" id="test_client_email" class="riwa-btn riwa-btn-secondary">
                                            <span class="dashicons dashicons-email-alt"></span>
                                            Tester email client
                                        </button>
                                        <button type="button" id="test_admin_email" class="riwa-btn riwa-btn-secondary">
                                            <span class="dashicons dashicons-email-alt"></span>
                                            Tester email admin
                                        </button>
                                        <span id="test_result" style="margin-left: 10px; font-weight: bold;"></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="riwa-setting-actions">
                                <button type="submit" name="save_email_settings" class="riwa-btn riwa-btn-primary">
                                    <span class="dashicons dashicons-saved"></span>
                                    Sauvegarder la configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Section Diagnostic -->
            <div class="riwa-section" id="debug-section">
                <div class="riwa-section-header">
                    <h2>Informations de diagnostic</h2>
                    <p>Informations techniques et de débogage</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <h3>Données de tarification</h3>
                        <?php
                        // Récupérer les données de tarification
                        $pricing_table = $wpdb->prefix . 'riwa_pricing';
                        $pricing_data = $wpdb->get_results("SELECT * FROM $pricing_table WHERE is_active = 1 ORDER BY start_date ASC");
                        ?>
                        <p><strong>Données de tarification disponibles :</strong></p>
                        <?php if (empty($pricing_data)): ?>
                            <p style="color: #d63638;">Aucune donnée de tarification trouvée !</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($pricing_data as $price): ?>
                                    <li>
                                        <strong><?php echo esc_html($price->season_name); ?></strong> : 
                                        <?php echo esc_html($price->price_per_night); ?> €/nuit 
                                        (du <?php echo esc_html(date('d/m/Y', strtotime($price->start_date))); ?> 
                                        au <?php echo esc_html(date('d/m/Y', strtotime($price->end_date))); ?>)
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <h3>Informations système</h3>
                        <p><strong>Mode debug WordPress :</strong> <?php echo WP_DEBUG ? 'Activé' : 'Désactivé'; ?></p>
                        <p><strong>Version du plugin :</strong> <?php echo RIWA_BOOKING_VERSION; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Popup des détails de réservation -->
<div id="riwa-details-popup" class="riwa-popup-overlay">
    <div class="riwa-popup-container">
        <div class="riwa-popup-header">
            <h3>Détails de la réservation</h3>
            <button type="button" class="riwa-popup-close" id="riwa-popup-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="riwa-popup-content">
            <div class="riwa-popup-section">
                <h4>Informations de réservation</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Référence</span>
                        <span class="riwa-popup-value" id="popup-reference"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">ID</span>
                        <span class="riwa-popup-value" id="popup-booking-id"></span>
                    </div>
                </div>
            </div>
            
            <div class="riwa-popup-section">
                <h4>Informations client</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Nom</span>
                        <span class="riwa-popup-value" id="popup-client-name"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Email</span>
                        <span class="riwa-popup-value" id="popup-client-email"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Téléphone</span>
                        <span class="riwa-popup-value" id="popup-client-phone"></span>
                    </div>
                </div>
            </div>
            
            <div class="riwa-popup-section">
                <h4>Détails du séjour</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date d'arrivée</span>
                        <span class="riwa-popup-value" id="popup-checkin"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date de départ</span>
                        <span class="riwa-popup-value" id="popup-checkout"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Durée</span>
                        <span class="riwa-popup-value" id="popup-duration"></span>
                    </div>
                </div>
            </div>
            
            <div class="riwa-popup-section">
                <h4>Composition des voyageurs</h4>
                <div class="riwa-popup-travelers" id="popup-travelers">
                    <!-- Le contenu sera rempli par JavaScript -->
                </div>
            </div>
            
            <div class="riwa-popup-section">
                <h4>Informations tarifaires</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Prix total</span>
                        <span class="riwa-popup-value" id="popup-total-price"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Prix par nuit</span>
                        <span class="riwa-popup-value" id="popup-price-per-night"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Statut</span>
                        <span class="riwa-popup-value" id="popup-status"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date de création</span>
                        <span class="riwa-popup-value" id="popup-created"></span>
                    </div>
                </div>
            </div>
            
            <div class="riwa-popup-section">
                <h4>Demandes spéciales</h4>
                <div class="riwa-popup-requests" id="popup-requests">
                    <!-- Le contenu sera rempli par JavaScript -->
                </div>
            </div>
        </div>
        <div class="riwa-popup-footer">
            <button type="button" class="riwa-btn riwa-btn-secondary" id="riwa-popup-close-btn">Fermer</button>
        </div>
    </div>
</div>

<style>
/* Design moderne et minimaliste - Copié exactement du panel PDF */
.riwa-pdf-admin {
    background: #f8f9fa;
    min-height: 100vh;
    margin: -20px -20px 0 -20px;
    padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

/* En-tête principal */
.riwa-admin-header {
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e1e5e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.riwa-header-content h1 {
    margin: 0 0 0.25rem 0;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-subtitle {
    margin: 0;
    color: #646970;
    font-size: 14px;
    font-weight: 400;
}

.riwa-header-actions {
    display: flex;
    gap: 0.75rem;
}

/* Container principal */
.riwa-pdf-admin-container {
    display: flex;
    height: calc(100vh - 100px);
}

/* Panneau de navigation */
.riwa-nav-panel {
    width: 225px;
    background: white;
    border-right: 1px solid #e1e5e9;
    display: flex;
    flex-direction: column;
}

.riwa-nav-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e1e5e9;
}

.riwa-nav-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-nav-menu {
    flex: 1;
    padding: 1rem 0;
}

.riwa-nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.875rem 1.5rem;
    color: #646970;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.riwa-nav-item:hover {
    background: #f6f7f7;
    color: #1d2327;
    border-left-color: #2271b1;
}

.riwa-nav-item.active {
    background: #f0f6fc;
    color: #2271b1;
    border-left-color: #2271b1;
}

.riwa-nav-item .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* Panneau de contenu */
.riwa-content-panel {
    flex: 1;
    overflow-y: auto;
    background: #f8f9fa;
}

.riwa-section {
    display: none;
    padding: 2rem;
}

.riwa-section.active {
    display: block !important;
}

.riwa-section-header {
    margin-bottom: 2rem;
}

.riwa-section-header h2 {
    margin: 0 0 0.5rem 0;
    font-size: 20px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-section-header p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.riwa-section-content {
    background: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    overflow: hidden;
}

/* Grille de formulaire - Utilisé pour les statistiques */
.riwa-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.riwa-form-group {
    display: flex;
    flex-direction: column;
    text-align: center;
    padding: 2rem;
    background: #f8f9fa;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.riwa-form-group:hover {
    background: #f0f0f1;
}

.riwa-form-group h3 {
    font-size: 12px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 0 0 1rem 0;
    color: #646970;
}

.stat-number {
    font-size: 36px;
    font-weight: 200;
    color: #1d2327;
    display: block;
    letter-spacing: -1px;
}

.stat-number.pending {
    color: #996800;
}

.stat-number.confirmed {
    color: #008a20;
}

.stat-number.cancelled {
    color: #d63638;
}

/* Aperçu - Utilisé pour le contenu des sections */
.riwa-preview-container {
    padding: 2rem;
}

.riwa-preview-container h3 {
    margin: 0 0 1rem 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-preview-container p {
    margin: 0 0 0.5rem 0;
    color: #646970;
}

.riwa-preview-container ul {
    margin: 0 0 1rem 0;
    padding-left: 1.5rem;
}

.riwa-preview-container li {
    margin-bottom: 0.25rem;
    color: #646970;
}

/* Tableau moderne et minimaliste */
.riwa-bookings-table {
    background: white;
    border-radius: 8px;
    overflow: hidden;
}

.riwa-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f1;
    background: #f8f9fa;
}

.riwa-table-count {
    font-size: 14px;
    font-weight: 500;
    color: #646970;
}

.riwa-table-wrapper {
    overflow-x: auto;
}

.riwa-modern-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.riwa-modern-table th {
    background: #f8f9fa;
    padding: 1rem 1.5rem;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #e1e5e9;
    white-space: nowrap;
}

.riwa-modern-table td {
    padding: 1rem 1rem;
    border-bottom: 1px solid #f0f0f1;
    vertical-align: middle;
    line-height: 1.4;
}

.riwa-booking-row:hover {
    background: #f8f9fa;
}

/* Container de prévisualisation */
.riwa-preview-container {
    padding: 0rem !important;
}

/* Colonnes spécifiques */
.riwa-th-id, .riwa-td-id {
    width: 60px;
    display: none; /* Masquer la colonne ID */
}

.riwa-th-reference, .riwa-td-reference {
    width: 120px;
}

.riwa-th-client, .riwa-td-client {
    width: 280px;
}

.riwa-th-price, .riwa-td-price {
    width: 120px;
}

.riwa-th-status, .riwa-td-status {
    width: 80px;
}

.riwa-th-date, .riwa-td-date {
    width: 120px;
}

.riwa-th-actions, .riwa-td-actions {
    width: 180px;
}

/* Contenu des cellules */
.riwa-booking-id {
    font-size: 12px;
    font-weight: 600;
    color: #646970;
    background: #f0f0f1;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: inline-block;
}

.riwa-booking-reference {
    font-size: 13px;
    font-weight: 600;
    color: #2271b1;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5px;
}

.riwa-client-name {
    font-weight: 600;
    color: #1d2327;
    font-size: 14px;
    margin-bottom: 0.25rem;
}

.riwa-client-contact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 12px;
}

.riwa-client-contact a {
    color: #2271b1;
    text-decoration: none;
}

.riwa-client-contact a:hover {
    text-decoration: underline;
}

.riwa-contact-separator {
    color: #646970;
    font-size: 10px;
}

.riwa-contact-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.riwa-contact-email a,
.riwa-contact-phone a {
    color: #2271b1;
    text-decoration: none;
    font-size: 13px;
}

.riwa-contact-email a:hover,
.riwa-contact-phone a:hover {
    text-decoration: underline;
}

.riwa-dates-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.riwa-dates-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 13px;
    color: #1d2327;
}

.riwa-date-checkin,
.riwa-date-checkout {
    font-weight: 500;
}

.riwa-date-arrow {
    color: #646970;
    font-size: 12px;
}

.riwa-date-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.riwa-date-label {
    font-size: 11px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.riwa-date-value {
    font-size: 13px;
    font-weight: 500;
    color: #1d2327;
}

.riwa-guests-count {
    font-size: 13px;
    color: #1d2327;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.riwa-guests-count .dashicons {
    font-size: 12px;
    width: 12px;
    height: 12px;
    color: #8c8f94;
}

.riwa-price-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.riwa-price-total {
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-price-per-night {
    font-size: 11px;
    color: #646970;
}

.riwa-price-unknown {
    font-size: 12px;
    color: #646970;
    font-style: normal;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.riwa-price-unknown .dashicons {
    font-size: 14px;
    width: 14px;
    height: 14px;
    color: #8c8f94;
}

.riwa-date-created {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.riwa-date-day {
    font-size: 13px;
    font-weight: 500;
    color: #1d2327;
}

.riwa-date-time {
    font-size: 11px;
    color: #646970;
}

.riwa-actions-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.riwa-actions-compact {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.riwa-status-select-compact {
    padding: 0.25rem 0.5rem;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    font-size: 11px;
    background: white;
    color: #1d2327;
    min-width: 80px;
}

.riwa-status-form {
    margin: 0;
}

.riwa-status-select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    font-size: 12px;
    background: white;
    color: #1d2327;
    min-width: 100px;
}

.riwa-delete-form {
    margin: 0;
}

.riwa-status-form {
    margin: 0;
}

.riwa-status-select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    font-size: 12px;
    background: white;
    color: #1d2327;
}

.riwa-status-select:focus {
    border-color: #2271b1;
    outline: none;
    box-shadow: 0 0 0 1px #2271b1;
}

/* Badges de statut modernisés */
.riwa-status-badge {
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: inline-block;
    text-align: center;
    min-width: 70px;
}

.riwa-status-pending {
    background: #fcf9e8;
    color: #996800;
}

.riwa-status-confirmed {
    background: #edfaef;
    color: #008a20;
}

.riwa-status-cancelled {
    background: #fcf0f1;
    color: #d63638;
}

/* Badges de statut pour le popup */
.riwa-status-badge.riwa-status-pending {
    background: #fcf9e8;
    color: #996800;
}

.riwa-status-badge.riwa-status-confirmed {
    background: #edfaef;
    color: #008a20;
}

.riwa-status-badge.riwa-status-cancelled {
    background: #fcf0f1;
    color: #d63638;
}

/* Détails des réservations */
.riwa-booking-details {
    background: #f8f9fa;
}

.riwa-booking-details td {
    padding: 0;
    border: none;
}

.riwa-details-content {
    padding: 1.5rem 2rem;
}

.riwa-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.riwa-details-section h4 {
    margin: 0 0 0.75rem 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.riwa-details-section p {
    margin: 0;
    font-size: 14px;
    color: #646970;
    line-height: 1.5;
}

.riwa-stay-info {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.riwa-stay-duration,
.riwa-stay-nights {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f1;
}

.riwa-stay-duration:last-child,
.riwa-stay-nights:last-child {
    border-bottom: none;
}

.riwa-stay-label {
    font-size: 13px;
    color: #646970;
}

.riwa-stay-value {
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
}

/* État vide */
.riwa-empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #646970;
}

.riwa-empty-state .dashicons {
    font-size: 48px;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.riwa-empty-state h3 {
    margin: 0 0 0.5rem 0;
    font-weight: 500;
    font-size: 16px;
}

.riwa-empty-state p {
    margin: 0;
    opacity: 0.7;
    font-size: 14px;
}



/* Boutons */
.riwa-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.75rem;
    border: 2px solid transparent;
    border-radius: 12px;
    background: white;
    color: #1d2327;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.riwa-btn:hover {
    background: #f6f7f7;
    border-color: #8c8f94;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.riwa-btn:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.riwa-btn-primary {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    border-color: #2271b1;
    color: white;
}

.riwa-btn-primary:hover {
    background: linear-gradient(135deg, #135e96 0%, #0d4b7a 100%);
    border-color: #135e96;
}

.riwa-btn-secondary {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-color: #dee2e6;
    color: #495057;
}

.riwa-btn-secondary:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    border-color: #adb5bd;
}

.riwa-btn-danger {
    background: #fcf0f1;
    border-color: #d63638;
    color: #d63638;
}

.riwa-btn-danger:hover {
    background: #d63638;
    color: white;
}

.riwa-btn.button-small {
    padding: 0.5rem 1rem;
    font-size: 12px;
}

/* Popup des détails */
.riwa-popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 999999;
    backdrop-filter: blur(4px);
}

.riwa-popup-overlay.show {
    display: flex;
    animation: riwa-fadeIn 0.3s ease;
}

.riwa-popup-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    animation: riwa-slideIn 0.3s ease;
}

.riwa-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #f0f0f1;
    background: #f8f9fa;
}

.riwa-popup-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-popup-close {
    background: none;
    border: none;
    color: #646970;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.riwa-popup-close:hover {
    background: #f0f0f1;
    color: #1d2327;
}

.riwa-popup-close .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.riwa-popup-content {
    padding: 2rem;
    max-height: 60vh;
    overflow-y: auto;
}

.riwa-popup-section {
    margin-bottom: 2rem;
}

.riwa-popup-section:last-child {
    margin-bottom: 0;
}

.riwa-popup-section h4 {
    margin: 0 0 1rem 0;
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 0.5rem;
}

.riwa-popup-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.riwa-popup-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.riwa-popup-label {
    font-size: 12px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.riwa-popup-value {
    font-size: 14px;
    color: #1d2327;
    font-weight: 500;
}

.riwa-popup-requests {
    background: #f8f9fa;
    border: 1px solid #f0f0f1;
    border-radius: 6px;
    padding: 1rem;
    font-size: 14px;
    color: #646970;
    line-height: 1.5;
    min-height: 60px;
    display: flex;
    align-items: center;
}

.riwa-popup-travelers {
    background: #f8f9fa;
    border: 1px solid #f0f0f1;
    border-radius: 6px;
    padding: 1rem;
    min-height: 60px;
}

.riwa-traveler-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f1;
}

.riwa-traveler-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.riwa-traveler-type {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: #1d2327;
}

.riwa-traveler-icon {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #2271b1;
}

.riwa-traveler-count {
    font-weight: 600;
    color: #2271b1;
    background: #f0f6fc;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 13px;
}

.riwa-popup-footer {
    padding: 1.5rem 2rem;
    border-top: 1px solid #f0f0f1;
    background: #f8f9fa;
    display: flex;
    justify-content: flex-end;
}

/* Animations */
@keyframes riwa-fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes riwa-slideIn {
    from { 
        opacity: 0;
        transform: translateY(-20px) scale(0.95);
    }
    to { 
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* Empêcher le défilement quand le popup est ouvert */
body.riwa-popup-open {
    overflow: hidden;
}

/* Responsive pour le popup */
@media (max-width: 768px) {
    .riwa-popup-container {
        width: 95%;
        margin: 1rem;
    }
    
    .riwa-popup-header,
    .riwa-popup-content,
    .riwa-popup-footer {
        padding: 1rem;
    }
    
    .riwa-popup-grid {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
}

/* Styles pour la section Tarification */
.riwa-pricing-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.riwa-pricing-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-pricing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.riwa-pricing-card {
    background: white;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 1.5rem;
    transition: all 0.2s ease;
}

.riwa-pricing-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 8px rgba(34, 113, 177, 0.1);
}

.riwa-pricing-card .riwa-pricing-header {
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f1;
}

.riwa-pricing-card .riwa-pricing-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.riwa-pricing-status {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.riwa-pricing-status.active {
    background: #edfaef;
    color: #008a20;
}

.riwa-pricing-status.inactive {
    background: #fcf0f1;
    color: #d63638;
}

.riwa-pricing-details {
    margin-bottom: 1.5rem;
}

.riwa-pricing-price {
    margin-bottom: 1rem;
}

.price-amount {
    font-size: 24px;
    font-weight: 700;
    color: #1d2327;
}

.price-unit {
    font-size: 14px;
    color: #646970;
    font-weight: 400;
}

.riwa-pricing-dates {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 13px;
    color: #646970;
}

.date-separator {
    color: #8c8f94;
}

.riwa-pricing-actions {
    display: flex;
    gap: 0.5rem;
}

/* Styles pour la section PDF */
.riwa-pdf-settings {
    max-width: 800px;
}

.riwa-setting-group {
    margin-bottom: 2.5rem;
    padding: 2rem;
    border: 1px solid #e1e5e9;
    border-radius: 16px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.riwa-setting-group:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.riwa-setting-group:last-child {
    margin-bottom: 0;
}

.riwa-setting-group h3 {
    margin: 0 0 1.5rem 0;
    font-size: 18px;
    font-weight: 700;
    color: #1d2327;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e1e5e9;
    position: relative;
}

.riwa-setting-group h3::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 50px;
    height: 2px;
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    border-radius: 1px;
}

.riwa-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1rem;
}

.riwa-form-row:last-child {
    margin-bottom: 0;
}

.riwa-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}

.riwa-form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #1d2327;
    margin-bottom: 0.375rem;
    display: block;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.riwa-input,
.riwa-textarea {
    position: relative !important;
    padding: 0.625rem 0.875rem !important;
    border: 1px solid #dcdcde !important;
    border-radius: 6px !important;
    font-size: 13px !important;
    color: #1d2327 !important;
    background: white !important;
    transition: all 0.2s ease !important;
    width: 100% !important;
    box-sizing: border-box !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    margin: 0 !important;
    outline: none !important;
    min-height: 36px !important;
}

.riwa-input:hover,
.riwa-textarea:hover {
    border-color: #2271b1 !important;
    background-color: #f8f9fa !important;
}

.riwa-input:focus,
.riwa-textarea:focus {
    border-color: #2271b1 !important;
    outline: none !important;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1) !important;
    transform: translateY(-1px) !important;
    background-color: #f8f9fa !important;
}

.riwa-input::placeholder,
.riwa-textarea::placeholder {
    color: #8c8f94 !important;
    font-style: italic !important;
    opacity: 0.7 !important;
}

.riwa-textarea {
    resize: vertical;
    min-height: 80px;
    padding: 0.625rem 0.875rem;
    background-image: none;
}

/* Style pour les checkboxes */
.riwa-form-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-right: 0.75rem;
    accent-color: #2271b1;
    border-radius: 4px;
    border: 2px solid #e1e5e9;
    transition: all 0.3s ease;
}

.riwa-form-group input[type="checkbox"]:checked {
    background-color: #2271b1;
    border-color: #2271b1;
}

.riwa-form-group input[type="checkbox"]:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
}

.riwa-form-group label[for*="notification"] {
    display: flex;
    align-items: center;
    font-weight: 500;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.riwa-form-group label[for*="notification"]:hover {
    background-color: #f8f9fa;
}

.riwa-setting-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    border: 1px solid #dee2e6;
    box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* Responsive pour les nouvelles sections */
@media (max-width: 768px) {
    .riwa-pricing-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .riwa-form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .riwa-setting-actions {
        flex-direction: column;
    }
}

/* Sélecteur */
.riwa-select {
    padding: 0.5rem;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    font-size: 12px;
    background: white;
}

/* Affichage des prix */
.price-display {
    font-weight: 600;
    color: #1d2327;
}

/* Responsive */
@media (max-width: 1200px) {
    .riwa-pdf-admin-container {
        flex-direction: column;
    }
    
    .riwa-nav-panel {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .riwa-nav-menu {
        display: flex;
        overflow-x: auto;
        padding: 1rem;
    }
    
    .riwa-nav-item {
        flex-shrink: 0;
        border-left: none;
    }
}

/* Styles pour les tests d'email */
#test_result {
    font-weight: 600;
    padding: 0.75rem 1.25rem;
    border-radius: 8px;
    margin-left: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 14px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

#test_result.success {
    color: #155724;
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border: 1px solid #46b450;
}

#test_result.success::before {
    content: '✓';
    font-weight: bold;
    color: #46b450;
}

#test_result.error {
    color: #721c24;
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
    border: 1px solid #dc3232;
}

#test_result.error::before {
    content: '✗';
    font-weight: bold;
    color: #dc3232;
}

.riwa-help-text {
    font-size: 13px;
    color: #6c757d;
    margin-top: 0.5rem;
    font-style: italic;
    padding: 0.5rem 0.75rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 6px;
    border-left: 3px solid #2271b1;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}
        border-bottom: 3px solid transparent;
        padding: 0.75rem 1rem;
    }
    
    .riwa-nav-item.active {
        border-bottom-color: #2271b1;
    }
}

@media (max-width: 768px) {
    .riwa-admin-header {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
    
    .riwa-header-actions {
        width: 100%;
        justify-content: flex-end;
    }
    
    .riwa-form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .riwa-section {
        padding: 1rem;
    }
    
    .riwa-preview-container {
        padding: 1rem;
        overflow-x: auto;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Navigation entre les panneaux
    $('.riwa-nav-item').on('click', function(e) {
        e.preventDefault();
        
        var targetSection = $(this).data('section');
                    // Navigation vers la section
        
        // Mettre à jour la navigation active
        $('.riwa-nav-item').removeClass('active');
        $(this).addClass('active');
        
        // Masquer toutes les sections
        $('.riwa-section').removeClass('active').hide();
        
        // Afficher la section correspondante
        var targetElement = $('#' + targetSection + '-section');
                    // Élément cible trouvé
        
        if (targetElement.length > 0) {
            targetElement.addClass('active').show();
        }
        
        // Mettre à jour l'URL sans recharger la page
        if (history.pushState) {
            history.pushState(null, null, '#' + targetSection);
        }
    });
    
    // Gestion de l'historique du navigateur
    $(window).on('popstate', function() {
        var hash = window.location.hash.substring(1) || 'overview';
        $('.riwa-nav-item[data-section="' + hash + '"]').click();
    });
    
    // Initialiser la section active depuis l'URL
    var initialSection = window.location.hash.substring(1) || 'overview';
                // Section initiale chargée
    
    // S'assurer que toutes les sections sont masquées au départ
    $('.riwa-section').removeClass('active').hide();
    
    // Afficher la section initiale
    var initialElement = $('#' + initialSection + '-section');
    if (initialElement.length > 0) {
        initialElement.addClass('active').show();
        $('.riwa-nav-item[data-section="' + initialSection + '"]').addClass('active');
    } else {
        // Fallback vers overview si la section n'existe pas
        $('#overview-section').addClass('active').show();
        $('.riwa-nav-item[data-section="overview"]').addClass('active');
    }
    
    // Gestion du popup des détails
    $('.view-details-popup').on('click', function() {
        var $btn = $(this);
        var bookingId = $btn.data('booking-id');
        var bookingName = $btn.data('booking-name');
        var bookingEmail = $btn.data('booking-email');
        var bookingPhone = $btn.data('booking-phone');
        var bookingCheckin = $btn.data('booking-checkin');
        var bookingCheckout = $btn.data('booking-checkout');
        var bookingGuests = $btn.data('booking-guests');
        var bookingAdults = $btn.data('booking-adults');
        var bookingChildren = $btn.data('booking-children');
        var bookingBabies = $btn.data('booking-babies');

        var bookingPrice = $btn.data('booking-price');
        var bookingPricePerNight = $btn.data('booking-price-per-night');
        var bookingStatus = $btn.data('booking-status');
        var bookingCreated = $btn.data('booking-created');
        var bookingRequests = $btn.data('booking-requests');
        
        // Calculer la durée du séjour
        var checkin = new Date(bookingCheckin);
        var checkout = new Date(bookingCheckout);
        var duration = Math.ceil((checkout - checkin) / (1000 * 60 * 60 * 24));
        
        // Formater les dates
        var formatDate = function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        };
        
        var formatDateTime = function(dateString) {
            var date = new Date(dateString);
            return date.toLocaleDateString('fr-FR') + ' à ' + date.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
        };
        
        // Remplir le popup avec les données
        $('#popup-reference').text('RIWA-' + String(bookingId).padStart(6, '0'));
        $('#popup-booking-id').text('#' + bookingId);
        $('#popup-client-name').text(bookingName);
        $('#popup-client-email').html('<a href="mailto:' + bookingEmail + '">' + bookingEmail + '</a>');
        $('#popup-client-phone').html('<a href="tel:' + bookingPhone + '">' + bookingPhone + '</a>');
        $('#popup-checkin').text(formatDate(bookingCheckin));
        $('#popup-checkout').text(formatDate(bookingCheckout));
        $('#popup-duration').text(duration + ' nuit' + (duration > 1 ? 's' : ''));
        
        // Afficher les détails des voyageurs
        var travelersHtml = '';
        
        if (bookingAdults > 0) {
            travelersHtml += '<div class="riwa-traveler-item">' +
                '<div class="riwa-traveler-type">' +
                '<span class="dashicons dashicons-admin-users riwa-traveler-icon"></span>' +
                'Adulte(s)' +
                '</div>' +
                '<span class="riwa-traveler-count">' + bookingAdults + '</span>' +
                '</div>';
        }
        
        if (bookingChildren > 0) {
            travelersHtml += '<div class="riwa-traveler-item">' +
                '<div class="riwa-traveler-type">' +
                '<span class="dashicons dashicons-admin-users riwa-traveler-icon"></span>' +
                'Enfant(s)' +
                '</div>' +
                '<span class="riwa-traveler-count">' + bookingChildren + '</span>' +
                '</div>';
        }
        
        if (bookingBabies > 0) {
            travelersHtml += '<div class="riwa-traveler-item">' +
                '<div class="riwa-traveler-type">' +
                '<span class="dashicons dashicons-admin-users riwa-traveler-icon"></span>' +
                'Bébé(s)' +
                '</div>' +
                '<span class="riwa-traveler-count">' + bookingBabies + '</span>' +
                '</div>';
        }
        

        
        if (travelersHtml === '') {
            travelersHtml = '<div class="riwa-traveler-item">' +
                '<div class="riwa-traveler-type">' +
                '<span class="dashicons dashicons-minus riwa-traveler-icon"></span>' +
                'Aucun détail disponible' +
                '</div>' +
                '</div>';
        }
        
        $('#popup-travelers').html(travelersHtml);
        
        if (bookingPrice > 0) {
            $('#popup-total-price').text(parseFloat(bookingPrice).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €');
            $('#popup-price-per-night').text(parseFloat(bookingPricePerNight).toLocaleString('fr-FR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' €');
        } else {
            $('#popup-total-price').text('Non calculé');
            $('#popup-price-per-night').text('Non calculé');
        }
        
        // Statut avec badge
        var statusText = '';
        switch(bookingStatus) {
            case 'pending': statusText = '<span class="riwa-status-badge riwa-status-pending">En attente</span>'; break;
            case 'confirmed': statusText = '<span class="riwa-status-badge riwa-status-confirmed">Confirmée</span>'; break;
            case 'cancelled': statusText = '<span class="riwa-status-badge riwa-status-cancelled">Annulée</span>'; break;
            default: statusText = bookingStatus;
        }
        $('#popup-status').html(statusText);
        
        $('#popup-created').text(formatDateTime(bookingCreated));
        
        // Demandes spéciales
        if (bookingRequests && bookingRequests.trim() !== '') {
            $('#popup-requests').text(bookingRequests);
        } else {
            $('#popup-requests').text('Aucune demande spéciale');
        }
        
        // Afficher le popup
        $('#riwa-details-popup').addClass('show');
        $('body').addClass('riwa-popup-open');
    });
    
    // Fermer le popup
    $('#riwa-popup-close, #riwa-popup-close-btn').on('click', function() {
        $('#riwa-details-popup').removeClass('show');
        $('body').removeClass('riwa-popup-open');
    });
    
    // Fermer le popup en cliquant sur l'overlay
    $('#riwa-details-popup').on('click', function(e) {
        if (e.target === this) {
            $(this).removeClass('show');
            $('body').removeClass('riwa-popup-open');
        }
    });
    
    // Fermer le popup avec la touche Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#riwa-details-popup').hasClass('show')) {
            $('#riwa-details-popup').removeClass('show');
            $('body').removeClass('riwa-popup-open');
        }
    });
    
    // Gestion de la suppression des réservations
    $('.delete-booking-btn').on('click', function() {
        var bookingId = $(this).data('booking-id');
        var bookingName = $(this).data('booking-name');
        
        if (confirm('Êtes-vous sûr de vouloir supprimer la réservation de "' + bookingName + '" ? Cette action est irréversible.')) {
            // Créer un formulaire temporaire pour la suppression
            var form = $('<form method="post"></form>');
            form.append('<input type="hidden" name="action" value="delete_booking">');
            form.append('<input type="hidden" name="booking_id" value="' + bookingId + '">');
            form.append('<?php echo wp_nonce_field("delete_booking_nonce", "delete_nonce", true, false); ?>');
            $('body').append(form);
            form.submit();
        }
    });
    
    // Actualiser les réservations
    $('#refresh-bookings').on('click', function() {
        location.reload();
    });
    
    // Exporter les réservations
    $('#export-bookings').on('click', function() {
        alert('Fonctionnalité d\'export à implémenter');
    });
    
    // Gestion de la tarification
    $('#add-pricing-btn').on('click', function() {
        alert('Fonctionnalité d\'ajout de tarif à implémenter');
    });
    
    $('.edit-pricing-btn').on('click', function() {
        var priceId = $(this).data('price-id');
        alert('Modifier le tarif #' + priceId + ' - Fonctionnalité à implémenter');
    });
    
    $('.delete-pricing-btn').on('click', function() {
        var priceId = $(this).data('price-id');
        var priceName = $(this).data('price-name');
        
        if (confirm('Êtes-vous sûr de vouloir supprimer le tarif "' + priceName + '" ?')) {
            alert('Supprimer le tarif #' + priceId + ' - Fonctionnalité à implémenter');
        }
    });
    
    // Gestion des paramètres PDF
    $('#save-pdf-settings').on('click', function() {
        alert('Paramètres PDF enregistrés ! (Fonctionnalité à implémenter)');
    });
    
    $('#preview-pdf').on('click', function() {
        alert('Aperçu PDF - Fonctionnalité à implémenter');
    });
    
    // Test email client
    $('#test_client_email').on('click', function() {
        var testEmail = $('#test_email').val();
        if (!testEmail) {
            alert('Veuillez saisir un email de test');
            return;
        }
        
        $('#test_result').html('Envoi en cours...').removeClass('success error');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'riwa_test_client_email',
                email: testEmail,
                nonce: '<?php echo wp_create_nonce('riwa_test_email'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#test_result').html('✅ Email client envoyé avec succès !').addClass('success');
                } else {
                    $('#test_result').html('❌ Erreur : ' + response.data).addClass('error');
                }
            },
            error: function() {
                $('#test_result').html('❌ Erreur lors de l\'envoi').addClass('error');
            }
        });
    });
    
    // Test email admin
    $('#test_admin_email').on('click', function() {
        var testEmail = $('#test_email').val();
        if (!testEmail) {
            alert('Veuillez saisir un email de test');
            return;
        }
        
        $('#test_result').html('Envoi en cours...').removeClass('success error');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'riwa_test_admin_email',
                email: testEmail,
                nonce: '<?php echo wp_create_nonce('riwa_test_email'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#test_result').html('✅ Email admin envoyé avec succès !').addClass('success');
                } else {
                    $('#test_result').html('❌ Erreur : ' + response.data).addClass('error');
                }
            },
            error: function() {
                $('#test_result').html('❌ Erreur lors de l\'envoi').addClass('error');
            }
        });
    });
});

// Fonction pour mettre à jour le statut d'une réservation
function updateBookingStatus(bookingId, newStatus) {
    if (newStatus === '') return;
    
    // Créer un formulaire temporaire pour la mise à jour
    var form = jQuery('<form method="post"></form>');
    form.append('<input type="hidden" name="action" value="update_status">');
    form.append('<input type="hidden" name="booking_id" value="' + bookingId + '">');
    form.append('<input type="hidden" name="new_status" value="' + newStatus + '">');
    jQuery('body').append(form);
    form.submit();
}
</script> 
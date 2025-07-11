<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Récupération des réservations
global $wpdb;
$table_name = $wpdb->prefix . 'riwa_bookings';

// Gestion des actions (changer le statut)
if (isset($_POST['action']) && isset($_POST['booking_id'])) {
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

// Récupération de toutes les réservations
$bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
?>

<div class="wrap riwa-admin-container">
    <h1>Réservations Riwa</h1>
    
    <div class="riwa-stats">
        <div class="stat-box">
            <h3>Total des réservations</h3>
            <span class="stat-number"><?php echo count($bookings); ?></span>
        </div>
        <div class="stat-box">
            <h3>En attente</h3>
            <span class="stat-number pending"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'pending'; })); ?></span>
        </div>
        <div class="stat-box">
            <h3>Confirmées</h3>
            <span class="stat-number confirmed"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'confirmed'; })); ?></span>
        </div>
        <div class="stat-box">
            <h3>Annulées</h3>
            <span class="stat-number cancelled"><?php echo count(array_filter($bookings, function($b) { return $b->status === 'cancelled'; })); ?></span>
        </div>
    </div>

    <!-- Section de débogage -->
    <div class="riwa-debug-section" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h3>Informations de débogage</h3>
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
        
        <p><strong>Mode debug WordPress :</strong> <?php echo WP_DEBUG ? 'Activé' : 'Désactivé'; ?></p>
        <p><strong>Version du plugin :</strong> <?php echo RIWA_BOOKING_VERSION; ?></p>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Client</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Arrivée</th>
                <th>Départ</th>
                <th>Invités</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Date de réservation</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="10">Aucune réservation trouvée.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?php echo esc_html($booking->id); ?></td>
                        <td><strong><?php echo esc_html($booking->guest_name); ?></strong></td>
                        <td><a href="mailto:<?php echo esc_attr($booking->guest_email); ?>"><?php echo esc_html($booking->guest_email); ?></a></td>
                        <td><a href="tel:<?php echo esc_attr($booking->guest_phone); ?>"><?php echo esc_html($booking->guest_phone); ?></a></td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in_date))); ?></td>
                        <td><?php echo esc_html(date('d/m/Y', strtotime($booking->check_out_date))); ?></td>
                        <td><?php echo esc_html($booking->guests_count); ?></td>
                        <td>
                            <?php if ($booking->total_price > 0): ?>
                                <span class="price-display">
                                    <?php echo number_format($booking->total_price, 2, ',', ' '); ?> €
                                </span>
                                <br>
                                <small style="color: #666;">
                                    (<?php echo number_format($booking->price_per_night, 2, ',', ' '); ?> €/nuit)
                                </small>
                            <?php else: ?>
                                <span style="color: #999;">Non calculé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo esc_attr($booking->status); ?>">
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
                        <td><?php echo esc_html(date('d/m/Y H:i', strtotime($booking->created_at))); ?></td>
                        <td>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                                <select name="new_status" onchange="this.form.submit()">
                                    <option value="">Changer statut</option>
                                    <option value="pending" <?php selected($booking->status, 'pending'); ?>>En attente</option>
                                    <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>Confirmée</option>
                                    <option value="cancelled" <?php selected($booking->status, 'cancelled'); ?>>Annulée</option>
                                </select>
                            </form>
                            
                            <button type="button" class="button button-small view-details" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                                Détails
                            </button>
                        </td>
                    </tr>
                    
                    <!-- Ligne des détails (cachée par défaut) -->
                    <tr class="booking-details" id="details-<?php echo esc_attr($booking->id); ?>" style="display: none;">
                        <td colspan="10">
                            <div class="booking-details-content">
                                <h4>Demandes spéciales :</h4>
                                <p><?php echo empty($booking->special_requests) ? 'Aucune demande spéciale' : esc_html($booking->special_requests); ?></p>
                                
                                <div class="booking-duration">
                                    <strong>Durée du séjour :</strong> 
                                    <?php 
                                    $checkin = new DateTime($booking->check_in_date);
                                    $checkout = new DateTime($booking->check_out_date);
                                    $interval = $checkin->diff($checkout);
                                    echo $interval->days . ' nuit' . ($interval->days > 1 ? 's' : '');
                                    ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div> 
<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$pricing_table = $wpdb->prefix . 'riwa_pricing';

// Gestion des actions
if (isset($_POST['action'])) {
    $action = sanitize_text_field($_POST['action']);
    
    if ($action === 'add_pricing' && wp_verify_nonce($_POST['pricing_nonce'], 'riwa_pricing_nonce')) {
        $season_name = sanitize_text_field($_POST['season_name']);
        $start_date = sanitize_text_field($_POST['start_date']);
        $end_date = sanitize_text_field($_POST['end_date']);
        $price_per_night = floatval($_POST['price_per_night']);
        $min_stay = intval($_POST['min_stay']);
        
        if (!empty($season_name) && !empty($start_date) && !empty($end_date) && $price_per_night > 0) {
            $result = $wpdb->insert(
                $pricing_table,
                array(
                    'season_name' => $season_name,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'price_per_night' => $price_per_night,
                    'min_stay' => $min_stay,
                    'is_active' => 1
                ),
                array('%s', '%s', '%s', '%f', '%d', '%d')
            );
            
            if ($result) {
                echo '<div class="notice notice-success"><p>Période tarifaire ajoutée avec succès !</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Erreur lors de l\'ajout de la période tarifaire.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>Veuillez remplir tous les champs obligatoires.</p></div>';
        }
    }
    
    if ($action === 'delete_pricing' && isset($_POST['pricing_id'])) {
        $pricing_id = intval($_POST['pricing_id']);
        $wpdb->delete($pricing_table, array('id' => $pricing_id), array('%d'));
        echo '<div class="notice notice-success"><p>Période tarifaire supprimée avec succès !</p></div>';
    }
    
    if ($action === 'toggle_pricing' && isset($_POST['pricing_id'])) {
        $pricing_id = intval($_POST['pricing_id']);
        $current_status = $wpdb->get_var($wpdb->prepare("SELECT is_active FROM $pricing_table WHERE id = %d", $pricing_id));
        $new_status = $current_status ? 0 : 1;
        
        $wpdb->update(
            $pricing_table,
            array('is_active' => $new_status),
            array('id' => $pricing_id),
            array('%d'),
            array('%d')
        );
        
        $status_text = $new_status ? 'activée' : 'désactivée';
        echo '<div class="notice notice-success"><p>Période tarifaire ' . $status_text . ' avec succès !</p></div>';
    }
}

// Récupération des périodes tarifaires
$pricing_seasons = $wpdb->get_results("SELECT * FROM $pricing_table ORDER BY start_date ASC");
?>

<div class="wrap">
    <h1>Tarification Saisonnière</h1>
    
    <!-- Lien vers la mise à jour de la base de données -->
    <div class="notice notice-info">
        <p><strong>Info :</strong> Si vous voyez des erreurs "Undefined property", <a href="<?php echo admin_url('admin.php?page=riwa-pricing&riwa_update_db=1'); ?>">cliquez ici pour corriger la base de données</a>.</p>
    </div>
    
    <div class="riwa-pricing-overview">
        <div class="pricing-stats">
            <div class="stat-card">
                <h3>Périodes actives</h3>
                <span class="stat-number"><?php echo !empty($pricing_seasons) ? count(array_filter($pricing_seasons, function($s) { return $s->is_active; })) : 0; ?></span>
            </div>
            <div class="stat-card">
                <h3>Prix moyen</h3>
                <span class="stat-number"><?php 
                    if (!empty($pricing_seasons)) {
                        $active_prices = array_filter($pricing_seasons, function($s) { return $s->is_active; });
                        $avg_price = count($active_prices) > 0 ? array_sum(array_column($active_prices, 'price_per_night')) / count($active_prices) : 0;
                        echo number_format($avg_price, 0) . ' €';
                    } else {
                        echo '0 €';
                    }
                ?></span>
            </div>
            <div class="stat-card">
                <h3>Période la plus chère</h3>
                <span class="stat-number"><?php 
                    if (!empty($pricing_seasons)) {
                        $max_price = max(array_column($pricing_seasons, 'price_per_night'));
                        echo number_format($max_price, 0) . ' €';
                    } else {
                        echo '0 €';
                    }
                ?></span>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout de période tarifaire -->
    <div class="riwa-add-pricing">
        <h2>Ajouter une nouvelle période tarifaire</h2>
        <form method="post" class="riwa-pricing-form">
            <input type="hidden" name="action" value="add_pricing">
            <?php wp_nonce_field('riwa_pricing_nonce', 'pricing_nonce'); ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="season_name">Nom de la saison *</label>
                    <input type="text" id="season_name" name="season_name" required placeholder="Ex: Haute saison été">
                </div>
                <div class="form-group">
                    <label for="price_per_night">Prix par nuit (€) *</label>
                    <input type="number" id="price_per_night" name="price_per_night" step="0.01" min="0" required placeholder="150.00">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Date de début *</label>
                    <input type="date" id="start_date" name="start_date" required>
                </div>
                <div class="form-group">
                    <label for="end_date">Date de fin *</label>
                    <input type="date" id="end_date" name="end_date" required>
                </div>
                <div class="form-group">
                    <label for="min_stay">Séjour minimum (nuits)</label>
                    <input type="number" id="min_stay" name="min_stay" min="1" value="1">
                </div>
            </div>
            
            <button type="submit" class="button button-primary">Ajouter la période tarifaire</button>
        </form>
    </div>

    <!-- Liste des périodes tarifaires -->
    <div class="riwa-pricing-list">
        <h2>Périodes tarifaires configurées</h2>
        
        <?php if (empty($pricing_seasons)): ?>
            <p>Aucune période tarifaire configurée. Ajoutez votre première période ci-dessus.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Saison</th>
                        <th>Période</th>
                        <th>Prix/nuit</th>
                        <th>Séjour min.</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pricing_seasons as $season): ?>
                        <tr>
                            <td><strong><?php echo esc_html($season->season_name); ?></strong></td>
                            <td>
                                <?php 
                                $start = new DateTime($season->start_date);
                                $end = new DateTime($season->end_date);
                                echo $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y');
                                ?>
                            </td>
                            <td>
                                <span class="price-display"><?php echo number_format($season->price_per_night, 2, ',', ' '); ?> €</span>
                            </td>
                            <td><?php echo isset($season->min_stay) ? $season->min_stay : 1; ?> nuit<?php echo (isset($season->min_stay) ? $season->min_stay : 1) > 1 ? 's' : ''; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $season->is_active ? 'active' : 'inactive'; ?>">
                                    <?php echo $season->is_active ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_pricing">
                                    <input type="hidden" name="pricing_id" value="<?php echo $season->id; ?>">
                                    <button type="submit" class="button button-small">
                                        <?php echo $season->is_active ? 'Désactiver' : 'Activer'; ?>
                                    </button>
                                </form>
                                
                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette période tarifaire ?');">
                                    <input type="hidden" name="action" value="delete_pricing">
                                    <input type="hidden" name="pricing_id" value="<?php echo $season->id; ?>">
                                    <button type="submit" class="button button-small button-link-delete">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Aperçu du calendrier avec prix -->
    <div class="riwa-pricing-preview">
        <h2>Aperçu du calendrier avec prix</h2>
        <div class="calendar-preview">
            <p>Les prix configurés ci-dessus s'afficheront automatiquement sur le calendrier de réservation.</p>
            <div class="price-legend">
                <h4>Légende des prix :</h4>
                <div class="legend-items">
                    <?php 
                    $active_seasons = array_filter($pricing_seasons, function($s) { return $s->is_active; });
                    foreach ($active_seasons as $season): 
                    ?>
                        <div class="legend-item">
                            <span class="legend-color" style="background-color: <?php echo $this->get_season_color($season->id); ?>"></span>
                            <span class="legend-text"><?php echo esc_html($season->season_name); ?> : <?php echo number_format($season->price_per_night, 0); ?>€</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.riwa-pricing-overview {
    margin: 20px 0 30px 0;
}

.pricing-stats {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    flex: 1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-number {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: #667eea;
}

.riwa-add-pricing {
    background: white;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.riwa-pricing-form .form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.riwa-pricing-form .form-group {
    flex: 1;
}

.riwa-pricing-form label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #2c3e50;
}

.riwa-pricing-form input {
    width: 100%;
    padding: 10px;
    border: 2px solid #e0e6ed;
    border-radius: 6px;
    font-size: 14px;
}

.riwa-pricing-form input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.price-display {
    font-weight: 600;
    color: #27ae60;
    font-size: 16px;
}

.status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.riwa-pricing-preview {
    background: white;
    border: 1px solid #e0e6ed;
    border-radius: 8px;
    padding: 25px;
    margin-top: 30px;
}

.price-legend {
    margin-top: 20px;
}

.legend-items {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.legend-text {
    font-size: 14px;
    color: #2c3e50;
}

@media (max-width: 768px) {
    .pricing-stats {
        flex-direction: column;
    }
    
    .riwa-pricing-form .form-row {
        flex-direction: column;
    }
    
    .legend-items {
        flex-direction: column;
    }
}
</style> 
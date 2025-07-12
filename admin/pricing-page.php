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

<div class="wrap riwa-admin">
    <div class="riwa-admin-header">
        <div class="riwa-header-content">
            <h1>Gestion de la Tarification</h1>
            <p class="riwa-subtitle">Configurez vos tarifs saisonniers</p>
        </div>
        <div class="riwa-header-actions">
            <button type="button" class="riwa-btn riwa-btn-secondary" id="import-pricing">
                <span class="dashicons dashicons-upload"></span>
                Importer
            </button>
            <button type="button" class="riwa-btn riwa-btn-primary" id="export-pricing">
                <span class="dashicons dashicons-download"></span>
                Exporter
            </button>
        </div>
    </div>
    
    <div class="riwa-admin-container">
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
                <a href="#add" class="riwa-nav-item" data-section="add">
                    <span class="dashicons dashicons-plus-alt"></span>
                    Ajouter
                </a>
                <a href="#list" class="riwa-nav-item" data-section="list">
                    <span class="dashicons dashicons-list-view"></span>
                    Liste
                </a>
                <a href="#preview" class="riwa-nav-item" data-section="preview">
                    <span class="dashicons dashicons-visibility"></span>
                    Aperçu
                </a>
            </nav>
        </div>
        
        <!-- Panneau de contenu -->
        <div class="riwa-content-panel">
            <!-- Section Vue d'ensemble -->
            <div class="riwa-section active" id="overview-section">
                <div class="riwa-section-header">
                    <h2>Statistiques de tarification</h2>
                    <p>Vue d'ensemble de vos tarifs saisonniers</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-stats">
                        <div class="stat-box">
                            <h3>Périodes actives</h3>
                            <span class="stat-number"><?php echo !empty($pricing_seasons) ? count(array_filter($pricing_seasons, function($s) { return $s->is_active; })) : 0; ?></span>
                        </div>
                        <div class="stat-box">
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
                        <div class="stat-box">
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
                        <div class="stat-box">
                            <h3>Total des périodes</h3>
                            <span class="stat-number"><?php echo count($pricing_seasons); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section Ajouter -->
            <div class="riwa-section" id="add-section">
                <div class="riwa-section-header">
                    <h2>Ajouter une période tarifaire</h2>
                    <p>Créez une nouvelle période tarifaire</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-form-container">
                        <form method="post" class="riwa-pricing-form">
                            <input type="hidden" name="action" value="add_pricing">
                            <?php wp_nonce_field('riwa_pricing_nonce', 'pricing_nonce'); ?>
                            
                            <div class="riwa-form-grid">
                                <div class="riwa-form-group">
                                    <label for="season_name">Nom de la saison *</label>
                                    <input type="text" id="season_name" name="season_name" required placeholder="Ex: Haute saison été">
                                </div>
                                <div class="riwa-form-group">
                                    <label for="price_per_night">Prix par nuit (€) *</label>
                                    <input type="number" id="price_per_night" name="price_per_night" step="0.01" min="0" required placeholder="150.00">
                                </div>
                                <div class="riwa-form-group">
                                    <label for="start_date">Date de début *</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="riwa-form-group">
                                    <label for="end_date">Date de fin *</label>
                                    <input type="date" id="end_date" name="end_date" required>
                                </div>
                                <div class="riwa-form-group">
                                    <label for="min_stay">Séjour minimum (nuits)</label>
                                    <input type="number" id="min_stay" name="min_stay" min="1" value="1">
                                </div>
                            </div>
                            
                            <div class="riwa-form-actions">
                                <button type="submit" class="riwa-btn riwa-btn-primary">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    Ajouter la période tarifaire
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Section Liste -->
            <div class="riwa-section" id="list-section">
                <div class="riwa-section-header">
                    <h2>Périodes tarifaires configurées</h2>
                    <p>Gérez toutes vos périodes tarifaires</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-table-container">
                        <?php if (empty($pricing_seasons)): ?>
                            <div class="riwa-empty-state">
                                <span class="dashicons dashicons-calendar-alt"></span>
                                <h3>Aucune période tarifaire</h3>
                                <p>Ajoutez votre première période tarifaire dans la section "Ajouter".</p>
                            </div>
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
                                                    <button type="submit" class="riwa-btn riwa-btn-secondary button-small">
                                                        <?php echo $season->is_active ? 'Désactiver' : 'Activer'; ?>
                                                    </button>
                                                </form>
                                                
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette période tarifaire ?');">
                                                    <input type="hidden" name="action" value="delete_pricing">
                                                    <input type="hidden" name="pricing_id" value="<?php echo $season->id; ?>">
                                                    <button type="submit" class="riwa-btn riwa-btn-danger button-small">Supprimer</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section Aperçu -->
            <div class="riwa-section" id="preview-section">
                <div class="riwa-section-header">
                    <h2>Aperçu du calendrier avec prix</h2>
                    <p>Visualisez comment vos tarifs s'afficheront</p>
                </div>
                <div class="riwa-section-content">
                    <div class="riwa-preview-container">
                        <div class="riwa-preview-header">
                            <p>Les prix configurés ci-dessus s'afficheront automatiquement sur le calendrier de réservation.</p>
                        </div>
                        <div class="riwa-preview-content">
                            <h4>Légende des prix :</h4>
                            <div class="price-legend">
                                <?php 
                                $active_seasons = array_filter($pricing_seasons, function($s) { return $s->is_active; });
                                if (empty($active_seasons)): ?>
                                    <p>Aucune période active à afficher.</p>
                                <?php else: ?>
                                    <?php foreach ($active_seasons as $season): ?>
                                        <div class="legend-item">
                                            <span class="legend-color" style="background-color: <?php echo $this->get_season_color($season->id); ?>"></span>
                                            <span class="legend-text"><?php echo esc_html($season->season_name); ?> : <?php echo number_format($season->price_per_night, 0); ?>€</span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Design moderne et minimaliste pour l'admin de tarification */
.riwa-admin {
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
.riwa-admin-container {
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
    display: block;
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

/* Statistiques */
.riwa-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    padding: 2rem;
}

.stat-box {
    background: var(--riwa-white);
    border: none;
    border-radius: 0;
    padding: 2rem;
    text-align: center;
    transition: all 0.2s ease;
}

.stat-box:hover {
    background: var(--riwa-gray-50);
}

.stat-box h3 {
    font-size: 12px;
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin: 0 0 1rem 0;
    color: var(--riwa-gray-500);
}

.stat-number {
    font-size: 36px;
    font-weight: 200;
    color: var(--riwa-black);
    display: block;
    letter-spacing: -1px;
}

/* Formulaire */
.riwa-form-container {
    padding: 2rem;
}

.riwa-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.riwa-form-group {
    display: flex;
    flex-direction: column;
}

.riwa-form-group label {
    font-size: 14px;
    font-weight: 500;
    color: #1d2327;
    margin-bottom: 0.5rem;
}

.riwa-form-group input,
.riwa-form-group select,
.riwa-form-group textarea {
    padding: 0.75rem;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
}

.riwa-form-group input:focus,
.riwa-form-group select:focus,
.riwa-form-group textarea:focus {
    border-color: #2271b1;
    box-shadow: 0 0 0 1px #2271b1;
    outline: none;
}

.riwa-form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Tableau */
.riwa-table-container {
    padding: 2rem;
}

.wp-list-table {
    border: none;
    border-radius: 0;
    box-shadow: none;
}

.wp-list-table th {
    background: #f6f7f7;
    border-bottom: 1px solid #e1e5e9;
    font-weight: 600;
    color: #1d2327;
}

.wp-list-table td {
    border-bottom: 1px solid #f0f0f1;
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

/* Badges de statut */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #edfaef;
    color: #008a20;
}

.status-inactive {
    background: #fcf0f1;
    color: #d63638;
}

/* Aperçu */
.riwa-preview-container {
    padding: 2rem;
}

.riwa-preview-header {
    margin-bottom: 1.5rem;
}

.riwa-preview-header p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

.riwa-preview-content h4 {
    margin: 0 0 1rem 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.price-legend {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
    background: #2271b1;
}

.legend-text {
    font-size: 14px;
    color: #1d2327;
}

/* Boutons */
.riwa-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    background: white;
    color: #1d2327;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.riwa-btn:hover {
    background: #f6f7f7;
    border-color: #8c8f94;
}

.riwa-btn-primary {
    background: #2271b1;
    border-color: #2271b1;
    color: white;
}

.riwa-btn-primary:hover {
    background: #135e96;
    border-color: #135e96;
}

.riwa-btn-secondary {
    background: #f6f7f7;
    border-color: #dcdcde;
}

.riwa-btn-secondary:hover {
    background: #f0f0f1;
    border-color: #8c8f94;
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

/* Affichage des prix */
.price-display {
    font-weight: 600;
    color: #1d2327;
}

/* Responsive */
@media (max-width: 1200px) {
    .riwa-admin-container {
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
    
    .riwa-stats {
        grid-template-columns: 1fr;
        gap: 1rem;
        padding: 1.5rem;
    }
    
    .riwa-section {
        padding: 1rem;
    }
    
    .riwa-form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .riwa-form-container {
        padding: 1.5rem;
    }
    
    .riwa-table-container {
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
        
        // Mettre à jour la navigation active
        $('.riwa-nav-item').removeClass('active');
        $(this).addClass('active');
        
        // Afficher la section correspondante
        $('.riwa-section').removeClass('active');
        $('#' + targetSection + '-section').addClass('active');
        
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
    $('.riwa-nav-item[data-section="' + initialSection + '"]').click();
    
    // Exporter les tarifs
    $('#export-pricing').on('click', function() {
        alert('Fonctionnalité d\'export à implémenter');
    });
    
    // Importer les tarifs
    $('#import-pricing').on('click', function() {
        alert('Fonctionnalité d\'import à implémenter');
    });
});
</script> 
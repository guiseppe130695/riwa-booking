<?php
if (!defined('ABSPATH')) {
    exit;
}
// Variables disponibles : $pricing_seasons (récupérés par Riwa_Pricing_Table::get_all_seasons())
?>
<div class="riwa-section" id="pricing-section">
    <div class="riwa-section-header">
        <h2>Gestion de la Tarification</h2>
        <p>Configurez vos tarifs saisonniers</p>
    </div>
    <div class="riwa-section-content">
        <div class="riwa-preview-container">
            <div class="riwa-setting-group">
                <h3>Ajouter une période tarifaire</h3>
                <form method="post" class="riwa-pricing-form">
                    <input type="hidden" name="action" value="add_pricing">
                    <?php wp_nonce_field('riwa_pricing_nonce', 'pricing_nonce'); ?>
                    <div class="riwa-form-linear">
                        <div class="riwa-form-row">
                            <div class="riwa-form-group">
                                <label for="season_name">Nom de la saison *</label>
                                <input type="text" id="season_name" name="season_name" required placeholder="Ex: Haute saison été" class="riwa-input">
                            </div>
                            <div class="riwa-form-group">
                                <label for="price_per_night">Prix par nuit (€) *</label>
                                <input type="number" id="price_per_night" name="price_per_night" step="0.01" min="0" required placeholder="150.00" class="riwa-input">
                            </div>
                            <div class="riwa-form-group">
                                <label for="start_date">Date de début *</label>
                                <input type="text" id="start_date" name="start_date" required placeholder="JJ/MM/AAAA" readonly class="riwa-input">
                            </div>
                            <div class="riwa-form-group">
                                <label for="end_date">Date de fin *</label>
                                <input type="text" id="end_date" name="end_date" required placeholder="JJ/MM/AAAA" readonly class="riwa-input">
                            </div>
                            <div class="riwa-form-group">
                                <label for="min_stay">Séjour min. (nuits)</label>
                                <input type="number" id="min_stay" name="min_stay" min="1" value="1" class="riwa-input">
                            </div>
                            <div class="riwa-form-group riwa-form-submit">
                                <button type="submit" class="riwa-btn riwa-btn-primary">
                                    <span class="dashicons dashicons-plus-alt"></span>
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="riwa-pricing-table-wrap">
                <h3>Périodes tarifaires configurées</h3>
            <?php if (empty($pricing_seasons)): ?>
                <div class="riwa-empty-state">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <h3>Aucune période tarifaire</h3>
                    <p>Ajoutez votre première période tarifaire ci-dessus.</p>
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
                                    $end   = new DateTime($season->end_date);
                                    echo esc_html($start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'));
                                    ?>
                                </td>
                                <td>
                                    <span class="price-display"><?php echo esc_html(number_format($season->price_per_night, 2, ',', ' ')); ?> €</span>
                                </td>
                                <td><?php $ms = isset($season->min_stay) ? $season->min_stay : 1; echo esc_html($ms . ' nuit' . ($ms > 1 ? 's' : '')); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $season->is_active ? 'active' : 'inactive'; ?>">
                                        <?php echo $season->is_active ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette période tarifaire ?');">
                                        <input type="hidden" name="action" value="delete_pricing">
                                        <input type="hidden" name="pricing_id" value="<?php echo esc_attr($season->id); ?>">
                                        <button type="submit" class="riwa-btn riwa-btn-danger button-small">Supprimer</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            </div><!-- /.riwa-pricing-table-wrap -->
        </div><!-- /.riwa-preview-container -->
    </div>
</div>

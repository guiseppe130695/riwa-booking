<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$pricing_data = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}riwa_pricing WHERE is_active = 1 ORDER BY start_date ASC"
);
?>
<div class="riwa-section" id="debug-section">
    <div class="riwa-section-header">
        <h2>Informations de diagnostic</h2>
        <p>Informations techniques et de débogage</p>
    </div>
    <div class="riwa-section-content">
        <div class="riwa-preview-container">
            <h3>Données de tarification</h3>
            <p><strong>Données de tarification disponibles :</strong></p>
            <?php if (empty($pricing_data)): ?>
                <p style="color:#d63638;">Aucune donnée de tarification trouvée !</p>
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
            <p><strong>Version du plugin :</strong> <?php echo esc_html(RIWA_BOOKING_VERSION); ?></p>
        </div>
    </div>
</div>

<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$pricing_data = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}riwa_pricing WHERE is_active = 1 ORDER BY start_date ASC"
);

// Informations système
$php_version  = phpversion();
$wp_version   = get_bloginfo('version');
$plugin_ver   = RIWA_BOOKING_VERSION;
$debug_on     = WP_DEBUG;
$db_version   = $wpdb->db_version();
$table_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}riwa_bookings");
$table_pricing  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}riwa_pricing");
$table_payments = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}riwa_payments'");
?>
<div class="riwa-section" id="debug-section">
    <div class="riwa-section-header">
        <h2>Diagnostic</h2>
        <p>Informations techniques et de débogage</p>
    </div>
    <div class="riwa-section-content">
        <div class="riwa-preview-container">

            <!-- Informations système -->
            <div class="riwa-setting-group">
                <h3>Informations système</h3>
                <div class="riwa-debug-grid">
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">Version du plugin</span>
                        <code class="riwa-debug-value"><?php echo esc_html($plugin_ver); ?></code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">PHP</span>
                        <code class="riwa-debug-value"><?php echo esc_html($php_version); ?></code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">WordPress</span>
                        <code class="riwa-debug-value"><?php echo esc_html($wp_version); ?></code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">MySQL</span>
                        <code class="riwa-debug-value"><?php echo esc_html($db_version); ?></code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">Mode debug WP</span>
                        <span class="riwa-debug-badge <?php echo $debug_on ? 'badge-on' : 'badge-off'; ?>">
                            <?php echo $debug_on ? 'Activé' : 'Désactivé'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- État des tables -->
            <div class="riwa-setting-group">
                <h3>État de la base de données</h3>
                <div class="riwa-debug-grid">
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">Réservations</span>
                        <code class="riwa-debug-value"><?php echo intval($table_bookings); ?> enregistrement(s)</code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">Périodes tarifaires</span>
                        <code class="riwa-debug-value"><?php echo intval($table_pricing); ?> enregistrement(s)</code>
                    </div>
                    <div class="riwa-debug-item">
                        <span class="riwa-debug-label">Table paiements</span>
                        <span class="riwa-debug-badge <?php echo $table_payments ? 'badge-on' : 'badge-off'; ?>">
                            <?php echo $table_payments ? 'Présente' : 'Absente'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tarification active -->
            <div class="riwa-setting-group">
                <h3>Tarification active</h3>
                <?php if (empty($pricing_data)): ?>
                    <div class="riwa-empty-state" style="padding: 1.5rem 0;">
                        <span class="dashicons dashicons-warning" style="font-size:24px;color:#ef4444;margin-bottom:8px;"></span>
                        <p style="color:#ef4444;margin:0;font-size:13px;">Aucune donnée de tarification trouvée. Ajoutez des périodes dans l'onglet <strong>Tarification</strong>.</p>
                    </div>
                <?php else: ?>
                    <div class="riwa-debug-pricing-list">
                        <?php foreach ($pricing_data as $price):
                            $start = new DateTime($price->start_date);
                            $end   = new DateTime($price->end_date);
                        ?>
                        <div class="riwa-debug-pricing-row">
                            <strong><?php echo esc_html($price->season_name); ?></strong>
                            <span><?php echo esc_html($start->format('d/m/Y') . ' → ' . $end->format('d/m/Y')); ?></span>
                            <code><?php echo esc_html(number_format($price->price_per_night, 2, ',', ' ')); ?> €/nuit</code>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

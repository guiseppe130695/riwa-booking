<?php
if (!defined('ABSPATH')) {
    exit;
}
global $wpdb;
$table = $wpdb->prefix . 'riwa_bookings';

// Mois courant et précédent
$current_year  = (int) date('Y');
$current_month = (int) date('n');
$prev_year     = $current_month === 1 ? $current_year - 1 : $current_year;
$prev_month    = $current_month === 1 ? 12 : $current_month - 1;

// Totaux globaux
$total_bookings = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
$total_revenue  = (float) $wpdb->get_var(
    "SELECT COALESCE(SUM(total_price), 0) FROM {$table} WHERE status = 'confirmed'"
);

// Totaux mois courant
$bookings_this_month = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d",
    $current_year, $current_month
));
$revenue_this_month = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(total_price), 0) FROM {$table}
     WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d AND status = 'confirmed'",
    $current_year, $current_month
));

// Totaux mois précédent (pour comparaison)
$bookings_prev_month = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table} WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d",
    $prev_year, $prev_month
));
$revenue_prev_month = (float) $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(total_price), 0) FROM {$table}
     WHERE YEAR(created_at) = %d AND MONTH(created_at) = %d AND status = 'confirmed'",
    $prev_year, $prev_month
));

// Calcul des tendances
if (!function_exists('riwa_trend')) {
    function riwa_trend($current, $previous) {
        if ($previous <= 0) {
            return $current > 0 ? ['pct' => 100, 'up' => true] : ['pct' => 0, 'up' => true];
        }
        $pct = round(($current - $previous) / $previous * 100);
        return ['pct' => abs($pct), 'up' => $pct >= 0];
    }
}
$trend_bookings = riwa_trend($bookings_this_month, $bookings_prev_month);
$trend_revenue  = riwa_trend($revenue_this_month, $revenue_prev_month);

// Taux d'occupation du mois courant
$days_in_month  = (int) date('t');
$nights_booked  = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COALESCE(SUM(DATEDIFF(check_out_date, check_in_date)), 0) FROM {$table}
     WHERE status = 'confirmed'
       AND MONTH(check_in_date) = %d AND YEAR(check_in_date) = %d",
    $current_month, $current_year
));
$occupancy_rate = $days_in_month > 0 ? min(100, round($nights_booked / $days_in_month * 100)) : 0;

// Compteurs par statut
$count_pending   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
$count_confirmed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'confirmed'");
$count_cancelled = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'cancelled'");

// 5 dernières réservations
$recent_bookings = $wpdb->get_results(
    "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 5"
);
?>
<div class="riwa-section" id="dashboard-section">
    <div class="riwa-section-header">
        <h2>Tableau de bord</h2>
        <p>Vue d'ensemble de votre activité</p>
    </div>
    <div class="riwa-section-content">

        <!-- Cartes de statistiques -->
        <div class="riwa-dashboard-grid">

            <div class="riwa-stat-card">
                <div class="riwa-stat-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                <div class="riwa-stat-body">
                    <div class="riwa-stat-value"><?php echo esc_html($total_bookings); ?></div>
                    <div class="riwa-stat-label">Total réservations</div>
                    <div class="riwa-stat-sub">
                        <span class="riwa-stat-month"><?php echo esc_html($bookings_this_month); ?> ce mois</span>
                        <span class="riwa-stat-trend <?php echo $trend_bookings['up'] ? 'riwa-stat-trend-up' : 'riwa-stat-trend-down'; ?>">
                            <?php echo $trend_bookings['up'] ? '↑' : '↓'; ?>
                            <?php echo esc_html($trend_bookings['pct']); ?>% vs mois préc.
                        </span>
                    </div>
                </div>
            </div>

            <div class="riwa-stat-card">
                <div class="riwa-stat-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="riwa-stat-body">
                    <div class="riwa-stat-value"><?php echo number_format($total_revenue, 0, ',', ' '); ?> €</div>
                    <div class="riwa-stat-label">Revenus confirmés</div>
                    <div class="riwa-stat-sub">
                        <span class="riwa-stat-month"><?php echo number_format($revenue_this_month, 0, ',', ' '); ?> € ce mois</span>
                        <span class="riwa-stat-trend <?php echo $trend_revenue['up'] ? 'riwa-stat-trend-up' : 'riwa-stat-trend-down'; ?>">
                            <?php echo $trend_revenue['up'] ? '↑' : '↓'; ?>
                            <?php echo esc_html($trend_revenue['pct']); ?>% vs mois préc.
                        </span>
                    </div>
                </div>
            </div>

            <div class="riwa-stat-card">
                <div class="riwa-stat-icon"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="riwa-stat-body">
                    <div class="riwa-stat-value"><?php echo esc_html($occupancy_rate); ?>%</div>
                    <div class="riwa-stat-label">Taux d'occupation</div>
                    <div class="riwa-stat-sub">
                        <span class="riwa-stat-month"><?php echo esc_html(date_i18n('F Y')); ?></span>
                    </div>
                    <div class="riwa-progress-bar">
                        <div class="riwa-progress-fill" style="width:<?php echo esc_attr($occupancy_rate); ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="riwa-stat-card riwa-stat-card--wide">
                <div class="riwa-stat-icon"><span class="dashicons dashicons-tag"></span></div>
                <div class="riwa-stat-body">
                    <div class="riwa-stat-label" style="margin-bottom:0.75rem;">Répartition par statut</div>
                    <div class="riwa-stat-badges">
                        <span class="riwa-status-badge riwa-status-pending"><?php echo esc_html($count_pending); ?> En attente</span>
                        <span class="riwa-status-badge riwa-status-confirmed"><?php echo esc_html($count_confirmed); ?> Confirmée<?php echo $count_confirmed > 1 ? 's' : ''; ?></span>
                        <span class="riwa-status-badge riwa-status-cancelled"><?php echo esc_html($count_cancelled); ?> Annulée<?php echo $count_cancelled > 1 ? 's' : ''; ?></span>
                    </div>
                </div>
            </div>

        </div>

        <!-- 5 dernières réservations -->
        <div class="riwa-dashboard-recent">
            <h3>Dernières réservations</h3>
            <?php if (empty($recent_bookings)): ?>
                <p style="color: var(--riwa-gray-500);">Aucune réservation pour le moment.</p>
            <?php else: ?>
                <div class="riwa-table-wrapper">
                    <table class="riwa-modern-table">
                        <thead>
                            <tr>
                                <th>Référence</th>
                                <th>Client</th>
                                <th>Dates</th>
                                <th>Prix</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <tr>
                                    <td><span class="riwa-booking-reference">RIWA-<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></span></td>
                                    <td>
                                        <div class="riwa-client-name"><?php echo esc_html($booking->guest_name); ?></div>
                                        <div style="font-size:12px;color:var(--riwa-gray-500);"><?php echo esc_html($booking->guest_email); ?></div>
                                    </td>
                                    <td>
                                        <?php echo esc_html(date('d/m/Y', strtotime($booking->check_in_date))); ?>
                                        → <?php echo esc_html(date('d/m/Y', strtotime($booking->check_out_date))); ?>
                                    </td>
                                    <td>
                                        <?php if ($booking->total_price > 0): ?>
                                            <span class="riwa-price-total"><?php echo number_format($booking->total_price, 0, ',', ' '); ?> €</span>
                                        <?php else: ?>
                                            <span style="color:var(--riwa-gray-400);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php $badge = Riwa_Bookings_Table::get_status_badge($booking->status); ?>
                                        <span class="riwa-status-badge riwa-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($badge['label']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

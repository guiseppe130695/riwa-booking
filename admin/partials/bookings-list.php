<?php
if (!defined('ABSPATH')) {
    exit;
}
// Récupérer les filtres depuis GET
$filter_status = sanitize_text_field($_GET['filter_status'] ?? '');
$filter_month  = (int) ($_GET['filter_month'] ?? 0);
$filter_search = sanitize_text_field($_GET['filter_search'] ?? '');
$current_page  = max(1, (int) ($_GET['paged'] ?? 1));
$per_page      = 20;

$result   = Riwa_Bookings_Table::get_filtered_bookings([
    'status'   => $filter_status,
    'month'    => $filter_month ?: null,
    'year'     => (int) date('Y'),
    'search'   => $filter_search,
    'page'     => $current_page,
    'per_page' => $per_page,
]);
$bookings    = $result['bookings'];
$total       = $result['total'];
$total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

$base_url = admin_url('admin.php?page=riwa-bookings&section=bookings');

if (!function_exists('riwa_filter_url')) {
    function riwa_filter_url($params) {
        $base = admin_url('admin.php?page=riwa-bookings&section=bookings');
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $v !== 0) {
                $base .= '&' . urlencode($k) . '=' . urlencode($v);
            }
        }
        return $base;
    }
}
?>
<div class="riwa-section" id="bookings-section">
    <div class="riwa-section-header">
        <h2>Réservations</h2>
        <p>Gérez et filtrez toutes vos réservations</p>
    </div>
    <div class="riwa-section-content">

        <!-- Barre de filtres -->
        <form method="get" class="riwa-filters-bar" action="">
            <input type="hidden" name="page" value="riwa-bookings">
            <input type="hidden" name="section" value="bookings">

            <div class="riwa-filter-group">
                <label for="filter_status">Statut</label>
                <select name="filter_status" id="filter_status" class="riwa-input">
                    <option value="">Tous</option>
                    <option value="pending"   <?php selected($filter_status, 'pending'); ?>>En attente</option>
                    <option value="confirmed" <?php selected($filter_status, 'confirmed'); ?>>Confirmée</option>
                    <option value="cancelled" <?php selected($filter_status, 'cancelled'); ?>>Annulée</option>
                </select>
            </div>

            <div class="riwa-filter-group">
                <label for="filter_month">Mois</label>
                <select name="filter_month" id="filter_month" class="riwa-input">
                    <option value="">Tous</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php selected($filter_month, $m); ?>>
                            <?php echo date_i18n('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="riwa-filter-group riwa-filter-search">
                <label for="filter_search">Recherche</label>
                <input type="text" name="filter_search" id="filter_search" class="riwa-input"
                       value="<?php echo esc_attr($filter_search); ?>"
                       placeholder="Nom ou email...">
            </div>

            <div class="riwa-filter-group riwa-filter-actions">
                <button type="submit" class="riwa-btn riwa-btn-primary">
                    <span class="dashicons dashicons-search"></span> Filtrer
                </button>
                <?php if ($filter_status || $filter_month || $filter_search): ?>
                    <a href="<?php echo esc_url($base_url); ?>" class="riwa-btn riwa-btn-secondary">
                        <span class="dashicons dashicons-no-alt"></span> Réinitialiser
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <p class="riwa-results-count">
            <?php printf(
                '<strong>%d</strong> réservation%s trouvée%s',
                $total, $total > 1 ? 's' : '', $total > 1 ? 's' : ''
            ); ?>
        </p>

        <?php if (empty($bookings)): ?>
            <div class="riwa-empty-state">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3>Aucune réservation</h3>
                <p>Aucune réservation ne correspond à vos critères.</p>
            </div>
        <?php else: ?>
            <div class="riwa-table-wrapper">
                <table class="riwa-modern-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Client &amp; Contact</th>
                            <th>Dates</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr class="riwa-booking-row">
                                <td><span class="riwa-booking-reference">RIWA-<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></span></td>
                                <td>
                                    <div class="riwa-client-name"><?php echo esc_html($booking->guest_name); ?></div>
                                    <div class="riwa-client-contact">
                                        <a href="mailto:<?php echo esc_attr($booking->guest_email); ?>"><?php echo esc_html($booking->guest_email); ?></a>
                                        <span class="riwa-contact-separator">•</span>
                                        <a href="tel:<?php echo esc_attr($booking->guest_phone); ?>"><?php echo esc_html($booking->guest_phone); ?></a>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in_date))); ?></div>
                                    <div style="font-size:11px;color:var(--riwa-gray-500);">→ <?php echo esc_html(date('d/m/Y', strtotime($booking->check_out_date))); ?></div>
                                </td>
                                <td>
                                    <?php if ($booking->total_price > 0): ?>
                                        <span class="riwa-price-total"><?php echo number_format($booking->total_price, 0, ',', ' '); ?> €</span>
                                    <?php else: ?>
                                        <span class="riwa-price-unknown"><span class="dashicons dashicons-minus"></span></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $badge = Riwa_Bookings_Table::get_status_badge($booking->status); ?>
                                    <span class="riwa-status-badge riwa-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($badge['label']); ?></span>
                                </td>
                                <td>
                                    <div class="riwa-actions-compact">
                                        <?php if ($booking->status === 'pending'): ?>
                                            <button type="button" class="riwa-btn riwa-btn-success button-small"
                                                    onclick="updateBookingStatus(<?php echo esc_attr($booking->id); ?>, 'confirmed')"
                                                    title="Confirmer">
                                                <span class="dashicons dashicons-yes"></span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($booking->status !== 'cancelled'): ?>
                                            <button type="button" class="riwa-btn riwa-btn-danger button-small"
                                                    onclick="updateBookingStatus(<?php echo esc_attr($booking->id); ?>, 'cancelled')"
                                                    title="Annuler">
                                                <span class="dashicons dashicons-no"></span>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=riwa_download_pdf&booking_id=' . $booking->id . '&nonce=' . wp_create_nonce('riwa_pdf_nonce_' . $booking->id))); ?>"
                                           class="riwa-btn riwa-btn-secondary button-small" title="Télécharger PDF" target="_blank">
                                            <span class="dashicons dashicons-pdf"></span>
                                        </a>
                                        <button type="button" class="riwa-btn riwa-btn-secondary button-small view-details-popup"
                                                data-booking-id="<?php echo esc_attr($booking->id); ?>"
                                                data-booking-name="<?php echo esc_attr($booking->guest_name); ?>"
                                                data-booking-email="<?php echo esc_attr($booking->guest_email); ?>"
                                                data-booking-phone="<?php echo esc_attr($booking->guest_phone); ?>"
                                                data-booking-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                                                data-booking-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                                                data-booking-guests="<?php echo esc_attr(Riwa_Bookings_Table::get_total_guests($booking)); ?>"
                                                data-booking-adults="<?php echo esc_attr($booking->adults_count ?? 0); ?>"
                                                data-booking-children="<?php echo esc_attr($booking->children_count ?? 0); ?>"
                                                data-booking-babies="<?php echo esc_attr($booking->babies_count ?? 0); ?>"
                                                data-booking-price="<?php echo esc_attr($booking->total_price); ?>"
                                                data-booking-price-per-night="<?php echo esc_attr($booking->price_per_night); ?>"
                                                data-booking-status="<?php echo esc_attr($booking->status); ?>"
                                                data-booking-created="<?php echo esc_attr($booking->created_at); ?>"
                                                data-booking-requests="<?php echo esc_attr($booking->special_requests); ?>"
                                                title="Voir les détails">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        <button type="button" class="riwa-btn riwa-btn-danger button-small delete-booking-btn"
                                                data-booking-id="<?php echo esc_attr($booking->id); ?>"
                                                data-booking-name="<?php echo esc_attr($booking->guest_name); ?>"
                                                title="Supprimer">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="riwa-pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo esc_url(riwa_filter_url(['filter_status' => $filter_status, 'filter_month' => $filter_month ?: '', 'filter_search' => $filter_search, 'paged' => $current_page - 1])); ?>"
                           class="riwa-btn riwa-btn-secondary">
                            <span class="dashicons dashicons-arrow-left-alt2"></span> Précédent
                        </a>
                    <?php endif; ?>
                    <span class="riwa-pagination-info">Page <?php echo esc_html($current_page); ?> / <?php echo esc_html($total_pages); ?></span>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo esc_url(riwa_filter_url(['filter_status' => $filter_status, 'filter_month' => $filter_month ?: '', 'filter_search' => $filter_search, 'paged' => $current_page + 1])); ?>"
                           class="riwa-btn riwa-btn-secondary">
                            Suivant <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

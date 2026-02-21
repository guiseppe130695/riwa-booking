<?php
if (!defined('ABSPATH')) {
    exit;
}

// Réservations du jour (arrivées + départs + séjours en cours)
global $wpdb;
$today     = current_time('Y-m-d');
$bk_table  = $wpdb->prefix . 'riwa_bookings';

$today_bookings = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $bk_table
     WHERE status = 'confirmed'
       AND check_in_date <= %s AND check_out_date >= %s
     ORDER BY check_in_date ASC",
    $today, $today
));
?>

<div class="riwa-section" id="notifications-section">
    <div class="riwa-section-header">
        <h2>Notifications</h2>
        <p>Centre de communication WhatsApp — envoyez des messages en un clic</p>
    </div>
    <div class="riwa-section-content">

        <!-- ── Alerte activation ─────────────────────────────────────────── -->
        <?php if (!get_option('riwa_notif_whatsapp_enabled', false)): ?>
        <div class="notice notice-warning" style="margin:0 0 1.5rem;">
            <p>
                <span class="dashicons dashicons-info" style="color:#d97706;"></span>
                Les boutons WhatsApp sont désactivés.
                <a href="<?php echo esc_url(admin_url('admin.php?page=riwa-bookings&section=settings#notif')); ?>">
                    Activer dans Paramètres → Notifications
                </a>
            </p>
        </div>
        <?php endif; ?>

        <!-- ── Réservations du jour ──────────────────────────────────────── -->
        <div class="riwa-notif-today-section">
            <div class="riwa-notif-section-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                Séjours actifs aujourd'hui
                <span class="riwa-badge-count"><?php echo count($today_bookings); ?></span>
            </div>

            <?php if (empty($today_bookings)): ?>
                <div class="riwa-notif-empty">
                    <span class="dashicons dashicons-yes-alt"></span>
                    Aucun séjour en cours aujourd'hui.
                </div>
            <?php else: ?>
            <div class="riwa-notif-bookings-grid">
                <?php foreach ($today_bookings as $b):
                    $is_arriving  = $b->check_in_date  === $today;
                    $is_departing = $b->check_out_date  === $today;
                    $ref = 'RIWA-' . str_pad($b->id, 6, '0', STR_PAD_LEFT);
                    $label = $is_arriving ? 'Arrivée' : ($is_departing ? 'Départ' : 'Séjour');
                    $label_class = $is_arriving ? 'arriving' : ($is_departing ? 'departing' : 'staying');
                ?>
                <div class="riwa-notif-booking-card" data-booking-id="<?php echo intval($b->id); ?>">
                    <div class="riwa-notif-booking-header">
                        <div class="riwa-notif-booking-name"><?php echo esc_html($b->guest_name); ?></div>
                        <span class="riwa-notif-day-label <?php echo esc_attr($label_class); ?>"><?php echo esc_html($label); ?></span>
                    </div>
                    <div class="riwa-notif-booking-meta">
                        <span class="dashicons dashicons-calendar"></span>
                        <?php echo esc_html(date_i18n('d/m/Y', strtotime($b->check_in_date))); ?>
                        →
                        <?php echo esc_html(date_i18n('d/m/Y', strtotime($b->check_out_date))); ?>
                        &nbsp;·&nbsp;
                        <span class="dashicons dashicons-phone"></span>
                        <?php echo esc_html($b->guest_phone ?: '—'); ?>
                    </div>
                    <div class="riwa-notif-booking-actions">
                        <?php if ($is_arriving): ?>
                            <button class="riwa-wa-btn riwa-wa-btn-sm riwa-notif-send-btn"
                                    data-booking-id="<?php echo intval($b->id); ?>"
                                    data-tpl="confirmation"
                                    data-target="client">
                                Confirmation
                            </button>
                            <button class="riwa-wa-btn riwa-wa-btn-sm riwa-notif-send-btn"
                                    data-booking-id="<?php echo intval($b->id); ?>"
                                    data-tpl="checkin"
                                    data-target="client">
                                Infos arrivée
                            </button>
                        <?php elseif ($is_departing): ?>
                            <button class="riwa-wa-btn riwa-wa-btn-sm riwa-notif-send-btn"
                                    data-booking-id="<?php echo intval($b->id); ?>"
                                    data-tpl="review"
                                    data-target="client">
                                Demande avis
                            </button>
                        <?php else: ?>
                            <button class="riwa-wa-btn riwa-wa-btn-sm riwa-notif-send-btn"
                                    data-booking-id="<?php echo intval($b->id); ?>"
                                    data-tpl="reminder"
                                    data-target="client">
                                Rappel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── Historique récent ─────────────────────────────────────────── -->
        <div class="riwa-notif-log-section">
            <div class="riwa-notif-section-title">
                <span class="dashicons dashicons-clock"></span>
                Historique récent
            </div>
            <div id="riwa-recent-notif-log" class="riwa-notif-log-table-wrap">
                <div class="riwa-notif-log-loading">
                    <span class="dashicons dashicons-update-alt riwa-spin"></span>
                    Chargement…
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal de prévisualisation WhatsApp (partagé avec le popup réservation) -->
<div id="riwa-wa-preview-modal" class="riwa-wa-modal-overlay" style="display:none;">
    <div class="riwa-wa-modal">
        <div class="riwa-wa-modal-header">
            <span class="riwa-wa-modal-icon">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
            </span>
            <h3 id="riwa-wa-modal-title">Aperçu du message</h3>
            <button type="button" class="riwa-wa-modal-close" id="riwa-wa-modal-close">&#x2715;</button>
        </div>
        <div class="riwa-wa-modal-body">
            <div class="riwa-wa-bubble-wrap">
                <div class="riwa-wa-bubble" id="riwa-wa-modal-message">—</div>
            </div>
            <div class="riwa-wa-modal-phone">
                <span class="dashicons dashicons-phone"></span>
                <span id="riwa-wa-modal-phone-display">—</span>
            </div>
        </div>
        <div class="riwa-wa-modal-footer">
            <button type="button" class="riwa-btn riwa-btn-secondary" id="riwa-wa-modal-cancel">Annuler</button>
            <a href="#" target="_blank" class="riwa-btn riwa-btn-wa" id="riwa-wa-modal-open">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                Ouvrir WhatsApp
            </a>
        </div>
    </div>
</div>

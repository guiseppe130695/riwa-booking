<?php
if (!defined('ABSPATH')) {
    exit;
}

$reason_labels      = Riwa_Planning::get_reason_labels();
$housekeeping_labels = Riwa_Planning::get_housekeeping_labels();
$today_movements    = Riwa_Planning::get_today_movements();
$arrivals_count     = count($today_movements['arrivals']);
$departures_count   = count($today_movements['departures']);

// Nonce planning (transmis au JS via wp_localize_script dans class-riwa-admin.php)
$planning_nonce = wp_create_nonce('riwa_planning_nonce');
?>

<div class="riwa-section" id="planning-section">
    <div class="riwa-section-header">
        <h2>Planning</h2>
        <p>Visualisez et gérez vos disponibilités, blocages et prix en temps réel</p>
    </div>
    <div class="riwa-section-content">

        <!-- ── Alertes du jour ───────────────────────────────────────── -->
        <?php if ($arrivals_count > 0 || $departures_count > 0): ?>
        <div class="riwa-planning-alerts">
            <?php if ($arrivals_count > 0): ?>
            <div class="riwa-planning-alert riwa-alert--arrival">
                <span class="dashicons dashicons-migrate"></span>
                <strong><?php echo $arrivals_count; ?> arrivée<?php echo $arrivals_count > 1 ? 's' : ''; ?> aujourd'hui</strong>
                <div class="riwa-alert-guests">
                    <?php foreach ($today_movements['arrivals'] as $a): ?>
                        <span class="riwa-alert-guest-chip riwa-chip-status--<?php echo esc_attr($a->status); ?>">
                            <?php echo esc_html($a->guest_name); ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($departures_count > 0): ?>
            <div class="riwa-planning-alert riwa-alert--departure">
                <span class="dashicons dashicons-undo"></span>
                <strong><?php echo $departures_count; ?> départ<?php echo $departures_count > 1 ? 's' : ''; ?> aujourd'hui</strong>
                <div class="riwa-alert-guests">
                    <?php foreach ($today_movements['departures'] as $d): ?>
                        <span class="riwa-alert-guest-chip riwa-housekeeping--<?php echo esc_attr($d->housekeeping_status); ?>">
                            <?php echo esc_html($d->guest_name); ?>
                            <span class="riwa-alert-hk-dot" title="<?php echo esc_attr($housekeeping_labels[$d->housekeeping_status] ?? ''); ?>"></span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Barre d'outils ────────────────────────────────────────── -->
        <div class="riwa-planning-toolbar">
            <div class="riwa-planning-toolbar-left">
                <!-- Navigation période -->
                <button type="button" class="riwa-toolbar-btn" id="plan-prev">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                </button>
                <button type="button" class="riwa-toolbar-btn" id="plan-today">Aujourd'hui</button>
                <button type="button" class="riwa-toolbar-btn" id="plan-next">
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
                <span class="riwa-planning-period-label" id="plan-period-label">—</span>
            </div>
            <div class="riwa-planning-toolbar-center">
                <!-- Sélecteur de vue -->
                <div class="riwa-view-tabs" id="plan-view-tabs">
                    <button type="button" class="riwa-view-tab active" data-view="2weeks">2 semaines</button>
                    <button type="button" class="riwa-view-tab" data-view="month">Mois</button>
                    <button type="button" class="riwa-view-tab" data-view="week">Semaine</button>
                </div>
            </div>
            <div class="riwa-planning-toolbar-right">
                <!-- Bouton Bloquer une date -->
                <div class="riwa-upsell-dropdown-wrap" id="riwa-block-dropdown-wrap">
                    <button type="button" class="riwa-toolbar-btn" id="riwa-block-open-btn">
                        <span class="dashicons dashicons-lock"></span>
                        Bloquer
                    </button>
                    <div id="riwa-block-form-dropdown" class="riwa-upsell-form-dropdown" style="display:none;">
                        <div class="riwa-upsell-form-dropdown-header">
                            <span>Bloquer des dates</span>
                            <button type="button" class="riwa-upsell-dropdown-close" id="riwa-block-close">&#x2715;</button>
                        </div>
                        <div class="riwa-upsell-form-dropdown-body" style="padding:1rem 1.1rem;">
                            <div class="riwa-form-row">
                                <label class="riwa-form-label">Du</label>
                                <input type="date" id="block-date-start" class="riwa-form-input">
                            </div>
                            <div class="riwa-form-row">
                                <label class="riwa-form-label">Au (exclu)</label>
                                <input type="date" id="block-date-end" class="riwa-form-input">
                            </div>
                            <div class="riwa-form-row">
                                <label class="riwa-form-label">Raison</label>
                                <select id="block-reason" class="riwa-form-input">
                                    <?php foreach ($reason_labels as $k => $l): ?>
                                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="riwa-form-row">
                                <label class="riwa-form-label">Note (optionnel)</label>
                                <input type="text" id="block-note" class="riwa-form-input" placeholder="Ex : Visite propriétaire">
                            </div>
                        </div>
                        <div class="riwa-upsell-form-dropdown-footer">
                            <button type="button" class="riwa-btn riwa-btn-primary" id="riwa-block-save-btn">
                                <span class="dashicons dashicons-lock"></span> Bloquer
                            </button>
                            <button type="button" class="riwa-btn riwa-btn-secondary" id="riwa-block-cancel-btn">Annuler</button>
                        </div>
                    </div>
                </div>

                <!-- Boutons démo -->
                <button type="button" class="riwa-toolbar-btn riwa-demo-seed-btn" id="riwa-demo-seed-btn"
                        title="Injecter des données de démonstration en base">
                    <span class="dashicons dashicons-database-add"></span>
                    Données démo
                </button>
                <button type="button" class="riwa-toolbar-btn riwa-demo-clear-btn" id="riwa-demo-clear-btn"
                        title="Supprimer toutes les données [DEMO] de la base" style="display:none;">
                    <span class="dashicons dashicons-database-remove"></span>
                    Effacer démo
                </button>
            </div>
        </div>

        <!-- ── Indicateur d'occupation ───────────────────────────────── -->
        <div class="riwa-occ-bar" id="riwa-occ-bar">
            <div class="riwa-occ-stats">
                <div class="riwa-occ-stat">
                    <span class="riwa-occ-stat-value" id="occ-rate">—</span>
                    <span class="riwa-occ-stat-label">Taux d'occupation</span>
                </div>
                <div class="riwa-occ-stat">
                    <span class="riwa-occ-stat-value" id="occ-nights">—</span>
                    <span class="riwa-occ-stat-label">Nuits réservées</span>
                </div>
                <div class="riwa-occ-stat">
                    <span class="riwa-occ-stat-value" id="occ-empty">—</span>
                    <span class="riwa-occ-stat-label">Nuits libres</span>
                </div>
                <div class="riwa-occ-stat">
                    <span class="riwa-occ-stat-value riwa-occ-revenue" id="occ-revenue">—</span>
                    <span class="riwa-occ-stat-label">Revenus confirmés</span>
                </div>
                <div class="riwa-occ-stat">
                    <span class="riwa-occ-stat-value riwa-occ-potential" id="occ-potential">—</span>
                    <span class="riwa-occ-stat-label">Revenus potentiels</span>
                </div>
            </div>
            <div class="riwa-occ-progress">
                <div class="riwa-occ-progress-fill" id="occ-progress-fill" style="width:0%"></div>
            </div>
        </div>

        <!-- ── Timeline calendrier ───────────────────────────────────── -->
        <div id="riwa-timeline-cal">
            <div class="riwa-planning-loader" id="riwa-planning-loader">
                <span class="dashicons dashicons-update-alt riwa-spin"></span>
                Chargement du planning…
            </div>
            <div id="riwa-timeline-render"></div>
        </div>

        <!-- ── Légende ───────────────────────────────────────────────── -->
        <div class="riwa-planning-legend">
            <span class="riwa-legend-item"><span class="riwa-legend-dot riwa-dot--confirmed"></span> Confirmée</span>
            <span class="riwa-legend-item"><span class="riwa-legend-dot riwa-dot--pending"></span> En attente</span>
            <span class="riwa-legend-item"><span class="riwa-legend-dot riwa-dot--blocked"></span> Bloqué</span>
            <span class="riwa-legend-item"><span class="riwa-legend-dot riwa-dot--price"></span> Prix spécial</span>
            <span class="riwa-legend-item"><span class="riwa-legend-dot riwa-dot--hk-pending"></span> À nettoyer</span>
        </div>

        <!-- ── Historique d'activité ──────────────────────────────────── -->
        <div class="riwa-activity-log" id="riwa-activity-log" style="display:none;">
            <div class="riwa-activity-log-header">
                <span class="dashicons dashicons-backup"></span>
                <span id="riwa-activity-log-title">Activité de la période</span>
                <span class="riwa-activity-log-count" id="riwa-activity-log-count"></span>
            </div>
            <div class="riwa-activity-log-body" id="riwa-activity-log-body"></div>
        </div>

    </div>
</div>

<!-- Input hidden pour nonce planning -->
<input type="hidden" id="riwa-planning-nonce" value="<?php echo esc_attr($planning_nonce); ?>">

<!-- Raisons (pour le JS) -->
<script>
var riwa_planning_reasons = <?php echo wp_json_encode($reason_labels); ?>;
var riwa_planning_hk      = <?php echo wp_json_encode($housekeeping_labels); ?>;
</script>

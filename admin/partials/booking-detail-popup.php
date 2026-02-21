<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="riwa-details-popup" class="riwa-popup-overlay">
    <div class="riwa-popup-container">

        <!-- Sidebar gauche -->
        <div class="riwa-popup-sidebar">
            <div class="riwa-popup-sidebar-header">
                <span class="riwa-popup-sidebar-icon dashicons dashicons-calendar-alt"></span>
                <div>
                    <div class="riwa-popup-sidebar-ref" id="popup-reference">—</div>
                    <div class="riwa-popup-sidebar-label">Réservation</div>
                </div>
            </div>

            <div class="riwa-popup-sidebar-steps">

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-admin-users"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Client</div>
                        <div class="riwa-popup-step-value" id="popup-client-name">—</div>
                        <div class="riwa-popup-step-sub" id="popup-client-email">—</div>
                        <div class="riwa-popup-step-sub" id="popup-client-phone">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-location"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Séjour</div>
                        <div class="riwa-popup-step-value" id="popup-duration">—</div>
                        <div class="riwa-popup-step-sub" id="popup-dates-range">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-groups"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Voyageurs</div>
                        <div class="riwa-popup-travelers-inline" id="popup-travelers">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-money-alt"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Prix total</div>
                        <div class="riwa-popup-step-value riwa-popup-price" id="popup-total-price">—</div>
                        <div class="riwa-popup-step-sub" id="popup-price-per-night-label">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed" id="popup-upsells-step" style="display:none;">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-store"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Services additionnels</div>
                        <div id="popup-upsells-list" class="riwa-popup-upsells-list">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-format-chat"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Demandes spéciales</div>
                        <div class="riwa-popup-step-sub" id="popup-requests-sidebar">—</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Panneau droit -->
        <div class="riwa-popup-main">

            <!-- X fermer en haut à droite -->
            <button type="button" class="riwa-popup-close" id="riwa-popup-close" title="Fermer">&#x2715;</button>

            <div class="riwa-popup-main-header">
                <div>
                    <h3>Suivi de la réservation</h3>
                    <div class="riwa-popup-main-sub">
                        <span id="popup-booking-id-fmt">—</span>
                        &nbsp;·&nbsp;
                        <span id="popup-created">—</span>
                    </div>
                </div>
                <span class="riwa-popup-status-badge" id="popup-status-badge">—</span>
            </div>

            <div class="riwa-popup-main-content">

                <!-- Timeline -->
                <div class="riwa-timeline" id="popup-timeline">

                    <div class="riwa-timeline-item" data-step="submitted">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-edit"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Demande reçue</div>
                            <div class="riwa-timeline-desc">Le client a soumis sa demande de réservation</div>
                            <div class="riwa-timeline-date" id="popup-submitted-date">—</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" data-step="confirmed">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-yes-alt"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Réservation confirmée</div>
                            <div class="riwa-timeline-desc">La réservation a été acceptée et confirmée</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" data-step="checkin">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-migrate"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Arrivée</div>
                            <div class="riwa-timeline-desc">Check-in du client à la villa</div>
                            <div class="riwa-timeline-date" id="popup-checkin-timeline">—</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" data-step="staying">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-admin-home"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Séjour en cours</div>
                            <div class="riwa-timeline-desc" id="popup-staying-desc">Durée du séjour</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" data-step="checkout">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-undo"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Départ</div>
                            <div class="riwa-timeline-desc">Check-out et fin du séjour</div>
                            <div class="riwa-timeline-date" id="popup-checkout-timeline">—</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" data-step="done">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-awards"></span></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Séjour terminé</div>
                            <div class="riwa-timeline-desc">Merci pour votre confiance</div>
                        </div>
                    </div>

                </div>

                <!-- Actions -->
                <div class="riwa-popup-actions" id="popup-actions">
                    <!-- Rempli dynamiquement par JS selon le statut -->
                </div>

                <!-- Section WhatsApp -->
                <div class="riwa-popup-wa-section" id="popup-notif-wa" style="display:none;">
                    <div class="riwa-popup-wa-header">
                        <span class="riwa-popup-wa-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                        </span>
                        <span>WhatsApp</span>
                    </div>
                    <div class="riwa-popup-wa-btns" id="popup-wa-btns">
                        <button class="riwa-wa-btn" data-tpl="confirmation" data-target="client">Confirmation</button>
                        <button class="riwa-wa-btn" data-tpl="reminder"     data-target="client">Rappel</button>
                        <button class="riwa-wa-btn" data-tpl="checkin"      data-target="client">Infos arrivée</button>
                        <button class="riwa-wa-btn" data-tpl="review"       data-target="client">Demande avis</button>
                    </div>
                    <div class="riwa-popup-notif-log" id="popup-notif-log">
                        <!-- Historique chargé en AJAX -->
                    </div>
                </div>

            </div>

            <!-- Footer minimaliste : dates + prix -->
            <div class="riwa-popup-main-footer">
                <div class="riwa-popup-footer-info">
                    <span id="popup-checkin-footer">—</span>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <span id="popup-checkout-footer">—</span>
                    &nbsp;·&nbsp;
                    <strong id="popup-total-price-footer">—</strong>
                </div>
            </div>

        </div>
    </div>
</div>

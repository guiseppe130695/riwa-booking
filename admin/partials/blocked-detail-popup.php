<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="riwa-blocked-popup" class="riwa-popup-overlay">
    <div class="riwa-popup-container">

        <!-- Sidebar gauche -->
        <div class="riwa-popup-sidebar">
            <div class="riwa-popup-sidebar-header">
                <span class="riwa-popup-sidebar-icon dashicons dashicons-lock"></span>
                <div>
                    <div class="riwa-popup-sidebar-ref" id="bl-popup-reason">—</div>
                    <div class="riwa-popup-sidebar-label">Date bloquée</div>
                </div>
            </div>

            <div class="riwa-popup-sidebar-steps">

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Période</div>
                        <div class="riwa-popup-step-value" id="bl-popup-duration">—</div>
                        <div class="riwa-popup-step-sub" id="bl-popup-dates-range">—</div>
                    </div>
                </div>

                <div class="riwa-popup-step completed">
                    <div class="riwa-popup-step-icon"><span class="dashicons dashicons-editor-quote"></span></div>
                    <div class="riwa-popup-step-body">
                        <div class="riwa-popup-step-title">Note</div>
                        <div class="riwa-popup-step-sub" id="bl-popup-note">—</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Panneau droit -->
        <div class="riwa-popup-main">

            <button type="button" class="riwa-popup-close" id="riwa-blocked-popup-close" title="Fermer">&#x2715;</button>

            <div class="riwa-popup-main-header">
                <div>
                    <h3>Détail du blocage</h3>
                    <div class="riwa-popup-main-sub" id="bl-popup-sub">—</div>
                </div>
                <span class="riwa-popup-status-badge status-blocked">Bloqué</span>
            </div>

            <div class="riwa-popup-main-content">

                <!-- Timeline blocage -->
                <div class="riwa-timeline" id="bl-popup-timeline">

                    <div class="riwa-timeline-item tl-done">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-lock"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Blocage créé</div>
                            <div class="riwa-timeline-desc">Ces dates ont été marquées comme indisponibles</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item" id="bl-tl-active">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-calendar-alt"></span></div>
                        <div class="riwa-timeline-connector"></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label" id="bl-tl-label">Période</div>
                            <div class="riwa-timeline-desc" id="bl-tl-desc">—</div>
                        </div>
                    </div>

                    <div class="riwa-timeline-item">
                        <div class="riwa-timeline-dot"><span class="dashicons dashicons-yes-alt"></span></div>
                        <div class="riwa-timeline-body">
                            <div class="riwa-timeline-label">Dates libérées</div>
                            <div class="riwa-timeline-desc">Le blocage sera supprimé manuellement</div>
                        </div>
                    </div>

                </div>

                <!-- Actions -->
                <div class="riwa-popup-actions" id="bl-popup-actions">
                    <button type="button" class="riwa-popup-action-btn delete riwa-planning-delete-blocked-btn" id="bl-popup-delete-btn" data-id="">
                        <span class="dashicons dashicons-trash"></span> Débloquer / Supprimer
                    </button>
                </div>

            </div>

            <!-- Footer -->
            <div class="riwa-popup-main-footer">
                <div class="riwa-popup-footer-info">
                    <span id="bl-popup-footer-start">—</span>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                    <span id="bl-popup-footer-end">—</span>
                    &nbsp;·&nbsp;
                    <strong id="bl-popup-footer-nights">—</strong>
                </div>
            </div>

        </div>
    </div>
</div>

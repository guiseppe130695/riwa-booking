<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Popup des détails de réservation (rempli par riwa-booking-admin.js) -->
<div id="riwa-details-popup" class="riwa-popup-overlay">
    <div class="riwa-popup-container">
        <div class="riwa-popup-header">
            <h3>Détails de la réservation</h3>
            <button type="button" class="riwa-popup-close" id="riwa-popup-close">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="riwa-popup-content">
            <div class="riwa-popup-section">
                <h4>Informations de réservation</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Référence</span>
                        <span class="riwa-popup-value" id="popup-reference"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">ID</span>
                        <span class="riwa-popup-value" id="popup-booking-id"></span>
                    </div>
                </div>
            </div>

            <div class="riwa-popup-section">
                <h4>Informations client</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Nom</span>
                        <span class="riwa-popup-value" id="popup-client-name"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Email</span>
                        <span class="riwa-popup-value" id="popup-client-email"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Téléphone</span>
                        <span class="riwa-popup-value" id="popup-client-phone"></span>
                    </div>
                </div>
            </div>

            <div class="riwa-popup-section">
                <h4>Détails du séjour</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date d'arrivée</span>
                        <span class="riwa-popup-value" id="popup-checkin"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date de départ</span>
                        <span class="riwa-popup-value" id="popup-checkout"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Durée</span>
                        <span class="riwa-popup-value" id="popup-duration"></span>
                    </div>
                </div>
            </div>

            <div class="riwa-popup-section">
                <h4>Composition des voyageurs</h4>
                <div class="riwa-popup-travelers" id="popup-travelers"></div>
            </div>

            <div class="riwa-popup-section">
                <h4>Informations tarifaires</h4>
                <div class="riwa-popup-grid">
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Prix total</span>
                        <span class="riwa-popup-value" id="popup-total-price"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Prix par nuit</span>
                        <span class="riwa-popup-value" id="popup-price-per-night"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Statut</span>
                        <span class="riwa-popup-value" id="popup-status"></span>
                    </div>
                    <div class="riwa-popup-item">
                        <span class="riwa-popup-label">Date de création</span>
                        <span class="riwa-popup-value" id="popup-created"></span>
                    </div>
                </div>
            </div>

            <div class="riwa-popup-section">
                <h4>Demandes spéciales</h4>
                <div class="riwa-popup-requests" id="popup-requests"></div>
            </div>
        </div>
        <div class="riwa-popup-footer">
            <button type="button" class="riwa-btn riwa-btn-secondary" id="riwa-popup-close-btn">Fermer</button>
        </div>
    </div>
</div>

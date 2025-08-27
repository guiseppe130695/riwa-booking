<?php
// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="riwa-booking-container">
    <h2><?php echo esc_html($atts['title']); ?></h2>
    
    <!-- Indicateur de progression -->
    <div class="riwa-progress-bar">
        <div class="riwa-progress-step active" data-step="1">
            <span class="riwa-step-number">1</span>
            <span class="riwa-step-label">Dates</span>
        </div>
        <div class="riwa-progress-step" data-step="2">
            <span class="riwa-step-number">2</span>
            <span class="riwa-step-label">Voyageurs</span>
        </div>
        <div class="riwa-progress-step" data-step="3">
            <span class="riwa-step-number">3</span>
            <span class="riwa-step-label">Informations</span>
        </div>
        <div class="riwa-progress-step" data-step="4">
            <span class="riwa-step-number">4</span>
            <span class="riwa-step-label">Confirmation</span>
        </div>
    </div>

    <!-- Messages -->
    <div id="riwa-booking-messages" style="display: none;">
        <div class="message-content"></div>
    </div>

    <form id="riwa-booking-form" class="riwa-form">
        <!-- Étape 1: Sélection des dates -->
        <div class="riwa-step active" data-step="1">
            <h2>Sélectionnez vos dates</h2>
            <div id="riwa-calendar"></div>
            
            <!-- Légende du calendrier -->
            <div class="riwa-footer-dates">

                <div class="riwa-selected-dates">
                        <label>Arrivée :</label>
                        <span id="selected-checkin">Non sélectionnée</span>
                        <input type="hidden" id="riwa-check-in" name="check_in_date">
                   
                        <label>Départ :</label>
                        <span id="selected-checkout">Non sélectionnée</span>
                        <input type="hidden" id="riwa-check-out" name="check_out_date">
                </div>
            </div>
            
            <div class="riwa-error-message" id="riwa-dates-error"></div>
            <div class="riwa-step-buttons">
                <button type="button" class="riwa-next-btn" data-next="2">Suivant</button>
            </div>
        </div>

                 <!-- Étape 2: Voyageurs -->
         <div class="riwa-step" data-step="2" id="riwa-travelers-step">
             <h2>Voyageurs</h2>
             <div class="riwa-travelers-section">
                 <!-- Adultes -->
                 <div class="riwa-traveler-type">
                     <div class="riwa-traveler-info">
                         <div class="riwa-traveler-title">Adultes</div>
                         <div class="riwa-traveler-subtitle">13 ans et plus (max 6)</div>
                     </div>
                     <div class="riwa-counter">
                         <button type="button" class="riwa-counter-btn" data-action="decrease" data-type="adults">-</button>
                         <span class="riwa-counter-value" data-type="adults">1</span>
                         <button type="button" class="riwa-counter-btn" data-action="increase" data-type="adults">+</button>
                     </div>
                 </div>

                                   <!-- Enfants -->
                  <div class="riwa-traveler-type">
                      <div class="riwa-traveler-info">
                          <div class="riwa-traveler-title">Enfants</div>
                          <div class="riwa-traveler-subtitle">De 2 à 12 ans</div>
                      </div>
                      <div class="riwa-counter">
                          <button type="button" class="riwa-counter-btn" data-action="decrease" data-type="children">-</button>
                          <span class="riwa-counter-value" data-type="children">0</span>
                          <button type="button" class="riwa-counter-btn" data-action="increase" data-type="children">+</button>
                      </div>
                  </div>

                  <!-- Bébés -->
                  <div class="riwa-traveler-type">
                      <div class="riwa-traveler-info">
                          <div class="riwa-traveler-title">Bébés</div>
                          <div class="riwa-traveler-subtitle">Moins de 2 ans</div>
                      </div>
                      <div class="riwa-counter">
                          <button type="button" class="riwa-counter-btn" data-action="decrease" data-type="babies">-</button>
                          <span class="riwa-counter-value" data-type="babies">0</span>
                          <button type="button" class="riwa-counter-btn" data-action="increase" data-type="babies">+</button>
                      </div>
                  </div>

             </div>
            <div class="riwa-error-message" id="riwa-travelers-error"></div>
            <div class="riwa-step-buttons">
                <button type="button" class="riwa-prev-btn" data-prev="1">Précédent</button>
                <button type="button" class="riwa-next-btn" data-next="3">Suivant</button>
            </div>
        </div>

        <!-- Étape 3: Informations -->
        <div class="riwa-step" data-step="3" id="riwa-info-step">
            <h2>Vos informations</h2>
            <div class="form-group-container">
                <div class="riwa-form-group">
                    <input type="text" id="riwa-guest-first-name" name="guest_first_name" placeholder="Prénom" required aria-label="Prénom">
                </div>
                <div class="riwa-form-group">
                    <input type="text" id="riwa-guest-last-name" name="guest_last_name" placeholder="Nom" required aria-label="Nom">
                </div>
                <div class="riwa-form-group">
                    <input type="text" id="riwa-guest-company" name="guest_company" placeholder="Société (optionnel)" aria-label="Société">
                </div>
                <div class="riwa-form-group">
                    <input type="email" id="riwa-guest-email" name="guest_email" placeholder="Email professionnel" required aria-label="Email professionnel">
                </div>
                <div class="riwa-form-group">
                    <input type="tel" id="riwa-guest-phone" name="guest_phone" placeholder="Téléphone" required aria-label="Téléphone">
                </div>
                <div class="riwa-form-group">
                    <textarea id="riwa-special-requests" name="special_requests" placeholder="Demandes spéciales ou commentaires" rows="3" aria-label="Demandes spéciales"></textarea>
                </div>
            </div>
            <div class="riwa-error-message" id="riwa-info-error"></div>
            <div class="riwa-step-buttons">
                <button type="button" class="riwa-prev-btn" data-prev="2">Précédent</button>
                <button type="button" class="riwa-next-btn" data-next="4">Suivant</button>
            </div>
        </div>

        <!-- Étape 4: Récapitulatif -->
        <div class="riwa-step" data-step="4" id="riwa-summary-step">
            
            <div class="booking-summary">
                <div class="summary-single-section">
                    <div class="summary-header">
                        <h4>Récapitulatif de votre réservation</h4>
                    </div>
                    <div class="summary-content">
                        <!-- Dates -->
                        <div class="summary-row">
                            <div class="summary-label">Arrivée</div>
                            <div class="summary-value summary-checkin">Non sélectionnée</div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Départ</div>
                            <div class="summary-value summary-checkout">Non sélectionnée</div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Durée</div>
                            <div class="summary-value" id="summary-duration">0 nuits</div>
                        </div>
                        
                        <!-- Voyageurs -->
                        <div class="summary-row">
                            <div class="summary-label">Adultes</div>
                            <div class="summary-value"><span id="summary-adults">1</span></div>
                        </div>
                        <div class="summary-row">
                            <div class="summary-label">Enfants</div>
                            <div class="summary-value"><span id="summary-children">0</span></div>
                        </div>
                                                 <div class="summary-row">
                             <div class="summary-label">Bébés</div>
                             <div class="summary-value"><span id="summary-babies">0</span></div>
                         </div>
                        
                        <!-- Tarification -->
                        <div class="summary-row">
                            <div class="summary-label">Prix par nuit</div>
                            <div class="summary-value" id="summary-price-per-night">0 €</div>
                        </div>
                        <div class="summary-row summary-total-row">
                            <div class="summary-label">Total</div>
                            <div class="summary-value" id="summary-total-price">0 €</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="riwa-error-message" id="riwa-summary-error"></div>
            <div class="riwa-step-buttons">
                <button type="button" class="riwa-prev-btn" data-prev="3">Précédent</button>
                <button type="submit" class="riwa-submit-btn">Confirmer la réservation</button>
            </div>
        </div>

        <!-- Champ caché pour le nom complet -->
        <input type="hidden" name="guest_name" id="riwa-guest-name">
        
        <?php wp_nonce_field('riwa_booking_nonce', 'nonce'); ?>
    </form>

    <!-- Page de remerciement -->
    <div id="riwa-thank-you-page" class="riwa-thank-you" style="display: none;">
        <!-- Animation de chargement -->
        <div class="riwa-loading-animation">
            <div class="riwa-spinner"></div>
            <p>Traitement de votre réservation...</p>
        </div>
        
        <!-- Contenu de remerciement -->
        <div class="riwa-thank-you-content" style="display: none;">
            <div class="riwa-success-icon">
                <svg width="60" height="60" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/>
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h2>Réservation confirmée</h2>
            <p class="riwa-thank-you-message">
                Merci pour votre réservation. Nous avons bien reçu votre demande et nous vous contacterons dans les plus brefs délais pour confirmer les détails.
            </p>
            <div class="riwa-booking-details">
                <h3>Détails de votre réservation</h3>
                <div class="riwa-booking-info">
                    <div class="riwa-info-row">
                        <span class="riwa-info-label">Référence</span>
                        <span class="riwa-info-value" id="riwa-booking-ref">-</span>
                    </div>
                    <div class="riwa-info-row">
                        <span class="riwa-info-label">Dates</span>
                        <span class="riwa-info-value" id="riwa-booking-dates">-</span>
                    </div>
                    <div class="riwa-info-row">
                        <span class="riwa-info-label">Voyageurs</span>
                        <span class="riwa-info-value" id="riwa-booking-guests">-</span>
                    </div>
                    <div class="riwa-info-row">
                        <span class="riwa-info-label">Total</span>
                        <span class="riwa-info-value" id="riwa-booking-total">-</span>
                    </div>
                </div>
            </div>
            <div class="riwa-thank-you-actions">
                <button type="button" class="riwa-download-pdf-btn" id="riwa-download-pdf" style="margin-right: 1rem;">Télécharger la confirmation</button>
                <button type="button" class="riwa-new-booking-btn" onclick="location.reload()">Nouvelle réservation</button>
            </div>
        </div>
    </div>
</div> 
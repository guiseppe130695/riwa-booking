/**
 * Riwa Booking - JavaScript Frontend
 * Gestion du formulaire de réservation avec calendrier et AJAX
 */

jQuery(document).ready(function($) {
    // Vérifier que Flatpickr est disponible
    if (typeof flatpickr === 'undefined') {
        console.error('Riwa Booking: Flatpickr n\'est pas chargé !');
        return;
    }
    
    // Flatpickr disponible
    
    // Configuration des compteurs de voyageurs
    const travelersConfig = {
        adults: { min: 1, max: 10, default: 1 },
        children: { min: 0, max: 6, default: 0 },
        babies: { min: 0, max: 4, default: 0 },
        pets: { min: 0, max: 2, default: 0 }
    };

    // Données de tarification et réservations
    let pricingData = riwa_ajax.pricing || [];
    let bookedDates = [];

    // Récupérer les dates réservées
    function loadBookedDates() {
        $.ajax({
            url: riwa_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'riwa_get_booked_dates',
                nonce: riwa_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    bookedDates = response.data;
                    updateCalendar();
                }
            },
            error: function(xhr, status, error) {
                // Erreur lors du chargement des dates réservées
            }
        });
    }

    // Initialisation du calendrier avec prix et dates réservées
    function initCalendar() {
        
        const calendar = flatpickr("#riwa-calendar", {
            locale: 'fr',
            mode: 'range',
            dateFormat: 'Y-m-d',
            minDate: 'today',
            inline: true,
            showMonths: 2,
            disableMobile: true,
            disable: bookedDates, // Désactiver les dates réservées
            onChange: function(selectedDates) {
                if (selectedDates.length === 2) {
                    const [checkIn, checkOut] = selectedDates;
                    $('#riwa-check-in').val(formatDateYMD(checkIn));
                    $('#riwa-check-out').val(formatDateYMD(checkOut));
                    $('#selected-checkin').text(formatDateFR(checkIn));
                    $('#selected-checkout').text(formatDateFR(checkOut));
                    updateSummary();
                }
            },
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                // Ajouter le prix sur chaque jour
                const dateStr = dayElem.dateObj.toISOString().split('T')[0];
                const price = getPriceForDate(dateStr);
                
                if (price) {
                    const priceElement = document.createElement('div');
                    priceElement.className = 'day-price';
                    priceElement.textContent = price + '€';
                    dayElem.appendChild(priceElement);
                }

                // Marquer les dates réservées visuellement
                if (isDateBooked(dateStr)) {
                    dayElem.classList.add('booked');
                    dayElem.setAttribute('title', 'Date non disponible');
                }
            }
        });

        return calendar;
    }

    // Obtenir le prix pour une date donnée
    function getPriceForDate(dateStr) {
        for (const season of pricingData) {
            if (dateStr >= season.start_date && dateStr <= season.end_date) {
                return season.price_per_night;
            }
        }
        return null;
    }

    // Obtenir le nom de la saison pour une date donnée
    function getSeasonName(dateStr) {
        for (const season of pricingData) {
            if (dateStr >= season.start_date && dateStr <= season.end_date) {
                return season.name;
            }
        }
        return null;
    }

    // Vérifier si une date est réservée
    function isDateBooked(dateStr) {
        return bookedDates.includes(dateStr);
    }

    // Mettre à jour le calendrier
    function updateCalendar() {
        // Recharger le calendrier si nécessaire
        if (window.riwaCalendar) {
            window.riwaCalendar.destroy();
        }
        window.riwaCalendar = initCalendar();
    }

    // Fonctions de formatage des dates
    function formatDateYMD(date) {
        return date.toISOString().split('T')[0];
    }

    function formatDateFR(date) {
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Navigation entre les étapes
    function showStep(stepNumber) {
        $('.riwa-step').removeClass('active');
        const targetStep = $(`.riwa-step[data-step="${stepNumber}"]`);
        targetStep.addClass('active');
        
        // Mise à jour de la barre de progression
        $('.riwa-progress-step').removeClass('active completed');
        $(`.riwa-progress-step[data-step="${stepNumber}"]`).addClass('active');
        for (let i = 1; i < stepNumber; i++) {
            $(`.riwa-progress-step[data-step="${i}"]`).addClass('completed');
        }
    }

    // Validation des étapes
    function validateStep(stepNumber) {
        const errorElement = $(`#riwa-${getStepId(stepNumber)}-error`);
        errorElement.removeClass('show');

        switch (stepNumber) {
            case 1: // Dates
                const checkIn = $('#riwa-check-in').val();
                const checkOut = $('#riwa-check-out').val();
                if (!checkIn || !checkOut) {
                    errorElement.text('Veuillez sélectionner vos dates').addClass('show');
                    return false;
                }
                
                // Vérifier si les dates sélectionnées sont disponibles
                if (!areDatesAvailable(checkIn, checkOut)) {
                    errorElement.text('Certaines dates sélectionnées ne sont pas disponibles').addClass('show');
                    return false;
                }
                return true;

            case 2: // Voyageurs
                const adults = getCurrentValue('adults');
                const children = getCurrentValue('children');
                const babies = getCurrentValue('babies');
                const total = adults + children + babies;
                
                if (adults < 1) {
                    errorElement.text('Il doit y avoir au moins un adulte').addClass('show');
                    return false;
                }
                if (total > 12) {
                    errorElement.text('Le nombre total de voyageurs ne peut pas dépasser 12 personnes').addClass('show');
                    return false;
                }
                return true;

            case 3: // Informations
                const firstName = $('#riwa-guest-first-name').val();
                const lastName = $('#riwa-guest-last-name').val();
                const email = $('#riwa-guest-email').val();
                const phone = $('#riwa-guest-phone').val();
                
                if (!firstName || !lastName || !email || !phone) {
                    errorElement.text('Veuillez remplir tous les champs obligatoires').addClass('show');
                    return false;
                }
                if (!isValidEmail(email)) {
                    errorElement.text('Veuillez entrer une adresse email valide').addClass('show');
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    // Vérifier si les dates sont disponibles
    function areDatesAvailable(checkIn, checkOut) {
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        const current = new Date(start);
        
        while (current < end) {
            const dateStr = current.toISOString().split('T')[0];
            if (isDateBooked(dateStr)) {
                return false;
            }
            current.setDate(current.getDate() + 1);
        }
        return true;
    }

    function getStepId(stepNumber) {
        const steps = ['dates', 'travelers', 'info', 'summary'];
        return steps[stepNumber - 1];
    }

    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Gestion des compteurs de voyageurs
    function initTravelersCounters() {
        $('.riwa-counter').each(function() {
            const type = $(this).find('.riwa-counter-value').data('type');
            const value = travelersConfig[type].default;
            updateCounter(type, value);
            updateButtonStates(type);
        });

        $('.riwa-counter-btn').on('click', function() {
            const type = $(this).data('type');
            const action = $(this).data('action');
            const currentValue = getCurrentValue(type);
            
            if (action === 'increase' && currentValue < travelersConfig[type].max) {
                updateCounter(type, currentValue + 1);
            } else if (action === 'decrease' && currentValue > travelersConfig[type].min) {
                updateCounter(type, currentValue - 1);
            }
            
            updateButtonStates(type);
            updateSummary();
        });
    }

    function updateCounter(type, value) {
        $(`.riwa-counter-value[data-type="${type}"]`).text(value);
        updateHiddenFields(type, value);
    }

    function updateButtonStates(type) {
        const currentValue = getCurrentValue(type);
        const decreaseBtn = $(`.riwa-counter-btn[data-type="${type}"][data-action="decrease"]`);
        const increaseBtn = $(`.riwa-counter-btn[data-type="${type}"][data-action="increase"]`);
        
        decreaseBtn.prop('disabled', currentValue <= travelersConfig[type].min);
        increaseBtn.prop('disabled', currentValue >= travelersConfig[type].max);
    }

    function getCurrentValue(type) {
        return parseInt($(`.riwa-counter-value[data-type="${type}"]`).text()) || travelersConfig[type].default;
    }

    function updateHiddenFields(type, value) {
        let input = $(`input[name="${type}_count"]`);
        if (!input.length) {
            input = $('<input>').attr({
                type: 'hidden',
                name: `${type}_count`
            }).appendTo('#riwa-booking-form');
        }
        input.val(value);
    }

    // Mise à jour du récapitulatif
    function updateSummary() {
        const checkIn = $('#riwa-check-in').val();
        const checkOut = $('#riwa-check-out').val();
        const adults = getCurrentValue('adults');
        const children = getCurrentValue('children');
        const babies = getCurrentValue('babies');
        const pets = getCurrentValue('pets');

        // Mise à jour des dates
        $('.summary-checkin').text(checkIn ? formatDateFR(new Date(checkIn)) : 'Non sélectionnée');
        $('.summary-checkout').text(checkOut ? formatDateFR(new Date(checkOut)) : 'Non sélectionnée');
        $('#summary-duration').text(calculateDuration(checkIn, checkOut));
        
        // Mise à jour des voyageurs
        $('#summary-adults').text(adults);
        $('#summary-children').text(children);
        $('#summary-babies').text(babies);
        $('#summary-pets').text(pets);

        // Mise à jour des prix
        updatePricing(checkIn, checkOut, adults + children);
        
        // Mettre à jour le champ caché du nom complet
        updateGuestName();
    }
    
    // Mettre à jour le nom complet du client
    function updateGuestName() {
        const firstName = $('#riwa-guest-first-name').val() || '';
        const lastName = $('#riwa-guest-last-name').val() || '';
        const fullName = (firstName + ' ' + lastName).trim();
        $('#riwa-guest-name').val(fullName);
    }

    function calculateDuration(checkIn, checkOut) {
        if (!checkIn || !checkOut) return '0 nuits';
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        const nights = Math.round((end - start) / (1000 * 60 * 60 * 24));
        return `${nights} nuit${nights > 1 ? 's' : ''}`;
    }

    function updatePricing(checkIn, checkOut, totalGuests) {
        if (!checkIn || !checkOut) {
            $('#summary-price-per-night').text('0 €');
            $('#summary-total-price').text('0 €');
            return;
        }

        // Utiliser les données de tarification saisonnière depuis PHP
        const pricingData = riwa_ajax.pricing || [];
        const nights = Math.round((new Date(checkOut) - new Date(checkIn)) / (1000 * 60 * 60 * 24));
        
        console.log('Calcul des prix:', {
            checkIn,
            checkOut,
            nights,
            totalGuests,
            pricingData
        });
        
        let totalPrice = 0;
        let pricePerNight = 0;
        const currentDate = new Date(checkIn);

        // Calculer le prix pour chaque nuit en utilisant les saisons
        for (let i = 0; i < nights; i++) {
            const dateStr = currentDate.toISOString().split('T')[0];
            let nightPrice = 150; // Prix par défaut

            // Chercher le prix pour cette date dans les saisons
            for (const season of pricingData) {
                if (dateStr >= season.start_date && dateStr <= season.end_date) {
                    nightPrice = season.price_per_night;
                    break;
                }
            }

            totalPrice += nightPrice;
            currentDate.setDate(currentDate.getDate() + 1);
        }

        pricePerNight = totalPrice / nights;

        // Appliquer les suppléments pour les voyageurs supplémentaires
        const baseGuests = 2; // Gratuit pour 2 personnes
        const extraGuests = Math.max(0, totalGuests - baseGuests);
        const guestSurcharge = 20; // Supplément par personne supplémentaire
        const totalSurcharge = extraGuests * guestSurcharge * nights;

        totalPrice += totalSurcharge;
        pricePerNight += (totalSurcharge / nights);

        $('#summary-price-per-night').text(`${pricePerNight.toFixed(2)} €`);
        $('#summary-total-price').text(`${totalPrice.toFixed(2)} €`);
    }

    // Gestionnaires d'événements pour la navigation
    $('.riwa-next-btn').on('click', function() {
        const nextStep = parseInt($(this).data('next'));
        const currentStep = nextStep - 1;
        
        if (validateStep(currentStep)) {
            showStep(nextStep);
            updateSummary();
        }
    });

    $('.riwa-prev-btn').on('click', function() {
        const prevStep = parseInt($(this).data('prev'));
        showStep(prevStep);
    });

    // Soumission du formulaire
    $('#riwa-booking-form').on('submit', function(e) {
        e.preventDefault();
        
        if (!validateStep(3)) { // Valider l'étape des informations
            return;
        }

        // Afficher la page de remerciement avec animation de chargement
        showThankYouPage();

        const formData = new FormData(this);
        formData.append('action', 'riwa_submit_booking');
        formData.append('nonce', riwa_ajax.nonce);

        $.ajax({
            url: riwa_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Afficher le contenu de remerciement après 2 secondes
                    setTimeout(function() {
                        showThankYouContent(response.data);
                    }, 2000);
                } else {
                    hideThankYouPage();
                    $('#riwa-summary-error').text(response.data).addClass('show');
                }
            },
            error: function() {
                hideThankYouPage();
                $('#riwa-summary-error').text('Une erreur est survenue. Veuillez réessayer.').addClass('show');
            }
        });
    });

    // Afficher la page de remerciement avec animation de chargement
    function showThankYouPage() {
        $('#riwa-booking-form').hide();
        $('#riwa-thank-you-page').show();
        $('.riwa-loading-animation').show();
        $('.riwa-thank-you-content').hide();
    }

    // Masquer la page de remerciement
    function hideThankYouPage() {
        $('#riwa-thank-you-page').hide();
        $('#riwa-booking-form').show();
    }

    // Afficher le contenu de remerciement
    function showThankYouContent(response) {
        $('.riwa-loading-animation').hide();
        $('.riwa-thank-you-content').show();
        
        // Mettre à jour les détails de la réservation
        updateThankYouDetails();
        
        // Stocker l'ID de la réservation pour le PDF
        if (response.booking_id) {
            window.riwaBookingId = response.booking_id;
        }
    }

    // Mettre à jour les détails dans la page de remerciement
    function updateThankYouDetails() {
        const checkIn = $('#riwa-check-in').val();
        const checkOut = $('#riwa-check-out').val();
        const adults = getCurrentValue('adults');
        const children = getCurrentValue('children');
        const babies = getCurrentValue('babies');
        const pets = getCurrentValue('pets');
        const totalPrice = $('#summary-total-price').text();

        // Générer une référence de réservation
        const bookingRef = 'RIWA-' + Date.now().toString().slice(-6);
        
        $('#riwa-booking-ref').text(bookingRef);
        $('#riwa-booking-dates').text(
            checkIn && checkOut ? 
            `${formatDateFR(new Date(checkIn))} - ${formatDateFR(new Date(checkOut))}` : 
            '-'
        );
        $('#riwa-booking-guests').text(
            `${adults} adulte(s), ${children} enfant(s), ${babies} bébé(s), ${pets} animal/aux`
        );
        $('#riwa-booking-total').text(totalPrice);
    }

    // Gestionnaire pour les champs de nom
    $('#riwa-guest-first-name, #riwa-guest-last-name').on('input', function() {
        updateGuestName();
    });

    // Gestionnaire pour le téléchargement du PDF
    $('#riwa-download-pdf').on('click', function() {
        if (!window.riwaBookingId) {
            alert('Erreur: ID de réservation non trouvé');
            return;
        }

        // Désactiver le bouton pendant le téléchargement
        const $btn = $(this);
        $btn.prop('disabled', true).text('Génération de la confirmation...');

        // Créer un formulaire temporaire pour le téléchargement
        const form = $('<form>', {
            method: 'POST',
            action: riwa_ajax.ajax_url,
            target: '_blank'
        });

        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'riwa_download_pdf'
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'booking_id',
            value: window.riwaBookingId
        }));

        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: riwa_ajax.nonce
        }));

        // Ajouter le formulaire au DOM, le soumettre, puis le supprimer
        $('body').append(form);
        form.submit();
        form.remove();

        // Réactiver le bouton après un délai
        setTimeout(function() {
            $btn.prop('disabled', false).text('Télécharger la confirmation PDF');
        }, 3000);
    });



    // Initialisation
    // Charger d'abord les dates réservées, puis initialiser le calendrier
    loadBookedDates();
    initTravelersCounters();
    
    // Initialiser le calendrier après un court délai pour s'assurer que les dates sont chargées
    setTimeout(function() {
        window.riwaCalendar = initCalendar();
        updateSummary();
    }, 500);
    
    // Fallback : initialiser le calendrier même si les dates réservées ne se chargent pas
    setTimeout(function() {
        if (!window.riwaCalendar) {
            window.riwaCalendar = initCalendar();
            updateSummary();
        }
    }, 2000);
}); 
/**
 * Riwa Booking - JavaScript Administration
 * Gestion de l'interface d'administration des réservations
 */

(function($) {
    'use strict';

    // Attendre que le DOM soit chargé
    $(document).ready(function() {
        initRiwaBookingAdmin();
    });

    function initRiwaBookingAdmin() {
        // Gestion des détails des réservations
        initBookingDetails();
        
        // Confirmation des changements de statut
        initStatusChanges();
        
        // Animations et interactions
        initAnimations();
        
        // Filtres et recherche (pour futures améliorations)
        initFilters();
    }

    /**
     * Gestion de l'affichage des détails des réservations
     */
    function initBookingDetails() {
        $('.view-details').on('click', function(e) {
            e.preventDefault();
            
            const bookingId = $(this).data('booking-id');
            const $detailsRow = $('#details-' + bookingId);
            const $button = $(this);
            
            if ($detailsRow.is(':visible')) {
                // Masquer les détails
                $detailsRow.fadeOut(300);
                $button.text('Détails').removeClass('active');
            } else {
                // Masquer tous les autres détails d'abord
                $('.booking-details').fadeOut(300);
                $('.view-details').text('Détails').removeClass('active');
                
                // Afficher les détails de cette réservation
                setTimeout(function() {
                    $detailsRow.fadeIn(300);
                    $button.text('Masquer').addClass('active');
                    
                    // Faire défiler vers les détails
                    $('html, body').animate({
                        scrollTop: $detailsRow.offset().top - 100
                    }, 500);
                }, 100);
            }
        });
    }

    /**
     * Confirmation des changements de statut
     */
    function initStatusChanges() {
        $('select[name="new_status"]').on('change', function(e) {
            const $select = $(this);
            const newStatus = $select.val();
            const bookingId = $select.closest('form').find('input[name="booking_id"]').val();
            
            if (newStatus) {
                const statusLabels = {
                    'pending': 'En attente',
                    'confirmed': 'Confirmée',
                    'cancelled': 'Annulée'
                };
                
                const message = `Êtes-vous sûr de vouloir changer le statut de cette réservation vers "${statusLabels[newStatus]}" ?`;
                
                if (!confirm(message)) {
                    // Restaurer la valeur précédente
                    $select.val($select.data('previous-value') || '');
                    return false;
                }
                
                // Sauvegarder la nouvelle valeur
                $select.data('previous-value', newStatus);
                
                // Ajouter un indicateur de chargement
                $select.prop('disabled', true);
                $select.after('<span class="spinner is-active" style="float: none;"></span>');
            }
        });

        // Sauvegarder la valeur initiale
        $('select[name="new_status"]').each(function() {
            $(this).data('previous-value', $(this).val());
        });
    }

    /**
     * Animations et interactions améliorées
     */
    function initAnimations() {
        // Animation au survol des lignes du tableau
        $('.wp-list-table tbody tr').not('.booking-details').hover(
            function() {
                $(this).addClass('hover-effect');
            },
            function() {
                $(this).removeClass('hover-effect');
            }
        );

        // Animation des statistiques
        animateStats();
        
        // Tooltip pour les actions
        initTooltips();
    }

    /**
     * Animation des statistiques au chargement
     */
    function animateStats() {
        $('.stat-number').each(function() {
            const $this = $(this);
            const finalValue = parseInt($this.text());
            
            if (finalValue > 0) {
                $this.text('0');
                
                // Animation du compteur
                $({ counter: 0 }).animate({ counter: finalValue }, {
                    duration: 1500,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.ceil(this.counter));
                    },
                    complete: function() {
                        $this.text(finalValue);
                    }
                });
            }
        });
    }

    /**
     * Initialisation des tooltips
     */
    function initTooltips() {
        // Tooltip pour les boutons d'action
        $('.view-details').attr('title', 'Cliquez pour voir/masquer les détails de la réservation');
        
        // Tooltip pour les sélecteurs de statut
        $('select[name="new_status"]').attr('title', 'Changer le statut de cette réservation');
        
        // Tooltip pour les liens email et téléphone
        $('a[href^="mailto:"]').attr('title', 'Envoyer un email');
        $('a[href^="tel:"]').attr('title', 'Appeler ce numéro');
    }

    /**
     * Filtres et recherche (base pour futures améliorations)
     */
    function initFilters() {
        // Recherche rapide dans le tableau
        addQuickSearch();
        
        // Filtre par statut (pour futures améliorations)
        // initStatusFilter();
    }

    /**
     * Ajouter une recherche rapide
     */
    function addQuickSearch() {
        // Ajouter un champ de recherche avant le tableau
        const searchHTML = `
            <div class="riwa-search-container" style="margin-bottom: 20px;">
                <input type="text" id="riwa-search" placeholder="Rechercher par nom, email ou téléphone..." 
                       style="width: 300px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                <span class="riwa-search-results" style="margin-left: 10px; color: #666;"></span>
            </div>
        `;
        
        $('.wp-list-table').before(searchHTML);
        
        // Fonctionnalité de recherche
        $('#riwa-search').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $rows = $('.wp-list-table tbody tr').not('.booking-details');
            let visibleCount = 0;
            
            $rows.each(function() {
                const $row = $(this);
                const rowText = $row.text().toLowerCase();
                
                if (searchTerm === '' || rowText.indexOf(searchTerm) !== -1) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                    // Masquer aussi les détails correspondants
                    const bookingId = $row.find('.view-details').data('booking-id');
                    if (bookingId) {
                        $('#details-' + bookingId).hide();
                    }
                }
            });
            
            // Afficher le nombre de résultats
            const totalCount = $rows.length;
            if (searchTerm === '') {
                $('.riwa-search-results').text('');
            } else {
                $('.riwa-search-results').text(`${visibleCount} sur ${totalCount} réservations`);
            }
        });
    }

    /**
     * Copier les informations dans le presse-papiers
     */
    function initCopyToClipboard() {
        // Ajouter des boutons de copie pour les emails et téléphones
        $('a[href^="mailto:"], a[href^="tel:"]').each(function() {
            const $link = $(this);
            const text = $link.text();
            
            $('<button class="copy-btn" title="Copier">📋</button>')
                .css({
                    'margin-left': '5px',
                    'background': 'none',
                    'border': 'none',
                    'cursor': 'pointer',
                    'font-size': '12px'
                })
                .on('click', function(e) {
                    e.preventDefault();
                    
                    // Copier dans le presse-papiers
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(function() {
                            showNotification('Copié !', 'success');
                        });
                    } else {
                        // Fallback pour les navigateurs plus anciens
                        const $temp = $('<input>');
                        $('body').append($temp);
                        $temp.val(text).select();
                        document.execCommand('copy');
                        $temp.remove();
                        showNotification('Copié !', 'success');
                    }
                })
                .insertAfter($link);
        });
    }

    /**
     * Afficher une notification temporaire
     */
    function showNotification(message, type = 'info') {
        const $notification = $(`
            <div class="riwa-notification riwa-notification-${type}" 
                 style="position: fixed; top: 30px; right: 30px; background: white; 
                        border: 1px solid #ddd; border-radius: 4px; padding: 15px; 
                        box-shadow: 0 4px 8px rgba(0,0,0,0.1); z-index: 9999;">
                ${message}
            </div>
        `);
        
        $('body').append($notification);
        
        // Animation d'apparition
        $notification.fadeIn(300);
        
        // Disparition automatique
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $notification.remove();
            });
        }, 2000);
    }

    /**
     * Raccourcis clavier (pour les power users)
     */
    function initKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl + F pour ouvrir la recherche
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                $('#riwa-search').focus();
            }
            
            // Escape pour fermer tous les détails
            if (e.key === 'Escape') {
                $('.booking-details').fadeOut(300);
                $('.view-details').text('Détails').removeClass('active');
            }
        });
    }

    // Initialiser les fonctionnalités supplémentaires
    initCopyToClipboard();
    initKeyboardShortcuts();

    // Styles CSS inline pour améliorer l'apparence
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .hover-effect {
                background-color: #f0f8ff !important;
                transition: background-color 0.2s ease;
            }
            .view-details.active {
                background-color: #667eea !important;
                color: white !important;
            }
            .riwa-search-container {
                background: #f9f9f9;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #e0e0e0;
            }
            .copy-btn:hover {
                opacity: 0.7;
            }
            .riwa-notification-success {
                border-left: 4px solid #27ae60 !important;
                color: #27ae60;
            }
        `)
        .appendTo('head');

    // Riwa Booking Admin initialisé avec succès

})(jQuery); 
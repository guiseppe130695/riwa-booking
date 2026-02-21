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

        // Popup détails réservation
        initDetailsPopup();

        // Confirmation des changements de statut
        initStatusChanges();

        // Animations et interactions
        initAnimations();

        // Filtres et recherche
        initFilters();

        // Toggle panneau filtres
        initFilterPanel();

        // Infinite scroll listing réservations
        initInfiniteScroll();
    }

    /**
     * Infinite scroll — charge les pages suivantes au scroll
     */
    function initInfiniteScroll() {
        var $sentinel = $('#riwa-infinite-sentinel');
        if (!$sentinel.length) return;

        var $table     = $('#riwa-bookings-table');
        var $tbody     = $table.find('tbody');
        var nextPage   = parseInt($table.data('current-page') || 1) + 1;
        var totalPages = parseInt($table.data('total-pages') || 1);
        var loading    = false;
        var exhausted  = nextPage > totalPages;

        var nonce = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';
        var ajaxUrl = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.ajax_url : '/wp-admin/admin-ajax.php';

        // Lire les filtres courants depuis les data-* du tableau
        function getFilters() {
            return {
                action           : 'riwa_bookings_load_more',
                nonce            : nonce,
                page             : nextPage,
                filter_status    : $table.data('filter-status')    || '',
                filter_period    : $table.data('filter-period')    || '',
                filter_date_from : $table.data('filter-date-from') || '',
                filter_date_to   : $table.data('filter-date-to')   || '',
                filter_duration  : $table.data('filter-duration')  || '',
                filter_guests    : $table.data('filter-guests')    || '',
                filter_price_min : $table.data('filter-price-min') || '',
                filter_price_max : $table.data('filter-price-max') || '',
                filter_orderby   : $table.data('filter-orderby')   || 'created_at',
                filter_search    : $table.data('filter-search')    || '',
            };
        }

        function loadNextPage() {
            if (loading || exhausted) return;
            loading = true;
            $sentinel.addClass('is-loading');

            $.post(ajaxUrl, getFilters(), function (resp) {
                loading = false;
                $sentinel.removeClass('is-loading');

                if (!resp.success) return;

                var $rows = $(resp.data.html);
                $tbody.append($rows);

                nextPage++;
                exhausted = !resp.data.has_more;

                if (exhausted) {
                    $sentinel.addClass('is-done');
                    observer.disconnect();
                }
            }).fail(function () {
                loading = false;
                $sentinel.removeClass('is-loading');
            });
        }

        // IntersectionObserver : déclenche quand le sentinel entre dans le viewport
        var observer = new IntersectionObserver(function (entries) {
            if (entries[0].isIntersecting) {
                loadNextPage();
            }
        }, { rootMargin: '200px' });

        observer.observe($sentinel[0]);
    }

    /**
     * Dropdown filtres ancré au bouton + onglets de vue JS
     */
    function initFilterPanel() {
        // ── Dropdown filtres ──────────────────────────────────────
        var $wrap     = $('#riwa-filter-dropdown-wrap');
        var $btn      = $('#riwa-toggle-filters');
        var $dropdown = $('#riwa-filters-popup');

        function positionDropdown() {
            var rect    = $btn[0].getBoundingClientRect();
            var dropW   = 340;
            var top     = rect.bottom + 6;
            var left    = rect.right - dropW;
            var maxH    = window.innerHeight - top - 16; // 16px de marge basse
            // Empêcher de sortir à gauche
            if (left < 8) left = 8;
            $dropdown.css({ top: top + 'px', left: left + 'px', right: 'auto' });
            $dropdown.find('.riwa-filter-dropdown-body').css('max-height', Math.max(120, maxH - 110) + 'px');
        }

        function openDropdown() {
            positionDropdown();
            $dropdown.stop(true, true).slideDown(160);
            $btn.addClass('riwa-toolbar-btn--active');
            // Sync chip labels avec l'état radio actuel
            $dropdown.find('input[type="radio"]').each(function () {
                $(this).closest('.riwa-chip-label').toggleClass('is-active', $(this).is(':checked'));
            });
        }

        function closeDropdown() {
            $dropdown.stop(true, true).slideUp(130);
            $btn.removeClass('riwa-toolbar-btn--active');
        }

        if ($btn.length && $dropdown.length) {
            $btn.on('click', function (e) {
                e.stopPropagation();
                if ($dropdown.is(':visible')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });
        }

        // Clic en dehors → fermer
        $(document).on('click', function (e) {
            if ($dropdown.is(':visible') && !$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                closeDropdown();
            }
        });

        // Escape → fermer
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $dropdown.is(':visible')) {
                closeDropdown();
            }
        });

        // ── Moteur de filtrage client-side ───────────────────────
        var today = new Date(); today.setHours(0, 0, 0, 0);

        // État courant des filtres
        var activeView     = 'all';   // view tab
        var activeStatus   = '';
        var activeDuration = '';
        var activeGuests   = '';
        var activeDateFrom = '';
        var activeDateTo   = '';
        var activePriceMin = '';
        var activePriceMax = '';
        var activeSearch   = '';

        function applyFilters() {
            var dateFrom = activeDateFrom ? new Date(activeDateFrom) : null;
            var dateTo   = activeDateTo   ? new Date(activeDateTo)   : null;
            if (dateFrom) dateFrom.setHours(0,0,0,0);
            if (dateTo)   dateTo.setHours(0,0,0,0);

            var visible = 0;

            $('.riwa-booking-row').each(function () {
                var $row    = $(this);
                var checkin = new Date($row.data('checkin'));
                var checkout= new Date($row.data('checkout'));
                checkin.setHours(0,0,0,0); checkout.setHours(0,0,0,0);

                var nights  = parseInt($row.data('nights'))  || 0;
                var guests  = parseInt($row.data('guests'))  || 0;
                var status  = $row.data('status')  || '';
                var price   = parseFloat($row.data('price')) || 0;
                var rowText = $row.text().toLowerCase();

                var show = true;

                // View tab
                if (activeView === 'upcoming') show = show && checkin > today;
                if (activeView === 'staying')  show = show && checkin <= today && checkout > today;
                if (activeView === 'past')     show = show && checkout <= today;

                // Statut
                if (activeStatus) show = show && status === activeStatus;

                // Durée
                if (activeDuration === '1')   show = show && nights === 1;
                if (activeDuration === '2-3') show = show && nights >= 2 && nights <= 3;
                if (activeDuration === '4+')  show = show && nights >= 4;

                // Voyageurs
                if (activeGuests === '1-2') show = show && guests >= 1 && guests <= 2;
                if (activeGuests === '3-5') show = show && guests >= 3 && guests <= 5;
                if (activeGuests === '6+')  show = show && guests >= 6;

                // Dates d'arrivée
                if (dateFrom) show = show && checkin >= dateFrom;
                if (dateTo)   show = show && checkin <= dateTo;

                // Montant
                if (activePriceMin !== '') show = show && price >= parseFloat(activePriceMin);
                if (activePriceMax !== '') show = show && price <= parseFloat(activePriceMax);

                // Recherche texte
                if (activeSearch) show = show && rowText.indexOf(activeSearch) !== -1;

                $row.toggle(show);
                if (show) visible++;
            });

            // Mise à jour compteur
            $('.riwa-toolbar-count').html(
                '<strong>' + visible + '</strong> réservation' + (visible > 1 ? 's' : '')
            );

            // Badge filtre actif sur le bouton
            var filterCount = (activeStatus ? 1 : 0)
                + ((activeDateFrom || activeDateTo) ? 1 : 0)
                + (activeDuration ? 1 : 0)
                + (activeGuests ? 1 : 0)
                + ((activePriceMin !== '' || activePriceMax !== '') ? 1 : 0);

            var $badge = $btn.find('.riwa-filter-count-badge');
            if (filterCount > 0) {
                if ($badge.length) { $badge.text(filterCount); }
                else { $btn.append('<span class="riwa-filter-count-badge">' + filterCount + '</span>'); }
                $btn.addClass('riwa-toolbar-btn--active');
                // Afficher le bouton Reset
                if (!$('.riwa-toolbar-reset-js').length) {
                    $btn.closest('.riwa-toolbar-right').prepend(
                        '<a href="#" class="riwa-toolbar-reset riwa-toolbar-reset-js" title="Réinitialiser">'
                        + '<span class="dashicons dashicons-dismiss"></span> Réinitialiser</a>'
                    );
                }
            } else {
                $badge.remove();
                $btn.removeClass('riwa-toolbar-btn--active');
                $('.riwa-toolbar-reset-js').remove();
            }

            // Affichage du message vide
            var $empty = $('.riwa-empty-state-js');
            if (visible === 0 && $('.riwa-booking-row').length > 0) {
                if (!$empty.length) {
                    $('<tr class="riwa-empty-state-js"><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">Aucune réservation ne correspond aux filtres.</td></tr>')
                        .appendTo('.riwa-modern-table tbody');
                }
            } else {
                $empty.remove();
            }
        }

        // Chip radio : is-active + filtrage immédiat
        $dropdown.on('change', 'input[type="radio"]', function () {
            var name = $(this).attr('name');
            var val  = $(this).val();
            $dropdown.find('input[name="' + name + '"]').each(function () {
                $(this).closest('.riwa-chip-label').toggleClass('is-active', $(this).is(':checked'));
            });
            if (name === 'filter_status')   activeStatus   = val;
            if (name === 'filter_duration') activeDuration = val;
            if (name === 'filter_guests')   activeGuests   = val;
            applyFilters();
        });

        // Dates et montants : filtrage à l'input
        $dropdown.on('input change', 'input[type="date"]', function () {
            var name = $(this).attr('name');
            if (name === 'filter_date_from') activeDateFrom = $(this).val();
            if (name === 'filter_date_to')   activeDateTo   = $(this).val();
            applyFilters();
        });
        $dropdown.on('input', 'input[type="number"]', function () {
            var name = $(this).attr('name');
            if (name === 'filter_price_min') activePriceMin = $(this).val();
            if (name === 'filter_price_max') activePriceMax = $(this).val();
            applyFilters();
        });

        // Reset JS (bouton généré dynamiquement)
        $(document).on('click', '.riwa-toolbar-reset-js', function (e) {
            e.preventDefault();
            activeStatus = activeDuration = activeGuests = '';
            activeDateFrom = activeDateTo = activePriceMin = activePriceMax = activeSearch = '';
            // Réinitialiser les chips
            $dropdown.find('input[type="radio"]').each(function () {
                var isDefault = $(this).val() === '';
                $(this).prop('checked', isDefault);
                $(this).closest('.riwa-chip-label').toggleClass('is-active', isDefault);
            });
            // Réinitialiser les dates et prix
            $dropdown.find('input[type="date"], input[type="number"]').val('');
            // Réinitialiser la recherche inline
            $('.riwa-search-input').val('');
            activeView = 'all';
            $('#riwa-view-tabs .riwa-view-tab').removeClass('active');
            $('#riwa-view-tabs .riwa-view-tab[data-view="all"]').addClass('active');
            applyFilters();
        });

        // Recherche inline → filtrage JS
        $(document).on('input', '.riwa-search-input', function () {
            activeSearch = $(this).val().toLowerCase().trim();
            $('#riwa-search-clear').toggle(activeSearch.length > 0);
            applyFilters();
        });

        // Bouton clear recherche
        $(document).on('click', '#riwa-search-clear', function () {
            $('.riwa-search-input').val('');
            activeSearch = '';
            $(this).hide();
            applyFilters();
        });

        // Bouton Reset PHP (visible au chargement si filtres URL actifs)
        $(document).on('click', '.riwa-toolbar-reset:not(.riwa-toolbar-reset-js)', function (e) {
            e.preventDefault();
            window.location.href = $(this).attr('href');
        });

        // ── Onglets de vue ────────────────────────────────────────
        var $tabs = $('#riwa-view-tabs');

        if ($tabs.length) {
            $tabs.on('click', '.riwa-view-tab', function () {
                $tabs.find('.riwa-view-tab').removeClass('active');
                $(this).addClass('active');
                activeView = $(this).data('view');
                applyFilters();
            });
        }
    }

    /**
     * Popup détails réservation — boutons .view-details-popup
     */
    function initDetailsPopup() {
        var $overlay = $('#riwa-details-popup');
        if (!$overlay.length) return;

        // Ouvrir la popup au clic (délégation pour les éléments chargés dynamiquement)
        $(document).on('click', '.view-details-popup', function() {
            var $btn = $(this);

            var id        = $btn.data('booking-id');
            var name      = $btn.data('booking-name');
            var email     = $btn.data('booking-email');
            var phone     = $btn.data('booking-phone');
            var checkin   = $btn.data('booking-checkin');
            var checkout  = $btn.data('booking-checkout');
            var adults    = parseInt($btn.data('booking-adults'))   || 0;
            var children  = parseInt($btn.data('booking-children')) || 0;
            var babies    = parseInt($btn.data('booking-babies'))   || 0;
            var price     = parseFloat($btn.data('booking-price'))  || 0;
            var ppn       = parseFloat($btn.data('booking-price-per-night')) || 0;
            var status    = $btn.data('booking-status');
            var created   = $btn.data('booking-created');
            var requests  = $btn.data('booking-requests');

            // Calcul durée
            var nights = 0;
            if (checkin && checkout) {
                var d1 = new Date(checkin);
                var d2 = new Date(checkout);
                nights = Math.round((d2 - d1) / 86400000);
            }

            // Formatage dates
            function fmtDate(str) {
                if (!str) return '—';
                var d = new Date(str);
                return d.toLocaleDateString('fr-FR');
            }

            // Libellés statut
            var statusLabels = { pending: 'En attente', confirmed: 'Confirmée', cancelled: 'Annulée' };

            var ref = 'RIWA-' + String(id).padStart(6, '0');
            var nightLabel = nights + ' nuit' + (nights > 1 ? 's' : '');
            var statusLabelsMap = { pending: 'En attente', confirmed: 'Confirmée', cancelled: 'Annulée' };
            var statusClassMap  = { pending: 'status-pending', confirmed: 'status-confirmed', cancelled: 'status-cancelled' };

            // Sidebar
            $('#popup-reference').text(ref);
            $('#popup-client-name').text(name || '—');
            $('#popup-client-email').text(email || '—');
            $('#popup-client-phone').text(phone || '—');
            $('#popup-duration').text(nightLabel);
            $('#popup-dates-range').text(fmtDate(checkin) + ' → ' + fmtDate(checkout));
            $('#popup-total-price').text(price > 0 ? price.toLocaleString('fr-FR') + ' €' : '—');
            $('#popup-price-per-night-label').text(ppn > 0 ? ppn.toLocaleString('fr-FR') + ' € / nuit' : '');

            // Panneau droit
            $('#popup-status-badge')
                .text(statusLabelsMap[status] || status)
                .attr('class', 'riwa-popup-status-badge ' + (statusClassMap[status] || ''));
            $('#popup-checkin').text(fmtDate(checkin));
            $('#popup-checkout').text(fmtDate(checkout));
            $('#popup-booking-id-fmt').text(ref);
            $('#popup-price-per-night').text(ppn > 0 ? ppn.toLocaleString('fr-FR') + ' €' : '—');
            $('#popup-created').text(fmtDate(created));
            $('#popup-status').text(statusLabelsMap[status] || status);
            $('#popup-requests-sidebar').text(requests || 'Aucune demande particulière');

            // Voyageurs (sidebar)
            var travelersHTML = '';
            if (adults > 0)   travelersHTML += '<span class="riwa-traveler-badge">' + adults   + ' adulte'  + (adults   > 1 ? 's' : '') + '</span>';
            if (children > 0) travelersHTML += '<span class="riwa-traveler-badge">' + children + ' enfant'  + (children > 1 ? 's' : '') + '</span>';
            if (babies > 0)   travelersHTML += '<span class="riwa-traveler-badge">' + babies   + ' bébé'    + (babies   > 1 ? 's' : '') + '</span>';
            if (!travelersHTML) travelersHTML = '<span style="opacity:.7">—</span>';
            $('#popup-travelers').html(travelersHTML);

            // ── Timeline ──────────────────────────────────
            var today     = new Date(); today.setHours(0,0,0,0);
            var dCheckin  = checkin  ? new Date(checkin)  : null;
            var dCheckout = checkout ? new Date(checkout) : null;
            if (dCheckin)  dCheckin.setHours(0,0,0,0);
            if (dCheckout) dCheckout.setHours(0,0,0,0);

            // Remplir les dates affichées dans la timeline
            $('#popup-submitted-date').text(fmtDate(created));
            $('#popup-checkin-timeline').text(fmtDate(checkin));
            $('#popup-checkout-timeline').text(fmtDate(checkout));
            $('#popup-staying-desc').text(nightLabel + ' à la villa');

            // Footer
            $('#popup-checkin-footer').text(fmtDate(checkin));
            $('#popup-checkout-footer').text(fmtDate(checkout));
            $('#popup-total-price-footer').text(price > 0 ? price.toLocaleString('fr-FR') + ' €' : '—');

            // Déterminer l'étape active selon statut + dates
            // Steps : submitted → confirmed → checkin → staying → checkout → done
            var $items = $('#popup-timeline .riwa-timeline-item');
            $items.removeClass('tl-done tl-active tl-pending tl-cancelled');

            if (status === 'cancelled') {
                // submitted=done, puis cancelled sur confirmed
                $items.filter('[data-step="submitted"]').addClass('tl-done');
                $items.filter('[data-step="confirmed"]').addClass('tl-cancelled');
            } else if (status === 'pending') {
                // Seul submitted est actif
                $items.filter('[data-step="submitted"]').addClass('tl-active');
            } else if (status === 'confirmed') {
                var stepsOrder = ['submitted', 'confirmed', 'checkin', 'staying', 'checkout', 'done'];
                var activeStep;

                if (!dCheckin || today < dCheckin) {
                    activeStep = 'confirmed';   // Pas encore arrivé
                } else if (today >= dCheckin && dCheckout && today < dCheckout) {
                    activeStep = 'staying';      // Séjour en cours
                } else if (dCheckout && today >= dCheckout) {
                    activeStep = 'done';         // Séjour terminé
                } else {
                    activeStep = 'confirmed';
                }

                var reached = false;
                $.each(stepsOrder, function(i, step) {
                    var $item = $items.filter('[data-step="' + step + '"]');
                    if (step === activeStep) {
                        reached = true;
                        if (step === 'done') {
                            $item.addClass('tl-done'); // done = complété
                        } else {
                            $item.addClass('tl-active');
                        }
                    } else if (!reached) {
                        $item.addClass('tl-done');
                    }
                });
            }

            // ── Actions rapides ───────────────────────────
            var actionsHTML = '';
            if (status === 'pending') {
                actionsHTML += '<button class="riwa-popup-action-btn confirm" data-action="confirmed" data-id="' + id + '">'
                    + '<span class="dashicons dashicons-yes"></span> Confirmer</button>';
            }
            if (status !== 'cancelled') {
                actionsHTML += '<button class="riwa-popup-action-btn cancel" data-action="cancelled" data-id="' + id + '">'
                    + '<span class="dashicons dashicons-no"></span> Annuler</button>';
            }
            if (email) {
                var subject = encodeURIComponent('Votre réservation ' + ref);
                actionsHTML += '<a class="riwa-popup-action-btn email" href="mailto:' + email
                    + '?subject=' + subject + '">'
                    + '<span class="dashicons dashicons-email-alt"></span> Envoyer un email</a>';
            }
            if (phone) {
                actionsHTML += '<a class="riwa-popup-action-btn contact" href="tel:' + phone + '">'
                    + '<span class="dashicons dashicons-phone"></span> Appeler</a>';
            }

            $('#popup-actions').html(actionsHTML);

            // ── Upsells de la réservation ─────────────────
            $('#popup-upsells-step').hide();
            $('#popup-upsells-list').html('<span style="opacity:.6">Chargement…</span>');
            var nonce = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';
            $.post(riwa_admin_ajax.ajax_url, {
                action:     'riwa_get_booking_upsells',
                booking_id: id,
                nonce:      nonce
            }, function(resp) {
                if (resp.success && resp.data && resp.data.length > 0) {
                    var upsellsHTML = '';
                    $.each(resp.data, function(_, u) {
                        upsellsHTML += '<div class="riwa-popup-upsell-row">'
                            + '<span class="riwa-popup-upsell-name">' + u.upsell_name + '</span>'
                            + '<span class="riwa-popup-upsell-price">' + parseFloat(u.total_price).toLocaleString('fr-FR') + ' €</span>'
                            + '</div>';
                    });
                    $('#popup-upsells-list').html(upsellsHTML);
                    $('#popup-upsells-step').show();
                }
            });

            // Notifications WhatsApp
            if (typeof window.riwaNotifPopupOpen === 'function') {
                window.riwaNotifPopupOpen(id);
            }

            // Afficher la popup (display:flex requis par le CSS)
            $overlay.css('display', 'flex').hide().fadeIn(200);
        });

        // Fermer la popup
        $(document).on('click', '#riwa-popup-close', function() {
            $overlay.fadeOut(200);
        });

        // Actions rapides depuis la popup
        $(document).on('click', '.riwa-popup-action-btn[data-action]', function() {
            var action    = $(this).data('action');
            var bookingId = $(this).data('id');
            var labels    = { confirmed: 'Réservation confirmée', cancelled: 'Réservation annulée' };
            var icons     = { confirmed: '✅', cancelled: '🚫' };
            var nonce     = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';

            $.post(location.href, {
                action:           'update_status',
                booking_id:       bookingId,
                new_status:       action,
                riwa_admin_nonce: nonce
            }, function() {
                riwaToast(icons[action] + ' ' + (labels[action] || action), action === 'cancelled' ? 'error' : 'success');
                $overlay.fadeOut(200, function() { location.reload(); });
            });
        });

        // Fermer en cliquant sur l'overlay
        $overlay.on('click', function(e) {
            if ($(e.target).is($overlay)) {
                $overlay.fadeOut(200);
            }
        });

        // Fermer avec Escape
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $overlay.is(':visible')) {
                $overlay.fadeOut(200);
            }
        });

        // ── Popup blocage (#riwa-blocked-popup) ──────────────────
        var $blOverlay = $('#riwa-blocked-popup');
        if ($blOverlay.length) {
            $(document).on('click', '#riwa-blocked-popup-close', function () {
                $blOverlay.fadeOut(200);
            });
            $blOverlay.on('click', function (e) {
                if ($(e.target).is($blOverlay)) $blOverlay.fadeOut(200);
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $blOverlay.is(':visible')) $blOverlay.fadeOut(200);
            });
        }
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
        // Ajouter un champ de recherche avant le tableau des réservations uniquement
        const searchHTML = `
            <div class="riwa-search-container" style="margin-bottom: 20px;">
                <input type="text" id="riwa-search" placeholder="Rechercher par nom, email ou téléphone..." 
                       style="width: 300px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                <span class="riwa-search-results" style="margin-left: 10px; color: #666;"></span>
            </div>
        `;
        
        // Ajouter le champ de recherche seulement dans la section des réservations
        $('#bookings-section .wp-list-table').before(searchHTML);
        
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

/**
 * Fonction globale de mise à jour de statut (appelée via onchange dans les partials HTML)
 * Envoie le nonce admin pour satisfaire la vérification sécurité côté PHP.
 */
function updateBookingStatus(bookingId, newStatus) {
    if (!newStatus) return;

    var labels = { pending: 'En attente', confirmed: 'Confirmée', cancelled: 'Annulée' };
    if (!confirm('Êtes-vous sûr de vouloir changer le statut vers "' + (labels[newStatus] || newStatus) + '" ?')) {
        // Recharger pour réinitialiser le select
        location.reload();
        return;
    }

    var nonce = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';

    jQuery.post(location.href, {
        action:          'update_status',
        booking_id:      bookingId,
        new_status:      newStatus,
        riwa_admin_nonce: nonce
    }, function() {
        location.reload();
    });
}

// Notification toast minimaliste
function riwaToast(message, type) {
    var bg = type === 'error' ? '#fee2e2' : '#dcfce7';
    var color = type === 'error' ? '#b91c1c' : '#15803d';
    var $toast = jQuery('<div>')
        .text(message)
        .css({
            position: 'fixed', bottom: '1.5rem', right: '1.5rem',
            background: bg, color: color,
            padding: '0.75rem 1.25rem', borderRadius: '10px',
            fontSize: '13px', fontWeight: '600',
            boxShadow: '0 4px 16px rgba(0,0,0,0.12)',
            zIndex: 1000000, opacity: 0,
            transition: 'opacity 0.2s'
        })
        .appendTo('body');

    setTimeout(function() { $toast.css('opacity', 1); }, 10);
    setTimeout(function() {
        $toast.css('opacity', 0);
        setTimeout(function() { $toast.remove(); }, 400);
    }, 30000);
}

// Onglets des Paramètres
jQuery(function($) {
    var $tabs    = $('.riwa-settings-tab');
    var $content = $('.riwa-settings-tab-content');

    $tabs.on('click', function() {
        var tab = $(this).data('tab');
        $tabs.removeClass('active');
        $content.removeClass('active');
        $(this).addClass('active');
        $('#settings-tab-' + tab).addClass('active');
    });

    // Media uploader pour le logo
    $('#riwa-logo-picker').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Choisir un logo', multiple: false });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#riwa_logo_url').val(attachment.url);
        });
        frame.open();
    });

    // Color picker pour la couleur principale
    if ($.fn.wpColorPicker) {
        $('.riwa-color-picker').wpColorPicker();
    }
});
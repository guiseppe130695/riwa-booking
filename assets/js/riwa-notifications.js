/**
 * Riwa Notifications — WhatsApp semi-auto
 * Gère les boutons WA dans le popup de réservation + la page Notifications
 */
(function ($) {
    'use strict';

    if (typeof riwa_notif_config === 'undefined') return;

    var AJAX_URL  = riwa_notif_config.ajax_url;
    var NONCE     = riwa_notif_config.nonce;
    var ENABLED   = riwa_notif_config.enabled;

    /* ---------------------------------------------------------------- */
    /*  Modal de prévisualisation                                        */
    /* ---------------------------------------------------------------- */

    var $modal         = null;
    var pendingWaLink  = '';
    var pendingBookingId = 0;
    var pendingType    = '';

    function initModal() {
        $modal = $('#riwa-wa-preview-modal');
        if (!$modal.length) return;

        $('#riwa-wa-modal-close, #riwa-wa-modal-cancel').on('click', closeModal);
        $modal.on('click', function (e) {
            if ($(e.target).is($modal)) closeModal();
        });

        $('#riwa-wa-modal-open').on('click', function (e) {
            if (!pendingWaLink) { e.preventDefault(); return; }
            // Log l'envoi côté serveur puis ouvre WA
            logSent(pendingBookingId, pendingType, function () {
                window.open(pendingWaLink, '_blank');
                closeModal();
                refreshNotifLog(pendingBookingId);
                refreshRecentLog();
            });
            e.preventDefault();
        });
    }

    function openModal(title, message, phone, waLink, bookingId, type) {
        if (!$modal || !$modal.length) return;
        pendingWaLink    = waLink;
        pendingBookingId = bookingId;
        pendingType      = type;

        $('#riwa-wa-modal-title').text(title);
        $('#riwa-wa-modal-message').text(message);
        $('#riwa-wa-modal-phone-display').text(phone || '—');
        $('#riwa-wa-modal-open').attr('href', waLink || '#');
        $modal.fadeIn(180);
    }

    function closeModal() {
        if ($modal) $modal.fadeOut(150);
    }

    /* ---------------------------------------------------------------- */
    /*  Envoi de la requête de prévisualisation                          */
    /* ---------------------------------------------------------------- */

    function sendPreview(bookingId, type, target, callback) {
        $.post(AJAX_URL, {
            action:     'riwa_notif_preview',
            nonce:      NONCE,
            booking_id: bookingId,
            type:       type,
            target:     target,
        }, function (resp) {
            if (resp.success) {
                callback(resp.data);
            } else {
                alert('Erreur : ' + (resp.data || 'Impossible de charger le message.'));
            }
        }).fail(function () {
            alert('Erreur réseau lors du chargement du message.');
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Log d'envoi                                                       */
    /* ---------------------------------------------------------------- */

    function logSent(bookingId, type, callback) {
        $.post(AJAX_URL, {
            action:     'riwa_notif_log_sent',
            nonce:      NONCE,
            booking_id: bookingId,
            type:       type,
        }, function () {
            if (callback) callback();
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Historique dans le popup de réservation                          */
    /* ---------------------------------------------------------------- */

    function refreshNotifLog(bookingId) {
        var $log = $('#popup-notif-log');
        if (!$log.length || !bookingId) return;

        $log.html('<span class="riwa-spin dashicons dashicons-update-alt"></span>');

        $.post(AJAX_URL, {
            action:     'riwa_notif_get_log',
            nonce:      NONCE,
            booking_id: bookingId,
        }, function (resp) {
            if (!resp.success || !resp.data.log.length) {
                $log.html('<p class="riwa-notif-log-empty">Aucune notification envoyée</p>');
                return;
            }
            var html = '<div class="riwa-notif-log-list">';
            $.each(resp.data.log, function (i, entry) {
                html += '<div class="riwa-notif-log-entry">'
                    + '<span class="riwa-notif-log-type">' + escHtml(entry.type) + '</span>'
                    + '<span class="riwa-notif-log-date">' + escHtml(entry.sent_at) + '</span>'
                    + '</div>';
            });
            html += '</div>';
            $log.html(html);
        }).fail(function () {
            $log.html('<p class="riwa-notif-log-empty">Erreur de chargement</p>');
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Historique global (section Notifications)                        */
    /* ---------------------------------------------------------------- */

    function refreshRecentLog() {
        var $wrap = $('#riwa-recent-notif-log');
        if (!$wrap.length) return;

        $wrap.html('<div class="riwa-notif-log-loading"><span class="dashicons dashicons-update-alt riwa-spin"></span> Chargement…</div>');

        $.post(AJAX_URL, {
            action: 'riwa_notif_recent_log',
            nonce:  NONCE,
        }, function (resp) {
            if (!resp.success || !resp.data.log.length) {
                $wrap.html('<p class="riwa-notif-log-empty">Aucune notification envoyée pour l\'instant.</p>');
                return;
            }
            var html = '<table class="riwa-notif-log-table">'
                + '<thead><tr>'
                + '<th>Réservation</th><th>Client</th><th>Type</th><th>Canal</th><th>Date</th>'
                + '</tr></thead><tbody>';

            $.each(resp.data.log, function (i, row) {
                html += '<tr>'
                    + '<td>#' + row.booking_id + '</td>'
                    + '<td>' + escHtml(row.guest_name) + '</td>'
                    + '<td>' + escHtml(row.type)       + '</td>'
                    + '<td>' + escHtml(row.channel)    + '</td>'
                    + '<td>' + escHtml(row.sent_at)    + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            $wrap.html(html);
        }).fail(function () {
            $wrap.html('<p class="riwa-notif-log-empty">Erreur de chargement</p>');
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Bind sur les boutons .riwa-wa-btn                                 */
    /* ---------------------------------------------------------------- */

    function bindWaButtons($context, bookingId) {
        $context.on('click', '.riwa-wa-btn, .riwa-notif-send-btn', function () {
            var $btn       = $(this);
            var type       = $btn.data('tpl');
            var target     = $btn.data('target') || 'client';
            var bId        = parseInt($btn.data('booking-id') || bookingId, 10);
            var typeLabel  = (riwa_notif_config.templates && riwa_notif_config.templates[type])
                             ? riwa_notif_config.templates[type] : type;

            if (!bId) { alert('ID de réservation manquant.'); return; }

            $btn.prop('disabled', true);
            sendPreview(bId, type, target, function (data) {
                $btn.prop('disabled', false);
                openModal(
                    'WhatsApp — ' + typeLabel,
                    data.message,
                    data.phone,
                    data.wa_link,
                    bId,
                    type
                );
            });
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Intégration avec le popup de réservation (riwa-booking-admin.js) */
    /* ---------------------------------------------------------------- */

    /**
     * Appelé depuis riwa-booking-admin.js quand le popup s'ouvre.
     * Expose une fonction globale pour rester découplé.
     */
    window.riwaNotifPopupOpen = function (bookingId) {
        var $waSection = $('#popup-notif-wa');
        if (!$waSection.length) return;

        if (ENABLED) {
            $waSection.show();
            // Mettre à jour le data-booking-id sur les boutons
            $waSection.find('.riwa-wa-btn').each(function () {
                $(this).data('booking-id', bookingId);
            });
            refreshNotifLog(bookingId);
        } else {
            $waSection.hide();
        }
    };

    /* ---------------------------------------------------------------- */
    /*  Initialisation                                                   */
    /* ---------------------------------------------------------------- */

    $(document).ready(function () {
        initModal();

        // Boutons dans le popup de réservation
        bindWaButtons($('#popup-notif-wa'), 0);

        // Boutons dans la section Notifications (page dédiée)
        bindWaButtons($('.riwa-notif-bookings-grid'), 0);

        // Charger l'historique global si on est sur la page Notifications
        if ($('#riwa-recent-notif-log').length) {
            refreshRecentLog();
        }
    });

    /* ---------------------------------------------------------------- */
    /*  Utilitaires                                                      */
    /* ---------------------------------------------------------------- */

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

}(jQuery));

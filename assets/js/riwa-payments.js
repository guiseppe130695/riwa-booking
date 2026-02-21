/**
 * Riwa Payments — Module paiements & acomptes
 */
(function ($) {
    'use strict';

    var AJAX    = riwa_payments_config.ajax_url;
    var NONCE   = riwa_payments_config.nonce;
    var EXPORT  = riwa_payments_config.export_url;
    var CURRENCY = riwa_payments_config.currency || '€';

    /* ---------------------------------------------------------------- */
    /*  Utilitaires                                                       */
    /* ---------------------------------------------------------------- */

    function fmt(n) {
        var val = parseFloat(n || 0);
        // Pas de décimales si valeur entière, pour garder l'affichage compact
        var decimals = val % 1 === 0 ? 0 : 2;
        return val.toLocaleString('fr-FR', {
            minimumFractionDigits: decimals, maximumFractionDigits: decimals
        }) + '\u202f' + CURRENCY;
    }

    function fmtCompact(n) {
        var val = parseFloat(n || 0);
        if (val >= 1000) {
            return (val / 1000).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 1 }) + '\u00a0k' + CURRENCY;
        }
        return fmt(val);
    }

    function fmtDate(d) {
        if (!d) return '—';
        var parts = d.split('-');
        if (parts.length !== 3) return d;
        return parts[2] + '/' + parts[1] + '/' + parts[0];
    }

    function statusBadge(status, label, color) {
        return '<span class="riwa-pay-badge" style="background:' + color + '20;color:' + color + ';border-color:' + color + '40;">'
            + label + '</span>';
    }

    function isOverdue(due_date) {
        if (!due_date) return false;
        return new Date(due_date) < new Date();
    }

    /* ---------------------------------------------------------------- */
    /*  Onglets                                                           */
    /* ---------------------------------------------------------------- */

    function initTabs() {
        $(document).on('click', '.riwa-pay-tab', function () {
            var tab = $(this).data('tab');
            $('.riwa-pay-tab').removeClass('active');
            $(this).addClass('active');
            $('.riwa-pay-tab-content').removeClass('active');
            $('#tab-' + tab).addClass('active');

            if (tab === 'dashboard') loadDashboard();
            if (tab === 'bookings') loadBookingsList('all', 1);
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Dashboard KPIs                                                   */
    /* ---------------------------------------------------------------- */

    function loadDashboard() {
        $.post(AJAX, { action: 'riwa_payments_get_dashboard', nonce: NONCE }, function (resp) {
            if (!resp.success) return;
            var d = resp.data;

            $('#kpi-val-encaisse').text(fmtCompact(d.encaisse_mois));
            $('#kpi-val-attente').text(fmtCompact(d.en_attente));
            $('#kpi-val-retard').text(fmtCompact(d.en_retard));
            $('#kpi-sub-retard').text(d.retard_count + ' réservation(s)');
            $('#kpi-val-acomptes').text(d.acomptes_count);
            $('#kpi-val-prevision').text(fmtCompact(d.prevision_30j));
        });

        loadOverdueList();
        loadQuickFormBookings();
    }

    function loadOverdueList() {
        $.post(AJAX, {
            action: 'riwa_payments_get_bookings_list',
            nonce:  NONCE,
            filter: 'overdue',
            page:   1,
        }, function (resp) {
            var $wrap = $('#pay-overdue-list');
            if (!resp.success || !resp.data.bookings.length) {
                $wrap.html('<div class="riwa-pay-empty"><span class="dashicons dashicons-yes-alt" style="color:#22c55e;"></span> Aucun paiement en retard</div>');
                return;
            }
            var html = '<div class="riwa-pay-overdue-list">';
            resp.data.bookings.forEach(function (b) {
                html += '<div class="riwa-pay-overdue-item">'
                    + '<div class="riwa-pay-overdue-client"><strong>' + esc(b.guest_name) + '</strong>'
                    + '<span class="riwa-pay-hint"> · arrivée ' + fmtDate(b.check_in_date) + '</span></div>'
                    + '<div class="riwa-pay-overdue-meta">'
                    + '<span>Solde : <strong>' + fmt(b.solde) + '</strong></span>'
                    + '<span>Échéance : <strong style="color:#ef4444;">' + fmtDate(b.balance_due_date) + '</strong></span>'
                    + '</div>'
                    + '<button class="riwa-btn riwa-btn-secondary riwa-pay-open-detail" data-id="' + b.id + '" data-name="' + esc(b.guest_name) + '">'
                    + '<span class="dashicons dashicons-plus-alt2"></span> Paiement'
                    + '</button>'
                    + '</div>';
            });
            html += '</div>';
            $wrap.html(html);
        });
    }

    function loadQuickFormBookings() {
        $.post(AJAX, {
            action: 'riwa_payments_get_bookings_list',
            nonce:  NONCE,
            filter: 'all',
            page:   1,
        }, function (resp) {
            if (!resp.success) return;
            var $sel = $('#qf-booking-id');
            $sel.find('option:not(:first)').remove();
            resp.data.bookings.forEach(function (b) {
                $sel.append(
                    $('<option>').val(b.id)
                        .text('#' + b.id + ' — ' + b.guest_name + ' (' + fmtDate(b.check_in_date) + ') — solde : ' + fmt(b.solde))
                        .data('solde', b.solde)
                        .data('total', b.total_price)
                );
            });
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Formulaire enregistrement rapide                                 */
    /* ---------------------------------------------------------------- */

    function initQuickForm() {
        $('#qf-booking-id').on('change', function () {
            var $opt = $(this).find(':selected');
            var solde = parseFloat($opt.data('solde') || 0);
            $('#qf-solde-hint').text(solde > 0 ? '(solde : ' + fmt(solde) + ')' : '');
            $('#qf-amount').val(solde > 0 ? solde.toFixed(2) : '');
        });

        $('#qf-submit').on('click', function () {
            var booking_id = $('#qf-booking-id').val();
            var amount     = parseFloat($('#qf-amount').val());
            if (!booking_id || !amount || amount <= 0) {
                $('#qf-msg').text('Veuillez remplir tous les champs.').css('color', '#ef4444');
                return;
            }
            submitPayment({
                booking_id:   booking_id,
                amount:       amount,
                method:       $('#qf-method').val(),
                payment_date: $('#qf-date').val(),
                reference:    $('#qf-reference').val(),
                note:         '',
            }, '#qf-msg', function () {
                $('#qf-booking-id').val('').trigger('change');
                $('#qf-amount').val('');
                $('#qf-reference').val('');
                loadDashboard();
            });
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Liste réservations                                               */
    /* ---------------------------------------------------------------- */

    var currentFilter = 'all';
    var currentPage   = 1;

    function loadBookingsList(filter, page) {
        currentFilter = filter;
        currentPage   = page;

        var $tbody = $('#pay-bookings-tbody');
        $tbody.html('<tr><td colspan="8" class="riwa-pay-loading"><span class="dashicons dashicons-update-alt riwa-spin"></span> Chargement…</td></tr>');

        $.post(AJAX, {
            action: 'riwa_payments_get_bookings_list',
            nonce:  NONCE,
            filter: filter,
            page:   page,
        }, function (resp) {
            if (!resp.success) return;
            var d = resp.data;

            if (!d.bookings.length) {
                $tbody.html('<tr><td colspan="8" class="riwa-pay-empty">Aucune réservation trouvée.</td></tr>');
                $('#pay-pagination').empty();
                return;
            }

            var html = '';
            d.bookings.forEach(function (b) {
                var overdue_cls = (b.status === 'overdue' || isOverdue(b.balance_due_date) && b.solde > 0) ? 'riwa-pay-row-overdue' : '';
                html += '<tr class="' + overdue_cls + '">'
                    + '<td><strong>' + esc(b.guest_name) + '</strong><br><small style="color:#94a3b8;">' + esc(b.guest_email) + '</small></td>'
                    + '<td>' + fmtDate(b.check_in_date) + '</td>'
                    + '<td>' + fmt(b.total_price) + '</td>'
                    + '<td><strong style="color:#16a34a;">' + fmt(b.amount_paid) + '</strong></td>'
                    + '<td>' + (b.solde > 0 ? '<strong style="color:#dc2626;">' + fmt(b.solde) + '</strong>' : '<span style="color:#22c55e;">—</span>') + '</td>'
                    + '<td>' + (b.balance_due_date ? '<span style="color:' + (isOverdue(b.balance_due_date) && b.solde > 0 ? '#ef4444' : '#64748b') + ';">' + fmtDate(b.balance_due_date) + '</span>' : '—') + '</td>'
                    + '<td>' + statusBadge(b.status, b.status_label, b.status_color) + '</td>'
                    + '<td>'
                    + '<button class="riwa-pay-action-btn riwa-pay-open-detail" data-id="' + b.id + '" data-name="' + esc(b.guest_name) + '" title="Paiements"><span class="dashicons dashicons-money-alt"></span></button>'
                    + '<button class="riwa-pay-action-btn riwa-pay-open-modal" data-id="' + b.id + '" data-name="' + esc(b.guest_name) + '" data-solde="' + b.solde + '" data-total="' + b.total_price + '" data-deposit="' + b.deposit_percent + '" data-due="' + (b.balance_due_date || '') + '" title="Ajouter paiement"><span class="dashicons dashicons-plus-alt2"></span></button>'
                    + '</td>'
                    + '</tr>';
            });
            $tbody.html(html);

            // Pagination
            var pag = '';
            if (d.pages > 1) {
                for (var i = 1; i <= d.pages; i++) {
                    pag += '<button class="riwa-pay-page-btn' + (i === d.page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
                }
            }
            $('#pay-pagination').html(pag);
        });
    }

    function initBookingsList() {
        $(document).on('click', '.riwa-pay-filter-btn', function () {
            $('.riwa-pay-filter-btn').removeClass('active');
            $(this).addClass('active');
            loadBookingsList($(this).data('filter'), 1);
        });

        $(document).on('click', '.riwa-pay-page-btn', function () {
            loadBookingsList(currentFilter, parseInt($(this).data('page')));
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Panel détail paiements (slide-in)                               */
    /* ---------------------------------------------------------------- */

    function openDetailPanel(booking_id, name) {
        var $panel = $('#pay-detail-panel');
        var $body  = $('#pay-detail-body');

        $('#pay-detail-name').text('Paiements — ' + name);
        $body.html('<div class="riwa-pay-loading"><span class="dashicons dashicons-update-alt riwa-spin"></span> Chargement…</div>');
        $panel.addClass('open');
        $('#pay-detail-overlay').addClass('open');

        $.post(AJAX, {
            action:     'riwa_payments_get_booking_payments',
            nonce:      NONCE,
            booking_id: booking_id,
        }, function (resp) {
            if (!resp.success) { $body.html('<p>Erreur de chargement.</p>'); return; }
            var d = resp.data;

            var html = '<div class="riwa-pay-detail-summary">'
                + '<div class="riwa-pay-detail-stat"><span>Total séjour</span><strong>' + fmt(d.total_price) + '</strong></div>'
                + '<div class="riwa-pay-detail-stat"><span>Encaissé</span><strong style="color:#16a34a;">' + fmt(d.total_paid) + '</strong></div>'
                + '<div class="riwa-pay-detail-stat"><span>Solde restant</span><strong style="color:#dc2626;">' + fmt(Math.max(0, d.total_price - d.total_paid)) + '</strong></div>'
                + '<div>' + statusBadge(d.status, d.status_label, d.status_color) + '</div>'
                + '</div>';

            // Acompte
            if (d.deposit_percent > 0 || d.deposit_amount > 0) {
                html += '<div class="riwa-pay-detail-deposit">'
                    + '<span class="dashicons dashicons-tickets-alt" style="color:#7c3aed;"></span>'
                    + ' Acompte requis : <strong>' + fmt(d.deposit_amount) + '</strong>'
                    + (d.deposit_percent ? ' (' + d.deposit_percent + '%)' : '')
                    + (d.balance_due_date ? ' · Échéance : <strong>' + fmtDate(d.balance_due_date) + '</strong>' : '')
                    + '</div>';
            }

            // Timeline paiements
            html += '<div class="riwa-pay-timeline">';
            if (!d.payments.length) {
                html += '<div class="riwa-pay-empty">Aucun paiement enregistré</div>';
            } else {
                d.payments.forEach(function (p) {
                    html += '<div class="riwa-pay-timeline-item">'
                        + '<div class="riwa-pay-timeline-dot"></div>'
                        + '<div class="riwa-pay-timeline-content">'
                        + '<div class="riwa-pay-timeline-amount">' + fmt(p.amount) + '</div>'
                        + '<div class="riwa-pay-timeline-meta">'
                        + fmtDate(p.payment_date) + ' · ' + esc(p.method_label)
                        + (p.reference ? ' · <em>' + esc(p.reference) + '</em>' : '')
                        + '</div>'
                        + (p.note ? '<div class="riwa-pay-timeline-note">' + esc(p.note) + '</div>' : '')
                        + '</div>'
                        + '<button class="riwa-pay-delete-payment" data-id="' + p.id + '" title="Supprimer"><span class="dashicons dashicons-trash"></span></button>'
                        + '</div>';
                });
            }
            html += '</div>';

            // Mini formulaire ajout
            html += renderMiniAddForm(booking_id, d);

            $body.html(html);

            // Bind delete
            $body.find('.riwa-pay-delete-payment').on('click', function () {
                if (!confirm('Supprimer ce paiement ?')) return;
                var pid = $(this).data('id');
                $.post(AJAX, { action: 'riwa_payments_delete_payment', nonce: NONCE, payment_id: pid }, function (r) {
                    if (r.success) openDetailPanel(booking_id, name);
                });
            });

            // Bind mini form submit
            $body.find('#mini-pay-submit').on('click', function () {
                submitPayment({
                    booking_id:   booking_id,
                    amount:       $('#mini-pay-amount').val(),
                    method:       $('#mini-pay-method').val(),
                    payment_date: $('#mini-pay-date').val(),
                    reference:    $('#mini-pay-ref').val(),
                    note:         '',
                }, '#mini-pay-msg', function () {
                    openDetailPanel(booking_id, name);
                    loadBookingsList(currentFilter, currentPage);
                });
            });
        });
    }

    function renderMiniAddForm(booking_id, d) {
        var methods_html = '';
        var methods = riwa_payments_config.methods || {};
        Object.keys(methods).forEach(function (k) {
            methods_html += '<option value="' + k + '">' + methods[k] + '</option>';
        });

        return '<div class="riwa-pay-mini-form">'
            + '<div class="riwa-pay-section-title" style="margin:1rem 0 .5rem;"><span class="dashicons dashicons-plus-alt2"></span> Ajouter un paiement</div>'
            + '<div class="riwa-pay-mini-form-grid">'
            + '<input type="number" id="mini-pay-amount" class="riwa-input" step="0.01" min="0" placeholder="Montant" value="' + Math.max(0, d.total_price - d.total_paid).toFixed(2) + '">'
            + '<select id="mini-pay-method" class="riwa-input">' + methods_html + '</select>'
            + '<input type="date" id="mini-pay-date" class="riwa-input" value="' + new Date().toISOString().slice(0, 10) + '">'
            + '<input type="text" id="mini-pay-ref" class="riwa-input" placeholder="Référence…">'
            + '</div>'
            + '<button type="button" class="riwa-btn riwa-btn-primary" id="mini-pay-submit" style="margin-top:.5rem;">'
            + '<span class="dashicons dashicons-yes"></span> Enregistrer</button>'
            + '<span class="riwa-pay-form-msg" id="mini-pay-msg"></span>'
            + '</div>';
    }

    function initDetailPanel() {
        $(document).on('click', '.riwa-pay-open-detail', function () {
            openDetailPanel($(this).data('id'), $(this).data('name'));
        });
        $(document).on('click', '#pay-detail-close, #pay-detail-overlay', function () {
            $('#pay-detail-panel').removeClass('open');
            $('#pay-detail-overlay').removeClass('open');
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Modal ajout paiement depuis table                               */
    /* ---------------------------------------------------------------- */

    function initModal() {
        $(document).on('click', '.riwa-pay-open-modal', function () {
            var $btn    = $(this);
            var id      = $btn.data('id');
            var name    = $btn.data('name');
            var solde   = parseFloat($btn.data('solde') || 0);
            var total   = parseFloat($btn.data('total') || 0);
            var deposit = parseFloat($btn.data('deposit') || 0);
            var due     = $btn.data('due') || '';

            $('#pm-booking-id').val(id);
            $('#pay-modal-title').text('Paiement — ' + name);
            $('#pm-amount').val(solde > 0 ? solde.toFixed(2) : '');
            $('#pm-solde-hint').text(solde > 0 ? 'solde : ' + fmt(solde) : '');
            $('#pm-deposit-percent').val(deposit || '');
            $('#pm-balance-due').val(due);
            $('#pm-method').val('cash');
            $('#pm-date').val(new Date().toISOString().slice(0, 10));
            $('#pm-reference').val('');
            $('#pm-msg').text('');

            updateDepositCalc(total, deposit);

            $('#pay-modal, #pay-modal-overlay').fadeIn(150);
        });

        $('#pm-deposit-percent').on('input', function () {
            var total = parseFloat($('#pm-booking-id').closest('tr').find('td').eq(2).text().replace(/[^\d,]/g, '').replace(',', '.')) || 0;
            updateDepositCalc(total, $(this).val());
        });

        $('#pm-submit').on('click', function () {
            var booking_id      = $('#pm-booking-id').val();
            var amount          = parseFloat($('#pm-amount').val());
            var deposit_percent = parseFloat($('#pm-deposit-percent').val() || 0);
            var balance_due     = $('#pm-balance-due').val();

            if (!booking_id || !amount || amount <= 0) {
                $('#pm-msg').text('Montant requis.').css('color', '#ef4444');
                return;
            }

            // Sauvegarder infos acompte si renseignées
            if (deposit_percent > 0 || balance_due) {
                $.post(AJAX, {
                    action:          'riwa_payments_save_deposit_info',
                    nonce:           NONCE,
                    booking_id:      booking_id,
                    deposit_percent: deposit_percent,
                    balance_due_date: balance_due,
                });
            }

            submitPayment({
                booking_id:   booking_id,
                amount:       amount,
                method:       $('#pm-method').val(),
                payment_date: $('#pm-date').val(),
                reference:    $('#pm-reference').val(),
                note:         '',
            }, '#pm-msg', function () {
                $('#pay-modal, #pay-modal-overlay').fadeOut(150);
                loadBookingsList(currentFilter, currentPage);
                if ($('#tab-dashboard').hasClass('active')) loadDashboard();
            });
        });

        $(document).on('click', '#pay-modal-close, #pm-cancel, #pay-modal-overlay', function () {
            $('#pay-modal, #pay-modal-overlay').fadeOut(150);
        });
    }

    function updateDepositCalc(total, percent) {
        var calc = total > 0 && percent > 0 ? '= ' + fmt(total * percent / 100) : '';
        $('#pm-deposit-calc').text(calc);
    }

    /* ---------------------------------------------------------------- */
    /*  AJAX soumission paiement                                         */
    /* ---------------------------------------------------------------- */

    function submitPayment(data, msgSel, onSuccess) {
        var $msg = $(msgSel);
        $msg.text('Enregistrement…').css('color', '#64748b');

        $.post(AJAX, $.extend({ action: 'riwa_payments_add_payment', nonce: NONCE }, data), function (resp) {
            if (resp.success) {
                $msg.text('Paiement enregistré !').css('color', '#22c55e');
                setTimeout(function () { $msg.text(''); }, 2500);
                if (onSuccess) onSuccess(resp.data);
            } else {
                $msg.text(resp.data || 'Erreur.').css('color', '#ef4444');
            }
        }).fail(function () {
            $msg.text('Erreur réseau.').css('color', '#ef4444');
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Export CSV                                                       */
    /* ---------------------------------------------------------------- */

    function initExport() {
        $('#pay-export-btn').on('click', function (e) {
            e.preventDefault();
            var month = $('#export-month').val();
            var url   = EXPORT + (month ? '&month=' + encodeURIComponent(month) : '');
            window.location.href = url;
        });
    }

    /* ---------------------------------------------------------------- */
    /*  Sécurisation XSS                                                 */
    /* ---------------------------------------------------------------- */

    function esc(str) {
        return $('<div>').text(str || '').html();
    }

    /* ---------------------------------------------------------------- */
    /*  Init                                                             */
    /* ---------------------------------------------------------------- */

    $(document).ready(function () {
        if (!$('#payments-section').length) return;

        initTabs();
        initQuickForm();
        initBookingsList();
        initDetailPanel();
        initModal();
        initExport();

        // Charger le dashboard au démarrage
        loadDashboard();
    });

})(jQuery);

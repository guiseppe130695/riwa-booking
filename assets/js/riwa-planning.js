/**
 * Riwa Planning — Smart Planning Board
 * Timeline horizontale + Quick-action panel + Occupation stats
 */

(function ($) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  État global                                                         */
    /* ------------------------------------------------------------------ */

    var state = {
        view      : '2weeks',   // 'week' | '2weeks' | 'month'
        startDate : null,       // DateTime début de la fenêtre affichée
        data      : null,       // Données reçues du serveur
        loading   : false,
    };

    var AJAX_URL = (typeof riwa_planning_config !== 'undefined') ? riwa_planning_config.ajax_url
                : (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.ajax_url
                : '/wp-admin/admin-ajax.php';
    var NONCE    = (typeof riwa_planning_config !== 'undefined') ? riwa_planning_config.nonce : '';
    var ADMIN_URL = (typeof riwa_planning_config !== 'undefined') ? riwa_planning_config.admin_url
                : (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_url : '';

    /* ------------------------------------------------------------------ */
    /*  Init                                                                */
    /* ------------------------------------------------------------------ */

    function init() {
        // Nonce depuis config localisée (priorité) ou depuis le DOM en fallback
        if (!NONCE) NONCE = $('#riwa-planning-nonce').val() || '';

        // Point de départ = lundi de la semaine courante
        state.startDate = getMondayOf(new Date());

        bindToolbar();
        bindBlockDropdown();
        bindDemoButtons();
        loadData();
    }

    /* ------------------------------------------------------------------ */
    /*  Utilitaires date                                                    */
    /* ------------------------------------------------------------------ */

    function getMondayOf(d) {
        var day = d.getDay(); // 0=dim
        var diff = (day === 0) ? -6 : 1 - day;
        var m = new Date(d);
        m.setDate(m.getDate() + diff);
        m.setHours(0, 0, 0, 0);
        return m;
    }

    function addDays(d, n) {
        var r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    function toYMD(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function parseYMD(s) {
        var p = s.split('-');
        return new Date(parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]));
    }

    function diffDays(a, b) {
        return Math.round((b - a) / 86400000);
    }

    function getWindowDays() {
        switch (state.view) {
            case 'week':   return 7;
            case 'month':  return 30;
            default:       return 14;
        }
    }

    function formatDateFr(d) {
        var days   = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
        var months = ['Jan','Fév','Mar','Apr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()];
    }

    function formatMonthYear(d) {
        var months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                      'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        return months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function formatPrice(v) {
        return parseFloat(v).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' €';
    }

    /* ------------------------------------------------------------------ */
    /*  Chargement données AJAX                                             */
    /* ------------------------------------------------------------------ */

    function loadData() {
        if (state.loading) return;
        state.loading = true;

        var totalDays = getWindowDays();
        var endDate   = addDays(state.startDate, totalDays);

        showLoader(true);

        $.post(AJAX_URL, {
            action     : 'riwa_planning_get_data',
            nonce      : NONCE,
            date_start : toYMD(state.startDate),
            date_end   : toYMD(endDate),
        }, function (resp) {
            state.loading = false;
            showLoader(false);
            if (resp.success) {
                state.data = resp.data;
                renderDemoBanner(resp.data.demo === true);
                render();
            } else {
                showError(resp.data || 'Erreur de chargement');
            }
        }).fail(function () {
            state.loading = false;
            showLoader(false);
            showError('Erreur réseau');
        });
    }

    function showLoader(on) {
        $('#riwa-planning-loader').toggle(on);
        $('#riwa-timeline-render').toggle(!on);
    }

    function renderDemoBanner(isDemo) {
        $('#riwa-demo-banner').remove();
        if (!isDemo) return;
        var html = '<div id="riwa-demo-banner" class="riwa-demo-banner">'
            + '<span class="dashicons dashicons-visibility"></span>'
            + '<div><strong>Mode démonstration</strong> — Ces données sont simulées. '
            + 'Ajoutez votre première réservation pour voir vos vraies données.</div>'
            + '<a href="' + ADMIN_URL + 'admin.php?page=riwa-bookings&section=bookings" class="riwa-demo-banner-cta">Voir les réservations</a>'
            + '</div>';
        $('#planning-section .riwa-section-content').prepend(html);
    }

    function showError(msg) {
        $('#riwa-timeline-render').html(
            '<div class="riwa-planning-error"><span class="dashicons dashicons-warning"></span> ' + msg + '</div>'
        ).show();
    }

    /* ------------------------------------------------------------------ */
    /*  Rendu Timeline                                                      */
    /* ------------------------------------------------------------------ */

    function render() {
        if (!state.data) return;

        var d        = state.data;
        var days     = getWindowDays();
        var startD   = state.startDate;
        var endD     = addDays(startD, days);
        var today    = new Date(); today.setHours(0,0,0,0);

        // Mettre à jour le label période
        $('#plan-period-label').text(
            formatDateFr(startD) + ' – ' + formatDateFr(addDays(endD, -1))
        );

        // Mettre à jour les stats d'occupation
        renderOccupation(d.stats);

        // ── Construire la grille ──────────────────────────────────────

        // Colonnes = jours
        var colWidth = 54; // px par jour (ajusté selon vue)
        if (days <= 7)  colWidth = 80;
        if (days >= 30) colWidth = 38;

        // Index des overrides (date → objet)
        var overridesMap = {};
        (d.overrides || []).forEach(function (o) { overridesMap[o.override_date] = o; });

        // ── Algorithme de placement anti-chevauchement (lane packing) ─
        // Chaque réservation/blocage est assigné à une "lane" (rangée).
        // On cherche la première lane disponible au moment du check_in.
        function assignLanes(items, getStart, getEnd) {
            var lanes = []; // lanes[i] = date de fin de la dernière barre dans cette lane
            return items.map(function (item) {
                var s = getStart(item);
                var e = getEnd(item);
                var lane = -1;
                for (var k = 0; k < lanes.length; k++) {
                    if (lanes[k] <= s) { lane = k; break; }
                }
                if (lane === -1) { lane = lanes.length; lanes.push(e); }
                else { lanes[lane] = e; }
                return { item: item, lane: lane };
            });
        }

        var bookingsVisible = (d.bookings || []).filter(function (b) {
            var s = parseYMD(b.check_in_date), e = parseYMD(b.check_out_date);
            return e > startD && s < endD;
        });
        var blockedVisible = (d.blocked || []).filter(function (bl) {
            var s = parseYMD(bl.date_start), e = parseYMD(bl.date_end);
            return e > startD && s < endD;
        });

        var bookingLanes = assignLanes(bookingsVisible,
            function (b) { return parseYMD(b.check_in_date).getTime(); },
            function (b) { return parseYMD(b.check_out_date).getTime(); }
        );
        var blockedLanes = assignLanes(blockedVisible,
            function (bl) { return parseYMD(bl.date_start).getTime(); },
            function (bl) { return parseYMD(bl.date_end).getTime(); }
        );

        var BAR_H    = 28; // hauteur d'une barre px
        var BAR_GAP  = 4;  // écart entre barres px
        var LANE_H   = BAR_H + BAR_GAP;
        var maxBookLane  = bookingLanes.reduce(function (m, x) { return Math.max(m, x.lane); }, -1);
        var maxBlockLane = blockedLanes.reduce(function (m, x) { return Math.max(m, x.lane); }, -1);
        // Les blocages s'empilent sous les réservations
        var blockLaneOffset = maxBookLane + 1;
        var totalLanes = blockLaneOffset + maxBlockLane + 1;
        if (totalLanes < 1) totalLanes = 1;
        var rowHeight = Math.max(LANE_H, totalLanes * LANE_H) + 8;

        // Construire le HTML
        var html = '<div class="riwa-tl-wrap" style="--tl-col-width:' + colWidth + 'px;">';

        // En-tête : jours
        html += '<div class="riwa-tl-header">';
        html += '<div class="riwa-tl-row-label"></div>';
        for (var i = 0; i < days; i++) {
            var cur     = addDays(startD, i);
            var ymd     = toYMD(cur);
            var isPast  = cur < today;
            var isToday = cur.getTime() === today.getTime();
            var isWE    = cur.getDay() === 0 || cur.getDay() === 6;
            var override = overridesMap[ymd];
            var cls = 'riwa-tl-day-header' +
                (isPast   ? ' is-past'    : '') +
                (isToday  ? ' is-today'   : '') +
                (isWE     ? ' is-weekend' : '') +
                (override ? ' has-override' : '');
            html += '<div class="' + cls + '" data-date="' + ymd + '">';
            html += '<span class="riwa-tl-day-num">' + cur.getDate() + '</span>';
            html += '<span class="riwa-tl-day-name">' + ['Di','Lu','Ma','Me','Je','Ve','Sa'][cur.getDay()] + '</span>';
            if (override && !isPast) {
                html += '<span class="riwa-tl-override-price" title="Prix spécial">' + formatPrice(override.price) + '</span>';
            }
            html += '</div>';
        }
        html += '</div>'; // .riwa-tl-header

        // ── Rangée Villa ──────────────────────────────────────────────
        html += '<div class="riwa-tl-row">';
        html += '<div class="riwa-tl-row-label"><span class="dashicons dashicons-admin-home"></span> Villa</div>';
        html += '<div class="riwa-tl-row-cells" style="position:relative;height:' + rowHeight + 'px;">';

        // Cellules de fond
        for (var j = 0; j < days; j++) {
            var cellDate = addDays(startD, j);
            var cellYmd  = toYMD(cellDate);
            var isCellPast  = cellDate < today;
            var isToday2    = cellDate.getTime() === today.getTime();
            var isWE2       = cellDate.getDay() === 0 || cellDate.getDay() === 6;
            html += '<div class="riwa-tl-cell' +
                (isCellPast ? ' is-past'    : '') +
                (isToday2   ? ' is-today'   : '') +
                (isWE2      ? ' is-weekend' : '') +
                '" data-date="' + cellYmd + '"></div>';
        }

        // Barres de réservations
        bookingLanes.forEach(function (entry) {
            var b        = entry.item;
            var lane     = entry.lane;
            var barStart = parseYMD(b.check_in_date);
            var barEnd   = parseYMD(b.check_out_date);
            var visStart = barStart < startD ? startD : barStart;
            var visEnd   = barEnd   > endD   ? endD   : barEnd;
            var colStart = diffDays(startD, visStart);
            var colSpan  = diffDays(visStart, visEnd);
            if (colSpan <= 0) return;

            var isPastBar = barEnd <= today;
            var pct_left  = (colStart / days * 100).toFixed(3);
            var pct_width = (colSpan  / days * 100).toFixed(3);
            var topPx     = lane * LANE_H;

            var cls = 'riwa-tl-bar riwa-tl-bar--' + b.status;
            if (isPastBar) cls += ' is-past';
            if (toYMD(barEnd) === toYMD(today) && b.housekeeping_status !== 'ready') {
                cls += ' riwa-tl-bar--hk-' + b.housekeeping_status;
            }
            var nights = diffDays(barStart, barEnd);
            var label  = escHtml(b.guest_name);
            if (colSpan >= 2) label += ' · ' + nights + 'n';

            html += '<div class="' + cls + '"' +
                ' style="left:' + pct_left + '%;width:calc(' + pct_width + '% - 3px);top:' + topPx + 'px;height:' + BAR_H + 'px;"' +
                ' data-booking-id="' + b.id + '"' +
                ' data-booking=\'' + JSON.stringify({
                    id: b.id, guest_name: b.guest_name,
                    check_in: b.check_in_date, check_out: b.check_out_date,
                    adults: b.adults_count, children: b.children_count, babies: b.babies_count,
                    total_price: b.total_price, status: b.status, housekeeping: b.housekeeping_status,
                }).replace(/'/g,"&#39;") + '\'' +
                ' title="' + escHtml(b.guest_name) + ' · ' + b.check_in_date + ' → ' + b.check_out_date + '">' +
                '<span class="riwa-tl-bar-label">' + label + '</span>' +
                '</div>';
        });

        // Barres de blocages (empilées sous les réservations)
        blockedLanes.forEach(function (entry) {
            var bl       = entry.item;
            var lane     = blockLaneOffset + entry.lane;
            var blStart  = parseYMD(bl.date_start);
            var blEnd    = parseYMD(bl.date_end);
            var visStart = blStart < startD ? startD : blStart;
            var visEnd   = blEnd   > endD   ? endD   : blEnd;
            var colStart = diffDays(startD, visStart);
            var colSpan  = diffDays(visStart, visEnd);
            if (colSpan <= 0) return;

            var isPastBar = blEnd <= today;
            var pct_left  = (colStart / days * 100).toFixed(3);
            var pct_width = (colSpan  / days * 100).toFixed(3);
            var topPx     = lane * LANE_H;

            var reasonLabel = (typeof riwa_planning_reasons !== 'undefined' && riwa_planning_reasons[bl.reason])
                ? riwa_planning_reasons[bl.reason] : bl.reason;

            html += '<div class="riwa-tl-bar riwa-tl-bar--blocked' + (isPastBar ? ' is-past' : '') + '"' +
                ' style="left:' + pct_left + '%;width:calc(' + pct_width + '% - 3px);top:' + topPx + 'px;height:' + BAR_H + 'px;"' +
                ' data-blocked-id="' + bl.id + '"' +
                ' data-blocked=\'' + JSON.stringify({
                    id: bl.id, date_start: bl.date_start, date_end: bl.date_end,
                    reason: bl.reason, note: bl.note,
                }).replace(/'/g,"&#39;") + '\'' +
                ' title="' + escHtml(reasonLabel) + (bl.note ? ' : ' + escHtml(bl.note) : '') + '">' +
                '<span class="riwa-tl-bar-label"><span class="dashicons dashicons-lock"></span> ' + escHtml(reasonLabel) + '</span>' +
                '</div>';
        });

        html += '</div>'; // .riwa-tl-row-cells
        html += '</div>'; // .riwa-tl-row
        html += '</div>'; // .riwa-tl-wrap

        $('#riwa-timeline-render').html(html);
        bindTimelineEvents();
        renderActivityLog();
    }

    /* ------------------------------------------------------------------ */
    /*  Historique d'activité de la période                                */
    /* ------------------------------------------------------------------ */

    function renderActivityLog() {
        var $log   = $('#riwa-activity-log');
        var $body  = $('#riwa-activity-log-body');
        var $count = $('#riwa-activity-log-count');
        if (!$log.length || !state.data) return;

        var days     = getWindowDays();
        var startD   = state.startDate;
        var endD     = addDays(startD, days);

        var events = [];

        // Réservations dont check_in ou check_out est dans la fenêtre
        (state.data.bookings || []).forEach(function (b) {
            var cin  = parseYMD(b.check_in_date);
            var cout = parseYMD(b.check_out_date);
            var statusLabels = {confirmed: 'Confirmée', pending: 'En attente', cancelled: 'Annulée'};
            var statusCls    = {confirmed: 'log-confirmed', pending: 'log-pending', cancelled: 'log-cancelled'};

            // Arrivée dans la fenêtre
            if (cin >= startD && cin < endD) {
                events.push({
                    date   : cin,
                    dateStr: b.check_in_date,
                    type   : 'arrival',
                    icon   : 'dashicons-migrate',
                    label  : 'Arrivée · ' + escHtml(b.guest_name),
                    sub    : statusLabels[b.status] || b.status,
                    cls    : statusCls[b.status] || '',
                });
            }
            // Départ dans la fenêtre
            if (cout > startD && cout <= endD) {
                events.push({
                    date   : cout,
                    dateStr: b.check_out_date,
                    type   : 'departure',
                    icon   : 'dashicons-undo',
                    label  : 'Départ · ' + escHtml(b.guest_name),
                    sub    : statusLabels[b.status] || b.status,
                    cls    : statusCls[b.status] || '',
                });
            }
        });

        // Blocages qui commencent dans la fenêtre
        var reasonLabels = (typeof riwa_planning_reasons !== 'undefined') ? riwa_planning_reasons : {};
        (state.data.blocked || []).forEach(function (bl) {
            var blStart = parseYMD(bl.date_start);
            if (blStart >= startD && blStart < endD) {
                var reasonLabel = reasonLabels[bl.reason] || bl.reason;
                events.push({
                    date   : blStart,
                    dateStr: bl.date_start,
                    type   : 'blocked',
                    icon   : 'dashicons-lock',
                    label  : 'Blocage · ' + escHtml(reasonLabel),
                    sub    : bl.note || '',
                    cls    : 'log-blocked',
                });
            }
        });

        // Prix spéciaux dans la fenêtre
        (state.data.overrides || []).forEach(function (o) {
            var d = parseYMD(o.override_date);
            if (d >= startD && d < endD) {
                events.push({
                    date   : d,
                    dateStr: o.override_date,
                    type   : 'price',
                    icon   : 'dashicons-tag',
                    label  : 'Prix spécial · ' + formatPrice(o.price),
                    sub    : 'Le ' + d.toLocaleDateString('fr-FR'),
                    cls    : 'log-price',
                });
            }
        });

        // Trier par date croissante
        events.sort(function (a, b) { return a.date - b.date; });

        if (events.length === 0) {
            $log.hide();
            return;
        }

        // Construire le HTML
        var viewLabel = state.view === 'week' ? 'la semaine'
                      : state.view === 'month' ? 'le mois'
                      : 'les 2 semaines';
        $('#riwa-activity-log-title').text('Activité sur ' + viewLabel);
        $count.text(events.length + ' événement' + (events.length > 1 ? 's' : ''));

        var html = '';
        var lastDate = '';
        events.forEach(function (ev) {
            var dateLabel = ev.date.toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long'});
            if (dateLabel !== lastDate) {
                html += '<div class="riwa-log-date-group">' + dateLabel + '</div>';
                lastDate = dateLabel;
            }
            html += '<div class="riwa-log-item ' + ev.cls + '">'
                + '<span class="riwa-log-icon dashicons ' + ev.icon + '"></span>'
                + '<div class="riwa-log-body">'
                + '<span class="riwa-log-label">' + ev.label + '</span>'
                + (ev.sub ? '<span class="riwa-log-sub">' + escHtml(ev.sub) + '</span>' : '')
                + '</div>'
                + '</div>';
        });

        $body.html(html);
        $log.show();
    }

    /* ------------------------------------------------------------------ */
    /*  Stats d'occupation                                                  */
    /* ------------------------------------------------------------------ */

    function renderOccupation(stats) {
        if (!stats || !stats.total_nights) {
            $('#riwa-occ-bar').hide();
            return;
        }
        $('#riwa-occ-bar').show();
        $('#occ-rate').text(stats.occupation_rate + ' %');
        $('#occ-nights').text(stats.occupied_nights + ' / ' + stats.total_nights);
        $('#occ-empty').text(stats.empty_nights);
        $('#occ-revenue').text(formatPrice(stats.revenue));
        $('#occ-potential').text(formatPrice(stats.potential_revenue));
        $('#occ-progress-fill').css('width', Math.min(100, stats.occupation_rate) + '%');
    }

    /* ------------------------------------------------------------------ */
    /*  Événements Timeline                                                 */
    /* ------------------------------------------------------------------ */

    function bindTimelineEvents() {

        // Click sur une barre de réservation
        $('#riwa-timeline-render').on('click', '.riwa-tl-bar--confirmed, .riwa-tl-bar--pending', function (e) {
            e.stopPropagation();
            var data = $(this).data('booking');
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (ex) {}
            }
            showBookingPanel(data);
        });

        // Click sur une barre bloquée
        $('#riwa-timeline-render').on('click', '.riwa-tl-bar--blocked', function (e) {
            e.stopPropagation();
            var data = $(this).data('blocked');
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (ex) {}
            }
            showBlockedPanel(data);
        });

        // Click sur une cellule vide → quick panel date
        $('#riwa-timeline-render').on('click', '.riwa-tl-cell', function (e) {
            var date = $(this).data('date');
            if (date) showDatePanel(date);
        });

        // Click sur header de jour (price override)
        $('#riwa-timeline-render').on('click', '.riwa-tl-day-header', function (e) {
            var date = $(this).data('date');
            if (date) showDatePanel(date);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Quick Panel                                                         */
    /* ------------------------------------------------------------------ */

    function openPanel(html) {
        $('#riwa-quick-panel-empty').hide();
        $('#riwa-quick-panel-content').html(html).show();
        $('#riwa-quick-panel').addClass('is-open');
    }

    function closePanel() {
        $('#riwa-quick-panel-content').hide().empty();
        $('#riwa-quick-panel-empty').show();
        $('#riwa-quick-panel').removeClass('is-open');
    }

    // Popup détail réservation (réutilise #riwa-details-popup de booking-detail-popup.php)
    function showBookingPanel(b) {
        if (!b) return;

        var $overlay = $('#riwa-details-popup');
        if (!$overlay.length) return;

        var checkin  = b.check_in;
        var checkout = b.check_out;
        var nights   = diffDays(parseYMD(checkin), parseYMD(checkout));
        var adults   = parseInt(b.adults)   || 0;
        var children = parseInt(b.children) || 0;
        var babies   = parseInt(b.babies)   || 0;
        var price    = parseFloat(b.total_price) || 0;
        var status   = b.status;
        var id       = b.id;

        var statusLabels    = {confirmed: 'Confirmée', pending: 'En attente', cancelled: 'Annulée'};
        var statusClassMap  = {confirmed: 'status-confirmed', pending: 'status-pending', cancelled: 'status-cancelled'};
        var ref = 'RIWA-' + String(id).padStart(6, '0');
        var nightLabel = nights + ' nuit' + (nights > 1 ? 's' : '');

        function fmtDate(str) {
            if (!str) return '—';
            var d = new Date(str);
            return d.toLocaleDateString('fr-FR');
        }

        // Sidebar
        $('#popup-reference').text(ref);
        $('#popup-client-name').text(b.guest_name || '—');
        $('#popup-client-email').text('—');
        $('#popup-client-phone').text('—');
        $('#popup-duration').text(nightLabel);
        $('#popup-dates-range').text(fmtDate(checkin) + ' → ' + fmtDate(checkout));
        $('#popup-total-price').text(price > 0 ? price.toLocaleString('fr-FR') + ' €' : '—');
        $('#popup-price-per-night-label').text('');

        // Panneau droit header
        $('#popup-status-badge')
            .text(statusLabels[status] || status)
            .attr('class', 'riwa-popup-status-badge ' + (statusClassMap[status] || ''));
        $('#popup-booking-id-fmt').text(ref);
        $('#popup-created').text('');
        $('#popup-requests-sidebar').text('—');

        // Voyageurs (sidebar)
        var travelersHTML = '';
        if (adults   > 0) travelersHTML += '<span class="riwa-traveler-badge">' + adults   + ' adulte'  + (adults   > 1 ? 's' : '') + '</span>';
        if (children > 0) travelersHTML += '<span class="riwa-traveler-badge">' + children + ' enfant'  + (children > 1 ? 's' : '') + '</span>';
        if (babies   > 0) travelersHTML += '<span class="riwa-traveler-badge">' + babies   + ' bébé'    + (babies   > 1 ? 's' : '') + '</span>';
        if (!travelersHTML) travelersHTML = '<span style="opacity:.7">—</span>';
        $('#popup-travelers').html(travelersHTML);

        // Timeline
        var today2    = new Date(); today2.setHours(0,0,0,0);
        var dCheckin  = checkin  ? new Date(checkin)  : null;
        var dCheckout = checkout ? new Date(checkout) : null;
        if (dCheckin)  dCheckin.setHours(0,0,0,0);
        if (dCheckout) dCheckout.setHours(0,0,0,0);

        $('#popup-submitted-date').text('—');
        $('#popup-checkin-timeline').text(fmtDate(checkin));
        $('#popup-checkout-timeline').text(fmtDate(checkout));
        $('#popup-staying-desc').text(nightLabel + ' à la villa');
        $('#popup-checkin-footer').text(fmtDate(checkin));
        $('#popup-checkout-footer').text(fmtDate(checkout));
        $('#popup-total-price-footer').text(price > 0 ? price.toLocaleString('fr-FR') + ' €' : '—');

        // États timeline
        var $items = $('#popup-timeline .riwa-timeline-item');
        $items.removeClass('tl-done tl-active tl-pending tl-cancelled');
        if (status === 'cancelled') {
            $items.filter('[data-step="submitted"]').addClass('tl-done');
            $items.filter('[data-step="confirmed"]').addClass('tl-cancelled');
        } else if (status === 'pending') {
            $items.filter('[data-step="submitted"]').addClass('tl-active');
        } else if (status === 'confirmed') {
            var stepsOrder = ['submitted', 'confirmed', 'checkin', 'staying', 'checkout', 'done'];
            var activeStep;
            if (!dCheckin || today2 < dCheckin) {
                activeStep = 'confirmed';
            } else if (today2 >= dCheckin && dCheckout && today2 < dCheckout) {
                activeStep = 'staying';
            } else if (dCheckout && today2 >= dCheckout) {
                activeStep = 'done';
            } else {
                activeStep = 'confirmed';
            }
            var reached = false;
            $.each(stepsOrder, function(i, step) {
                var $item = $items.filter('[data-step="' + step + '"]');
                if (step === activeStep) {
                    reached = true;
                    $item.addClass(step === 'done' ? 'tl-done' : 'tl-active');
                } else if (!reached) {
                    $item.addClass('tl-done');
                }
            });
        }

        // Actions + ménage
        var hkLabels = (typeof riwa_planning_hk !== 'undefined') ? riwa_planning_hk : {pending: 'À nettoyer', cleaning: 'En cours', ready: 'Prêt'};
        var actionsHTML = '';

        // Section ménage
        actionsHTML += '<div class="riwa-popup-hk-section">'
            + '<span class="riwa-popup-hk-label">Ménage</span>'
            + '<div class="riwa-popup-hk-btns" data-booking-id="' + id + '">';
        ['pending', 'cleaning', 'ready'].forEach(function (s) {
            var lbl = hkLabels[s] || s;
            actionsHTML += '<button type="button" class="riwa-popup-hk-btn riwa-planning-hk-btn'
                + (b.housekeeping === s ? ' is-active' : '') + '" data-hk="' + s + '">' + lbl + '</button>';
        });
        actionsHTML += '</div></div>';

        // Confirmer (si en attente)
        if (status === 'pending') {
            actionsHTML += '<button class="riwa-popup-action-btn confirm riwa-planning-confirm-btn" data-id="' + id + '">'
                + '<span class="dashicons dashicons-yes"></span> Confirmer</button>';
        }
        // Annuler
        if (status !== 'cancelled') {
            actionsHTML += '<button class="riwa-popup-action-btn cancel riwa-planning-cancel-btn" data-id="' + id + '">'
                + '<span class="dashicons dashicons-no"></span> Annuler</button>';
        }
        // Lien vers réservations
        actionsHTML += '<a class="riwa-popup-action-btn" href="'
            + escHtml(ADMIN_URL + 'admin.php?page=riwa-bookings&section=bookings') + '">'
            + '<span class="dashicons dashicons-visibility"></span> Voir les réservations</a>';

        $('#popup-actions').html(actionsHTML);
        $('#popup-upsells-step').hide();

        // Afficher la popup
        $overlay.css('display', 'flex').hide().fadeIn(200);

        // Bind des boutons ménage (dans la popup)
        $overlay.off('click.planningHk').on('click.planningHk', '.riwa-planning-hk-btn', function () {
            var $btn      = $(this);
            var bookingId = $btn.closest('[data-booking-id]').data('booking-id');
            var hkStatus  = $btn.data('hk');
            $btn.closest('.riwa-popup-hk-btns').find('.riwa-popup-hk-btn').removeClass('is-active');
            $btn.addClass('is-active');
            $.post(AJAX_URL, {
                action             : 'riwa_planning_update_housekeeping',
                nonce              : NONCE,
                booking_id         : bookingId,
                housekeeping_status: hkStatus,
            });
            // Mettre à jour l'état local
            if (state.data && state.data.bookings) {
                state.data.bookings.forEach(function (bk) {
                    if (parseInt(bk.id) === parseInt(bookingId)) bk.housekeeping_status = hkStatus;
                });
            }
        });

        // Confirmer depuis la popup planning
        $overlay.off('click.planningConfirm').on('click.planningConfirm', '.riwa-planning-confirm-btn', function () {
            var bookingId = $(this).data('id');
            var nonce2 = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';
            $.post(location.href, {
                action: 'update_status', booking_id: bookingId,
                new_status: 'confirmed', riwa_admin_nonce: nonce2,
            }, function () {
                $overlay.fadeOut(200, function () { loadData(); });
            });
        });

        // Annuler depuis la popup planning
        $overlay.off('click.planningCancel').on('click.planningCancel', '.riwa-planning-cancel-btn', function () {
            if (!confirm('Annuler cette réservation ?')) return;
            var bookingId = $(this).data('id');
            var nonce2 = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.admin_nonce : '';
            $.post(location.href, {
                action: 'update_status', booking_id: bookingId,
                new_status: 'cancelled', riwa_admin_nonce: nonce2,
            }, function () {
                $overlay.fadeOut(200, function () { loadData(); });
            });
        });
    }

    // Popup blocage (réutilise #riwa-blocked-popup)
    function showBlockedPanel(bl) {
        if (!bl) return;

        var $overlay = $('#riwa-blocked-popup');
        if (!$overlay.length) return;

        var reasonLabels = (typeof riwa_planning_reasons !== 'undefined') ? riwa_planning_reasons : {};
        var reasonLabel  = reasonLabels[bl.reason] || bl.reason;
        var nights  = diffDays(parseYMD(bl.date_start), parseYMD(bl.date_end));
        var nightLabel = nights + ' nuit' + (nights > 1 ? 's' : '');

        function fmtDate(str) {
            if (!str) return '—';
            return new Date(str).toLocaleDateString('fr-FR');
        }

        // Sidebar
        $('#bl-popup-reason').text(reasonLabel);
        $('#bl-popup-duration').text(nightLabel);
        $('#bl-popup-dates-range').text(fmtDate(bl.date_start) + ' → ' + fmtDate(bl.date_end));
        $('#bl-popup-note').text(bl.note || 'Aucune note');

        // Header droit
        $('#bl-popup-sub').text(reasonLabel + (bl.note ? ' · ' + bl.note : ''));

        // Timeline : état selon dates
        var today3    = new Date(); today3.setHours(0,0,0,0);
        var dStart    = new Date(bl.date_start); dStart.setHours(0,0,0,0);
        var dEnd      = new Date(bl.date_end);   dEnd.setHours(0,0,0,0);
        var $tlActive = $('#bl-tl-active');
        $tlActive.removeClass('tl-done tl-active');

        if (today3 < dStart) {
            // À venir
            $('#bl-tl-label').text('Période à venir');
            $('#bl-tl-desc').text('Du ' + fmtDate(bl.date_start) + ' au ' + fmtDate(bl.date_end));
            $tlActive.addClass('tl-active');
        } else if (today3 >= dStart && today3 < dEnd) {
            // En cours
            $('#bl-tl-label').text('Période en cours');
            $('#bl-tl-desc').text(nightLabel + ' — se termine le ' + fmtDate(bl.date_end));
            $tlActive.addClass('tl-active');
        } else {
            // Passé
            $('#bl-tl-label').text('Période terminée');
            $('#bl-tl-desc').text('Du ' + fmtDate(bl.date_start) + ' au ' + fmtDate(bl.date_end));
            $tlActive.addClass('tl-done');
        }

        // Footer
        $('#bl-popup-footer-start').text(fmtDate(bl.date_start));
        $('#bl-popup-footer-end').text(fmtDate(bl.date_end));
        $('#bl-popup-footer-nights').text(nightLabel);

        // Bouton supprimer
        $('#bl-popup-delete-btn').data('id', bl.id).attr('data-id', bl.id);

        // Afficher la popup
        $overlay.css('display', 'flex').hide().fadeIn(200);

        // Bind suppression
        $overlay.off('click.blDelete').on('click.blDelete', '.riwa-planning-delete-blocked-btn', function () {
            if (!confirm('Débloquer ces dates ?')) return;
            var id = $(this).data('id');
            $.post(AJAX_URL, {
                action: 'riwa_planning_delete_blocked',
                nonce : NONCE,
                id    : id,
            }, function (resp) {
                if (resp.success) {
                    $overlay.fadeOut(200, function () { loadData(); });
                }
            });
        });
    }

    // Panneau date vide
    function showDatePanel(date) {
        var d = parseYMD(date);
        var overridesMap = {};
        if (state.data && state.data.overrides) {
            state.data.overrides.forEach(function (o) { overridesMap[o.override_date] = o; });
        }
        var currentPrice = overridesMap[date] ? overridesMap[date].price : '';

        var html = '<div class="riwa-qp-section">';
        html += '<div class="riwa-qp-header">';
        html += '<span class="riwa-qp-icon dashicons dashicons-calendar"></span>';
        html += '<div><strong>' + formatDateFr(d) + '</strong><span class="riwa-status-badge riwa-status--free">Libre</span></div>';
        html += '</div>';

        // Override de prix
        html += '<div class="riwa-qp-price-override">';
        html += '<label class="riwa-qp-label">Prix spécial pour cette date</label>';
        html += '<div class="riwa-qp-price-row">';
        html += '<input type="number" id="qp-price-input" class="riwa-form-input" min="0" step="1" placeholder="Ex: 280" value="' + escHtml(String(currentPrice)) + '">';
        html += '<span class="riwa-qp-price-unit">€ / nuit</span>';
        html += '</div>';
        html += '<button type="button" class="riwa-btn riwa-btn-primary riwa-btn-sm riwa-save-price-btn" data-date="' + date + '">'
            + '<span class="dashicons dashicons-yes"></span> Appliquer</button>';
        if (currentPrice) {
            html += '<button type="button" class="riwa-btn riwa-btn-secondary riwa-btn-sm riwa-remove-price-btn" data-date="' + date + '">'
                + 'Supprimer le prix spécial</button>';
        }
        html += '</div>';

        // Bloquer rapidement
        html += '<div class="riwa-qp-quick-block">';
        html += '<label class="riwa-qp-label">Bloquer cette date</label>';
        html += '<div class="riwa-qp-hk-btns">';
        var reasons = (typeof riwa_planning_reasons !== 'undefined') ? riwa_planning_reasons : {};
        Object.keys(reasons).forEach(function (k) {
            html += '<button type="button" class="riwa-qp-hk-btn riwa-quick-block-btn" data-reason="' + k + '" data-date="' + date + '">'
                + escHtml(reasons[k]) + '</button>';
        });
        html += '</div>';
        html += '</div>';

        html += '</div>';
        html += '<button type="button" class="riwa-qp-close" id="riwa-qp-close">&#x2715; Fermer</button>';

        openPanel(html);
        bindPanelEvents();
    }

    function bindPanelEvents() {

        // Fermer panel
        $('#riwa-quick-panel-content').off('click', '#riwa-qp-close').on('click', '#riwa-qp-close', closePanel);

        // Housekeeping buttons
        $('#riwa-quick-panel-content').off('click', '.riwa-qp-hk-btn[data-hk]').on('click', '.riwa-qp-hk-btn[data-hk]', function () {
            var $btn       = $(this);
            var bookingId  = $btn.closest('[data-booking-id]').data('booking-id');
            var hkStatus   = $btn.data('hk');
            $btn.closest('.riwa-qp-hk-btns').find('.riwa-qp-hk-btn').removeClass('is-active');
            $btn.addClass('is-active');
            $.post(AJAX_URL, {
                action             : 'riwa_planning_update_housekeeping',
                nonce              : NONCE,
                booking_id         : bookingId,
                housekeeping_status: hkStatus,
            });
            // Mettre à jour l'état local
            if (state.data && state.data.bookings) {
                state.data.bookings.forEach(function (b) {
                    if (parseInt(b.id) === parseInt(bookingId)) b.housekeeping_status = hkStatus;
                });
            }
        });

        // Supprimer blocage
        $('#riwa-quick-panel-content').off('click', '.riwa-delete-blocked-btn').on('click', '.riwa-delete-blocked-btn', function () {
            if (!confirm('Débloquer ces dates ?')) return;
            var id = $(this).data('id');
            $.post(AJAX_URL, {
                action: 'riwa_planning_delete_blocked',
                nonce : NONCE,
                id    : id,
            }, function (resp) {
                if (resp.success) { closePanel(); loadData(); }
            });
        });

        // Sauvegarder override prix
        $('#riwa-quick-panel-content').off('click', '.riwa-save-price-btn').on('click', '.riwa-save-price-btn', function () {
            var date  = $(this).data('date');
            var price = parseFloat($('#qp-price-input').val()) || 0;
            $.post(AJAX_URL, {
                action: 'riwa_planning_save_price_override',
                nonce : NONCE,
                date  : date,
                price : price,
            }, function (resp) {
                if (resp.success) { closePanel(); loadData(); }
            });
        });

        // Supprimer override prix
        $('#riwa-quick-panel-content').off('click', '.riwa-remove-price-btn').on('click', '.riwa-remove-price-btn', function () {
            var date = $(this).data('date');
            $.post(AJAX_URL, {
                action: 'riwa_planning_save_price_override',
                nonce : NONCE,
                date  : date,
                price : 0,
            }, function (resp) {
                if (resp.success) { closePanel(); loadData(); }
            });
        });

        // Blocage rapide depuis date panel
        $('#riwa-quick-panel-content').off('click', '.riwa-quick-block-btn').on('click', '.riwa-quick-block-btn', function () {
            var reason    = $(this).data('reason');
            var date      = $(this).data('date');
            var dateEnd   = toYMD(addDays(parseYMD(date), 1));
            $.post(AJAX_URL, {
                action     : 'riwa_planning_add_blocked',
                nonce      : NONCE,
                date_start : date,
                date_end   : dateEnd,
                reason     : reason,
                note       : '',
            }, function (resp) {
                if (resp.success) { closePanel(); loadData(); }
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Boutons Démo : Seed / Clear                                         */
    /* ------------------------------------------------------------------ */

    function bindDemoButtons() {
        var $seedBtn  = $('#riwa-demo-seed-btn');
        var $clearBtn = $('#riwa-demo-clear-btn');

        // Afficher le bouton "Effacer" si des données démo existent déjà
        function checkDemoState() {
            if (state.data && state.data.bookings && state.data.bookings.length > 0) {
                // Vérifier si au moins une réservation est [DEMO]
                var hasDemo = state.data.bookings.some(function (b) {
                    return b.guest_name && b.guest_name.indexOf('[DEMO]') === 0;
                });
                $clearBtn.toggle(hasDemo);
                $seedBtn.toggle(!hasDemo);
            } else {
                $clearBtn.hide();
                $seedBtn.show();
            }
        }

        // Appeler après chaque render
        var origRender = render;
        render = function () {
            origRender();
            checkDemoState();
        };

        $seedBtn.on('click', function () {
            if (!confirm('Injecter 24 réservations de démonstration + blocages + prix spéciaux en base ?')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('Injection…');
            $.post(AJAX_URL, {
                action: 'riwa_planning_seed_demo',
                nonce : NONCE,
            }, function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Données démo');
                if (resp.success) {
                    loadData();
                } else {
                    alert('Erreur : ' + (resp.data || 'inconnue'));
                }
            }).fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Données démo');
                alert('Erreur réseau');
            });
        });

        $clearBtn.on('click', function () {
            if (!confirm('Supprimer toutes les données [DEMO] de la base ? Cette action est irréversible.')) return;
            var $btn = $(this);
            $btn.prop('disabled', true).text('Suppression…');
            $.post(AJAX_URL, {
                action: 'riwa_planning_clear_demo',
                nonce : NONCE,
            }, function (resp) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-remove"></span> Effacer démo');
                if (resp.success) {
                    loadData();
                } else {
                    alert('Erreur : ' + (resp.data || 'inconnue'));
                }
            }).fail(function () {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-remove"></span> Effacer démo');
                alert('Erreur réseau');
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Toolbar navigation                                                  */
    /* ------------------------------------------------------------------ */

    function bindToolbar() {

        // Navigation < >
        $('#plan-prev').on('click', function () {
            state.startDate = addDays(state.startDate, -getWindowDays());
            loadData();
        });
        $('#plan-next').on('click', function () {
            state.startDate = addDays(state.startDate, getWindowDays());
            loadData();
        });
        $('#plan-today').on('click', function () {
            state.startDate = getMondayOf(new Date());
            loadData();
        });

        // Sélecteur de vue
        $('#plan-view-tabs').on('click', '.riwa-view-tab', function () {
            $('#plan-view-tabs .riwa-view-tab').removeClass('active');
            $(this).addClass('active');
            state.view = $(this).data('view');
            // Réajuster le startDate pour la nouvelle vue
            if (state.view === 'month') {
                var d = new Date();
                state.startDate = new Date(d.getFullYear(), d.getMonth(), 1);
            } else {
                state.startDate = getMondayOf(state.startDate.getTime() ? state.startDate : new Date());
            }
            loadData();
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Dropdown Blocage                                                    */
    /* ------------------------------------------------------------------ */

    function bindBlockDropdown() {
        var $wrap     = $('#riwa-block-dropdown-wrap');
        var $btn      = $('#riwa-block-open-btn');
        var $dropdown = $('#riwa-block-form-dropdown');

        function positionDD() {
            var rect = $btn[0].getBoundingClientRect();
            var w    = 340;
            var top  = rect.bottom + 6;
            var left = rect.right - w;
            if (left < 8) left = 8;
            $dropdown.css({ top: top + 'px', left: left + 'px', right: 'auto', width: w + 'px' });
        }

        $btn.on('click', function (e) {
            e.stopPropagation();
            if ($dropdown.is(':visible')) {
                $dropdown.stop(true).slideUp(130);
            } else {
                positionDD();
                $dropdown.stop(true).slideDown(160);
            }
        });

        $('#riwa-block-close, #riwa-block-cancel-btn').on('click', function () {
            $dropdown.stop(true).slideUp(130);
        });

        $(document).on('click', function (e) {
            if ($dropdown.is(':visible') && !$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
                $dropdown.stop(true).slideUp(130);
            }
        });

        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $dropdown.is(':visible')) $dropdown.stop(true).slideUp(130);
        });

        // Sauvegarder blocage
        $('#riwa-block-save-btn').on('click', function () {
            var start  = $('#block-date-start').val();
            var end    = $('#block-date-end').val();
            var reason = $('#block-reason').val();
            var note   = $('#block-note').val();
            if (!start || !end || end <= start) {
                alert('Veuillez sélectionner des dates valides.');
                return;
            }
            var $btn2 = $(this);
            $btn2.prop('disabled', true);
            $.post(AJAX_URL, {
                action     : 'riwa_planning_add_blocked',
                nonce      : NONCE,
                date_start : start,
                date_end   : end,
                reason     : reason,
                note       : note,
            }, function (resp) {
                $btn2.prop('disabled', false);
                if (resp.success) {
                    $dropdown.stop(true).slideUp(130);
                    $('#block-date-start, #block-date-end, #block-note').val('');
                    loadData();
                } else {
                    alert(resp.data || 'Erreur lors du blocage.');
                }
            });
        });
    }

    /* ------------------------------------------------------------------ */
    /*  Utilitaire XSS                                                      */
    /* ------------------------------------------------------------------ */

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /* ------------------------------------------------------------------ */
    /*  Démarrage                                                           */
    /* ------------------------------------------------------------------ */

    $(document).ready(function () {
        if ($('#planning-section').length) {
            init();
        }
    });

}(jQuery));

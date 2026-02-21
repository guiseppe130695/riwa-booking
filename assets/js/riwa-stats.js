/**
 * Riwa Stats — Pulse Board
 * 3 onglets : Pulse / Analyse / Prévision
 * Chart.js requis (chargé via CDN)
 */

(function ($) {
    'use strict';

    var AJAX_URL = (typeof riwa_stats_config !== 'undefined') ? riwa_stats_config.ajax_url : '/wp-admin/admin-ajax.php';
    var NONCE    = (typeof riwa_stats_config !== 'undefined') ? riwa_stats_config.nonce    : '';

    /* ------------------------------------------------------------------ */
    /*  État                                                               */
    /* ------------------------------------------------------------------ */

    var state = {
        tab        : 'pulse',
        year       : new Date().getFullYear(),
        charts     : {},   // instances Chart.js en cours
        loaded     : {},   // tabs déjà chargés
    };

    /* ------------------------------------------------------------------ */
    /*  Init                                                               */
    /* ------------------------------------------------------------------ */

    function init() {
        if (!$('#stats-section').length) return;

        // Navigation onglets
        $(document).on('click', '#riwa-stats-nav .riwa-stats-tab', function () {
            var tab = $(this).data('tab');
            if (tab === state.tab) return;
            state.tab = tab;
            state.loaded = {}; // reset cache si on veut re-charger
            $('#riwa-stats-nav .riwa-stats-tab').removeClass('active');
            $(this).addClass('active');
            $('.riwa-stats-tab-content').removeClass('active');
            $('#riwa-stats-tab-' + tab).addClass('active');
            loadTab(tab);
        });

        // Charger Pulse au démarrage
        loadTab('pulse');
    }

    /* ------------------------------------------------------------------ */
    /*  Chargement AJAX                                                    */
    /* ------------------------------------------------------------------ */

    function loadTab(tab) {
        if (state.loaded[tab]) return;

        showLoader(true);

        $.post(AJAX_URL, {
            action : 'riwa_stats_get_data',
            nonce  : NONCE,
            tab    : tab,
            year   : state.year,
        }, function (resp) {
            showLoader(false);
            if (!resp.success) return;
            state.loaded[tab] = true;
            switch (tab) {
                case 'pulse':    renderPulse(resp.data);    break;
                case 'analysis': renderAnalysis(resp.data); break;
                case 'forecast': renderForecast(resp.data); break;
            }
        }).fail(function () {
            showLoader(false);
        });
    }

    function showLoader(on) {
        $('#riwa-stats-loader').toggle(on);
    }

    /* ------------------------------------------------------------------ */
    /*  Utilitaires                                                        */
    /* ------------------------------------------------------------------ */

    function fmtPrice(v) {
        return parseFloat(v || 0).toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0}) + ' €';
    }

    function escHtml(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
            .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }

    function destroyChart(id) {
        if (state.charts[id]) {
            state.charts[id].destroy();
            delete state.charts[id];
        }
    }

    function buildChart(type, canvasId, labels, datasets, opts) {
        destroyChart(canvasId);
        var ctx = document.getElementById(canvasId);
        if (!ctx || typeof Chart === 'undefined') return;
        state.charts[canvasId] = new Chart(ctx, {
            type: type,
            data: { labels: labels, datasets: datasets },
            options: $.extend(true, {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: datasets.length > 1 },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var val = ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed;
                                if (opts && opts.pct) return val + ' %';
                                if (opts && opts.raw) return val;
                                return fmtPrice(val);
                            }
                        }
                    }
                },
                scales: type !== 'doughnut' ? {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' },
                         ticks: { color: '#94a3b8', font: { size: 11 } } },
                    x: { grid: { display: false },
                         ticks: { color: '#64748b', font: { size: 11 } } }
                } : undefined,
            }, opts || {}),
        });
    }

    /* ------------------------------------------------------------------ */
    /*  PULSE                                                              */
    /* ------------------------------------------------------------------ */

    function renderPulse(data) {
        var h       = data.health;
        var week    = data.week;
        var alerts  = data.alerts || [];

        // ── Score santé ───────────────────────────────────────────────
        var gradeColors = {A:'#16a34a', B:'#2563eb', C:'#d97706', D:'#dc2626'};
        var color = gradeColors[h.grade] || '#94a3b8';

        var scoreHTML = '<div class="riwa-pulse-grid">';

        // Carte score
        scoreHTML += '<div class="riwa-pulse-score-card">';
        scoreHTML += '<div class="riwa-pulse-score-ring" style="--score-color:' + color + ';--score:' + h.score + ';">';
        scoreHTML += '<svg viewBox="0 0 100 100" class="riwa-score-svg">';
        scoreHTML += '<circle cx="50" cy="50" r="42" fill="none" stroke="#e2e8f0" stroke-width="10"/>';
        var circ = 2 * Math.PI * 42;
        var dash = (h.score / 100) * circ;
        scoreHTML += '<circle cx="50" cy="50" r="42" fill="none" stroke="' + color + '" stroke-width="10"'
            + ' stroke-dasharray="' + dash.toFixed(1) + ' ' + circ.toFixed(1) + '"'
            + ' stroke-linecap="round" transform="rotate(-90 50 50)"/>';
        scoreHTML += '</svg>';
        scoreHTML += '<div class="riwa-score-inner">'
            + '<span class="riwa-score-value" style="color:' + color + '">' + h.score + '</span>'
            + '<span class="riwa-score-label">/100</span>'
            + '</div>';
        scoreHTML += '</div>';
        scoreHTML += '<div class="riwa-pulse-score-info">';
        scoreHTML += '<div class="riwa-pulse-grade" style="background:' + color + '">Grade ' + h.grade + '</div>';
        scoreHTML += '<div class="riwa-pulse-grade-label">' + h.label + '</div>';
        scoreHTML += '<div class="riwa-pulse-breakdown">';
        var bd = h.breakdown;
        scoreHTML += pulseBar('Occupation', bd.occupation, 40, color);
        scoreHTML += pulseBar('Confirmation', bd.confirmation, 30, color);
        scoreHTML += pulseBar('Non-annulation', bd.annulation, 20, color);
        scoreHTML += pulseBar('Ménage', bd.menage, 10, color);
        scoreHTML += '</div>';
        scoreHTML += '</div>';
        scoreHTML += '</div>'; // riwa-pulse-score-card

        // Météo financière (7 jours)
        scoreHTML += '<div class="riwa-pulse-week-card">';
        scoreHTML += '<div class="riwa-pulse-week-header">'
            + '<span class="dashicons dashicons-money-alt"></span>'
            + '<span>CA des 7 derniers jours</span>'
            + '<strong class="riwa-pulse-week-total">' + fmtPrice(week.total) + '</strong>'
            + '</div>';
        var maxVal = Math.max.apply(null, week.values.concat([1]));
        scoreHTML += '<div class="riwa-pulse-week-bars">';
        for (var wi = 0; wi < week.labels.length; wi++) {
            var pct = Math.round(week.values[wi] / maxVal * 100);
            scoreHTML += '<div class="riwa-week-col">'
                + '<div class="riwa-week-bar-wrap">'
                + '<div class="riwa-week-bar-fill" style="height:' + pct + '%"'
                + (week.values[wi] > 0 ? ' title="' + fmtPrice(week.values[wi]) + '"' : '') + '></div>'
                + '</div>'
                + '<div class="riwa-week-label">' + escHtml(week.labels[wi]) + '</div>'
                + '</div>';
        }
        scoreHTML += '</div>';
        scoreHTML += '</div>'; // riwa-pulse-week-card

        scoreHTML += '</div>'; // riwa-pulse-grid

        // ── Alertes ───────────────────────────────────────────────────
        var alertHTML = '';
        if (alerts.length > 0) {
            alertHTML = '<div class="riwa-pulse-alerts">';
            alertHTML += '<div class="riwa-pulse-alerts-title">'
                + '<span class="dashicons dashicons-flag"></span> Actions recommandées</div>';
            alerts.forEach(function (a) {
                alertHTML += '<div class="riwa-alert-item riwa-alert--' + a.type + '">'
                    + '<span class="riwa-alert-icon dashicons ' + a.icon + '"></span>'
                    + '<span class="riwa-alert-msg">' + escHtml(a.message) + '</span>'
                    + (a.link ? '<a href="' + escHtml(a.link) + '" class="riwa-alert-cta">'
                        + escHtml(a.action || 'Voir') + '</a>' : '')
                    + '</div>';
            });
            alertHTML += '</div>';
        } else {
            alertHTML = '<div class="riwa-pulse-alerts riwa-pulse-all-good">'
                + '<span class="dashicons dashicons-yes-alt"></span>'
                + ' Tout est en ordre — aucune action requise.'
                + '</div>';
        }

        $('#riwa-stats-tab-pulse').html(scoreHTML + alertHTML);
    }

    function pulseBar(label, pts, max, color) {
        var pct = max > 0 ? Math.round(pts / max * 100) : 0;
        return '<div class="riwa-pb-row">'
            + '<span class="riwa-pb-label">' + label + '</span>'
            + '<div class="riwa-pb-track"><div class="riwa-pb-fill" style="width:' + pct + '%;background:' + color + '"></div></div>'
            + '<span class="riwa-pb-pts">' + pts + '/' + max + '</span>'
            + '</div>';
    }

    /* ------------------------------------------------------------------ */
    /*  ANALYSE                                                            */
    /* ------------------------------------------------------------------ */

    function renderAnalysis(data) {
        var kpis    = data.kpis;
        var monthly = data.monthly;  // tableau 12 mois
        var profile = data.profile;

        // ── KPIs ──────────────────────────────────────────────────────
        var html = '<div class="riwa-stats-kpi-grid">';
        html += kpiCard('dashicons-money-alt',     'CA confirmé',          fmtPrice(kpis.revenue),      null);
        html += kpiCard('dashicons-calendar-alt',  'Réservations',         kpis.bookings,               null);
        html += kpiCard('dashicons-admin-home',    'Durée moy. séjour',    kpis.avg_nights + ' nuits',  null);
        html += kpiCard('dashicons-chart-line',    'Taux d\'occupation',   kpis.occ_rate + ' %',        null);
        html += kpiCard('dashicons-tag',           'Revenu moy./résa',     fmtPrice(kpis.avg_revenue),  null);
        html += kpiCard('dashicons-dismiss',       'Taux d\'annulation',   kpis.canc_rate + ' %',       kpis.canc_rate > 20 ? 'danger' : null);
        html += kpiCard('dashicons-clock',         'Délai rés. → arrivée', kpis.avg_lead_time + ' j',   null);
        html += '</div>';

        // ── Graphique CA mensuel ───────────────────────────────────────
        html += '<div class="riwa-stats-chart-section">';
        html += '<div class="riwa-stats-chart-title">Chiffre d\'affaires mensuel</div>';
        html += '<div class="riwa-chart-container"><canvas id="chart-ca-monthly"></canvas></div>';
        html += '</div>';

        // ── Graphique Occupation mensuelle ────────────────────────────
        html += '<div class="riwa-stats-chart-section">';
        html += '<div class="riwa-stats-chart-title">Taux d\'occupation mensuel (%)</div>';
        html += '<div class="riwa-chart-container"><canvas id="chart-occ-monthly"></canvas></div>';
        html += '</div>';

        // ── Profil voyageurs ──────────────────────────────────────────
        html += '<div class="riwa-stats-profile-grid">';

        // Doughnut voyageurs
        html += '<div class="riwa-stats-profile-card">';
        html += '<div class="riwa-stats-chart-title">Répartition voyageurs</div>';
        html += '<div class="riwa-chart-container riwa-chart-small"><canvas id="chart-travelers"></canvas></div>';
        html += '</div>';

        // Durées
        html += '<div class="riwa-stats-profile-card">';
        html += '<div class="riwa-stats-chart-title">Durées de séjour</div>';
        var dur = profile.durations;
        var durTotal = (dur['1-3'] || 0) + (dur['4-7'] || 0) + (dur['8+'] || 0);
        html += '<div class="riwa-dur-bars">';
        [['1-3 nuits', dur['1-3']], ['4-7 nuits', dur['4-7']], ['8+ nuits', dur['8+']]].forEach(function (row) {
            var pct = durTotal > 0 ? Math.round(row[1] / durTotal * 100) : 0;
            html += '<div class="riwa-dur-row">'
                + '<span class="riwa-dur-label">' + row[0] + '</span>'
                + '<div class="riwa-dur-track"><div class="riwa-dur-fill" style="width:' + pct + '%"></div></div>'
                + '<span class="riwa-dur-pct">' + pct + '%</span>'
                + '</div>';
        });
        html += '</div>';
        html += '</div>';

        // Jours d'arrivée
        html += '<div class="riwa-stats-profile-card">';
        html += '<div class="riwa-stats-chart-title">Jours d\'arrivée</div>';
        html += '<table class="riwa-arrival-table">';
        (profile.arrival_days || []).forEach(function (r) {
            html += '<tr><td class="riwa-arr-day">' + escHtml(r.day) + '</td>'
                + '<td class="riwa-arr-cnt">' + r.cnt + ' arrivée' + (r.cnt > 1 ? 's' : '') + '</td></tr>';
        });
        html += '</table>';
        html += '</div>';

        html += '</div>'; // riwa-stats-profile-grid

        $('#riwa-stats-tab-analysis').html(html);

        // Construire les charts après insertion dans le DOM
        var caLabels  = monthly.map(function (m) { return m.month; });
        var caValues  = monthly.map(function (m) { return m.revenue; });
        var occValues = monthly.map(function (m) { return m.occ_rate; });

        buildChart('bar', 'chart-ca-monthly', caLabels, [{
            label: 'CA confirmé',
            data : caValues,
            backgroundColor: 'rgba(34, 113, 177, 0.7)',
            borderColor    : '#1b6ca8',
            borderWidth    : 1,
            borderRadius   : 4,
        }], {});

        buildChart('line', 'chart-occ-monthly', caLabels, [{
            label: 'Occupation %',
            data : occValues,
            borderColor   : '#16a34a',
            backgroundColor: 'rgba(22,163,74,0.08)',
            borderWidth   : 2,
            pointRadius   : 4,
            fill          : true,
            tension       : 0.4,
        }], {pct: true});

        var tv = profile.travelers;
        if (tv && (tv.adults + tv.children + tv.babies) > 0) {
            buildChart('doughnut', 'chart-travelers',
                ['Adultes','Enfants','Bébés'],
                [{
                    data: [tv.adults, tv.children, tv.babies],
                    backgroundColor: ['#2563eb','#f59e0b','#10b981'],
                }],
                {raw: true}
            );
        }
    }

    function kpiCard(icon, label, value, modifier) {
        var cls = 'riwa-stats-kpi-card' + (modifier ? ' riwa-kpi--' + modifier : '');
        return '<div class="' + cls + '">'
            + '<span class="riwa-kpi-icon dashicons ' + icon + '"></span>'
            + '<div class="riwa-kpi-body">'
            + '<div class="riwa-kpi-value">' + value + '</div>'
            + '<div class="riwa-kpi-label">' + label + '</div>'
            + '</div>'
            + '</div>';
    }

    /* ------------------------------------------------------------------ */
    /*  PRÉVISION                                                          */
    /* ------------------------------------------------------------------ */

    function renderForecast(data) {
        var fc  = data.forecast;
        var opp = data.opportunities;

        // ── Projection annuelle ───────────────────────────────────────
        var vsColor = fc.vs_prev_pct === null ? '#94a3b8'
                    : fc.vs_prev_pct >= 0 ? '#16a34a' : '#dc2626';
        var vsArrow = fc.vs_prev_pct >= 0 ? '↑' : '↓';

        var html = '<div class="riwa-forecast-summary">';
        html += '<div class="riwa-forecast-card">';
        html += '<div class="riwa-forecast-label">CA réalisé ' + fc.year + '</div>';
        html += '<div class="riwa-forecast-value">' + fmtPrice(fc.realized) + '</div>';
        html += '</div>';
        html += '<div class="riwa-forecast-card riwa-forecast-card--projected">';
        html += '<div class="riwa-forecast-label">Projection fin d\'année</div>';
        html += '<div class="riwa-forecast-value riwa-forecast-big">' + fmtPrice(fc.projected_total) + '</div>';
        if (fc.vs_prev_pct !== null) {
            html += '<div class="riwa-forecast-vs" style="color:' + vsColor + '">'
                + vsArrow + ' ' + Math.abs(fc.vs_prev_pct) + '% vs ' + (fc.year - 1)
                + '</div>';
        }
        html += '</div>';
        html += '<div class="riwa-forecast-card">';
        html += '<div class="riwa-forecast-label">Estimé restant (mois ' + (fc.cur_month + 1) + '-12)</div>';
        html += '<div class="riwa-forecast-value">' + fmtPrice(fc.estimated_rest) + '</div>';
        html += '</div>';
        html += '</div>';

        // Chart empilé réalisé + estimé
        html += '<div class="riwa-stats-chart-section">';
        html += '<div class="riwa-stats-chart-title">Projection CA ' + fc.year + ' (réalisé + estimé)</div>';
        html += '<div class="riwa-chart-container"><canvas id="chart-forecast"></canvas></div>';
        html += '</div>';

        // ── Opportunités ──────────────────────────────────────────────
        html += '<div class="riwa-opp-section">';
        html += '<div class="riwa-stats-chart-title"><span class="dashicons dashicons-lightbulb"></span> Opportunités détectées</div>';

        // Impact +10%
        if (opp.impact_10pct > 0) {
            html += '<div class="riwa-opp-card riwa-opp--green">'
                + '<span class="riwa-opp-icon dashicons dashicons-tag"></span>'
                + '<div class="riwa-opp-body">'
                + '<strong>+10% sur vos prix futurs</strong>'
                + '<span>générerait environ <strong>' + fmtPrice(opp.impact_10pct) + '</strong> de revenus supplémentaires</span>'
                + '</div></div>';
        }

        // Mois forts
        if (opp.top_months && opp.top_months.length > 0) {
            var topStr = opp.top_months.map(function (m) {
                return m.month + ' (' + fmtPrice(m.avg_ppn) + '/nuit en moy.)';
            }).join(', ');
            html += '<div class="riwa-opp-card riwa-opp--blue">'
                + '<span class="riwa-opp-icon dashicons dashicons-chart-bar"></span>'
                + '<div class="riwa-opp-body">'
                + '<strong>Mois les plus rentables</strong>'
                + '<span>' + escHtml(topStr) + ' — envisagez une hausse tarifaire ciblée</span>'
                + '</div></div>';
        }

        // Trous courts
        if (opp.gaps && opp.gaps.length > 0) {
            html += '<div class="riwa-opp-card riwa-opp--orange">'
                + '<span class="riwa-opp-icon dashicons dashicons-calendar-alt"></span>'
                + '<div class="riwa-opp-body">'
                + '<strong>' + opp.gaps.length + ' trou' + (opp.gaps.length > 1 ? 's' : '') + ' de 1–3 nuits</strong>'
                + '<span>Ces fenêtres libres entre réservations sont difficiles à vendre — envisagez de les bloquer ou de proposer une promo courte durée.</span>'
                + '<ul class="riwa-gaps-list">';
            opp.gaps.slice(0, 5).forEach(function (g) {
                html += '<li>' + escHtml(g.from) + ' → ' + escHtml(g.to) + ' (' + g.nights + ' nuit' + (g.nights > 1 ? 's' : '') + ')</li>';
            });
            if (opp.gaps.length > 5) {
                html += '<li style="opacity:.6">+ ' + (opp.gaps.length - 5) + ' autres…</li>';
            }
            html += '</ul></div></div>';
        }

        if (!opp.impact_10pct && (!opp.top_months || !opp.top_months.length) && (!opp.gaps || !opp.gaps.length)) {
            html += '<div class="riwa-opp-empty">'
                + '<span class="dashicons dashicons-yes-alt"></span>'
                + ' Aucune opportunité détectée pour l\'instant — revenez quand vous aurez plus de données.'
                + '</div>';
        }

        html += '</div>'; // riwa-opp-section

        $('#riwa-stats-tab-forecast').html(html);

        // Chart forecast
        var fLabels    = fc.monthly.map(function (m) { return m.month; });
        var fRealized  = fc.monthly.map(function (m) { return m.realized; });
        var fEstimated = fc.monthly.map(function (m) { return m.estimated; });

        buildChart('bar', 'chart-forecast', fLabels, [
            {
                label: 'Réalisé',
                data : fRealized,
                backgroundColor: 'rgba(34,113,177,0.8)',
                borderRadius   : 4,
            },
            {
                label: 'Estimé',
                data : fEstimated,
                backgroundColor: 'rgba(148,163,184,0.5)',
                borderColor    : '#94a3b8',
                borderWidth    : 1,
                borderDash     : [4, 4],
                borderRadius   : 4,
            },
        ], {});
    }

    /* ------------------------------------------------------------------ */
    /*  Démarrage                                                          */
    /* ------------------------------------------------------------------ */

    $(document).ready(function () {
        init();
    });

}(jQuery));

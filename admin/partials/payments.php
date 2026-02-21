<?php
if (!defined('ABSPATH')) exit;

$methods     = Riwa_Payments::get_methods();
$export_url  = admin_url('admin-ajax.php?action=riwa_payments_export_csv&nonce=' . wp_create_nonce('riwa_payments_nonce'));
$mois_fr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
$months  = [];
for ($i = 0; $i < 12; $i++) {
    $ts       = strtotime("-$i months");
    $m        = (int) date('n', $ts);
    $y        = date('Y', $ts);
    $months[] = ['val' => date('Y-m', $ts), 'label' => $mois_fr[$m] . ' ' . $y];
}
?>

<div class="riwa-section" id="payments-section">

    <!-- ── En-tête section ─────────────────────────────────────────── -->
    <div class="riwa-section-header">
        <h2>Paiements & Acomptes</h2>
        <p>Suivi en temps réel de votre trésorerie</p>
    </div>

    <!-- ── Onglets ─────────────────────────────────────────────────── -->
    <div class="riwa-pay-tabs">
        <button class="riwa-pay-tab active" data-tab="dashboard">
            <span class="dashicons dashicons-chart-bar"></span> Vue globale
        </button>
        <button class="riwa-pay-tab" data-tab="bookings">
            <span class="dashicons dashicons-list-view"></span> Réservations
        </button>
        <button class="riwa-pay-tab" data-tab="export">
            <span class="dashicons dashicons-download"></span> Export
        </button>
    </div>

    <!-- ================================================================ -->
    <!-- Onglet 1 : Vue globale                                           -->
    <!-- ================================================================ -->
    <div class="riwa-pay-tab-content active" id="tab-dashboard">

        <!-- KPIs -->
        <div class="riwa-pay-kpis" id="pay-kpis">
            <div class="riwa-pay-kpi" id="kpi-encaisse">
                <div class="riwa-pay-kpi-icon" style="background:#dcfce7;color:#16a34a;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="riwa-pay-kpi-body">
                    <div class="riwa-pay-kpi-label">Encaissé ce mois</div>
                    <div class="riwa-pay-kpi-value" id="kpi-val-encaisse">—</div>
                </div>
            </div>
            <div class="riwa-pay-kpi" id="kpi-attente">
                <div class="riwa-pay-kpi-icon" style="background:#fef9c3;color:#ca8a04;">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="riwa-pay-kpi-body">
                    <div class="riwa-pay-kpi-label">En attente</div>
                    <div class="riwa-pay-kpi-value" id="kpi-val-attente">—</div>
                </div>
            </div>
            <div class="riwa-pay-kpi" id="kpi-retard">
                <div class="riwa-pay-kpi-icon" style="background:#fee2e2;color:#dc2626;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="riwa-pay-kpi-body">
                    <div class="riwa-pay-kpi-label">En retard</div>
                    <div class="riwa-pay-kpi-value" id="kpi-val-retard">—</div>
                    <div class="riwa-pay-kpi-sub" id="kpi-sub-retard"></div>
                </div>
            </div>
            <div class="riwa-pay-kpi" id="kpi-acomptes">
                <div class="riwa-pay-kpi-icon" style="background:#ede9fe;color:#7c3aed;">
                    <span class="dashicons dashicons-tickets-alt"></span>
                </div>
                <div class="riwa-pay-kpi-body">
                    <div class="riwa-pay-kpi-label">Acomptes reçus</div>
                    <div class="riwa-pay-kpi-value" id="kpi-val-acomptes">—</div>
                    <div class="riwa-pay-kpi-sub">réservations en cours</div>
                </div>
            </div>
            <div class="riwa-pay-kpi" id="kpi-prevision">
                <div class="riwa-pay-kpi-icon" style="background:#dbeafe;color:#2563eb;">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="riwa-pay-kpi-body">
                    <div class="riwa-pay-kpi-label">Prévision 30 jours</div>
                    <div class="riwa-pay-kpi-value" id="kpi-val-prevision">—</div>
                    <div class="riwa-pay-kpi-sub">soldes à encaisser</div>
                </div>
            </div>
        </div>

        <!-- Alertes retards -->
        <div class="riwa-pay-section-title">
            <span class="dashicons dashicons-warning" style="color:#ef4444;"></span>
            Paiements en retard
        </div>
        <div id="pay-overdue-list" class="riwa-pay-overdue-wrap">
            <div class="riwa-pay-loading"><span class="dashicons dashicons-update-alt riwa-spin"></span> Chargement…</div>
        </div>

        <!-- Formulaire enregistrement rapide -->
        <div class="riwa-pay-section-title" style="margin-top:2rem;">
            <span class="dashicons dashicons-plus-alt" style="color:#2271b1;"></span>
            Enregistrer un paiement rapide
        </div>
        <div class="riwa-pay-quick-form" id="pay-quick-form">
            <div class="riwa-pay-inline-form">
                <div class="riwa-pay-field riwa-pay-field-lg">
                    <label>Réservation</label>
                    <select id="qf-booking-id" class="riwa-input">
                        <option value="">— Choisir —</option>
                    </select>
                </div>
                <div class="riwa-pay-field riwa-pay-field-sm">
                    <label>Montant <span id="qf-solde-hint" class="riwa-pay-hint"></span></label>
                    <input type="number" id="qf-amount" class="riwa-input" step="0.01" min="0" placeholder="0.00">
                </div>
                <div class="riwa-pay-field riwa-pay-field-md">
                    <label>Mode</label>
                    <select id="qf-method" class="riwa-input">
                        <?php foreach ($methods as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="riwa-pay-field riwa-pay-field-sm">
                    <label>Date</label>
                    <input type="date" id="qf-date" class="riwa-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="riwa-pay-field riwa-pay-field-md">
                    <label>Référence</label>
                    <input type="text" id="qf-reference" class="riwa-input" placeholder="N° virement…">
                </div>
                <div class="riwa-pay-field riwa-pay-field-action">
                    <label>&nbsp;</label>
                    <button type="button" class="riwa-btn riwa-btn-primary riwa-pay-submit-btn" id="qf-submit">
                        <span class="dashicons dashicons-yes"></span> Enregistrer
                    </button>
                </div>
            </div>
            <span class="riwa-pay-form-msg" id="qf-msg" style="display:block;margin-top:6px;"></span>
        </div>

    </div><!-- /#tab-dashboard -->

    <!-- ================================================================ -->
    <!-- Onglet 2 : Réservations & Paiements                              -->
    <!-- ================================================================ -->
    <div class="riwa-pay-tab-content" id="tab-bookings">

        <!-- Filtres -->
        <div class="riwa-pay-filters">
            <button class="riwa-pay-filter-btn active" data-filter="all">Toutes</button>
            <button class="riwa-pay-filter-btn" data-filter="unpaid">
                <span class="riwa-pay-dot" style="background:#94a3b8;"></span> Non payé
            </button>
            <button class="riwa-pay-filter-btn" data-filter="partial">
                <span class="riwa-pay-dot" style="background:#3b82f6;"></span> Partiel
            </button>
            <button class="riwa-pay-filter-btn" data-filter="deposit_paid">
                <span class="riwa-pay-dot" style="background:#f59e0b;"></span> Acompte reçu
            </button>
            <button class="riwa-pay-filter-btn" data-filter="paid">
                <span class="riwa-pay-dot" style="background:#22c55e;"></span> Payé
            </button>
            <button class="riwa-pay-filter-btn" data-filter="overdue">
                <span class="riwa-pay-dot" style="background:#ef4444;"></span> En retard
            </button>
        </div>

        <!-- Table -->
        <div class="riwa-pay-table-wrap">
            <table class="riwa-pay-table" id="pay-bookings-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Arrivée</th>
                        <th>Total séjour</th>
                        <th>Encaissé</th>
                        <th>Solde restant</th>
                        <th>Échéance</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pay-bookings-tbody">
                    <tr><td colspan="8" class="riwa-pay-loading">
                        <span class="dashicons dashicons-update-alt riwa-spin"></span> Chargement…
                    </td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="riwa-pay-pagination" id="pay-pagination"></div>

        <!-- Panel détail paiements (slide-in) -->
        <div class="riwa-pay-detail-overlay" id="pay-detail-overlay"></div>
        <div class="riwa-pay-detail-panel" id="pay-detail-panel">
            <div class="riwa-pay-detail-header">
                <span id="pay-detail-name">Paiements</span>
                <button class="riwa-pay-detail-close" id="pay-detail-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="riwa-pay-detail-body" id="pay-detail-body">
                <!-- Injecté par JS -->
            </div>
        </div>

    </div><!-- /#tab-bookings -->

    <!-- ================================================================ -->
    <!-- Onglet 3 : Export CSV                                            -->
    <!-- ================================================================ -->
    <div class="riwa-pay-tab-content" id="tab-export">
        <div class="riwa-pay-export-wrap">
            <div class="riwa-pay-export-card">
                <div class="riwa-pay-export-icon">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                </div>
                <h3>Export CSV des paiements</h3>
                <p>Téléchargez la liste complète des paiements enregistrés.<br>Compatible Excel et LibreOffice (encodage UTF-8).</p>

                <div class="riwa-pay-export-filters">
                    <label>Filtrer par mois :</label>
                    <select id="export-month" class="riwa-input">
                        <option value="">Toute la période</option>
                        <?php foreach ($months as $m): ?>
                        <option value="<?php echo esc_attr($m['val']); ?>"><?php echo esc_html($m['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <a id="pay-export-btn" href="#" class="riwa-btn riwa-btn-primary riwa-pay-export-btn">
                    <span class="dashicons dashicons-download"></span> Télécharger le CSV
                </a>
            </div>
        </div>
    </div><!-- /#tab-export -->

</div><!-- /.riwa-section -->

<!-- Modal paiement rapide depuis table réservations -->
<div class="riwa-pay-modal-overlay" id="pay-modal-overlay" style="display:none;"></div>
<div class="riwa-pay-modal" id="pay-modal" style="display:none;">
    <div class="riwa-pay-modal-header">
        <span id="pay-modal-title">Enregistrer un paiement</span>
        <button class="riwa-pay-modal-close" id="pay-modal-close">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="riwa-pay-modal-body">
        <input type="hidden" id="pm-booking-id">
        <div class="riwa-pay-form-grid">
            <div class="riwa-pay-field">
                <label>Montant <span id="pm-solde-hint" class="riwa-pay-hint"></span></label>
                <input type="number" id="pm-amount" class="riwa-input" step="0.01" min="0" placeholder="0.00">
            </div>
            <div class="riwa-pay-field">
                <label>Mode de paiement</label>
                <select id="pm-method" class="riwa-input">
                    <?php foreach ($methods as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="riwa-pay-field">
                <label>Date</label>
                <input type="date" id="pm-date" class="riwa-input" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="riwa-pay-field riwa-pay-field-wide">
                <label>Référence / Note</label>
                <input type="text" id="pm-reference" class="riwa-input" placeholder="N° virement, note…">
            </div>
        </div>

        <!-- Acompte -->
        <div class="riwa-pay-deposit-section">
            <div class="riwa-pay-section-title" style="margin:1rem 0 .5rem;">
                <span class="dashicons dashicons-tickets-alt" style="color:#7c3aed;"></span>
                Paramètres acompte
            </div>
            <div class="riwa-pay-form-grid">
                <div class="riwa-pay-field">
                    <label>Acompte requis (%)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="number" id="pm-deposit-percent" class="riwa-input" min="0" max="100" step="1" placeholder="30">
                        <span class="riwa-pay-hint" id="pm-deposit-calc"></span>
                    </div>
                </div>
                <div class="riwa-pay-field">
                    <label>Date limite solde</label>
                    <input type="date" id="pm-balance-due" class="riwa-input">
                </div>
            </div>
        </div>
    </div>
    <div class="riwa-pay-modal-footer">
        <button type="button" class="riwa-btn riwa-btn-primary" id="pm-submit">
            <span class="dashicons dashicons-yes"></span> Enregistrer
        </button>
        <button type="button" class="riwa-btn riwa-btn-secondary" id="pm-cancel">Annuler</button>
        <span class="riwa-pay-form-msg" id="pm-msg"></span>
    </div>
</div>

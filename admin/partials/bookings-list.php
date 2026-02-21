<?php
if (!defined('ABSPATH')) {
    exit;
}

// ── Lecture des filtres GET ─────────────────────────────────────────────────
$filter_status   = sanitize_text_field($_GET['filter_status']   ?? '');
$filter_period   = sanitize_text_field($_GET['filter_period']   ?? '');
$filter_date_from = sanitize_text_field($_GET['filter_date_from'] ?? '');
$filter_date_to   = sanitize_text_field($_GET['filter_date_to']   ?? '');
$filter_duration = sanitize_text_field($_GET['filter_duration'] ?? '');
$filter_guests   = sanitize_text_field($_GET['filter_guests']   ?? '');
$filter_price_min = sanitize_text_field($_GET['filter_price_min'] ?? '');
$filter_price_max = sanitize_text_field($_GET['filter_price_max'] ?? '');
$filter_orderby  = sanitize_text_field($_GET['filter_orderby']  ?? 'created_at');
$filter_search   = sanitize_text_field($_GET['filter_search']   ?? '');
$current_page    = max(1, (int) ($_GET['paged'] ?? 1));
$per_page        = 20;

// ── Requête filtrée ─────────────────────────────────────────────────────────
$result = Riwa_Bookings_Table::get_filtered_bookings([
    'status'     => $filter_status,
    'period'     => $filter_period,
    'date_from'  => $filter_date_from,
    'date_to'    => $filter_date_to,
    'duration'   => $filter_duration,
    'guests'     => $filter_guests,
    'price_min'  => $filter_price_min !== '' ? $filter_price_min : '',
    'price_max'  => $filter_price_max !== '' ? $filter_price_max : '',
    'orderby'    => $filter_orderby,
    'order'      => $filter_orderby === 'total_price' ? 'DESC' : 'DESC',
    'search'     => $filter_search,
    'page'       => $current_page,
    'per_page'   => $per_page,
]);
$bookings    = $result['bookings'];
$total       = $result['total'];
$total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 1;

// ── Smart View ──────────────────────────────────────────────────────────────
$smart = Riwa_Bookings_Table::get_smart_counts();

// ── Helpers URL ─────────────────────────────────────────────────────────────
if (!function_exists('riwa_filter_url')) {
    function riwa_filter_url($params) {
        $base = admin_url('admin.php?page=riwa-bookings&section=bookings');
        foreach ($params as $k => $v) {
            if ($v !== '' && $v !== null && $v !== false) {
                $base .= '&' . urlencode($k) . '=' . urlencode($v);
            }
        }
        return $base;
    }
}

$base_url    = admin_url('admin.php?page=riwa-bookings&section=bookings');
$has_filters = $filter_status || $filter_period || $filter_date_from || $filter_date_to
             || $filter_duration || $filter_guests || $filter_price_min || $filter_price_max
             || $filter_search;

// ── Filtres rapides (quick filters) ─────────────────────────────────────────
$quick_filters = [
    'arriving_today' => ['label' => 'Arrivées aujourd\'hui', 'icon' => 'dashicons-migrate',    'period' => 'arriving_today'],
    'departing_today'=> ['label' => 'Départs aujourd\'hui',  'icon' => 'dashicons-undo',        'period' => 'departing_today'],
    'staying'        => ['label' => 'Séjours en cours',      'icon' => 'dashicons-admin-home',  'period' => 'staying'],
    'pending'        => ['label' => 'En attente',            'icon' => 'dashicons-clock',       'status' => 'pending'],
    'upcoming'       => ['label' => 'À venir',               'icon' => 'dashicons-calendar-alt','period' => 'upcoming'],
];

// ── Labels lisibles pour les filtres actifs ──────────────────────────────────
$status_labels  = ['pending' => 'En attente', 'confirmed' => 'Confirmée', 'cancelled' => 'Annulée'];
$period_labels  = [
    'today' => 'Créées aujourd\'hui', 'this_week' => 'Cette semaine', 'this_month' => 'Ce mois',
    'arriving_today' => 'Arrivées aujourd\'hui', 'arriving_week' => 'Arrivées cette semaine',
    'departing_today' => 'Départs aujourd\'hui', 'staying' => 'Séjours en cours',
    'upcoming' => 'À venir', 'past' => 'Passées',
];
$duration_labels = ['1' => '1 nuit', '2-3' => '2–3 nuits', '4+' => '4 nuits +'];
$guests_labels   = ['1-2' => '1–2 personnes', '3-5' => '3–5 personnes', '6+' => '6+ personnes'];
$orderby_labels  = ['created_at' => 'Date réservation', 'check_in_date' => 'Date arrivée', 'total_price' => 'Montant ↓'];

// Comptage des filtres actifs (periode exclue : gérée par smart chips / view tabs)
$active_filter_count = (int)!!$filter_status + (int)($filter_date_from || $filter_date_to)
                     + (int)!!$filter_duration + (int)!!$filter_guests
                     + (int)($filter_price_min !== '' || $filter_price_max !== '') + (int)!!$filter_search;
?>
<div class="riwa-section" id="bookings-section">
    <div class="riwa-section-header">
        <h2>Réservations</h2>
        <p>Gérez et filtrez toutes vos réservations</p>
    </div>
    <div class="riwa-section-content">

        <!-- ── Smart View ──────────────────────────────────────────────── -->
        <div class="riwa-smart-view">
            <?php if ($smart['arriving_today'] > 0): ?>
            <a href="<?php echo esc_url(riwa_filter_url(['filter_period' => 'arriving_today'])); ?>" class="riwa-smart-chip riwa-smart-chip--green">
                <span class="dashicons dashicons-migrate"></span>
                <strong><?php echo $smart['arriving_today']; ?></strong>
                <span>arrivée<?php echo $smart['arriving_today'] > 1 ? 's' : ''; ?> aujourd'hui</span>
            </a>
            <?php endif; ?>
            <?php if ($smart['departing_today'] > 0): ?>
            <a href="<?php echo esc_url(riwa_filter_url(['filter_period' => 'departing_today'])); ?>" class="riwa-smart-chip riwa-smart-chip--orange">
                <span class="dashicons dashicons-undo"></span>
                <strong><?php echo $smart['departing_today']; ?></strong>
                <span>départ<?php echo $smart['departing_today'] > 1 ? 's' : ''; ?> aujourd'hui</span>
            </a>
            <?php endif; ?>
            <?php if ($smart['staying'] > 0): ?>
            <a href="<?php echo esc_url(riwa_filter_url(['filter_period' => 'staying'])); ?>" class="riwa-smart-chip riwa-smart-chip--blue">
                <span class="dashicons dashicons-admin-home"></span>
                <strong><?php echo $smart['staying']; ?></strong>
                <span>séjour<?php echo $smart['staying'] > 1 ? 's' : ''; ?> en cours</span>
            </a>
            <?php endif; ?>
            <?php if ($smart['pending'] > 0): ?>
            <a href="<?php echo esc_url(riwa_filter_url(['filter_status' => 'pending'])); ?>" class="riwa-smart-chip riwa-smart-chip--yellow">
                <span class="dashicons dashicons-clock"></span>
                <strong><?php echo $smart['pending']; ?></strong>
                <span>en attente</span>
            </a>
            <?php endif; ?>
            <?php if ($smart['arriving_week'] > 0): ?>
            <a href="<?php echo esc_url(riwa_filter_url(['filter_period' => 'arriving_week'])); ?>" class="riwa-smart-chip riwa-smart-chip--purple">
                <span class="dashicons dashicons-calendar"></span>
                <strong><?php echo $smart['arriving_week']; ?></strong>
                <span>arrivée<?php echo $smart['arriving_week'] > 1 ? 's' : ''; ?> cette semaine</span>
            </a>
            <?php endif; ?>
            <?php if ($smart['arriving_today'] + $smart['departing_today'] + $smart['staying'] + $smart['pending'] + $smart['arriving_week'] === 0): ?>
            <span class="riwa-smart-chip riwa-smart-chip--gray">
                <span class="dashicons dashicons-yes-alt"></span>
                <span>Aucune action requise aujourd'hui</span>
            </span>
            <?php endif; ?>
        </div>

        <!-- ── Toolbar style Attio ───────────────────────────────────────── -->
        <div class="riwa-toolbar">
            <div class="riwa-toolbar-left">
                <span class="riwa-toolbar-count">
                    <strong><?php echo $total; ?></strong> réservation<?php echo $total > 1 ? 's' : ''; ?>
                </span>

                <!-- Vues rapides (filtrage JS instantané) -->
                <div class="riwa-view-tabs" id="riwa-view-tabs">
                    <button type="button" class="riwa-view-tab active" data-view="all">Toutes</button>
                    <button type="button" class="riwa-view-tab" data-view="upcoming">À venir</button>
                    <button type="button" class="riwa-view-tab" data-view="staying">En cours</button>
                    <button type="button" class="riwa-view-tab" data-view="past">Passées</button>
                </div>
            </div>

            <div class="riwa-toolbar-right">
                <!-- Bouton Reset (visible si filtres actifs) -->
                <?php if ($active_filter_count): ?>
                    <a href="<?php echo esc_url($base_url); ?>" class="riwa-toolbar-reset" title="Réinitialiser les filtres">
                        <span class="dashicons dashicons-dismiss"></span> Réinitialiser
                    </a>
                <?php endif; ?>

                <!-- Bouton Filtres + dropdown ancré -->
                <div class="riwa-filter-dropdown-wrap" id="riwa-filter-dropdown-wrap">
                    <button type="button" class="riwa-toolbar-btn <?php echo $active_filter_count ? 'riwa-toolbar-btn--active' : ''; ?>" id="riwa-toggle-filters">
                        <span class="dashicons dashicons-filter"></span>
                        Filtres
                        <?php if ($active_filter_count): ?>
                            <span class="riwa-filter-count-badge"><?php echo $active_filter_count; ?></span>
                        <?php endif; ?>
                    </button>

                    <!-- ── Dropdown filtres ──────────────────────────────────── -->
                    <div id="riwa-filters-popup" class="riwa-filter-dropdown" style="display:none;">
                        <form method="get" id="riwa-filters-form" action="">
                            <input type="hidden" name="page"    value="riwa-bookings">
                            <input type="hidden" name="section" value="bookings">
                            <?php if ($filter_orderby !== 'created_at'): ?><input type="hidden" name="filter_orderby" value="<?php echo esc_attr($filter_orderby); ?>"><?php endif; ?>

                            <div class="riwa-filter-dropdown-body">

                                <div class="riwa-filter-group">
                                    <span class="riwa-filter-group-label">Statut</span>
                                    <div class="riwa-filter-chips">
                                        <label class="riwa-chip-label <?php echo !$filter_status ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_status" value="" <?php checked(!$filter_status); ?>> Tous
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_status === 'pending' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_status" value="pending" <?php checked($filter_status, 'pending'); ?>> En attente
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_status === 'confirmed' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_status" value="confirmed" <?php checked($filter_status, 'confirmed'); ?>> Confirmée
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_status === 'cancelled' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_status" value="cancelled" <?php checked($filter_status, 'cancelled'); ?>> Annulée
                                        </label>
                                    </div>
                                </div>

                                <div class="riwa-filter-group">
                                    <span class="riwa-filter-group-label">Dates d'arrivée</span>
                                    <div class="riwa-filter-date-row">
                                        <input type="date" name="filter_date_from" class="riwa-filter-date" value="<?php echo esc_attr($filter_date_from); ?>">
                                        <span class="riwa-filter-date-sep">→</span>
                                        <input type="date" name="filter_date_to" class="riwa-filter-date" value="<?php echo esc_attr($filter_date_to); ?>">
                                    </div>
                                </div>

                                <div class="riwa-filter-group">
                                    <span class="riwa-filter-group-label">Durée</span>
                                    <div class="riwa-filter-chips">
                                        <label class="riwa-chip-label <?php echo !$filter_duration ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_duration" value="" <?php checked(!$filter_duration); ?>> Toutes
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_duration === '1' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_duration" value="1" <?php checked($filter_duration, '1'); ?>> 1 nuit
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_duration === '2-3' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_duration" value="2-3" <?php checked($filter_duration, '2-3'); ?>> 2–3 nuits
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_duration === '4+' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_duration" value="4+" <?php checked($filter_duration, '4+'); ?>> 4+ nuits
                                        </label>
                                    </div>
                                </div>

                                <div class="riwa-filter-group">
                                    <span class="riwa-filter-group-label">Voyageurs</span>
                                    <div class="riwa-filter-chips">
                                        <label class="riwa-chip-label <?php echo !$filter_guests ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_guests" value="" <?php checked(!$filter_guests); ?>> Tous
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_guests === '1-2' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_guests" value="1-2" <?php checked($filter_guests, '1-2'); ?>> 1–2
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_guests === '3-5' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_guests" value="3-5" <?php checked($filter_guests, '3-5'); ?>> 3–5
                                        </label>
                                        <label class="riwa-chip-label <?php echo $filter_guests === '6+' ? 'is-active' : ''; ?>">
                                            <input type="radio" name="filter_guests" value="6+" <?php checked($filter_guests, '6+'); ?>> 6+
                                        </label>
                                    </div>
                                </div>

                                <div class="riwa-filter-group">
                                    <span class="riwa-filter-group-label">Montant (€)</span>
                                    <div class="riwa-filter-price-row">
                                        <div class="riwa-filter-price-field">
                                            <span class="riwa-filter-price-prefix">Min</span>
                                            <input type="number" name="filter_price_min" class="riwa-filter-price-input" placeholder="0"
                                                   value="<?php echo esc_attr($filter_price_min); ?>" min="0" step="50">
                                        </div>
                                        <span class="riwa-filter-date-sep">–</span>
                                        <div class="riwa-filter-price-field">
                                            <span class="riwa-filter-price-prefix">Max</span>
                                            <input type="number" name="filter_price_max" class="riwa-filter-price-input" placeholder="∞"
                                                   value="<?php echo esc_attr($filter_price_max); ?>" min="0" step="50">
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.riwa-filter-dropdown-body -->
                        </form>
                    </div><!-- /#riwa-filters-popup -->
                </div><!-- /.riwa-filter-dropdown-wrap -->

                <!-- Tri rapide -->
                <form method="get" class="riwa-sort-form" id="riwa-sort-form">
                    <input type="hidden" name="page"    value="riwa-bookings">
                    <input type="hidden" name="section" value="bookings">
                    <?php if ($filter_status):   ?><input type="hidden" name="filter_status"   value="<?php echo esc_attr($filter_status); ?>"><?php endif; ?>
                    <?php if ($filter_period):   ?><input type="hidden" name="filter_period"   value="<?php echo esc_attr($filter_period); ?>"><?php endif; ?>
                    <?php if ($filter_duration): ?><input type="hidden" name="filter_duration" value="<?php echo esc_attr($filter_duration); ?>"><?php endif; ?>
                    <?php if ($filter_guests):   ?><input type="hidden" name="filter_guests"   value="<?php echo esc_attr($filter_guests); ?>"><?php endif; ?>
                    <?php if ($filter_search):   ?><input type="hidden" name="filter_search"   value="<?php echo esc_attr($filter_search); ?>"><?php endif; ?>
                    <select name="filter_orderby" class="riwa-sort-select" onchange="this.form.submit()">
                        <option value="created_at"    <?php selected($filter_orderby, 'created_at'); ?>>Trier : Date réservation</option>
                        <option value="check_in_date" <?php selected($filter_orderby, 'check_in_date'); ?>>Trier : Date arrivée</option>
                        <option value="total_price"   <?php selected($filter_orderby, 'total_price'); ?>>Trier : Montant ↓</option>
                    </select>
                </form>

                <!-- Recherche inline (filtrage JS instantané) -->
                <div class="riwa-search-wrap">
                    <span class="dashicons dashicons-search riwa-search-icon"></span>
                    <input type="text" class="riwa-search-input" placeholder="Rechercher..." autocomplete="off">
                    <button type="button" class="riwa-search-clear" id="riwa-search-clear" style="display:none;">&#x2715;</button>
                </div>
            </div>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="riwa-empty-state">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3>Aucune réservation</h3>
                <p>Aucune réservation ne correspond à vos critères.</p>
                <?php if ($has_filters): ?>
                    <a href="<?php echo esc_url($base_url); ?>" class="riwa-btn riwa-btn-secondary">Voir toutes les réservations</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="riwa-table-wrapper">
                <table class="riwa-modern-table"
                    id="riwa-bookings-table"
                    data-total="<?php echo esc_attr($total); ?>"
                    data-total-pages="<?php echo esc_attr($total_pages); ?>"
                    data-current-page="<?php echo esc_attr($current_page); ?>"
                    data-filter-status="<?php echo esc_attr($filter_status); ?>"
                    data-filter-period="<?php echo esc_attr($filter_period); ?>"
                    data-filter-date-from="<?php echo esc_attr($filter_date_from); ?>"
                    data-filter-date-to="<?php echo esc_attr($filter_date_to); ?>"
                    data-filter-duration="<?php echo esc_attr($filter_duration); ?>"
                    data-filter-guests="<?php echo esc_attr($filter_guests); ?>"
                    data-filter-price-min="<?php echo esc_attr($filter_price_min); ?>"
                    data-filter-price-max="<?php echo esc_attr($filter_price_max); ?>"
                    data-filter-orderby="<?php echo esc_attr($filter_orderby); ?>"
                    data-filter-search="<?php echo esc_attr($filter_search); ?>">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Dates &amp; Durée</th>
                            <th>Voyageurs</th>
                            <th>Prix</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking):
                            $nights = (int) (new DateTime($booking->check_in_date))->diff(new DateTime($booking->check_out_date))->days;
                            $total_g = Riwa_Bookings_Table::get_total_guests($booking);
                        ?>
                            <tr class="riwa-booking-row"
                                data-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                                data-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                                data-nights="<?php echo esc_attr($nights); ?>"
                                data-guests="<?php echo esc_attr($total_g); ?>"
                                data-status="<?php echo esc_attr($booking->status); ?>"
                                data-price="<?php echo esc_attr($booking->total_price ?? 0); ?>">
                                <td><span class="riwa-booking-reference">RIWA-<?php echo str_pad($booking->id, 6, '0', STR_PAD_LEFT); ?></span></td>
                                <td>
                                    <div class="riwa-client-name"><?php echo esc_html($booking->guest_name); ?></div>
                                    <?php $badge = Riwa_Bookings_Table::get_status_badge($booking->status); ?>
                                    <span class="riwa-status-badge riwa-status-<?php echo esc_attr($booking->status); ?>"><?php echo esc_html($badge['label']); ?></span>
                                </td>
                                <td>
                                    <div><?php echo esc_html(date('d/m/Y', strtotime($booking->check_in_date))); ?> → <?php echo esc_html(date('d/m/Y', strtotime($booking->check_out_date))); ?></div>
                                    <div class="riwa-nights-badge"><?php echo $nights; ?> nuit<?php echo $nights > 1 ? 's' : ''; ?></div>
                                </td>
                                <td>
                                    <?php if ($total_g > 0): ?>
                                        <span class="riwa-guests-compact">
                                            <span class="dashicons dashicons-groups"></span> <?php echo $total_g; ?>
                                        </span>
                                        <div class="riwa-guests-detail">
                                            <?php if ($booking->adults_count):   echo esc_html($booking->adults_count) . ' adulte' . ($booking->adults_count > 1 ? 's' : ''); endif; ?>
                                            <?php if ($booking->children_count): echo ' · ' . esc_html($booking->children_count) . ' enfant' . ($booking->children_count > 1 ? 's' : ''); endif; ?>
                                            <?php if ($booking->babies_count):   echo ' · ' . esc_html($booking->babies_count) . ' bébé' . ($booking->babies_count > 1 ? 's' : ''); endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color:var(--riwa-gray-400);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking->total_price > 0): ?>
                                        <span class="riwa-price-total"><?php echo number_format($booking->total_price, 0, ',', ' '); ?> €</span>
                                        <?php if ($booking->price_per_night > 0): ?>
                                            <div style="font-size:11px;color:var(--riwa-gray-500);"><?php echo number_format($booking->price_per_night, 0, ',', ' '); ?> €/nuit</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="riwa-price-unknown"><span class="dashicons dashicons-minus"></span></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="riwa-btn riwa-btn-secondary button-small view-details-popup"
                                            data-booking-id="<?php echo esc_attr($booking->id); ?>"
                                            data-booking-name="<?php echo esc_attr($booking->guest_name); ?>"
                                            data-booking-email="<?php echo esc_attr($booking->guest_email); ?>"
                                            data-booking-phone="<?php echo esc_attr($booking->guest_phone); ?>"
                                            data-booking-checkin="<?php echo esc_attr($booking->check_in_date); ?>"
                                            data-booking-checkout="<?php echo esc_attr($booking->check_out_date); ?>"
                                            data-booking-guests="<?php echo esc_attr(Riwa_Bookings_Table::get_total_guests($booking)); ?>"
                                            data-booking-adults="<?php echo esc_attr($booking->adults_count ?? 0); ?>"
                                            data-booking-children="<?php echo esc_attr($booking->children_count ?? 0); ?>"
                                            data-booking-babies="<?php echo esc_attr($booking->babies_count ?? 0); ?>"
                                            data-booking-price="<?php echo esc_attr($booking->total_price); ?>"
                                            data-booking-price-per-night="<?php echo esc_attr($booking->price_per_night); ?>"
                                            data-booking-status="<?php echo esc_attr($booking->status); ?>"
                                            data-booking-created="<?php echo esc_attr($booking->created_at); ?>"
                                            data-booking-requests="<?php echo esc_attr($booking->special_requests); ?>"
                                            title="Voir les détails">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div id="riwa-infinite-sentinel" class="riwa-infinite-sentinel">
                    <span class="riwa-infinite-spinner">
                        <span class="dashicons dashicons-update-alt riwa-spin"></span>
                        Chargement…
                    </span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

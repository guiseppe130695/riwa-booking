<?php
/**
 * Tests unitaires autonomes — sans WordPress, sans PHPUnit
 * Teste les fonctions pures extraites du plugin Riwa Booking
 *
 * Usage : php tests/test-functions.php
 */

$pass = 0;
$fail = 0;
$errors = [];

function assert_equals($label, $expected, $actual) {
    global $pass, $fail, $errors;
    // Comparaison souple sur les valeurs numériques (int vs float identiques)
    $match = ($expected === $actual) || (is_numeric($expected) && is_numeric($actual) && (float)$expected === (float)$actual);
    if ($match) {
        echo "  ✅ $label\n";
        $pass++;
    } else {
        echo "  ❌ $label\n";
        echo "     Attendu : " . var_export($expected, true) . "\n";
        echo "     Obtenu  : " . var_export($actual, true) . "\n";
        $fail++;
        $errors[] = $label;
    }
}

function assert_true($label, $value) {
    assert_equals($label, true, (bool) $value);
}

function assert_false($label, $value) {
    assert_equals($label, false, (bool) $value);
}

// ─────────────────────────────────────────────────────────────
// Copie des fonctions pures extraites du plugin (sans $wpdb)
// ─────────────────────────────────────────────────────────────

/**
 * riwa_trend() — dashboard.php
 */
function riwa_trend($current, $previous) {
    if ($previous <= 0) {
        return $current > 0 ? ['pct' => 100, 'up' => true] : ['pct' => 0, 'up' => true];
    }
    $pct = round(($current - $previous) / $previous * 100);
    return ['pct' => abs($pct), 'up' => $pct >= 0];
}

/**
 * get_status_badge() — class-riwa-bookings-table.php
 */
function get_status_badge($status) {
    $statuses = [
        'pending'   => ['label' => 'En attente', 'class' => 'status-pending'],
        'confirmed' => ['label' => 'Confirmée',  'class' => 'status-confirmed'],
        'cancelled' => ['label' => 'Annulée',    'class' => 'status-cancelled'],
    ];
    return $statuses[$status] ?? ['label' => ucfirst($status), 'class' => 'status-unknown'];
}

/**
 * get_total_guests() — class-riwa-bookings-table.php
 */
function get_total_guests($booking) {
    return ($booking->adults_count ?? 0)
         + ($booking->children_count ?? 0)
         + ($booking->babies_count ?? 0);
}

/**
 * get_season_color() — class-riwa-pricing.php
 */
function get_season_color($season_id) {
    $colors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe',
        '#43e97b', '#38f9d7', '#fa709a', '#fee140', '#a8edea', '#fed6e3',
    ];
    return $colors[$season_id % count($colors)];
}

/**
 * calculate_nights() — logique extraite de calculate_total()
 */
function calculate_nights($check_in_date, $check_out_date) {
    $check_in  = new DateTime($check_in_date);
    $check_out = new DateTime($check_out_date);
    return (int) $check_in->diff($check_out)->days;
}

/**
 * check_date_overlap_pure() — logique de chevauchement sans BDD
 * Reproduit exactement la condition SQL de check_date_overlap()
 */
function check_date_overlap_pure($new_start, $new_end, array $existing_periods) {
    $overlapping = [];
    foreach ($existing_periods as $p) {
        $s = $p['start'];
        $e = $p['end'];
        $overlaps = ($s <= $new_start && $e >= $new_start)  // new_start dans la période
                 || ($s <= $new_end   && $e >= $new_end)    // new_end dans la période
                 || ($s >= $new_start && $e <= $new_end);   // période contenue dans new
        if ($overlaps) {
            $overlapping[] = $p;
        }
    }
    return $overlapping;
}

/**
 * calculate_occupancy_rate() — logique extraite du dashboard
 */
function calculate_occupancy_rate($nights_booked, $days_in_month) {
    if ($days_in_month <= 0) return 0;
    return min(100, round($nights_booked / $days_in_month * 100));
}

/**
 * riwa_filter_url() — bookings-list.php
 */
function riwa_filter_url($params) {
    $base = 'admin.php?page=riwa-bookings&section=bookings';
    foreach ($params as $k => $v) {
        if ($v !== '' && $v !== null && $v !== 0) {
            $base .= '&' . urlencode($k) . '=' . urlencode($v);
        }
    }
    return $base;
}

// ─────────────────────────────────────────────────────────────
// SUITE 1 : riwa_trend()
// ─────────────────────────────────────────────────────────────
echo "\n📊 Suite 1 : riwa_trend()\n";

$t = riwa_trend(10, 8);
assert_equals('hausse 10 vs 8 → 25%', 25, $t['pct']);
assert_true('hausse → up=true', $t['up']);

$t = riwa_trend(6, 10);
assert_equals('baisse 6 vs 10 → 40%', 40, $t['pct']);
assert_false('baisse → up=false', $t['up']);

$t = riwa_trend(5, 5);
assert_equals('égalité → 0%', 0, $t['pct']);
assert_true('égalité → up=true', $t['up']);

$t = riwa_trend(0, 0);
assert_equals('0 vs 0 → 0%', 0, $t['pct']);
assert_true('0 vs 0 → up=true (pas de dégradation)', $t['up']);

$t = riwa_trend(3, 0);
assert_equals('3 vs 0 → 100% (nouveau)', 100, $t['pct']);
assert_true('3 vs 0 → up=true', $t['up']);

$t = riwa_trend(0, 5);
assert_equals('0 vs 5 → 100%', 100, $t['pct']);
assert_false('0 vs 5 → up=false (perte totale)', $t['up']);

// ─────────────────────────────────────────────────────────────
// SUITE 2 : get_status_badge()
// ─────────────────────────────────────────────────────────────
echo "\n🏷️  Suite 2 : get_status_badge()\n";

$b = get_status_badge('pending');
assert_equals('pending → label', 'En attente', $b['label']);
assert_equals('pending → class', 'status-pending', $b['class']);

$b = get_status_badge('confirmed');
assert_equals('confirmed → label', 'Confirmée', $b['label']);
assert_equals('confirmed → class', 'status-confirmed', $b['class']);

$b = get_status_badge('cancelled');
assert_equals('cancelled → label', 'Annulée', $b['label']);
assert_equals('cancelled → class', 'status-cancelled', $b['class']);

$b = get_status_badge('unknown_status');
assert_equals('statut inconnu → label capitalisé', 'Unknown_status', $b['label']);
assert_equals('statut inconnu → class', 'status-unknown', $b['class']);

// ─────────────────────────────────────────────────────────────
// SUITE 3 : get_total_guests()
// ─────────────────────────────────────────────────────────────
echo "\n👥 Suite 3 : get_total_guests()\n";

$booking = (object)['adults_count' => 2, 'children_count' => 1, 'babies_count' => 0];
assert_equals('2 adultes + 1 enfant + 0 bébé = 3', 3, get_total_guests($booking));

$booking = (object)['adults_count' => 4, 'children_count' => 0, 'babies_count' => 1];
assert_equals('4 adultes + 0 enfant + 1 bébé = 5', 5, get_total_guests($booking));

$booking = (object)[];
assert_equals('aucun champ → 0', 0, get_total_guests($booking));

$booking = (object)['adults_count' => 0, 'children_count' => 0, 'babies_count' => 0];
assert_equals('tous à 0 → 0', 0, get_total_guests($booking));

// ─────────────────────────────────────────────────────────────
// SUITE 4 : calculate_nights()
// ─────────────────────────────────────────────────────────────
echo "\n🌙 Suite 4 : calculate_nights()\n";

assert_equals('1 nuit', 1, calculate_nights('2025-09-02', '2025-09-03'));
assert_equals('8 nuits (RIWA-000017)', 8, calculate_nights('2025-09-02', '2025-09-10'));
assert_equals('30 nuits', 30, calculate_nights('2025-06-01', '2025-07-01'));
assert_equals('0 nuit (même jour)', 0, calculate_nights('2025-09-02', '2025-09-02'));
assert_equals('traversée mois', 5, calculate_nights('2025-01-29', '2025-02-03'));
assert_equals('traversée année', 31, calculate_nights('2025-12-01', '2026-01-01'));

// ─────────────────────────────────────────────────────────────
// SUITE 5 : calculate_occupancy_rate()
// ─────────────────────────────────────────────────────────────
echo "\n📅 Suite 5 : calculate_occupancy_rate()\n";

assert_equals('15 nuits / 30 jours = 50%', 50, calculate_occupancy_rate(15, 30));
assert_equals('28 nuits / 28 jours = 100%', 100, calculate_occupancy_rate(28, 28));
assert_equals('35 nuits / 30 jours → plafonné à 100%', 100, calculate_occupancy_rate(35, 30));
assert_equals('0 nuit / 31 jours = 0%', 0, calculate_occupancy_rate(0, 31));
assert_equals('mois 0 jours → 0', 0, calculate_occupancy_rate(10, 0));
assert_equals('8 nuits / 28 jours = 29%', 29, calculate_occupancy_rate(8, 28));

// ─────────────────────────────────────────────────────────────
// SUITE 6 : check_date_overlap_pure()
// ─────────────────────────────────────────────────────────────
echo "\n🗓️  Suite 6 : check_date_overlap_pure()\n";

$existing = [
    ['name' => 'Été', 'start' => '2025-07-01', 'end' => '2025-08-31'],
];

$result = check_date_overlap_pure('2025-07-15', '2025-07-20', $existing);
assert_true('nouvelle période contenue dans existante → chevauchement', !empty($result));

$result = check_date_overlap_pure('2025-06-01', '2025-07-10', $existing);
assert_true('nouvelle période déborde sur début existante → chevauchement', !empty($result));

$result = check_date_overlap_pure('2025-08-20', '2025-09-10', $existing);
assert_true('nouvelle période déborde sur fin existante → chevauchement', !empty($result));

$result = check_date_overlap_pure('2025-06-01', '2025-09-30', $existing);
assert_true('nouvelle période englobe existante → chevauchement', !empty($result));

$result = check_date_overlap_pure('2025-09-01', '2025-09-30', $existing);
assert_false('après existante → pas de chevauchement', !empty($result));

$result = check_date_overlap_pure('2025-05-01', '2025-06-30', $existing);
assert_false('avant existante → pas de chevauchement', !empty($result));

// Cas limite : même jour de fin / début
$result = check_date_overlap_pure('2025-09-01', '2025-10-01', $existing);
assert_false('adjacent après (start=end+1) → pas de chevauchement', !empty($result));

// ─────────────────────────────────────────────────────────────
// SUITE 7 : get_season_color()
// ─────────────────────────────────────────────────────────────
echo "\n🎨 Suite 7 : get_season_color()\n";

assert_equals('id=0 → première couleur', '#667eea', get_season_color(0));
assert_equals('id=11 → dernière couleur', '#fed6e3', get_season_color(11));
assert_equals('id=12 → cycle (=id=0)', '#667eea', get_season_color(12));
assert_equals('id=13 → cycle (=id=1)', '#764ba2', get_season_color(13));

// ─────────────────────────────────────────────────────────────
// SUITE 8 : riwa_filter_url()
// ─────────────────────────────────────────────────────────────
echo "\n🔗 Suite 8 : riwa_filter_url()\n";

$url = riwa_filter_url(['filter_status' => 'confirmed', 'filter_month' => 0, 'filter_search' => '']);
assert_true('statut confirmed → dans URL', strpos($url, 'filter_status=confirmed') !== false);
assert_false('filter_month=0 → exclu de URL', strpos($url, 'filter_month') !== false);
assert_false('filter_search vide → exclu de URL', strpos($url, 'filter_search') !== false);

$url = riwa_filter_url(['filter_status' => '', 'filter_month' => 3, 'filter_search' => 'Dupont']);
assert_false('statut vide → exclu', strpos($url, 'filter_status') !== false);
assert_true('filter_month=3 → dans URL', strpos($url, 'filter_month=3') !== false);
assert_true('filter_search=Dupont → dans URL', strpos($url, 'filter_search=Dupont') !== false);

$url = riwa_filter_url([]);
assert_equals('params vides → URL de base', 'admin.php?page=riwa-bookings&section=bookings', $url);

// ─────────────────────────────────────────────────────────────
// RÉSUMÉ
// ─────────────────────────────────────────────────────────────
echo "\n" . str_repeat('─', 50) . "\n";
echo "✅ Réussis : $pass\n";
echo "❌ Échoués : $fail\n";

if (!empty($errors)) {
    echo "\nTests en échec :\n";
    foreach ($errors as $e) {
        echo "  • $e\n";
    }
}

echo str_repeat('─', 50) . "\n";
exit($fail > 0 ? 1 : 0);

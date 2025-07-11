<?php
/**
 * Fichier de test pour vérifier l'enregistrement des réservations
 * À supprimer après les tests
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

// Test d'enregistrement d'une réservation
function test_booking_insertion() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'riwa_bookings';
    
    // Données de test
    $test_data = array(
        'guest_name' => 'Test User',
        'guest_email' => 'test@example.com',
        'guest_phone' => '0123456789',
        'check_in_date' => '2025-07-15',
        'check_out_date' => '2025-07-20',
        'adults_count' => 2,
        'children_count' => 1,
        'babies_count' => 0,
        'pets_count' => 1,
        'special_requests' => 'Test de réservation',
        'total_price' => 800.00,
        'price_per_night' => 160.00,
        'status' => 'pending'
    );
    
    // Tentative d'insertion
    $result = $wpdb->insert(
        $table_name,
        $test_data,
        array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%f', '%f', '%s')
    );
    
    if ($result === false) {
        echo "Erreur lors de l'insertion: " . $wpdb->last_error;
    } else {
        echo "Réservation de test insérée avec succès. ID: " . $wpdb->insert_id;
    }
}

// Test de récupération des données
function test_booking_retrieval() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'riwa_bookings';
    
    $bookings = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 5");
    
    echo "<h3>Dernières réservations:</h3>";
    foreach ($bookings as $booking) {
        echo "<p>ID: {$booking->id} - Nom: {$booking->guest_name} - Email: {$booking->guest_email} - Dates: {$booking->check_in_date} à {$booking->check_out_date}</p>";
    }
}

// Exécuter les tests si on est en mode debug
if (WP_DEBUG) {
    echo "<h2>Tests Riwa Booking</h2>";
    test_booking_insertion();
    echo "<br><br>";
    test_booking_retrieval();
}
?> 
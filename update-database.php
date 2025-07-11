<?php
/**
 * Script de mise à jour de la base de données Riwa Booking
 * À exécuter une seule fois pour corriger la structure des tables
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}

function riwa_booking_update_database() {
    global $wpdb;
    
    echo "<h2>Mise à jour de la base de données Riwa Booking</h2>";
    
    // 1. Mettre à jour la table de tarification
    $pricing_table = $wpdb->prefix . 'riwa_pricing';
    
    // Vérifier si la table existe
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$pricing_table'");
    
    if (!$table_exists) {
        echo "<p style='color: red;'>❌ La table de tarification n'existe pas. Veuillez désactiver et réactiver le plugin.</p>";
        return;
    }
    
    // Vérifier et ajouter la colonne min_stay
    $min_stay_exists = $wpdb->get_results("SHOW COLUMNS FROM $pricing_table LIKE 'min_stay'");
    
    if (empty($min_stay_exists)) {
        $result = $wpdb->query("ALTER TABLE $pricing_table ADD COLUMN min_stay int(3) DEFAULT 1 AFTER price_per_night");
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Colonne 'min_stay' ajoutée à la table de tarification</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de l'ajout de la colonne 'min_stay'</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ La colonne 'min_stay' existe déjà</p>";
    }
    
    // 2. Mettre à jour la table de réservations
    $bookings_table = $wpdb->prefix . 'riwa_bookings';
    
    // Vérifier si la table existe
    $bookings_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'");
    
    if (!$bookings_exists) {
        echo "<p style='color: red;'>❌ La table de réservations n'existe pas. Veuillez désactiver et réactiver le plugin.</p>";
        return;
    }
    
    // Vérifier et ajouter la colonne total_price
    $total_price_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'total_price'");
    
    if (empty($total_price_exists)) {
        $result = $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN total_price decimal(10,2) DEFAULT 0.00 AFTER special_requests");
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Colonne 'total_price' ajoutée à la table de réservations</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de l'ajout de la colonne 'total_price'</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ La colonne 'total_price' existe déjà</p>";
    }
    
    // Vérifier et ajouter la colonne price_per_night
    $price_per_night_exists = $wpdb->get_results("SHOW COLUMNS FROM $bookings_table LIKE 'price_per_night'");
    
    if (empty($price_per_night_exists)) {
        $result = $wpdb->query("ALTER TABLE $bookings_table ADD COLUMN price_per_night decimal(10,2) DEFAULT 0.00 AFTER total_price");
        if ($result !== false) {
            echo "<p style='color: green;'>✅ Colonne 'price_per_night' ajoutée à la table de réservations</p>";
        } else {
            echo "<p style='color: red;'>❌ Erreur lors de l'ajout de la colonne 'price_per_night'</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ La colonne 'price_per_night' existe déjà</p>";
    }
    
    // 3. Mettre à jour les enregistrements existants
    $updated_count = $wpdb->query("UPDATE $pricing_table SET min_stay = 1 WHERE min_stay IS NULL");
    if ($updated_count > 0) {
        echo "<p style='color: green;'>✅ $updated_count enregistrements de tarification mis à jour avec min_stay = 1</p>";
    }
    
    echo "<p style='color: green; font-weight: bold;'>🎉 Mise à jour terminée ! Vous pouvez maintenant utiliser la tarification saisonnière.</p>";
    echo "<p><a href='" . admin_url('admin.php?page=riwa-pricing') . "'>→ Aller à la page de tarification</a></p>";
}

// Exécuter la mise à jour si demandé
if (isset($_GET['riwa_update_db']) && current_user_can('manage_options')) {
    riwa_booking_update_database();
    exit;
}
?>

<div class="wrap">
    <h1>Mise à jour de la base de données Riwa Booking</h1>
    
    <div class="notice notice-warning">
        <p><strong>Attention :</strong> Cette mise à jour corrige la structure de la base de données pour la tarification saisonnière.</p>
    </div>
    
    <p>Si vous voyez des erreurs "Undefined property" dans la page de tarification, cliquez sur le bouton ci-dessous pour corriger automatiquement la structure de la base de données.</p>
    
    <a href="<?php echo admin_url('admin.php?page=riwa-pricing&riwa_update_db=1'); ?>" class="button button-primary">
        🔧 Corriger la structure de la base de données
    </a>
    
    <hr style="margin: 30px 0;">
    
    <h3>Structure attendue des tables :</h3>
    
    <h4>Table de tarification (<?php echo $wpdb->prefix; ?>riwa_pricing) :</h4>
    <ul>
        <li>id (clé primaire)</li>
        <li>season_name (nom de la saison)</li>
        <li>start_date (date de début)</li>
        <li>end_date (date de fin)</li>
        <li>price_per_night (prix par nuit)</li>
        <li><strong>min_stay</strong> (séjour minimum) ← Cette colonne manquait</li>
        <li>is_active (actif/inactif)</li>
        <li>created_at (date de création)</li>
    </ul>
    
    <h4>Table de réservations (<?php echo $wpdb->prefix; ?>riwa_bookings) :</h4>
    <ul>
        <li>id (clé primaire)</li>
        <li>guest_name (nom du client)</li>
        <li>guest_email (email)</li>
        <li>guest_phone (téléphone)</li>
        <li>check_in_date (date d'arrivée)</li>
        <li>check_out_date (date de départ)</li>
        <li>guests_count (nombre d'invités)</li>
        <li>special_requests (demandes spéciales)</li>
        <li><strong>total_price</strong> (prix total) ← Cette colonne manquait</li>
        <li><strong>price_per_night</strong> (prix par nuit) ← Cette colonne manquait</li>
        <li>status (statut)</li>
        <li>created_at (date de création)</li>
    </ul>
</div> 
<?php
/**
 * Script temporaire pour supprimer la colonne pets_count de la base de données
 * À exécuter une seule fois puis supprimer
 */

// Vérifier que WordPress est chargé
if (!defined('ABSPATH')) {
    require_once('../../../wp-load.php');
}

// Vérifier les permissions d'administration
if (!current_user_can('manage_options')) {
    wp_die('Accès refusé');
}

global $wpdb;

try {
    // Vérifier si la colonne existe
    $table_name = $wpdb->prefix . 'riwa_bookings';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'pets_count'");
    
    if (!empty($column_exists)) {
        // Supprimer la colonne pets_count
        $result = $wpdb->query("ALTER TABLE $table_name DROP COLUMN pets_count");
        
        if ($result !== false) {
            echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin: 20px; border: 1px solid #c3e6cb; border-radius: 4px;">';
            echo '<h3>✅ Succès !</h3>';
            echo '<p>La colonne <strong>pets_count</strong> a été supprimée avec succès de la table <strong>' . $table_name . '</strong></p>';
            echo '</div>';
        } else {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">';
            echo '<h3>❌ Erreur !</h3>';
            echo '<p>Erreur lors de la suppression de la colonne pets_count : ' . $wpdb->last_error . '</p>';
            echo '</div>';
        }
    } else {
        echo '<div style="background: #d1ecf1; color: #0c5460; padding: 15px; margin: 20px; border: 1px solid #bee5eb; border-radius: 4px;">';
        echo '<h3>ℹ️ Information</h3>';
        echo '<p>La colonne <strong>pets_count</strong> n\'existe pas dans la table <strong>' . $table_name . '</strong></p>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">';
    echo '<h3>❌ Exception !</h3>';
    echo '<p>Erreur : ' . $e->getMessage() . '</p>';
    echo '</div>';
}

echo '<div style="background: #fff3cd; color: #856404; padding: 15px; margin: 20px; border: 1px solid #ffeaa7; border-radius: 4px;">';
echo '<h3>⚠️ Important</h3>';
echo '<p>Ce script a été exécuté. Vous pouvez maintenant le supprimer du serveur pour des raisons de sécurité.</p>';
echo '</div>';
?>

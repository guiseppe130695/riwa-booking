<?php
/**
 * Page de démonstration du design minimaliste
 * À utiliser pour tester le nouveau design
 */

// Empêcher l'accès direct
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Démonstration - Design Minimaliste Riwa Booking</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #fafafa;
            color: #171717;
        }
        
        .demo-header {
            background: #000;
            color: #fff;
            padding: 2rem;
            text-align: center;
        }
        
        .demo-header h1 {
            font-size: 32px;
            font-weight: 300;
            margin: 0;
            letter-spacing: -1px;
        }
        
        .demo-header p {
            font-size: 16px;
            margin: 1rem 0 0 0;
            opacity: 0.8;
        }
        
        .demo-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }
        
        .demo-section {
            margin-bottom: 3rem;
        }
        
        .demo-section h2 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 1.5rem;
            color: #000;
        }
        
        .demo-info {
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .demo-info h3 {
            font-size: 18px;
            font-weight: 400;
            margin: 0 0 1rem 0;
            color: #000;
        }
        
        .demo-info ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .demo-info li {
            margin: 0.5rem 0;
            color: #525252;
        }
        
        .demo-button {
            display: inline-block;
            padding: 1rem 2rem;
            background: #000;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .demo-button:hover {
            background: #262626;
            transform: translateY(-1px);
        }
    </style>
    <?php wp_head(); ?>
</head>
<body>
    <div class="demo-header">
        <h1>Design Minimaliste</h1>
        <p>Riwa Booking - Interface épurée en noir et blanc</p>
    </div>
    
    <div class="demo-container">
        <div class="demo-section">
            <h2>Caractéristiques du design</h2>
            
            <div class="demo-info">
                <h3>Palette de couleurs</h3>
                <ul>
                    <li><strong>Noir pur</strong> (#000000) pour les éléments principaux</li>
                    <li><strong>Blanc</strong> (#ffffff) pour les arrière-plans</li>
                    <li><strong>Gris subtils</strong> pour les éléments secondaires</li>
                    <li><strong>Aucune couleur</strong> pour un look épuré et professionnel</li>
                </ul>
            </div>
            
            <div class="demo-info">
                <h3>Typographie</h3>
                <ul>
                    <li><strong>Police système</strong> pour une lisibilité optimale</li>
                    <li><strong>Poids de police variés</strong> (300, 400, 500, 600)</li>
                    <li><strong>Espacement des lettres</strong> pour l'élégance</li>
                    <li><strong>Hiérarchie claire</strong> avec différentes tailles</li>
                </ul>
            </div>
            
            <div class="demo-info">
                <h3>Interface utilisateur</h3>
                <ul>
                    <li><strong>Bordures subtiles</strong> pour délimiter les sections</li>
                    <li><strong>Ombres minimales</strong> pour la profondeur</li>
                    <li><strong>Animations douces</strong> pour les interactions</li>
                    <li><strong>Espacement généreux</strong> pour la respiration</li>
                </ul>
            </div>
        </div>
        
        <div class="demo-section">
            <h2>Test du formulaire</h2>
            <p>Utilisez le formulaire ci-dessous pour tester le nouveau design minimaliste :</p>
            
            <?php
            // Afficher le shortcode de réservation
            echo do_shortcode('[riwa_booking title="Réserver votre villa"]');
            ?>
        </div>
        
        <div class="demo-section">
            <h2>Actions</h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=riwa-bookings'); ?>" class="demo-button">
                    Voir l'administration
                </a>
            </p>
        </div>
    </div>
    
    <?php wp_footer(); ?>
</body>
</html> 
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Réservation - Riwa</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000000;
            background: #ffffff;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #000000;
            padding-bottom: 20px;
        }
        
        .logo {
            font-size: 32px;
            font-weight: 200;
            letter-spacing: -2px;
            margin-bottom: 10px;
        }
        
        .subtitle {
            font-size: 14px;
            font-weight: 300;
            color: #737373;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .confirmation-title {
            font-size: 24px;
            font-weight: 200;
            text-align: center;
            margin: 30px 0;
            letter-spacing: -1px;
        }
        
        .booking-number {
            text-align: center;
            font-size: 14px;
            font-weight: 300;
            color: #737373;
            margin-bottom: 30px;
        }
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e5e5;
            padding-bottom: 5px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #737373;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            font-weight: 400;
            color: #000000;
        }
        
        .dates-section {
            background: #fafafa;
            padding: 20px;
            margin: 20px 0;
        }
        
        .dates-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .date-item {
            text-align: center;
        }
        
        .date-label {
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #737373;
            margin-bottom: 8px;
        }
        
        .date-value {
            font-size: 18px;
            font-weight: 300;
            color: #000000;
        }
        
        .guests-section {
            background: #fafafa;
            padding: 20px;
            margin: 20px 0;
        }
        
        .guests-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        
        .guest-item {
            text-align: center;
        }
        
        .guest-count {
            font-size: 24px;
            font-weight: 300;
            color: #000000;
            margin-bottom: 5px;
        }
        
        .guest-label {
            font-size: 11px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #737373;
        }
        
        .price-section {
            background: #000000;
            color: #ffffff;
            padding: 25px;
            margin: 30px 0;
        }
        
        .price-title {
            font-size: 14px;
            font-weight: 400;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #333333;
        }
        
        .price-row:last-child {
            border-bottom: none;
            border-top: 2px solid #ffffff;
            margin-top: 10px;
            padding-top: 15px;
            font-weight: 500;
            font-size: 16px;
        }
        
        .price-label {
            font-size: 13px;
            font-weight: 300;
        }
        
        .price-value {
            font-size: 13px;
            font-weight: 400;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #737373;
            border-top: 1px solid #e5e5e5;
            padding-top: 20px;
        }
        
        .footer p {
            margin-bottom: 8px;
        }
        
        .qr-code {
            text-align: center;
            margin: 30px 0;
        }
        
        .qr-code img {
            width: 100px;
            height: 100px;
        }
        
        @media print {
            body {
                padding: 20px;
            }
            
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">RIWA</div>
        <div class="subtitle">Villa de luxe</div>
    </div>
    
    <div class="confirmation-title">Confirmation de Réservation</div>
    <div class="booking-number">Réservation #<?php echo $booking_id; ?></div>
    
    <div class="section">
        <div class="section-title">Informations du voyageur</div>
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">Nom complet</div>
                <div class="info-value"><?php echo htmlspecialchars($guest_name); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo htmlspecialchars($guest_email); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Téléphone</div>
                <div class="info-value"><?php echo htmlspecialchars($guest_phone); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Date de réservation</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($created_at)); ?></div>
            </div>
        </div>
    </div>
    
    <div class="dates-section">
        <div class="section-title">Période de séjour</div>
        <div class="dates-grid">
            <div class="date-item">
                <div class="date-label">Arrivée</div>
                <div class="date-value"><?php echo date('d/m/Y', strtotime($check_in_date)); ?></div>
            </div>
            <div class="date-item">
                <div class="date-label">Départ</div>
                <div class="date-value"><?php echo date('d/m/Y', strtotime($check_out_date)); ?></div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 13px; color: #737373;">
            <?php 
            $nights = (strtotime($check_out_date) - strtotime($check_in_date)) / (60 * 60 * 24);
            echo $nights . ' nuit' . ($nights > 1 ? 's' : '');
            ?>
        </div>
    </div>
    
    <div class="guests-section">
        <div class="section-title">Voyageurs</div>
        <div class="guests-grid">
            <div class="guest-item">
                <div class="guest-count"><?php echo $adults_count; ?></div>
                <div class="guest-label">Adultes</div>
            </div>
            <div class="guest-item">
                <div class="guest-count"><?php echo $children_count; ?></div>
                <div class="guest-label">Enfants</div>
            </div>
            <div class="guest-item">
                <div class="guest-count"><?php echo $babies_count; ?></div>
                <div class="guest-label">Bébés</div>
            </div>
            <div class="guest-item">
                <div class="guest-count"><?php echo $pets_count; ?></div>
                <div class="guest-label">Animaux</div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($special_requests)): ?>
    <div class="section">
        <div class="section-title">Demandes spéciales</div>
        <div style="background: #fafafa; padding: 15px; font-size: 13px; line-height: 1.5;">
            <?php echo nl2br(htmlspecialchars($special_requests)); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="price-section">
        <div class="price-title">Détails du tarif</div>
        <div class="price-row">
            <div class="price-label">Prix par nuit</div>
            <div class="price-value"><?php echo number_format($price_per_night, 2, ',', ' '); ?> €</div>
        </div>
        <div class="price-row">
            <div class="price-label">Nombre de nuits</div>
            <div class="price-value"><?php echo $nights; ?></div>
        </div>
        <div class="price-row">
            <div class="price-label">Total</div>
            <div class="price-value"><?php echo number_format($total_price, 2, ',', ' '); ?> €</div>
        </div>
    </div>
    
    <div class="qr-code">
        <div style="font-size: 11px; color: #737373; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 1px;">
            Code QR de réservation
        </div>
        <div style="width: 100px; height: 100px; background: #f0f0f0; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999;">
            QR Code<br><?php echo $booking_id; ?>
        </div>
    </div>
    
    <div class="footer">
        <p><strong>Riwa Villa</strong> - Villa de luxe</p>
        <p>Pour toute question, contactez-nous à contact@riwa.com</p>
        <p>Ce document fait foi de réservation</p>
        <p style="margin-top: 15px; font-size: 10px;">
            Document généré le <?php echo date('d/m/Y à H:i'); ?>
        </p>
    </div>
</body>
</html> 
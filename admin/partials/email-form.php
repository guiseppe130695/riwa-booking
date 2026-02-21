<?php
if (!defined('ABSPATH')) {
    exit;
}
// Variables disponibles : $email_options (tableau retourné par Riwa_Email_Settings::get_options())
$o = $email_options;
?>
<div class="riwa-preview-container">
    <form method="post" action="">
        <?php wp_nonce_field('riwa_email_settings', 'riwa_email_nonce'); ?>

        <div class="riwa-setting-group">
            <h3>Paramètres généraux</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label class="riwa-toggle-label">
                        <input type="checkbox" name="notification_enabled" value="1" <?php checked($o['notification_enabled'], 1); ?>>
                        <span class="riwa-toggle-slider"></span>
                        Activer les notifications par email
                    </label>
                </div>
            </div>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="admin_email">Email administrateur</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?php echo esc_attr($o['admin_address']); ?>" class="riwa-input">
                    <p class="riwa-help-text">Email qui recevra les notifications de nouvelles réservations</p>
                </div>
                <div class="riwa-form-group">
                    <label for="from_name">Nom de l'expéditeur</label>
                    <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr($o['from_name']); ?>" class="riwa-input">
                </div>
                <div class="riwa-form-group">
                    <label for="from_address">Email de l'expéditeur</label>
                    <input type="email" id="from_address" name="from_address" value="<?php echo esc_attr($o['from_address']); ?>" class="riwa-input">
                </div>
            </div>
        </div>

        <div class="riwa-setting-group">
            <h3>Email de confirmation client</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="client_subject">Objet de l'email</label>
                    <input type="text" id="client_subject" name="client_subject" value="<?php echo esc_attr($o['client_subject']); ?>" class="riwa-input">
                </div>
            </div>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="client_message">Message</label>
                    <textarea id="client_message" name="client_message" rows="8" class="riwa-textarea"><?php echo esc_textarea($o['client_message']); ?></textarea>
                    <p class="riwa-help-text">Variables disponibles : {guest_name}, {check_in}, {check_out}</p>
                </div>
            </div>
        </div>

        <div class="riwa-setting-group">
            <h3>Email de notification administrateur</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="admin_subject">Objet de l'email</label>
                    <input type="text" id="admin_subject" name="admin_subject" value="<?php echo esc_attr($o['admin_subject']); ?>" class="riwa-input">
                </div>
            </div>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="admin_message">Message</label>
                    <textarea id="admin_message" name="admin_message" rows="12" class="riwa-textarea"><?php echo esc_textarea($o['admin_message']); ?></textarea>
                    <p class="riwa-help-text">Variables : {guest_name}, {guest_email}, {guest_phone}, {check_in}, {check_out}, {adults_count}, {children_count}, {babies_count}, {special_requests}, {admin_url}</p>
                </div>
            </div>
        </div>

        <div class="riwa-setting-group">
            <h3>Test d'envoi</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group" style="max-width:320px;">
                    <label for="test_email">Email de test</label>
                    <input type="email" id="test_email" name="test_email" class="riwa-input" placeholder="votre-email@exemple.com">
                </div>
            </div>
            <div class="riwa-form-row" style="margin-top:0.5rem;">
                <div class="riwa-form-group" style="flex-direction:row;align-items:center;gap:0.75rem;flex:none;">
                    <button type="button" id="test_client_email" class="riwa-btn riwa-btn-secondary">
                        <span class="dashicons dashicons-email-alt"></span>
                        Tester email client
                    </button>
                    <button type="button" id="test_admin_email" class="riwa-btn riwa-btn-secondary">
                        <span class="dashicons dashicons-email-alt"></span>
                        Tester email admin
                    </button>
                    <span id="test_result" style="font-size:13px;font-weight:600;"></span>
                </div>
            </div>
        </div>

        <div class="riwa-setting-actions">
            <button type="submit" name="save_email_settings" class="riwa-btn riwa-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                Sauvegarder la configuration
            </button>
        </div>
    </form>
</div>

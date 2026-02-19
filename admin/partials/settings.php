<?php
if (!defined('ABSPATH')) {
    exit;
}
// Variables disponibles : $pricing_seasons, $email_options
// Onglet actif via hash URL géré en JS ; fallback sur 'general'

// Sauvegarder les paramètres généraux
if (isset($_POST['save_general_settings'])) {
    if (!wp_verify_nonce($_POST['riwa_general_nonce'] ?? '', 'riwa_general_settings')) {
        echo '<div class="notice notice-error"><p>Action non autorisée.</p></div>';
    } elseif (!current_user_can('manage_options')) {
        echo '<div class="notice notice-error"><p>Permissions insuffisantes.</p></div>';
    } else {
        update_option('riwa_setting_language',      sanitize_text_field($_POST['riwa_language'] ?? 'fr'));
        update_option('riwa_setting_currency',      sanitize_text_field($_POST['riwa_currency'] ?? 'EUR'));
        update_option('riwa_setting_timezone',      sanitize_text_field($_POST['riwa_timezone'] ?? 'Europe/Paris'));
        update_option('riwa_setting_logo_url',      esc_url_raw($_POST['riwa_logo_url'] ?? ''));
        update_option('riwa_setting_primary_color', sanitize_hex_color($_POST['riwa_primary_color'] ?? '#2271b1'));
        echo '<div class="notice notice-success"><p>Paramètres généraux enregistrés.</p></div>';
    }
}

$gen_language  = get_option('riwa_setting_language', 'fr');
$gen_currency  = get_option('riwa_setting_currency', 'EUR');
$gen_timezone  = get_option('riwa_setting_timezone', 'Europe/Paris');
$gen_logo      = get_option('riwa_setting_logo_url', '');
$gen_color     = get_option('riwa_setting_primary_color', '#2271b1');
?>
<div class="riwa-section" id="settings-section">
    <div class="riwa-section-header">
        <h2>Paramètres</h2>
        <p>Configuration générale du plugin</p>
    </div>
    <div class="riwa-section-content">

        <!-- Onglets -->
        <div class="riwa-settings-tabs" id="riwa-settings-tabs">
            <button type="button" class="riwa-settings-tab active" data-tab="general">Général</button>
            <button type="button" class="riwa-settings-tab" data-tab="pricing">Tarification</button>
            <button type="button" class="riwa-settings-tab" data-tab="email">Email</button>
            <button type="button" class="riwa-settings-tab" data-tab="debug">Diagnostic</button>
        </div>

        <!-- Onglet Général -->
        <div class="riwa-settings-tab-content active" id="settings-tab-general">
            <div class="riwa-preview-container">
                <form method="post" action="">
                    <?php wp_nonce_field('riwa_general_settings', 'riwa_general_nonce'); ?>

                    <div class="riwa-setting-group">
                        <h3>Langue &amp; Région</h3>
                        <div class="riwa-form-row">
                            <div class="riwa-form-group">
                                <label for="riwa_language">Langue</label>
                                <select id="riwa_language" name="riwa_language" class="riwa-input">
                                    <option value="fr" <?php selected($gen_language, 'fr'); ?>>Français</option>
                                    <option value="en" <?php selected($gen_language, 'en'); ?>>English</option>
                                </select>
                            </div>
                            <div class="riwa-form-group">
                                <label for="riwa_currency">Devise</label>
                                <select id="riwa_currency" name="riwa_currency" class="riwa-input">
                                    <option value="EUR" <?php selected($gen_currency, 'EUR'); ?>>€ Euro (EUR)</option>
                                    <option value="USD" <?php selected($gen_currency, 'USD'); ?>>$ Dollar (USD)</option>
                                    <option value="CHF" <?php selected($gen_currency, 'CHF'); ?>>CHF Franc suisse</option>
                                    <option value="MAD" <?php selected($gen_currency, 'MAD'); ?>>MAD Dirham marocain</option>
                                </select>
                            </div>
                            <div class="riwa-form-group">
                                <label for="riwa_timezone">Fuseau horaire</label>
                                <select id="riwa_timezone" name="riwa_timezone" class="riwa-input">
                                    <option value="Europe/Paris"    <?php selected($gen_timezone, 'Europe/Paris'); ?>>Europe/Paris</option>
                                    <option value="Europe/London"   <?php selected($gen_timezone, 'Europe/London'); ?>>Europe/London</option>
                                    <option value="Europe/Zurich"   <?php selected($gen_timezone, 'Europe/Zurich'); ?>>Europe/Zurich</option>
                                    <option value="Africa/Casablanca" <?php selected($gen_timezone, 'Africa/Casablanca'); ?>>Africa/Casablanca</option>
                                    <option value="America/New_York" <?php selected($gen_timezone, 'America/New_York'); ?>>America/New_York</option>
                                    <option value="UTC"             <?php selected($gen_timezone, 'UTC'); ?>>UTC</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="riwa-setting-group">
                        <h3>Apparence</h3>
                        <div class="riwa-form-row">
                            <div class="riwa-form-group">
                                <label for="riwa_logo_url">URL du logo</label>
                                <div style="display:flex;gap:0.5rem;align-items:center;">
                                    <input type="url" id="riwa_logo_url" name="riwa_logo_url"
                                           value="<?php echo esc_attr($gen_logo); ?>"
                                           class="riwa-input" placeholder="https://...">
                                    <button type="button" id="riwa-logo-picker" class="riwa-btn riwa-btn-secondary">
                                        <span class="dashicons dashicons-admin-media"></span> Choisir
                                    </button>
                                </div>
                                <?php if ($gen_logo): ?>
                                    <div style="margin-top:0.5rem;">
                                        <img src="<?php echo esc_url($gen_logo); ?>" alt="Logo" style="max-height:60px;border:1px solid var(--riwa-border);padding:4px;border-radius:4px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="riwa-form-group">
                                <label for="riwa_primary_color">Couleur principale</label>
                                <input type="text" id="riwa_primary_color" name="riwa_primary_color"
                                       value="<?php echo esc_attr($gen_color); ?>"
                                       class="riwa-color-picker">
                            </div>
                        </div>
                    </div>

                    <div class="riwa-setting-actions">
                        <button type="submit" name="save_general_settings" class="riwa-btn riwa-btn-primary">
                            <span class="dashicons dashicons-saved"></span>
                            Enregistrer les paramètres
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Onglet Tarification -->
        <div class="riwa-settings-tab-content" id="settings-tab-pricing">
            <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/pricing.php'; ?>
        </div>

        <!-- Onglet Email -->
        <div class="riwa-settings-tab-content" id="settings-tab-email">
            <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/email-form.php'; ?>
        </div>

        <!-- Onglet Diagnostic -->
        <div class="riwa-settings-tab-content" id="settings-tab-debug">
            <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/debug.php'; ?>
        </div>

    </div>
</div>

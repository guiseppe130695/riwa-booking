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
            <button type="button" class="riwa-settings-tab active" data-tab="general">
                <span class="dashicons dashicons-admin-settings"></span> Général
            </button>
            <button type="button" class="riwa-settings-tab" data-tab="pricing">
                <span class="dashicons dashicons-money-alt"></span> Tarification
            </button>
            <button type="button" class="riwa-settings-tab" data-tab="email">
                <span class="dashicons dashicons-email-alt"></span> Email
            </button>
            <button type="button" class="riwa-settings-tab" data-tab="notifications">
                <span class="dashicons dashicons-bell"></span> Notifications
            </button>
            <button type="button" class="riwa-settings-tab" data-tab="debug">
                <span class="dashicons dashicons-search"></span> Diagnostic
            </button>
            <button type="button" class="riwa-settings-tab" data-tab="demo">
                <span class="dashicons dashicons-database"></span> Données démo
            </button>
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

        <!-- Onglet Notifications -->
        <div class="riwa-settings-tab-content" id="settings-tab-notifications">
            <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/notif-settings-form.php'; ?>
        </div>

        <!-- Onglet Diagnostic -->
        <div class="riwa-settings-tab-content" id="settings-tab-debug">
            <?php include RIWA_BOOKING_PLUGIN_PATH . 'admin/partials/debug.php'; ?>
        </div>

        <!-- Onglet Données démo -->
        <div class="riwa-settings-tab-content" id="settings-tab-demo">
            <div class="riwa-preview-container">
                <div class="riwa-setting-group">
                    <h3>Données de démonstration</h3>
                    <p style="color:var(--riwa-text-light);margin-bottom:1.5rem;">
                        Injectez des réservations, blocages et prix spéciaux fictifs pour tester le Planning.
                        Toutes les données démo sont préfixées <code>[DEMO]</code> et peuvent être supprimées en un clic.
                    </p>

                    <div style="display:flex;flex-direction:column;gap:1rem;max-width:480px;">
                        <div class="riwa-demo-action-card">
                            <div class="riwa-demo-action-info">
                                <span class="dashicons dashicons-database-add" style="color:#d97706;font-size:24px;"></span>
                                <div>
                                    <strong>Injecter les données démo</strong>
                                    <p>24 réservations réalistes + 5 blocages + prix spéciaux week-end sur 90 jours</p>
                                </div>
                            </div>
                            <button type="button" id="settings-seed-btn" class="riwa-btn riwa-btn-primary">
                                <span class="dashicons dashicons-database-add"></span> Injecter
                            </button>
                        </div>

                        <div class="riwa-demo-action-card" id="settings-clear-card" style="display:none;">
                            <div class="riwa-demo-action-info">
                                <span class="dashicons dashicons-database-remove" style="color:#dc2626;font-size:24px;"></span>
                                <div>
                                    <strong>Effacer les données démo</strong>
                                    <p>Supprime toutes les entrées <code>[DEMO]</code> de la base de données</p>
                                </div>
                            </div>
                            <button type="button" id="settings-clear-btn" class="riwa-btn riwa-btn-danger">
                                <span class="dashicons dashicons-trash"></span> Effacer
                            </button>
                        </div>
                    </div>

                    <div id="settings-demo-msg" style="margin-top:1rem;display:none;"></div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function($){
    var AJAX_URL = (typeof riwa_admin_ajax !== 'undefined') ? riwa_admin_ajax.ajax_url : '/wp-admin/admin-ajax.php';
    var NONCE    = (typeof riwa_planning_config !== 'undefined') ? riwa_planning_config.nonce : $('#riwa-planning-nonce').val() || '';

    function showDemoMsg(msg, type) {
        $('#settings-demo-msg')
            .attr('class', 'notice notice-' + type)
            .html('<p>' + msg + '</p>')
            .show();
    }

    function checkDemoExists() {
        $.post(AJAX_URL, { action: 'riwa_planning_demo_status', nonce: NONCE }, function(resp) {
            if (resp.success && resp.data.has_demo) {
                $('#settings-clear-card').show();
            } else {
                $('#settings-clear-card').hide();
            }
        });
    }

    $('#settings-seed-btn').on('click', function() {
        if (!confirm('Injecter 24 réservations + blocages + prix spéciaux en base ?')) return;
        var $btn = $(this).prop('disabled', true).text('Injection…');
        $.post(AJAX_URL, { action: 'riwa_planning_seed_demo', nonce: NONCE }, function(resp) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Injecter');
            if (resp.success) {
                showDemoMsg('✓ ' + resp.data.count + ' réservations injectées avec succès. Allez sur le Planning pour les voir.', 'success');
                $('#settings-clear-card').show();
            } else {
                showDemoMsg('Erreur : ' + (resp.data || 'inconnue'), 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-database-add"></span> Injecter');
            showDemoMsg('Erreur réseau', 'error');
        });
    });

    $('#settings-clear-btn').on('click', function() {
        if (!confirm('Supprimer toutes les données [DEMO] ? Action irréversible.')) return;
        var $btn = $(this).prop('disabled', true).text('Suppression…');
        $.post(AJAX_URL, { action: 'riwa_planning_clear_demo', nonce: NONCE }, function(resp) {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Effacer');
            if (resp.success) {
                showDemoMsg('✓ Données démo supprimées.', 'success');
                $('#settings-clear-card').hide();
            } else {
                showDemoMsg('Erreur : ' + (resp.data || 'inconnue'), 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Effacer');
            showDemoMsg('Erreur réseau', 'error');
        });
    });

    // Vérifier si des données démo existent quand l'onglet est ouvert
    $(document).on('click', '.riwa-settings-tab[data-tab="demo"]', function() {
        checkDemoExists();
    });
}(jQuery));
</script>

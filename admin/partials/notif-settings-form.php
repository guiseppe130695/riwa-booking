<?php
if (!defined('ABSPATH')) {
    exit;
}

Riwa_Notif_Settings::handle_save();
$opts = Riwa_Notif_Settings::get_options();
?>

<div class="riwa-preview-container">
    <form method="post" action="">
        <?php wp_nonce_field('riwa_notif_settings', 'riwa_notif_settings_nonce'); ?>

        <!-- ── Activation ──────────────────────────────────────────── -->
        <div class="riwa-setting-group">
            <h3>Activation</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label class="riwa-toggle-label">
                        <input type="checkbox" name="notif_whatsapp_enabled" value="1"
                               <?php checked($opts['whatsapp_enabled']); ?>>
                        <span class="riwa-toggle-slider"></span>
                        Activer les boutons WhatsApp dans les réservations
                    </label>
                    <p class="riwa-field-desc">Affiche des boutons d'envoi rapide dans la popup de chaque réservation.</p>
                </div>
            </div>
        </div>

        <!-- ── Coordonnées ─────────────────────────────────────────── -->
        <div class="riwa-setting-group">
            <h3>Coordonnées WhatsApp</h3>
            <div class="riwa-form-row">
                <div class="riwa-form-group">
                    <label for="notif_country_code">Indicatif pays par défaut</label>
                    <input type="text" id="notif_country_code" name="notif_country_code"
                           value="<?php echo esc_attr($opts['country_code']); ?>"
                           class="riwa-input" placeholder="+33" style="max-width:120px;">
                    <p class="riwa-field-desc">Utilisé quand le numéro client commence par 0 (ex : 06 12…)</p>
                </div>
                <div class="riwa-form-group">
                    <label for="notif_admin_phone">Votre numéro WhatsApp (admin)</label>
                    <input type="text" id="notif_admin_phone" name="notif_admin_phone"
                           value="<?php echo esc_attr($opts['admin_phone']); ?>"
                           class="riwa-input" placeholder="+212661234567" style="max-width:220px;">
                    <p class="riwa-field-desc">Pour les boutons d'envoi vers l'hôte.</p>
                </div>
            </div>
        </div>

        <!-- ── Variables disponibles ───────────────────────────────── -->
        <div class="riwa-setting-group">
            <h3>Variables disponibles dans les templates</h3>
            <div class="riwa-notif-vars-grid">
                <?php
                $vars = [
                    '{nom_client}', '{email_client}', '{telephone_client}',
                    '{date_arrivee}', '{date_depart}', '{nuits}',
                    '{adultes}', '{enfants}', '{bebes}',
                    '{prix_total}', '{prix_nuit}', '{statut}', '{reference}',
                ];
                foreach ($vars as $v): ?>
                    <code class="riwa-notif-var-chip"><?php echo esc_html($v); ?></code>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Templates ───────────────────────────────────────────── -->
        <?php
        $templates = [
            'confirmation' => ['label' => 'Confirmation de réservation',  'key' => 'tpl_confirmation'],
            'reminder'     => ['label' => 'Rappel paiement / arrivée',    'key' => 'tpl_reminder'],
            'checkin'      => ['label' => 'Informations check-in',        'key' => 'tpl_checkin'],
            'review'       => ['label' => 'Demande d\'avis après séjour', 'key' => 'tpl_review'],
        ];
        foreach ($templates as $type => $meta):
        ?>
        <div class="riwa-setting-group">
            <h3><?php echo esc_html($meta['label']); ?></h3>
            <div class="riwa-form-group">
                <label for="notif_<?php echo esc_attr($type); ?>">Message WhatsApp</label>
                <textarea id="notif_<?php echo esc_attr($type); ?>"
                          name="notif_<?php echo esc_attr($meta['key']); ?>"
                          class="riwa-input riwa-notif-tpl-textarea"
                          rows="6"><?php echo esc_textarea($opts[$meta['key']]); ?></textarea>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- ── Sauvegarde ──────────────────────────────────────────── -->
        <div class="riwa-setting-actions">
            <button type="submit" name="save_notif_settings" class="riwa-btn riwa-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                Enregistrer les templates
            </button>
        </div>

    </form>
</div>

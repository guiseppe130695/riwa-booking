<?php
if (!defined('ABSPATH')) exit;

$settings   = Riwa_PDF_Studio::get_settings();
$doc_types  = Riwa_PDF_Studio::$doc_types;
$doc_labels = Riwa_PDF_Studio::$doc_labels;
$block_labels = Riwa_PDF_Studio::$block_labels;

$themes = [
    'minimal' => ['name' => 'Minimaliste', 'primary' => '#1a1a2e', 'font' => 'helvetica'],
    'elegant' => ['name' => 'Élégant',     'primary' => '#7c3aed', 'font' => 'dejavuserif'],
    'marocain'=> ['name' => 'Marocain',    'primary' => '#c2410c', 'font' => 'dejavusans'],
    'ocean'   => ['name' => 'Océan',       'primary' => '#0369a1', 'font' => 'helvetica'],
];

$fonts = [
    'helvetica'   => 'Helvetica',
    'times'       => 'Times',
    'courier'     => 'Courier',
    'dejavusans'  => 'DejaVu Sans',
    'dejavuserif' => 'DejaVu Serif',
];

$blocks_palette = [
    ['type' => 'header',    'icon' => 'dashicons-admin-home',    'label' => 'En-tête',      'fixed' => true],
    ['type' => 'company',   'icon' => 'dashicons-building',      'label' => 'Entreprise',   'fixed' => false],
    ['type' => 'client',    'icon' => 'dashicons-admin-users',   'label' => 'Client',       'fixed' => false],
    ['type' => 'stay',      'icon' => 'dashicons-calendar-alt',  'label' => 'Séjour',       'fixed' => false],
    ['type' => 'travelers', 'icon' => 'dashicons-groups',        'label' => 'Voyageurs',    'fixed' => false],
    ['type' => 'pricing',   'icon' => 'dashicons-money-alt',     'label' => 'Tarifs',       'fixed' => false],
    ['type' => 'text',      'icon' => 'dashicons-editor-ul',     'label' => 'Texte libre',  'fixed' => false],
    ['type' => 'signature', 'icon' => 'dashicons-edit',          'label' => 'Signature',    'fixed' => false],
    ['type' => 'qr',        'icon' => 'dashicons-grid-view',     'label' => 'QR Code',      'fixed' => false],
    ['type' => 'footer',    'icon' => 'dashicons-align-center',  'label' => 'Pied de page', 'fixed' => true],
];
?>
<div class="wrap riwa-studio-wrap">

    <!-- ── En-tête ──────────────────────────────────────────────────────── -->
    <div class="riwa-studio-topbar">
        <div class="riwa-studio-topbar-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=riwa-bookings&section=dashboard')); ?>"
               class="riwa-studio-back-btn" title="Retour au tableau de bord">
                <span class="dashicons dashicons-arrow-left-alt"></span>
            </a>
            <span class="riwa-studio-logo">
                <span class="dashicons dashicons-media-document"></span>
            </span>
            <div>
                <h1 class="riwa-studio-title">Riwa Doc Studio</h1>
                <p class="riwa-studio-subtitle">Éditeur visuel de documents PDF</p>
            </div>
        </div>
        <div class="riwa-studio-topbar-right">
            <span class="riwa-studio-save-status" id="studio-save-status"></span>
            <button type="button" class="riwa-btn riwa-btn-secondary" id="studio-reset-btn">
                <span class="dashicons dashicons-image-rotate"></span> Réinitialiser
            </button>
            <button type="button" class="riwa-btn riwa-btn-secondary" id="studio-preview-btn">
                <span class="dashicons dashicons-visibility"></span> Aperçu
            </button>
            <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=riwa_test_pdf&nonce=' . wp_create_nonce('riwa_pdf_admin_nonce'))); ?>"
               target="_blank" class="riwa-btn riwa-btn-secondary" id="studio-test-pdf-btn">
                <span class="dashicons dashicons-download"></span> Tester PDF
            </a>
            <button type="button" class="riwa-btn riwa-btn-primary" id="studio-save-btn">
                <span class="dashicons dashicons-saved"></span> Sauvegarder
            </button>
        </div>
    </div>

    <div class="riwa-studio-layout">

        <!-- ── Colonne gauche : palette + settings ───────────────────────── -->
        <div class="riwa-studio-sidebar">

            <!-- Type de document -->
            <div class="riwa-studio-panel">
                <div class="riwa-studio-panel-title">
                    <span class="dashicons dashicons-media-document"></span> Type de document
                </div>
                <div class="riwa-studio-doc-types" id="studio-doc-types">
                    <?php foreach ($doc_types as $i => $type): ?>
                    <button type="button"
                            class="riwa-studio-doc-btn <?php echo $i === 0 ? 'active' : ''; ?>"
                            data-type="<?php echo esc_attr($type); ?>">
                        <?php echo esc_html($doc_labels[$type]); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Blocs disponibles -->
            <div class="riwa-studio-panel">
                <div class="riwa-studio-panel-title">
                    <span class="dashicons dashicons-screenoptions"></span> Blocs
                    <span class="riwa-studio-panel-hint">Glisser vers le canvas →</span>
                </div>
                <div class="riwa-studio-palette" id="studio-palette">
                    <?php foreach ($blocks_palette as $block): ?>
                    <div class="riwa-studio-palette-item"
                         data-type="<?php echo esc_attr($block['type']); ?>"
                         draggable="true">
                        <span class="dashicons <?php echo esc_attr($block['icon']); ?>"></span>
                        <span><?php echo esc_html($block['label']); ?></span>
                        <span class="riwa-studio-drag-hint">⠿</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Thèmes -->
            <div class="riwa-studio-panel">
                <div class="riwa-studio-panel-title">
                    <span class="dashicons dashicons-art"></span> Thème
                </div>
                <div class="riwa-studio-themes">
                    <?php foreach ($themes as $key => $theme): ?>
                    <button type="button" class="riwa-studio-theme-btn"
                            data-color="<?php echo esc_attr($theme['primary']); ?>"
                            data-font="<?php echo esc_attr($theme['font']); ?>"
                            title="<?php echo esc_attr($theme['name']); ?>">
                        <span class="riwa-studio-theme-swatch" style="background:<?php echo esc_attr($theme['primary']); ?>;"></span>
                        <?php echo esc_html($theme['name']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div class="riwa-studio-field-row">
                    <label>Couleur principale</label>
                    <input type="text" id="studio-primary-color" class="riwa-color-picker"
                           value="<?php echo esc_attr($settings['primary_color']); ?>">
                </div>
                <div class="riwa-studio-field-row">
                    <label>Police</label>
                    <select id="studio-font-family" class="riwa-input">
                        <?php foreach ($fonts as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['font_family'], $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Informations entreprise -->
            <div class="riwa-studio-panel">
                <div class="riwa-studio-panel-title">
                    <span class="dashicons dashicons-building"></span> Entreprise
                </div>
                <?php
                $company_fields = [
                    'company_name'    => 'Nom société',
                    'company_address' => 'Adresse',
                    'company_phone'   => 'Téléphone',
                    'company_email'   => 'Email',
                    'company_ice'     => 'ICE',
                    'company_rc'      => 'RC',
                    'company_if'      => 'IF',
                    'company_patente' => 'Patente',
                    'logo_url'        => 'URL Logo',
                    'footer_text'     => 'Pied de page',
                ];
                foreach ($company_fields as $key => $label): ?>
                <div class="riwa-studio-field-row">
                    <label for="studio-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
                    <input type="text"
                           id="studio-<?php echo esc_attr($key); ?>"
                           class="riwa-input riwa-studio-setting-field"
                           data-key="<?php echo esc_attr($key); ?>"
                           value="<?php echo esc_attr($settings[$key] ?? ''); ?>">
                </div>
                <?php endforeach; ?>
            </div>

        </div><!-- /.riwa-studio-sidebar -->

        <!-- ── Panneau central : canvas ──────────────────────────────────── -->
        <div class="riwa-studio-canvas-wrap">
            <div class="riwa-studio-canvas-header">
                <span class="riwa-studio-canvas-label" id="studio-canvas-label">
                    <?php echo esc_html($doc_labels['confirmation']); ?>
                </span>
                <span class="riwa-studio-canvas-hint">Glissez des blocs ici · Double-clic = pleine largeur · ✕ = supprimer</span>
            </div>
            <div class="riwa-studio-a4" id="studio-canvas">
                <!-- Lignes de blocs injectées par JS -->
                <div class="riwa-studio-canvas-empty" id="studio-canvas-empty" style="display:none;">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <p>Glissez des blocs depuis la palette pour composer votre document</p>
                </div>
            </div>
        </div>

        <!-- ── Panneau droit : aperçu iframe ─────────────────────────────── -->
        <div class="riwa-studio-preview-panel">
            <div class="riwa-studio-preview-header">
                <span class="dashicons dashicons-visibility"></span>
                Aperçu rendu
                <button type="button" class="riwa-studio-refresh-preview" id="studio-refresh-preview" title="Actualiser">
                    <span class="dashicons dashicons-update-alt"></span>
                </button>
            </div>
            <div class="riwa-studio-preview-wrap">
                <div class="riwa-studio-preview-loading" id="studio-preview-loading" style="display:none;">
                    <span class="dashicons dashicons-update-alt riwa-spin"></span> Génération…
                </div>
                <iframe id="studio-preview-iframe" class="riwa-studio-iframe"
                        srcdoc="<html><body style='display:flex;align-items:center;justify-content:center;height:100%;color:#94a3b8;font-family:sans-serif;'><p>Cliquez sur Aperçu pour voir le rendu</p></body></html>"></iframe>
            </div>
        </div>

    </div><!-- /.riwa-studio-layout -->
</div><!-- /.riwa-studio-wrap -->

<!-- Toast notification -->
<div class="riwa-studio-toast" id="studio-toast"></div>

<!-- Panneau de propriétés du bloc (drawer) -->
<div class="riwa-studio-props-overlay" id="studio-props-overlay"></div>
<div class="riwa-studio-props-drawer" id="studio-props-drawer">
    <div class="riwa-studio-props-header">
        <span class="dashicons" id="studio-props-icon"></span>
        <span id="studio-props-title">Propriétés</span>
        <button type="button" class="riwa-studio-props-close" id="studio-props-close">
            <span class="dashicons dashicons-no-alt"></span>
        </button>
    </div>
    <div class="riwa-studio-props-body" id="studio-props-body">
        <!-- Champs injectés par JS selon le type de bloc -->
    </div>
    <div class="riwa-studio-props-footer">
        <button type="button" class="riwa-btn riwa-btn-primary" id="studio-props-apply">
            <span class="dashicons dashicons-yes"></span> Appliquer
        </button>
        <button type="button" class="riwa-btn riwa-btn-secondary" id="studio-props-cancel">Annuler</button>
    </div>
</div>

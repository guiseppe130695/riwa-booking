<?php
if (!defined('ABSPATH')) {
    exit;
}
// Variables disponibles : $section_title, $section_description, $section_id
$section_id = $section_id ?? 'coming-soon';
?>
<div class="riwa-section" id="<?php echo esc_attr($section_id); ?>-section">
    <div class="riwa-coming-soon-page">
        <span class="dashicons dashicons-clock riwa-coming-soon-icon"></span>
        <h2><?php echo esc_html($section_title ?? 'Section'); ?></h2>
        <p><?php echo esc_html($section_description ?? 'Cette fonctionnalité sera bientôt disponible.'); ?></p>
        <span class="riwa-badge-soon">Bientôt disponible</span>
    </div>
</div>

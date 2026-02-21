<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="riwa-section" id="stats-section">
    <div class="riwa-section-header">
        <h2>Statistiques</h2>
        <p>Pulse Board — pilotez votre activité et prenez les bonnes décisions</p>
    </div>
    <div class="riwa-section-content">

        <!-- ── Navigation des onglets ────────────────────────────────── -->
        <div class="riwa-stats-nav" id="riwa-stats-nav">
            <button class="riwa-stats-tab active" data-tab="pulse">
                <span class="dashicons dashicons-heart"></span>
                Pulse
            </button>
            <button class="riwa-stats-tab" data-tab="analysis">
                <span class="dashicons dashicons-chart-bar"></span>
                Analyse
            </button>
            <button class="riwa-stats-tab" data-tab="forecast">
                <span class="dashicons dashicons-lightbulb"></span>
                Prévision
            </button>
        </div>

        <!-- ── Loader ─────────────────────────────────────────────────── -->
        <div class="riwa-stats-loader" id="riwa-stats-loader">
            <span class="dashicons dashicons-update-alt riwa-spin"></span>
            Chargement…
        </div>

        <!-- ── Contenu onglet Pulse ──────────────────────────────────── -->
        <div class="riwa-stats-tab-content active" id="riwa-stats-tab-pulse"></div>

        <!-- ── Contenu onglet Analyse ────────────────────────────────── -->
        <div class="riwa-stats-tab-content" id="riwa-stats-tab-analysis"></div>

        <!-- ── Contenu onglet Prévision ──────────────────────────────── -->
        <div class="riwa-stats-tab-content" id="riwa-stats-tab-forecast"></div>

    </div>
</div>

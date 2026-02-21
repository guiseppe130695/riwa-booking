<?php
if (!defined('ABSPATH')) {
    exit;
}

Riwa_Upsells_Table::handle_post_actions();
$upsells        = Riwa_Upsells_Table::get_all();
$pricing_labels = Riwa_Upsells_Table::get_pricing_type_labels();
$nonce_field    = wp_nonce_field('riwa_upsell_action', 'riwa_upsell_nonce', true, false);

// Données JSON des upsells pour le JS (édition via dropdown)
$upsells_json = wp_json_encode(array_map(function($u) {
    return [
        'id'           => (int) $u->id,
        'name'         => $u->name,
        'description'  => $u->description,
        'price'        => $u->price,
        'pricing_type' => $u->pricing_type,
        'icon'         => $u->icon,
        'is_active'    => (int) $u->is_active,
    ];
}, $upsells));

$icon_options = [
    'dashicons-coffee'        => 'Café / Petit-déjeuner',
    'dashicons-airplane'      => 'Avion / Navette',
    'dashicons-clock'         => 'Horloge / Late check-out',
    'dashicons-migrate'       => 'Arrivée / Early check-in',
    'dashicons-heart'         => 'Cœur / Romantique',
    'dashicons-palmtree'      => 'Palmier / Piscine',
    'dashicons-star-filled'   => 'Étoile / Premium',
    'dashicons-groups'        => 'Groupe / Famille',
    'dashicons-car'           => 'Voiture / Transfert',
    'dashicons-superhero-alt' => 'Ménage / Service',
    'dashicons-food'          => 'Nourriture / Repas',
    'dashicons-location'      => 'Lieu / Destination',
    'dashicons-admin-home'    => 'Maison / Villa',
    'dashicons-camera'        => 'Photo / Souvenir',
    'dashicons-tickets-alt'   => 'Billet / Activité',
    'dashicons-awards'        => 'Récompense / VIP',
    'dashicons-money-alt'     => 'Argent / Finance',
    'dashicons-sos'           => 'Urgence / Assistance',
    'dashicons-smartphone'    => 'Mobile / Tech',
    'dashicons-admin-generic' => 'Générique',
];
?>

<div class="riwa-section" id="services-section">
    <div class="riwa-section-header">
        <h2>Services additionnels (Upsells)</h2>
        <p>Proposez des services optionnels à vos voyageurs lors de la réservation</p>
    </div>
    <div class="riwa-section-content">

        <!-- ── Barre d'outils ────────────────────────────────────────── -->
        <div class="riwa-upsell-toolbar">
            <div class="riwa-upsell-toolbar-left">
                <span class="riwa-upsell-count-label">
                    <?php echo count($upsells); ?> service<?php echo count($upsells) > 1 ? 's' : ''; ?> configuré<?php echo count($upsells) > 1 ? 's' : ''; ?>
                </span>
            </div>
            <div class="riwa-upsell-toolbar-right">
                <!-- Bouton + dropdown ancré -->
                <div class="riwa-upsell-dropdown-wrap" id="riwa-upsell-dropdown-wrap">
                    <button type="button" class="riwa-btn riwa-btn-primary" id="riwa-upsell-add-btn">
                        <span class="dashicons dashicons-plus"></span>
                        Ajouter un service
                    </button>

                    <!-- ── Dropdown formulaire ──────────────────────── -->
                    <div id="riwa-upsell-form-dropdown" class="riwa-upsell-form-dropdown" style="display:none;">
                        <div class="riwa-upsell-form-dropdown-header">
                            <span id="riwa-upsell-dropdown-title">Ajouter un service</span>
                            <button type="button" class="riwa-upsell-dropdown-close" id="riwa-upsell-dropdown-close" title="Fermer">&#x2715;</button>
                        </div>

                        <form method="post" class="riwa-upsell-form" id="riwa-upsell-form">
                            <?php echo $nonce_field; ?>
                            <input type="hidden" name="riwa_upsell_action" id="riwa-upsell-action-field" value="add">
                            <input type="hidden" name="upsell_id" id="riwa-upsell-id-field" value="">

                            <div class="riwa-upsell-form-dropdown-body">

                                <div class="riwa-upsell-form-grid">
                                    <div class="riwa-upsell-form-col">
                                        <div class="riwa-form-row">
                                            <label class="riwa-form-label" for="upsell_name">Nom du service <span class="required">*</span></label>
                                            <input type="text" id="upsell_name" name="upsell_name" class="riwa-form-input"
                                                   value="" placeholder="Ex : Petit-déjeuner" required>
                                        </div>
                                        <div class="riwa-form-row">
                                            <label class="riwa-form-label" for="upsell_description">Description</label>
                                            <textarea id="upsell_description" name="upsell_description" class="riwa-form-input" rows="2"
                                                      placeholder="Description courte visible par le voyageur"></textarea>
                                        </div>
                                        <div class="riwa-form-row">
                                            <label class="riwa-form-label">Icône</label>
                                            <input type="hidden" id="upsell_icon" name="upsell_icon" value="dashicons-admin-generic">
                                            <div class="riwa-icon-grid" id="riwa-icon-grid">
                                                <?php foreach ($icon_options as $dashicon => $label): ?>
                                                    <button type="button"
                                                            class="riwa-icon-option <?php echo $dashicon === 'dashicons-admin-generic' ? 'is-selected' : ''; ?>"
                                                            data-icon="<?php echo esc_attr($dashicon); ?>"
                                                            title="<?php echo esc_attr($label); ?>">
                                                        <span class="dashicons <?php echo esc_attr($dashicon); ?>"></span>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                            <span class="riwa-form-hint">Sélectionnez l'icône qui représente ce service</span>
                                        </div>
                                    </div>
                                    <div class="riwa-upsell-form-col">
                                        <div class="riwa-form-row">
                                            <label class="riwa-form-label" for="upsell_price">Prix (€) <span class="required">*</span></label>
                                            <input type="number" id="upsell_price" name="upsell_price" class="riwa-form-input"
                                                   value="" min="0" step="0.01" placeholder="0.00" required>
                                        </div>
                                        <div class="riwa-form-row">
                                            <label class="riwa-form-label" for="upsell_pricing_type">Mode de tarification</label>
                                            <select id="upsell_pricing_type" name="upsell_pricing_type" class="riwa-form-input">
                                                <?php foreach ($pricing_labels as $key => $label): ?>
                                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="riwa-form-hint" id="pricing-type-hint">Ex : 60 € pour la navette aéroport</span>
                                        </div>
                                        <div class="riwa-form-row riwa-form-row--checkbox">
                                            <label class="riwa-toggle-label">
                                                <input type="checkbox" id="upsell_is_active" name="upsell_is_active" value="1" checked>
                                                <span class="riwa-toggle-switch"></span>
                                                <span>Actif (visible dans le formulaire de réservation)</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                            </div><!-- /.riwa-upsell-form-dropdown-body -->

                            <div class="riwa-upsell-form-dropdown-footer">
                                <button type="submit" class="riwa-btn riwa-btn-primary" id="riwa-upsell-submit-btn">
                                    <span class="dashicons dashicons-plus" id="riwa-upsell-submit-icon"></span>
                                    <span id="riwa-upsell-submit-label">Ajouter le service</span>
                                </button>
                                <button type="button" class="riwa-btn riwa-btn-secondary" id="riwa-upsell-cancel-btn">
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </div><!-- /#riwa-upsell-form-dropdown -->
                </div><!-- /.riwa-upsell-dropdown-wrap -->
            </div>
        </div>

        <!-- ── Liste des services ─────────────────────────────────────── -->
        <?php if (empty($upsells)): ?>
            <div class="riwa-empty-state">
                <span class="dashicons dashicons-store"></span>
                <h3>Aucun service configuré</h3>
                <p>Ajoutez vos premiers services additionnels avec le bouton ci-dessus.</p>
            </div>
        <?php else: ?>
            <div class="riwa-upsell-grid" id="riwa-upsell-grid">
                <?php foreach ($upsells as $upsell): ?>
                    <div class="riwa-upsell-card <?php echo (int)$upsell->is_active ? 'is-active' : 'is-inactive'; ?>"
                         data-upsell-id="<?php echo esc_attr($upsell->id); ?>">
                        <div class="riwa-upsell-card-icon"><?php echo Riwa_Upsells_Table::render_icon($upsell->icon ?: 'dashicons-admin-generic'); ?></div>
                        <div class="riwa-upsell-card-body">
                            <div class="riwa-upsell-card-name"><?php echo esc_html($upsell->name); ?></div>
                            <div class="riwa-upsell-card-desc"><?php echo esc_html($upsell->description); ?></div>
                            <div class="riwa-upsell-card-price">
                                <strong><?php echo number_format($upsell->price, 0, ',', ' '); ?> €</strong>
                                <span class="riwa-upsell-pricing-type"><?php echo esc_html($pricing_labels[$upsell->pricing_type] ?? $upsell->pricing_type); ?></span>
                            </div>
                        </div>
                        <div class="riwa-upsell-card-status">
                            <?php if ((int)$upsell->is_active): ?>
                                <span class="riwa-badge-active">Actif</span>
                            <?php else: ?>
                                <span class="riwa-badge-inactive">Inactif</span>
                            <?php endif; ?>
                        </div>
                        <div class="riwa-upsell-card-actions">
                            <!-- Activer/Désactiver -->
                            <form method="post" style="display:inline;">
                                <?php echo $nonce_field; ?>
                                <input type="hidden" name="riwa_upsell_action" value="toggle">
                                <input type="hidden" name="upsell_id" value="<?php echo esc_attr($upsell->id); ?>">
                                <button type="submit" class="riwa-icon-btn"
                                        title="<?php echo (int)$upsell->is_active ? 'Désactiver' : 'Activer'; ?>">
                                    <span class="dashicons <?php echo (int)$upsell->is_active ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"></span>
                                </button>
                            </form>
                            <!-- Modifier (ouvre le dropdown en mode édition) -->
                            <button type="button" class="riwa-icon-btn riwa-upsell-edit-btn"
                                    title="Modifier"
                                    data-id="<?php echo esc_attr($upsell->id); ?>"
                                    data-name="<?php echo esc_attr($upsell->name); ?>"
                                    data-description="<?php echo esc_attr($upsell->description); ?>"
                                    data-price="<?php echo esc_attr($upsell->price); ?>"
                                    data-pricing-type="<?php echo esc_attr($upsell->pricing_type); ?>"
                                    data-icon="<?php echo esc_attr($upsell->icon ?: 'dashicons-admin-generic'); ?>"
                                    data-is-active="<?php echo esc_attr((int)$upsell->is_active); ?>">
                                <span class="dashicons dashicons-edit"></span>
                            </button>
                            <!-- Supprimer -->
                            <form method="post" style="display:inline;"
                                  onsubmit="return confirm('Supprimer ce service ? Cette action est irréversible.');">
                                <?php echo $nonce_field; ?>
                                <input type="hidden" name="riwa_upsell_action" value="delete">
                                <input type="hidden" name="upsell_id" value="<?php echo esc_attr($upsell->id); ?>">
                                <button type="submit" class="riwa-icon-btn riwa-icon-btn--danger" title="Supprimer">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="riwa-upsell-info-banner">
                <span class="dashicons dashicons-info-outline"></span>
                Les services <strong>actifs</strong> apparaissent dans le formulaire de réservation et dans les factures PDF.
                Modifiez les prix ou activez/désactivez un service à tout moment sans affecter les réservations existantes.
            </div>
        <?php endif; ?>

    </div>
</div>

<script>
jQuery(function($) {

    // ── Dropdown upsell form ──────────────────────────────────────────────────
    var $wrap     = $('#riwa-upsell-dropdown-wrap');
    var $btn      = $('#riwa-upsell-add-btn');
    var $dropdown = $('#riwa-upsell-form-dropdown');

    var pricingHints = {
        'fixed'               : 'Ex : 60 € pour la navette aéroport',
        'per_night'           : 'Ex : 30 €/nuit pour la piscine chauffée',
        'per_person'          : 'Ex : 15 € par voyageur',
        'per_person_per_night': 'Ex : 25 €/personne/nuit pour le petit-déjeuner'
    };

    function positionDropdown() {
        var rect  = $btn[0].getBoundingClientRect();
        var dropW = 560;
        var top   = rect.bottom + 6;
        var left  = rect.right - dropW;
        var maxH  = window.innerHeight - top - 16;
        if (left < 8) left = 8;
        $dropdown.css({ top: top + 'px', left: left + 'px', right: 'auto' });
        $dropdown.find('.riwa-upsell-form-dropdown-body').css('max-height', Math.max(180, maxH - 120) + 'px');
    }

    function openDropdown() {
        positionDropdown();
        $dropdown.stop(true, true).slideDown(160);
        $btn.addClass('riwa-toolbar-btn--active');
    }

    function closeDropdown() {
        $dropdown.stop(true, true).slideUp(130);
        $btn.removeClass('riwa-toolbar-btn--active');
    }

    function resetForm() {
        $('#riwa-upsell-action-field').val('add');
        $('#riwa-upsell-id-field').val('');
        $('#riwa-upsell-dropdown-title').text('Ajouter un service');
        $('#riwa-upsell-submit-icon').attr('class', 'dashicons dashicons-plus');
        $('#riwa-upsell-submit-label').text('Ajouter le service');
        $('#upsell_name').val('');
        $('#upsell_description').val('');
        $('#upsell_price').val('');
        $('#upsell_pricing_type').val('fixed');
        $('#pricing-type-hint').text(pricingHints['fixed']);
        $('#upsell_is_active').prop('checked', true);
        selectIcon('dashicons-admin-generic');
    }

    function selectIcon(icon) {
        $('#riwa-icon-grid .riwa-icon-option').removeClass('is-selected');
        $('#riwa-icon-grid .riwa-icon-option[data-icon="' + icon + '"]').addClass('is-selected');
        $('#upsell_icon').val(icon);
    }

    // Bouton "Ajouter un service" → reset + ouvrir
    $btn.on('click', function(e) {
        e.stopPropagation();
        if ($dropdown.is(':visible')) {
            closeDropdown();
        } else {
            resetForm();
            openDropdown();
        }
    });

    // Bouton fermer (×) et Annuler
    $('#riwa-upsell-dropdown-close, #riwa-upsell-cancel-btn').on('click', function() {
        closeDropdown();
    });

    // Clic en dehors → fermer
    $(document).on('click', function(e) {
        if ($dropdown.is(':visible') && !$wrap.is(e.target) && $wrap.has(e.target).length === 0) {
            closeDropdown();
        }
    });

    // Escape → fermer
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $dropdown.is(':visible')) {
            closeDropdown();
        }
    });

    // Resize → repositionner si ouvert
    $(window).on('resize', function() {
        if ($dropdown.is(':visible')) positionDropdown();
    });

    // ── Boutons Modifier sur les cartes ──────────────────────────────────────
    $(document).on('click', '.riwa-upsell-edit-btn', function(e) {
        e.stopPropagation();
        var $card = $(this);
        var id          = $card.data('id');
        var name        = $card.data('name');
        var description = $card.data('description');
        var price       = $card.data('price');
        var pricingType = $card.data('pricing-type');
        var icon        = $card.data('icon') || 'dashicons-admin-generic';
        var isActive    = parseInt($card.data('is-active'), 10);

        // Remplir le formulaire
        $('#riwa-upsell-action-field').val('edit');
        $('#riwa-upsell-id-field').val(id);
        $('#riwa-upsell-dropdown-title').text('Modifier le service');
        $('#riwa-upsell-submit-icon').attr('class', 'dashicons dashicons-yes');
        $('#riwa-upsell-submit-label').text('Enregistrer les modifications');
        $('#upsell_name').val(name);
        $('#upsell_description').val(description);
        $('#upsell_price').val(price);
        $('#upsell_pricing_type').val(pricingType);
        $('#pricing-type-hint').text(pricingHints[pricingType] || '');
        $('#upsell_is_active').prop('checked', isActive === 1);
        selectIcon(icon);

        openDropdown();
    });

    // ── Mise à jour du hint selon le type de tarification ────────────────────
    $('#upsell_pricing_type').on('change', function() {
        $('#pricing-type-hint').text(pricingHints[$(this).val()] || '');
    });

    // ── Sélecteur d'icônes ───────────────────────────────────────────────────
    $('#riwa-icon-grid').on('click', '.riwa-icon-option', function() {
        selectIcon($(this).data('icon'));
    });

});
</script>

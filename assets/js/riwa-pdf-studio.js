/**
 * Riwa Doc Studio — Éditeur PDF Canva-like
 * SortableJS + AJAX + aperçu iframe
 */
(function ($) {
    'use strict';

    if (typeof riwa_studio_config === 'undefined') return;

    var CFG      = riwa_studio_config;
    var AJAX     = CFG.ajax_url;
    var NONCE    = CFG.nonce;

    /* ================================================================ */
    /*  État de l'éditeur                                                */
    /* ================================================================ */

    var State = {
        docType  : CFG.doc_types[0] || 'confirmation',
        layouts  : JSON.parse(JSON.stringify(CFG.layouts || {})),  // deep copy
        settings : JSON.parse(JSON.stringify(CFG.settings || {})),
        dirty    : false,
        blockIdCounter: 100,
    };

    /* ================================================================ */
    /*  Utilitaires                                                       */
    /* ================================================================ */

    function uid() {
        return 'b' + (++State.blockIdCounter) + '-' + Math.random().toString(36).slice(2, 6);
    }

    function rowUid() {
        return 'r' + (++State.blockIdCounter) + '-' + Math.random().toString(36).slice(2, 6);
    }

    function toast(msg, type) {
        var $t = $('#studio-toast');
        $t.attr('class', 'riwa-studio-toast riwa-studio-toast--' + (type || 'success'))
          .text(msg).addClass('visible');
        clearTimeout($t.data('timer'));
        $t.data('timer', setTimeout(function () { $t.removeClass('visible'); }, 3000));
    }

    function markDirty() {
        State.dirty = true;
        $('#studio-save-status').text('Modifications non sauvegardées').addClass('dirty');
    }

    function markClean() {
        State.dirty = false;
        $('#studio-save-status').text('Sauvegardé').removeClass('dirty');
        setTimeout(function () { $('#studio-save-status').text(''); }, 2500);
    }

    /* ================================================================ */
    /*  Layout → DOM                                                     */
    /* ================================================================ */

    var BLOCK_ICONS = {
        header   : 'dashicons-admin-home',
        company  : 'dashicons-building',
        client   : 'dashicons-admin-users',
        stay     : 'dashicons-calendar-alt',
        travelers: 'dashicons-groups',
        pricing  : 'dashicons-money-alt',
        text     : 'dashicons-editor-ul',
        signature: 'dashicons-edit',
        qr       : 'dashicons-grid-view',
        footer   : 'dashicons-align-center',
    };

    var BLOCK_FIXED_FULL = ['header', 'footer']; // toujours span 2

    function blockLabel(type) {
        return (CFG.block_labels && CFG.block_labels[type]) ? CFG.block_labels[type] : type;
    }

    function makeBlockEl(block) {
        var type   = block.type;
        var span   = BLOCK_FIXED_FULL.indexOf(type) !== -1 ? 2 : (block.span || 1);
        var icon   = BLOCK_ICONS[type] || 'dashicons-admin-generic';
        var label  = blockLabel(type);
        var cls    = 'riwa-studio-block' + (span === 2 ? ' block-full' : '');

        var $el = $('<div>')
            .addClass(cls)
            .attr('data-type', type)
            .attr('data-span', span)
            .attr('data-block-id', block.id || uid());

        if (block.config) {
            $el.attr('data-config', JSON.stringify(block.config));
        }

        // Sous-label de prévisualisation
        var config  = block.config || {};
        var sublabel = '';
        if (type === 'text' && config.content) {
            sublabel = config.content.substring(0, 40) + (config.content.length > 40 ? '…' : '');
        } else if (config.title)    { sublabel = config.title; }
        else if (config.subtitle)   { sublabel = config.subtitle; }
        else if (config.label)      { sublabel = config.label; }
        else if (config.text)       { sublabel = config.text.substring(0, 40) + (config.text.length > 40 ? '…' : ''); }

        $el.html(
            '<span class="studio-block-drag" title="Déplacer">⠿</span>' +
            '<span class="dashicons ' + icon + '"></span>' +
            '<span class="studio-block-label">' + escHtml(label) + '</span>' +
            (sublabel ? '<span class="studio-block-sublabel">' + escHtml(sublabel) + '</span>' : '') +
            (BLOCK_FIXED_FULL.indexOf(type) === -1
                ? '<button class="studio-block-toggle" title="Pleine largeur / Demi">⟺</button>'
                : '') +
            '<button class="studio-block-remove" title="Supprimer">✕</button>'
        );

        return $el;
    }

    function makeRowEl(row) {
        var $row = $('<div>').addClass('riwa-studio-canvas-row').attr('data-row-id', row.id || rowUid());
        $.each(row.blocks || [], function (_, block) {
            $row.append(makeBlockEl(block));
        });
        return $row;
    }

    function renderCanvas() {
        var $canvas = $('#studio-canvas');
        var layout  = State.layouts[State.docType] || { rows: [] };

        $canvas.find('.riwa-studio-canvas-row').remove();

        if (!layout.rows || layout.rows.length === 0) {
            $('#studio-canvas-empty').show();
        } else {
            $('#studio-canvas-empty').hide();
            $.each(layout.rows, function (_, row) {
                $canvas.append(makeRowEl(row));
            });
        }

        initSortableCanvas();
        updateCanvasLabel();
    }

    function updateCanvasLabel() {
        var label = (CFG.doc_labels && CFG.doc_labels[State.docType]) ? CFG.doc_labels[State.docType] : State.docType;
        $('#studio-canvas-label').text(label);
    }

    /* ================================================================ */
    /*  DOM → Layout JSON                                                 */
    /* ================================================================ */

    function domToLayout() {
        var rows = [];
        $('#studio-canvas .riwa-studio-canvas-row').each(function () {
            var rowId  = $(this).attr('data-row-id') || rowUid();
            var blocks = [];
            $(this).find('.riwa-studio-block').each(function () {
                var $b = $(this);
                var b = {
                    id   : $b.attr('data-block-id') || uid(),
                    type : $b.attr('data-type'),
                    span : parseInt($b.attr('data-span')) || 1,
                };
                var configStr = $b.attr('data-config');
                if (configStr) {
                    try { b.config = JSON.parse(configStr); } catch(e) {}
                }
                blocks.push(b);
            });
            if (blocks.length > 0) {
                rows.push({ id: rowId, blocks: blocks });
            }
        });
        return { rows: rows };
    }

    function syncLayout() {
        State.layouts[State.docType] = domToLayout();
        markDirty();
    }

    /* ================================================================ */
    /*  SortableJS                                                        */
    /* ================================================================ */

    var sortableCanvas = null;
    var sortableRows   = [];

    function initSortableCanvas() {
        // Détruire anciennes instances
        sortableRows.forEach(function (s) { s.destroy(); });
        sortableRows = [];
        if (sortableCanvas) { sortableCanvas.destroy(); sortableCanvas = null; }

        var canvas = document.getElementById('studio-canvas');
        if (!canvas) return;

        // Trier les lignes (rows) entre elles
        sortableCanvas = new Sortable(canvas, {
            group: 'rows',
            animation: 150,
            handle: '.studio-block-drag',
            filter: '.riwa-studio-canvas-empty',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function () { syncLayout(); schedulePreview(); },
        });

        // Trier les blocs dans chaque ligne
        canvas.querySelectorAll('.riwa-studio-canvas-row').forEach(function (row) {
            var s = new Sortable(row, {
                group: { name: 'blocks', pull: true, put: true },
                animation: 150,
                handle: '.studio-block-drag',
                ghostClass: 'sortable-ghost',
                filter: '.studio-block-remove, .studio-block-toggle',
                onEnd: function (evt) {
                    enforceBlockConstraints(evt.item);
                    syncLayout();
                    schedulePreview();
                },
            });
            sortableRows.push(s);
        });
    }

    function enforceBlockConstraints(el) {
        var $el  = $(el);
        var type = $el.attr('data-type');
        var $row = $el.closest('.riwa-studio-canvas-row');

        // Header/Footer : toujours full, seul dans sa ligne
        if (BLOCK_FIXED_FULL.indexOf(type) !== -1) {
            $el.attr('data-span', 2).addClass('block-full');
            $row.find('.riwa-studio-block').not($el).remove();
        }

        // Max 2 blocs par ligne
        var siblings = $row.find('.riwa-studio-block');
        if (siblings.length > 2) {
            // Retirer le dernier ajouté sauf celui en cours
            siblings.last().remove();
        }
    }

    /* ================================================================ */
    /*  Palette → Canvas (drop via palette items)                        */
    /* ================================================================ */

    function initPalette() {
        $('#studio-palette').on('click', '.riwa-studio-palette-item', function () {
            var type  = $(this).attr('data-type');
            addBlockToCanvas(type);
        });

        // Drag natif HTML5 depuis la palette
        document.querySelectorAll('.riwa-studio-palette-item').forEach(function (item) {
            item.addEventListener('dragstart', function (e) {
                e.dataTransfer.setData('text/plain', item.dataset.type);
                e.dataTransfer.effectAllowed = 'copy';
            });
        });

        var canvas = document.getElementById('studio-canvas');
        if (!canvas) return;

        canvas.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            canvas.classList.add('drag-over');
        });

        canvas.addEventListener('dragleave', function () {
            canvas.classList.remove('drag-over');
        });

        canvas.addEventListener('drop', function (e) {
            e.preventDefault();
            canvas.classList.remove('drag-over');
            var type = e.dataTransfer.getData('text/plain');
            if (type) addBlockToCanvas(type);
        });
    }

    function addBlockToCanvas(type) {
        var block = { id: uid(), type: type, span: BLOCK_FIXED_FULL.indexOf(type) !== -1 ? 2 : 1 };
        var $block = makeBlockEl(block);

        // Header → toujours première ligne ; Footer → dernière
        if (type === 'header') {
            var $row = $('<div>').addClass('riwa-studio-canvas-row').attr('data-row-id', rowUid());
            $row.append($block);
            $('#studio-canvas').prepend($row);
        } else if (type === 'footer') {
            var $row = $('<div>').addClass('riwa-studio-canvas-row').attr('data-row-id', rowUid());
            $row.append($block);
            $('#studio-canvas').append($row);
        } else {
            // Essayer d'ajouter dans la dernière ligne non-footer qui a moins de 2 blocs
            var $lastRow = null;
            $('#studio-canvas .riwa-studio-canvas-row').each(function () {
                var types = $(this).find('.riwa-studio-block').map(function () {
                    return $(this).attr('data-type');
                }).get();
                var hasFixed = types.some(function (t) { return BLOCK_FIXED_FULL.indexOf(t) !== -1; });
                if (!hasFixed && types.length < 2) $lastRow = $(this);
            });

            if ($lastRow) {
                $lastRow.append($block);
            } else {
                var $row = $('<div>').addClass('riwa-studio-canvas-row').attr('data-row-id', rowUid());
                $row.append($block);
                // Insérer avant le footer s'il existe
                var $footer = $('#studio-canvas .riwa-studio-canvas-row').filter(function () {
                    return $(this).find('[data-type="footer"]').length > 0;
                });
                if ($footer.length) {
                    $footer.before($row);
                } else {
                    $('#studio-canvas').append($row);
                }
            }
        }

        $('#studio-canvas-empty').hide();
        initSortableCanvas();
        syncLayout();
        schedulePreview();
    }

    /* ================================================================ */
    /*  Panneau de propriétés (drawer)                                   */
    /* ================================================================ */

    // Schéma des champs par type de bloc
    // Groupes : section = séparateur visuel dans le drawer
    var BLOCK_PROPS = {
        header: [
            { section: 'Contenu' },
            { key: 'subtitle',    label: 'Sous-titre',        type: 'text', placeholder: 'Confirmation de réservation' },
            { section: 'Affichage' },
            { key: 'show_ref',    label: 'Afficher la référence', type: 'checkbox', default: true },
            { key: 'show_date',   label: 'Afficher la date',      type: 'checkbox', default: true },
        ],
        company: [
            { section: 'Titre' },
            { key: 'title', label: 'Titre du bloc', type: 'text', placeholder: 'Hébergeur' },
            { section: 'Champs visibles' },
            { key: 'show_name',    label: 'Nom / Société',  type: 'checkbox', default: true },
            { key: 'show_address', label: 'Adresse',         type: 'checkbox', default: true },
            { key: 'show_phone',   label: 'Téléphone',       type: 'checkbox', default: true },
            { key: 'show_email',   label: 'Email',           type: 'checkbox', default: true },
            { key: 'show_ice',     label: 'ICE',             type: 'checkbox', default: false },
            { key: 'show_rc',      label: 'RC',              type: 'checkbox', default: false },
            { key: 'show_if',      label: 'IF',              type: 'checkbox', default: false },
            { key: 'show_patente', label: 'Patente',         type: 'checkbox', default: false },
            { section: 'Labels' },
            { key: 'lbl_name',    label: 'Label Société',   type: 'text', placeholder: 'Société' },
            { key: 'lbl_address', label: 'Label Adresse',   type: 'text', placeholder: 'Adresse' },
            { key: 'lbl_phone',   label: 'Label Téléphone', type: 'text', placeholder: 'Tél' },
            { key: 'lbl_email',   label: 'Label Email',     type: 'text', placeholder: 'Email' },
        ],
        client: [
            { section: 'Titre' },
            { key: 'title', label: 'Titre du bloc', type: 'text', placeholder: 'Client' },
            { section: 'Champs visibles' },
            { key: 'show_name',  label: 'Nom',   type: 'checkbox', default: true },
            { key: 'show_email', label: 'Email', type: 'checkbox', default: true },
            { key: 'show_phone', label: 'Tél',   type: 'checkbox', default: true },
            { section: 'Labels' },
            { key: 'lbl_name',  label: 'Label Nom',   type: 'text', placeholder: 'Nom' },
            { key: 'lbl_email', label: 'Label Email', type: 'text', placeholder: 'Email' },
            { key: 'lbl_phone', label: 'Label Tél',   type: 'text', placeholder: 'Tél' },
        ],
        stay: [
            { section: 'Titre' },
            { key: 'title', label: 'Titre du bloc', type: 'text', placeholder: 'Séjour' },
            { section: 'Champs visibles' },
            { key: 'show_checkin',  label: 'Arrivée',  type: 'checkbox', default: true },
            { key: 'show_checkout', label: 'Départ',   type: 'checkbox', default: true },
            { key: 'show_nights',   label: 'Durée',    type: 'checkbox', default: true },
            { section: 'Labels' },
            { key: 'lbl_checkin',  label: 'Label Arrivée', type: 'text', placeholder: 'Arrivée' },
            { key: 'lbl_checkout', label: 'Label Départ',  type: 'text', placeholder: 'Départ' },
            { key: 'lbl_nights',   label: 'Label Durée',   type: 'text', placeholder: 'Durée' },
            { key: 'lbl_nights_unit', label: 'Unité durée', type: 'text', placeholder: 'nuit(s)' },
        ],
        travelers: [
            { section: 'Titre' },
            { key: 'title', label: 'Titre du bloc', type: 'text', placeholder: 'Voyageurs' },
            { section: 'Champs visibles' },
            { key: 'show_adults',   label: 'Adultes', type: 'checkbox', default: true },
            { key: 'show_children', label: 'Enfants', type: 'checkbox', default: true },
            { key: 'show_babies',   label: 'Bébés',   type: 'checkbox', default: true },
            { section: 'Labels' },
            { key: 'lbl_adults',   label: 'Label Adultes', type: 'text', placeholder: 'Adultes' },
            { key: 'lbl_children', label: 'Label Enfants', type: 'text', placeholder: 'Enfants' },
            { key: 'lbl_babies',   label: 'Label Bébés',   type: 'text', placeholder: 'Bébés' },
        ],
        pricing: [
            { section: 'Titre' },
            { key: 'title',    label: 'Titre du bloc', type: 'text', placeholder: 'Tarifs' },
            { section: 'Devise & Format' },
            { key: 'currency', label: 'Devise', type: 'select',
              options: [['€','EUR — Euro'],['$','USD — Dollar'],['MAD','MAD — Dirham'],['CHF','CHF — Franc suisse']] },
            { key: 'lbl_total', label: 'Label Total', type: 'text', placeholder: 'Total' },
            { key: 'lbl_night', label: 'Unité nuit', type: 'text', placeholder: 'nuit(s)' },
        ],
        text: [
            { section: 'Contenu' },
            { key: 'content', label: 'Texte', type: 'textarea',
              placeholder: 'Saisissez votre texte ici…', rows: 7 },
        ],
        signature: [
            { section: 'Contenu' },
            { key: 'label',        label: 'Libellé zone signature', type: 'text', placeholder: 'Signature du client' },
            { key: 'label_vendor', label: 'Libellé vendeur (optionnel)', type: 'text', placeholder: 'Signature de l\'hébergeur' },
            { section: 'Affichage' },
            { key: 'show_date',    label: 'Afficher ligne date',    type: 'checkbox', default: true },
            { key: 'two_cols',     label: 'Deux colonnes (client + vendeur)', type: 'checkbox', default: false },
        ],
        footer: [
            { section: 'Contenu' },
            { key: 'text', label: 'Texte principal', type: 'textarea',
              placeholder: 'Pour toute question, contactez-nous.', rows: 3 },
            { section: 'Affichage' },
            { key: 'show_generated', label: 'Afficher "Document généré par…"', type: 'checkbox', default: true },
        ],
        qr: [
            { section: 'Contenu' },
            { key: 'label', label: 'Titre du bloc', type: 'text', placeholder: 'Référence' },
        ],
    };

    var $propsDrawer  = null;
    var $propsOverlay = null;
    var currentPropsBlockId = null;

    function initPropsDrawer() {
        $propsDrawer  = $('#studio-props-drawer');
        $propsOverlay = $('#studio-props-overlay');

        $('#studio-props-close, #studio-props-cancel').on('click', closePropsDrawer);
        $propsOverlay.on('click', closePropsDrawer);

        $('#studio-props-apply').on('click', applyBlockConfig);
    }

    function openPropsDrawer($block) {
        var type    = $block.attr('data-type');
        var blockId = $block.attr('data-block-id');
        var config  = {};
        try { config = JSON.parse($block.attr('data-config') || '{}'); } catch(e) {}

        var fields  = BLOCK_PROPS[type] || [];
        var icon    = BLOCK_ICONS[type] || 'dashicons-admin-generic';
        var label   = blockLabel(type);

        currentPropsBlockId = blockId;

        // En-tête
        $('#studio-props-icon').attr('class', 'dashicons ' + icon);
        $('#studio-props-title').text(label);

        // Corps — générer les champs
        var html = '';

        if (fields.length === 0) {
            html = '<p class="riwa-studio-props-empty">Ce bloc n\'a pas de propriétés configurables.</p>';
        } else {
            $.each(fields, function(_, field) {
                // Séparateur de section
                if (field.section !== undefined) {
                    html += '<div class="riwa-studio-props-section">' + escHtml(field.section) + '</div>';
                    return; // continue
                }

                // Valeur : config sauvegardée → défaut du schéma → chaîne vide
                var val;
                if (config[field.key] !== undefined) {
                    val = config[field.key];
                } else if (field['default'] !== undefined) {
                    val = field['default'];
                } else {
                    val = '';
                }

                html += '<div class="riwa-studio-props-field">';
                html += '<label class="riwa-studio-props-label" for="prop-' + field.key + '">' + escHtml(field.label) + '</label>';

                if (field.type === 'textarea') {
                    html += '<textarea id="prop-' + field.key + '" class="riwa-studio-props-input" '
                         + 'data-key="' + field.key + '" rows="' + (field.rows || 3) + '" '
                         + 'placeholder="' + escHtml(field.placeholder || '') + '">'
                         + escHtml(val) + '</textarea>';

                } else if (field.type === 'select') {
                    html += '<select id="prop-' + field.key + '" class="riwa-studio-props-input" data-key="' + field.key + '">';
                    $.each(field.options, function(_, opt) {
                        html += '<option value="' + escHtml(opt[0]) + '"'
                             + (val === opt[0] ? ' selected' : '') + '>'
                             + escHtml(opt[1]) + '</option>';
                    });
                    html += '</select>';

                } else if (field.type === 'checkbox') {
                    var isChecked = (val === true || val === 'true' || val === 1 || val === '1');
                    html += '<label class="riwa-studio-props-checkbox">'
                         + '<input type="checkbox" id="prop-' + field.key + '" data-key="' + field.key + '"'
                         + (isChecked ? ' checked' : '') + '>'
                         + ' ' + escHtml(field.label) + '</label>';

                } else {
                    html += '<input type="text" id="prop-' + field.key + '" class="riwa-studio-props-input" '
                         + 'data-key="' + field.key + '" value="' + escHtml(val) + '" '
                         + 'placeholder="' + escHtml(field.placeholder || '') + '">';
                }

                html += '</div>';
            });
        }

        $('#studio-props-body').html(html);

        $propsDrawer.addClass('open');
        $propsOverlay.addClass('visible');

        // Focus premier champ
        $propsDrawer.find('input[type="text"], textarea').first().focus();
    }

    function closePropsDrawer() {
        $propsDrawer.removeClass('open');
        $propsOverlay.removeClass('visible');
        currentPropsBlockId = null;
    }

    function applyBlockConfig() {
        if (!currentPropsBlockId) return;

        var $block = $('#studio-canvas [data-block-id="' + currentPropsBlockId + '"]');
        if (!$block.length) { closePropsDrawer(); return; }

        // Collecter les valeurs (ignorer les séparateurs qui n'ont pas data-key)
        var config = {};
        $('#studio-props-body [data-key]').each(function () {
            var $f  = $(this);
            var key = $f.data('key');
            if (!key) return;
            if ($f.attr('type') === 'checkbox') {
                config[key] = $f.is(':checked');
            } else {
                config[key] = $f.val();
            }
        });

        // Stocker dans le DOM
        $block.attr('data-config', JSON.stringify(config));

        // Mettre à jour le sous-label visuel du bloc
        updateBlockSubLabel($block, config);

        closePropsDrawer();
        syncLayout();
        schedulePreview();
    }

    function updateBlockSubLabel($block, config) {
        var type = $block.attr('data-type');

        // Retirer l'ancien sous-label
        $block.find('.studio-block-sublabel').remove();

        var hint = '';
        if (type === 'text' && config.content) {
            hint = config.content.substring(0, 40) + (config.content.length > 40 ? '…' : '');
        } else if (config.title) {
            hint = config.title;
        } else if (config.subtitle) {
            hint = config.subtitle;
        } else if (config.label) {
            hint = config.label;
        } else if (config.text) {
            hint = config.text.substring(0, 40) + (config.text.length > 40 ? '…' : '');
        }

        if (hint) {
            $block.find('.studio-block-label').after(
                '<span class="studio-block-sublabel">' + escHtml(hint) + '</span>'
            );
        }
    }

    /* ================================================================ */
    /*  Interactions sur les blocs (toggle, remove)                      */
    /* ================================================================ */

    function initBlockInteractions() {
        $('#studio-canvas').on('click', '.studio-block-remove', function (e) {
            e.stopPropagation();
            var $block = $(this).closest('.riwa-studio-block');
            var $row   = $block.closest('.riwa-studio-canvas-row');
            $block.remove();
            if ($row.find('.riwa-studio-block').length === 0) {
                $row.remove();
            }
            if ($('#studio-canvas .riwa-studio-canvas-row').length === 0) {
                $('#studio-canvas-empty').show();
            }
            syncLayout();
            schedulePreview();
        });

        // Clic simple → ouvrir le panneau de propriétés
        $('#studio-canvas').on('click', '.riwa-studio-block', function (e) {
            if ($(e.target).hasClass('studio-block-remove') ||
                $(e.target).hasClass('studio-block-toggle') ||
                $(e.target).hasClass('studio-block-drag')) return;
            openPropsDrawer($(this));
        });

        // Double-clic → toggle span
        $('#studio-canvas').on('dblclick', '.riwa-studio-block', function (e) {
            if ($(e.target).hasClass('studio-block-remove') || $(e.target).hasClass('studio-block-toggle')) return;
            var $b   = $(this);
            var type = $b.attr('data-type');
            if (BLOCK_FIXED_FULL.indexOf(type) !== -1) return;

            var $row   = $b.closest('.riwa-studio-canvas-row');
            var $sibs  = $row.find('.riwa-studio-block');
            var current = parseInt($b.attr('data-span')) || 1;

            if ($sibs.length === 1) {
                var newSpan = current === 2 ? 1 : 2;
                $b.attr('data-span', newSpan).toggleClass('block-full', newSpan === 2);
            } else {
                // Full → demander à être seul
                $sibs.not($b).detach().each(function () {
                    var $row2 = $('<div>').addClass('riwa-studio-canvas-row').attr('data-row-id', rowUid());
                    $row2.append($(this));
                    $row.after($row2);
                });
                $b.attr('data-span', 2).addClass('block-full');
            }
            initSortableCanvas();
            syncLayout();
            schedulePreview();
        });

        // Bouton toggle
        $('#studio-canvas').on('click', '.studio-block-toggle', function (e) {
            e.stopPropagation();
            $(this).closest('.riwa-studio-block').trigger('dblclick');
        });
    }

    /* ================================================================ */
    /*  Aperçu iframe                                                    */
    /* ================================================================ */

    var previewTimer = null;

    function schedulePreview(immediate) {
        clearTimeout(previewTimer);
        if (immediate) {
            loadPreview();
        } else {
            previewTimer = setTimeout(loadPreview, 1200);
        }
    }

    function loadPreview() {
        var layout = State.layouts[State.docType] || { rows: [] };

        $('#studio-preview-loading').show();

        $.post(AJAX, {
            action   : 'riwa_studio_preview',
            nonce    : NONCE,
            doc_type : State.docType,
            layout   : JSON.stringify(layout),
        }, function (resp) {
            $('#studio-preview-loading').hide();
            if (resp.success) {
                var fullHtml = '<!DOCTYPE html><html><head>'
                    + '<meta charset="UTF-8">'
                    + '<style>body{margin:16px;font-family:sans-serif;font-size:13px;}</style>'
                    + '</head><body>' + resp.data.html + '</body></html>';
                $('#studio-preview-iframe').attr('srcdoc', fullHtml);
            }
        }).fail(function () {
            $('#studio-preview-loading').hide();
        });
    }

    /* ================================================================ */
    /*  Sauvegarde                                                        */
    /* ================================================================ */

    function saveAll() {
        var layout   = State.layouts[State.docType] || domToLayout();
        var settings = collectSettings();

        var $btn = $('#studio-save-btn').prop('disabled', true);

        // Sauvegarde layout
        $.post(AJAX, {
            action   : 'riwa_studio_save_layout',
            nonce    : NONCE,
            doc_type : State.docType,
            layout   : JSON.stringify(layout),
        });

        // Sauvegarde settings
        $.post(AJAX, {
            action   : 'riwa_studio_save_settings',
            nonce    : NONCE,
            settings : JSON.stringify(settings),
        }, function (resp) {
            $btn.prop('disabled', false);
            if (resp.success) {
                markClean();
                toast('Sauvegardé avec succès !', 'success');
            } else {
                toast('Erreur lors de la sauvegarde', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            toast('Erreur réseau', 'error');
        });
    }

    function collectSettings() {
        var s = {};
        $('.riwa-studio-setting-field').each(function () {
            s[$(this).data('key')] = $(this).val();
        });
        s.primary_color = $('#studio-primary-color').val();
        s.font_family   = $('#studio-font-family').val();
        return s;
    }

    /* ================================================================ */
    /*  Type de document                                                  */
    /* ================================================================ */

    function switchDocType(type) {
        if (State.dirty) {
            syncLayout(); // Sauvegarder dans State avant de changer
        }
        State.docType = type;
        if (!State.layouts[type]) {
            State.layouts[type] = JSON.parse(JSON.stringify(CFG.layouts[type] || { rows: [] }));
        }
        renderCanvas();
        schedulePreview(true);
    }

    /* ================================================================ */
    /*  Réinitialiser le layout                                           */
    /* ================================================================ */

    function resetLayout() {
        if (!confirm('Réinitialiser le layout de ce document aux valeurs par défaut ?')) return;

        $.post(AJAX, {
            action   : 'riwa_studio_reset_layout',
            nonce    : NONCE,
            doc_type : State.docType,
        }, function (resp) {
            if (resp.success) {
                State.layouts[State.docType] = resp.data.layout;
                renderCanvas();
                schedulePreview(true);
                toast('Layout réinitialisé', 'info');
            }
        });
    }

    /* ================================================================ */
    /*  Initialisation                                                    */
    /* ================================================================ */

    function init() {
        // Onglet type de document
        $(document).on('click', '.riwa-studio-doc-btn', function () {
            $('.riwa-studio-doc-btn').removeClass('active');
            $(this).addClass('active');
            switchDocType($(this).attr('data-type'));
        });

        // Thèmes prédéfinis
        $(document).on('click', '.riwa-studio-theme-btn', function () {
            var color = $(this).attr('data-color');
            var font  = $(this).attr('data-font');
            $('#studio-primary-color').val(color).trigger('change');
            $('#studio-font-family').val(font).trigger('change');
            if ($.fn.wpColorPicker) {
                $('#studio-primary-color').wpColorPicker('color', color);
            }
            markDirty();
            schedulePreview();
        });

        // Color picker WordPress
        if ($.fn.wpColorPicker) {
            $('#studio-primary-color').wpColorPicker({
                change: function (e, ui) {
                    markDirty();
                    schedulePreview();
                },
            });
        }

        // Changements de settings
        $(document).on('change input', '.riwa-studio-setting-field, #studio-font-family', function () {
            markDirty();
            schedulePreview();
        });

        // Bouton aperçu / refresh
        $('#studio-preview-btn, #studio-refresh-preview').on('click', function () {
            loadPreview();
        });

        // Sauvegarde
        $('#studio-save-btn').on('click', saveAll);

        // Réinitialisation
        $('#studio-reset-btn').on('click', resetLayout);

        // Media uploader pour le logo
        $(document).on('click', '#studio-logo_url-picker', function (e) {
            e.preventDefault();
            var frame = wp.media({ title: 'Choisir un logo', button: { text: 'Utiliser' }, multiple: false });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $('#studio-logo_url').val(att.url).trigger('input');
            });
            frame.open();
        });

        // Rendu initial
        renderCanvas();
        initPalette();
        initBlockInteractions();
        initPropsDrawer();

        // Aperçu initial après délai
        schedulePreview();
    }

    /* ================================================================ */
    /*  Utilitaires                                                       */
    /* ================================================================ */

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    $(document).ready(init);

}(jQuery));

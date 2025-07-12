/**
 * JavaScript pour l'interface d'administration PDF - Version moderne
 */

jQuery(document).ready(function($) {
    
    // Navigation entre les panneaux
    $('.riwa-nav-item').on('click', function(e) {
        e.preventDefault();
        
        var targetSection = $(this).data('section');
        
        // Mettre à jour la navigation active
        $('.riwa-nav-item').removeClass('active');
        $(this).addClass('active');
        
        // Afficher la section correspondante
        $('.riwa-section').removeClass('active');
        $('#' + targetSection + '-section').addClass('active');
        
        // Mettre à jour l'URL sans recharger la page
        if (history.pushState) {
            history.pushState(null, null, '#' + targetSection);
        }
    });
    
    // Gestion de l'historique du navigateur
    $(window).on('popstate', function() {
        var hash = window.location.hash.substring(1) || 'general';
        $('.riwa-nav-item[data-section="' + hash + '"]').click();
    });
    
    // Initialiser la section active depuis l'URL
    var initialSection = window.location.hash.substring(1) || 'general';
    $('.riwa-nav-item[data-section="' + initialSection + '"]').click();
    
    // Initialiser les sélecteurs de couleurs
    if (typeof wp !== 'undefined' && wp.colorPicker) {
        $('input[type="color"]').each(function() {
            $(this).wpColorPicker({
                change: function(event, ui) {
                    updatePreview();
                }
            });
        });
    }
    
    // Gestionnaire pour le bouton de sélection de logo
    if (typeof wp !== 'undefined' && wp.media) {
        $('#logo_url').after('<button type="button" class="riwa-btn riwa-btn-secondary" id="select-logo">Sélectionner un logo</button>');
        
        $('#select-logo').on('click', function(e) {
            e.preventDefault();
            
            var image = wp.media({
                title: 'Sélectionner un logo',
                multiple: false,
                library: {
                    type: 'image'
                }
            }).open();
            
            image.on('select', function() {
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                $('#logo_url').val(image_url);
                updatePreview();
            });
        });
    }
    
    // Gestionnaire pour l'enregistrement
    $('#save-all-settings').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Enregistrement...');
        
        // Soumettre le formulaire via AJAX
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'riwa_save_pdf_settings',
                formData: $('#riwa-pdf-form').serialize(),
                nonce: riwa_pdf_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Paramètres enregistrés avec succès !', 'success');
                } else {
                    showNotification('Erreur lors de l\'enregistrement: ' + response.data, 'error');
                }
            },
            error: function() {
                showNotification('Erreur de connexion lors de l\'enregistrement', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Gestionnaire pour la génération d'aperçu
    $('#generate-preview').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Génération...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'riwa_generate_pdf_preview',
                nonce: riwa_pdf_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#pdf-preview').html(response.data.preview);
                    showNotification('Aperçu généré avec succès !', 'success');
                } else {
                    $('#pdf-preview').html('<div class="riwa-error-message">Erreur: ' + response.data + '</div>');
                    showNotification('Erreur lors de la génération de l\'aperçu', 'error');
                }
            },
            error: function() {
                $('#pdf-preview').html('<div class="riwa-error-message">Erreur de connexion lors de la génération</div>');
                showNotification('Erreur de connexion', 'error');
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // Gestionnaire pour le test PDF
    $('#test-pdf').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Génération...');
        
        // Créer l'URL de test avec le nonce actuel
        var testUrl = riwa_pdf_admin.ajaxurl + '?action=riwa_test_pdf&nonce=' + riwa_pdf_admin.nonce;
        window.open(testUrl, '_blank');
        
        setTimeout(function() {
            button.prop('disabled', false).html(originalText);
            showNotification('Test PDF généré !', 'success');
        }, 2000);
    });
    
    // Gestionnaire pour le test PDF compact
    $('#test-pdf-compact').on('click', function() {
        var button = $(this);
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt"></span> Génération...');
        
        // Créer l'URL de test compact avec timestamp
        var testUrl = riwa_pdf_admin.ajaxurl + '?action=riwa_test_pdf_compact&nonce=' + riwa_pdf_admin.nonce + '&t=' + Date.now();
        window.open(testUrl, '_blank');
        
        setTimeout(function() {
            button.prop('disabled', false).html(originalText);
            showNotification('Test PDF compact généré !', 'success');
        }, 2000);
    });
    
    // Gestionnaire pour le diagnostic
    $('#show-diagnostic').on('click', function() {
        var button = $(this);
        var diagnosticSection = $('#diagnostic-section');
        var diagnosticContent = $('#diagnostic-content');
        
        if (diagnosticSection.is(':visible')) {
            diagnosticSection.hide();
            button.html('<span class="dashicons dashicons-info"></span> Diagnostic');
        } else {
            button.html('<span class="dashicons dashicons-update-alt"></span> Actualisation...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'riwa_pdf_diagnostic',
                    nonce: riwa_pdf_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        diagnosticContent.html(response.data.html);
                        diagnosticSection.show();
                        button.html('<span class="dashicons dashicons-info"></span> Masquer Diagnostic');
                        showNotification('Diagnostic terminé !', 'success');
                    } else {
                        showNotification('Erreur diagnostic: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showNotification('Erreur de connexion pour le diagnostic', 'error');
                },
                complete: function() {
                    if (!diagnosticSection.is(':visible')) {
                        button.html('<span class="dashicons dashicons-info"></span> Diagnostic');
                    }
                }
            });
        }
    });
    
    // Aperçu en temps réel
    function updatePreview() {
        // Mettre à jour l'aperçu des couleurs
        var primaryColor = $('#primary_color').val() || '#000000';
        var secondaryColor = $('#secondary_color').val() || '#666666';
        
        // Mettre à jour les éléments visuels
        $('.riwa-nav-item.active').css('border-left-color', primaryColor);
        $('.riwa-section-header h2').css('color', primaryColor);
        
        // Mettre à jour les boutons
        $('.riwa-btn-primary').css('background-color', primaryColor);
        $('.riwa-btn-primary').css('border-color', primaryColor);
    }
    
    // Écouter les changements pour l'aperçu en temps réel
    $('input, select, textarea').on('input change', function() {
        updatePreview();
    });
    
    // Initialiser l'aperçu
    updatePreview();
    
    // Validation en temps réel
    function validateField(field) {
        var $field = $(field);
        var value = $field.val().trim();
        var fieldName = $field.attr('id');
        var isValid = true;
        var errorMessage = '';
        
        // Supprimer les messages d'erreur précédents
        $field.siblings('.riwa-field-error').remove();
        $field.removeClass('riwa-field-error');
        
        // Validation spécifique par champ
        switch(fieldName) {
            case 'company_name':
                if (!value) {
                    isValid = false;
                    errorMessage = 'Le nom de l\'entreprise est requis';
                }
                break;
                
            case 'company_email':
                if (value && !isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'Format d\'email invalide';
                }
                break;
                
            case 'logo_url':
                if (value && !isValidUrl(value)) {
                    isValid = false;
                    errorMessage = 'URL invalide';
                }
                break;
        }
        
        // Afficher l'erreur si nécessaire
        if (!isValid) {
            $field.addClass('riwa-field-error');
            $field.after('<div class="riwa-field-error">' + errorMessage + '</div>');
        }
        
        return isValid;
    }
    
    // Validation des emails
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Validation des URLs
    function isValidUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }
    
    // Validation en temps réel
    $('input, textarea').on('blur', function() {
        validateField(this);
    });
    
    // Système de notifications
    function showNotification(message, type) {
        var notification = $('<div class="riwa-notification riwa-notification-' + type + '">' + message + '</div>');
        
        $('body').append(notification);
        
        // Animation d'entrée
        setTimeout(function() {
            notification.addClass('riwa-notification-show');
        }, 100);
        
        // Auto-suppression
        setTimeout(function() {
            notification.removeClass('riwa-notification-show');
            setTimeout(function() {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Raccourcis clavier
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S pour sauvegarder
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            $('#save-all-settings').click();
        }
        
        // Échap pour fermer les notifications
        if (e.key === 'Escape') {
            $('.riwa-notification').remove();
        }
    });
    
    // Amélioration de l'UX - Focus automatique
    $('.riwa-nav-item').on('click', function() {
        setTimeout(function() {
            $('.riwa-section.active input:first, .riwa-section.active textarea:first').focus();
        }, 100);
    });
    
    // Auto-sauvegarde périodique (optionnel)
    var autoSaveInterval = setInterval(function() {
        if ($('input, textarea, select').filter(function() {
            return $(this).data('changed');
        }).length > 0) {
            // Il y a des changements non sauvegardés
            // Changements détectés - auto-sauvegarde disponible
        }
    }, 30000); // Vérifier toutes les 30 secondes
    
    // Marquer les champs comme modifiés
    $('input, textarea, select').on('input change', function() {
        $(this).data('changed', true);
    });
    
    // Réinitialiser le flag après sauvegarde
    $('#save-all-settings').on('click', function() {
        $('input, textarea, select').data('changed', false);
    });
    
    // Styles CSS pour les notifications et erreurs
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .riwa-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
                border-radius: 6px;
                color: white;
                font-weight: 500;
                z-index: 999999;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                max-width: 300px;
            }
            
            .riwa-notification-show {
                transform: translateX(0);
            }
            
            .riwa-notification-success {
                background: #00a32a;
            }
            
            .riwa-notification-error {
                background: #d63638;
            }
            
            .riwa-field-error {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 1px #d63638 !important;
            }
            
            .riwa-field-error + .riwa-field-error {
                color: #d63638;
                font-size: 12px;
                margin-top: 0.25rem;
            }
            
            .riwa-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            
            .riwa-btn:disabled .dashicons {
                animation: riwa-spin 1s linear infinite;
            }
            
            @keyframes riwa-spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `)
        .appendTo('head');
}); 
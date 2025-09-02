/**
 * LEGACY COOKIE SUGGESTIONS LOGIC - PRESERVED FOR REFACTORING
 * TODO: This file contains the old cookie suggestions logic that needs to be refactored
 * TODO: Move working functions to the new admin structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

(function() {
    //console.log("okokokokinitCookieSuggestions");
    function initCookieSuggestions() {
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery not loaded yet, retrying...');
            setTimeout(initCookieSuggestions, 100);
            return;
        }
        
        if (typeof WPCCM_COOKIE_SUGGEST === 'undefined') {
            console.warn('WPCCM_COOKIE_SUGGEST global not found');
            return;
        }
        
        jQuery(document).ready(function($) {
    
    const texts = WPCCM_COOKIE_SUGGEST.texts || {};

    $('#wpccm-suggest-cookies-btn').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        $.ajax({
            url: WPCCM_COOKIE_SUGGEST.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_suggest_purge_cookies',
                _wpnonce: WPCCM_COOKIE_SUGGEST.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCookieSuggestionsInline(response.data);
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('AJAX error occurred');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });

    function renderCookieSuggestionsInline(suggestions) {
        if (!suggestions.length) {
            $('#wpccm-cookie-suggestions-inline').html('<p>No cookie suggestions found.</p>');
            return;
        }

        let html = '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">';
        html += '<h4 style="margin: 0 0 10px 0;">' + (texts.suggest_purge_cookies || 'Cookie Suggestions') + '</h4>';
        html += '<table class="widefat fixed striped" style="margin-bottom: 15px;">';
        html += '<thead><tr>';
        html += '<th>' + (texts.cookie || 'Cookie') + '</th>';
        html += '<th>' + (texts.suggested_category || 'Category') + '</th>';
        html += '<th>' + (texts.auto_detected || 'Reason') + '</th>';
        html += '<th style="width: 80px;">' + (texts.should_purge || 'Add?') + '</th>';
        html += '</tr></thead><tbody>';

        suggestions.forEach(function(item) {
            const categoryText = getCategoryDisplayText(item.category);
            
            html += '<tr>';
            html += '<td><code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">' + escapeHtml(item.name) + '</code></td>';
            html += '<td>' + categoryText + '</td>';
            html += '<td style="font-size: 12px; color: #666;">' + escapeHtml(item.reason) + '</td>';
            html += '<td><input type="checkbox" class="wpccm-suggestion-cb" data-cookie="' + escapeHtml(item.name) + '" checked></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<button type="button" class="button button-primary" id="wpccm-add-selected-cookies">' + (texts.update_purge_list || 'Add Selected Cookies') + '</button>';
        html += ' <button type="button" class="button" id="wpccm-cancel-suggestions">Cancel</button>';
        html += '</div>';

        $('#wpccm-cookie-suggestions-inline').html(html);
        
        // Bind buttons
        $('#wpccm-add-selected-cookies').on('click', addSelectedCookies);
        $('#wpccm-cancel-suggestions').on('click', function() {
            $('#wpccm-cookie-suggestions-inline').empty();
        });
    }

    function addSelectedCookies() {
        const checkboxes = $('.wpccm-suggestion-cb:checked');
        const selectedCookies = [];
        
        checkboxes.each(function() {
            selectedCookies.push($(this).data('cookie'));
        });
        
        if (!selectedCookies.length) {
            alert('No cookies selected');
            return;
        }

        // TODO: Implement addSelectedCookies functionality
        //console.log('Selected cookies:', selectedCookies);
    }

    // TODO: REFACTORING NOTES:
    // 1. jQuery event handlers -> Move to admin/events.js
    // 2. AJAX calls -> Move to admin/api.js
    // 3. Cookie suggestion functions -> Move to admin/cookie-suggester.js
    // 4. Table rendering functions -> Move to admin/table-renderer.js
    // 5. UI interaction functions -> Move to admin/ui-handler.js

    // TODO: DEPENDENCIES TO CHECK:
    // - jQuery library
    // - WPCCM_COOKIE_SUGGEST global object
    // - WordPress AJAX endpoints
    // - DOM manipulation functions

    // TODO: INTEGRATION POINTS:
    // - Replace old cookie suggestions with new admin system
    // - Update suggestion handling throughout the codebase
    // - Maintain backward compatibility during transition
    // - Test all functionality after refactoring

    // TODO: MODULES TO CREATE:
    // - admin/events.js - Event handling
    // - admin/api.js - AJAX communication
    // - admin/cookie-suggester.js - Cookie suggestions
    // - admin/table-renderer.js - Table rendering
    // - admin/ui-handler.js - UI interactions

        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCookieSuggestions);
    } else {
        initCookieSuggestions();
    }

})();

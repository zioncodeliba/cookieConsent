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
        
        // Get current cookies from input
        const currentInput = $('#wpccm-purge-cookies-input');
        const currentValue = currentInput.val().trim();
        const currentCookies = currentValue ? currentValue.split(',').map(c => c.trim()).filter(c => c) : [];
        
        // Add new cookies (avoid duplicates)
        selectedCookies.forEach(function(cookie) {
            if (currentCookies.indexOf(cookie) === -1) {
                currentCookies.push(cookie);
            }
        });
        
        // Update input field
        currentInput.val(currentCookies.join(', '));
        
        // Clear suggestions
        $('#wpccm-cookie-suggestions-inline').empty();
        
        // Show success message
        const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Added ' + selectedCookies.length + ' cookies to the purge list!</p></div>');
        currentInput.after(successMsg);
        
        // Auto-dismiss success message
        setTimeout(function() {
            successMsg.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    function getCategoryDisplayText(category) {
        const categoryTexts = {
            'necessary': texts.necessary || 'Necessary',
            'functional': texts.functional || 'Functional',
            'performance': texts.performance || 'Performance',
            'analytics': texts.analytics || 'Analytics',
            'advertisement': texts.advertisement || 'Advertisement',
            'others': texts.others || 'Others',
            'social': 'Social Media'
        };
        return categoryTexts[category] || category;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
        }); // End jQuery document ready
    } // End initCookieSuggestions function
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCookieSuggestions);
    } else {
        initCookieSuggestions();
    }
})(); // End IIFE

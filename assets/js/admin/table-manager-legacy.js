/**
 * LEGACY TABLE MANAGER LOGIC - PRESERVED FOR REFACTORING
 * TODO: This file contains the old table management logic that needs to be refactored
 * TODO: Move working functions to the new admin structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

(function() {
    console.log("okokokokinitTableManager");
    function initTableManager() {
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery not loaded yet, retrying...');
            setTimeout(initTableManager, 100);
            return;
        }
        
        if (typeof WPCCM_TABLE === 'undefined') {
            console.warn('WPCCM_TABLE global not found');
            return;
        }
        
        jQuery(document).ready(function($) {
    
    const texts = WPCCM_TABLE.texts || {};

    // Initialize cookies dropdown functionality
    updateCookiesFromDropdown();
    
    // Initialize dropdowns with saved data
    initializeSavedCookieSelections();
    
    // Purge tab: save only purge cookies via AJAX
    $(document).on('click', 'input[name="save_purge_settings"]', function(e) {
        e.preventDefault();
        const button = $(this);
        const original = button.val();
        button.prop('disabled', true).val(texts.loading || 'Saving...');
        
        // Ensure hidden field reflects current table state
        updateCookieData();
        const cookiesJson = $('#wpccm-cookies-data').val() || '[]';
        console.log('WPCCM Debug: Saving purge cookies JSON:', cookiesJson);
        
        $.post(WPCCM_TABLE.ajaxUrl, {
            action: 'wpccm_save_purge_cookies',
            cookies_json: cookiesJson,
            _wpnonce: WPCCM_TABLE.nonce
        }).done(function(resp){
            if (resp && resp.success) {
                const msg = $('<div class="notice notice-success is-dismissible" style="margin:10px 0"><p>הגדרות מחיקת עוגיות נשמרו (' + (resp.data && resp.data.saved || 0) + ')</p></div>');
                $('#wpccm-cookie-purge-table').prepend(msg);
                setTimeout(function(){ msg.fadeOut(function(){ $(this).remove(); }); }, 3000);
            } else {
                alert('Error saving purge cookies');
            }
        }).fail(function(){
            alert('AJAX error saving purge cookies');
        }).always(function(){
            button.prop('disabled', false).val(original);
        });
    });

    // Handle Mapping Table Management
    $('#wpccm-add-handle').on('click', function() {
        addHandleRow();
    });

    $(document).on('click', '.remove-handle', function() {
        $(this).closest('tr').remove();
        updateHandleMapping();
    });

    $(document).on('change', '.handle-input, .category-select, .cookies-input-manual', function() {
        updateHandleMapping();
    });

    // Cookie Purge Table Management
    $('#wpccm-add-cookie').on('click', function() {
        addCookieRow();
    });

    $(document).on('click', '.remove-cookie', function() {
        console.log('WPCCM Debug: remove-cookie clicked');
        $(this).closest('tr').remove();
        console.log('WPCCM Debug: Row removed, calling updateCookieData');
        updateCookieData();
    });

    $(document).on('change', '.cookie-input', function() {
        // Auto-update category when cookie name changes
        const cookieName = $(this).val().trim();
        const categorySelect = $(this).closest('tr').find('.category-select');
        
        if (cookieName && categorySelect.val() === '') {
            // Try to get category from handle mapping first
            let category = getCategoryFromMapping(cookieName);
            
            // If not found in mapping, use pattern detection
            if (!category) {
                category = detectCookieCategory(cookieName);
            }
            
            if (category) {
                categorySelect.val(category);
            }
        }
        
        updateCookieData();
    });

    // TODO: REFACTORING NOTES:
    // 1. jQuery event handlers -> Move to admin/events.js
    // 2. AJAX calls -> Move to admin/api.js
    // 3. Table manipulation functions -> Move to admin/table-utils.js
    // 4. Cookie management functions -> Move to admin/cookie-manager.js
    // 5. UI update functions -> Move to admin/ui-updater.js

    // TODO: DEPENDENCIES TO CHECK:
    // - jQuery library
    // - WPCCM_TABLE global object
    // - WordPress AJAX endpoints
    // - DOM manipulation functions

    // TODO: INTEGRATION POINTS:
    // - Replace old table management with new admin system
    // - Update event handlers throughout the codebase
    // - Maintain backward compatibility during transition
    // - Test all functionality after refactoring

    // TODO: MODULES TO CREATE:
    // - admin/events.js - Event handling
    // - admin/api.js - AJAX communication
    // - admin/table-utils.js - Table manipulation
    // - admin/cookie-manager.js - Cookie operations
    // - admin/ui-updater.js - UI updates

        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableManager);
    } else {
        initTableManager();
    }

})();

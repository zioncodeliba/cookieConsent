/**
 * LEGACY HANDLE MAPPER LOGIC - PRESERVED FOR REFACTORING
 * TODO: This file contains the old handle mapping logic that needs to be refactored
 * TODO: Move working functions to the new admin structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

(function() {
    //console.log("okokokokinitHandleMapper");
    function initHandleMapper() {
        if (typeof jQuery === 'undefined') {
            console.warn('jQuery not loaded yet, retrying...');
            setTimeout(initHandleMapper, 100);
            return;
        }
        
        if (typeof WPCCM_MAPPER === 'undefined') {
            // console.warn('WPCCM_MAPPER global not found');
            return;
        }
        
        jQuery(document).ready(function($) {
    
    const texts = WPCCM_MAPPER.texts || {};
    let currentHandles = [];

    $('#wpccm-scan-handles').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        $.ajax({
            url: WPCCM_MAPPER.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_scan_handles',
                _wpnonce: WPCCM_MAPPER.nonce
            },
            success: function(response) {
                if (response.success) {
                    currentHandles = response.data;
                    renderHandlesTable(response.data);
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

    function renderHandlesTable(handles) {
        if (!handles.length) {
            $('#wpccm-handles-table').html('<p>No handles found.</p>');
            return;
        }

        let html = '<table class="widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>' + (texts.handle || 'Handle') + '</th>';
        html += '<th>' + (texts.type || 'Type') + '</th>';
        html += '<th>' + (texts.suggested_category || 'Suggested Category') + '</th>';
        html += '<th>' + (texts.select_category || 'Select Category') + '</th>';
        html += '</tr></thead><tbody>';

        handles.forEach(function(item) {
            const categoryText = getCategoryDisplayText(item.suggested);
            
            html += '<tr>';
            html += '<td><code>' + escapeHtml(item.handle) + '</code></td>';
            html += '<td>' + escapeHtml(item.type) + '</td>';
            html += '<td>' + categoryText + '</td>';
            html += '<td>' + renderCategorySelect(item.handle, item.suggested) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '<div style="margin-top: 15px;">';
        html += '<button type="button" class="button button-primary" id="wpccm-save-mapping">' + (texts.save_mapping || 'Save Mapping') + '</button>';
        html += '</div>';

        $('#wpccm-handles-table').html(html);
        
        // Bind save button
        $('#wpccm-save-mapping').on('click', saveHandleMapping);
    }

    function renderCategorySelect(handle, suggested) {
        const categories = [
            { value: 'none', text: texts.none || 'None' },
            { value: 'necessary', text: texts.necessary || 'Necessary' },
            { value: 'functional', text: texts.functional || 'Functional' },
            { value: 'performance', text: texts.performance || 'Performance' },
            { value: 'analytics', text: texts.analytics || 'Analytics' },
            { value: 'advertisement', text: texts.advertisement || 'Advertisement' },
            { value: 'others', text: texts.others || 'Others' }
        ];

        let html = '<select class="handle-category-select" data-handle="' + escapeHtml(handle) + '">';
        
        categories.forEach(function(cat) {
            const selected = cat.value === suggested ? ' selected' : '';
            html += '<option value="' + cat.value + '"' + selected + '>' + cat.text + '</option>';
        });
        
        html += '</select>';
        return html;
    }

    // TODO: REFACTORING NOTES:
    // 1. jQuery event handlers -> Move to admin/events.js
    // 2. AJAX calls -> Move to admin/api.js
    // 3. Table rendering functions -> Move to admin/table-renderer.js
    // 4. Category selection functions -> Move to admin/category-manager.js
    // 5. Handle mapping functions -> Move to admin/handle-manager.js

    // TODO: DEPENDENCIES TO CHECK:
    // - jQuery library
    // - WPCCM_MAPPER global object
    // - WordPress AJAX endpoints
    // - DOM manipulation functions

    // TODO: INTEGRATION POINTS:
    // - Replace old handle mapping with new admin system
    // - Update event handlers throughout the codebase
    // - Maintain backward compatibility during transition
    // - Test all functionality after refactoring

    // TODO: MODULES TO CREATE:
    // - admin/events.js - Event handling
    // - admin/api.js - AJAX communication
    // - admin/table-renderer.js - Table rendering
    // - admin/category-manager.js - Category management
    // - admin/handle-manager.js - Handle operations

        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHandleMapper);
    } else {
        initHandleMapper();
    }

})();

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
            html += '<option value="' + escapeHtml(cat.value) + '"' + selected + '>' + escapeHtml(cat.text) + '</option>';
        });
        
        html += '</select>';
        return html;
    }

    function getCategoryDisplayText(category) {
        const categoryTexts = {
            'necessary': texts.necessary || 'Necessary',
            'functional': texts.functional || 'Functional',
            'performance': texts.performance || 'Performance',
            'analytics': texts.analytics || 'Analytics',
            'advertisement': texts.advertisement || 'Advertisement',
            'others': texts.others || 'Others',
            'uncategorized': texts.uncategorized || 'Uncategorized'
        };
        
        return categoryTexts[category] || category;
    }

    function saveHandleMapping() {
        const mapping = {};
        
        $('.handle-category-select').each(function() {
            const handle = $(this).data('handle');
            const category = $(this).val();
            if (category && category !== 'none') {
                mapping[handle] = category;
            }
        });

        const button = $('#wpccm-save-mapping');
        const originalText = button.text();
        button.text(texts.loading || 'Loading...').prop('disabled', true);

        $.ajax({
            url: WPCCM_MAPPER.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_save_handle_mapping',
                mapping: mapping,
                _wpnonce: WPCCM_MAPPER.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Saved ' + response.data.count + ' handle mappings!');
                    
                    // Update the manual textarea to reflect changes
                    updateManualTextarea(mapping);
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
    }

    function updateManualTextarea(mapping) {
        const pairs = [];
        for (const handle in mapping) {
            pairs.push(handle + ':' + mapping[handle]);
        }
        $('textarea[name="wpccm_options[map]"]').val(pairs.join(', '));
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
        }); // End jQuery document ready
    } // End initHandleMapper function
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHandleMapper);
    } else {
        initHandleMapper();
    }
})(); // End IIFE

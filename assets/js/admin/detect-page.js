/**
 * Admin Detection Page JavaScript
 * Handles the automatic detection page functionality
 */

(function($) {
    'use strict';
    
    // //console.log('=== DETECT PAGE JS LOADED ===');
    // //console.log('jQuery available:', typeof $ !== 'undefined');
    // //console.log('jQuery version:', $ ? $.fn.jquery : 'NOT AVAILABLE');

    // Configuration
    const CONFIG = {
        selectors: {
            scanUrl: '#wpccm-scan-url',
            scanButton: '#wpccm-scan-button',
            scanIframe: '#wpccm-scan-iframe',
            scanIframeContainer: '#wpccm-scan-iframe-container',
            resultsTbody: '#wpccm-results-tbody',
            selectAllCheckbox: '#wpccm-select-all-checkbox',
            selectAllButton: '#wpccm-select-all',
            deselectAllButton: '#wpccm-deselect-all',
            saveSelectedButton: '#wpccm-save-selected',
            noResultsRow: '#wpccm-no-results',
            spinner: '.spinner'
        },
        messageType: 'CC_DETECT_RESULTS'
    };

    // State
    let detectedItems = {};
    let isScanning = false;

    /**
     * Initialize the detection page
     */
    function init() {
        //console.log('=== INIT FUNCTION CALLED ===');
        //console.log('Initializing detection page...');
        
        try {
            // Check if localized data is available
            if (typeof wpccmDetectData === 'undefined') {
                console.error('wpccmDetectData is not available');
                updateDebugStatus('Error: wpccmDetectData not available');
                return;
            }
            
            //console.log('wpccmDetectData:', wpccmDetectData);
            //console.log('typeof wpccmDetectData:', typeof wpccmDetectData);
            //console.log('AJAX URL:', wpccmDetectData.ajaxUrl);
            //console.log('Nonce:', wpccmDetectData.nonce);
            
            // Update debug status
            updateDebugStatus('JavaScript loaded, initializing...');
            
            bindEvents();
            setupMessageListener();
            //console.log('WPCCM Detection Page initialized');
            
            // Update debug status
            updateDebugStatus('Initialization complete');
        } catch (error) {
            console.error('Error in init function:', error);
            updateDebugStatus('Error: ' + error.message);
        }
    }
    
    /**
     * Update debug status
     */
    function updateDebugStatus(message) {
        //console.log('=== UPDATE DEBUG STATUS ===');
        //console.log('Message:', message);
        
        const debugStatus = document.getElementById('wpccm-debug-status');
        //console.log('Debug status element found:', !!debugStatus);
        
        if (debugStatus) {
            debugStatus.textContent = message;
            //console.log('Debug status updated successfully');
        } else {
            console.warn('Debug status element not found');
        }
        
        //console.log('Debug Status:', message);
    }
    
    /**
     * Guess category from heuristics (client-side fallback)
     */
    function guessCategoryFromHeuristics(item) {
        const src = (item.src || '').toLowerCase();
        const domain = (item.domain || '').toLowerCase();
        const handle = (item.handle || '').toLowerCase();
        
        // Analytics
        if (src.includes('google-analytics') || src.includes('googletagmanager') || 
            src.includes('gtag') || src.includes('analytics') ||
            domain.includes('google-analytics') || domain.includes('googletagmanager') ||
            handle.includes('ga') || handle.includes('analytics')) {
            return 'analytics';
        }
        
        // Marketing
        if (src.includes('facebook') || src.includes('doubleclick') || 
            src.includes('googleadservices') || src.includes('pixel') ||
            domain.includes('facebook') || domain.includes('doubleclick') ||
            handle.includes('facebook') || handle.includes('pixel') ||
            src.includes('youtube') || domain.includes('youtube')) {
            return 'marketing';
        }
        
        // Functional
        if (src.includes('stripe') || src.includes('paypal') || 
            src.includes('maps') || src.includes('chat') ||
            domain.includes('stripe') || domain.includes('paypal') ||
            handle.includes('form') || handle.includes('chat')) {
            return 'functional';
        }
        
        // Performance
        if (src.includes('cloudflare') || src.includes('cdn') || 
            src.includes('optimize') || src.includes('lazy') ||
            domain.includes('cloudflare') || domain.includes('cdn')) {
            return 'performance';
        }
        
        // Necessary
        if (src.includes('wp-') || src.includes('wordpress') || 
            src.includes('recaptcha') || src.includes('security') ||
            domain.includes('wp-') || handle.includes('wp-')) {
            return 'necessary';
        }
        
        return null;
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        //console.log('=== BINDING EVENTS ===');
        //console.log('Binding events...');
        
        // Scan button
        const scanButton = $(CONFIG.selectors.scanButton);
        //console.log('Scan button found:', scanButton.length);
        //console.log('Scan button element:', scanButton[0]);
        //console.log('Scan button HTML:', scanButton.prop('outerHTML'));
        
        if (scanButton.length > 0) {
            scanButton.on('click', function(e) {
                //console.log('=== SCAN BUTTON CLICK EVENT ===');
                //console.log('Event:', e);
                //console.log('This:', this);
                handleScan();
            });
            //console.log('Scan button click event bound successfully');
        } else {
            console.error('Scan button not found!');
        }
        
        // Select all checkbox
        const selectAllCheckbox = $(CONFIG.selectors.selectAllCheckbox);
        //console.log('Select all checkbox found:', selectAllCheckbox.length);
        selectAllCheckbox.on('change', handleSelectAll);
        
        // Select all button
        const selectAllButton = $(CONFIG.selectors.selectAllButton);
        //console.log('Select all button found:', selectAllButton.length);
        selectAllButton.on('click', selectAllItems);
        
        // Deselect all button
        const deselectAllButton = $(CONFIG.selectors.deselectAllButton);
        //console.log('Deselect all button found:', deselectAllButton.length);
        deselectAllButton.on('click', deselectAllItems);
        
        // Save selected button
        const saveSelectedButton = $(CONFIG.selectors.saveSelectedButton);
        //console.log('Save selected button found:', saveSelectedButton.length);
        saveSelectedButton.on('click', saveSelectedItems);
        
        // Individual item checkboxes
        $(document).on('change', '.wpccm-item-checkbox', handleItemCheckboxChange);
        
        // Category select changes
        $(document).on('change', '.wpccm-category-select', handleCategoryChange);
        
        // Delete mapping buttons
        $(document).on('click', '.wpccm-delete-mapping', handleDeleteMapping);
        
        // Add mapping button
        $(document).on('click', '.wpccm-add-mapping-row', handleAddMappingRow);
        
        // Remove mapping button
        $(document).on('click', '.wpccm-remove-mapping', handleRemoveMappingRow);
        
        //console.log('Events binding complete');
    }

    /**
     * Setup message listener for iframe communication
     */
    function setupMessageListener() {
        //console.log('Setting up message listener...');
        //console.log('Listening for message type:', CONFIG.messageType);
        
        window.addEventListener('message', function(event) {
            //console.log('=== MESSAGE RECEIVED ===');
            //console.log('Event:', event);
            //console.log('Origin:', event.origin);
            //console.log('Data:', event.data);
            //console.log('Data type:', typeof event.data);
            //console.log('=======================');
            
            // Since iframe now has allow-same-origin, we can check origin
            if (event.origin !== window.location.origin) {
                //console.log('Origin mismatch, ignoring message from:', event.origin);
                return;
            }
            
            // Verify the message structure
            if (!event.data || typeof event.data !== 'object') {
                //console.log('Invalid message format, ignoring');
                return;
            }
            
            // Check message type
            if (event.data.type === CONFIG.messageType) {
                //console.log('Valid detection message received:', event.data);
                handleDetectionResults(event.data.items);
            } else {
                //console.log('Message type mismatch, ignoring:', event.data.type);
            }
        });
        
        //console.log('Message listener setup complete');
    }

    /**
     * Handle scan button click
     */
    function handleScan() {
        //console.log('=== SCAN BUTTON CLICKED ===');
        //console.log('Scan button clicked!');
        
        if (isScanning) {
            //console.log('Already scanning, ignoring click');
            return;
        }

        const url = $(CONFIG.selectors.scanUrl).val().trim();
        //console.log('URL to scan:', url);
        //console.log('URL length:', url.length);
        
        if (!url) {
            //console.log('No URL entered, showing alert');
            alert('Please enter a URL path to scan.');
            return;
        }

        // Validate URL is same-origin
        //console.log('Validating URL is same-origin...');
        if (!isSameOrigin(url)) {
            //console.log('URL is not same-origin, showing alert');
            alert('Please enter a URL path on the same site (e.g., /blog/, /).');
            return;
        }

        //console.log('URL validation passed, starting scan...');
        startScan(url);
    }

    /**
     * Check if URL is same-origin
     */
    function isSameOrigin(url) {
        //console.log('Checking if URL is same-origin:', url);
        //console.log('Current origin:', window.location.origin);
        
        // Remove leading slash if present
        const cleanUrl = url.replace(/^\/+/, '');
        //console.log('Clean URL:', cleanUrl);
        
        // Check if it's a relative path
        if (!cleanUrl.includes('://') && !cleanUrl.includes('//')) {
            //console.log('URL is relative path - allowing');
            return true;
        }
        
        // Check if it's the same domain
        try {
            const urlObj = new URL(url, window.location.origin);
            //console.log('URL object origin:', urlObj.origin);
            const isSame = urlObj.origin === window.location.origin;
            //console.log('Is same origin:', isSame);
            return isSame;
        } catch (e) {
            console.error('Error parsing URL:', e);
            return false;
        }
    }

    /**
     * Start the scanning process
     */
    function startScan(url) {
        //console.log('startScan called with URL:', url);
        //console.log('wpccmDetectData:', wpccmDetectData);
        //console.log('Site URL:', wpccmDetectData.siteUrl);
        
        isScanning = true;
        
        // Show loading state
        $(CONFIG.selectors.scanButton).prop('disabled', true).text('Scanning...');
        $(CONFIG.selectors.spinner).show();
        
        // Build full URL with detection flag
        const fullUrl = wpccmDetectData.siteUrl + url + (url.includes('?') ? '&' : '?') + 'cc_detect=1';
        //console.log('Full URL to load:', fullUrl);
        
        // Show iframe and load URL
        $(CONFIG.selectors.scanIframeContainer).show();
        $(CONFIG.selectors.scanIframe).attr('src', fullUrl);
        //console.log('Iframe src set to:', fullUrl);
        //console.log('Iframe element:', $(CONFIG.selectors.scanIframe)[0]);
        
        // Add iframe load event listener
        $(CONFIG.selectors.scanIframe).on('load', function() {
            //console.log('Iframe loaded successfully');
            //console.log('Iframe contentWindow:', this.contentWindow);
        });
        
        // Add iframe error event listener
        $(CONFIG.selectors.scanIframe).on('error', function() {
            console.error('Iframe failed to load');
        });
        
        // Set timeout to handle scan completion
        setTimeout(function() {
            if (isScanning) {
                //console.log('Timeout reached, completing scan');
                completeScan();
            }
        }, 10000); // 10 second timeout - increased for debugging
    }

    /**
     * Complete the scanning process
     */
    function completeScan() {
        isScanning = false;
        
        // Hide iframe
        $(CONFIG.selectors.scanIframeContainer).hide();
        
        // Restore button state
        $(CONFIG.selectors.scanButton).prop('disabled', false).text('🔍 Scan Page');
        $(CONFIG.selectors.spinner).hide();
        
        // Show completion message
        if (Object.keys(detectedItems).length > 0) {
            showMessage('Scan complete! Found ' + Object.keys(detectedItems).length + ' items.', 'success');
        } else {
            showMessage('Scan complete! No new items detected.', 'info');
        }
    }

    /**
     * Handle detection results from iframe
     */
    function handleDetectionResults(items) {
        if (!Array.isArray(items)) {
            return;
        }

        // Process items and create unique keys
        items.forEach(item => {
            if (item.type && (item.handle || item.src)) {
                const key = createItemKey(item);
                
                // Apply heuristics if not already set
                if (!item.suggested && (item.src || item.domain)) {
                    item.suggested = guessCategoryFromHeuristics(item);
                }
                
                // Set category to suggested if available, otherwise unassigned
                const initialCategory = item.suggested || 'unassigned';
                
                detectedItems[key] = {
                    ...item,
                    key: key,
                    selected: false,
                    category: initialCategory
                };
            }
        });

        // Store items via AJAX
        storeDetectedItems();
        
        // Update UI
        updateResultsTable();
        
        // Complete scan
        completeScan();
    }

    /**
     * Create unique key for item
     */
    function createItemKey(item) {
        if (item.handle) {
            return item.type + '|handle:' + item.handle;
        } else if (item.src) {
            return item.type + '|src:' + item.src;
        }
        return item.type + '|' + Date.now();
    }

    /**
     * Store detected items via AJAX
     */
    function storeDetectedItems() {
        //console.log('=== STORING DETECTED ITEMS ===');
        //console.log('Items to store:', detectedItems);
        //console.log('AJAX URL:', wpccmDetectData.ajaxUrl);
        //console.log('Nonce:', wpccmDetectData.nonce);
        
        //console.log('=== SENDING AJAX REQUEST ===');
        //console.log('URL:', wpccmDetectData.ajaxUrl);
        //console.log('Action:', 'cc_detect_store');
        //console.log('Nonce:', wpccmDetectData.nonce);
        //console.log('Items count:', Object.keys(detectedItems).length);
        
        $.ajax({
            url: wpccmDetectData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cc_detect_store',
                nonce: wpccmDetectData.nonce,
                items: JSON.stringify(detectedItems)
            },
            success: function(response) {
                //console.log('=== AJAX SUCCESS ===');
                //console.log('Response:', response);
                
                if (response.success) {
                    //console.log('Items stored successfully:', response.data.count);
                    showMessage('Items stored successfully!', 'success');
                } else {
                    console.error('Error storing items:', response.data);
                    if (response.data && response.data.code === 'forbidden') {
                        showMessage('Access denied. Please refresh the page and try again.', 'error');
                    } else {
                        showMessage('Error storing items: ' + (response.data || 'Unknown error'), 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('=== AJAX ERROR ===');
                console.error('XHR:', xhr);
                console.error('Status:', status);
                console.error('Error:', error);
                showMessage('Network error storing items: ' + error, 'error');
            }
        });
    }

    /**
     * Update the results table
     */
    function updateResultsTable() {
        //console.log('=== UPDATING RESULTS TABLE ===');
        //console.log('Detected items:', detectedItems);
        
        const tbody = $(CONFIG.selectors.resultsTbody);
        
        // Remove no results row if exists
        $(CONFIG.selectors.noResultsRow).remove();
        
        // Clear existing rows
        tbody.find('tr[data-key]').remove();
        
        // Only show results if we have detected items
        if (Object.keys(detectedItems).length === 0) {
            //console.log('No detected items, showing no results message');
            tbody.html(`
                <tr id="wpccm-no-results">
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <p>No items detected yet. Use the scanner above to detect scripts and iframes.</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        // Add new rows
        Object.values(detectedItems).forEach(item => {
            const row = createResultRow(item);
            tbody.append(row);
        });
        
        // Update select all checkbox
        updateSelectAllCheckbox();
        
        //console.log('Results table updated with', Object.keys(detectedItems).length, 'items');
    }

    /**
     * Create result row HTML
     */
    function createResultRow(item) {
        const typeClass = 'wpccm-type-' + item.type;
        const typeText = item.type.charAt(0).toUpperCase() + item.type.slice(1);
        
        const handleCell = item.handle ? '<code>' + escapeHtml(item.handle) + '</code>' : '<em>—</em>';
        const srcCell = item.src ? '<a href="' + escapeHtml(item.src) + '" target="_blank" title="' + escapeHtml(item.src) + '">' + truncateUrl(item.src) + '</a>' : '<em>—</em>';
        const domainCell = item.domain ? '<code>' + escapeHtml(item.domain) + '</code>' : '<em>—</em>';
        const ccCell = item.cc ? '<span class="wpccm-cc-badge wpccm-cc-' + escapeHtml(item.cc) + '">' + escapeHtml(item.cc.charAt(0).toUpperCase() + item.cc.slice(1)) + '</span>' : '<em>—</em>';
        
        // Suggested category cell
        const suggestedCell = item.suggested ? 
            '<span class="wpccm-suggested-badge wpccm-suggested-' + escapeHtml(item.suggested) + '">' + escapeHtml(item.suggested.charAt(0).toUpperCase() + item.suggested.slice(1)) + '</span>' : 
            '<em>Unassigned</em>';
        
        return `
            <tr data-key="${escapeHtml(item.key)}">
                <td>
                    <input type="checkbox" 
                           class="wpccm-item-checkbox" 
                           value="${escapeHtml(item.key)}" 
                           ${item.selected ? 'checked' : ''} />
                </td>
                <td>
                    <span class="wpccm-item-type ${typeClass}">
                        ${typeText}
                    </span>
                </td>
                <td>${handleCell}</td>
                <td>${srcCell}</td>
                <td>${domainCell}</td>
                <td>${ccCell}</td>
                <td>${suggestedCell}</td>
                <td>
                    <select class="wpccm-category-select">
                        <option value="unassigned" ${item.category === 'unassigned' ? 'selected' : ''}>Unassigned</option>
                        <option value="necessary" ${item.category === 'necessary' ? 'selected' : ''}>Necessary</option>
                        <option value="functional" ${item.category === 'functional' ? 'selected' : ''}>Functional</option>
                        <option value="analytics" ${item.category === 'analytics' ? 'selected' : ''}>Analytics</option>
                        <option value="performance" ${item.category === 'performance' ? 'selected' : ''}>Performance</option>
                        <option value="marketing" ${item.category === 'marketing' ? 'selected' : ''}>Marketing</option>
                        <option value="others" ${item.category === 'others' ? 'selected' : ''}>Others</option>
                    </select>
                </td>
            </tr>
        `;
    }

    /**
     * Handle select all checkbox change
     */
    function handleSelectAll() {
        const isChecked = $(CONFIG.selectors.selectAllCheckbox).is(':checked');
        
        $('.wpccm-item-checkbox').prop('checked', isChecked);
        
        // Update item selection state
        Object.keys(detectedItems).forEach(key => {
            detectedItems[key].selected = isChecked;
        });
    }

    /**
     * Select all items
     */
    function selectAllItems() {
        $(CONFIG.selectors.selectAllCheckbox).prop('checked', true).trigger('change');
    }

    /**
     * Deselect all items
     */
    function deselectAllItems() {
        $(CONFIG.selectors.selectAllCheckbox).prop('checked', false).trigger('change');
    }

    /**
     * Handle individual item checkbox change
     */
    function handleItemCheckboxChange() {
        const checkedCount = $('.wpccm-item-checkbox:checked').length;
        const totalCount = $('.wpccm-item-checkbox').length;
        
        // Update select all checkbox
        $(CONFIG.selectors.selectAllCheckbox).prop('indeterminate', checkedCount > 0 && checkedCount < totalCount);
        $(CONFIG.selectors.selectAllCheckbox).prop('checked', checkedCount === totalCount);
        
        // Update item selection state
        $('.wpccm-item-checkbox:checked').each(function() {
            const key = $(this).val();
            if (detectedItems[key]) {
                detectedItems[key].selected = true;
            }
        });
        
        $('.wpccm-item-checkbox:not(:checked)').each(function() {
            const key = $(this).val();
            if (detectedItems[key]) {
                detectedItems[key].selected = false;
            }
        });
    }

    /**
     * Handle category select change
     */
    function handleCategoryChange() {
        const select = $(this);
        const row = select.closest('tr');
        const key = row.data('key');
        const category = select.val();
        
        if (detectedItems[key]) {
            detectedItems[key].category = category;
        }
    }

    /**
     * Handle delete mapping button click
     */
    function handleDeleteMapping() {
        const button = $(this);
        const type = button.data('type');
        const identifier = button.data('identifier');
        
        if (!confirm('Are you sure you want to delete this mapping?')) {
            return;
        }
        
        // Show loading state
        button.prop('disabled', true).text('Deleting...');
        
        //console.log('=== DELETING MAPPING ===');
        //console.log('Type:', type);
        //console.log('Identifier:', identifier);
        
        // Send AJAX request
        $.ajax({
            url: wpccmDetectData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cc_detect_delete_mapping',
                nonce: wpccmDetectData.nonce,
                type: type,
                identifier: identifier
            },
            success: function(response) {
                //console.log('=== DELETE MAPPING RESPONSE ===');
                //console.log('Response:', response);
                
                if (response && response.success === true) {
                    //console.log('Mapping deleted successfully');
                    showMessage('Mapping deleted successfully!', 'success');
                    
                    // Remove the row from the table
                    button.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        const tbody = $('#wpccm-mappings-tbody');
                        if (tbody.find('tr').length === 0) {
                            tbody.html(`
                                <tr id="wpccm-no-mappings">
                                    <td colspan="5" style="text-align: center; padding: 40px;">
                                        <p>No mappings found. Use the scanner above to detect and save script mappings.</p>
                                    </td>
                                </tr>
                            `);
                        }
                    });
                } else {
                    console.error('Delete mapping failed:', response);
                    
                    let errorMessage = 'Unknown error occurred';
                    if (response && response.data) {
                        if (response.data.message) {
                            errorMessage = 'Error: ' + response.data.message;
                        } else if (typeof response.data === 'string') {
                            errorMessage = 'Error: ' + response.data;
                        }
                    }
                    
                    showMessage(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('=== DELETE MAPPING ERROR ===');
                console.error('XHR:', xhr);
                console.error('Status:', status);
                console.error('Error:', error);
                showMessage('Network error deleting mapping: ' + error, 'error');
            },
            complete: function() {
                // Restore button state
                button.prop('disabled', false).text('Delete');
            }
        });
    }

    /**
     * Save selected items
     */
    function saveSelectedItems() {
        const selectedItems = Object.values(detectedItems).filter(item => item.selected && item.category !== 'unassigned');
        
        if (selectedItems.length === 0) {
            alert('Please select items and assign categories before saving.');
            return;
        }

        // Show loading state
        $(CONFIG.selectors.saveSelectedButton).prop('disabled', true).text('Saving...');
        
        //console.log('=== SENDING SAVE MAPPINGS AJAX REQUEST ===');
        //console.log('URL:', wpccmDetectData.ajaxUrl);
        //console.log('Action:', 'cc_detect_save_map');
        //console.log('Nonce:', wpccmDetectData.nonce);
        //console.log('Selected items count:', selectedItems.length);
        
        // Send AJAX request
        $.ajax({
            url: wpccmDetectData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'cc_detect_save_map',
                nonce: wpccmDetectData.nonce,
                selected_items: JSON.stringify(selectedItems)
            },
            success: function(response) {
                //console.log('=== SAVE MAPPINGS RESPONSE ===');
                //console.log('Response:', response);
                //console.log('Response type:', typeof response);
                //console.log('Response success property:', response.success);
                
                // Log response for debugging (removed alert)
                // alert('Response received: ' + JSON.stringify(response, null, 2));
                
                // Check if response is successful
                if (response && response.success === true) {
                    //console.log('Mappings saved successfully');
                    showMessage('Mappings saved successfully! Saved ' + (response.data.saved_count || 0) + ' mappings.', 'success');
                    
                    // Add saved items to the mappings table
                    addItemsToMappingsTable(selectedItems);
                    
                    // Remove saved items from detected items
                    selectedItems.forEach(item => {
                        delete detectedItems[item.key];
                    });
                    
                    // Update table
                    updateResultsTable();
                    
                    // Show no results if empty
                    if (Object.keys(detectedItems).length === 0) {
                        $(CONFIG.selectors.resultsTbody).html(`
                            <tr id="wpccm-no-results">
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    <p>No items detected yet. Use the scanner above to detect scripts and iframes.</p>
                                </td>
                            </tr>
                        `);
                    }
                } else {
                    console.error('Save mappings failed:', response);
                    
                    // Handle different error response formats
                    let errorMessage = 'Unknown error occurred';
                    
                    if (response && response.data) {
                        if (response.data.code === 'forbidden') {
                            errorMessage = 'Access denied. Please refresh the page and try again.';
                        } else if (response.data.message) {
                            errorMessage = 'Error: ' + response.data.message;
                        } else if (typeof response.data === 'string') {
                            errorMessage = 'Error: ' + response.data;
                        }
                    } else if (response && response.message) {
                        errorMessage = 'Error: ' + response.message;
                    }
                    
                    showMessage(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                showMessage('Network error saving mappings: ' + error, 'error');
            },
            complete: function() {
                // Restore button state
                $(CONFIG.selectors.saveSelectedButton).prop('disabled', false).text('Save Selected');
            }
        });
    }

    /**
     * Update select all checkbox state
     */
    function updateSelectAllCheckbox() {
        const checkedCount = $('.wpccm-item-checkbox:checked').length;
        const totalCount = $('.wpccm-item-checkbox').length;
        
        if (totalCount === 0) {
            $(CONFIG.selectors.selectAllCheckbox).prop('indeterminate', false).prop('checked', false);
            return;
        }
        
        $(CONFIG.selectors.selectAllCheckbox).prop('indeterminate', checkedCount > 0 && checkedCount < totalCount);
        $(CONFIG.selectors.selectAllCheckbox).prop('checked', checkedCount === totalCount);
    }

    /**
     * Add items to the mappings table
     */
    function addItemsToMappingsTable(selectedItems) {
        const mappingsTbody = $('#wpccm-script-mappings');
        
        // Remove "no mappings" row if it exists
        $('#wpccm-no-mappings').remove();
        
        selectedItems.forEach(item => {
            if (item.category && item.category !== 'unassigned') {
                // Add handle mapping row if handle exists
                if (item.handle) {
                    const handleRow = createMappingRow('handle', item.handle, item.category, 'detection');
                    mappingsTbody.append(handleRow);
                }
                
                // Add domain mapping row if domain exists
                if (item.domain) {
                    const domainRow = createMappingRow('domain', item.domain, item.category, 'detection');
                    mappingsTbody.append(domainRow);
                }
            }
        });
    }

    /**
     * Create mapping row HTML for editable table
     */
    function createMappingRow(type, identifier, category, source) {
        const categories = {
            'necessary': 'Necessary',
            'functional': 'Functional',
            'performance': 'Performance',
            'analytics': 'Analytics',
            'marketing': 'Marketing',
            'others': 'Others'
        };
        
        const timestamp = Date.now();
        let categoryOptions = '';
        
        Object.keys(categories).forEach(key => {
            const selected = key === category ? 'selected' : '';
            categoryOptions += `<option value="${escapeHtml(key)}" ${selected}>${escapeHtml(categories[key])}</option>`;
        });
        
        return `
            <tr>
                <td>
                    <input type="text" 
                           name="cc_script_handle_map[${escapeHtml(identifier)}]" 
                           value="${escapeHtml(identifier)}" 
                           class="regular-text wpccm-mapping-key" 
                           placeholder="script_handle or domain.com" 
                           data-original-key="${escapeHtml(identifier)}" />
                </td>
                <td>
                    <select name="cc_script_handle_map_categories[${escapeHtml(identifier)}]" class="wpccm-mapping-category">
                        ${categoryOptions}
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-small wpccm-remove-mapping">
                        Remove
                    </button>
                </td>
            </tr>
        `;
    }

    /**
     * Handle add mapping row button click
     */
    function handleAddMappingRow() {
        const button = $(this);
        const type = button.data('type');
        const tbody = $('#wpccm-script-mappings');
        const template = $('#wpccm-script-template').html();
        const timestamp = Date.now();
        
        // Remove "no mappings" row if it exists
        $('#wpccm-no-mappings').remove();
        
        // Replace template placeholders
        const newRow = template.replace(/\{\{key\}\}/g, 'new_' + timestamp);
        
        // Add new row
        tbody.append(newRow);
        
        // Focus on the new input field
        tbody.find('tr:last input[type="text"]').focus();
    }

    /**
     * Handle remove mapping row button click
     */
    function handleRemoveMappingRow() {
        const button = $(this);
        const row = button.closest('tr');
        const tbody = row.closest('tbody');
        
        // Remove the row with animation
        row.fadeOut(300, function() {
            $(this).remove();
            
            // Check if table is empty
            if (tbody.find('tr').length === 0) {
                tbody.html(`
                    <tr id="wpccm-no-mappings">
                        <td colspan="3" style="text-align: center; padding: 40px;">
                            <p>No mappings found. Use the scanner above to detect and save script mappings.</p>
                        </td>
                    </tr>
                `);
            }
        });
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const messageClass = type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-info';
        const messageHtml = `
            <div class="notice ${messageClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

        // Remove existing messages
        $('.wpccm-detect-container .notice').remove();
        
        // Add new message
        $('.wpccm-detect-container').prepend(messageHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.wpccm-detect-container .notice').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Utility functions
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function truncateUrl(url, length = 50) {
        if (url.length <= length) {
            return url;
        }
        return url.substring(0, length - 3) + '...';
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        //console.log('=== DOM READY ===');
        //console.log('jQuery version:', $.fn.jquery);
        //console.log('Document ready, calling init...');
        init();
        
        // Initialize results table with no results
        updateResultsTable();
    });
    
    // Also try to initialize immediately
    //console.log('=== IMMEDIATE INIT ATTEMPT ===');
    if (document.readyState === 'loading') {
        //console.log('Document still loading, will wait for DOM ready');
    } else {
        //console.log('Document already ready, calling init immediately');
        init();
    }

})(jQuery);

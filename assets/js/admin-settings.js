/**
 * Admin Settings Page JavaScript
 * Handles dynamic table management for cookie and script mappings
 */

(function($) {
    'use strict';

    // Configuration
    const CONFIG = {
        selectors: {
            addButton: '.wpccm-add-mapping-row',
            removeButton: '.wpccm-remove-mapping',
            mappingTable: '.wpccm-mapping-table',
            tableBody: 'tbody',
            saveButton: '.wpccm-save-mappings'
        },
        categories: {
            necessary: 'Necessary',
            functional: 'Functional',
            performance: 'Performance',
            analytics: 'Analytics',
            advertisement: 'Advertisement',
            others: 'Others'
        }
    };

    /**
     * Initialize admin settings functionality
     */
    function init() {
        bindEvents();
        //console.log('WPCCM Admin Settings initialized');
    }

    /**
     * Bind event handlers
     */
    function bindEvents() {
        // Add new mapping row
        $(document).on('click', CONFIG.selectors.addButton, function(e) {
            e.preventDefault();
            const type = $(this).closest(CONFIG.selectors.mappingTable).data('type');
            addMappingRow(type);
        });

        // Remove mapping row
        $(document).on('click', CONFIG.selectors.removeButton, function(e) {
            e.preventDefault();
            removeMappingRow($(this));
        });

        // Save mappings
        $(document).on('click', CONFIG.selectors.saveButton, function(e) {
            e.preventDefault();
            saveMappings();
        });

        // Scan cookies
        $(document).on('click', '.wpccm-scan-cookies', function(e) {
            e.preventDefault();
            scanCookies();
        });

        // Scan scripts
        $(document).on('click', '.wpccm-scan-scripts', function(e) {
            e.preventDefault();
            scanScripts();
        });
    }

    /**
     * Add new mapping row to table
     * @param {string} type - 'cookie' or 'script'
     */
    function addMappingRow(type) {
        const tableBody = $(`[data-type="${type}"] ${CONFIG.selectors.tableBody}`);
        const rowIndex = Date.now(); // Unique index
        
        const newRow = createMappingRow(type, rowIndex);
        tableBody.append(newRow);
        
        // Focus on the new input
        tableBody.find(`tr:last-child input[type="text"]`).focus();
    }

    /**
     * Create HTML for new mapping row
     * @param {string} type - 'cookie' or 'script'
     * @param {number} index - Unique row index
     * @returns {string} HTML string
     */
    function createMappingRow(type, index) {
        const categories = Object.entries(CONFIG.categories)
            .map(([key, name]) => `<option value="${key}">${name}</option>`)
            .join('');

        return `
            <tr>
                <td>
                    <input type="text" 
                           name="cc_${type}_map[${index}][key]" 
                           value="" 
                           class="regular-text" 
                           placeholder="${type === 'cookie' ? 'Cookie name or regex' : 'Script handle or domain'}" />
                </td>
                <td>
                    <select name="cc_${type}_map[${index}][category]">
                        ${categories}
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
     * Remove mapping row
     * @param {jQuery} button - Remove button element
     */
    function removeMappingRow(button) {
        const row = button.closest('tr');
        
        // Add fade out effect
        row.fadeOut(300, function() {
            row.remove();
        });
    }

    /**
     * Save all mappings
     */
    function saveMappings() {
        const saveButton = $(CONFIG.selectors.saveButton);
        const originalText = saveButton.text();
        
        // Show loading state
        saveButton.text('Saving...').prop('disabled', true);
        
        // Collect form data
        const formData = collectFormData();
        
        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpccm_save_mappings',
                nonce: wpccmAdminData.nonce,
                mappings: formData
            },
            success: function(response) {
                if (response.success) {
                    showSuccessMessage('Mappings saved successfully!');
                } else {
                    showErrorMessage('Error saving mappings: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Network error: ' + error);
            },
            complete: function() {
                // Restore button state
                saveButton.text(originalText).prop('disabled', false);
            }
        });
    }

    /**
     * Collect form data from all mapping tables
     * @returns {Object} Form data object
     */
    function collectFormData() {
        const data = {
            cookies: {},
            scripts: {}
        };

        // Collect cookie mappings
        $('[data-type="cookie"] input[name*="[key]"]').each(function() {
            const row = $(this).closest('tr');
            const key = $(this).val().trim();
            const category = row.find('select').val();
            
            if (key) {
                data.cookies[key] = category;
            }
        });

        // Collect script mappings
        $('[data-type="script"] input[name*="[key]"]').each(function() {
            const row = $(this).closest('tr');
            const key = $(this).val().trim();
            const category = row.find('select').val();
            
            if (key) {
                data.scripts[key] = category;
            }
        });

        return data;
    }

    /**
     * Show success message
     * @param {string} message - Success message
     */
    function showSuccessMessage(message) {
        showMessage(message, 'success');
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    function showErrorMessage(message) {
        showMessage(message, 'error');
    }

    /**
     * Show message with type
     * @param {string} message - Message text
     * @param {string} type - 'success' or 'error'
     */
    function showMessage(message, type) {
        const messageClass = type === 'success' ? 'notice-success' : 'notice-error';
        const messageHtml = `
            <div class="notice ${messageClass} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `;

        // Remove existing messages
        $('.wpccm-settings-page .notice').remove();
        
        // Add new message
        $('.wpccm-settings-page').prepend(messageHtml);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $('.wpccm-settings-page .notice').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    /**
     * Scan current browser cookies
     */
    function scanCookies() {
        const button = $('.wpccm-scan-cookies');
        const spinner = $('.wpccm-scan-controls .spinner');
        
        // Show loading state
        button.prop('disabled', true);
        spinner.show();
        
        // Get cookies from current page
        const cookies = parseCookies();
        const cookieTable = $('[data-type="cookie"] tbody');
        
        // Clear existing rows
        cookieTable.empty();
        
        // Add found cookies
        cookies.forEach((cookie, index) => {
            const rowIndex = Date.now() + index;
            const newRow = createMappingRow('cookie', rowIndex);
            const row = $(newRow);
            
            // Set cookie name
            row.find('input[name*="[key]"]').val(cookie.name);
            
            // Suggest category based on cookie name
            const suggestedCategory = suggestCookieCategory(cookie.name);
            row.find('select').val(suggestedCategory);
            
            cookieTable.append(row);
        });
        
        // Show results
        showSuccessMessage(`Found ${cookies.length} cookies and added them to the mapping table.`);
        
        // Restore button state
        button.prop('disabled', false);
        spinner.hide();
    }

    /**
     * Scan registered WordPress scripts
     */
    function scanScripts() {
        const button = $('.wpccm-scan-scripts');
        const spinner = $('.wpccm-scan-controls .spinner');
        
        // Show loading state
        button.prop('disabled', true);
        spinner.show();
        
        // Send AJAX request to get registered scripts
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpccm_scan_scripts',
                nonce: wpccmAdminData.nonce
            },
            success: function(response) {
                if (response.success && response.data.scripts) {
                    const scriptTable = $('[data-type="script"] tbody');
                    
                    // Clear existing rows
                    scriptTable.empty();
                    
                    // Add found scripts
                    response.data.scripts.forEach((script, index) => {
                        const rowIndex = Date.now() + index;
                        const newRow = createMappingRow('script', rowIndex);
                        const row = $(newRow);
                        
                        // Set script handle
                        row.find('input[name*="[key]"]').val(script.handle);
                        
                        // Suggest category based on script
                        const suggestedCategory = suggestScriptCategory(script);
                        row.find('select').val(suggestedCategory);
                        
                        scriptTable.append(row);
                    });
                    
                    showSuccessMessage(`Found ${response.data.scripts.length} scripts and added them to the mapping table.`);
                } else {
                    showErrorMessage('Error scanning scripts: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showErrorMessage('Network error scanning scripts: ' + error);
            },
            complete: function() {
                // Restore button state
                button.prop('disabled', false);
                spinner.hide();
            }
        });
    }

    /**
     * Parse cookies from document.cookie
     * @returns {Array} Array of cookie objects
     */
    function parseCookies() {
        const cookies = [];
        const cookieString = document.cookie;
        
        if (!cookieString) {
            return cookies;
        }
        
        const cookiePairs = cookieString.split(';');
        
        cookiePairs.forEach(pair => {
            const [name, value] = pair.trim().split('=');
            if (name && value) {
                cookies.push({
                    name: name.trim(),
                    value: value.trim()
                });
            }
        });
        
        return cookies;
    }

    /**
     * Suggest category for cookie based on name
     * @param {string} cookieName - Cookie name
     * @returns {string} Suggested category
     */
    function suggestCookieCategory(cookieName) {
        const name = cookieName.toLowerCase();
        
        // Analytics cookies
        if (name.includes('_ga') || name.includes('_gid') || name.includes('_gat') || 
            name.includes('analytics') || name.includes('stats')) {
            return 'analytics';
        }
        
        // Advertisement cookies
        if (name.includes('_fbp') || name.includes('_fbc') || name.includes('ads') || 
            name.includes('advertising') || name.includes('marketing')) {
            return 'advertisement';
        }
        
        // WordPress cookies
        if (name.includes('wordpress') || name.includes('wp_') || name.includes('comment')) {
            return 'necessary';
        }
        
        // Session cookies
        if (name.includes('session') || name.includes('sess')) {
            return 'functional';
        }
        
        // Performance cookies
        if (name.includes('cache') || name.includes('performance') || name.includes('speed')) {
            return 'performance';
        }
        
        // Default to others
        return 'others';
    }

    /**
     * Suggest category for script based on handle and src
     * @param {Object} script - Script object
     * @returns {string} Suggested category
     */
    function suggestScriptCategory(script) {
        const handle = script.handle.toLowerCase();
        const src = (script.src || '').toLowerCase();
        
        // Analytics scripts
        if (handle.includes('analytics') || handle.includes('ga') || handle.includes('gtm') ||
            src.includes('google-analytics') || src.includes('googletagmanager')) {
            return 'analytics';
        }
        
        // Advertisement scripts
        if (handle.includes('ads') || handle.includes('advertising') || handle.includes('facebook') ||
            src.includes('facebook') || src.includes('ads')) {
            return 'advertisement';
        }
        
        // Performance scripts
        if (handle.includes('lazy') || handle.includes('optimize') || handle.includes('minify') ||
            src.includes('optimize') || src.includes('performance')) {
            return 'performance';
        }
        
        // Functional scripts
        if (handle.includes('form') || handle.includes('contact') || handle.includes('chat') ||
            src.includes('form') || src.includes('contact')) {
            return 'functional';
        }
        
        // Default to others
        return 'others';
    }

    // Initialize when DOM is ready
    $(document).ready(init);

})(jQuery);


(function(window) {

    function initTableManager() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initTableManager, 100);
            return;
        }
        
        if (typeof WPCCM_TABLE === 'undefined') {
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
        $(this).closest('tr').remove();
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

    // Add change listener for category selects in cookie table
    $(document).on('change', '#wpccm-cookies-table .category-select', function() {
        updateCookieData();
    });

    // Sync categories from mapping to purge table
    function syncCategoriesFromMapping() {

        const tableRows = $('#wpccm-cookies-table tbody tr');

        
        tableRows.each(function() {
            const cookieInput = $(this).find('.cookie-input');
            const categorySelect = $(this).find('.category-select');
            
            if (cookieInput.length > 0 && categorySelect.length > 0) {
                const cookieName = cookieInput.val();
                const currentCategory = categorySelect.val();
                
                if (cookieName && typeof cookieName === 'string') {
                    const trimmedName = cookieName.trim();
                    if (trimmedName) {
                        // Get category from handle mapping
                        const mappedCategory = getCategoryFromMapping(trimmedName);

                        
                        if (mappedCategory && mappedCategory !== currentCategory) {
                            // Update the category select
                            $(this).find('.category-select').val(mappedCategory);

                        }
                    }
                }
            }
        });
        
        // Update the data

        updateCookieData();
    }

    // Sync to purge list functionality  
    $('#wpccm-sync-to-purge').on('click', function() {
        const cookies = [];
        const handleCookies = {};
        const noRelatedCookiesText = texts.no_related_cookies || 'No related cookies found';
        
        $('#wpccm-handles-table tbody tr').each(function() {
            const handle = $(this).find('.handle-input').val().trim();
            const cookiesStr = $(this).find('.cookies-input-manual').val().trim();
            const category = $(this).find('.category-select').val();
            
            // Skip if category is necessary or cookies are empty/not found
            if (handle && cookiesStr && category && 
                category !== 'necessary' && 
                cookiesStr !== (texts.unknown_cookies || '(click to detect)') &&
                cookiesStr !== noRelatedCookiesText &&
                cookiesStr.trim() !== '') {
                
                handleCookies[handle] = cookiesStr;
                // Split cookies by comma and clean them up
                const cookieList = cookiesStr.split(',').map(c => c.trim()).filter(c => c);
                cookies.push(...cookieList);
            }
        });
        
        if (cookies.length === 0) {
            alert(texts.no_non_essential_cookies || 'No non-essential cookies found to sync.');
            return;
        }
        
        // Remove duplicates
        const uniqueCookies = [...new Set(cookies)];
        
        if (confirm('Add ' + uniqueCookies.length + ' cookies to the purge list?\n\nCookies: ' + uniqueCookies.join(', '))) {
            // Add to purge list (we'll need to save to the purge section)
            const currentPurgeCookies = [];
            $('#wpccm-cookies-table tbody tr').each(function() {
                const cookieInput = $(this).find('.cookie-input');
                if (cookieInput.length > 0) {
                    const cookieName = cookieInput.val();
                    if (cookieName && typeof cookieName === 'string') {
                        const trimmedName = cookieName.trim();
                        if (trimmedName) {
                            currentPurgeCookies.push(trimmedName);
                        }
                    }
                }
            });
            
            let addedCount = 0;
            uniqueCookies.forEach(function(cookie) {
                if (!currentPurgeCookies.includes(cookie)) {
                    // This assumes we're on the same page with the purge table
                    if (typeof addCookieRow === 'function') {
                        // Get the category from the handle that created this cookie
                        let cookieCategory = 'others';
                        for (const [handle, cookiesStr] of Object.entries(handleCookies)) {
                            const cookieList = cookiesStr.split(',').map(c => c.trim()).filter(c => c);
                            const isMatch = cookieList.some(function(mappedCookie) {
                                if (mappedCookie.includes('*')) {
                                    const pattern = mappedCookie.replace(/\*/g, '.*');
                                    const regex = new RegExp('^' + pattern + '$', 'i');
                                    return regex.test(cookie);
                                } else {
                                    return mappedCookie.toLowerCase() === cookie.toLowerCase();
                                }
                            });
                            
                            if (isMatch) {
                                // Find the category for this handle
                                $('#wpccm-handles-table tbody tr').each(function() {
                                    const rowHandle = $(this).find('.handle-input').val().trim();
                                    if (rowHandle === handle) {
                                        cookieCategory = $(this).find('.category-select').val() || 'others';
                                        return false;
                                    }
                                });
                                break;
                            }
                        }
                        
                        addCookieRow(cookie, cookieCategory);
                        addedCount++;
                    }
                }
            });
            
            const message = 'Added ' + addedCount + ' new cookies to purge list!' + 
                          (addedCount < uniqueCookies.length ? ' (' + (uniqueCookies.length - addedCount) + ' already existed)' : '');
            
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + message + '</p></div>');
            $('#wpccm-handle-mapping-table').prepend(successMsg);
            setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 4000);
        }
    });

    // Clear all handles functionality
    $('#wpccm-clear-all-handles').on('click', function() {
        if (confirm(texts.confirm_clear_all_handles || 'Are you sure you want to remove all handle mappings?')) {
            $('#wpccm-handles-table tbody').empty();
            updateHandleMapping();
            
            // Show success message
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + (texts.all_items_cleared || 'All items cleared successfully!') + '</p></div>');
            $('#wpccm-handle-mapping-table').prepend(successMsg);
            setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 3000);
        }
    });

    // Clear all cookies functionality
    $('#wpccm-clear-all-cookies').on('click', function() {

        if (confirm(texts.confirm_clear_all_cookies || 'Are you sure you want to remove all cookies from the purge list?')) {

            $('#wpccm-cookies-table tbody').empty();

            updateCookieData();
            
            // Show success message
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + (texts.all_items_cleared || 'All items cleared successfully!') + '</p></div>');
            $('#wpccm-cookie-purge-table').prepend(successMsg);
            setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 3000);
        } else {

        }
    });

    // Scan Live Handles functionality
    $('#wpccm-scan-live-handles').on('click', function() {

        const button = $(this);
        const originalText = button.text();
        

        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        // Create iframe instead of popup window to avoid blocking
        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.top = '-1000px';
        iframe.style.left = '-1000px';
        iframe.style.width = '800px';
        iframe.style.height = '600px';
        iframe.style.border = 'none';
        iframe.style.zIndex = '-1';
        document.body.appendChild(iframe);
        
        iframe.onload = function() {
            try {
                // Wait a bit for scripts to load
                setTimeout(function() {
                    const scripts = [];
                    const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                    

                    
                    // Look for scripts with IDs (WordPress style)
                    iframeDoc.querySelectorAll('script[id]').forEach(function(script) {
                        const id = script.id;
                        if (id.endsWith('-js')) {
                            const handle = id.replace('-js', '');
                            const src = script.src || script.getAttribute('src') || '';
                            
                            // Only include scripts that look like plugin/theme scripts
                            if (src && (src.includes('/wp-content/plugins/') || 
                                       src.includes('/wp-content/themes/') || 
                                       src.includes('://') && !src.includes(window.location.host + '/wp-admin') && !src.includes(window.location.host + '/wp-includes'))) {
                                scripts.push({
                                    handle: handle,
                                    src: src,
                                    suggested: categorizeLiveScript(handle, src),
                                    type: 'script'
                                });
                            }
                        }
                    });
                    
                    // Also look for scripts without IDs but with external sources
                    iframeDoc.querySelectorAll('script[src]').forEach(function(script) {
                        const src = script.src || script.getAttribute('src') || '';
                        if (src && (src.includes('analytics') || src.includes('facebook') || 
                                   src.includes('google') || src.includes('tracking') ||
                                   (src.includes('://') && !src.includes('/wp-admin') && !src.includes('/wp-includes')))) {
                            // Try to guess handle from src
                            let handle = src.split('/').pop().split('.')[0];
                            if (src.includes('gtag')) handle = 'google-analytics';
                            if (src.includes('fbevents')) handle = 'facebook-pixel';
                            
                            scripts.push({
                                handle: handle,
                                src: src,
                                suggested: categorizeLiveScript(handle, src),
                                type: 'script'
                            });
                        }
                    });
                    
                    // Remove iframe
                    document.body.removeChild(iframe);
                    

                    
                    if (scripts.length > 0) {
                        showScanResults(scripts);
                    } else {
                        const totalScripts = iframeDoc.querySelectorAll('script').length;
                        alert('No relevant third-party scripts found on the live website.\n\n' +
                              'Total scripts found: ' + totalScripts + '\n' +
                              'The site might not have tracking scripts, or they might:\n' +
                              '• Load dynamically after user interaction\n' +
                              '• Be loaded by other plugins\n' +
                              '• Use different naming conventions\n\n' +
                              'Try using "Scan Registered Handles" instead, or check the browser console for details.');
                    }
                    
                    button.text(originalText).prop('disabled', false);
                }, 3000); // Wait 3 seconds for scripts to load
                
            } catch (e) {

                document.body.removeChild(iframe);
                fallbackToAjaxScan(button, originalText);
            }
        };
        
        iframe.onerror = function() {
            document.body.removeChild(iframe);
            fallbackToAjaxScan(button, originalText);
        };
        
        // Load the homepage
        iframe.src = window.location.origin;
        
        // Timeout after 15 seconds
        setTimeout(function() {
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
                fallbackToAjaxScan(button, originalText);
            }
        }, 15000);
    });
    
    function fallbackToAjaxScan(button, originalText) {
        $.ajax({
            url: WPCCM_TABLE.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_scan_handles',
                _wpnonce: WPCCM_TABLE.nonce
            },
            success: function(response) {
                if (response.success) {
                    showScanResults(response.data);
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

    // Scan Handles functionality
    $('#wpccm-scan-handles').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        $.ajax({
            url: WPCCM_TABLE.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_scan_handles',
                _wpnonce: WPCCM_TABLE.nonce
            },
            success: function(response) {
                if (response.success) {
                    showScanResults(response.data);
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

    // Sync categories functionality
    $('#wpccm-sync-categories-btn').on('click', function() {

        const button = $(this);
        const originalText = button.text();
        

        button.text('מסנכרן...').prop('disabled', true);
        
        // Run the sync
        syncCategoriesFromMapping();
        
        // Show success message
        const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>קטגוריות סונכרנו בהצלחה מטבלת המיפוי!</p></div>');
        $('#wpccm-cookie-purge-table').prepend(successMsg);
        setTimeout(function() { 
            successMsg.fadeOut(function() { $(this).remove(); }); 
            button.text(originalText).prop('disabled', false);
        }, 3000);
    });

    // Cookie suggestions functionality (updated for table)
    $('#wpccm-suggest-cookies-btn').on('click', function() {
        const button = $(this);
        const originalText = button.text();
        
        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        $.ajax({
            url: WPCCM_TABLE.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wpccm_suggest_purge_cookies',
                _wpnonce: WPCCM_TABLE.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderCookieSuggestionsForTable(response.data);
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

    // Sync with current cookies functionality  


    
    $(document).on('click', '#wpccm-sync-current-cookies-btn', function() {
        //console.log('WPCCM: Syncing cookies from frontend URL:########################');
        const button = $(this);
        const originalText = button.text();
        
        button.text(texts.loading || 'Loading...').prop('disabled', true);
        
        // Get frontend site URL (remove /wp-admin/ part)
        const currentUrl = window.location.href;
        const frontendUrl = currentUrl.replace(/\/wp-admin\/.*$/, '');
        
        //console.log('WPCCM: Syncing cookies from frontend URL:', currentUrl);
        //console.log('WPCCM: Syncing cookies from frontend URL:', frontendUrl);
        
        // Show loading message
        const scanningMsg = texts.scanning_site_cookies || 'Scanning cookies from the site...';
        $('#wpccm-sync-result').html('<span class="loading">' + escapeHtml(scanningMsg) + '</span>');
        
        // First, try to get cookies from frontend site via iframe method
        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = frontendUrl + '/wp-admin/admin-ajax.php?action=wpccm_get_frontend_cookies&_wpnonce=' + WPCCM_TABLE.nonce;
        
        // //console.log(iframe.src);
        // die();


        // Set up message listener for iframe response
        const messageListener = function(event) {
            if (event.origin !== window.location.origin && event.origin !== frontendUrl) {
                return;
            }
            
            if (event.data && event.data.type === 'wpccm_cookies_response') {
                window.removeEventListener('message', messageListener);
                document.body.removeChild(iframe);
                
                ////console.log('WPCCM: Frontend cookies from iframe:', event.data);
                
                if (event.data.success) {
                    const frontendCookies = event.data.cookies || [];
                    
                    //console.log('WPCCM: Found cookies from frontend:', frontendCookies);
                    ////console.log('WPCCM: Found ' + frontendCookies.length + ' cookies from frontend');
                    
                    // Show success message
                    const foundMsgTpl = texts.site_cookies_found || 'Found %d cookies from the site';
                    const foundMsg = foundMsgTpl.replace('%d', frontendCookies.length);
                    $('#wpccm-sync-result').html('<span class="success">' + escapeHtml(foundMsg) + '</span>');
                    
                    // Now send to admin to process
                    let formData = {
                        action: 'wpccm_get_current_non_essential_cookies',
                        _wpnonce: WPCCM_TABLE.nonce,
                        current_cookies: frontendCookies
                    };
                    
                    $.ajax({
                        url: WPCCM_TABLE.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                // Add cookies directly to the table instead of showing suggestions
                                addCookiesDirectlyToTable(response.data);
                                const addedMsg = texts.cookies_added_to_table || 'Cookies added to the table successfully';
                                $('#wpccm-sync-result').html('<span class="success">✓ ' + escapeHtml(addedMsg) + '</span>');
                            } else {
                                const errTpl = texts.error_with_message || 'Error: %s';
                                const unknownErr = texts.unknown_error || 'Unknown error';
                                const errMsg = errTpl.replace('%s', response.data || unknownErr);
                                $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            const errMsg = texts.error_saving_cookies || 'Error saving cookies';
                            $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                        },
                        complete: function() {
                            button.text(originalText).prop('disabled', false);
                        }
                    });
                } else {
                    // Fallback to AJAX method
                    fallbackToAjaxMethod();
                }
            }
        };
        
        window.addEventListener('message', messageListener);
        
        // Set timeout for iframe method
        setTimeout(function() {
            window.removeEventListener('message', messageListener);
            if (document.body.contains(iframe)) {
                document.body.removeChild(iframe);
            }
            ////console.log('WPCCM: Iframe method timeout, falling back to AJAX');
            fallbackToAjaxMethod();
        }, 5000);
        
        document.body.appendChild(iframe);
        
        function fallbackToAjaxMethod() {
            // Fallback to AJAX method
            $.ajax({
                url: frontendUrl + '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'wpccm_get_frontend_cookies',
                    _wpnonce: WPCCM_TABLE.nonce
                },
                success: function(frontendResponse) {
                    ////console.log('WPCCM: Frontend cookies response:', frontendResponse);
                    
                    if (frontendResponse.success) {
                        const frontendCookies = frontendResponse.data.cookies || [];
                        
                        //console.log('WPCCM: Found ' + frontendCookies.length + ' cookies from frontend (AJAX method)');
                        const $result = $('#wpccm-sync-result');
                        
                        // Show success message
                        const foundMsgTpl = texts.site_cookies_found || 'Found %d cookies from the site';
                        const foundMsg = foundMsgTpl.replace('%d', frontendCookies.length);
                        $result.html('<span class="success">' + escapeHtml(foundMsg) + '</span>');
                        
                        // Now send to admin to process
                        let formData = {
                            action: 'wpccm_get_current_non_essential_cookies',
                            _wpnonce: WPCCM_TABLE.nonce,
                            current_cookies: frontendCookies
                        };
                        
                        $.ajax({
                            url: WPCCM_TABLE.ajaxUrl,
                            type: 'POST',
                            data: formData,
                            success: function(response) {
                                if (response.success) {
                                    // Add cookies directly to the table instead of showing suggestions
                                    addCookiesDirectlyToTable(response.data);
                                    const addedMsg = texts.cookies_added_to_table || 'Cookies added to the table successfully';
                                    $result.html('<span class="success">✓ ' + escapeHtml(addedMsg) + '</span>');
                                } else {
                                    const errTpl = texts.error_with_message || 'Error: %s';
                                    const unknownErr = texts.unknown_error || 'Unknown error';
                                    const errMsg = errTpl.replace('%s', response.data || unknownErr);
                                    $result.html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                                }
                            },
                            error: function(xhr, status, error) {
                                const errMsg = texts.error_saving_cookies || 'Error saving cookies';
                                $result.html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                            },
                            complete: function() {
                                button.text(originalText).prop('disabled', false);
                            }
                        });
                    } else {
                        // Fallback to current method if frontend call fails
                        ////console.log('WPCCM: Frontend call failed, falling back to current method');
                        const siteAccessErr = texts.error_accessing_site_using_admin || 'Could not access the site, using admin cookies';
                        $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(siteAccessErr) + '</span>');
                        
                        // Get current cookies from browser (admin cookies)
                        const currentCookies = [];
                        if (document.cookie) {
                            const cookies = document.cookie.split(';');
                            cookies.forEach(function(cookie) {
                                const cookieName = cookie.split('=')[0].trim();
                                if (cookieName) {
                                    currentCookies.push(cookieName);
                                }
                            });
                        }
                        
                        ////console.log('WPCCM: Found ' + currentCookies.length + ' cookies from admin');
                        
                        // Prepare form data
                        let formData = {
                            action: 'wpccm_get_current_non_essential_cookies',
                            _wpnonce: WPCCM_TABLE.nonce,
                            current_cookies: currentCookies
                        };
                        
                        $.ajax({
                            url: WPCCM_TABLE.ajaxUrl,
                            type: 'POST',
                            data: formData,
                            success: function(response) {
                                if (response.success) {
                                    // Add cookies directly to the table instead of showing suggestions
                                    addCookiesDirectlyToTable(response.data);
                                    const adminAddedMsg = texts.cookies_added_to_table_admin || 'Cookies added to the table successfully (from admin)';
                                    $('#wpccm-sync-result').html('<span class="success">✓ ' + escapeHtml(adminAddedMsg) + '</span>');
                                } else {
                                    const errTpl = texts.error_with_message || 'Error: %s';
                                    const unknownErr = texts.unknown_error || 'Unknown error';
                                    const errMsg = errTpl.replace('%s', response.data || unknownErr);
                                    $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                                }
                            },
                            error: function(xhr, status, error) {
                                const errMsg = texts.error_saving_cookies || 'Error saving cookies';
                                $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                            },
                            complete: function() {
                                button.text(originalText).prop('disabled', false);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    ////console.log('WPCCM: Frontend AJAX error:', {xhr: xhr, status: status, error: error});
                    const siteAccessErr = texts.error_accessing_site_using_admin || 'Could not access the site, using admin cookies';
                    $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(siteAccessErr) + '</span>');
                    
                    // Fallback to current method
                    const currentCookies = [];
                    if (document.cookie) {
                        const cookies = document.cookie.split(';');
                        cookies.forEach(function(cookie) {
                            const cookieName = cookie.split('=')[0].trim();
                            if (cookieName) {
                                currentCookies.push(cookieName);
                            }
                        });
                    }
                    
                    ////console.log('WPCCM: Found ' + currentCookies.length + ' cookies from admin (fallback)');
                    
                    let formData = {
                        action: 'wpccm_get_current_non_essential_cookies',
                        _wpnonce: WPCCM_TABLE.nonce,
                        current_cookies: currentCookies
                    };
                    
                    $.ajax({
                        url: WPCCM_TABLE.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                addCookiesDirectlyToTable(response.data);
                                const adminAddedMsg = texts.cookies_added_to_table_admin || 'Cookies added to the table successfully (from admin)';
                                $('#wpccm-sync-result').html('<span class="success">✓ ' + escapeHtml(adminAddedMsg) + '</span>');
                            } else {
                                const errTpl = texts.error_with_message || 'Error: %s';
                                const unknownErr = texts.unknown_error || 'Unknown error';
                                const errMsg = errTpl.replace('%s', response.data || unknownErr);
                                $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                            }
                        },
                        error: function(xhr, status, error) {
                            const errMsg = texts.error_saving_cookies || 'Error saving cookies';
                            $('#wpccm-sync-result').html('<span class="error">✗ ' + escapeHtml(errMsg) + '</span>');
                        },
                        complete: function() {
                            button.text(originalText).prop('disabled', false);
                        }
                    });
                }
            });
        }
    });

    function addHandleRow(handle = '', category = '', src = '') {
        const tbody = $('#wpccm-handles-table tbody');
        const categorySelect = createCategorySelect(category);
        
        // Format script source display
        let scriptDisplay = '';
        if (src) {
            // Extract filename from full URL
            const filename = src.split('/').pop().split('?')[0];
            scriptDisplay = '<span class="script-src-display" style="color: #666; font-size: 12px;" title="' + escapeHtml(src) + '">' + escapeHtml(filename) + '</span>';
        } else {
            scriptDisplay = '<span class="script-src-display" style="color: #999; font-size: 12px; font-style: italic;">' + (texts.unknown_script || '(unknown)') + '</span>';
        }
        
        // Get related cookies for this handle - check for saved mapping first
        let cookiesValue = '';
        if (handle) {
            // Try to get saved cookies first
            cookiesValue = getSavedCookiesForHandle(handle);
        }
        if (!cookiesValue) {
            cookiesValue = getRelatedCookiesForHandle(handle);
        }
        
        // Create cookies input container with dropdown and manual input
        const cookiesInput = createCookiesInputContainer(cookiesValue, handle);
        
        const row = $('<tr>' +
            '<td><input type="text" class="handle-input" value="' + escapeHtml(handle) + '" placeholder="' + (texts.enter_handle_name || 'Enter handle name...') + '" /></td>' +
            '<td>' + scriptDisplay + '</td>' +
            '<td>' + cookiesInput + '</td>' +
            '<td>' + categorySelect + '</td>' +
            '<td><button type="button" class="button remove-handle">' + (texts.remove || 'Remove') + '</button></td>' +
            '</tr>');
        
        // Store the src in data attribute for later saving
        if (src) {
            row.data('script-src', src);
        }
        
        tbody.append(row);
        
        // Update the manual input field with detected cookies after adding to DOM
        if (cookiesValue && cookiesValue !== (texts.no_related_cookies || 'No related cookies found')) {

            setTimeout(function() {
                row.find('.cookies-input-manual').val(cookiesValue);
                
                // Also update dropdown selection
                const dropdown = row.find('.cookies-dropdown');
                const cookieList = cookiesValue.split(',').map(s => s.trim()).filter(s => s);
                dropdown.find('option').prop('selected', false);
                cookieList.forEach(function(cookie) {
                    dropdown.find('option[value="' + cookie + '"]').prop('selected', true);
                });
                

            }, 100);
        }
        
        updateHandleMapping();
    }

    function addCookieRow(cookieName = '', category = '') {

        const tbody = $('#wpccm-cookies-table tbody');

        
        // Auto-detect category if not provided
        if (!category && cookieName) {
            // First try to get category from handle mapping
            category = getCategoryFromMapping(cookieName);
            
            // If not found in mapping, use pattern detection
            if (!category) {
                category = detectCookieCategory(cookieName);
            }
        }
        

        const categorySelect = createCategorySelect(category);
        
        const row = $('<tr>' +
            '<td><input type="text" class="cookie-input" value="' + escapeHtml(cookieName) + '" placeholder="' + (texts.enter_cookie_name || 'Enter cookie name...') + '" /></td>' +
            '<td>' + categorySelect + '</td>' +
            '<td><button type="button" class="button remove-cookie">' + (texts.remove || 'Remove') + '</button></td>' +
            '</tr>');
        

        tbody.append(row);

        updateCookieData();

    }

    function createCookiesInputContainer(cookiesValue = '', handle = '') {
        // Get cookies from purge list
        const purgeCookies = [];
        $('#wpccm-cookies-table tbody tr').each(function() {
            const cookieInput = $(this).find('.cookie-input');
            if (cookieInput.length > 0) {
                const cookieName = cookieInput.val();
                if (cookieName && typeof cookieName === 'string') {
                    const trimmedName = cookieName.trim();
                    if (trimmedName) {
                        purgeCookies.push(trimmedName);
                    }
                }
            }
        });
        
        // If no cookies in purge list, try to get them from the server
        if (purgeCookies.length === 0) {
            // Try to get cookies from the hidden field
            const cookiesDataField = $('#wpccm-cookies-data');
            if (cookiesDataField.length > 0) {
                try {
                    const savedCookies = JSON.parse(cookiesDataField.val());
                    if (Array.isArray(savedCookies)) {
                        savedCookies.forEach(function(cookieData) {
                            if (typeof cookieData === 'string') {
                                // Old format
                                purgeCookies.push(cookieData);
                            } else if (cookieData && cookieData.name) {
                                // New format with categories
                                purgeCookies.push(cookieData.name);
                            }
                        });
                    }
                } catch (e) {

                }
            }
        }
        
        // Parse existing cookies value
        const selectedCookies = cookiesValue ? cookiesValue.split(', ').map(c => c.trim()).filter(c => c) : [];
        
        let html = '<div class="cookies-input-container">';
        html += '<select class="cookies-dropdown" multiple style="width: 100%; font-size: 12px; min-height: 60px;">';
        
        // Add suggested cookies for this handle if available
        if (handle) {
            const suggestedCookies = getRelatedCookiesForHandle(handle);
            if (suggestedCookies && suggestedCookies !== (texts.no_related_cookies || 'No related cookies found')) {
                const suggestedList = suggestedCookies.split(',').map(c => c.trim()).filter(c => c);
                suggestedList.forEach(function(cookie) {
                    if (!purgeCookies.includes(cookie)) {
                        purgeCookies.push(cookie);
                    }
                });
            }
        }
        
        purgeCookies.forEach(function(cookie) {
            const selected = selectedCookies.includes(cookie) ? ' selected' : '';
            html += '<option value="' + escapeHtml(cookie) + '"' + selected + '>' + escapeHtml(cookie) + '</option>';
        });
        
        html += '</select>';
        html += '<input type="text" class="cookies-input-manual" value="' + escapeHtml(cookiesValue) + '" placeholder="' + (texts.enter_cookies_separated || 'Enter cookies separated by commas') + '" style="width: 100%; font-size: 12px; margin-top: 5px;" title="' + (texts.cookies_input_help || 'Enter cookie names that this script creates, separated by commas') + '" />';
        html += '<div class="cookies-input-help" style="font-size: 11px; color: #666; margin-top: 3px;">';
        html += escapeHtml(texts.cookies_input_helper_text || 'Choose from the list or enter manually');
        html += '</div>';
        html += '</div>';
        
        return html;
    }

    function createCategorySelect(selected = '') {
        const categories = [
            { value: '', text: texts.none || 'None' },
            { value: 'necessary', text: texts.necessary || 'Necessary' },
            { value: 'functional', text: texts.functional || 'Functional' },
            { value: 'performance', text: texts.performance || 'Performance' },
            { value: 'analytics', text: texts.analytics || 'Analytics' },
            { value: 'advertisement', text: texts.advertisement || 'Advertisement' },
            { value: 'others', text: texts.others || 'Others' }
        ];

        let html = '<select class="category-select">';
        categories.forEach(function(cat) {
            const sel = cat.value === selected ? ' selected' : '';
            html += '<option value="' + escapeHtml(cat.value) + '"' + sel + '>' + escapeHtml(cat.text) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function updateCookiesFromDropdown() {

        // Update manual input when dropdown selection changes
        $(document).on('change', '.cookies-dropdown', function() {

            const selectedOptions = $(this).find('option:selected');
            const selectedValues = [];
            selectedOptions.each(function() {
                selectedValues.push($(this).val());
            });
            

            const manualInput = $(this).closest('.cookies-input-container').find('.cookies-input-manual');
            manualInput.val(selectedValues.join(', '));
            
            // Trigger change event to update mapping
            manualInput.trigger('change');
        });
        
        // Update dropdown when manual input changes and trigger mapping update
        $(document).on('input', '.cookies-input-manual', function() {

            const manualValue = $(this).val();
            const dropdown = $(this).closest('.cookies-input-container').find('.cookies-dropdown');
            
            // Clear all selections
            dropdown.find('option').prop('selected', false);
            
            if (manualValue) {
                const cookies = manualValue.split(',').map(c => c.trim()).filter(c => c);
                cookies.forEach(function(cookie) {
                    const option = dropdown.find('option[value="' + escapeHtml(cookie) + '"]');
                    if (option.length > 0) {
                        option.prop('selected', true);
                    }
                });
            }
            
            // Trigger change event to update mapping
            $(this).trigger('change');
        });
        

    }

    function initializeSavedCookieSelections() {

        // Initialize dropdown selections based on saved manual input values
        const handleTableRows = $('#wpccm-handles-table tbody tr');

        
        handleTableRows.each(function() {
            const row = $(this);
            const dropdown = row.find('.cookies-dropdown');
            const manualInput = row.find('.cookies-input-manual');
            const savedCookies = manualInput.val().trim();
            

            
            if (savedCookies && dropdown.length) {
                // Parse saved cookies and select matching options in dropdown
                const cookieList = savedCookies.split(',').map(s => s.trim()).filter(s => s);
                
                dropdown.find('option').prop('selected', false);
                cookieList.forEach(function(cookie) {
                    dropdown.find('option[value="' + cookie + '"]').prop('selected', true);
                });
                

            }
        });

    }

    function updateHandleMapping() {
        const mapping = {};
        const cookieMapping = {};
        const scriptSources = {};
        
        $('#wpccm-handles-table tbody tr').each(function() {
            const handle = $(this).find('.handle-input').val().trim();
            const category = $(this).find('.category-select').val();
            const cookies = $(this).find('.cookies-input-manual').val().trim();
            
            // Get script source from data attribute if available
            const srcFromScan = $(this).data('script-src');
            
            if (handle && category) {
                mapping[handle] = category;
            }
            
            if (handle && cookies) {
                cookieMapping[handle] = cookies;
            }
            
            if (handle && srcFromScan) {
                scriptSources[handle] = srcFromScan;
            }
        });
        
        $('#wpccm-map-data').val(JSON.stringify(mapping));
        
        // Store cookie mapping
        let cookieField = $('#wpccm-cookie-mapping-data');
        if (cookieField.length === 0) {
            cookieField = $('<input type="hidden" name="wpccm_options[cookie_mapping]" id="wpccm-cookie-mapping-data" />');
            $('#wpccm-map-data').after(cookieField);
        }
        cookieField.val(JSON.stringify(cookieMapping));
        
        // Store script sources
        let sourcesField = $('#wpccm-script-sources-data');
        if (sourcesField.length === 0) {
            sourcesField = $('<input type="hidden" name="wpccm_options[script_sources]" id="wpccm-script-sources-data" />');
            cookieField.after(sourcesField);
        }
        sourcesField.val(JSON.stringify(scriptSources));
    }

    function updateCookieData() {

        const cookies = [];
        const tableRows = $('#wpccm-cookies-table tbody tr');

        
        tableRows.each(function() {
            const cookieInput = $(this).find('.cookie-input');
            const categorySelect = $(this).find('.category-select');
            
            if (cookieInput.length > 0 && categorySelect.length > 0) {
                const cookieName = cookieInput.val();
                const category = categorySelect.val();
                
                if (cookieName && typeof cookieName === 'string') {
                    const trimmedName = cookieName.trim();
                    if (trimmedName) {
                        cookies.push({
                            name: trimmedName,
                            category: category || 'others'
                        });
                    }
                }
            }
        });
        
        const jsonData = JSON.stringify(cookies);
        const hiddenField = $('#wpccm-cookies-data');

        
        if (hiddenField.length > 0) {
            hiddenField.val(jsonData);

        } else {

        }

    }

    function showScanResults(handles) {
        if (!handles.length) {
            alert('No handles found');
            return;
        }

        // Get existing handles to avoid duplicates
        const existingHandles = [];
        $('#wpccm-handles-table tbody tr').each(function() {
            const handle = $(this).find('.handle-input').val().trim();
            if (handle) {
                existingHandles.push(handle);
            }
        });
        
        // Add scanned handles (only new ones)
        const relevantHandles = handles.filter(function(item) {
            return item.suggested !== 'uncategorized' && !existingHandles.includes(item.handle);
        });
        
        if (existingHandles.length === 0) {
            // If table was empty, clear it completely first
            $('#wpccm-handles-table tbody').empty();
        }
        
        relevantHandles.forEach(function(item) {
            addHandleRow(item.handle, item.suggested, item.src);
        });
        
        updateHandleMapping();
        
        // Show success message
        let message = '';
        if (relevantHandles.length > 0) {
            message = 'Added ' + relevantHandles.length + ' new handles from scan!';
            if (relevantHandles.length < handles.length) {
                const skipped = handles.length - relevantHandles.length;
                message += ' (' + skipped + ' already existed or were uncategorized)';
            }
        } else {
            message = 'No new handles found - all ' + existingHandles.length + ' handles already exist in the table.';
        }
        
        const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + message + '</p></div>');
        $('#wpccm-handle-mapping-table').prepend(successMsg);
        
        setTimeout(function() {
            successMsg.fadeOut(function() { $(this).remove(); });
        }, 4000);
        
        // Scroll to bottom of table to see new items
        if (relevantHandles.length > 0) {
            const tableContainer = $('.wpccm-table-container').first();
            if (tableContainer.length) {
                tableContainer.scrollTop(tableContainer[0].scrollHeight);
            }
        }
    }

    function renderCookieSuggestionsForTable(suggestions) {
        if (!suggestions.length) {
            $('#wpccm-cookie-suggestions-inline').html('<p>No cookie suggestions found.</p>');
            return;
        }

        let html = '<div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; border-radius: 4px; margin-top: 15px;">';
        html += '<h4 style="margin: 0 0 10px 0;">Cookie Suggestions</h4>';
        html += '<div class="wpccm-table-container" style="max-height: 300px; margin-bottom: 15px;">';
        html += '<table class="widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Cookie</th>';
        html += '<th>Category</th>';
        html += '<th>Reason</th>';
        html += '<th style="width: 80px;">Add?</th>';
        html += '</tr></thead><tbody>';

        suggestions.forEach(function(item) {
            html += '<tr>';
            html += '<td><code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">' + escapeHtml(item.name) + '</code></td>';
            html += '<td>' + escapeHtml(item.category) + '</td>';
            html += '<td style="font-size: 12px; color: #666;">' + escapeHtml(item.reason) + '</td>';
            html += '<td><input type="checkbox" class="cookie-suggestion-cb" data-cookie="' + escapeHtml(item.name) + '" checked></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '</div>'; // Close table container
        html += '<button type="button" class="button button-primary" id="add-suggested-cookies">Add Selected Cookies</button>';
        html += ' <button type="button" class="button" id="cancel-cookie-suggestions">Cancel</button>';
        html += '</div>';

        $('#wpccm-cookie-suggestions-inline').html(html);
        
        // Bind buttons
        $('#add-suggested-cookies').on('click', function() {
            const selected = [];
            $('.cookie-suggestion-cb:checked').each(function() {
                selected.push($(this).data('cookie'));
            });
            
            // Add to table
            selected.forEach(function(cookie) {
                addCookieRow(cookie, 'others'); // Default category for suggestions
            });
            
            $('#wpccm-cookie-suggestions-inline').empty();
            
            // Show success
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Added ' + selected.length + ' cookies!</p></div>');
            $('#wpccm-cookie-purge-table').prepend(successMsg);
            setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 3000);
            
            // Scroll to show new items in cookies table
            const cookieContainer = $('#wpccm-cookie-purge-table .wpccm-table-container');
            if (cookieContainer.length) {
                cookieContainer.scrollTop(cookieContainer[0].scrollHeight);
            }
        });
        
        $('#cancel-cookie-suggestions').on('click', function() {
            $('#wpccm-cookie-suggestions-inline').empty();
        });
    }

    function renderCurrentCookieSuggestionsForTable(suggestions) {
        if (!suggestions.length) {
            $('#wpccm-cookie-suggestions-inline').html('<div style="background: #f0f6fc; border: 1px solid #0969da; border-radius: 4px; padding: 15px; margin-top: 15px;"><p>' + (texts.no_non_essential_cookies || 'No non-essential cookies found on this site') + '</p></div>');
            return;
        }

        let html = '<div style="background: #f0f6fc; border: 1px solid #0969da; padding: 15px; border-radius: 4px; margin-top: 15px;">';
        html += '<h4 style="margin: 0 0 10px 0; color: #0969da;">' + (texts.non_essential_cookies_found || 'Found non-essential cookies') + ' (' + suggestions.length + ')</h4>';
        html += '<div class="wpccm-table-container" style="max-height: 300px; margin-bottom: 15px;">';
        html += '<table class="widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Cookie</th>';
        html += '<th>Category</th>';
        html += '<th>Reason</th>';
        html += '<th style="width: 80px;">Add?</th>';
        html += '</tr></thead><tbody>';

        suggestions.forEach(function(item) {
            html += '<tr>';
            html += '<td><code style="background: #f8f9fa; padding: 2px 4px; border-radius: 3px;">' + escapeHtml(item.name) + '</code></td>';
            html += '<td><span style="display: inline-block; padding: 2px 8px; background: #e3f2fd; color: #1976d2; border-radius: 12px; font-size: 11px;">' + escapeHtml(item.category_display) + '</span></td>';
            html += '<td style="font-size: 12px; color: #666;">' + escapeHtml(item.reason) + '</td>';
            html += '<td><input type="checkbox" class="current-cookie-suggestion-cb" data-cookie="' + escapeHtml(item.name) + '" checked></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        html += '</div>'; // Close table container
        html += '<button type="button" class="button button-primary" id="add-current-suggested-cookies">Add Selected Cookies</button>';
        html += ' <button type="button" class="button" id="cancel-current-cookie-suggestions">Cancel</button>';
        html += '</div>';

        $('#wpccm-cookie-suggestions-inline').html(html);
        
        // Bind buttons
        $('#add-current-suggested-cookies').on('click', function() {
            const selected = [];
            $('.current-cookie-suggestion-cb:checked').each(function() {
                selected.push($(this).data('cookie'));
            });
            
            // Add to table
            selected.forEach(function(cookie) {
                addCookieRow(cookie, 'others'); // Default category for current suggestions
            });
            
            $('#wpccm-cookie-suggestions-inline').empty();
            
            // Show success
            const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>Added ' + selected.length + ' cookies from current site!</p></div>');
            $('#wpccm-cookie-purge-table').prepend(successMsg);
            setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 3000);
            
            // Scroll to show new items in cookies table
            const cookieContainer = $('#wpccm-cookie-purge-table .wpccm-table-container');
            if (cookieContainer.length) {
                cookieContainer.scrollTop(cookieContainer[0].scrollHeight);
            }
        });
        
        $('#cancel-current-cookie-suggestions').on('click', function() {
            $('#wpccm-cookie-suggestions-inline').empty();
        });
    }

    function addCookiesDirectlyToTable(suggestions) {

        
        if (!suggestions.length) {
            // Show message that no cookies were found
            const noFoundMsg = $('<div class="notice notice-info is-dismissible" style="margin: 10px 0;"><p>' + (texts.no_non_essential_cookies || 'No cookies found on this website') + '</p></div>');
            $('#wpccm-cookie-purge-table').prepend(noFoundMsg);
            setTimeout(function() { noFoundMsg.fadeOut(function() { $(this).remove(); }); }, 4000);
            return;
        }

        // Check for existing cookies to avoid duplicates
        const existingCookies = [];
        $('#wpccm-cookies-table tbody tr').each(function() {
            const cookieInput = $(this).find('.cookie-input');
            if (cookieInput.length > 0) {
                const cookieName = cookieInput.val();
                if (cookieName && typeof cookieName === 'string') {
                    const trimmedName = cookieName.trim();
                    if (trimmedName) {
                        existingCookies.push(trimmedName);
                    }
                }
            }
        });



        // Add only new cookies
        let addedCount = 0;
        suggestions.forEach(function(item) {
            if (!existingCookies.includes(item.name)) {
                addCookieRowReadOnly(item.name, item.category || 'others', item.value || ''); // Use read-only format with value
                addedCount++;
            } else {

            }
        });

        // Show success message
        let message = '';
        if (addedCount > 0) {
            message = 'Added ' + addedCount + ' cookies to purge list!';
            if (addedCount < suggestions.length) {
                const duplicates = suggestions.length - addedCount;
                message += ' (' + duplicates + ' already existed)';
            }
        } else {
            message = 'All ' + suggestions.length + ' cookies already exist in the purge list.';
        }

        const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' + message + '</p></div>');
        $('#wpccm-cookie-purge-table').prepend(successMsg);
        setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 4000);

        // Scroll to show new items in cookies table
        if (addedCount > 0) {
            const cookieContainer = $('#wpccm-cookie-purge-table .wpccm-table-container');
            if (cookieContainer.length) {
                cookieContainer.scrollTop(cookieContainer[0].scrollHeight);
            }
        }
        
        // Auto-save cookies after adding them
        if (addedCount > 0) {
            autoSaveCookies();
        }

    }

    /**
     * Add cookie row in read-only format (new style)
     */
    function addCookieRowReadOnly(cookieName, category, cookieValue) {
        const tbody = $('#wpccm-cookies-table tbody');
        
        // Remove empty table message if it exists
        const emptyMessage = tbody.find('tr td[colspan="4"]');
        if (emptyMessage.length > 0) {
            emptyMessage.closest('tr').remove();
        }
        
        // Use provided cookie value or default to empty
        cookieValue = cookieValue || '';
        
        // Truncate long values for display
        if (cookieValue.length > 50) {
            cookieValue = cookieValue.substring(0, 50) + '...';
        }
        
        // Get category display name
        const categoryDisplayNames = {
            'necessary': 'חיוני',
            'functional': 'פונקציונלי',
            'performance': 'ביצועים',
            'analytics': 'אנליטיקה',
            'advertisement': 'פרסום',
            'others': 'אחר'
        };
        const categoryDisplay = categoryDisplayNames[category] || category;
        
        const row = $('<tr>' +
            '<td><strong>' + escapeHtml(cookieName) + '</strong>' +
            '<input type="hidden" class="cookie-input" value="' + escapeHtml(cookieName) + '" />' +
            '</td>' +
            '<td><code>' + escapeHtml(cookieValue || 'N/A') + '</code></td>' +
            '<td><span class="category-badge category-' + category + '">' + escapeHtml(categoryDisplay) + '</span>' +
            '<select class="category-select" style="display: none;">' +
            '<option value="' + escapeHtml(category) + '" selected>' + escapeHtml(categoryDisplay) + '</option>' +
            '</select>' +
            '</td>' +
            '<td>' +
            '<button type="button" class="button button-small edit-category-btn" data-cookie="' + escapeHtml(cookieName) + '" data-category="' + escapeHtml(category) + '" title="ערוך קטגוריה">' +
            '<span class="dashicons dashicons-edit" style="font-size: 14px; line-height: 1;"></span>' +
            '</button>' +
            '</td>' +
            '</tr>');
        
        tbody.append(row);
        updateCookieData();
    }

    /**
     * Auto-save cookies to database
     */
    function autoSaveCookies() {
        updateCookieData(); // Update the hidden field
        
        const cookiesJson = $('#wpccm-cookies-data').val() || '[]';
        
        // Show saving indicator
        const savingMsg = $('<div class="notice notice-info" style="margin: 10px 0;"><p>שומר עוגיות...</p></div>');
        $('#wpccm-cookie-purge-table').prepend(savingMsg);
        
        $.post(WPCCM_TABLE.ajaxUrl, {
            action: 'wpccm_save_purge_cookies',
            cookies_json: cookiesJson,
            _wpnonce: WPCCM_TABLE.nonce
        }).done(function(resp) {
            savingMsg.remove();
            if (resp && resp.success) {
                const successMsg = $('<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>✓ העוגיות נשמרו אוטומטית (' + (resp.data && resp.data.saved || 0) + ')</p></div>');
                $('#wpccm-cookie-purge-table').prepend(successMsg);
                setTimeout(function() { successMsg.fadeOut(function() { $(this).remove(); }); }, 3000);
            } else {
                const errorMsg = $('<div class="notice notice-error is-dismissible" style="margin: 10px 0;"><p>✗ שגיאה בשמירה אוטומטית</p></div>');
                $('#wpccm-cookie-purge-table').prepend(errorMsg);
                setTimeout(function() { errorMsg.fadeOut(function() { $(this).remove(); }); }, 5000);
            }
        }).fail(function() {
            savingMsg.remove();
            const errorMsg = $('<div class="notice notice-error is-dismissible" style="margin: 10px 0;"><p>✗ שגיאה בחיבור לשרת</p></div>');
            $('#wpccm-cookie-purge-table').prepend(errorMsg);
            setTimeout(function() { errorMsg.fadeOut(function() { $(this).remove(); }); }, 5000);
        });
    }

    function detectCookieCategory(cookieName) {
        const name = cookieName.toLowerCase();
        
        // Necessary patterns (essential for site function)
        if (/(phpsessid|wordpress_|_wp_session|csrf_token|wp-settings|session|auth|login|user_|admin_)/i.test(name)) {
            return 'necessary';
        }
        
        // Functional patterns (WooCommerce, forms, preferences)
        if (/(woocommerce|wp-|cart_|wishlist_|currency|language|preference|compare|theme_|settings|contact|form|mailpoet)/i.test(name)) {
            return 'functional';
        }
        
        // Performance patterns
        if (/(cache_|cdn_|speed|performance|optimization|compress|minify|lazy|defer|w3tc_|wp_rocket)/i.test(name)) {
            return 'performance';
        }
        
        // Analytics patterns
        if (/(_ga|_gid|_gat|__utm|_hjid|_hjsession|ajs_|_mkto_trk|hubspot|_pk_|_omapp|__qca|optimizely|mp_|_clck|_clsk|muid|_scid|_uetvid|vuid)/i.test(name)) {
            return 'analytics';
        }
        
        // Advertisement patterns  
        if (/(_fbp|fr|_gcl_|ide|test_cookie|dsid|__gads|__gpi|_gac_|anid|nid|1p_jar|apisid|hsid|sapisid|sid|sidcc|ssid|_pinterest|uuid2|sess|anj|usersync|tdcpm|tdid|tuuid|ouuid|_cc_)/i.test(name)) {
            return 'advertisement';
        }
        
        // Social media patterns
        if (/(twitter_|personalization_id|guest_id|datr|sb|wd|xs|c_user|li_sugr|lidc|bcookie|bscookie|ysc|visitor_info)/i.test(name)) {
            return 'others';
        }
        
        // Default to others for anything unrecognized
        return 'others';
    }

    function getCategoryFromMapping(cookieName) {

        // Check if this cookie is mapped in the handle mapping table
        let foundCategory = '';
        
        const handleTableRows = $('#wpccm-handles-table tbody tr');

        
        handleTableRows.each(function() {
            const cookiesStr = $(this).find('.cookies-input-manual').val().trim();
            const category = $(this).find('.category-select').val();
            

            
            if (cookiesStr && category) {
                const cookieList = cookiesStr.split(',').map(c => c.trim()).filter(c => c);

                
                // Check for exact match or wildcard match
                const isMatch = cookieList.some(function(mappedCookie) {
                    if (mappedCookie.includes('*')) {
                        // Wildcard match
                        const pattern = mappedCookie.replace(/\*/g, '.*');
                        const regex = new RegExp('^' + pattern + '$', 'i');
                        const matches = regex.test(cookieName);

                        return matches;
                    } else {
                        // Exact match
                        const matches = mappedCookie.toLowerCase() === cookieName.toLowerCase();

                        return matches;
                    }
                });
                
                if (isMatch) {
                    foundCategory = category;

                    return false; // Break the loop
                }
            }
        });
        

        return foundCategory;
    }

    function categorizeLiveScript(handle, src) {
        const text = (handle + ' ' + src).toLowerCase();
        
        // Analytics patterns
        if (/google.*analytics|gtag|gtm|_ga|analytics|mixpanel|segment|hotjar|matomo|piwik|tracking|statistics/.test(text)) {
            return 'analytics';
        }
        
        // Advertisement patterns  
        if (/facebook.*pixel|fbevents|adnexus|doubleclick|googleads|adsense|criteo|outbrain|taboola|advertising|marketing|campaign/.test(text)) {
            return 'advertisement';
        }
        
        // Performance patterns
        if (/cache|cdn|speed|performance|optimization|compress|minify|lazy|defer/.test(text)) {
            return 'performance';
        }
        
        // Functional patterns
        if (/woo|commerce|cart|checkout|form|contact|mail|newsletter|slider|gallery|popup|modal/.test(text)) {
            return 'functional';
        }
        
        // Default
        return 'others';
    }

    function getSavedCookiesForHandle(handle) {
        // Check if we have saved cookie mapping in hidden field
        const savedCookieMapping = $('#wpccm-cookie-mapping-data').val();
        if (savedCookieMapping) {
            try {
                const cookieMap = JSON.parse(savedCookieMapping);
                if (cookieMap[handle]) {
                    return cookieMap[handle];
                }
            } catch (e) {

            }
        }
        
        // Also check existing table rows to get already saved values
        let foundCookies = '';
        $('#wpccm-handles-table tbody tr').each(function() {
            const existingHandle = $(this).find('.handle-input').val().trim();
            if (existingHandle === handle) {
                foundCookies = $(this).find('.cookies-input-manual').val().trim();
                return false; // break loop
            }
        });
        
        return foundCookies;
    }

    function getRelatedCookiesForHandle(handle) {
        const handleCookies = {
            'google-analytics': '_ga, _gid, _gat',
            'gtag': '_ga, _gid, _gat_*',
            'facebook-pixel': '_fbp, fr',
            'woocommerce': 'woocommerce_*',
            'mailpoet': 'mailpoet_*',
            'contact-form-7': 'cf7_*',
            'hotjar': '_hjid, _hjSessionUser',
            'mixpanel': 'mp_*',
            'segment': 'ajs_*',
            'hubspot': '__hstc, hubspotutk',
            'linkedin': 'li_sugr, lidc',
            'pinterest': '_pinterest_*',
            'twitter': 'personalization_id',
            'youtube': 'VISITOR_INFO1_LIVE',
            'criteo': 'cto_*',
            'doubleclick': 'IDE, test_cookie',
            'adsense': '__gads, __gpi'
        };

        if (handleCookies[handle]) {
            return handleCookies[handle];
        }

        // Try to guess based on handle patterns
        const lowerHandle = handle.toLowerCase();
        if (lowerHandle.includes('analytics') || lowerHandle.includes('ga')) {
            return '_ga, _gid';
        }
        if (lowerHandle.includes('facebook') || lowerHandle.includes('fb')) {
            return '_fbp, fr';
        }
        if (lowerHandle.includes('woo') || lowerHandle.includes('commerce')) {
            return 'woocommerce_*';
        }
        if (lowerHandle.includes('pixel')) {
            return 'tracking cookies';
        }

        return texts.no_related_cookies || 'No related cookies found';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
        }); // End jQuery document ready


    } // End initTableManager function
    
    const wpccmModule = window.WPCCM || (window.WPCCM = {});
    wpccmModule.TableManager = {
        init: initTableManager,
        forceScan: function() {
            if (typeof jQuery === 'undefined') {
                return;
            }
            jQuery('#wpccm-scan-live-handles').trigger('click');
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTableManager);
    } else {
        initTableManager();
    }

})(window);

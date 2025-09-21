(function(){

    // Add immediate console log to see if script loads at all

// Helper functions
function getCookie(name) {
    // Escape special regex characters in the cookie name
    var escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    var match = document.cookie.match(new RegExp('(^| )' + escapedName + '=([^;]+)'));
    var result = match ? match[2] : null;
    return result;
}

function getDefaultConsentState() {
    return {
        necessary: true,
        functional: false,
        performance: false,
        analytics: false,
        advertisement: false,
        others: false
    };
}

function setCookie(name, value, days) {
    var expires = '';
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
    }
    
    // Try different cookie settings to ensure compatibility
    var cookieStrings = [
        // Basic cookie without SameSite
        name + '=' + (value || '') + expires + '; path=/',
        // With SameSite=Lax
        name + '=' + (value || '') + expires + '; path=/; SameSite=Lax',
        // Without path
        name + '=' + (value || '') + expires,
        // With domain (if available)
        name + '=' + (value || '') + expires + '; path=/; domain=' + window.location.hostname
    ];
    
    console.log('WPCCM: Setting cookie:', name, '=', value, 'days:', days);
    
    // Try to set the cookie with different settings
    var cookieSet = false;
    for (var i = 0; i < cookieStrings.length; i++) {
        try {
            console.log('WPCCM: Trying cookie string:', cookieStrings[i]);
            document.cookie = cookieStrings[i];
            
            // Check if it was set
            var testValue = getCookie(name);
            if (testValue !== null) {
                console.log('WPCCM: Cookie set successfully with string', i + 1);
                cookieSet = true;
                break;
            }
        } catch (e) {
            console.log('WPCCM: Failed to set cookie with string', i + 1, 'Error:', e);
        }
    }
    
    if (!cookieSet) {
        console.error('WPCCM: Failed to set cookie:', name, 'with any method');
    }
    
    // Verify cookie was set
    setTimeout(function() {
        var savedValue = getCookie(name);
        console.log('WPCCM: Cookie verification -', name, '=', savedValue, 'Expected:', value);
    }, 100);
}

function deleteCookie(name) {
    console.log('Deleting cookie:', name);
    
    var methods = [
        name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin_gpt5/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin_gpt5/wp-admin; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin_gpt5/wp-content/plugins; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin_gpt5/wp-content/plugins/wp-cookie-consent-manager; Expires=Thu, 01 Jan 1970 00:00:01 GMT;'
    ];
    
    for (var i = 0; i < methods.length; i++) {
        console.log('Trying method', i + 1, ':', methods[i]);
        document.cookie = methods[i];
        
        // // Check if cookie was deleted
        // setTimeout(function(methodIndex) {
        //     var cookieExists = getCookie(name);
        //     if (cookieExists === null) {
        //         console.log('Method', methodIndex + 1, 'successful - cookie deleted');
        //     } else {
        //         console.log('Method', methodIndex + 1, 'failed - cookie still exists');
        //     }
        // }, 100, i);
    }
}

function ensureConsentCookies(state) {
    if (!state || typeof state !== 'object') {
        return;
    }

    var days = 180;

    Object.keys(state).forEach(function(key) {
        var cookieName = 'consent_' + key;
        setCookie(cookieName, state[key] ? '1' : '0', days);
    });
}

function deleteCookieViaServer(name) {
    console.log('Deleting cookie via server:', name);
    
    if (typeof WPCCM === 'undefined' || !WPCCM.ajaxUrl) {
        console.error('WPCCM AJAX URL not found');
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    console.log('Cookie deleted via server:', name);
                } else {
                    console.error('Failed to delete cookie via server:', response.message);
                }
            } catch (e) {
                console.error('Error parsing server response:', e);
            }
        } else {
            console.error('Server error deleting cookie:', xhr.status);
        }
    };
    
    xhr.onerror = function() {
        console.error('Network error deleting cookie via server');
    };
    
    xhr.send('action=wpccm_delete_cookies&cookie_name=' + encodeURIComponent(name) + '&nonce=' + (WPCCM.nonce || ''));
}

function currentState() {
    var state = {};
    
    // Get categories from WPCCM global or use defaults
    var categories = [];
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories) && WPCCM.categories.length > 0) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories
        categories = [
            {key: 'necessary', required: true},
            {key: 'functional', required: false},
            {key: 'performance', required: false},
            {key: 'analytics', required: false},
            {key: 'advertisement', required: false},
            {key: 'others', required: false}
        ];
    }

    
    categories.forEach(function(cat) {
        // Always include all categories in state, regardless of enabled status
        if (cat.required) {
            state[cat.key] = true; // Always true for required
        } else {
            var cookieValue = getCookie('consent_' + cat.key);
            if (cookieValue === null && typeof localStorage !== 'undefined') {
                try {
                    var persistedState = JSON.parse(localStorage.getItem('wpccm_consent_state') || '{}');
                    if (typeof persistedState[cat.key] !== 'undefined') {
                        cookieValue = persistedState[cat.key] ? '1' : '0';
                    }
                } catch (e) {}
            }

            state[cat.key] = cookieValue === '1';
        }
    });

    return state;
}


function normalizeConsentForStore(state) {
    var baselineKeys = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
    var normalized = {};

    baselineKeys.forEach(function(key) {
        if (key === 'necessary') {
            normalized[key] = true;
            return;
        }

        var value = false;
        if (typeof state[key] !== 'undefined') {
            value = state[key] === true || state[key] === '1' || state[key] === 'true';
        }

        normalized[key] = value;
    });

    return normalized;
}


function applyConsentStateToDom(state) {
    if (!state || typeof state !== 'object') {
        return;
    }

    var normalized = normalizeConsentForStore(state);

    try {
        var scriptNodes = document.querySelectorAll('script[data-cc]');

        scriptNodes.forEach(function(placeholder) {
            var category = placeholder.getAttribute('data-cc');
            if (!category) {
                return;
            }

            var allowed = !!normalized[category];
            var isPlaceholder = placeholder.getAttribute('type') === 'text/plain' ||
                                placeholder.hasAttribute('data-src') ||
                                placeholder.getAttribute('data-inline-blocked') === '1';

            if (allowed) {
                if (!isPlaceholder) {
                    // Already active script, nothing to do
                    return;
                }

                var newScript = document.createElement('script');

                Array.from(placeholder.attributes).forEach(function(attr) {
                    var name = attr.name;
                    var value = attr.value;

                    if (name === 'type' ||
                        name === 'data-cc-blocked' ||
                        name === 'data-inline-blocked' ||
                        name === 'data-cc-processed') {
                        return;
                    }

                    if (name === 'data-src') {
                        if (value) {
                            newScript.src = value;
                        }
                        return;
                    }

                    if (name === 'style') {
                        return;
                    }

                    newScript.setAttribute(name, value);
                });

                if (!newScript.src) {
                    newScript.text = placeholder.text || placeholder.textContent || '';
                }

                newScript.setAttribute('data-cc', category);
                newScript.removeAttribute('data-cc-blocked');
                newScript.removeAttribute('data-inline-blocked');
                newScript.setAttribute('data-cc-processed', '1');
                newScript.removeAttribute('style');
                newScript.style.display = '';

                placeholder.parentNode.replaceChild(newScript, placeholder);
            } else {
                // Block script by converting to placeholder
                if (placeholder.getAttribute('type') !== 'text/plain') {
                    var currentSrc = placeholder.getAttribute('src');
                    if (currentSrc) {
                        placeholder.setAttribute('data-src', currentSrc);
                        placeholder.removeAttribute('src');
                    }
                    placeholder.setAttribute('type', 'text/plain');
                }

                placeholder.setAttribute('data-cc-blocked', 'true');
                if (!placeholder.hasAttribute('data-inline-blocked') && !placeholder.hasAttribute('data-src')) {
                    placeholder.setAttribute('data-inline-blocked', '1');
                }
                placeholder.style.display = 'none';
            }
        });

        var iframeNodes = document.querySelectorAll('iframe[data-cc]');

        iframeNodes.forEach(function(iframe) {
            var category = iframe.getAttribute('data-cc');
            if (!category) {
                return;
            }

            var allowed = !!normalized[category];

            if (allowed) {
                if (iframe.getAttribute('data-cc-blocked') === 'true') {
                    var storedSrc = iframe.getAttribute('data-src');
                    if (storedSrc) {
                        iframe.src = storedSrc;
                    }
                    iframe.removeAttribute('data-cc-blocked');
                    iframe.style.display = '';
                }
            } else {
                if (!iframe.hasAttribute('data-src')) {
                    iframe.setAttribute('data-src', iframe.src || '');
                }
                iframe.src = '';
                iframe.setAttribute('data-cc-blocked', 'true');
                iframe.style.display = 'none';
            }
        });
    } catch (e) {
        console.warn('WPCCM: Failed to apply consent state to DOM', e);
    }
}


function applyPendingConsentState() {
    var pendingState = getDefaultConsentState();

    ensureConsentCookies(pendingState);

    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.setItem('wpccm_consent_state', JSON.stringify(pendingState));
            localStorage.removeItem('wpccm_consent_resolved');
        } catch (e) {}
    }

    // Ensure main consent cookies indicating resolution are cleared
    deleteCookie('wpccm_consent_resolved');
    deleteCookie('wpccm_resolved');
    deleteCookie('wpccm_simple');

    applyConsentStateToDom(pendingState);
}


function storeNewState(state) {
    var days = 180; // Default cookie expiry days
    
    console.log('WPCCM: Storing new state:', state);
    
    // Store state for all defined categories
    Object.keys(state).forEach(function(key) {
        setCookie('consent_' + key, state[key] ? '1' : '0', days);
    });

    // Set main consent cookie to indicate user has made a choice
    console.log('WPCCM: Setting consent resolved cookie');
    setCookie('wpccm_consent_resolved', '1', days);
    
    // Also set a simpler cookie as backup
    setCookie('wpccm_resolved', '1', days);
    
    // Persist consent state to localStorage for additional reliability
    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.setItem('wpccm_consent_resolved', '1');
            localStorage.setItem('wpccm_consent_state', JSON.stringify(state));
        } catch (e) {
            console.warn('WPCCM: Failed to persist consent state to localStorage', e);
        }
    }

    // Try direct cookie setting as last resort
    try {
        document.cookie = 'wpccm_simple=1; path=/; max-age=' + (days * 24 * 60 * 60);
        console.log('WPCCM: Set simple cookie directly');
    } catch (e) {
        console.error('WPCCM: Failed to set simple cookie:', e);
    }
    
    // Verify cookie was set
    setTimeout(function() {
        var resolvedCookie = getCookie('wpccm_consent_resolved');
        var simpleCookie = getCookie('wpccm_simple');
        console.log('WPCCM: Consent resolved cookie verification:', resolvedCookie);
        console.log('WPCCM: Simple cookie verification:', simpleCookie);
        console.log('WPCCM: All cookies:', document.cookie);
    }, 200);

    // Synchronize with consent store for real-time script handling
    var normalizedConsent = normalizeConsentForStore(state);

    applyConsentStateToDom(normalizedConsent);

    if (typeof window !== 'undefined' && typeof window.setConsent === 'function') {
        try {
            window.setConsent(normalizedConsent);
        } catch (e) {
            console.warn('WPCCM: Failed to update consent store via setConsent', e);
        }
    } else {
        try {
            var consentEvent = new CustomEvent('cc:changed', {
                detail: { preferences: normalizedConsent }
            });
            document.dispatchEvent(consentEvent);

            if (typeof window !== 'undefined' && window.ccLoader && typeof window.ccLoader.rehydrateAllElements === 'function') {
                window.ccLoader.rehydrateAllElements();
            }
        } catch (e) {
            console.warn('WPCCM: Failed to dispatch consent change event fallback', e);
        }
    }
}

function isResolved(state) {
    // Check if user has made a consent decision by looking for the main consent cookie
    var hasNewConsentCookie = getCookie('wpccm_consent_resolved') === '1';
    var hasSimpleConsentCookie = getCookie('wpccm_resolved') === '1';
    var hasDirectCookie = getCookie('wpccm_simple') === '1';
    var hasLocalStorageFlag = false;

    if (typeof localStorage !== 'undefined') {
        try {
            var lsFlag = localStorage.getItem('wpccm_consent_resolved');
            hasLocalStorageFlag = lsFlag === '1' || lsFlag === 'true';
        } catch (e) {}
    }
    
    // For backward compatibility, also check if old wpccm_consent cookie exists
    var hasOldConsentCookie = getCookie('wpccm_consent') !== null;
    
    var resolved = hasNewConsentCookie || hasSimpleConsentCookie || hasDirectCookie || hasOldConsentCookie || hasLocalStorageFlag;
    console.log('WPCCM: isResolved check - new:', hasNewConsentCookie, 'simple:', hasSimpleConsentCookie, 'direct:', hasDirectCookie, 'old:', hasOldConsentCookie, 'localStorage:', hasLocalStorageFlag, 'resolved:', resolved);
    
    return resolved;
}

function activateDeferredScripts() {
    // var scripts = document.querySelectorAll('script[type="text/plain"][data-consent]');
    // var state = currentState();
    
    // scripts.forEach(function(script) {
    //     var category = script.getAttribute('data-consent');
    //     if (state[category]) {
    //         var newScript = document.createElement('script');
            
    //         // Copy attributes
    //         Array.from(script.attributes).forEach(function(attr) {
    //             if (attr.name !== 'type' && attr.name !== 'data-consent') {
    //                 newScript.setAttribute(attr.name, attr.value);
    //             }
    //         });
            
    //         // Handle src vs inline
    //         var dataSrc = script.getAttribute('data-src');
    //         if (dataSrc) {
    //             newScript.src = dataSrc;
    //         } else {
    //             newScript.innerHTML = script.innerHTML;
    //         }
            
    //         script.parentNode.replaceChild(newScript, script);
    //     }
    // });
}

function purgeOnReject() {
    console.log('WPCCM: purgeOnReject() called - deleting cookies from rejected categories');

    // Use the same smart cookie deletion logic as in purgeNonEssentialCookies
    if (typeof WPCCM !== 'undefined' && WPCCM.cookies && Array.isArray(WPCCM.cookies)) {
        var userConsentState = currentState();

        WPCCM.cookies.forEach(function(cookieInfo) {
            var cookieName = cookieInfo.name || cookieInfo.cookie_name;
            var cookieCategory = cookieInfo.category || 'others';

            // Only delete cookies from categories that user has NOT approved
            // Skip 'necessary' category as it's always approved
            if (cookieCategory !== 'necessary' && !userConsentState[cookieCategory]) {
                console.log('WPCCM: Deleting cookie from rejected category:', cookieName, 'category:', cookieCategory);

                // Handle wildcard cookies (e.g., woocommerce_*)
                if (cookieName && cookieName.includes('*')) {
                    var prefix = cookieName.replace('*', '');
                    var cookies = document.cookie.split(';');
                    cookies.forEach(function(cookie) {
                        var name = cookie.split('=')[0].trim();
                        if (name.startsWith(prefix)) {
                            console.log('WPCCM: Purging wildcard cookie:', name);
                            deleteCookie(name);
                        }
                    });
                } else if (cookieName) {
                    console.log('WPCCM: Purging cookie:', cookieName);
                    deleteCookie(cookieName);
                }
            }
        });
    }
    
    // Clear localStorage items
    try { 
        localStorage.removeItem('_ga'); 
        localStorage.removeItem('_gid');
    } catch(e) {}
}

function generateCategoryToggles(texts, cookiesByCategory, designSettings) {
    
    // Get categories from WPCCM global or use defaults
    var categories = [];
    
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories
        categories = [
            {key: 'necessary', name: texts.necessary || 'Necessary', required: true, description: 'Essential cookies required for basic site functionality.', enabled: true},
            {key: 'functional', name: texts.functional || 'Functional', required: false, description: 'Cookies that enhance your experience by remembering your preferences.', enabled: true},
            {key: 'performance', name: texts.performance || 'Performance', required: false, description: 'Cookies that help us optimize our website performance.', enabled: true},
            {key: 'analytics', name: texts.analytics || 'Analytics', required: false, description: 'Cookies that help us understand how visitors interact with our website.', enabled: true},
            {key: 'advertisement', name: texts.advertisement || 'Advertisement', required: false, description: 'Cookies used to deliver personalized advertisements.', enabled: true},
            {key: 'others', name: texts.others || 'Others', required: false, description: 'Other cookies that do not fit into the above categories.', enabled: true}
        ];
    }
    
    cookiesByCategory = cookiesByCategory || {};
    
    var html = '';
    categories.forEach(function(cat) {
        // Skip disabled categories
        if (cat.enabled === false) {
            return;
        }
        
        // Check current consent state for this category
        var storedConsentValue = getCookie('consent_' + cat.key);

        if ((!storedConsentValue || storedConsentValue === null) && typeof localStorage !== 'undefined') {
            try {
                var persisted = JSON.parse(localStorage.getItem('wpccm_consent_state') || '{}');
                if (typeof persisted[cat.key] !== 'undefined') {
                    storedConsentValue = persisted[cat.key] ? '1' : '0';
                }
            } catch (e) {}
        }

        var currentConsent = storedConsentValue === '1' || storedConsentValue === 'true';
        var isChecked = cat.required || currentConsent;
        
        var checked = isChecked ? 'checked' : '';
        var disabled = cat.required ? 'disabled' : '';
        var statusText = cat.required ? (texts.always_enabled || 'Always Enabled') : 
                        (isChecked ? (texts.enabled || 'Enabled') : (texts.disabled || 'Disabled'));
        
        // Generate cookies list for this category
        var cookiesHtml = '';
        var categoryData = cookiesByCategory[cat.key];
            
        // Handle both old format (array) and new format (object with cookies/scripts)
        var cookies = [];
        var scripts = [];
        
        if (Array.isArray(categoryData)) {
            cookies = categoryData;
        } else if (categoryData && typeof categoryData === 'object') {
            cookies = categoryData.cookies || [];
            scripts = categoryData.scripts || [];
        }
        
        // Build content for cookies and scripts
        var contentHtml = '';
        
        // Show cookies from server data (includes scanned cookies from database)
        if (cookies && cookies.length > 0) {
            contentHtml += '<div class="wpccm-cookies-list" style="margin-top: 10px; font-size: 12px; color: #666;">' +
                '<strong>🍪 ' + (texts.cookies_in_category || 'עוגיות בקטגוריה זו:') + '</strong><br>' +
                '<div class="wpccm-cookie-tags">';
            
            cookies.forEach(function(cookieName) {
                contentHtml += '<span class="wpccm-cookie-tag" style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px;">' + escapeHtml(cookieName) + '</span>';
            });
            
            contentHtml += '</div></div>';
        }
        
        // Show scripts from server data
        if (scripts && scripts.length > 0) {
            contentHtml += '<div class="wpccm-scripts-list" style="margin-top: 10px; font-size: 12px; color: #666;">' +
                '<strong>📜 ' + (texts.scripts_in_category || 'סקריפטים בקטגוריה זו:') + '</strong><br>' +
                '<div class="wpccm-script-tags">';
            
            scripts.forEach(function(scriptName) {
                contentHtml += '<span class="wpccm-script-tag" style="background: #f3e5f5; color: #7b1fa2; padding: 2px 6px; margin: 2px; border-radius: 3px; font-size: 11px;">' + escapeHtml(scriptName) + '</span>';
            });
            
            contentHtml += '</div></div>';
        }
        
        // If no cookies or scripts, show message
        if ((!cookies || cookies.length === 0) && (!scripts || scripts.length === 0)) {
            contentHtml = '<div class="wpccm-empty-category" style="margin-top: 10px; font-size: 12px; color: #999; font-style: italic;">' +
                (texts.no_content_found || 'אין עוגיות או סקריפטים בקטגוריה זו כרגע') +
                '</div>';
            console.log('WPCCM: No content found for category:', cat.key);
        }
        
        cookiesHtml = contentHtml;

        var baseTextColor = (designSettings && designSettings.textColor) ? designSettings.textColor : '#333333';
        var isTextWhite = baseTextColor === '#ffffff' || baseTextColor === '#fff' || baseTextColor === '#FFFFFF';
        var arrowBackground = isTextWhite ? 'rgba(255,255,255,0.1)' : '#f1f3f5';
        var arrowColor = isTextWhite ? '#ffffff' : baseTextColor;

        html += '<div class="wpccm-category" data-category="' + cat.key + '">' +
            '<div class="wpccm-category-header" style="display: flex; align-items: center; gap: 12px; justify-content: space-between;">' +
            '<button type="button" class="wpccm-category-summary" aria-expanded="false" style="flex: 1; display: flex; align-items: center; gap: 10px; flex-direction: row-reverse; background: none; border: none; padding: 0; color: inherit; cursor: pointer; text-align: right;">' +
            
            '<div class="wpccm-category-text" style="flex: 1; text-align: right;">' +
            '<h4 style="margin: 0;">' + escapeHtml(cat.name) + '</h4>' +
            '<p style="margin: 4px 0 0; font-size: 12px;">' + escapeHtml(cat.description) + '</p>' +
            '</div>' +
            '<span class="wpccm-category-arrow" aria-hidden="true" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: ' + arrowBackground + '; color: ' + arrowColor + '; font-size: 14px; transition: transform 0.2s ease;">▾</span>' +
            '</button>' +
            '<div class="wpccm-category-toggle">' +
            '<span class="wpccm-toggle-status">' + statusText + '</span>' +
            '<label class="wpccm-switch">' +
            '<input type="checkbox" data-category="' + escapeHtml(cat.key) + '" ' + checked + ' ' + disabled + '>' +
            '<span class="wpccm-slider"></span>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '<div class="wpccm-category-details" style="display: none; margin-top: 10px; padding: 15px; background: ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; border-radius: 6px; border: 1px solid ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; color: ' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + ';">' +
            '<div class="wpccm-details-table" style="display: flex; gap: 20px; direction: rtl;">' +
            '<div class="wpccm-scripts-column" style="flex: 1;">' +
            '<h5 style="margin: 0 0 10px 0; color: ' + (designSettings.textColor === '#ffffff' ? '#e8b4f8' : '#7b1fa2') + '; font-size: 13px; text-align: right;">📜 סקריפטים</h5>' +
            '<div class="wpccm-scripts-list">';
        
        // Add scripts
        if (scripts && scripts.length > 0) {
            scripts.forEach(function(scriptName) {
                html += '<div style="background: ' + (designSettings.textColor === '#ffffff' ? '#4a2c5a' : '#f3e5f5') + '; color: ' + (designSettings.textColor === '#ffffff' ? '#e8b4f8' : '#7b1fa2') + '; padding: 4px 8px; margin: 3px 0; border-radius: 3px; font-size: 11px; border-right: 3px solid ' + (designSettings.textColor === '#ffffff' ? '#e8b4f8' : '#7b1fa2') + '; text-align: right;">' + 
                        escapeHtml(scriptName) + '</div>';
            });
        } else {
            html += '<div style="color: ' + (designSettings.textColor === '#ffffff' ? '#bbb' : '#999') + '; font-style: italic; font-size: 11px; text-align: right;">אין סקריפטים</div>';
        }
        
        html += '</div>' + // Close scripts-list
            '</div>' + // Close scripts-column
            '<div class="wpccm-cookies-column" style="flex: 1;">' +
            '<h5 style="margin: 0 0 10px 0; color: ' + (designSettings.textColor === '#ffffff' ? '#87ceeb' : '#1976d2') + '; font-size: 13px; text-align: right;">🍪 עוגיות</h5>' +
            '<div class="wpccm-cookies-list">';
        
        // Add cookies
        if (cookies && cookies.length > 0) {
            cookies.forEach(function(cookieName) {
                html += '<div style="background: ' + (designSettings.textColor === '#ffffff' ? '#2c4a5a' : '#e3f2fd') + '; color: ' + (designSettings.textColor === '#ffffff' ? '#87ceeb' : '#1976d2') + '; padding: 4px 8px; margin: 3px 0; border-radius: 3px; font-size: 11px; border-right: 3px solid ' + (designSettings.textColor === '#ffffff' ? '#87ceeb' : '#1976d2') + '; text-align: right;">' + 
                        escapeHtml(cookieName) + '</div>';
            });
        } else {
            html += '<div style="color: ' + (designSettings.textColor === '#ffffff' ? '#bbb' : '#999') + '; font-style: italic; font-size: 11px; text-align: right;">אין עוגיות</div>';
        }
        
        html += '</div>' + // Close cookies-list
            '</div>' + // Close cookies-column
            '</div>' + // Close details-table
            '</div>' + // Close category-details
            '</div>'; // Close category
    });
    
    return html;
}

function renderBanner(){
    var root = document.getElementById('wpccm-banner-root');
    
    if (!root) {
        return;
    }
    
    // Safe access to WPCCM global
    if (typeof WPCCM === 'undefined') {
        return;
    }

    var o = (WPCCM && WPCCM.options) ? WPCCM.options : {};
    var b = o.banner || {};
    var texts = (WPCCM && WPCCM.texts) ? WPCCM.texts : {};

    // Get design settings from data attributes
    var designSettings = {
        bannerPosition: root.getAttribute('data-banner-position') || 'top',
        floatingPosition: root.getAttribute('data-floating-position') || 'bottom-right',
        backgroundColor: root.getAttribute('data-background-color') || '#ffffff',
        textColor: root.getAttribute('data-text-color') || '#000000',
        acceptButtonColor: root.getAttribute('data-accept-button-color') || '#0073aa',
        rejectButtonColor: root.getAttribute('data-reject-button-color') || '#6c757d',
        settingsButtonColor: root.getAttribute('data-settings-button-color') || '#28a745',
        dataDeletionButtonColor: root.getAttribute('data-data-deletion-button-color') || '#dc3545',
        size: root.getAttribute('data-size') || 'medium',
        padding: root.getAttribute('data-padding') || '15px',
        fontSize: root.getAttribute('data-font-size') || '14px',
        buttonPadding: root.getAttribute('data-button-padding') || '8px 16px'
    };
    


    // Detect language for RTL support
    var isHebrew = (WPCCM && WPCCM.texts && WPCCM.texts.functional_required && WPCCM.texts.functional_required.indexOf('נדרש') > -1);
    var langAttr = isHebrew ? ' data-lang="he"' : '';

    // Create the small top banner first with dynamic styles
    var bannerStyle = (designSettings.bannerPosition === 'bottom' ? 'top: auto ; bottom: 0 ; border-top: 1px solid #dee2e6 ; border-bottom: none ;' : 'top: 0 ;') +
    'background-color: ' + designSettings.backgroundColor + ' ; color: ' + designSettings.textColor + ' ; padding: ' + designSettings.padding + ' ; font-size: ' + designSettings.fontSize + ' ;';
    //for class condition
    var _class = (designSettings.textColor === '#ffffff' ? 'dark-theme' : 'light-theme');


    
    var topBannerHtml = ''+
    '<div class="wpccm-top-banner '+_class+' banner_'+designSettings.bannerPosition+'"' + langAttr + ' role="dialog" aria-live="polite" style="' + bannerStyle + '">\n' +
    ' <div class="wpccm-top-content " style="max-width: 1200px; margin: 0 auto; padding: 12px 20px; display: flex; flex-direction: row-reverse; align-items: center; justify-content: space-between; gap: 20px;">\n' +
    ' <div class="wpccm-left-actions" style="display: flex; align-items: center;">\n' +
    ' <div class="wpccm-top-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">\n' +
    ' <button class="wpccm-btn-data-deletion" id="wpccm-data-deletion-btn" style="background-color: transparent ; color: ' + designSettings.dataDeletionButtonColor + ' ; border: 1px solid ' + designSettings.dataDeletionButtonColor + ' ; padding: ' + designSettings.buttonPadding + ' ; cursor: pointer ; font-size: ' + designSettings.fontSize + ' ; transition: all 0.3s ease ; display: flex ; align-items: center ; justify-content: center ; gap: 5px;"><svg xmlns="http://www.w3.org/2000/svg" style="width: ' + (designSettings.size === 'small' ? '16px' : designSettings.size === 'large' ? '24px' : '20px') + '; height: ' + (designSettings.size === 'small' ? '16px' : designSettings.size === 'large' ? '24px' : '20px') + ';" viewBox="0 0 20 20" fill="none"><path d="M1 1L9 9.5M12.554 9.085C15.034 10.037 17.017 9.874 19 9.088C18.5 15.531 15.496 18.008 11.491 19C11.491 19 8.474 16.866 8.039 11.807C7.992 11.259 7.969 10.986 8.082 10.677C8.196 10.368 8.42 10.147 8.867 9.704C9.603 8.976 9.97 8.612 10.407 8.52C10.844 8.43 11.414 8.648 12.554 9.085Z" stroke=' + designSettings.dataDeletionButtonColor + ' stroke-linecap="round" stroke-linejoin="round"/><path d="M17.5 14.446C17.5 14.446 15 14.93 12.5 13" stroke=' + designSettings.dataDeletionButtonColor + ' stroke-linecap="round" stroke-linejoin="round"/><path d="M13.5 5.25C13.5 5.58152 13.6317 5.89946 13.8661 6.13388C14.1005 6.3683 14.4185 6.5 14.75 6.5C15.0815 6.5 15.3995 6.3683 15.6339 6.13388C15.8683 5.89946 16 5.58152 16 5.25C16 4.91848 15.8683 4.60054 15.6339 4.36612C15.3995 4.1317 15.0815 4 14.75 4C14.4185 4 14.1005 4.1317 13.8661 4.36612C13.6317 4.60054 13.5 4.91848 13.5 5.25Z" stroke=' + designSettings.dataDeletionButtonColor + '/><path d="M11 2V2.1" stroke=' + designSettings.dataDeletionButtonColor + ' stroke-linecap="round" stroke-linejoin="round"/></svg> ניקוי היסטוריה </button>\n' +
    ' <button class="wpccm-btn-reject" id="wpccm-reject-all-btn" style="background-color: transparent ; color: ' + designSettings.textColor + ' ; border: 1px solid ' + designSettings.textColor + ' ; padding: ' + designSettings.buttonPadding + ' ; font-size: ' + designSettings.fontSize + ' ;">' + (texts.reject_all || 'דחה הכל') + '</button>\n' +
    ' <button class="wpccm-btn-accept" id="wpccm-accept-all-btn" style="    background-color: transparent ; color:'+ designSettings.acceptButtonColor + ' ; border:1px solid ' + designSettings.acceptButtonColor + ' ; padding: ' + designSettings.buttonPadding + ' ; font-size: ' + designSettings.fontSize + ' ;">' + (texts.accept_all || 'קבל הכל') + '</button>\n' +
    ' </div>\n' +
    ' <button class="wpccm-btn-settings" id="wpccm-settings-btn" style="border-color: ' + designSettings.settingsButtonColor + ' ; width: ' + (designSettings.size === 'small' ? '32px' : designSettings.size === 'large' ? '48px' : '40px') + ' ; height: ' + (designSettings.size === 'small' ? '32px' : designSettings.size === 'large' ? '48px' : '40px') + ' ;" ><svg xmlns="http://www.w3.org/2000/svg" style="width: ' + (designSettings.size === 'small' ? '16px' : designSettings.size === 'large' ? '24px' : '20px') + '; height: ' + (designSettings.size === 'small' ? '16px' : designSettings.size === 'large' ? '24px' : '20px') + ';" viewBox="0 0 20 20" fill="none"><path d="M8.54149 7.47418C8.20711 7.6643 7.91364 7.91868 7.67796 8.22268C7.44229 8.52667 7.26908 8.87429 7.1683 9.2455C7.06752 9.61671 7.04116 10.0042 7.09074 10.3856C7.14032 10.7671 7.26485 11.1349 7.45718 11.4681C7.64951 11.8012 7.90583 12.093 8.21139 12.3266C8.51694 12.5603 8.86569 12.7312 9.23757 12.8295C9.60944 12.9278 9.99709 12.9516 10.3782 12.8995C10.7593 12.8474 11.1263 12.7204 11.4582 12.5259C12.1226 12.1363 12.606 11.4998 12.8029 10.7552C12.9997 10.0106 12.8941 9.21833 12.509 8.55133C12.1239 7.88432 11.4906 7.39672 10.7473 7.19492C10.004 6.99312 9.21104 7.09351 8.54149 7.47418ZM8.19566 11.0417C8.05671 10.8047 7.96601 10.5425 7.92879 10.2703C7.89157 9.99806 7.90856 9.72117 7.97879 9.45555C8.04901 9.18992 8.17109 8.94081 8.33798 8.72256C8.50487 8.50431 8.71329 8.32122 8.95123 8.18384C9.18917 8.04647 9.45193 7.95751 9.72439 7.92209C9.99685 7.88668 10.2736 7.90551 10.5388 7.9775C10.8039 8.04948 11.0522 8.17321 11.2694 8.34154C11.4865 8.50988 11.6682 8.71951 11.804 8.95835C12.0759 9.4366 12.1476 10.003 12.0035 10.5339C11.8593 11.0648 11.511 11.5172 11.0346 11.7923C10.5582 12.0673 9.99228 12.1428 9.46041 12.0022C8.92855 11.8616 8.47389 11.5163 8.19566 11.0417Z" fill=' + designSettings.settingsButtonColor + ' /><path d="M8.8834 2.08331C8.67444 2.08343 8.47314 2.16204 8.31941 2.30358C8.16569 2.44511 8.07074 2.63924 8.0534 2.84748L7.96423 3.91331C7.14681 4.18703 6.39292 4.62264 5.74756 5.19415L4.77923 4.73748C4.59014 4.64848 4.37451 4.63378 4.17509 4.69629C3.97567 4.7588 3.80701 4.89396 3.70256 5.07498L2.5859 7.00831C2.48127 7.1894 2.44856 7.4032 2.49426 7.60728C2.53995 7.81136 2.66071 7.9908 2.83256 8.10998L3.7109 8.71998C3.53895 9.56465 3.53895 10.4353 3.7109 11.28L2.83256 11.89C2.66071 12.0092 2.53995 12.1886 2.49426 12.3927C2.44856 12.5968 2.48127 12.8106 2.5859 12.9916L3.70256 14.925C3.80701 15.106 3.97567 15.2412 4.17509 15.3037C4.37451 15.3662 4.59014 15.3515 4.77923 15.2625L5.7484 14.8058C6.3935 15.3772 7.1471 15.8128 7.96423 16.0866L8.0534 17.1525C8.07074 17.3607 8.16569 17.5548 8.31941 17.6964C8.47314 17.8379 8.67444 17.9165 8.8834 17.9166H11.1167C11.3257 17.9165 11.527 17.8379 11.6807 17.6964C11.8344 17.5548 11.9294 17.3607 11.9467 17.1525L12.0359 16.0866C12.8533 15.8129 13.6072 15.3773 14.2526 14.8058L15.2209 15.2625C15.41 15.3515 15.6256 15.3662 15.825 15.3037C16.0245 15.2412 16.1931 15.106 16.2976 14.925L17.4142 12.9916C17.5189 12.8106 17.5516 12.5968 17.5059 12.3927C17.4602 12.1886 17.3394 12.0092 17.1676 11.89L16.2892 11.28C16.4612 10.4353 16.4612 9.56465 16.2892 8.71998L17.1676 8.10998C17.3394 7.9908 17.4602 7.81136 17.5059 7.60728C17.5516 7.4032 17.5189 7.1894 17.4142 7.00831L16.2976 5.07498C16.1931 4.89396 16.0245 4.7588 15.825 4.69629C15.6256 4.63378 15.41 4.64848 15.2209 4.73748L14.2517 5.19415C13.6066 4.62274 12.853 4.18713 12.0359 3.91331L11.9467 2.84748C11.9294 2.63924 11.8344 2.44511 11.6807 2.30358C11.527 2.16204 11.3257 2.08343 11.1167 2.08331H8.8834ZM8.8834 2.91665H11.1167L11.2526 4.54998L11.5301 4.62915C12.4152 4.88169 13.2242 5.34918 13.8851 5.98998L14.0926 6.18998L15.5759 5.49165L16.6926 7.42498L15.3467 8.35998L15.4167 8.63915C15.6392 9.53273 15.6392 10.4672 15.4167 11.3608L15.3467 11.64L16.6926 12.575L15.5759 14.5083L14.0926 13.8091L13.8851 14.0091C13.2243 14.6503 12.4153 15.118 11.5301 15.3708L11.2526 15.45L11.1167 17.0833H8.8834L8.74756 15.45L8.47006 15.3708C7.58489 15.1183 6.77588 14.6508 6.11506 14.01L5.90756 13.81L4.42423 14.5083L3.30756 12.575L4.6534 11.64L4.5834 11.3608C4.35889 10.4675 4.35889 9.53248 4.5834 8.63915L4.6534 8.35998L3.3084 7.42498L4.42506 5.49165L5.9084 6.19081L6.1159 5.99081C6.77663 5.34971 7.58564 4.88193 8.4709 4.62915L8.7484 4.54998L8.8834 2.91665Z" fill=' + designSettings.settingsButtonColor + ' /></svg></button>\n' +
    ' </div>\n' +
    ' <span class="wpccm-top-text" style="flex: 1; color: ' + designSettings.textColor + ' ; font-size: ' + designSettings.fontSize + ' ; line-height: 1.4; display: flex; align-items: center; gap: 10px;">\n' +
    ' <svg xmlns="http://www.w3.org/2000/svg" style="width: ' + (designSettings.size === 'small' ? '35px' : designSettings.size === 'large' ? '55px' : '45px') + '; height: ' + (designSettings.size === 'small' ? '35px' : designSettings.size === 'large' ? '55px' : '45px') + '; flex-shrink: 0;" viewBox="0 0 71 71" fill="none"><g clip-path="url(#clip0_123_23)"><path d="M21.627 47.9957C24.6078 47.9957 27.0242 45.1557 27.0242 41.6523C27.0242 38.149 24.6078 35.309 21.627 35.309C18.6462 35.309 16.2297 38.149 16.2297 41.6523C16.2297 45.1557 18.6462 47.9957 21.627 47.9957Z" fill="#33294D"/><path d="M50.2095 47.9957C53.1903 47.9957 55.6067 45.1557 55.6067 41.6523C55.6067 38.149 53.1903 35.309 50.2095 35.309C47.2287 35.309 44.8123 38.149 44.8123 41.6523C44.8123 45.1557 47.2287 47.9957 50.2095 47.9957Z" fill="#33294D"/><path d="M39.8331 45.4354C38.8069 44.4801 37.4005 43.9451 35.9182 43.9451C34.4359 43.9451 33.0296 44.4801 32.0033 45.4354C31.0531 46.3143 30.521 47.4607 30.521 48.6453C30.521 51.2438 32.9535 53.3455 35.9182 53.3455C38.8829 53.3455 41.3154 51.2438 41.3154 48.6453C41.3154 46.0468 40.7833 46.2761 39.8331 45.4354ZM35.9182 45.015C37.1345 45.015 38.2367 45.4736 38.9969 46.1614C38.2747 46.8875 37.1725 47.3843 35.9182 47.3843C34.6639 47.3843 33.5237 46.8875 32.8395 46.1614C33.5997 45.4354 34.7019 45.015 35.9182 45.015ZM35.9182 52.3902C34.5119 52.3902 33.2576 51.8552 32.3834 51.0145C32.8775 50.5177 34.1698 49.486 35.9182 50.5559C37.6286 49.486 38.9589 50.4795 39.453 51.0145C38.5788 51.8552 37.3245 52.3902 35.9182 52.3902Z" fill="#33294D" stroke="#33294D" stroke-miterlimit="10"/><path d="M22.9572 30.303C23.2233 31.6404 21.931 32.9779 20.0686 33.3218C18.2441 33.6657 16.5338 32.8633 16.3057 31.564C16.0396 30.2266 17.3319 28.8891 19.1944 28.5452C21.0188 28.2013 22.7292 29.0037 22.9572 30.303Z" fill="#33294D"/><path d="M48.917 30.303C48.651 31.6404 49.9433 32.9779 51.8057 33.3218C53.6301 33.6657 55.3405 32.8633 55.5685 31.564C55.8346 30.2266 54.5423 28.8891 52.6799 28.5452C50.8555 28.2013 49.1451 29.0037 48.917 30.303Z" fill="#33294D"/><path d="M35.5001 1.64317C37.7046 1.64317 39.8331 1.83424 41.9235 2.25458C42.8357 4.70022 45.3443 8.82724 52.0718 9.43865C52.0718 9.43865 54.2383 14.4446 61.1939 14.4446C68.1495 14.4446 61.65 14.4446 61.878 14.4446C66.4391 20.2148 69.1757 27.5517 69.1757 35.5C69.1757 43.4483 69.1757 37.2578 69.0617 38.1367C69.4798 38.8245 69.4417 39.7417 68.8716 40.3913L68.7956 40.4677C66.4011 56.8229 52.4139 69.3568 35.5001 69.3568C18.5863 69.3568 4.40909 56.6701 2.16658 40.2002C1.67247 39.6652 1.52044 38.8628 1.8245 38.1749L1.93853 37.9074C1.86251 37.105 1.86251 36.3025 1.86251 35.5C1.8245 16.8138 16.9139 1.64317 35.5001 1.64317ZM35.5001 5.12227e-06C16.0397 5.12227e-06 0.190135 15.9349 0.190135 35.5C0.190135 55.0651 0.190135 36.9139 0.266152 37.6017C-0.151942 38.6717 -0.0379162 39.8945 0.608229 40.8498C1.86251 49.1039 5.96744 56.6701 12.2389 62.1728C18.6623 67.8665 26.9482 71 35.5381 71C44.128 71 52.2999 67.9047 58.7233 62.2874C64.9567 56.8229 69.0997 49.3332 70.43 41.1555C71.0761 40.162 71.2281 38.9392 70.8101 37.831C70.8481 37.0667 70.8861 36.3025 70.8861 35.5C70.8861 27.3988 68.2255 19.7562 63.2083 13.4128L62.6762 12.7632H61.84C61.65 12.8014 61.4219 12.8014 61.2319 12.8014C55.4926 12.8014 53.7062 8.94188 53.6302 8.78903L53.2501 7.87191L52.2619 7.79548C46.7126 7.29871 44.4701 4.16524 43.5199 1.68138L43.1778 0.802481L42.2656 0.611415C40.0611 0.191071 37.8186 -0.038208 35.5381 -0.038208L35.5001 5.12227e-06Z" fill="#33294D"/></g><defs><clipPath id="clip0_123_23"><rect width="71" height="71" fill="white"/></clipPath></defs></svg>\n' + 
    ' <span style="flex: 1;">' + (b.description || texts.cookie_description || 'We use cookies on our website to give you the most relevant experience by remembering your preferences and repeat visits. By clicking "Accept All", you consent to the use of ALL the cookies.') + (b.policy_url ? ' <a href="' + b.policy_url + '" target="_blank" style="color: ' + designSettings.acceptButtonColor + ' ; text-decoration: underline ;">' + (texts.learn_more || 'Learn more') + '</a>' : '')+ '</span></span>\n' +
    ' </div>\n' +
    '</div>';

    // Load cookies by category from server (includes scanned cookies from database)
    loadCookiesByCategory(function(cookiesByCategory) {
        // Render banner with server data
        // Determine theme for modals
        var modalThemeClass = (designSettings.textColor === '#ffffff' ? 'dark-theme' : 'light-theme');
        var modalBgColor = (designSettings.textColor === '#ffffff' ? '#2c2c2c' : '#ffffff');
        var modalTextColor = (designSettings.textColor === '#ffffff' ? '#ffffff' : '#000000');
        
        // Create the detailed modal (hidden by default)
        var modalHtml = ''+
        '<div class="wpccm-modal ' + modalThemeClass + '" id="wpccm-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content" style="background-color: ' + modalBgColor + '; color: ' + modalTextColor + '; max-width: 800px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">\n' +
        ' <div class="wpccm-modal-header" style="background: ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; color: ' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '; padding: 20px; border-radius: 12px 12px 0 0; position: relative;">\n' +
        ' <h2 style="margin: 0; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;"><svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" viewBox="0 0 20 20" fill="none"><path d="M8.54149 7.47418C8.20711 7.6643 7.91364 7.91868 7.67796 8.22268C7.44229 8.52667 7.26908 8.87429 7.1683 9.2455C7.06752 9.61671 7.04116 10.0042 7.09074 10.3856C7.14032 10.7671 7.26485 11.1349 7.45718 11.4681C7.64951 11.8012 7.90583 12.093 8.21139 12.3266C8.51694 12.5603 8.86569 12.7312 9.23757 12.8295C9.60944 12.9278 9.99709 12.9516 10.3782 12.8995C10.7593 12.8474 11.1263 12.7204 11.4582 12.5259C12.1226 12.1363 12.606 11.4998 12.8029 10.7552C12.9997 10.0106 12.8941 9.21833 12.509 8.55133C12.1239 7.88432 11.4906 7.39672 10.7473 7.19492C10.004 6.99312 9.21104 7.09351 8.54149 7.47418ZM8.19566 11.0417C8.05671 10.8047 7.96601 10.5425 7.92879 10.2703C7.89157 9.99806 7.90856 9.72117 7.97879 9.45555C8.04901 9.18992 8.17109 8.94081 8.33798 8.72256C8.50487 8.50431 8.71329 8.32122 8.95123 8.18384C9.18917 8.04647 9.45193 7.95751 9.72439 7.92209C9.99685 7.88668 10.2736 7.90551 10.5388 7.9775C10.8039 8.04948 11.0522 8.17321 11.2694 8.34154C11.4865 8.50988 11.6682 8.71951 11.804 8.95835C12.0759 9.4366 12.1476 10.003 12.0035 10.5339C11.8593 11.0648 11.511 11.5172 11.0346 11.7923C10.5582 12.0673 9.99228 12.1428 9.46041 12.0022C8.92855 11.8616 8.47389 11.5163 8.19566 11.0417Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '"></path><path d="M8.8834 2.08331C8.67444 2.08343 8.47314 2.16204 8.31941 2.30358C8.16569 2.44511 8.07074 2.63924 8.0534 2.84748L7.96423 3.91331C7.14681 4.18703 6.39292 4.62264 5.74756 5.19415L4.77923 4.73748C4.59014 4.64848 4.37451 4.63378 4.17509 4.69629C3.97567 4.7588 3.80701 4.89396 3.70256 5.07498L2.5859 7.00831C2.48127 7.1894 2.44856 7.4032 2.49426 7.60728C2.53995 7.81136 2.66071 7.9908 2.83256 8.10998L3.7109 8.71998C3.53895 9.56465 3.53895 10.4353 3.7109 11.28L2.83256 11.89C2.66071 12.0092 2.53995 12.1886 2.49426 12.3927C2.44856 12.5968 2.48127 12.8106 2.5859 12.9916L3.70256 14.925C3.80701 15.106 3.97567 15.2412 4.17509 15.3037C4.37451 15.3662 4.59014 15.3515 4.77923 15.2625L5.7484 14.8058C6.3935 15.3772 7.1471 15.8128 7.96423 16.0866L8.0534 17.1525C8.07074 17.3607 8.16569 17.5548 8.31941 17.6964C8.47314 17.8379 8.67444 17.9165 8.8834 17.9166H11.1167C11.3257 17.9165 11.527 17.8379 11.6807 17.6964C11.8344 17.5548 11.9294 17.3607 11.9467 17.1525L12.0359 16.0866C12.8533 15.8129 13.6072 15.3773 14.2526 14.8058L15.2209 15.2625C15.41 15.3515 15.6256 15.3662 15.825 15.3037C16.0245 15.2412 16.1931 15.106 16.2976 14.925L17.4142 12.9916C17.5189 12.8106 17.5516 12.5968 17.5059 12.3927C17.4602 12.1886 17.3394 12.0092 17.1676 11.89L16.2892 11.28C16.4612 10.4353 16.4612 9.56465 16.2892 8.71998L17.1676 8.10998C17.3394 7.9908 17.4602 7.81136 17.5059 7.60728C17.5516 7.4032 17.5189 7.1894 17.4142 7.00831L16.2976 5.07498C16.1931 4.89396 16.0245 4.7588 15.825 4.69629C15.6256 4.63378 15.41 4.64848 15.2209 4.73748L14.2517 5.19415C13.6066 4.62274 12.853 4.18713 12.0359 3.91331L11.9467 2.84748C11.9294 2.63924 11.8344 2.44511 11.6807 2.30358C11.527 2.16204 11.3257 2.08343 11.1167 2.08331H8.8834ZM8.8834 2.91665H11.1167L11.2526 4.54998L11.5301 4.62915C12.4152 4.88169 13.2242 5.34918 13.8851 5.98998L14.0926 6.18998L15.5759 5.49165L16.6926 7.42498L15.3467 8.35998L15.4167 8.63915C15.6392 9.53273 15.6392 10.4672 15.4167 11.3608L15.3467 11.64L16.6926 12.575L15.5759 14.5083L14.0926 13.8091L13.8851 14.0091C13.2243 14.6503 12.4153 15.118 11.5301 15.3708L11.2526 15.45L11.1167 17.0833H8.8834L8.74756 15.45L8.47006 15.3708C7.58489 15.1183 6.77588 14.6508 6.11506 14.01L5.90756 13.81L4.42423 14.5083L3.30756 12.575L4.6534 11.64L4.5834 11.3608C4.35889 10.4675 4.35889 9.53248 4.5834 8.63915L4.6534 8.35998L3.3084 7.42498L4.42506 5.49165L5.9084 6.19081L6.1159 5.99081C6.77663 5.34971 7.58564 4.88193 8.4709 4.62915L8.7484 4.54998L8.8834 2.91665Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '"></path></svg>' + (b.title || texts.privacy_overview || 'הגדרות עוגיות וסקריפטים') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close" style="background: none; border: none; color: ' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '; font-size: 24px; position: absolute; top: 15px; left: 20px; cursor: pointer; padding: 5px;">&times;</button>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-body" style="padding: 20px; max-height: 60vh; overflow-y: auto;">\n' +
        ' <div class="wpccm-categories">\n' +
        generateCategoryToggles(texts, cookiesByCategory, designSettings) +
        ' </div>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-footer" style="padding: 15px 20px; background: ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; border-radius: 0 0 12px 12px; display: flex; gap: 10px; justify-content: flex-end;">\n' +
        ' <button class="wpccm-btn-save-accept" id="wpccm-save-accept-btn" style="background-color: rgba(0, 0, 0, 0); color: #ffffff; border: 1px solid #ffffff; padding: 8px 16px; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">שמור והמשך</button>\n' +
        ' </div>\n' +
        ' </div>\n' +
        '</div>';

        // // Add floating button based on design settings
        // var floatingButtonHtml = '';
        // if (designSettings.floatingPosition) {
        //     var floatingButtonStyle = 'position: fixed !important; z-index: 999998 !important; cursor: pointer !important; border: none !important; border-radius: 50% !important; width: 50px !important; height: 50px !important; background-color: ' + designSettings.settingsButtonColor + ' !important; color: white !important; font-size: 20px !important; box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important; transition: all 0.3s ease !important;';
            
        //     // Position the floating button
        //     if (designSettings.floatingPosition === 'top-right') {
        //         floatingButtonStyle += 'top: 20px !important; right: 20px !important;';
        //     } else if (designSettings.floatingPosition === 'top-left') {
        //         floatingButtonStyle += 'top: 20px !important; left: 20px !important;';
        //     } else if (designSettings.floatingPosition === 'bottom-right') {
        //         floatingButtonStyle += 'bottom: 20px !important; right: 20px !important;';
        //     } else if (designSettings.floatingPosition === 'bottom-left') {
        //         floatingButtonStyle += 'bottom: 20px !important; left: 20px !important;';
        //     }
            
        //     floatingButtonHtml = '<button id="wpccm-floating-btn" style="' + floatingButtonStyle + '" title="' + (texts.cookie_settings || 'Cookie Settings') + '">⚙️</button>';
        // }
        
        // Add data deletion modal
        var dataDeletionModalHtml = ''+
        '<div class="wpccm-modal ' + modalThemeClass + '" id="wpccm-data-deletion-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content" style="background-color: ' + modalBgColor + '; color: ' + modalTextColor + '; max-width: 800px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">\n' +
        ' <div class="wpccm-modal-header" style="background: ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; color: ' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '; padding: 20px; border-radius: 12px 12px 0 0; position: relative;">\n' +
        ' <h2 style="margin: 0; font-size: 20px; font-weight: 600; display: flex; align-items: center; gap: 10px;"><svg xmlns="http://www.w3.org/2000/svg" style="width: 20px; height: 20px;" viewBox="0 0 20 20" fill="none"><path d="M1 1L9 9.5M12.554 9.085C15.034 10.037 17.017 9.874 19 9.088C18.5 15.531 15.496 18.008 11.491 19C11.491 19 8.474 16.866 8.039 11.807C7.992 11.259 7.969 10.986 8.082 10.677C8.196 10.368 8.42 10.147 8.867 9.704C9.603 8.976 9.97 8.612 10.407 8.52C10.844 8.43 11.414 8.648 12.554 9.085Z" stroke="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.5 14.446C17.5 14.446 15 14.93 12.5 13" stroke="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.5 5.25C13.5 5.58152 13.6317 5.89946 13.8661 6.13388C14.1005 6.3683 14.4185 6.5 14.75 6.5C15.0815 6.5 15.3995 6.3683 15.6339 6.13388C15.8683 5.89946 16 5.58152 16 5.25C16 4.91848 15.8683 4.60054 15.6339 4.36612C15.3995 4.1317 15.0815 4 14.75 4C14.4185 4 14.1005 4.1317 13.8661 4.36612C13.6317 4.60054 13.5 4.91848 13.5 5.25Z" stroke="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '"/><path d="M11 2V2.1" stroke="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '" stroke-linecap="round" stroke-linejoin="round"/></svg>' + (texts.data_deletion || 'מחיקת היסטוריית נתונים') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close" style="background: none; border: none; color: ' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#ffffff') + '; font-size: 24px; position: absolute; top: 15px; left: 20px; cursor: pointer; padding: 5px;">&times;</button>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-body">\n' +
        ' <p>' + (texts.data_deletion_description || 'בחר את סוג הנתונים שברצונך למחוק:') + '</p>\n' +
        ' <div class="wpccm-data-deletion-options">\n' +
        ' <div class="wpccm-deletion-option">\n' +
        ' <label class="wpccm-deletion-radio">\n' +
        ' <input type="radio" name="deletion_type" value="browsing" checked>\n' +
        ' <span class="wpccm-radio-label">' + (texts.delete_browsing_data || 'שליחת בקשה למחיקת נתוני גלישה') + '</span>\n' +
        ' </label>\n' +
        ' <p class="wpccm-option-description">' + (texts.browsing_data_description || 'מחיקת היסטוריית גלישה, עוגיות, והעדפות') + '</p>\n' +
        ' </div>\n' +
        ' <div class="wpccm-deletion-option">\n' +
        ' <label class="wpccm-deletion-radio">\n' +
        ' <input type="radio" name="deletion_type" value="account">\n' +
        ' <span class="wpccm-radio-label">' + (texts.delete_account_data || 'שליחת בקשה למחיקת נתוני גלישה וחשבון') + '</span>\n' +
        ' </label>\n' +
        ' <p class="wpccm-option-description">' + (texts.account_data_description || 'מחיקת כל הנתונים כולל חשבון משתמש') + '</p>\n' +
        ' </div>\n' +
        ' </div>\n' +
        ' <div class="wpccm-ip-section">\n' +
        ' <label for="wpccm-ip-input">' + (texts.ip_address || 'כתובת IP:') + '</label>\n' +
        ' <input type="text" id="wpccm-ip-input" class="wpccm-ip-input" readonly>\n' +
        ' <button type="button" id="wpccm-edit-ip-btn" class="wpccm-edit-ip-btn">' + (texts.edit_ip || 'ערוך') + '</button>\n' +
        ' </div>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-footer" style="padding: 15px 20px; background: ' + (designSettings.textColor === '#ffffff' ? '#242424F2' : designSettings.backgroundColor) + '; border-radius: 0 0 12px 12px; display: flex; gap: 10px; justify-content: flex-end;">\n' +
        ' <button class="wpccm-btn-cancel" id="wpccm-cancel-deletion-btn" style="background-color: rgba(0, 0, 0, 0); color: #ffffff; border: 1px solid #ffffff; padding: 8px 16px; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">' + (texts.cancel || 'ביטול') + '</button>\n' +
        ' <button class="wpccm-btn-submit-deletion" id="wpccm-submit-deletion-btn" style="background-color: rgba(0, 0, 0, 0); color: #ffffff; border: 1px solid #ffffff; padding: 8px 16px; font-size: 14px; cursor: pointer; transition: all 0.3s ease;">' + (texts.submit_deletion_request || 'שלח בקשה') + '</button>\n' +
        ' </div>\n' +
        ' </div>\n' +
        '</div>';

        root.innerHTML = topBannerHtml  + modalHtml + dataDeletionModalHtml;

        // Pre-fill checkboxes if user has a previous state
        var s = currentState();
        var checkboxes = root.querySelectorAll('input[data-category]');
        checkboxes.forEach(function(checkbox) {
            var category = checkbox.getAttribute('data-category');
            if (s[category] !== undefined) {
                checkbox.checked = s[category];
                updateToggleStatus(checkbox, texts);
            }
        });

        // Add change listeners to update status text
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                updateToggleStatus(this, texts);
            });
        });

        // Add click handlers
        var settingsBtn = document.getElementById('wpccm-settings-btn');
        var dataDeletionBtn = document.getElementById('wpccm-data-deletion-btn');
        var acceptAllBtn = document.getElementById('wpccm-accept-all-btn');
        var rejectAllBtn = document.getElementById('wpccm-reject-all-btn');
        var saveAcceptBtn = document.getElementById('wpccm-save-accept-btn');
        // var floatingBtn = document.getElementById('wpccm-floating-btn');
        var modalCloseBtn = root.querySelector('.wpccm-modal-close');
        var modalOverlay = root.querySelector('.wpccm-modal-overlay');
        var modal = document.getElementById('wpccm-modal');
        var dataDeletionModal = document.getElementById('wpccm-data-deletion-modal');
        
        if(settingsBtn) settingsBtn.addEventListener('click', function(){ 
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        });
        
        // if(floatingBtn) floatingBtn.addEventListener('click', function(){ 
        //     modal.style.display = 'flex';
        //     document.body.style.overflow = 'hidden';
        // });
        
        if(dataDeletionBtn) dataDeletionBtn.addEventListener('click', function(){ 
            showDataDeletionModal();
        });
        
        if(acceptAllBtn) acceptAllBtn.addEventListener('click', function(){ acceptAll(); });
        if(rejectAllBtn) rejectAllBtn.addEventListener('click', function(){ rejectAll(); });
        if(saveAcceptBtn) {
            saveAcceptBtn.addEventListener('click', function(){ saveChoices(); });
            
            // Add hover effects to match banner buttons
            saveAcceptBtn.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
            });
            
            saveAcceptBtn.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'rgba(0, 0, 0, 0)';
            });
        }
        
        if(modalCloseBtn) modalCloseBtn.addEventListener('click', function(){ 
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        if(modalOverlay) modalOverlay.addEventListener('click', function(){ 
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        // Add improved styling to categories
        var categoryElements = modal.querySelectorAll('.wpccm-category');
        categoryElements.forEach(function(category) {
            category.style.cssText += 'border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 8px; background: #fff; transition: all 0.2s ease; cursor: default;';
        });
        
        // Toggle category details on click instead of hover
        var categories = modal.querySelectorAll('.wpccm-category');
        categories.forEach(function(category) {
            var details = category.querySelector('.wpccm-category-details');
            var summaryButton = category.querySelector('.wpccm-category-summary');
            var arrow = category.querySelector('.wpccm-category-arrow');

            if (!details || !summaryButton) {
                return;
            }

            var openClass = 'wpccm-open';

            var openDetails = function() {
                category.classList.add(openClass);
                details.style.display = 'block';
                details.style.opacity = '0';
                details.style.transform = 'translateY(-10px)';
                details.style.transition = 'all 0.3s ease';

                requestAnimationFrame(function() {
                    details.style.opacity = '1';
                    details.style.transform = 'translateY(0)';
                });

                summaryButton.setAttribute('aria-expanded', 'true');
                if (arrow) {
                    arrow.style.transform = 'rotate(180deg)';
                }

                category.style.borderColor = '#007cba';
                category.style.boxShadow = '0 2px 8px rgba(0,123,186,0.15)';
            };

            var closeDetails = function() {
                category.classList.remove(openClass);
                summaryButton.setAttribute('aria-expanded', 'false');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }

                details.style.opacity = '0';
                details.style.transform = 'translateY(-10px)';
                setTimeout(function() {
                    if (!category.classList.contains(openClass)) {
                        details.style.display = 'none';
                    }
                }, 200);

                category.style.borderColor = '#e9ecef';
                category.style.boxShadow = 'none';
            };

            // Ensure details start collapsed
            closeDetails();

            summaryButton.addEventListener('click', function(event) {
                event.preventDefault();
                if (category.classList.contains(openClass)) {
                    closeDetails();
                } else {
                    openDetails();
                }
            });

            summaryButton.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    summaryButton.click();
                }
            });

            summaryButton.addEventListener('focus', function() {
                if (!category.classList.contains(openClass)) {
                    category.style.borderColor = '#007cba';
                    category.style.boxShadow = '0 2px 8px rgba(0,123,186,0.1)';
                }
            });

            summaryButton.addEventListener('blur', function() {
                if (!category.classList.contains(openClass)) {
                    category.style.borderColor = '#e9ecef';
                    category.style.boxShadow = 'none';
                }
            });

            category.addEventListener('mouseenter', function() {
                if (!category.classList.contains(openClass)) {
                    category.style.borderColor = '#007cba';
                    category.style.boxShadow = '0 2px 8px rgba(0,123,186,0.1)';
                }
            });

            category.addEventListener('mouseleave', function() {
                if (!category.classList.contains(openClass)) {
                    category.style.borderColor = '#e9ecef';
                    category.style.boxShadow = 'none';
                }
            });
        });
    });
}

function showDataDeletionModal() {
    var modal = document.getElementById('wpccm-data-deletion-modal');
    var ipInput = document.getElementById('wpccm-ip-input');
    var editIpBtn = document.getElementById('wpccm-edit-ip-btn');
    var submitBtn = document.getElementById('wpccm-submit-deletion-btn');
    var cancelBtn = document.getElementById('wpccm-cancel-deletion-btn');
    var closeBtn = modal.querySelector('.wpccm-modal-close');
    var overlay = modal.querySelector('.wpccm-modal-overlay');
    
    // Get user's real IP address from server
    ipInput.value = 'מקבל כתובת IP...'; // Loading message in Hebrew
    getRealUserIP(function(realIP) {
        ipInput.value = realIP;
    });
    
    // Show modal
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Edit IP button
    if(editIpBtn) {
        editIpBtn.addEventListener('click', function() {
            ipInput.readOnly = !ipInput.readOnly;
            editIpBtn.textContent = ipInput.readOnly ? (WPCCM.texts.edit_ip || 'ערוך') : (WPCCM.texts.save_ip || 'שמור');
        });
    }
    
    // Submit deletion request
    if(submitBtn) {
        submitBtn.addEventListener('click', function() {
            var deletionType = document.querySelector('input[name="deletion_type"]:checked').value;
            var ipAddress = ipInput.value;
            
            submitDataDeletionRequest(deletionType, ipAddress);
        });
        
        // Add hover effects
        submitBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
        });
        
        submitBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'rgba(0, 0, 0, 0)';
        });
    }
    
    // Close modal
    if(cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        // Add hover effects
        cancelBtn.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(255, 255, 255, 0.1)';
        });
        
        cancelBtn.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'rgba(0, 0, 0, 0)';
        });
    }
    
    if(closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
    }
    
    if(overlay) {
        overlay.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
    }
}

function getUserIP() {
    // Try to get IP from server response or use a fallback
    if (typeof WPCCM !== 'undefined' && WPCCM.userIP) {
        return WPCCM.userIP;
    }
    
    // Fallback - this will be replaced by server-side IP detection
    return '127.0.0.1';
}

function getRealUserIP(callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.data && response.data.ip) {
                        callback(response.data.ip);
                    } else {
                        callback('127.0.0.1'); // fallback
                    }
                } catch (e) {
                    console.error('Error parsing IP response:', e);
                    callback('127.0.0.1'); // fallback
                }
            } else {
                console.error('Request failed with status:', xhr.status);
                callback('127.0.0.1'); // fallback
            }
        }
    };
    
    var data = 'action=wpccm_get_user_ip';
    if (typeof WPCCM !== 'undefined' && WPCCM.nonce) {
        data += '&nonce=' + encodeURIComponent(WPCCM.nonce);
    }
    
    xhr.send(data);
}

function submitDataDeletionRequest(deletionType, ipAddress) {
    if (typeof WPCCM === 'undefined' || !WPCCM.ajaxUrl) {
        console.error('WPCCM AJAX URL not found');
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert(WPCCM.texts.deletion_request_sent || 'בקשה נשלחה בהצלחה');
                    document.getElementById('wpccm-data-deletion-modal').style.display = 'none';
                    document.body.style.overflow = '';
                } else {
                    alert(response.data || WPCCM.texts.deletion_request_error || 'שגיאה בשליחת הבקשה');
                }
            } catch (e) {
                console.error('Error parsing response:', e);
                alert(WPCCM.texts.deletion_request_error || 'שגיאה בשליחת הבקשה');
            }
        } else {
            console.error('Request failed with status:', xhr.status);
            alert(WPCCM.texts.deletion_request_error || 'שגיאה בשליחת הבקשה');
        }
    };
    
    xhr.onerror = function() {
        console.error('Network error');
        alert(WPCCM.texts.deletion_request_error || 'שגיאה בשליחת הבקשה');
    };
    
    var data = 'action=wpccm_submit_data_deletion_request&deletion_type=' + encodeURIComponent(deletionType) + '&ip_address=' + encodeURIComponent(ipAddress);
    xhr.send(data);
}

function updateToggleStatus(checkbox, texts) {
    var statusSpan = checkbox.closest('.wpccm-category-toggle').querySelector('.wpccm-toggle-status');
    if (statusSpan) {
        if (checkbox.disabled) {
            statusSpan.textContent = texts.always_enabled || 'Always Enabled';
        } else {
            statusSpan.textContent = checkbox.checked ? (texts.enabled || 'Enabled') : (texts.disabled || 'Disabled');
        }
    }
}

function acceptAll() {
    var newState = {};
    
    // Get categories from WPCCM global or use defaults
    var categories = [];
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories
        categories = [
            {key: 'necessary', enabled: true},
            {key: 'functional', enabled: true},
            {key: 'performance', enabled: true},
            {key: 'analytics', enabled: true},
            {key: 'advertisement', enabled: true},
            {key: 'others', enabled: true}
        ];
    }
    
    categories.forEach(function(cat) {
        if (cat.enabled !== false) {
            newState[cat.key] = true;
        }
    });
    
    storeNewState(newState);
    
    // Log the consent action
    logConsentAction('accept', newState, Object.keys(newState).filter(function(key) { return newState[key]; }));
    
    // Stop cookie monitoring if it's running
    if (window.wpccmCookieMonitor) {
        clearInterval(window.wpccmCookieMonitor);
    }
    
    activateDeferredScripts();
    hideBanner();
}

function rejectAll() {
    console.log('WPCCM: rejectAll() called');
    var newState = {};
    
    // Get categories from WPCCM global or use defaults
    var categories = [];
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories
        categories = [
            {key: 'necessary', required: true},
            {key: 'functional', required: false},
            {key: 'performance', required: false},
            {key: 'analytics', required: false},
            {key: 'advertisement', required: false},
            {key: 'others', required: false}
        ];
    }
    
    // Only accept required/necessary categories
    categories.forEach(function(cat) {
        newState[cat.key] = cat.required === true || cat.key === 'necessary';
    });
    
    console.log('WPCCM: rejectAll newState:', newState);
    storeNewState(newState);
    
    // Log the consent action
    logConsentAction('reject', newState, Object.keys(newState).filter(function(key) { return newState[key]; }));
    
    // Delete all non-essential cookies immediately
    purgeNonEssentialCookies();
    
    // Block all non-essential scripts from running
    blockNonEssentialScripts();
    
    hideBanner();
}

function purgeNonEssentialCookies() {
    
    var deletedCookies = [];
    var keptCookies = [];
    
    // Essential cookies that should never be deleted
    var essentialCookies = [
        'wpccm_consent', 'wpccm_consent_resolved', 'wpccm_resolved', 'wpccm_simple', 
        'consent_necessary', 'consent_',
        'PHPSESSID', 'wordpress_*', 'wp-*', 'woocommerce_cart_hash',
        'woocommerce_items_in_cart', 'wp_woocommerce_session_*'
    ];
    
    function isEssential(cookieName) {
        return essentialCookies.some(function(essential) {
            if (essential.includes('*')) {
                var prefix = essential.replace('*', '');
                return cookieName.startsWith(prefix);
            }
            return cookieName === essential;
        });
    }
    
    // Get all current cookies
    var allCookies = document.cookie.split(";");
    
    allCookies.forEach(function(cookie) {
        var cookieNameOnly = cookie.split("=")[0].trim();
        
        if (cookieNameOnly && !isEssential(cookieNameOnly)) {
            deletedCookies.push(cookieNameOnly);
            deleteCookie(cookieNameOnly);
        } else if (cookieNameOnly) {
            keptCookies.push(cookieNameOnly);
        }
    });
    
    // Delete cookies from database that are not in approved categories
    // This follows the same logic as script blocking
    if (typeof WPCCM !== 'undefined' && WPCCM.cookies && Array.isArray(WPCCM.cookies)) {
        var userConsentState = currentState();

        WPCCM.cookies.forEach(function(cookieInfo) {
            var cookieName = cookieInfo.name || cookieInfo.cookie_name;
            var cookieCategory = cookieInfo.category || 'others';

            // Only delete cookies from categories that user has NOT approved
            // Skip 'necessary' category as it's always approved
            if (cookieCategory !== 'necessary' && !userConsentState[cookieCategory]) {
                console.log('WPCCM: Deleting cookie from unapproved category:', cookieName, 'category:', cookieCategory);

                // Handle wildcard cookies (e.g., woocommerce_*)
                if (cookieName && cookieName.includes('*')) {
                    var prefix = cookieName.replace('*', '');
                    var currentCookies = document.cookie.split(";");

                    currentCookies.forEach(function(cookie) {
                        var cookieNameOnly = cookie.split("=")[0].trim();
                        if (cookieNameOnly.startsWith(prefix) && !isEssential(cookieNameOnly)) {
                            deleteCookie(cookieNameOnly);
                            if (deletedCookies.indexOf(cookieNameOnly) === -1) {
                                deletedCookies.push(cookieNameOnly);
                            }
                        }
                    });
                } else if (cookieName && !isEssential(cookieName)) {
                    // Delete exact cookie name
                    deleteCookie(cookieName);
                    if (deletedCookies.indexOf(cookieName) === -1) {
                        deletedCookies.push(cookieName);
                    }
                }
            }
        });
    }
    
}

function blockNonEssentialScripts() {
    
    // Block Google Analytics
    window['ga-disable-' + (window.gtag_config_id || 'GA_MEASUREMENT_ID')] = true;
    
    // Block GTM
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({'gtm.blocked': true});
    
    
    // Block common tracking scripts by preventing their execution
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
        var script = scripts[i];
        var src = script.src;
        
        // Check if it's a tracking script
        if (src && (
            src.includes('googletagmanager.com') ||
            src.includes('google-analytics.com') ||
            src.includes('facebook.net') ||
            src.includes('connect.facebook.net') ||
            src.includes('hotjar.com') ||
            src.includes('doubleclick.net')
        )) {
            script.type = 'text/blocked';
        }
    }
    
}

function setupCookieMonitoring() {
    if (window.wpccmCookieMonitor) {
        clearInterval(window.wpccmCookieMonitor);
        window.wpccmCookieMonitor = null;
        console.log('🔁 WPCCM Cookie Monitor: אתחול מחדש של המעקב אחר עוגיות.');
    }

    if (window.wpccmAutoSyncTimer) {
        clearInterval(window.wpccmAutoSyncTimer);
        window.wpccmAutoSyncTimer = null;
        console.log('🔁 WPCCM Auto Sync: מנקה טיימר קודם לפני יצירת חדש.');
    }

    if (!document || typeof document.cookie === 'undefined') {
        console.warn('WPCCM Cookie Monitor: לא ניתן להתחיל מעקב ללא document.cookie');
        return;
    }

    // Monitor for new cookies every 60 seconds
    var cookieMonitor = setInterval(function() {
        var currentCookies = document.cookie.split(";");
        var newCookies = [];

        currentCookies.forEach(function(cookie) {
            var cookieName = cookie.split("=")[0].trim();
            
            // Check if it's a non-essential cookie
            if (cookieName && !isEssentialCookie(cookieName)) {
                newCookies.push(cookieName);
                deleteCookie(cookieName);
            }
        });
        
        if (newCookies.length > 0) {
        }
    }, 60000);
    
    // Store the monitor so we can stop it later if needed
    window.wpccmCookieMonitor = cookieMonitor;
    console.log('🛰️ WPCCM Cookie Monitor: המעקב פועל - מרווח 60 שניות.');

    // Auto sync timer based on admin settings
    if (typeof window.WPCCM !== 'undefined' && window.WPCCM.ajaxUrl) {
        var syncInterval = parseInt(window.WPCCM.syncIntervalMinutes, 10);
        if (isNaN(syncInterval) || syncInterval < 1) {
            syncInterval = 60;
        }
        console.log('⏱️ WPCCM Auto Sync: תדירות שנבחרה באדמין (דקות):', syncInterval);
        var syncIntervalMs = syncInterval * 60 * 1000; // Convert to milliseconds

        // Calculate next run time
        var nextRunTime = new Date(Date.now() + syncIntervalMs);

        console.log('🔄 WPCCM Auto Sync: מערכת סינכרון אוטומטי מופעלת');
        console.log('⏱️ WPCCM Auto Sync: תדירות - כל', syncInterval, 'דקות');
        console.log('⏰ WPCCM Auto Sync: הסינכרון הבא יתבצע ב:', nextRunTime.toLocaleString('he-IL'));

        var autoSyncTimer = setInterval(function() {
            // Check if banner root or floating button is visible (user is on site)
            var bannerRoot = document.getElementById('wpccm-banner-root');
            var floatingButton = document.querySelector('.wpccm-floating-button');

            console.log('🔍 WPCCM Auto Sync: בדיקת אלמנטים - banner root:', !!bannerRoot, 'floating button:', !!floatingButton);

            if (bannerRoot || floatingButton) {
                var currentTime = new Date();
                console.log('🚀 WPCCM Auto Sync: מתחיל סינכרון - ' + currentTime.toLocaleString('he-IL'));

                var payload = new URLSearchParams();
                payload.append('action', 'wpccm_frontend_auto_sync');
                if (window.WPCCM.nonce) {
                    payload.append('nonce', window.WPCCM.nonce);
                }

                fetch(window.WPCCM.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: payload.toString(),
                    credentials: 'same-origin'
                })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(response) {
                        var endTime = new Date();
                        if (response.success) {
                            console.log('✅ WPCCM Auto Sync: סינכרון הושלם בהצלחה!');
                            console.log('📊 WPCCM Auto Sync: תוצאות:', response.data);

                            // Calculate next run
                            var nextRun = new Date(Date.now() + syncIntervalMs);
                            console.log('⏰ WPCCM Auto Sync: הסינכרון הבא יתבצע ב:', nextRun.toLocaleString('he-IL'));
                        } else {
                            console.log('❌ WPCCM Auto Sync: סינכרון נכשל:', response.data);
                        }
                        var duration = endTime.getTime() - currentTime.getTime();
                        console.log('⏱️ WPCCM Auto Sync: זמן ביצוע:', duration + 'ms');
                    })
                    .catch(function(error) {
                        console.log('❌ WPCCM Auto Sync: שגיאת AJAX - החיבור לשרת נכשל', error);
                    });
            } else {
                console.log('⏸️ WPCCM Auto Sync: מדלג על סינכרון - אין באנר/כפתור צף באתר');
            }
        }, syncIntervalMs);

        // Store the timer so we can stop it later if needed
        window.wpccmAutoSyncTimer = autoSyncTimer;

        // Log timer info for debugging
        console.log('📝 WPCCM Auto Sync: Timer ID:', autoSyncTimer);
        console.log('🔧 WPCCM Auto Sync: ניתן לעצור עם: clearInterval(window.wpccmAutoSyncTimer)');
    } else {
        console.log('❌ WPCCM Auto Sync: הגדרות לא נמצאו - הסינכרון לא יופעל');
        console.log('🔍 WPCCM Auto Sync: Debug - window.WPCCM:', typeof window.WPCCM);
        if (typeof window.WPCCM !== 'undefined') {
            console.log('🔍 WPCCM Auto Sync: Debug - WPCCM object:', window.WPCCM);
            console.log('🔍 WPCCM Auto Sync: Debug - WPCCM.ajaxUrl:', window.WPCCM.ajaxUrl);
            console.log('🔍 WPCCM Auto Sync: Debug - WPCCM.syncIntervalMinutes:', window.WPCCM.syncIntervalMinutes);
        }
    }
}

function setupFormConsentEnforcement() {
    if (typeof window.WPCCM === 'undefined') {
        return;
    }

    var consentText = (WPCCM.texts && WPCCM.texts.forms_policy_label) ? WPCCM.texts.forms_policy_label : 'I confirm that I accept the cookie policy.';
    var requiredMessage = (WPCCM.texts && WPCCM.texts.forms_policy_required) ? WPCCM.texts.forms_policy_required : 'You must accept the cookie policy before submitting.';

    function enforce() {
        var forms = document.querySelectorAll('form');

        if (!forms || !forms.length) {
            return;
        }

        forms.forEach(function(form) {
            if (form.dataset && form.dataset.wpccmConsentEnforced === '1') {
                return;
            }

            // Skip WordPress search/login forms by heuristic
            var isSearchForm = form.classList.contains('search-form') ||
                form.id === 'adminbar-search' ||
                form.querySelector('input[type="search"]') ||
                form.querySelector('input[name="s"]');

            if (isSearchForm || form.id === 'loginform') {
                form.dataset.wpccmConsentEnforced = '1';
                return;
            }

            if (form.hasAttribute('data-wpccm-ignore-consent')) {
                form.dataset.wpccmConsentEnforced = '1';
                return;
            }

            if (form.querySelector('input[name="wpccm_consent_policy"]')) {
                form.dataset.wpccmConsentEnforced = '1';
                return;
            }

            // Create wrapper
            var wrapper = document.createElement('div');
            wrapper.className = 'wpccm-form-consent';
            wrapper.style.marginTop = '12px';
            wrapper.style.display = 'flex';
            wrapper.style.alignItems = 'flex-start';
            wrapper.style.gap = '8px';

            var checkboxId = 'wpccm-consent-' + Math.random().toString(36).substring(2, 10);

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.required = true;
            checkbox.id = checkboxId;
            checkbox.name = 'wpccm_consent_policy';
            checkbox.value = '1';
            checkbox.style.marginTop = '4px';

            var label = document.createElement('label');
            label.setAttribute('for', checkboxId);
            label.textContent = consentText;
            label.style.fontSize = '13px';
            label.style.color = '#333';

            checkbox.addEventListener('invalid', function() {
                checkbox.setCustomValidity(requiredMessage);
            });

            checkbox.addEventListener('change', function() {
                if (checkbox.checked) {
                    checkbox.setCustomValidity('');
                }
            });

            wrapper.appendChild(checkbox);
            wrapper.appendChild(label);

            if (form.querySelector('.wpccm-form-consent')) {
                form.dataset.wpccmConsentEnforced = '1';
                return;
            }

            if (form.lastElementChild && form.lastElementChild.tagName && form.lastElementChild.tagName.toLowerCase() === 'p') {
                form.lastElementChild.insertAdjacentElement('afterend', wrapper);
            } else {
                form.appendChild(wrapper);
            }

            form.dataset.wpccmConsentEnforced = '1';
        });
    }

    enforce();

    if (!window.wpccmFormsMonitor) {
        window.wpccmFormsMonitor = setInterval(enforce, 5000);
    }
}

function isEssentialCookie(cookieName) {
    var essentialCookies = [
        'wpccm_consent', 'wpccm_consent_resolved', 'wpccm_resolved', 'wpccm_simple',
        'consent_necessary', 'consent_',
        'PHPSESSID', 'wordpress_', 'wp-', 'woocommerce_cart_hash',
        'woocommerce_items_in_cart', 'wp_woocommerce_session_'
    ];
    
    return essentialCookies.some(function(essential) {
        if (essential.endsWith('_')) {
            return cookieName.startsWith(essential);
        }
        return cookieName === essential;
    });
}

function saveChoices() {
    var root = document.getElementById('wpccm-banner-root');
    var checkboxes = root.querySelectorAll('input[data-category]');
    var newState = {};

    checkboxes.forEach(function(checkbox) {
        var category = checkbox.getAttribute('data-category');
        newState[category] = checkbox.checked;
    });

    
    // Ensure required categories are always true
    var categories = [];
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories
        categories = [
            {key: 'necessary', enabled: true},
            {key: 'functional', enabled: true},
            {key: 'performance', enabled: true},
            {key: 'analytics', enabled: true},
            {key: 'advertisement', enabled: true},
            {key: 'others', enabled: true}
        ];
    }
    
    categories.forEach(function(cat) {
        if (cat.required && cat.enabled !== false) {
            newState[cat.key] = true;
        }
    });

    storeNewState(newState);

    // Persist the latest toggle labels inline
    var statusTexts = (typeof WPCCM !== 'undefined' && WPCCM.texts) ? WPCCM.texts : {};
    checkboxes.forEach(function(checkbox) {
        updateToggleStatus(checkbox, statusTexts);
    });

    // Purge cookies if some categories are rejected
    var hasRejected = Object.keys(newState).some(function(key) {
        var category = categories.find(function(cat) { return cat.key === key; });
        return !category || (!category.required && !newState[key]);
    });
    
    if (hasRejected) purgeOnReject();
    
    // Log the consent action
    logConsentAction('custom', newState, Object.keys(newState).filter(function(key) { return newState[key]; }));
    
    activateDeferredScripts();
    hideBanner();
}

function hideBanner() {
    var root = document.getElementById('wpccm-banner-root');
    if (root) root.innerHTML = '';
    document.body.style.overflow = '';
    
    // Show floating button after banner is hidden
    // Force creation without checking isResolved since we just made a choice
    setTimeout(function() {
        var floatingButton = document.getElementById('wpccm-floating-btn');
        if (floatingButton) {
            floatingButton.style.display = 'flex';
            // Update the expandable panel content with new consent status
            updateFloatingButtonContent();
        } else {
            createFloatingButton(); // Call createFloatingButton directly
        }
    }, 500);
}

function loadCookiesByCategory(callback) {
    
    // Check if AJAX URL is available
    if (typeof WPCCM === 'undefined' || !WPCCM.ajaxUrl) {
        console.warn('WPCCM AJAX URL not found, fallback to empty cookies');
        callback({});
        return;
    }
    
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        
        
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                console.log('WPCCM: Server response:', response);
                
                if (response.success && response.data) {
                    console.log('WPCCM: Cookies by category from server:', response.data);
                    callback(response.data);
                } else {
                    console.warn('Failed to load cookies by category:', response);
                    callback({});
                }
            } catch (e) {
                console.warn('Error parsing cookies response:', e);
                callback({});
            }
        } else {
            console.warn('Failed to load cookies, status:', xhr.status);
            callback({});
        }
    };
    
    xhr.onerror = function() {
        console.warn('Network error loading cookies');
        callback({});
    };
    
    // Send simple request - server will use purge list instead of current browser cookies
    xhr.send('action=wpccm_get_current_cookies_by_category');
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function init(){

    var s = currentState();

    setupCookieMonitoring();

    
    if (!isResolved(s)) {
        applyPendingConsentState();

        renderBanner();
    } else {

        activateDeferredScripts();
    }
}

// Public API
window.WPCCM_API = {
    grantAll: function(){ 
        acceptAll();
    },
    denyAll: function(){ 
        var newState = {};
        
        // Get categories from WPCCM global or use defaults
        var categories = [];
        if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
            categories = WPCCM.categories;
        } else {
            // Fallback to default categories
            categories = [
                {key: 'necessary', required: true},
                {key: 'functional', required: false},
                {key: 'performance', required: false},
                {key: 'analytics', required: false},
                {key: 'advertisement', required: false},
                {key: 'others', required: false}
            ];
        }
        
        categories.forEach(function(cat) {
            // Always include all categories, only required ones are enabled
            newState[cat.key] = cat.required; // Only required categories are enabled
        });
        
        storeNewState(newState);
        purgeOnReject(); 
    },
    resetConsent: function() {
        // Delete the main WPCCM consent cookies
        deleteCookie('wpccm_consent');
        deleteCookie('wpccm_consent_resolved');
        deleteCookie('wpccm_resolved');
        deleteCookie('wpccm_simple');
        // Also delete old format cookies if they exist
        var categories = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
        categories.forEach(function(cat) {
            deleteCookie('consent_' + cat);
        });
        if (typeof localStorage !== 'undefined') {
            try {
                localStorage.removeItem('wpccm_consent_state');
                localStorage.removeItem('wpccm_consent_resolved');
            } catch (e) {}
        }
        location.reload();
    },
    checkCookies: function() {
        var cookieList = document.cookie.split(';').map(function(c) {
            var parts = c.trim().split('=');
            return {
                name: parts[0],
                value: parts[1] || '',
                essential: isEssentialCookie(parts[0])
            };
        }).filter(function(cookie) {
            return cookie.name.length > 0;
        });
        
        console.table(cookieList);
        
        var essential = cookieList.filter(function(c) { return c.essential; });
        var nonEssential = cookieList.filter(function(c) { return !c.essential; });
        
        return {
            all: cookieList,
            essential: essential,
            nonEssential: nonEssential
        };
    },
    analyzeSite: function() {
        
        // Check configured cookies from plugin
        if (typeof WPCCM !== 'undefined' && WPCCM.options && WPCCM.options.purge && WPCCM.options.purge.cookies) {
            console.table(WPCCM.options.purge.cookies);
        } else {
        }
        
        // Check current cookies
        this.checkCookies();
        
        // Check tracking scripts
        var scripts = document.getElementsByTagName('script');
        var trackingScripts = [];
        
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            if (src && (
                src.includes('google') ||
                src.includes('facebook') ||
                src.includes('analytics') ||
                src.includes('gtag') ||
                src.includes('gtm') ||
                src.includes('doubleclick') ||
                src.includes('hotjar')
            )) {
                trackingScripts.push(src);
            }
        }
        
        
        return {
            configuredCookies: WPCCM.options?.purge?.cookies || [],
            currentCookies: this.checkCookies(),
            trackingScripts: trackingScripts
        };
    },
    showBanner: function() {

        renderBanner();
    },
    debugState: function() {
        var s = currentState();
    }
};

// Floating Button Functions
function initFloatingButton() {
    // Only show floating button if consent has been resolved
    if (!isResolved()) {
        return;
    }
    
    createFloatingButton();
}

function createFloatingButton() {
    // Remove existing button if any
    var existing = document.getElementById('wpccm-floating-btn');
    if (existing) {
        existing.remove();
    }

    var root = document.getElementById('wpccm-banner-root');
    var texts = (WPCCM && WPCCM.texts) ? WPCCM.texts : {};

    var designSettings = {
        bannerPosition: root.getAttribute('data-banner-position') || 'top',
        floatingPosition: root.getAttribute('data-floating-position') || 'bottom-right',
        backgroundColor: root.getAttribute('data-background-color') || '#ffffff',
        textColor: root.getAttribute('data-text-color') || '#000000',
        acceptButtonColor: root.getAttribute('data-accept-button-color') || '#0073aa',
        rejectButtonColor: root.getAttribute('data-reject-button-color') || '#6c757d',
        settingsButtonColor: root.getAttribute('data-settings-button-color') || '#28a745',
        size: root.getAttribute('data-size') || 'medium',
        padding: root.getAttribute('data-padding') || '15px',
        fontSize: root.getAttribute('data-font-size') || '14px',
        buttonPadding: root.getAttribute('data-button-padding') || '8px 16px'
    };

    // ' + designSettings.settingsButtonColor + '
    // transparent !important
    // Dynamic colors based on theme
    var _classbg = (designSettings.textColor === '#ffffff' ? '#000000' : '#ffffff');
    var _classcolor = (designSettings.textColor === '#ffffff' ? '#ffffff' : '#33294D');
    
    // Create floating button with new structure and dynamic styling
    var button = document.createElement('button');
    button.id = 'wpccm-floating-btn';
    button.className = 'wpccm-floating-button';
    
    // Create the button content with just the logo
    button.innerHTML = '<span class="wpccm-button-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 71 71" fill="none"><g clip-path="url(#clip0_123_23)"><path d="M21.627 47.9957C24.6078 47.9957 27.0242 45.1557 27.0242 41.6523C27.0242 38.149 24.6078 35.309 21.627 35.309C18.6462 35.309 16.2297 38.149 16.2297 41.6523C16.2297 45.1557 18.6462 47.9957 21.627 47.9957Z" fill="' + _classcolor + '"></path><path d="M50.2095 47.9957C53.1903 47.9957 55.6067 45.1557 55.6067 41.6523C55.6067 38.149 53.1903 35.309 50.2095 35.309C47.2287 35.309 44.8123 38.149 44.8123 41.6523C44.8123 45.1557 47.2287 47.9957 50.2095 47.9957Z" fill="' + _classcolor + '"></path><path d="M39.8331 45.4354C38.8069 44.4801 37.4005 43.9451 35.9182 43.9451C34.4359 43.9451 33.0296 44.4801 32.0033 45.4354C31.0531 46.3143 30.521 47.4607 30.521 48.6453C30.521 51.2438 32.9535 53.3455 35.9182 53.3455C38.8829 53.3455 41.3154 51.2438 41.3154 48.6453C41.3154 46.0468 40.7833 46.2761 39.8331 45.4354ZM35.9182 45.015C37.1345 45.015 38.2367 45.4736 38.9969 46.1614C38.2747 46.8875 37.1725 47.3843 35.9182 47.3843C34.6639 47.3843 33.5237 46.8875 32.8395 46.1614C33.5997 45.4354 34.7019 45.015 35.9182 45.015ZM35.9182 52.3902C34.5119 52.3902 33.2576 51.8552 32.3834 51.0145C32.8775 50.5177 34.1698 49.486 35.9182 50.5559C37.6286 49.486 38.9589 50.4795 39.453 51.0145C38.5788 51.8552 37.3245 52.3902 35.9182 52.3902Z" fill="' + _classcolor + '" stroke="' + _classcolor + '" stroke-miterlimit="10"></path><path d="M22.9572 30.303C23.2233 31.6404 21.931 32.9779 20.0686 33.3218C18.2441 33.6657 16.5338 32.8633 16.3057 31.564C16.0396 30.2266 17.3319 28.8891 19.1944 28.5452C21.0188 28.2013 22.7292 29.0037 22.9572 30.303Z" fill="' + _classcolor + '"></path><path d="M48.917 30.303C48.651 31.6404 49.9433 32.9779 51.8057 33.3218C53.6301 33.6657 55.3405 32.8633 55.5685 31.564C55.8346 30.2266 54.5423 28.8891 52.6799 28.5452C50.8555 28.2013 49.1451 29.0037 48.917 30.303Z" fill="' + _classcolor + '"></path><path d="M35.5001 1.64317C37.7046 1.64317 39.8331 1.83424 41.9235 2.25458C42.8357 4.70022 45.3443 8.82724 52.0718 9.43865C52.0718 9.43865 54.2383 14.4446 61.1939 14.4446C68.1495 14.4446 61.65 14.4446 61.878 14.4446C66.4391 20.2148 69.1757 27.5517 69.1757 35.5C69.1757 43.4483 69.1757 37.2578 69.0617 38.1367C69.4798 38.8245 69.4417 39.7417 68.8716 40.3913L68.7956 40.4677C66.4011 56.8229 52.4139 69.3568 35.5001 69.3568C18.5863 69.3568 4.40909 56.6701 2.16658 40.2002C1.67247 39.6652 1.52044 38.8628 1.8245 38.1749L1.93853 37.9074C1.86251 37.105 1.86251 36.3025 1.86251 35.5C1.8245 16.8138 16.9139 1.64317 35.5001 1.64317ZM35.5001 5.12227e-06C16.0397 5.12227e-06 0.190135 15.9349 0.190135 35.5C0.190135 55.0651 0.190135 36.9139 0.266152 37.6017C-0.151942 38.6717 -0.0379162 39.8945 0.608229 40.8498C1.86251 49.1039 5.96744 56.6701 12.2389 62.1728C18.6623 67.8665 26.9482 71 35.5381 71C44.128 71 52.2999 67.9047 58.7233 62.2874C64.9567 56.8229 69.0997 49.3332 70.43 41.1555C71.0761 40.162 71.2281 38.9392 70.8101 37.831C70.8481 37.0667 70.8861 36.3025 70.8861 35.5C70.8861 27.3988 68.2255 19.7562 63.2083 13.4128L62.6762 12.7632H61.84C61.65 12.8014 61.4219 12.8014 61.2319 12.8014C55.4926 12.8014 53.7062 8.94188 53.6302 8.78903L53.2501 7.87191L52.2619 7.79548C46.7126 7.29871 44.4701 4.16524 43.5199 1.68138L43.1778 0.802481L42.2656 0.611415C40.0611 0.191071 37.8186 -0.038208 35.5381 -0.038208L35.5001 5.12227e-06Z" fill="' + _classcolor + '"></path></g><defs><clipPath id="clip0_123_23"><rect width="71" height="71" fill="white"></rect></clipPath></defs></svg></span>';
    button.title = 'הגדרות עוגיות';
    
    // Apply dynamic styling based on theme
    var dynamicStyle = '';
    
    // Position the button based on settings and add position-specific class
    if (designSettings.floatingPosition === 'top-right') {
        dynamicStyle += 'top: 20px; right: 20px; ';
        button.className += ' position-top-right';
    } else if (designSettings.floatingPosition === 'top-left') {
        dynamicStyle += 'top: 20px; left: 20px; ';
        button.className += ' position-top-left';
    } else if (designSettings.floatingPosition === 'bottom-right') {
        dynamicStyle += 'bottom: 20px; right: 20px; ';
        button.className += ' position-bottom-right';
    } else if (designSettings.floatingPosition === 'bottom-left') {
        dynamicStyle += 'bottom: 20px; left: 20px; ';
        button.className += ' position-bottom-left';
    }
    
    // Add dynamic background color based on theme
    dynamicStyle += 'background-color: ' + _classbg + '; ';
    dynamicStyle += 'color: ' + _classcolor + '; ';
    dynamicStyle += 'width: 50px; ';
    dynamicStyle += 'height: 50px; ';
    dynamicStyle += 'border-radius: 50%; ';
    dynamicStyle += 'display: flex; ';
    dynamicStyle += 'align-items: center; ';
    dynamicStyle += 'justify-content: center; ';
    dynamicStyle += 'transition: all 0.3s ease; ';
    dynamicStyle += 'box-shadow: 0 2px 10px rgba(0,0,0,0.1); ';
    
    button.style.cssText += dynamicStyle;
    
    // Store original content for restoration
    var originalContent = button.innerHTML;
    var originalWidth = '50px';
    var originalHeight = '50px';
    var originalBorderRadius = '50%';
    var originalPadding = '0';
    
    // Add hover effects with expansion
    var hideTimeout;
    var isExpanded = false;
    
    button.addEventListener('mouseenter', function() {
        if (hideTimeout) {
            clearTimeout(hideTimeout);
            hideTimeout = null;
        }
        if (!isExpanded) {
            expandButton(button, originalContent, _classcolor);
            isExpanded = true;
        }
    });
    
    button.addEventListener('mouseleave', function() {
        hideTimeout = setTimeout(function() {
            if (isExpanded) {
                contractButton(button, originalContent, originalWidth, originalHeight, originalBorderRadius, originalPadding);
                isExpanded = false;
            }
        }, 200); // Small delay to prevent flickering
    });
    
    // Add click event to open settings directly
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        if (isExpanded) {
            contractButton(button, originalContent, originalWidth, originalHeight, originalBorderRadius, originalPadding);
            isExpanded = false;
        }
        editCookieSettings();
    });
    
    // Add to body
    document.body.appendChild(button);
}

// Expand button to show status content
function expandButton(button, originalContent, textColor) {
    // Get current consent state
    var state = currentState();
    var categories = [];
    
    if (typeof WPCCM !== 'undefined' && WPCCM.categories && Array.isArray(WPCCM.categories)) {
        categories = WPCCM.categories;
    } else {
        // Fallback to default categories with short names
        categories = [
            {key: 'necessary', name: 'נדרש', enabled: true},
            {key: 'functional', name: 'פונקציונלי', enabled: true},
            {key: 'performance', name: 'ביצועים', enabled: true},
            {key: 'analytics', name: 'אנליטיקס', enabled: true},
            {key: 'advertisement', name: 'פרסום', enabled: true},
            {key: 'others', name: 'אחר', enabled: true}
        ];
    }
    
    // Create expanded content
    var expandedContent = originalContent;
    
    categories.forEach(function(cat) {
        if (cat.enabled === false) return; // Skip disabled categories
        
        var isAccepted = state[cat.key];
        var statusIcon = isAccepted ? 
            '<span style="color: #4CAF50; font-size: 12px;">✓</span>' : 
            '<span style="color: #f44336; font-size: 12px;">✗</span>';
        
        var shortName = (cat.name || cat.key).substring(0, 4); // Keep names very short
        
        expandedContent += '<span style="margin: 0 4px; display: inline-flex; align-items: center; gap: 2px; font-size: 10px;">' +
                          statusIcon + '<span>' + shortName + '</span>' +
                          '</span>';
    });
    
    // Animate expansion
    // Check if button is positioned on the right side
    var isRightPositioned = button.className.includes('position-top-right') || button.className.includes('position-bottom-right');

    // Set common styles first
    button.style.width = 'auto';
    button.style.minWidth = '200px';
    button.style.maxWidth = '400px'; // Prevent excessive expansion

    // For right-positioned buttons, we'll calculate the actual width after content is set
    if (isRightPositioned) {
        // We'll adjust position after the content is rendered
        button.setAttribute('data-needs-right-adjustment', 'true');
    }

    button.style.height = '50px';
    button.style.borderRadius = '25px';
    button.style.padding = '0 15px';
    button.style.justifyContent = 'flex-start';
    button.style.gap = '8px';
    button.style.transform = 'scale(1.02)';
    button.style.boxShadow = '0 4px 15px rgba(0,0,0,0.2)';
    
    // Update content
    setTimeout(function() {
        button.innerHTML = expandedContent;

        // If this is a right-positioned button, adjust its position after content is set
        if (button.getAttribute('data-needs-right-adjustment') === 'true') {
            // Force a reflow to get accurate measurements
            button.offsetHeight;

            // Get the actual width of the button now that content is loaded
            var actualWidth = button.offsetWidth;
            var originalWidth = 60; // Original button width

            // Only expand by the difference, not the full width
            var expandedDistance = actualWidth - originalWidth;

            // Move the button left by only the expanded amount
            button.style.marginLeft = '-' + expandedDistance + 'px';

            button.removeAttribute('data-needs-right-adjustment');
        }
    }, 100);
}

function contractButton(button, originalContent, originalWidth, originalHeight, originalBorderRadius, originalPadding) {
    // Animate contraction
    button.style.width = originalWidth;
    button.style.minWidth = originalWidth;
    button.style.height = originalHeight;
    button.style.borderRadius = originalBorderRadius;
    button.style.padding = originalPadding;
    button.style.justifyContent = 'center';
    button.style.gap = '0';
    button.style.transform = 'scale(1)';
    button.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
    // Reset margin and width constraints for right-positioned buttons
    button.style.marginLeft = '0';
    button.style.maxWidth = 'none';
    
    // Restore original content
    setTimeout(function() {
        button.innerHTML = originalContent;
    }, 100);
}

function updateFloatingButtonContent() {
    // If button is currently expanded, update its content
    var button = document.getElementById('wpccm-floating-btn');
    if (button && button.style.minWidth === '200px') {
        // Button is expanded, refresh its content
        var root = document.getElementById('wpccm-banner-root');
        var textColor = root ? (root.getAttribute('data-text-color') || '#000000') : '#000000';
        var classColor = (textColor === '#ffffff' ? '#ffffff' : '#33294D');
        
        var originalContent = '<span class="wpccm-button-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 71 71" fill="none"><g clip-path="url(#clip0_123_23)"><path d="M21.627 47.9957C24.6078 47.9957 27.0242 45.1557 27.0242 41.6523C27.0242 38.149 24.6078 35.309 21.627 35.309C18.6462 35.309 16.2297 38.149 16.2297 41.6523C16.2297 45.1557 18.6462 47.9957 21.627 47.9957Z" fill="' + classColor + '"></path><path d="M50.2095 47.9957C53.1903 47.9957 55.6067 45.1557 55.6067 41.6523C55.6067 38.149 53.1903 35.309 50.2095 35.309C47.2287 35.309 44.8123 38.149 44.8123 41.6523C44.8123 45.1557 47.2287 47.9957 50.2095 47.9957Z" fill="' + classColor + '"></path><path d="M39.8331 45.4354C38.8069 44.4801 37.4005 43.9451 35.9182 43.9451C34.4359 43.9451 33.0296 44.4801 32.0033 45.4354C31.0531 46.3143 30.521 47.4607 30.521 48.6453C30.521 51.2438 32.9535 53.3455 35.9182 53.3455C38.8829 53.3455 41.3154 51.2438 41.3154 48.6453C41.3154 46.0468 40.7833 46.2761 39.8331 45.4354ZM35.9182 45.015C37.1345 45.015 38.2367 45.4736 38.9969 46.1614C38.2747 46.8875 37.1725 47.3843 35.9182 47.3843C34.6639 47.3843 33.5237 46.8875 32.8395 46.1614C33.5997 45.4354 34.7019 45.015 35.9182 45.015ZM35.9182 52.3902C34.5119 52.3902 33.2576 51.8552 32.3834 51.0145C32.8775 50.5177 34.1698 49.486 35.9182 50.5559C37.6286 49.486 38.9589 50.4795 39.453 51.0145C38.5788 51.8552 37.3245 52.3902 35.9182 52.3902Z" fill="' + classColor + '" stroke="' + classColor + '" stroke-miterlimit="10"></path><path d="M22.9572 30.303C23.2233 31.6404 21.931 32.9779 20.0686 33.3218C18.2441 33.6657 16.5338 32.8633 16.3057 31.564C16.0396 30.2266 17.3319 28.8891 19.1944 28.5452C21.0188 28.2013 22.7292 29.0037 22.9572 30.303Z" fill="' + classColor + '"></path><path d="M48.917 30.303C48.651 31.6404 49.9433 32.9779 51.8057 33.3218C53.6301 33.6657 55.3405 32.8633 55.5685 31.564C55.8346 30.2266 54.5423 28.8891 52.6799 28.5452C50.8555 28.2013 49.1451 29.0037 48.917 30.303Z" fill="' + classColor + '"></path><path d="M35.5001 1.64317C37.7046 1.64317 39.8331 1.83424 41.9235 2.25458C42.8357 4.70022 45.3443 8.82724 52.0718 9.43865C52.0718 9.43865 54.2383 14.4446 61.1939 14.4446C68.1495 14.4446 61.65 14.4446 61.878 14.4446C66.4391 20.2148 69.1757 27.5517 69.1757 35.5C69.1757 43.4483 69.1757 37.2578 69.0617 38.1367C69.4798 38.8245 69.4417 39.7417 68.8716 40.3913L68.7956 40.4677C66.4011 56.8229 52.4139 69.3568 35.5001 69.3568C18.5863 69.3568 4.40909 56.6701 2.16658 40.2002C1.67247 39.6652 1.52044 38.8628 1.8245 38.1749L1.93853 37.9074C1.86251 37.105 1.86251 36.3025 1.86251 35.5C1.8245 16.8138 16.9139 1.64317 35.5001 1.64317ZM35.5001 5.12227e-06C16.0397 5.12227e-06 0.190135 15.9349 0.190135 35.5C0.190135 55.0651 0.190135 36.9139 0.266152 37.6017C-0.151942 38.6717 -0.0379162 39.8945 0.608229 40.8498C1.86251 49.1039 5.96744 56.6701 12.2389 62.1728C18.6623 67.8665 26.9482 71 35.5381 71C44.128 71 52.2999 67.9047 58.7233 62.2874C64.9567 56.8229 69.0997 49.3332 70.43 41.1555C71.0761 40.162 71.2281 38.9392 70.8101 37.831C70.8481 37.0667 70.8861 36.3025 70.8861 35.5C70.8861 27.3988 68.2255 19.7562 63.2083 13.4128L62.6762 12.7632H61.84C61.65 12.8014 61.4219 12.8014 61.2319 12.8014C55.4926 12.8014 53.7062 8.94188 53.6302 8.78903L53.2501 7.87191L52.2619 7.79548C46.7126 7.29871 44.4701 4.16524 43.5199 1.68138L43.1778 0.802481L42.2656 0.611415C40.0611 0.191071 37.8186 -0.038208 35.5381 -0.038208L35.5001 5.12227e-06Z" fill="' + classColor + '"></path></g><defs><clipPath id="clip0_123_23"><rect width="71" height="71" fill="white"></rect></clipPath></defs></svg></span>';
        
        expandButton(button, originalContent, classColor);
    }
}

function editCookieSettings() {
    // Hide the floating button when opening settings
    var floatingButton = document.getElementById('wpccm-floating-btn');
    if (floatingButton) {
        floatingButton.style.display = 'none';
    }
    
    // Reset the main consent state to show banner again
    // Keep individual category preferences intact so they show in the modal
    deleteCookie('wpccm_consent');
    deleteCookie('wpccm_consent_resolved');
    deleteCookie('wpccm_resolved');
    deleteCookie('wpccm_simple');
    if (typeof localStorage !== 'undefined') {
        try {
            localStorage.removeItem('wpccm_consent_state');
            localStorage.removeItem('wpccm_consent_resolved');
        } catch (e) {}
    }

    applyPendingConsentState();

    // Re-render the banner
    renderBanner();
}

// Make functions global for onclick handlers
window.editCookieSettings = editCookieSettings;

/**
 * Log consent action to database
 */
function logConsentAction(actionType, consentData, categoriesAccepted) {
    //console.log('WPCCM: logConsentAction called with:', { actionType, consentData, categoriesAccepted });
    
    // Check if we have AJAX URL and nonce
    if (typeof WPCCM === 'undefined' || !WPCCM.ajaxUrl || !WPCCM.nonce) {
        console.warn('WPCCM: Cannot log consent - missing AJAX configuration');
        console.log('WPCCM: WPCCM object:', WPCCM);
        return;
    }
    
    console.log('WPCCM: AJAX URL:', WPCCM.ajaxUrl);
    console.log('WPCCM: Nonce:', WPCCM.nonce);
    
    // Prepare data for logging
    var logData = {
        action: 'wpccm_log_consent',
        nonce: WPCCM.nonce,
        action_type: actionType,
        consent_data: consentData,
        categories_accepted: categoriesAccepted
    };
    
    // Send AJAX request
    var xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        //console.log('WPCCM: Consent logged successfully:', response.data);
                    } else {
                        console.error('WPCCM: Failed to log consent:', response.data);
                    }
                } catch (e) {
                    console.error('WPCCM: Error parsing log response:', e);
                }
            } else {
                console.error('WPCCM: AJAX error logging consent:', xhr.status);
            }
        }
    };
    
    // Convert data to URL-encoded string
    var params = Object.keys(logData).map(function(key) {
        var value = logData[key];
        if (typeof value === 'object') {
            value = JSON.stringify(value);
        }
        return encodeURIComponent(key) + '=' + encodeURIComponent(value);
    }).join('&');
    
    //console.log('WPCCM: Sending AJAX request with params:', params);
    xhr.send(params);
}



// Wait for both DOM and other scripts to load
function safeInit() {
    // Only initialize if we're not in admin area
    if (document.body && document.body.classList.contains('wp-admin')) {
        return;
    }
    
    // Don't run during AJAX requests
    if (window.location.href.indexOf('admin-ajax.php') !== -1) {
        return;
    }
    
    // Immediate initialization
    init();
}

    // Initialize immediately - WPCCM should be available
    if (typeof WPCCM !== 'undefined') {
        safeInit();
        
        // Initialize floating button after banner initialization
        setTimeout(function() {
            initFloatingButton();
        }, 1000);

        setTimeout(function() {
            setupFormConsentEnforcement();
        }, 500);
    }
})();



function createConsentCharts() {
    // Check if chart elements exist (only on admin pages)
    var timeChartElement = document.getElementById('consentTimeChart');
    var categoryChartElement = document.getElementById('consentCategoryChart');
    
    if (!timeChartElement || !categoryChartElement) {
        //console.log('WPCCM: Chart elements not found, skipping chart creation');
        return;
    }
    
    var ctxTime = timeChartElement.getContext('2d');
    var ctxCategory = categoryChartElement.getContext('2d');

    var consentTimeChart = new Chart(ctxTime, {
        type: 'line',
        data: {
            labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
            datasets: [{
                label: 'Consents Over Time',
                data: [65, 59, 80, 81, 56, 55, 40],
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    var consentCategoryChart = new Chart(ctxCategory, {
        type: 'doughnut',
        data: {
            labels: ['Necessary', 'Functional', 'Performance', 'Analytics', 'Advertisement'],
            datasets: [{
                label: 'Category Distribution',
                data: [12, 19, 3, 5, 2],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        }
    });
}

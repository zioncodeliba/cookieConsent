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
        name + '=; Path=/wp-plagin/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin/wp-admin; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin/wp-content/plugins; Expires=Thu, 01 Jan 1970 00:00:01 GMT;',
        name + '=; Path=/wp-plagin/wp-content/plugins/wp-cookie-consent-manager; Expires=Thu, 01 Jan 1970 00:00:01 GMT;'
    ];
    
    for (var i = 0; i < methods.length; i++) {
        console.log('Trying method', i + 1, ':', methods[i]);
        document.cookie = methods[i];
        
        // Check if cookie was deleted
        setTimeout(function(methodIndex) {
            var cookieExists = getCookie(name);
            if (cookieExists === null) {
                console.log('Method', methodIndex + 1, 'successful - cookie deleted');
            } else {
                console.log('Method', methodIndex + 1, 'failed - cookie still exists');
            }
        }, 100, i);
    }
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
            state[cat.key] = getCookie('consent_' + cat.key) === '1';
        }
    });
    
    return state;
}


function storeNewState(state) {
    var days = 180; // Default cookie expiry days
    
    // Store state for all defined categories
    Object.keys(state).forEach(function(key) {
        setCookie('consent_' + key, state[key] ? '1' : '0', days);
    });
}

function isResolved(state) {
    // return getCookie('consent_necessary') !== null || getCookie('consent_analytics') !== null;
    // Check if user has made a consent decision
    // Look for any consent cookie, not just specific ones
    
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.startsWith('consent_')) {
            return true;
        }
    }
    
    return false;
}

function activateDeferredScripts() {
    var scripts = document.querySelectorAll('script[type="text/plain"][data-consent]');
    var state = currentState();
    
    scripts.forEach(function(script) {
        var category = script.getAttribute('data-consent');
        if (state[category]) {
            var newScript = document.createElement('script');
            
            // Copy attributes
            Array.from(script.attributes).forEach(function(attr) {
                if (attr.name !== 'type' && attr.name !== 'data-consent') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });
            
            // Handle src vs inline
            var dataSrc = script.getAttribute('data-src');
            if (dataSrc) {
                newScript.src = dataSrc;
            } else {
                newScript.innerHTML = script.innerHTML;
            }
            
            script.parentNode.replaceChild(newScript, script);
        }
    });
}

function purgeOnReject() {
    var options = {};
    if (typeof WPCCM !== 'undefined' && WPCCM && WPCCM.options) {
        options = WPCCM.options;
    }
    var cookiesToPurge = (options.purge && options.purge.cookies) || [{name:'_ga'}, {name:'_ga_*'}, {name:'_gid'}, {name:'_fbp'}, {name:'_hjSessionUser'}];

    cookiesToPurge.forEach(function(cookieName) {
        console.log('Purging cookie:', cookieName);

        if (cookieName.name.includes('*')) {
            // Handle wildcard cookies
            var prefix = cookieName.replace('*', '');
            var cookies = document.cookie.split(';');
            cookies.forEach(function(cookie) {
                var name = cookie.split('=')[0].trim();
                if (name.startsWith(prefix)) {
                    deleteCookie(name);
                    // deleteCookieViaServer(name);
                }
            });
        } else {
            deleteCookie(cookieName.name);
            // deleteCookieViaServer(cookieName.name);
        }
    });
    
    // Clear localStorage items
    try { 
        localStorage.removeItem('_ga'); 
        localStorage.removeItem('_gid');
    } catch(e) {}
}

function generateCategoryToggles(texts, cookiesByCategory) {
    
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
        
        var checked = cat.required ? 'checked' : '';
        var disabled = cat.required ? 'disabled' : '';
        var statusText = cat.required ? (texts.always_enabled || 'Always Enabled') : (texts.disabled || 'Disabled');
        
        // Generate cookies list for this category
        var cookiesHtml = '';
        var categoryData = cookiesByCategory[cat.key];
            
        // Check if categoryData is an array (direct cookies) or object with cookies property
        var cookies = [];
        if (Array.isArray(categoryData)) {
            cookies = categoryData;
        } else if (categoryData && categoryData.cookies && Array.isArray(categoryData.cookies)) {
            cookies = categoryData.cookies;
        }
        
        if (cookies && cookies.length > 0) {

            cookiesHtml = '<div class="wpccm-cookies-list" style="margin-top: 10px; font-size: 12px; color: #666;">' +
                '<strong>' + (texts.cookies_in_category || 'Cookies in this category:') + '</strong><br>' +
                '<div class="wpccm-cookie-tags">';
            
            cookies.forEach(function(cookieName) {
                cookiesHtml += '<span class="wpccm-cookie-tag">' + escapeHtml(cookieName) + '</span>';
            });
            
            cookiesHtml += '</div></div>';
        } else {
            console.log('WPCCM: No cookies found for category:', cat.key);
        }
        
        html += '<div class="wpccm-category">' +
            '<div class="wpccm-category-header">' +
            '<div class="wpccm-category-info">' +
            '<h4>' + escapeHtml(cat.name) + '</h4>' +
            '<p>' + escapeHtml(cat.description) + '</p>' +
            cookiesHtml +
            '</div>' +
            '<div class="wpccm-category-toggle">' +
            '<span class="wpccm-toggle-status">' + statusText + '</span>' +
            '<label class="wpccm-switch">' +
            '<input type="checkbox" data-category="' + escapeHtml(cat.key) + '" ' + checked + ' ' + disabled + '>' +
            '<span class="wpccm-slider"></span>' +
            '</label>' +
            '</div>' +
            '</div>' +
            '</div>';
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
    var bannerStyle = (designSettings.bannerPosition === 'bottom' ? 'top: auto !important; bottom: 0 !important; border-top: 1px solid #dee2e6 !important; border-bottom: none !important;' : 'top: 0 !important;') +
    'background-color: ' + designSettings.backgroundColor + ' !important; color: ' + designSettings.textColor + ' !important; padding: ' + designSettings.padding + ' !important; font-size: ' + designSettings.fontSize + ' !important;';
    

    
    var topBannerHtml = ''+
    '<div class="wpccm-top-banner"' + langAttr + ' role="dialog" aria-live="polite" style="' + bannerStyle + '">\n' +
    ' <div class="wpccm-top-content" style="max-width: 1200px; margin: 0 auto; padding: 12px 20px; display: flex; flex-direction: row-reverse; align-items: center; justify-content: space-between; gap: 20px;">\n' +
    ' <div class="wpccm-left-actions" style="display: flex; align-items: center;">\n' +
    ' <button class="wpccm-btn-data-deletion" id="wpccm-data-deletion-btn" style="background-color: transparent !important; color: ' + designSettings.dataDeletionButtonColor + ' !important; border: 1px solid ' + designSettings.dataDeletionButtonColor + ' !important; padding: ' + designSettings.buttonPadding + ' !important; border-radius: 4px !important; cursor: pointer !important; font-size: ' + designSettings.fontSize + ' !important; transition: all 0.3s ease !important; display: flex !important; align-items: center !important; justify-content: center !important;" title="מחיקת היסטוריית נתונים"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display: block;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button>\n' +
    ' </div>\n' +
    ' <span class="wpccm-top-text" style="flex: 1; color: ' + designSettings.textColor + ' !important; font-size: ' + designSettings.fontSize + ' !important; line-height: 1.4;">' + (b.description || texts.cookie_description || 'We use cookies on our website to give you the most relevant experience by remembering your preferences and repeat visits. By clicking "Accept All", you consent to the use of ALL the cookies.') + (b.policy_url ? ' <a href="' + b.policy_url + '" target="_blank" style="color: ' + designSettings.acceptButtonColor + ' !important; text-decoration: underline !important;">' + (texts.learn_more || 'Learn more') + '</a>' : '') + '</span>\n' +
    ' <div class="wpccm-top-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">\n' +
    ' <button class="wpccm-btn-settings" id="wpccm-settings-btn" style="background-color: ' + designSettings.settingsButtonColor + ' !important; color: white !important; border: none !important; padding: ' + designSettings.buttonPadding + ' !important; font-size: ' + designSettings.fontSize + ' !important;">' + (texts.cookie_settings || 'Cookie Settings') + '</button>\n' +
    ' <button class="wpccm-btn-reject" id="wpccm-reject-all-btn" style="background-color: transparent !important; color: ' + designSettings.textColor + ' !important; border: 1px solid ' + designSettings.textColor + ' !important; padding: ' + designSettings.buttonPadding + ' !important; font-size: ' + designSettings.fontSize + ' !important;">' + (texts.reject_all || 'Reject All') + '</button>\n' +
    ' <button class="wpccm-btn-accept" id="wpccm-accept-all-btn" style="background-color: ' + designSettings.acceptButtonColor + ' !important; color: white !important; border: none !important; padding: ' + designSettings.buttonPadding + ' !important; font-size: ' + designSettings.fontSize + ' !important;">' + (texts.accept_all || 'Accept All') + '</button>\n' +
    ' </div>\n' +
    ' </div>\n' +
    '</div>';

    // Get cookies by category from WPCCM configuration (faster than AJAX)
    var cookiesByCategory = {};
    if (typeof WPCCM !== 'undefined' && WPCCM.options && WPCCM.options.purge && WPCCM.options.purge.cookies) {
        // Organize cookies by category
        var cookies = WPCCM.options.purge.cookies;
        cookies.forEach(function(cookie) {
            var category = 'others'; // default
            if (typeof cookie === 'object' && cookie.category) {
                category = cookie.category;
            }
            if (!cookiesByCategory[category]) {
                cookiesByCategory[category] = [];
            }
            var cookieName = typeof cookie === 'object' ? cookie.name : cookie;
            if (cookieName) {
                cookiesByCategory[category].push(cookieName);
            }
        });
    }
    
    // Render banner immediately with available data
    (function(cookiesByCategory) {
        // Create the detailed modal (hidden by default)
        var modalHtml = ''+
        '<div class="wpccm-modal" id="wpccm-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content">\n' +
        ' <div class="wpccm-modal-header">\n' +
        ' <h2>' + (b.title || texts.privacy_overview || 'Privacy Overview') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close">&times;</button>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-body">\n' +
        ' <p>' + (b.description || texts.cookie_description || 'This website uses cookies to improve your experience while you navigate through the website. Out of these, the cookies that are categorized as necessary are stored on your browser as they are essential for the working of basic functionalities of the website...') + (b.policy_url ? ' <a href="' + b.policy_url + '" target="_blank" style="color: #0073aa; text-decoration: underline;">' + (texts.learn_more || 'Learn more') + '</a>' : '') + '</p>\n' +
        ' <div class="wpccm-categories">\n' +
        generateCategoryToggles(texts, cookiesByCategory) +
        ' </div>\n' +
        ' </div>\n' +
        ' <div class="wpccm-modal-footer">\n' +
        ' <button class="wpccm-btn-save-accept" id="wpccm-save-accept-btn">' + (texts.save_accept || 'SAVE & ACCEPT') + '</button>\n' +
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
        '<div class="wpccm-modal" id="wpccm-data-deletion-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content">\n' +
        ' <div class="wpccm-modal-header">\n' +
        ' <h2>' + (texts.data_deletion || 'מחיקת היסטוריית נתונים') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close">&times;</button>\n' +
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
        ' <div class="wpccm-modal-footer">\n' +
        ' <button class="wpccm-btn-cancel" id="wpccm-cancel-deletion-btn">' + (texts.cancel || 'ביטול') + '</button>\n' +
        ' <button class="wpccm-btn-submit-deletion" id="wpccm-submit-deletion-btn">' + (texts.submit_deletion_request || 'שלח בקשה') + '</button>\n' +
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
        if(saveAcceptBtn) saveAcceptBtn.addEventListener('click', function(){ saveChoices(); });
        
        if(modalCloseBtn) modalCloseBtn.addEventListener('click', function(){ 
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
        
        if(modalOverlay) modalOverlay.addEventListener('click', function(){ 
            modal.style.display = 'none';
            document.body.style.overflow = '';
        });
    })(cookiesByCategory);
}

function showDataDeletionModal() {
    var modal = document.getElementById('wpccm-data-deletion-modal');
    var ipInput = document.getElementById('wpccm-ip-input');
    var editIpBtn = document.getElementById('wpccm-edit-ip-btn');
    var submitBtn = document.getElementById('wpccm-submit-deletion-btn');
    var cancelBtn = document.getElementById('wpccm-cancel-deletion-btn');
    var closeBtn = modal.querySelector('.wpccm-modal-close');
    var overlay = modal.querySelector('.wpccm-modal-overlay');
    
    // Get user's IP address
    var userIP = getUserIP();
    ipInput.value = userIP;
    
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
    }
    
    // Close modal
    if(cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
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
    
    storeNewState(newState);
    
    // Log the consent action
    logConsentAction('reject', newState, Object.keys(newState).filter(function(key) { return newState[key]; }));
    
    // Delete all non-essential cookies immediately
    purgeNonEssentialCookies();
    
    // Block all non-essential scripts from running
    blockNonEssentialScripts();
    
    // Set up continuous monitoring to prevent new cookies
    setupCookieMonitoring();
    
    hideBanner();
}

function purgeNonEssentialCookies() {
    
    var deletedCookies = [];
    var keptCookies = [];
    
    // Essential cookies that should never be deleted
    var essentialCookies = [
        'wpccm_consent', 'consent_necessary', 
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
    
    // Get all non-essential cookies from WPCCM configuration
    if (typeof WPCCM !== 'undefined' && WPCCM.options && WPCCM.options.purge && WPCCM.options.purge.cookies) {
        var cookies = WPCCM.options.purge.cookies;
        
        cookies.forEach(function(cookieInfo) {
            var cookieName = typeof cookieInfo === 'string' ? cookieInfo : cookieInfo.name;
            var cookieCategory = typeof cookieInfo === 'object' ? cookieInfo.category : 'others';
            
            // Only delete non-necessary cookies
            if (cookieCategory !== 'necessary') {
                // Handle wildcard cookies (e.g., woocommerce_*)
                if (cookieName.includes('*')) {
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
                } else if (!isEssential(cookieName)) {
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
    
    // Monitor for new cookies every 5 seconds
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
    }, 5000);
    
    // Store the monitor so we can stop it later if needed
    window.wpccmCookieMonitor = cookieMonitor;
}

function isEssentialCookie(cookieName) {
    var essentialCookies = [
        'wpccm_consent', 'consent_necessary', 
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
    setTimeout(function() {
        initFloatingButton();
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
                
                if (response.success && response.data) {
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

    
    if (!isResolved(s)) {

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
        // Delete the main WPCCM consent cookie
        deleteCookie('wpccm_consent');
        // Also delete old format cookies if they exist
        var categories = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
        categories.forEach(function(cat) {
            deleteCookie('consent_' + cat);
        });
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

    var floatingButtonHtml = '';
        if (designSettings.floatingPosition) {
            var floatingButtonStyle = 'position: fixed !important; z-index: 999998 !important; cursor: pointer !important; border: none !important; border-radius: 50% !important; width: 50px !important; height: 50px !important; background-color: ' + designSettings.settingsButtonColor + ' !important; color: white !important; font-size: 20px !important; box-shadow: 0 2px 10px rgba(0,0,0,0.2) !important; transition: all 0.3s ease !important;';
            
            // Position the floating button
            if (designSettings.floatingPosition === 'top-right') {
                floatingButtonStyle += 'top: 20px !important; right: 20px !important;';
            } else if (designSettings.floatingPosition === 'top-left') {
                floatingButtonStyle += 'top: 20px !important; left: 20px !important;';
            } else if (designSettings.floatingPosition === 'bottom-right') {
                floatingButtonStyle += 'bottom: 20px !important; right: 20px !important;';
            } else if (designSettings.floatingPosition === 'bottom-left') {
                floatingButtonStyle += 'bottom: 20px !important; left: 20px !important;';
            }
            
            floatingButtonHtml = '<button id="wpccm-floating-btn" style="' + floatingButtonStyle + '" title="' + (texts.cookie_settings || 'Cookie Settings') + '">🍪</button>';
        }
    
    // Create floating button
    var button = document.createElement('button');
    // var button = floatingButtonHtml;
    button.innerHTML = floatingButtonHtml;
    // button.id = 'wpccm-floating-button';
    // button.className = 'wpccm-floating-button';
    // button.innerHTML = '🍪'; // Cookie emoji
    button.title = 'תתתתתתתתהגדרות עוגיות';
    
    // Create popup
    var popup = document.createElement('div');
    popup.id = 'wpccm-floating-popup';
    popup.className = 'wpccm-floating-popup';
    popup.innerHTML = '<div class="wpccm-floating-popup-content">' +
        '<button class="wpccm-floating-popup-button primary" onclick="editCookieSettings()">עריכת עוגיות</button>' +
        '<button class="wpccm-floating-popup-button secondary" onclick="closeFloatingPopup()">סגור</button>' +
        '</div>';
    
    // Add click event to toggle popup
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleFloatingPopup();
    });
    
    // Close popup when clicking outside
    document.addEventListener('click', function(e) {
        var popup = document.getElementById('wpccm-floating-popup');
        if (popup && !popup.contains(e.target) && !button.contains(e.target)) {
            closeFloatingPopup();
        }
    });
    
    // Update popup position on window resize
    window.addEventListener('resize', function() {
        var popup = document.getElementById('wpccm-floating-popup');
        if (popup && popup.classList.contains('show')) {
            toggleFloatingPopup(); // This will reposition the popup
        }
    });
    
    // Add to body
    document.body.appendChild(button);
    document.body.appendChild(popup);
}

function toggleFloatingPopup() {
    console.log("toggleFloatingPopup"); 
    var popup = document.getElementById('wpccm-floating-popup');
    var button = document.getElementById('wpccm-floating-btn');
    
    if (popup && button) {
        console.log("toggleFloatingPopup55555"); 
        // Get button position
        var buttonRect = button.getBoundingClientRect();
        var popupRect = popup.getBoundingClientRect();
        
        // Check if button is in top position
        var isTopPosition = buttonRect.top < window.innerHeight / 2;
        
        // Calculate popup position based on button position
        var popupTop, popupLeft;
        
        if (isTopPosition) {
            // Button is in top half - show popup below button
            popupTop = buttonRect.bottom + 10; // 10px gap below
            popup.classList.add('arrow-up');
            popup.classList.remove('arrow-down');
        } else {
            // Button is in bottom half - show popup above button
            popupTop = buttonRect.top - popupRect.height - 10; // 10px gap above
            popup.classList.add('arrow-down');
            popup.classList.remove('arrow-up');
        }
        
        popupLeft = buttonRect.left + (buttonRect.width / 2) - (popupRect.width / 2);
        
        // Ensure popup doesn't go off screen
        if (popupLeft < 20) popupLeft = 20;
        if (popupLeft + popupRect.width > window.innerWidth - 20) {
            popupLeft = window.innerWidth - popupRect.width - 20;
        }
        
        console.log(popupTop);
        console.log(popupLeft);
        // Position the popup
        popup.style.top = popupTop + 'px';
        popup.style.left = popupLeft + 'px';
        
        // Position the arrow to point to the button
        var arrowLeft = buttonRect.left + (buttonRect.width / 2) - popupLeft - 8; // 8px is half arrow width
        popup.style.setProperty('--arrow-left', arrowLeft + 'px');
        
        popup.classList.toggle('show');
    }
}

function closeFloatingPopup() {
    var popup = document.getElementById('wpccm-floating-popup');
    if (popup) {
        popup.classList.remove('show');
    }
}

function editCookieSettings() {
    closeFloatingPopup();
    
    // Reset the consent state to show banner again
    deleteCookie('wpccm_consent');
    
    // Also clear old format cookies
    var categories = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
    categories.forEach(function(cat) {
        deleteCookie('consent_' + cat);
    });
    
    // Re-render the banner
    renderBanner();
}

// Make functions global for onclick handlers
window.editCookieSettings = editCookieSettings;
window.closeFloatingPopup = closeFloatingPopup;

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
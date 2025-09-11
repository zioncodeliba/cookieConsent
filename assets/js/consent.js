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
        
        // Check current consent state for this category
        var currentConsent = getCookie('consent_' + cat.key) === 'true';
        var isChecked = cat.required || currentConsent;
        
        var checked = isChecked ? 'checked' : '';
        var disabled = cat.required ? 'disabled' : '';
        var statusText = cat.required ? (texts.always_enabled || 'Always Enabled') : 
                        (isChecked ? (texts.enabled || 'Enabled') : (texts.disabled || 'Disabled'));
        
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
        // Determine theme for modals
        var modalThemeClass = (designSettings.textColor === '#ffffff' ? 'dark-theme' : 'light-theme');
        var modalBgColor = (designSettings.textColor === '#ffffff' ? '#2c2c2c' : '#ffffff');
        var modalTextColor = (designSettings.textColor === '#ffffff' ? '#ffffff' : '#000000');
        
        // Create the detailed modal (hidden by default)
        var modalHtml = ''+
        '<div class="wpccm-modal ' + modalThemeClass + '" id="wpccm-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content" style="background-color: ' + modalBgColor + '; color: ' + modalTextColor + ';">\n' +
        ' <div class="wpccm-modal-header">\n' +
        ' <h2>' + (b.title || texts.privacy_overview || 'Privacy Overview') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="37" height="37" viewBox="0 0 37 37" fill="none"><path d="M18.5 2.3125C9.48125 2.3125 2.3125 9.48125 2.3125 18.5C2.3125 27.5188 9.48125 34.6875 18.5 34.6875C27.5188 34.6875 34.6875 27.5188 34.6875 18.5C34.6875 9.48125 27.5188 2.3125 18.5 2.3125ZM18.5 32.375C10.8688 32.375 4.625 26.1313 4.625 18.5C4.625 10.8688 10.8688 4.625 18.5 4.625C26.1313 4.625 32.375 10.8688 32.375 18.5C32.375 26.1313 26.1313 32.375 18.5 32.375Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#D3D3D3') + '"/><path d="M24.7437 26.5938L18.5 20.35L12.2563 26.5938L10.4062 24.7437L16.65 18.5L10.4062 12.2563L12.2563 10.4062L18.5 16.65L24.7437 10.4062L26.5938 12.2563L20.35 18.5L26.5938 24.7437L24.7437 26.5938Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#D3D3D3') + '"/></svg></button>\n' +
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
        '<div class="wpccm-modal ' + modalThemeClass + '" id="wpccm-data-deletion-modal" style="display: none;" role="dialog" aria-modal="true">\n' +
        ' <div class="wpccm-modal-overlay"></div>\n' +
        ' <div class="wpccm-modal-content" style="background-color: ' + modalBgColor + '; color: ' + modalTextColor + ';">\n' +
        ' <div class="wpccm-modal-header">\n' +
        ' <h2>' + (texts.data_deletion || 'מחיקת היסטוריית נתונים') + '</h2>\n' +
        ' <button class="wpccm-modal-close" aria-label="Close"><svg xmlns="http://www.w3.org/2000/svg" width="37" height="37" viewBox="0 0 37 37" fill="none"><path d="M18.5 2.3125C9.48125 2.3125 2.3125 9.48125 2.3125 18.5C2.3125 27.5188 9.48125 34.6875 18.5 34.6875C27.5188 34.6875 34.6875 27.5188 34.6875 18.5C34.6875 9.48125 27.5188 2.3125 18.5 2.3125ZM18.5 32.375C10.8688 32.375 4.625 26.1313 4.625 18.5C4.625 10.8688 10.8688 4.625 18.5 4.625C26.1313 4.625 32.375 10.8688 32.375 18.5C32.375 26.1313 26.1313 32.375 18.5 32.375Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#D3D3D3') + '"/><path d="M24.7437 26.5938L18.5 20.35L12.2563 26.5938L10.4062 24.7437L16.65 18.5L10.4062 12.2563L12.2563 10.4062L18.5 16.65L24.7437 10.4062L26.5938 12.2563L20.35 18.5L26.5938 24.7437L24.7437 26.5938Z" fill="' + (designSettings.textColor === '#ffffff' ? '#ffffff' : '#D3D3D3') + '"/></svg></button>\n' +
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
        var floatingButton = document.getElementById('wpccm-floating-btn');
        if (floatingButton) {
            floatingButton.style.display = 'flex';
        } else {
            initFloatingButton();
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

    // ' + designSettings.settingsButtonColor + '
    // transparent !important
    // Dynamic colors based on theme
    var _classbg = (designSettings.textColor === '#ffffff' ? '#000000' : '#ffffff');
    var _classcolor = (designSettings.textColor === '#ffffff' ? '#ffffff' : '#33294D');
    
    // Create floating button with new structure and dynamic styling
    var button = document.createElement('button');
    button.id = 'wpccm-floating-btn';
    button.className = 'wpccm-floating-button';
    
    // Create the button content with original logo and text
    button.innerHTML = '<span class="wpccm-button-icon"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 71 71" fill="none"><g clip-path="url(#clip0_123_23)"><path d="M21.627 47.9957C24.6078 47.9957 27.0242 45.1557 27.0242 41.6523C27.0242 38.149 24.6078 35.309 21.627 35.309C18.6462 35.309 16.2297 38.149 16.2297 41.6523C16.2297 45.1557 18.6462 47.9957 21.627 47.9957Z" fill="' + _classcolor + '"></path><path d="M50.2095 47.9957C53.1903 47.9957 55.6067 45.1557 55.6067 41.6523C55.6067 38.149 53.1903 35.309 50.2095 35.309C47.2287 35.309 44.8123 38.149 44.8123 41.6523C44.8123 45.1557 47.2287 47.9957 50.2095 47.9957Z" fill="' + _classcolor + '"></path><path d="M39.8331 45.4354C38.8069 44.4801 37.4005 43.9451 35.9182 43.9451C34.4359 43.9451 33.0296 44.4801 32.0033 45.4354C31.0531 46.3143 30.521 47.4607 30.521 48.6453C30.521 51.2438 32.9535 53.3455 35.9182 53.3455C38.8829 53.3455 41.3154 51.2438 41.3154 48.6453C41.3154 46.0468 40.7833 46.2761 39.8331 45.4354ZM35.9182 45.015C37.1345 45.015 38.2367 45.4736 38.9969 46.1614C38.2747 46.8875 37.1725 47.3843 35.9182 47.3843C34.6639 47.3843 33.5237 46.8875 32.8395 46.1614C33.5997 45.4354 34.7019 45.015 35.9182 45.015ZM35.9182 52.3902C34.5119 52.3902 33.2576 51.8552 32.3834 51.0145C32.8775 50.5177 34.1698 49.486 35.9182 50.5559C37.6286 49.486 38.9589 50.4795 39.453 51.0145C38.5788 51.8552 37.3245 52.3902 35.9182 52.3902Z" fill="' + _classcolor + '" stroke="' + _classcolor + '" stroke-miterlimit="10"></path><path d="M22.9572 30.303C23.2233 31.6404 21.931 32.9779 20.0686 33.3218C18.2441 33.6657 16.5338 32.8633 16.3057 31.564C16.0396 30.2266 17.3319 28.8891 19.1944 28.5452C21.0188 28.2013 22.7292 29.0037 22.9572 30.303Z" fill="' + _classcolor + '"></path><path d="M48.917 30.303C48.651 31.6404 49.9433 32.9779 51.8057 33.3218C53.6301 33.6657 55.3405 32.8633 55.5685 31.564C55.8346 30.2266 54.5423 28.8891 52.6799 28.5452C50.8555 28.2013 49.1451 29.0037 48.917 30.303Z" fill="' + _classcolor + '"></path><path d="M35.5001 1.64317C37.7046 1.64317 39.8331 1.83424 41.9235 2.25458C42.8357 4.70022 45.3443 8.82724 52.0718 9.43865C52.0718 9.43865 54.2383 14.4446 61.1939 14.4446C68.1495 14.4446 61.65 14.4446 61.878 14.4446C66.4391 20.2148 69.1757 27.5517 69.1757 35.5C69.1757 43.4483 69.1757 37.2578 69.0617 38.1367C69.4798 38.8245 69.4417 39.7417 68.8716 40.3913L68.7956 40.4677C66.4011 56.8229 52.4139 69.3568 35.5001 69.3568C18.5863 69.3568 4.40909 56.6701 2.16658 40.2002C1.67247 39.6652 1.52044 38.8628 1.8245 38.1749L1.93853 37.9074C1.86251 37.105 1.86251 36.3025 1.86251 35.5C1.8245 16.8138 16.9139 1.64317 35.5001 1.64317ZM35.5001 5.12227e-06C16.0397 5.12227e-06 0.190135 15.9349 0.190135 35.5C0.190135 55.0651 0.190135 36.9139 0.266152 37.6017C-0.151942 38.6717 -0.0379162 39.8945 0.608229 40.8498C1.86251 49.1039 5.96744 56.6701 12.2389 62.1728C18.6623 67.8665 26.9482 71 35.5381 71C44.128 71 52.2999 67.9047 58.7233 62.2874C64.9567 56.8229 69.0997 49.3332 70.43 41.1555C71.0761 40.162 71.2281 38.9392 70.8101 37.831C70.8481 37.0667 70.8861 36.3025 70.8861 35.5C70.8861 27.3988 68.2255 19.7562 63.2083 13.4128L62.6762 12.7632H61.84C61.65 12.8014 61.4219 12.8014 61.2319 12.8014C55.4926 12.8014 53.7062 8.94188 53.6302 8.78903L53.2501 7.87191L52.2619 7.79548C46.7126 7.29871 44.4701 4.16524 43.5199 1.68138L43.1778 0.802481L42.2656 0.611415C40.0611 0.191071 37.8186 -0.038208 35.5381 -0.038208L35.5001 5.12227e-06Z" fill="' + _classcolor + '"></path></g><defs><clipPath id="clip0_123_23"><rect width="71" height="71" fill="white"></rect></clipPath></defs></svg></span><span class="wpccm-button-text">COOKIE SETTINGS</span>';
    button.title = 'Cookie Settings';
    
    // Apply dynamic styling based on theme
    var dynamicStyle = '';
    
    // Position the button based on settings
    if (designSettings.floatingPosition === 'top-right') {
        dynamicStyle += 'top: 20px; right: 20px; ';
    } else if (designSettings.floatingPosition === 'top-left') {
        dynamicStyle += 'top: 20px; left: 20px; ';
    } else if (designSettings.floatingPosition === 'bottom-right') {
        dynamicStyle += 'bottom: 20px; right: 20px; ';
    } else if (designSettings.floatingPosition === 'bottom-left') {
        dynamicStyle += 'bottom: 20px; left: 20px; ';
    }
    
    // Add dynamic background color based on theme
    dynamicStyle += 'background-color: ' + _classbg + '; ';
    dynamicStyle += 'color: ' + _classcolor + '; ';
    
    button.style.cssText += dynamicStyle;
    
    // Add click event to open settings directly
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        editCookieSettings(); // Open settings directly without popup
    });
    
    // Add to body
    document.body.appendChild(button);
}

// Popup functions removed - floating button now opens settings directly

function editCookieSettings() {
    // Hide the floating button when opening settings
    var floatingButton = document.getElementById('wpccm-floating-btn');
    if (floatingButton) {
        floatingButton.style.display = 'none';
    }
    
    // Only reset the main consent state to show banner again
    // Keep individual category preferences intact so they show in the modal
    deleteCookie('wpccm_consent');
    
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
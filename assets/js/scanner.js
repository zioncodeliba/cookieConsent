(function(){
    console.log("okokokokinitScanner");
// Check if required globals exist
if (typeof WPCCM_SUGGEST === 'undefined') {
    console.warn('WPCCM_SUGGEST global not found');
    return;
}

const root = document.getElementById('wpccm-cookie-scanner');
if (!root) return;


const raw = document.cookie.split(';').map(c => c.trim()).filter(Boolean);
const cookies = raw.map(row => {
const [name, ...rest] = row.split('=');
return { name, value: rest.join('=') };
});


function suggestCategory(name) {
    const lowercaseName = name.toLowerCase();
    
    // Necessary patterns (essential for site function)
    if (/(phpsessid|wordpress_|_wp_session|csrf_token|wp-settings|session|auth|login|user_|admin_)/i.test(lowercaseName)) {
        return 'necessary';
    }
    
    // Functional patterns
    if (/(wp-|cart_|wishlist_|currency|language|preference|compare|theme_|settings)/i.test(lowercaseName)) {
        return 'functional';
    }
    
    // Performance patterns
    if (/(cache_|cdn_|speed|performance|optimization|compress|minify|lazy|defer|w3tc_|wp_rocket)/i.test(lowercaseName)) {
        return 'performance';
    }
    
    // Analytics patterns
    if (/(_ga|_gid|_gat|__utm|_hjid|_hjsession|ajs_|_mkto_trk|hubspot|_pk_|_omapp|__qca|optimizely|mp_|_clck|_clsk|muid|_scid|_uetvid|vuid)/i.test(lowercaseName)) {
        return 'analytics';
    }
    
    // Advertisement patterns  
    if (/(_fbp|fr|_gcl_|ide|test_cookie|dsid|__gads|__gpi|_gac_|anid|nid|1p_jar|apisid|hsid|sapisid|sid|sidcc|ssid|_pinterest|uuid2|sess|anj|usersync|tdcpm|tdid|tuuid|ouuid|_cc_)/i.test(lowercaseName)) {
        return 'advertisement';
    }
    
    // Social media patterns
    if (/(twitter_|personalization_id|guest_id|datr|sb|wd|xs|c_user|li_sugr|lidc|bcookie|bscookie|ysc|visitor_info)/i.test(lowercaseName)) {
        return 'others';
    }
    
    // Custom/unknown cookies - check for common patterns
    if (/^(test_|demo_|hello|sample_|custom_|user_)/i.test(lowercaseName)) {
        return 'functional';
    }
    
    // WordPress-specific patterns we might have missed
    if (/(wordpress|wp_|_wp)/i.test(lowercaseName)) {
        return 'functional';
    }
    
    // Default to uncategorized for anything unrecognized
    return 'uncategorized';
}

function getCategoryText(cat) {
    const texts = WPCCM_SUGGEST.texts || {};
    return texts[cat] || cat;
}


const texts = WPCCM_SUGGEST.texts || {};
let html = '<table class="widefat fixed striped"><thead><tr><th>'+(texts.cookie || 'Cookie')+'</th><th>'+(texts.suggested_category || 'Suggested Category')+'</th><th>'+(texts.include_in_purge || 'Include in Purge?')+'</th></tr></thead><tbody>';
cookies.forEach(c => {
const suggested = suggestCategory(c.name);
const categoryText = getCategoryText(suggested);
html += '<tr>' +
'<td><code>' + c.name + '</code></td>' +
'<td>' + categoryText + '</td>' +
'<td><input type="checkbox" class="wpccm-purge-cb" data-name="'+c.name+'" '+(suggested !== 'functional' ? 'checked' : '')+'/></td>' +
'</tr>';
});
html += '</tbody></table>';
html += '<button class="button button-primary" id="wpccm-save-purge">'+(texts.update_settings || 'Update Settings')+'</button>';
root.innerHTML = html;


document.getElementById('wpccm-save-purge').addEventListener('click', function(){
const checked = Array.from(document.querySelectorAll('.wpccm-purge-cb:checked')).map(cb => cb.dataset.name);
const xhr = new XMLHttpRequest();
xhr.open('POST', ajaxurl);
xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
xhr.onload = function(){ alert('Saved: '+xhr.responseText); };
xhr.send('action=wpccm_save_purge&cookies='+encodeURIComponent(checked.join(',')));
});

// Purge suggestions functionality
const suggestButton = document.getElementById('wpccm-suggest-purge');
if (suggestButton) {
    suggestButton.addEventListener('click', function() {
        const button = this;
        const originalText = button.textContent;
        const texts = WPCCM_SUGGEST.texts || {};
        
        button.textContent = texts.loading || 'Loading...';
        button.disabled = true;
        
        const xhr = new XMLHttpRequest();
        xhr.open('POST', WPCCM_SUGGEST.ajaxUrl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        renderPurgeSuggestions(response.data);
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                } catch (e) {
                    alert('Error parsing response');
                }
            } else {
                alert('Request failed');
            }
            
            button.textContent = originalText;
            button.disabled = false;
        };
        
        xhr.onerror = function() {
            alert('Network error');
            button.textContent = originalText;
            button.disabled = false;
        };
        
        xhr.send('action=wpccm_suggest_purge_cookies&_wpnonce=' + encodeURIComponent(WPCCM_SUGGEST.nonce));
    });
}

function renderPurgeSuggestions(suggestions) {
    const container = document.getElementById('wpccm-purge-suggestions');
    if (!container || !suggestions.length) return;
    
    const texts = WPCCM_SUGGEST.texts || {};
    
    let html = '<table class="widefat fixed striped">';
    html += '<thead><tr>';
    html += '<th>' + (texts.cookie || 'Cookie') + '</th>';
    html += '<th>' + (texts.suggested_category || 'Category') + '</th>';
    html += '<th>' + (texts.auto_detected || 'Reason') + '</th>';
    html += '<th>' + (texts.should_purge || 'Should Purge?') + '</th>';
    html += '</tr></thead><tbody>';
    
    suggestions.forEach(function(item) {
        const categoryText = getCategoryDisplayText(item.category);
        html += '<tr>';
        html += '<td><code>' + escapeHtml(item.name) + '</code></td>';
        html += '<td>' + categoryText + '</td>';
        html += '<td>' + escapeHtml(item.reason) + '</td>';
        html += '<td><input type="checkbox" class="wpccm-purge-suggestion-cb" data-cookie="' + escapeHtml(item.name) + '" checked></td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    html += '<div style="margin-top: 15px;">';
    html += '<button type="button" class="button button-primary" id="wpccm-update-purge-list">' + (texts.update_purge_list || 'Update Purge List') + '</button>';
    html += '</div>';
    
    container.innerHTML = html;
    
    // Bind update button
    document.getElementById('wpccm-update-purge-list').addEventListener('click', updatePurgeList);
}

function updatePurgeList() {
    const checkboxes = document.querySelectorAll('.wpccm-purge-suggestion-cb:checked');
    const selectedCookies = Array.from(checkboxes).map(cb => cb.dataset.cookie);
    
    if (!selectedCookies.length) {
        alert('No cookies selected');
        return;
    }
    
    const button = document.getElementById('wpccm-update-purge-list');
    const originalText = button.textContent;
    const texts = WPCCM_SUGGEST.texts || {};
    
    button.textContent = texts.loading || 'Loading...';
    button.disabled = true;
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', WPCCM_SUGGEST.ajaxUrl);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert('Updated purge list with ' + response.data.count + ' cookies!');
                    // Optionally refresh the main settings page or show success message
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                }
            } catch (e) {
                alert('Error parsing response');
            }
        } else {
            alert('Request failed');
        }
        
        button.textContent = originalText;
        button.disabled = false;
    };
    
    xhr.onerror = function() {
        alert('Network error');
        button.textContent = originalText;
        button.disabled = false;
    };
    
    const formData = 'action=wpccm_update_purge_list&_wpnonce=' + encodeURIComponent(WPCCM_SUGGEST.nonce);
    const cookieParams = selectedCookies.map((cookie, index) => 'cookies[' + index + ']=' + encodeURIComponent(cookie)).join('&');
    
    xhr.send(formData + '&' + cookieParams);
}

function getCategoryDisplayText(category) {
    const texts = WPCCM_SUGGEST.texts || {};
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

})();

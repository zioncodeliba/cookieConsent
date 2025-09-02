/**
 * LEGACY SCANNER LOGIC - PRESERVED FOR REFACTORING
 * TODO: This file contains the old cookie scanning logic that needs to be refactored
 * TODO: Move working functions to the new admin structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

(function(){
    //console.log("okokokokinitScanner");
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
    // TODO: Implement purge suggestions functionality
}

// TODO: REFACTORING NOTES:
// 1. Cookie scanning functions -> Move to admin/cookie-scanner.js
// 2. Category suggestion functions -> Move to admin/category-suggester.js
// 3. Table rendering functions -> Move to admin/table-renderer.js
// 4. AJAX handling functions -> Move to admin/api.js
// 5. UI interaction functions -> Move to admin/ui-handler.js

// TODO: DEPENDENCIES TO CHECK:
// - WPCCM_SUGGEST global object
// - WordPress AJAX endpoints
// - DOM manipulation functions
// - Cookie parsing functions

// TODO: INTEGRATION POINTS:
// - Replace old scanner with new admin system
// - Update cookie scanning throughout the codebase
// - Maintain backward compatibility during transition
// - Test all functionality after refactoring

// TODO: MODULES TO CREATE:
// - admin/cookie-scanner.js - Cookie scanning logic
// - admin/category-suggester.js - Category suggestions
// - admin/table-renderer.js - Table rendering
// - admin/api.js - AJAX communication
// - admin/ui-handler.js - UI interactions

})();

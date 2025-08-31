/**
 * LEGACY CONSENT LOGIC - PRESERVED FOR REFACTORING
 * TODO: This file contains the old consent logic that needs to be refactored
 * TODO: Move working functions to the new modular structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

(function(){

    // Add immediate console log to see if script loads at all
    console.log('WPCCM consent.js script loaded successfully!');

// Helper functions
function getCookie(name) {
    var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

function setCookie(name, value, days) {
    var expires = '';
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = '; expires=' + date.toUTCString();
    }
    document.cookie = name + '=' + (value || '') + expires + '; path=/';
}

function deleteCookie(name) {
    document.cookie = name + '=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
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

function storeState(state) {
    var days = 180; // Default cookie expiry days
    
    // Store state for all defined categories
    Object.keys(state).forEach(function(key) {
        setCookie('consent_' + key, state[key] ? '1' : '0', days);
    });
}

function storeNewState(state) {
    var days = 180; // Default cookie expiry days
    
    // Store state for all defined categories
    Object.keys(state).forEach(function(key) {
        setCookie('consent_' + key, state[key] ? '1' : '0', days);
    });
}

function isResolved(state) {
    // Check if user has made a consent decision
    return getCookie('consent_necessary') !== null || getCookie('consent_analytics') !== null;
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
            
            // Replace the blocked script
            script.parentNode.replaceChild(newScript, script);
        }
    });
}

// TODO: REFACTORING NOTES:
// 1. getCookie, setCookie, deleteCookie -> Move to cookie-utils.js
// 2. currentState, storeState, storeNewState -> Move to consent-state.js  
// 3. isResolved -> Move to consent-validation.js
// 4. activateDeferredScripts -> Move to script-loader.js
// 5. All other functions -> Review and move to appropriate modules

// TODO: DEPENDENCIES TO CHECK:
// - WPCCM global object
// - jQuery (if used)
// - DOM manipulation functions
// - Cookie management functions

// TODO: INTEGRATION POINTS:
// - Replace old consent system with new modular system
// - Update function calls throughout the codebase
// - Maintain backward compatibility during transition
// - Test all functionality after refactoring

})();

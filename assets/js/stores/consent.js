/**
 * Consent Storage Utilities
 * Manages user consent preferences with localStorage and cookie mirroring
 */

// Storage key for consent preferences
const CC_STORAGE_KEY = 'cc_prefs_v1';

// Expose consent state globally
window.CC_STORAGE_KEY = CC_STORAGE_KEY;
window.ccGetConsent = getConsent;
window.setConsent = setConsent;
window.isAllowed = isAllowed;
window.clearConsent = clearConsent;
window.getConsentForCategories = getConsentForCategories;
window.areRequiredCategoriesAllowed = areRequiredCategoriesAllowed;

// Initialize dataLayer with current consent state
document.addEventListener('DOMContentLoaded', () => {
    if (window.dataLayer && Array.isArray(window.dataLayer)) {
        const currentConsent = getConsent();
        window.dataLayer.push({
            event: 'cc_consent_initialized',
            consent: currentConsent
        });
    }
});

/**
 * Get current consent preferences
 * @returns {Object} Consent preferences object
 */
function getConsent() {
    try {
        // Validate localStorage availability
        if (typeof localStorage !== 'undefined' && localStorage !== null) {
            const stored = localStorage.getItem(CC_STORAGE_KEY);
            if (stored) {
                const parsed = JSON.parse(stored);
                // Validate parsed data structure
                if (parsed && typeof parsed === 'object' && 'necessary' in parsed) {
                    return parsed;
                }
            }
        }
        
        // Fallback to cookie
        const cookieValue = getCookie(CC_STORAGE_KEY);
        if (cookieValue) {
            try {
                const parsed = JSON.parse(decodeURIComponent(cookieValue));
                // Validate parsed data structure
                if (parsed && typeof parsed === 'object' && 'necessary' in parsed) {
                    return parsed;
                }
            } catch (parseError) {
                console.warn('Error parsing cookie consent data:', parseError);
            }
        }
        
        // Return default preferences
        return {
            necessary: true,
            functional: false,
            performance: false,
            analytics: false,
            advertisement: false,
            others: false
        };
    } catch (error) {
        console.warn('Error reading consent preferences:', error);
        return {
            necessary: true,
            functional: false,
            performance: false,
            analytics: false,
            advertisement: false,
            others: false
        };
    }
}

/**
 * Set consent preferences
 * @param {Object} prefs - Consent preferences object
 */
function setConsent(prefs) {
    try {
        // Validate input parameters
        if (!prefs || typeof prefs !== 'object') {
            console.error('Invalid consent preferences provided:', prefs);
            return;
        }
        
        // Validate required fields
        const requiredFields = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
        const missingFields = requiredFields.filter(field => !(field in prefs));
        
        if (missingFields.length > 0) {
            console.error('Missing required consent fields:', missingFields);
            return;
        }
        
        // Validate boolean values
        const invalidFields = requiredFields.filter(field => typeof prefs[field] !== 'boolean');
        if (invalidFields.length > 0) {
            console.error('Invalid consent field types (must be boolean):', invalidFields);
            return;
        }
        
        // Ensure necessary is always true
        if (prefs.necessary !== true) {
            console.warn('Forcing necessary consent to true');
            prefs.necessary = true;
        }
        
        // Store in localStorage
        if (typeof localStorage !== 'undefined' && localStorage !== null) {
            localStorage.setItem(CC_STORAGE_KEY, JSON.stringify(prefs));
        }
        
        // Mirror in cookie (7 days expiry)
        setCookie(CC_STORAGE_KEY, JSON.stringify(prefs), 7);
        
        // Dispatch custom event
        const event = new CustomEvent('cc:changed', {
            detail: { preferences: prefs }
        });
        document.dispatchEvent(event);
        
        // Push to dataLayer for GTM/GA4
        if (window.dataLayer && Array.isArray(window.dataLayer)) {
            window.dataLayer.push({
                event: 'cc_consent_changed',
                consent: prefs
            });
        }
        
        // //console.log('Consent preferences updated:', prefs);
    } catch (error) {
        console.error('Error setting consent preferences:', error);
    }
}

/**
 * Check if consent is allowed for a specific category
 * @param {string} category - Category to check
 * @returns {boolean} Whether consent is allowed
 */
function isAllowed(category) {
    // Validate input parameter
    if (!category || typeof category !== 'string') {
        console.warn('Invalid category provided to isAllowed:', category);
        return false;
    }
    
    // Validate category name
    const validCategories = ['necessary', 'functional', 'performance', 'analytics', 'advertisement', 'others'];
    if (!validCategories.includes(category)) {
        console.warn('Invalid consent category:', category);
        return false;
    }
    
    const prefs = getConsent();
    return prefs[category] === true;
}

/**
 * Helper function to get cookie value
 * @param {string} name - Cookie name
 * @returns {string|null} Cookie value or null
 */
function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? match[2] : null;
}

/**
 * Helper function to set cookie
 * @param {string} name - Cookie name
 * @param {string} value - Cookie value
 * @param {number} days - Days until expiry
 */
function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
}

/**
 * Clear all consent preferences
 */
function clearConsent() {
    try {
        localStorage.removeItem(CC_STORAGE_KEY);
        deleteCookie(CC_STORAGE_KEY);
        
        // Dispatch custom event
        const event = new CustomEvent('cc:changed', {
            detail: { preferences: null }
        });
        document.dispatchEvent(event);
        
        // Push to dataLayer for GTM/GA4
        if (window.dataLayer) {
            window.dataLayer.push({
                event: 'cc_consent_changed',
                consent: null
            });
        }
        
        // //console.log('Consent preferences cleared');
    } catch (error) {
        console.error('Error clearing consent preferences:', error);
    }
}

/**
 * Helper function to delete cookie
 * @param {string} name - Cookie name
 */
function deleteCookie(name) {
    document.cookie = `${name}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
}

/**
 * Get consent preferences for specific categories
 * @param {Array} categories - Array of category names
 * @returns {Object} Object with category:allowed pairs
 */
function getConsentForCategories(categories) {
    const prefs = getConsent();
    const result = {};
    
    categories.forEach(category => {
        result[category] = prefs[category] === true;
    });
    
    return result;
}

/**
 * Check if all required categories are allowed
 * @param {Array} requiredCategories - Array of required category names
 * @returns {boolean} Whether all required categories are allowed
 */
function areRequiredCategoriesAllowed(requiredCategories) {
    const prefs = getConsent();
    
    return requiredCategories.every(category => {
        return prefs[category] === true;
    });
}

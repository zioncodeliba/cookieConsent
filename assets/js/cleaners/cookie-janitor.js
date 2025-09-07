/**
 * Cookie Janitor - Client-side cookie cleaning utility
 * Manages cookie deletion based on user consent preferences
 */

// Use global constant from consent store
// Storage key for consent preferences
const CC_STORAGE_KEY1 = 'cc_prefs_v1';
// Configuration
const JANITOR_CONFIG = {
    // Cookies that should always be preserved
    protectedCookies: [
        CC_STORAGE_KEY1, // Always keep consent preferences
        'PHPSESSID',
        'wordpress_',
        'wp-',
        'woocommerce_cart_hash',
        'woocommerce_items_in_cart',
        'wp_woocommerce_session_'
    ],
    
    // Cookie deletion intervals (in milliseconds)
    sweepInterval: 5000, // 5 seconds
    
    // Maximum cookies to process per sweep
    maxCookiesPerSweep: 50
};

// Global janitor instance
let cookieJanitor = null;

/**
 * Parse all cookies from document.cookie
 * @returns {Array} Array of cookie objects with name, value, and metadata
 */
function parseCookies() {
    try {
        // Validate document.cookie exists
        if (typeof document.cookie !== 'string') {
            console.warn('CC Janitor: document.cookie is not available');
            return [];
        }
        
        const cookies = document.cookie.split(';');
        const parsedCookies = [];
        
        cookies.forEach(cookie => {
            const trimmed = cookie.trim();
            if (trimmed) {
                const [name, ...valueParts] = trimmed.split('=');
                const value = valueParts.join('=');
                
                if (name) {
                    parsedCookies.push({
                        name: name.trim(),
                        value: value || '',
                        fullString: trimmed,
                        isProtected: isProtectedCookie(name.trim())
                    });
                }
            }
        });
        
        return parsedCookies;
    } catch (error) {
        console.error('Error parsing cookies:', error);
        return [];
    }
}

/**
 * Check if a cookie is protected from deletion
 * @param {string} cookieName - Name of the cookie to check
 * @returns {boolean} True if protected, false otherwise
 */
function isProtectedCookie(cookieName) {
    return JANITOR_CONFIG.protectedCookies.some(protectedCookie => {
        if (protectedCookie.endsWith('_')) {
            return cookieName.startsWith(protectedCookie);
        }
        if (protectedCookie.includes('*')) {
            const pattern = protectedCookie.replace(/\*/g, '.*');
            return new RegExp(pattern).test(cookieName);
        }
        return cookieName === protectedCookie;
    });
}

/**
 * Delete a cookie from everywhere (document.cookie, localStorage, sessionStorage)
 * @param {string} name - Name of the cookie to delete
 * @param {boolean} force - Force deletion even if protected
 * @returns {boolean} True if deleted, false otherwise
 */
function deleteCookieEverywhere(name, force = false) {
    try {
        // Validate input parameters
        if (!name || typeof name !== 'string') {
            console.warn('CC Janitor: Invalid cookie name provided');
            return false;
        }
        
        // Validate window.location.hostname exists
        if (!window.location || !window.location.hostname) {
            console.warn('CC Janitor: window.location.hostname not available');
            return false;
        }
        
        // Check if cookie is protected
        if (!force && isProtectedCookie(name)) {
            // //console.log(`Cookie ${name} is protected, skipping deletion`);
            return false;
        }
        
        // Delete from document.cookie
        document.cookie = `${name}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;`;
        document.cookie = `${name}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT; domain=${window.location.hostname};`;
        document.cookie = `${name}=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT; domain=.${window.location.hostname};`;
        
        // Delete from localStorage
        try {
            localStorage.removeItem(name);
        } catch (e) {
            // localStorage might be blocked
        }
        
        // Delete from sessionStorage
        try {
            sessionStorage.removeItem(name);
        } catch (e) {
            // sessionStorage might be blocked
        }
        
        // //console.log(`Cookie ${name} deleted from everywhere`);
        return true;
    } catch (error) {
        console.error(`Error deleting cookie ${name}:`, error);
        return false;
    }
}

/**
 * Sweep cookies based on allowed cookie map
 * @param {Object} allowedCookieMap - Map of cookie names to allowed status
 * @returns {Object} Sweep results
 */
function sweepCookies(allowedCookieMap) {
    try {
        const cookies = parseCookies();
        const results = {
            total: cookies.length,
            deleted: 0,
            protected: 0,
            allowed: 0,
            errors: 0
        };
        
        cookies.forEach(cookie => {
            try {
                if (cookie.isProtected) {
                    results.protected++;
                    return;
                }
                
                // Check if cookie is allowed based on map
                const isAllowed = allowedCookieMap[cookie.name] === true;
                
                if (isAllowed) {
                    results.allowed++;
                } else {
                    // Delete cookie if not allowed
                    if (deleteCookieEverywhere(cookie.name)) {
                        results.deleted++;
                    } else {
                        results.errors++;
                    }
                }
            } catch (error) {
                console.error(`Error processing cookie ${cookie.name}:`, error);
                results.errors++;
            }
        });
        
        // //console.log('Cookie sweep completed:', results);
        return results;
    } catch (error) {
        console.error('Error during cookie sweep:', error);
        return {
            total: 0,
            deleted: 0,
            protected: 0,
            allowed: 0,
            errors: 1
        };
    }
}

/**
 * Boot the cookie janitor with continuous monitoring
 * @param {Object} allowedCookieMap - Map of cookie names to allowed status
 * @param {Object} options - Configuration options
 * @returns {Object} Janitor instance with control methods
 */
function bootCookieJanitor(allowedCookieMap, options = {}) {
    // Stop existing janitor if running
    if (cookieJanitor) {
        cookieJanitor.stop();
    }
    
    // Merge options with defaults
    const config = {
        ...JANITOR_CONFIG,
        ...options
    };
    
    // Create janitor instance
    cookieJanitor = {
        isRunning: false,
        intervalId: null,
        allowedCookieMap: { ...allowedCookieMap },
        config: config,
        
        /**
         * Start the janitor
         */
        start() {
            if (this.isRunning) {
                // //console.log('Cookie janitor is already running');
                return;
            }
            
            this.isRunning = true;
            this.intervalId = setInterval(() => {
                this.performSweep();
            }, this.config.sweepInterval);
            
            // //console.log('Cookie janitor started');
        },
        
        /**
         * Stop the janitor
         */
        stop() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
            
            this.isRunning = false;
            // //console.log('Cookie janitor stopped');
        },
        
        /**
         * Perform a single sweep
         */
        performSweep() {
            try {
                sweepCookies(this.allowedCookieMap);
            } catch (error) {
                console.error('Error during janitor sweep:', error);
            }
        },
        
        /**
         * Update allowed cookie map
         * @param {Object} newMap - New allowed cookie map
         */
        updateAllowedMap(newMap) {
            this.allowedCookieMap = { ...newMap };
            // //console.log('Cookie janitor map updated:', this.allowedCookieMap);
        },
        
        /**
         * Get current status
         */
        getStatus() {
            return {
                isRunning: this.isRunning,
                allowedCookieMap: { ...this.allowedCookieMap },
                config: { ...this.config }
            };
        },
        
        /**
         * Force immediate sweep
         */
        forceSweep() {
            this.performSweep();
        }
    };
    
    // Start the janitor
    cookieJanitor.start();
    
    return cookieJanitor;
}

/**
 * Stop the cookie janitor
 */
function stopCookieJanitor() {
    if (cookieJanitor) {
        cookieJanitor.stop();
        cookieJanitor = null;
        // //console.log('Cookie janitor stopped');
    }
}

/**
 * Get current janitor instance
 */
function getCookieJanitor() {
    return cookieJanitor;
}

/**
 * Utility function to check if janitor is running
 */
function isJanitorRunning() {
    return cookieJanitor && cookieJanitor.isRunning;
}

/**
 * Utility function to get protected cookies list
 */
function getProtectedCookies() {
    return [...JANITOR_CONFIG.protectedCookies];
}

/**
 * Add a cookie to protected list
 * @param {string} cookieName - Cookie name to protect
 */
function protectCookie(cookieName) {
    if (!JANITOR_CONFIG.protectedCookies.includes(cookieName)) {
        JANITOR_CONFIG.protectedCookies.push(cookieName);
        // //console.log(`Cookie ${cookieName} added to protected list`);
    }
}

/**
 * Remove a cookie from protected list
 * @param {string} cookieName - Cookie name to unprotect
 */
function unprotectCookie(cookieName) {
    const index = JANITOR_CONFIG.protectedCookies.indexOf(cookieName);
    if (index > -1) {
        JANITOR_CONFIG.protectedCookies.splice(index, 1);
        // //console.log(`Cookie ${cookieName} removed from protected list`);
    }
}

// Make functions available globally
window.parseCookies = parseCookies;
window.deleteCookieEverywhere = deleteCookieEverywhere;
window.sweepCookies = sweepCookies;
window.bootCookieJanitor = bootCookieJanitor;
window.stopCookieJanitor = stopCookieJanitor;
window.getCookieJanitor = getCookieJanitor;
window.isJanitorRunning = isJanitorRunning;
window.getProtectedCookies = getProtectedCookies;
window.protectCookie = protectCookie;
window.unprotectCookie = unprotectCookie;

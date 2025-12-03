/**
 * CC Detection Probe
 * Scans the current page for scripts and iframes
 * Runs when ?cc_detect=1 is present in URL
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        messageType: 'CC_DETECT_RESULTS',
        scanDelay: 1500, // Delay to catch late-inserted content
        maxRetries: 3
    };

    // State
    let scanAttempts = 0;
    let detectedItems = [];

    /**
     * Initialize the probe
     */
    function init() {
        // Check if we should run
        if (!shouldRun()) {
            return;
        }

        ////console.log('CC Detection Probe initialized');

        // Start scanning
        setTimeout(performScan, CONFIG.scanDelay);
        
        // Also scan after DOM changes
        setupMutationObserver();
    }

    /**
     * Check if probe should run
     */
    function shouldRun() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('cc_detect') === '1';
    }

    /**
     * Perform the main scan
     */
    function performScan() {
        try {
            // Scan scripts
            scanScripts();
            
            // Scan iframes
            scanIframes();
            
            // Send results
            sendResults();
            
        } catch (error) {
            console.error('Error during scan:', error);
            
            // Retry if possible
            if (scanAttempts < CONFIG.maxRetries) {
                scanAttempts++;
                ////console.log(`Retrying scan (attempt ${scanAttempts})...`);
                setTimeout(performScan, CONFIG.scanDelay);
            }
        }
    }

    /**
     * Scan all script tags
     */
    function scanScripts() {
        const scripts = document.querySelectorAll('script');
        
        scripts.forEach(script => {
            try {
                const item = analyzeScript(script);
                if (item) {
                    detectedItems.push(item);
                }
            } catch (error) {
                console.warn('Error analyzing script:', error);
            }
        });
    }

    /**
     * Analyze a single script tag
     */
    function analyzeScript(script) {
        // Skip if no src and no inline content
        if (!script.src && !script.textContent.trim()) {
            return null;
        }

        const item = {
            type: 'script',
            handle: null,
            src: null,
            domain: null,
            cc: null,
            suggested: null
        };

        // Get handle (id, data attributes, or generate one)
        item.handle = getScriptHandle(script);
        
        // Get source URL
        if (script.src) {
            item.src = script.src;
            item.domain = extractDomain(script.src);
        }
        
        // Get consent category if present
        item.cc = script.getAttribute('data-cc');
        
        // Get other attributes
        const type = script.getAttribute('type');
        const async = script.hasAttribute('async');
        const defer = script.hasAttribute('defer');
        
        // Suggest category based on attributes and content
        item.suggested = suggestScriptCategory(script, item);
        
        // Set initial category to suggested if available
        if (item.suggested) {
            item.category = item.suggested;
        }
        
        return item;
    }

    /**
     * Get script handle
     */
    function getScriptHandle(script) {
        // Try ID first
        if (script.id) {
            return script.id;
        }
        
        // Try data attributes
        const dataHandle = script.getAttribute('data-handle') || 
                          script.getAttribute('data-wp-handle') ||
                          script.getAttribute('data-script-handle');
        
        if (dataHandle) {
            return dataHandle;
        }
        
        // Try to infer from src
        if (script.src) {
            const filename = script.src.split('/').pop().split('?')[0];
            if (filename && filename !== '') {
                return filename.replace(/\.js$/, '');
            }
        }
        
        // Generate a handle based on content hash
        if (script.textContent.trim()) {
            const contentHash = btoa(script.textContent.trim()).substring(0, 8);
            return 'inline-' + contentHash;
        }
        
        return null;
    }

    /**
     * Scan all iframe tags
     */
    function scanIframes() {
        const iframes = document.querySelectorAll('iframe');
        
        iframes.forEach(iframe => {
            try {
                const item = analyzeIframe(iframe);
                if (item) {
                    detectedItems.push(item);
                }
            } catch (error) {
                console.warn('Error analyzing iframe:', error);
            }
        });
    }

    /**
     * Analyze a single iframe tag
     */
    function analyzeIframe(iframe) {
        // Skip if no src
        if (!iframe.src) {
            return null;
        }

        const item = {
            type: 'iframe',
            handle: null,
            src: iframe.src,
            domain: extractDomain(iframe.src),
            cc: null,
            suggested: null
        };

        // Get handle (id or generate one)
        item.handle = iframe.id || 'iframe-' + extractDomain(iframe.src);
        
        // Get consent category if present
        item.cc = iframe.getAttribute('data-cc');
        
        // Suggest category based on domain
        item.suggested = suggestIframeCategory(iframe, item);
        
        // Set initial category to suggested if available
        if (item.suggested) {
            item.category = item.suggested;
        }
        
        return item;
    }

    /**
     * Extract domain from URL
     */
    function extractDomain(url) {
        try {
            // Skip data URLs
            if (url.startsWith('data:')) {
                return null;
            }
            
            const urlObj = new URL(url);
            return urlObj.hostname;
        } catch (error) {
            return null;
        }
    }

    /**
     * Suggest category for script
     */
    function suggestScriptCategory(script, item) {
        const handle = (item.handle || '').toLowerCase();
        const src = (item.src || '').toLowerCase();
        const domain = (item.domain || '').toLowerCase();
        
        // Analytics
        if (handle.includes('analytics') || handle.includes('ga') || handle.includes('gtm') ||
            src.includes('google-analytics') || src.includes('googletagmanager') ||
            src.includes('gtag') || src.includes('analytics')) {
            return 'analytics';
        }
        
        // Marketing/Advertising
        if (handle.includes('ads') || handle.includes('advertising') || handle.includes('facebook') ||
            handle.includes('pixel') || handle.includes('conversion') ||
            src.includes('facebook') || src.includes('ads') || src.includes('pixel') ||
            domain.includes('facebook') || domain.includes('doubleclick') || domain.includes('googleadservices')) {
            return 'marketing';
        }
        
        // Performance
        if (handle.includes('lazy') || handle.includes('optimize') || handle.includes('minify') ||
            handle.includes('cache') || handle.includes('cdn') ||
            src.includes('optimize') || src.includes('performance') || src.includes('cdn')) {
            return 'performance';
        }
        
        // Functional
        if (handle.includes('form') || handle.includes('contact') || handle.includes('chat') ||
            handle.includes('support') || handle.includes('help') ||
            src.includes('form') || src.includes('contact') || src.includes('chat')) {
            return 'functional';
        }
        
        // WordPress core (usually necessary)
        if (handle.includes('wp-') || handle.includes('wordpress') ||
            src.includes('wp-includes') || src.includes('wp-content')) {
            return 'necessary';
        }
        
        // Default
        return 'others';
    }

    /**
     * Suggest category for iframe
     */
    function suggestIframeCategory(iframe, item) {
        const handle = (item.handle || '').toLowerCase();
        const src = (item.src || '').toLowerCase();
        const domain = (item.domain || '').toLowerCase();
        
        // Video platforms (usually marketing/analytics)
        if (domain.includes('youtube') || domain.includes('vimeo') || domain.includes('dailymotion') ||
            src.includes('youtube') || src.includes('vimeo')) {
            return 'marketing';
        }
        
        // Social media (usually marketing)
        if (domain.includes('facebook') || domain.includes('twitter') || domain.includes('instagram') ||
            domain.includes('linkedin') || domain.includes('pinterest')) {
            return 'marketing';
        }
        
        // Maps (usually functional)
        if (domain.includes('google') && src.includes('maps') ||
            domain.includes('openstreetmap') || src.includes('map')) {
            return 'functional';
        }
        
        // Forms and tools (usually functional)
        if (src.includes('form') || src.includes('contact') || src.includes('chat') ||
            src.includes('support') || src.includes('help')) {
            return 'functional';
        }
        
        // Default
        return 'others';
    }

    /**
     * Setup mutation observer for dynamic content
     */
    function setupMutationObserver() {
        if (!window.MutationObserver) {
            return;
        }

        const observer = new MutationObserver(function(mutations) {
            let hasNewContent = false;
            
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            // Check for new scripts
                            if (node.tagName === 'SCRIPT') {
                                const item = analyzeScript(node);
                                if (item) {
                                    detectedItems.push(item);
                                    hasNewContent = true;
                                }
                            }
                            
                            // Check for new iframes
                            if (node.tagName === 'IFRAME') {
                                const item = analyzeIframe(node);
                                if (item) {
                                    detectedItems.push(item);
                                    hasNewContent = true;
                                }
                            }
                            
                            // Check children
                            const newScripts = node.querySelectorAll('script');
                            const newIframes = node.querySelectorAll('iframe');
                            
                            newScripts.forEach(script => {
                                const item = analyzeScript(script);
                                if (item) {
                                    detectedItems.push(item);
                                    hasNewContent = true;
                                }
                            });
                            
                            newIframes.forEach(iframe => {
                                const item = analyzeIframe(iframe);
                                if (item) {
                                    detectedItems.push(item);
                                    hasNewContent = true;
                                }
                            });
                        }
                    });
                }
            });
            
            // If new content was found, send updated results
            if (hasNewContent) {
                setTimeout(sendResults, 1000); // Wait a bit for more content
            }
        });

        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Send results to parent window
     */
    function sendResults() {
        try {
            // Remove duplicates based on key
            const uniqueItems = removeDuplicates(detectedItems);
            
            // Send message to parent
            if (window.parent && window.parent !== window) {
                // Send message without origin restriction since iframe is sandboxed
                window.parent.postMessage({
                    type: CONFIG.messageType,
                    items: uniqueItems
                }, '*');
                
                ////console.log(`CC Detection Probe: Sent ${uniqueItems.length} items to parent window`);
            }
            
        } catch (error) {
            console.error('Error sending results:', error);
        }
    }

    /**
     * Remove duplicate items
     */
    function removeDuplicates(items) {
        const seen = new Set();
        const unique = [];
        
        items.forEach(item => {
            const key = createItemKey(item);
            if (!seen.has(key)) {
                seen.add(key);
                unique.push(item);
            }
        });
        
        return unique;
    }

    /**
     * Create unique key for item
     */
    function createItemKey(item) {
        if (item.handle) {
            return item.type + '|handle:' + item.handle;
        } else if (item.src) {
            return item.type + '|src:' + item.src;
        }
        return item.type + '|' + Date.now();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

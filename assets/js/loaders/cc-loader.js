/**
 * Client Consent Loader
 * Handles dynamic loading of scripts and iframes based on user consent
 */

// Use global functions from consent store

// Configuration
const CC_LOADER_CONFIG = {
    scriptSelector: 'script[type="text/plain"][data-cc]',
    iframeSelector: 'iframe[data-cc]',
    observerOptions: {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['data-cc']
    }
};

// Track processed elements to avoid double-processing
const processedElements = new WeakSet();

/**
 * Main loader class
 */
class ConsentLoader {
    constructor() {
        this.observer = null;
        this.init();
    }

    /**
     * Initialize the loader
     */
    init() {
        // Process existing elements
        this.processExistingElements();
        
        // Set up mutation observer
        this.setupMutationObserver();
        
        // Listen for consent changes
        this.setupConsentListener();
        
        // console.log('CC Loader initialized');
    }

    /**
     * Process existing elements on page load
     */
    processExistingElements() {
        // Process existing scripts
        const existingScripts = document.querySelectorAll(CC_LOADER_CONFIG.scriptSelector);
        existingScripts.forEach(script => this.processScript(script));

        // Process existing iframes
        const existingIframes = document.querySelectorAll(CC_LOADER_CONFIG.iframeSelector);
        existingIframes.forEach(iframe => this.processIframe(iframe));
    }

    /**
     * Set up mutation observer for dynamic content
     */
    setupMutationObserver() {
        this.observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                // Handle added nodes
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Check if the added node itself matches
                        if (this.isConsentElement(node)) {
                            this.processElement(node);
                        }
                        
                        // Check children of added node
                        const scripts = node.querySelectorAll?.(CC_LOADER_CONFIG.scriptSelector) || [];
                        const iframes = node.querySelectorAll?.(CC_LOADER_CONFIG.iframeSelector) || [];
                        
                        scripts.forEach(script => this.processScript(script));
                        iframes.forEach(iframe => this.processIframe(iframe));
                    }
                });

                // Handle attribute changes
                if (mutation.type === 'attributes' && 
                    mutation.attributeName === 'data-cc' &&
                    this.isConsentElement(mutation.target)) {
                    this.processElement(mutation.target);
                }
            });
        });

        // Start observing
        this.observer.observe(document.body, CC_LOADER_CONFIG.observerOptions);
    }

    /**
     * Set up listener for consent changes
     */
    setupConsentListener() {
        document.addEventListener('cc:changed', (event) => {
            console.log('Consent changed, rehydrating elements:', event.detail);
            this.rehydrateAllElements();
        });
    }

    /**
     * Check if element is a consent element
     */
    isConsentElement(element) {
        return element.matches?.(CC_LOADER_CONFIG.scriptSelector) || 
               element.matches?.(CC_LOADER_CONFIG.iframeSelector);
    }

    /**
     * Process a script element
     */
    processScript(script) {
        if (processedElements.has(script)) return;
        
        // Validate script element
        if (!script || !script.nodeType || script.nodeType !== Node.ELEMENT_NODE) {
            console.warn('CC Loader: Invalid script element provided');
            return;
        }
        
        const category = script.getAttribute('data-cc');
        if (!category) return;

        if (isAllowed(category)) {
            this.hydrateScript(script);
        } else {
            this.blockScript(script);
        }
        
        processedElements.add(script);
    }

    /**
     * Process an iframe element
     */
    processIframe(iframe) {
        if (processedElements.has(iframe)) return;
        
        // Validate iframe element
        if (!iframe || !iframe.nodeType || iframe.nodeType !== Node.ELEMENT_NODE) {
            console.warn('CC Loader: Invalid iframe element provided');
            return;
        }
        
        const category = iframe.getAttribute('data-cc');
        if (!category) return;

        if (isAllowed(category)) {
            this.hydrateIframe(iframe);
        } else {
            this.blockIframe(iframe);
        }
        
        processedElements.add(iframe);
    }

    /**
     * Process any consent element
     */
    processElement(element) {
        if (element.matches(CC_LOADER_CONFIG.scriptSelector)) {
            this.processScript(element);
        } else if (element.matches(CC_LOADER_CONFIG.iframeSelector)) {
            this.processIframe(element);
        }
    }

    /**
     * Hydrate a blocked script
     */
    hydrateScript(script) {
        try {
            // Validate script element and parent
            if (!script || !script.parentNode) {
                console.warn('CC Loader: Cannot hydrate script - invalid element or no parent');
                return;
            }
            
            // Create new script element
            const newScript = document.createElement('script');
            
            // Copy all attributes except type and data-cc
            Array.from(script.attributes).forEach(attr => {
                if (attr.name !== 'type' && attr.name !== 'data-cc') {
                    newScript.setAttribute(attr.name, attr.value);
                }
            });

            // Handle src vs inline content
            const dataSrc = script.getAttribute('data-src');
            if (dataSrc) {
                newScript.src = dataSrc;
            } else {
                newScript.innerHTML = script.innerHTML;
            }

            // Replace the blocked script
            script.parentNode.replaceChild(newScript, script);
            
            console.log('Script hydrated for category:', script.getAttribute('data-cc'));
        } catch (error) {
            console.error('Error hydrating script:', error);
        }
    }

    /**
     * Block a script
     */
    blockScript(script) {
        // Mark as blocked
        script.setAttribute('data-cc-blocked', 'true');
        script.style.display = 'none';
        
        console.log('Script blocked for category:', script.getAttribute('data-cc'));
    }

    /**
     * Hydrate a blocked iframe
     */
    hydrateIframe(iframe) {
        try {
            // Validate iframe element
            if (!iframe) {
                console.warn('CC Loader: Cannot hydrate iframe - invalid element');
                return;
            }
            
            // Get the source from data-src or src
            const dataSrc = iframe.getAttribute('data-src') || iframe.getAttribute('src');
            if (dataSrc) {
                iframe.src = dataSrc;
            }
            
            // Remove blocked state
            iframe.removeAttribute('data-cc-blocked');
            iframe.style.display = '';
            
            console.log('Iframe hydrated for category:', iframe.getAttribute('data-cc'));
        } catch (error) {
            console.error('Error hydrating iframe:', error);
        }
    }

    /**
     * Block an iframe
     */
    blockIframe(iframe) {
        // Store original src if not already stored
        if (!iframe.hasAttribute('data-src')) {
            iframe.setAttribute('data-src', iframe.src || '');
        }
        
        // Clear src and mark as blocked
        iframe.src = '';
        iframe.setAttribute('data-cc-blocked', 'true');
        iframe.style.display = 'none';
        
        console.log('Iframe blocked for category:', iframe.getAttribute('data-cc'));
    }

    /**
     * Rehydrate all elements based on current consent
     */
    rehydrateAllElements() {
        // Clear processed elements cache
        processedElements.clear();
        
        // Process all consent elements again
        this.processExistingElements();
        
        console.log('All elements rehydrated based on new consent');
    }

    /**
     * Destroy the loader and clean up
     */
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
        
        // Remove event listeners
        document.removeEventListener('cc:changed', this.setupConsentListener);
        
        console.log('CC Loader destroyed');
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.ccLoader = new ConsentLoader();
    });
} else {
    window.ccLoader = new ConsentLoader();
}

// Make available globally
window.ConsentLoader = ConsentLoader;

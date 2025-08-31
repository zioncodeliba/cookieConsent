<?php
/**
 * Script Enqueuing for WP Cookie Consent Manager
 * Loads consent-related JavaScript files on the frontend
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('wp_enqueue_script')) {
    return;
}

/**
 * Enqueue consent-related scripts on frontend
 * Only loads on frontend, not in admin area
 */
function wpccm_enqueue_consent_scripts() {
    // Only enqueue on frontend
    if (is_admin()) {
        return;
    }
    
    // Check if we're in a feed or REST API request
    if (is_feed() || wp_is_json_request()) {
        return;
    }
    
    // Check if scripts are already enqueued
    if (wp_script_is('wpccm-consent-stores', 'enqueued')) {
        return;
    }
    
    // Get plugin directory URL
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    
    // Enqueue consent storage utilities
    wp_enqueue_script(
        'wpccm-consent-stores',
        $plugin_url . 'assets/js/stores/consent.js',
        [],
        '1.0.0',
        true
    );
    
    // Enqueue consent loader
    wp_enqueue_script(
        'wpccm-cc-loader',
        $plugin_url . 'assets/js/loaders/cc-loader.js',
        ['wpccm-consent-stores'],
        '1.0.0',
        true
    );
    
    // Enqueue cookie janitor
    wp_enqueue_script(
        'wpccm-cookie-janitor',
        $plugin_url . 'assets/js/cleaners/cookie-janitor.js',
        ['wpccm-consent-stores', 'wpccm-cc-loader'],
        '1.0.0',
        true
    );
    
    // Get cookie name mapping from options
    $cookie_name_map = get_option('cc_cookie_name_map', []);
    
    // Get script handle mapping from options
    $script_handle_map = get_option('cc_script_handle_map', []);
    
    // Ensure both are arrays
    if (!is_array($cookie_name_map)) {
        $cookie_name_map = [];
    }
    if (!is_array($script_handle_map)) {
        $script_handle_map = [];
    }
    
    // Create allowed cookie map for localization
    $cc_allowed_cookie_map = [];
    
    // Process cookie name mappings
    foreach ($cookie_name_map as $cookie_name => $category) {
        $cc_allowed_cookie_map[$cookie_name] = [
            'category' => $category,
            'allowed' => false // Will be set by JavaScript based on current consent
        ];
    }
    
    // Localize script with data
    wp_localize_script('wpccm-consent-stores', 'wpccmConsentData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpccm_consent_nonce'),
        'cookieNameMap' => $cookie_name_map,
        'scriptHandleMap' => $script_handle_map,
        'allowedCookieMap' => $cc_allowed_cookie_map,
        'siteUrl' => get_site_url(),
        'homeUrl' => get_home_url(),
        'isSsl' => is_ssl(),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ]);
    
    // Localize cc-loader with specific data
    wp_localize_script('wpccm-cc-loader', 'wpccmLoaderData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpccm_loader_nonce'),
        'ccAllowedCookieMap' => $cc_allowed_cookie_map,
        'scriptSelector' => 'script[type="text/plain"][data-cc]',
        'iframeSelector' => 'iframe[data-cc]',
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ]);
    
    // Localize cookie janitor with specific data
    wp_localize_script('wpccm-cookie-janitor', 'wpccmJanitorData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpccm_janitor_nonce'),
        'ccAllowedCookieMap' => $cc_allowed_cookie_map,
        'sweepInterval' => 5000, // 5 seconds
        'maxCookiesPerSweep' => 50,
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ]);
    
    // Add inline script for immediate initialization
    wp_add_inline_script('wpccm-consent-stores', wpccm_get_consent_init_script(), 'after');
    
    // Enqueue detection probe if cc_detect=1 is present
    if (isset($_GET['cc_detect']) && $_GET['cc_detect'] === '1') {
        wp_enqueue_script(
            'wpccm-detect-probe',
            $plugin_url . 'assets/js/detect/probe.js',
            [],
            '1.0.0',
            true
        );
    }
}

/**
 * Generate inline script for consent initialization
 * 
 * @return string JavaScript code
 */
function wpccm_get_consent_init_script() {
    return "
        // Initialize consent system when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // console.log('WPCCM: Initializing consent system...');
            
            // Check if all required modules are loaded
            if (typeof window.wpccmConsentData === 'undefined') {
                console.error('WPCCM: Consent data not available');
                return;
            }
            
            // Initialize consent loader if available
            if (typeof window.ccLoader !== 'undefined') {
                // console.log('WPCCM: Consent loader initialized');
            }
            
            // Initialize cookie janitor if available
            if (typeof window.cookieJanitor !== 'undefined') {
                // console.log('WPCCM: Cookie janitor initialized');
            }
            
            // console.log('WPCCM: Consent system initialization complete');
        });
    ";
}

/**
 * Enqueue consent styles on frontend
 */
function wpccm_enqueue_consent_styles() {
    // Only enqueue on frontend
    if (is_admin()) {
        return;
    }
    
    // Get plugin directory URL
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    
    // Enqueue main consent styles
    wp_enqueue_style(
        'wpccm-consent-styles',
        $plugin_url . 'assets/css/consent.css',
        [],
        '1.0.0'
    );
}

/**
 * Add preload hints for critical resources
 */
function wpccm_add_resource_hints($hints, $relation_type) {
    // Only add hints on frontend
    if (is_admin()) {
        return $hints;
    }
    
    if ($relation_type === 'preload') {
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        
        // Preload critical consent scripts
        $hints[] = [
            'href' => $plugin_url . 'assets/js/stores/consent.js',
            'as' => 'script',
            'crossorigin' => ''
        ];
        
        $hints[] = [
            'href' => $plugin_url . 'assets/js/loaders/cc-loader.js',
            'as' => 'script',
            'crossorigin' => ''
        ];
    }
    
    return $hints;
}

/**
 * Add async/defer attributes to consent scripts
 */
function wpccm_add_script_attributes($tag, $handle, $src) {
    // Only modify consent scripts
    $consent_scripts = [
        'wpccm-consent-stores',
        'wpccm-cc-loader',
        'wpccm-cookie-janitor'
    ];
    
    if (in_array($handle, $consent_scripts)) {
        // Add async attribute for better performance
        $tag = str_replace('<script ', '<script async ', $tag);
    }
    
    return $tag;
}

/**
 * Conditionally enqueue scripts based on page context
 */
function wpccm_conditional_enqueue() {
    // Skip on specific pages if needed
    if (is_404() || is_search()) {
        return;
    }
    
    // Check if consent banner should be shown
    $show_consent = wpccm_should_show_consent();
    
    if ($show_consent) {
        wpccm_enqueue_consent_scripts();
        wpccm_enqueue_consent_styles();
    }
}

/**
 * Check if consent banner should be shown
 * 
 * @return bool True if consent banner should be shown
 */
function wpccm_should_show_consent() {
    // Always show on frontend for now
    // This can be customized based on specific requirements
    return !is_admin();
}

// Hook into WordPress
add_action('wp_enqueue_scripts', 'wpccm_conditional_enqueue', 10);

// Add resource hints
add_filter('wp_resource_hints', 'wpccm_add_resource_hints', 10, 2);

// Add script attributes
add_filter('script_loader_tag', 'wpccm_add_script_attributes', 10, 3);

/**
 * Get current consent scripts status
 * 
 * @return array Status of consent scripts
 */
function wpccm_get_scripts_status() {
    return [
        'stores_loaded' => wp_script_is('wpccm-consent-stores', 'enqueued'),
        'loader_loaded' => wp_script_is('wpccm-cc-loader', 'enqueued'),
        'janitor_loaded' => wp_script_is('wpccm-cookie-janitor', 'enqueued'),
        'styles_loaded' => wp_style_is('wpccm-consent-styles', 'enqueued'),
        'cookie_map' => get_option('cc_cookie_name_map', []),
        'script_map' => get_option('cc_script_handle_map', [])
    ];
}

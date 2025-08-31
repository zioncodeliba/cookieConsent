<?php
/**
 * WordPress Header Filter
 * Filters Set-Cookie headers based on user consent preferences
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('get_option')) {
    return;
}

/**
 * Filter Set-Cookie headers on shutdown
 * This function runs after all headers are sent but before the script ends
 */
function wpccm_filter_set_cookie_headers() {
    // Only run if we're in the frontend
    if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }
    
    // Check if headers have already been sent
    if (headers_sent()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WPCCM: Headers already sent, cannot filter Set-Cookie headers');
        }
        return;
    }
    
    // Get cookie name mapping from options
    $cookie_name_map = get_option('cc_cookie_name_map', []);
    
    // If no mapping exists, don't filter
    if (empty($cookie_name_map)) {
        return;
    }
    
    // Get current consent preferences from cookie
    $consent_prefs = wpccm_get_current_consent();
    
    // If no consent preferences, don't filter
    if (empty($consent_prefs)) {
        return;
    }
    
    // Get all headers that were sent
    $headers_list = headers_list();
    
    // Filter Set-Cookie headers
    $filtered_headers = wpccm_filter_cookie_headers($headers_list, $cookie_name_map, $consent_prefs);
    
    // Log filtered headers for debugging (only in debug mode)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        wpccm_log_header_filtering($headers_list, $filtered_headers, $consent_prefs);
    }
}

/**
 * Filter cookie headers based on consent preferences
 * 
 * @param array $headers_list List of all headers
 * @param array $cookie_name_map Mapping of cookie names to consent categories
 * @param array $consent_prefs Current consent preferences
 * @return array Filtered headers
 */
function wpccm_filter_cookie_headers($headers_list, $cookie_name_map, $consent_prefs) {
    $filtered_headers = [];
    
    foreach ($headers_list as $header) {
        // Check if this is a Set-Cookie header
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookie_name = wpccm_extract_cookie_name($header);
            
            if ($cookie_name && isset($cookie_name_map[$cookie_name])) {
                $category = $cookie_name_map[$cookie_name];
                
                // Check if this category is allowed
                if (isset($consent_prefs[$category]) && $consent_prefs[$category] === true) {
                    // Consent granted, keep the header
                    $filtered_headers[] = $header;
                } else {
                    // Consent denied, drop the header
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("WPCCM: Dropped Set-Cookie header for {$cookie_name} (category: {$category}) - consent denied");
                    }
                }
            } else {
                // Cookie not in mapping, keep the header
                $filtered_headers[] = $header;
            }
        } else {
            // Not a Set-Cookie header, keep it
            $filtered_headers[] = $header;
        }
    }
    
    return $filtered_headers;
}

/**
 * Extract cookie name from Set-Cookie header
 * 
 * @param string $header Set-Cookie header string
 * @return string|false Cookie name or false if extraction fails
 */
function wpccm_extract_cookie_name($header) {
    // Remove "Set-Cookie: " prefix
    $cookie_part = substr($header, 12);
    
    // Find the first semicolon or end of string
    $semicolon_pos = strpos($cookie_part, ';');
    
    if ($semicolon_pos !== false) {
        $cookie_part = substr($cookie_part, 0, $semicolon_pos);
    }
    
    // Split on equals sign to get name and value
    $parts = explode('=', $cookie_part, 2);
    
    if (count($parts) >= 1) {
        return trim($parts[0]);
    }
    
    return false;
}

/**
 * Get current consent preferences from cookie
 * 
 * @return array|false Consent preferences or false if not available
 */
function wpccm_get_current_consent() {
    // Check if consent cookie exists
    if (!isset($_COOKIE['cc_prefs_v1'])) {
        return false;
    }
    
    try {
        $consent_data = json_decode(stripslashes($_COOKIE['cc_prefs_v1']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }
        
        return $consent_data;
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WPCCM: Error parsing consent cookie: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Log header filtering for debugging
 * 
 * @param array $original_headers Original headers
 * @param array $filtered_headers Filtered headers
 * @param array $consent_prefs Consent preferences
 */
function wpccm_log_header_filtering($original_headers, $filtered_headers, $consent_prefs) {
    $set_cookie_headers = array_filter($original_headers, function($header) {
        return stripos($header, 'Set-Cookie:') === 0;
    });
    
    $filtered_set_cookie_headers = array_filter($filtered_headers, function($header) {
        return stripos($header, 'Set-Cookie:') === 0;
    });
    
    $dropped_count = count($set_cookie_headers) - count($filtered_set_cookie_headers);
    
    error_log(sprintf(
        "WPCCM: Header filtering complete. Original Set-Cookie: %d, Filtered: %d, Dropped: %d, Consent: %s",
        count($set_cookie_headers),
        count($filtered_set_cookie_headers),
        $dropped_count,
        json_encode($consent_prefs)
    ));
}

/**
 * Add cookie name mapping
 * 
 * @param string $cookie_name Cookie name
 * @param string $category Consent category
 * @return bool True on success, false on failure
 */
function wpccm_add_cookie_mapping($cookie_name, $category) {
    $valid_categories = [
        'necessary',
        'functional',
        'performance', 
        'analytics',
        'advertisement',
        'others'
    ];
    
    if (!in_array($category, $valid_categories, true)) {
        return false;
    }
    
    $mappings = get_option('cc_cookie_name_map', []);
    $mappings[$cookie_name] = $category;
    
    return update_option('cc_cookie_name_map', $mappings);
}

/**
 * Remove cookie name mapping
 * 
 * @param string $cookie_name Cookie name to remove
 * @return bool True on success, false on failure
 */
function wpccm_remove_cookie_mapping($cookie_name) {
    $mappings = get_option('cc_cookie_name_map', []);
    
    if (isset($mappings[$cookie_name])) {
        unset($mappings[$cookie_name]);
        return update_option('cc_cookie_name_map', $mappings);
    }
    
    return false;
}

/**
 * Get all cookie name mappings
 * 
 * @return array Array of cookie name mappings
 */
function wpccm_get_cookie_mappings() {
    return get_option('cc_cookie_name_map', []);
}

/**
 * Check if a cookie is mapped to a consent category
 * 
 * @param string $cookie_name Cookie name to check
 * @return string|false Consent category or false if not mapped
 */
function wpccm_get_cookie_category($cookie_name) {
    $mappings = get_option('cc_cookie_name_map', []);
    return isset($mappings[$cookie_name]) ? $mappings[$cookie_name] : false;
}

/**
 * Bulk update cookie mappings
 * 
 * @param array $mappings Array of cookie_name => category mappings
 * @return bool True on success, false on failure
 */
function wpccm_bulk_update_cookie_mappings($mappings) {
    $valid_categories = [
        'necessary',
        'functional',
        'performance',
        'analytics', 
        'advertisement',
        'others'
    ];
    
    // Validate all categories
    foreach ($mappings as $cookie_name => $category) {
        if (!in_array($category, $valid_categories, true)) {
            return false;
        }
    }
    
    return update_option('cc_cookie_name_map', $mappings);
}

// Hook into shutdown to filter headers
add_action('shutdown', 'wpccm_filter_set_cookie_headers', 999);

// Also hook into wp_footer for earlier execution if needed
add_action('wp_footer', 'wpccm_filter_set_cookie_headers', 999);

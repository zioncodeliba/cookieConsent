<?php
/**
 * WordPress Script Tag Filters
 * Modifies script tags based on consent category mappings
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
 * Filter script tags to add consent attributes
 * 
 * @param string $tag    The complete script tag
 * @param string $handle The script handle
 * @param string $src    The script source URL
 * @return string Modified script tag
 */
function wpccm_filter_script_tag($tag, $handle, $src) {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return $tag;
    }
    
    // Don't block probe.js - it needs to run for detection
    if (strpos($src, 'probe.js') !== false) {
        return $tag;
    }
    
    // Get script handle mapping from options
    $script_handle_map = get_option('cc_script_handle_map', []);
    
    // Check if this handle is mapped to a consent category
    if (isset($script_handle_map[$handle]) && !empty($script_handle_map[$handle])) {
        $category = sanitize_text_field($script_handle_map[$handle]);
        
        // Only process if category is valid
        if (wpccm_is_valid_consent_category($category)) {
            // Convert to consent-controlled script
            $tag = wpccm_convert_to_consent_script($tag, $src, $category);
            return $tag;
        }
    }
    
    // If no handle mapping, check domain mapping
    if (!empty($src)) {
        $domain = wpccm_extract_domain_from_url($src);
        if ($domain) {
            $script_domain_map = get_option('cc_script_domain_map', []);
            
            if (isset($script_domain_map[$domain]) && !empty($script_domain_map[$domain])) {
                $category = sanitize_text_field($script_domain_map[$domain]);
                
                // Only process if category is valid
                if (wpccm_is_valid_consent_category($category)) {
                    // Convert to consent-controlled script
                    $tag = wpccm_convert_to_consent_script($tag, $src, $category);
                }
            }
        }
    }
    
    return $tag;
}

/**
 * Filter script tags for enqueued scripts
 * 
 * @param string $tag    The complete script tag
 * @param string $handle The script handle
 * @param string $src    The script source URL
 * @return string Modified script tag
 */
function wpccm_filter_enqueued_script_tag($tag, $handle, $src) {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return $tag;
    }
    
    // Get script handle mapping from options
    $script_handle_map = get_option('cc_script_handle_map', []);
    
    // Check if this handle is mapped to a consent category
    if (isset($script_handle_map[$handle]) && !empty($script_handle_map[$handle])) {
        $category = sanitize_text_field($script_handle_map[$handle]);
        
        // Only process if category is valid
        if (wpccm_is_valid_consent_category($category)) {
            // Convert to consent-controlled script
            $tag = wpccm_convert_to_consent_script($tag, $src, $category);
        }
    }
    
    return $tag;
}

/**
 * Filter inline script tags
 * 
 * @param string $data The script data
 * @param string $handle The script handle
 * @return string Modified script data
 */
function wpccm_filter_inline_script($data, $handle) {
    // Get script handle mapping from options
    $script_handle_map = get_option('cc_script_handle_map', []);
    
    // Check if this handle is mapped to a consent category
    if (isset($script_handle_map[$handle]) && !empty($script_handle_map[$handle])) {
        $category = sanitize_text_field($script_handle_map[$handle]);
        
        // Only process if category is valid
        if (wpccm_is_valid_consent_category($category)) {
            // Convert inline script to consent-controlled
            $data = wpccm_convert_inline_to_consent_script($data, $category);
        }
    }
    
    return $data;
}

/**
 * Convert regular script tag to consent-controlled script
 * 
 * @param string $tag      The original script tag
 * @param string $src      The script source URL
 * @param string $category The consent category
 * @return string Modified script tag
 */
function wpccm_convert_to_consent_script($tag, $src, $category) {
    // Create new consent-controlled script tag
    $new_tag = sprintf(
        '<script type="text/plain" data-cc="%s" data-src="%s"></script>',
        esc_attr($category),
        esc_url($src)
    );
    
    return $new_tag;
}

/**
 * Convert inline script to consent-controlled script
 * 
 * @param string $data     The script data
 * @param string $category The consent category
 * @return string Modified script data
 */
function wpccm_convert_inline_to_consent_script($data, $category) {
    // Wrap inline script in consent-controlled container
    $new_data = sprintf(
        '<script type="text/plain" data-cc="%s">%s</script>',
        esc_attr($category),
        $data
    );
    
    return $new_data;
}

/**
 * Check if consent category is valid
 * 
 * @param string $category The category to validate
 * @return bool True if valid, false otherwise
 */
function wpccm_is_valid_consent_category($category) {
    $valid_categories = [
        'necessary',
        'functional', 
        'performance',
        'analytics',
        'advertisement',
        'others'
    ];
    
    return in_array($category, $valid_categories, true);
}

/**
 * Extract domain from URL
 * 
 * @param string $url The URL to extract domain from
 * @return string|false Domain name or false on failure
 */
function wpccm_extract_domain_from_url($url) {
    if (empty($url)) {
        return false;
    }
    
    // Skip data URLs
    if (strpos($url, 'data:') === 0) {
        return false;
    }
    
    // Parse URL
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        return false;
    }
    
    return $parsed['host'];
}

/**
 * Add filters for script tag modification
 */
function wpccm_add_script_filters() {
    // Filter script tags
    add_filter('script_loader_tag', 'wpccm_filter_script_tag', 10, 3);
    
    // Filter inline scripts
    add_filter('wp_add_inline_script', 'wpccm_filter_inline_script', 10, 2);
}

// Initialize filters
wpccm_add_script_filters();

/**
 * Utility function to manually apply consent filter to script tag
 * 
 * @param string $tag      The script tag
 * @param string $handle   The script handle
 * @param string $category The consent category
 * @return string Modified script tag
 */
function wpccm_apply_consent_filter($tag, $handle, $category = '') {
    if (empty($category)) {
        // Try to get category from handle mapping
        $script_handle_map = get_option('cc_script_handle_map', []);
        $category = isset($script_handle_map[$handle]) ? $script_handle_map[$handle] : '';
    }
    
    if (!empty($category) && wpccm_is_valid_consent_category($category)) {
        // Extract src from tag
        preg_match('/src=["\']([^"\']+)["\']/', $tag, $matches);
        $src = isset($matches[1]) ? $matches[1] : '';
        
        if (!empty($src)) {
            return wpccm_convert_to_consent_script($tag, $src, $category);
        }
    }
    
    return $tag;
}

/**
 * Get all mapped script handles
 * 
 * @return array Array of script handle mappings
 */
function wpccm_get_script_handle_mappings() {
    return get_option('cc_script_handle_map', []);
}

/**
 * Add script handle mapping
 * 
 * @param string $handle   The script handle
 * @param string $category The consent category
 * @return bool True on success, false on failure
 */
function wpccm_add_script_mapping($handle, $category) {
    if (!wpccm_is_valid_consent_category($category)) {
        return false;
    }
    
    $mappings = get_option('cc_script_handle_map', []);
    $mappings[$handle] = $category;
    
    return update_option('cc_script_handle_map', $mappings);
}

/**
 * Remove script handle mapping
 * 
 * @param string $handle The script handle to remove
 * @return bool True on success, false on failure
 */
function wpccm_remove_script_mapping($handle) {
    $mappings = get_option('cc_script_handle_map', []);
    
    if (isset($mappings[$handle])) {
        unset($mappings[$handle]);
        return update_option('cc_script_handle_map', $mappings);
    }
    
    return false;
}

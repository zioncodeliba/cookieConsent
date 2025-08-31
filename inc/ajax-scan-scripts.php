<?php
/**
 * AJAX Script Scanner
 * Scans registered WordPress scripts for consent management
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for scanning registered scripts
 */
function wpccm_ajax_scan_scripts() {
    // Check nonce
    if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error('Invalid request method');
        return;
    }
    
    try {
        $scripts = wpccm_scan_registered_scripts();
        wp_send_json_success(['scripts' => $scripts]);
    } catch (Exception $e) {
        wp_send_json_error('Error scanning scripts: ' . $e->getMessage());
    }
}

/**
 * Scan all registered WordPress scripts
 * 
 * @return array Array of script objects
 */
function wpccm_scan_registered_scripts() {
    global $wp_scripts;
    
    $scripts = [];
    
    if (!$wp_scripts || !is_object($wp_scripts)) {
        return $scripts;
    }
    
    // Get all registered scripts
    foreach ($wp_scripts->registered as $handle => $script) {
        // Skip WordPress core scripts
        if (wpccm_is_core_script($handle)) {
            continue;
        }
        
        // Skip already mapped scripts
        $existing_mappings = get_option('cc_script_handle_map', []);
        if (isset($existing_mappings[$handle])) {
            continue;
        }
        
        $scripts[] = [
            'handle' => $handle,
            'src' => $script->src,
            'deps' => $script->deps,
            'ver' => $script->ver,
            'in_footer' => $script->extra['in_footer'] ?? false
        ];
    }
    
    // Sort by handle
    usort($scripts, function($a, $b) {
        return strcasecmp($a['handle'], $b['handle']);
    });
    
    return $scripts;
}

/**
 * Check if script is WordPress core script
 * 
 * @param string $handle Script handle
 * @return bool True if core script
 */
function wpccm_is_core_script($handle) {
    $core_scripts = [
        'jquery',
        'jquery-core',
        'jquery-migrate',
        'jquery-ui-core',
        'jquery-ui-widget',
        'jquery-ui-mouse',
        'jquery-ui-draggable',
        'jquery-ui-droppable',
        'jquery-ui-sortable',
        'jquery-ui-resizable',
        'jquery-ui-selectable',
        'jquery-ui-selectmenu',
        'jquery-ui-accordion',
        'jquery-ui-autocomplete',
        'jquery-ui-button',
        'jquery-ui-datepicker',
        'jquery-ui-dialog',
        'jquery-ui-menu',
        'jquery-ui-progressbar',
        'jquery-ui-slider',
        'jquery-ui-spinner',
        'jquery-ui-tabs',
        'jquery-ui-tooltip',
        'wp-embed',
        'wp-emoji-release',
        'wp-api-request',
        'wp-api-fetch',
        'wp-data',
        'wp-element',
        'wp-components',
        'wp-blocks',
        'wp-editor',
        'wp-format-library',
        'wp-nux',
        'wp-plugins',
        'wp-polyfill',
        'wp-url',
        'wp-util',
        'wp-hooks',
        'wp-i18n',
        'wp-is-shallow-equal',
        'wp-keycodes',
        'wp-token-list',
        'wp-dom-ready',
        'wp-a11y',
        'wp-autop',
        'wp-blob',
        'wp-block-editor',
        'wp-block-library',
        'wp-block-serialization-default-parser',
        'wp-compose',
        'wp-core-data',
        'wp-customize-loader',
        'wp-customize-preview',
        'wp-customize-selective-refresh',
        'wp-date',
        'wp-deprecated',
        'wp-dom',
        'wp-escape-html',
        'wp-html-entities',
        'wp-http-fetch',
        'wp-keyboard-shortcuts',
        'wp-list-reusable-blocks',
        'wp-media-utils',
        'wp-notices',
        'wp-nux',
        'wp-page-template',
        'wp-preferences',
        'wp-priority-queue',
        'wp-primitives',
        'wp-redux-routine',
        'wp-render-block',
        'wp-rich-text',
        'wp-server-side-render',
        'wp-shortcode',
        'wp-token-list',
        'wp-url',
        'wp-viewport',
        'wp-wordcount'
    ];
    
    return in_array($handle, $core_scripts, true);
}

// Register AJAX handlers
add_action('wp_ajax_wpccm_scan_scripts', 'wpccm_ajax_scan_scripts');

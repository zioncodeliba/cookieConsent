<?php
/**
 * AJAX Endpoint for Deleting Cookies (Including HttpOnly)
 * Handles server-side cookie deletion for both logged-in and non-logged-in users
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('wp_ajax_nopriv_wpccm_delete_cookies')) {
    return;
}

/**
 * AJAX handler for deleting cookies (logged-in users)
 */
function wpccm_ajax_delete_cookies() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        wp_send_json_error('Plugin not activated');
        return;
    }
    
    // Check if user has permission
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_delete_cookies')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Process cookie deletion
    $result = wpccm_process_cookie_deletion();
    wp_send_json_success($result);
}

/**
 * AJAX handler for deleting cookies (non-logged-in users)
 */
function wpccm_ajax_nopriv_delete_cookies() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        wp_send_json_error('Plugin not activated');
        return;
    }
    
    // Verify nonce for security (same nonce for both)
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_delete_cookies')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    // Process cookie deletion
    $result = wpccm_process_cookie_deletion();
    wp_send_json_success($result);
}

/**
 * Process the actual cookie deletion
 * 
 * @return array Result of cookie deletion operation
 */
function wpccm_process_cookie_deletion() {
    try {
        // Check if this is a POST request
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return [
                'success' => false,
                'message' => 'Invalid request method',
                'deleted' => [],
                'errors' => []
            ];
        }
        
        // Get cookie names from POST data
        $cookie_names = $_POST['names'] ?? [];
        
        // Validate input
        if (empty($cookie_names) || !is_array($cookie_names)) {
            return [
                'success' => false,
                'message' => 'No cookie names provided',
                'deleted' => [],
                'errors' => []
            ];
        }
        
        // Limit the number of cookies that can be deleted in one request
        if (count($cookie_names) > 100) {
            return [
                'success' => false,
                'message' => 'Too many cookie names provided (max: 100)',
                'deleted' => [],
                'errors' => []
            ];
        }
        
        // Sanitize cookie names
        $sanitized_names = array_map('sanitize_text_field', $cookie_names);
        
        // Filter out empty names and validate format
        $sanitized_names = array_filter($sanitized_names, function($name) {
            return !empty($name) && 
                   strlen($name) <= 255 && 
                   preg_match('/^[a-zA-Z0-9_\-\.]+$/', $name);
        });
        
        if (empty($sanitized_names)) {
            return [
                'success' => false,
                'message' => 'No valid cookie names provided',
                'deleted' => [],
                'errors' => []
            ];
        }
        
        // Get current domain and path
        $domain = wpccm_get_cookie_domain();
        $path = wpccm_get_cookie_path();
        
        // Process each cookie
        $deleted_cookies = [];
        $errors = [];
        
        foreach ($sanitized_names as $cookie_name) {
            $delete_result = wpccm_delete_single_cookie($cookie_name, $domain, $path);
            
            if ($delete_result['success']) {
                $deleted_cookies[] = $cookie_name;
            } else {
                $errors[] = [
                    'cookie' => $cookie_name,
                    'error' => $delete_result['error']
                ];
            }
        }
        
        // Prepare response
        $result = [
            'success' => true,
            'message' => sprintf(
                'Successfully processed %d cookies. Deleted: %d, Errors: %d',
                count($sanitized_names),
                count($deleted_cookies),
                count($errors)
            ),
            'deleted' => $deleted_cookies,
            'errors' => $errors,
            'total_requested' => count($sanitized_names),
            'total_deleted' => count($deleted_cookies),
            'total_errors' => count($errors)
        ];
        
        // Log the operation
        wpccm_log_cookie_deletion($result);
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Exception occurred: ' . $e->getMessage(),
            'deleted' => [],
            'errors' => []
        ];
    }
}

/**
 * Delete a single cookie with multiple domain/path combinations
 * 
 * @param string $cookie_name Name of the cookie to delete
 * @param string $domain Domain for the cookie
 * @param string $path Path for the cookie
 * @return array Result of deletion attempt
 */
function wpccm_delete_single_cookie($cookie_name, $domain, $path) {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return [
            'success' => false,
            'error' => 'Plugin not activated'
        ];
    }
    
    try {
        $deleted = false;
        $expired_time = time() - 3600; // 1 hour ago
        
        // Try different domain/path combinations to ensure deletion
        $combinations = [
            ['domain' => $domain, 'path' => $path],
            ['domain' => $domain, 'path' => '/'],
            ['domain' => '', 'path' => $path],
            ['domain' => '', 'path' => '/'],
            ['domain' => '.' . $domain, 'path' => $path],
            ['domain' => '.' . $domain, 'path' => '/']
        ];
        
        foreach ($combinations as $combo) {
            $result = setcookie(
                $cookie_name,
                '',
                $expired_time,
                $combo['path'],
                $combo['domain'],
                is_ssl(),
                true // HttpOnly
            );
            
            if ($result) {
                $deleted = true;
            }
        }
        
        // Also try to unset from $_COOKIE superglobal
        if (isset($_COOKIE[$cookie_name])) {
            unset($_COOKIE[$cookie_name]);
        }
        
        if ($deleted) {
            return [
                'success' => true,
                'message' => "Cookie '{$cookie_name}' deleted successfully"
            ];
        } else {
            return [
                'success' => false,
                'error' => "Failed to delete cookie '{$cookie_name}'"
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => "Exception deleting cookie '{$cookie_name}': " . $e->getMessage()
        ];
    }
}

/**
 * Get appropriate cookie domain
 * 
 * @return string Cookie domain
 */
function wpccm_get_cookie_domain() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return '';
    }
    
    // Get site URL
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    
    if (isset($parsed_url['host'])) {
        $host = $parsed_url['host'];
        
        // Remove www. prefix if present
        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }
        
        return $host;
    }
    
    return '';
}

/**
 * Get appropriate cookie path
 * 
 * @return string Cookie path
 */
function wpccm_get_cookie_path() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return '/';
    }
    
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    
    if (isset($parsed_url['path'])) {
        return $parsed_url['path'];
    }
    
    return '/';
}

/**
 * Log cookie deletion operation
 * 
 * @param array $result Result of cookie deletion operation
 */
function wpccm_log_cookie_deletion($result) {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return;
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            'WPCCM: Cookie deletion completed. Requested: %d, Deleted: %d, Errors: %d',
            $result['total_requested'],
            $result['total_deleted'],
            $result['total_errors']
        ));
        
        if (!empty($result['deleted'])) {
            error_log('WPCCM: Deleted cookies: ' . implode(', ', $result['deleted']));
        }
        
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                error_log("WPCCM: Error deleting cookie '{$error['cookie']}': {$error['error']}");
            }
        }
    }
}

/**
 * Generate nonce for cookie deletion
 * 
 * @return string Nonce value
 */
function wpccm_get_delete_cookies_nonce() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return '';
    }
    
    return wp_create_nonce('wpccm_delete_cookies');
}

/**
 * Verify if user can delete cookies
 * 
 * @return bool True if user can delete cookies
 */
function wpccm_can_delete_cookies() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return false;
    }
    
    // Allow both logged-in and non-logged-in users
    // Additional restrictions can be added here if needed
    return true;
}

// Register AJAX actions
add_action('wp_ajax_wpccm_delete_cookies', 'wpccm_ajax_delete_cookies');
add_action('wp_ajax_nopriv_wpccm_delete_cookies', 'wpccm_ajax_nopriv_delete_cookies');

/**
 * Enqueue JavaScript for cookie deletion
 */
function wpccm_enqueue_delete_cookies_script() {
    // Check if plugin is activated
    if (!WP_CCM_Consent::is_plugin_activated()) {
        return;
    }
    
    wp_enqueue_script(
        'wpccm-delete-cookies',
        plugin_dir_url(__FILE__) . '../assets/js/delete-cookies.js',
        ['jquery'],
        '1.0.0',
        true
    );
    
    // Localize script with nonce and AJAX URL
    wp_localize_script('wpccm-delete-cookies', 'wpccmDeleteCookies', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wpccm_get_delete_cookies_nonce(),
        'action' => 'wpccm_delete_cookies'
    ]);
}

// Enqueue script on frontend (only if plugin is activated)
add_action('wp_enqueue_scripts', 'wpccm_enqueue_delete_cookies_script');

// Add hook to check plugin activation before any operations
add_action('init', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_loaded', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('template_redirect', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_head', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_footer', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_print_scripts', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_print_styles', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_print_footer_scripts', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

// Add hook to check plugin activation before any operations
add_action('wp_print_admin_notices', function() {
    if (!WP_CCM_Consent::is_plugin_activated()) {
        // Plugin not activated, disable all functionality
        return;
    }
});

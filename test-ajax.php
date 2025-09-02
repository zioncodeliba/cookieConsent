<?php
/**
 * Test AJAX functionality
 * This file can be used to test if the AJAX endpoints are working
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test function to verify AJAX setup
function wpccm_test_ajax_setup() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    // Test nonce creation
    $test_nonce = wp_create_nonce('wpccm_detect_nonce');
    
    // Test AJAX URL
    $ajax_url = admin_url('admin-ajax.php');
    
    // Test if actions are registered
    global $wp_filter;
    $store_action_registered = isset($wp_filter['wp_ajax_cc_detect_store']);
    $save_action_registered = isset($wp_filter['wp_ajax_cc_detect_save_map']);
    
    return array(
        'nonce_created' => !empty($test_nonce),
        'ajax_url' => $ajax_url,
        'store_action_registered' => $store_action_registered,
        'save_action_registered' => $save_action_registered,
        'test_nonce' => $test_nonce
    );
}

// Add test endpoint
add_action('wp_ajax_wpccm_test_ajax', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('code' => 'forbidden'));
        return;
    }
    
    $test_result = wpccm_test_ajax_setup();
    wp_send_json_success($test_result);
});

// Add admin notice for testing
add_action('admin_notices', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'cc-detect') {
        echo '<div class="notice notice-info"><p><strong>AJAX Test:</strong> <a href="#" onclick="testAjax()">Test AJAX Setup</a></p></div>';
        echo '<script>
        function testAjax() {
            jQuery.ajax({
                url: "' . admin_url('admin-ajax.php') . '",
                type: "POST",
                data: {
                    action: "wpccm_test_ajax",
                    nonce: "' . wp_create_nonce('wpccm_detect_nonce') . '"
                },
                success: function(response) {
                    //console.log("AJAX Test Response:", response);
                    alert("AJAX Test: " + JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Test Error:", xhr, status, error);
                    alert("AJAX Test Error: " + error);
                }
            });
        }
        </script>';
    }
});

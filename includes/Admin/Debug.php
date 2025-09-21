<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Debug {
    public function register() {
        add_action('wp_ajax_wpccm_get_debug_log', [$this, 'ajax_get_debug_log']);
    }

    /**
     * AJAX handler for getting debug log
     */
    public function ajax_get_debug_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }

        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        if (!wp_verify_nonce($nonce, 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }

        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        $log_content = '';

        if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
            $lines = file($debug_log_path);
            $wpccm_lines = [];
            foreach (array_slice($lines, -1000) as $line) {
                if (strpos($line, 'WPCCM:') !== false) {
                    $wpccm_lines[] = $line;
                }
            }

            if ($wpccm_lines) {
                $log_content = implode('', array_slice($wpccm_lines, -50));
            } else {
                $log_content = "No WPCCM debug entries found in the last 1000 log lines.\n";
                $log_content .= "Debug log path: {$debug_log_path}\n";
                $log_content .= "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'enabled' : 'disabled') . "\n";
                $log_content .= "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'enabled' : 'disabled') . "\n";
            }
        } else {
            $log_content = "Debug log file not found or not readable.\n";
            $log_content .= "Expected path: {$debug_log_path}\n";
            $log_content .= "File exists: " . (file_exists($debug_log_path) ? 'yes' : 'no') . "\n";
            $log_content .= "File readable: " . (is_readable($debug_log_path) ? 'yes' : 'no') . "\n";
            $log_content .= "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'enabled' : 'disabled') . "\n";
            $log_content .= "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'enabled' : 'disabled') . "\n";
        }

        wp_send_json_success([
            'log' => $log_content,
            'timestamp' => current_time('mysql'),
        ]);
    }
}

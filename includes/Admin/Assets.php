<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Assets {
    public function register() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue($hook) {
        $plugin_pages = [
            'settings_page_wpccm',
            'settings_page_wpccm-scanner',
            'settings_page_wpccm-management',
            'settings_page_wpccm-deletion',
            'settings_page_wpccm-history',
            'cookie-consent_page_wpccm-advanced-scanner',
        ];

        if (!in_array($hook, $plugin_pages, true)) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_style('wpccm-admin', WPCCM_URL . 'assets/css/consent.css', [], WPCCM_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

        wp_localize_script('jquery', 'wpccm_ajax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wpccm_admin_nonce'),
        ]);

        error_log('WPCCM: Current hook: ' . $hook);

        if ($hook === 'cookie-consent_page_wpccm-advanced-scanner') {
            error_log('WPCCM: Advanced scanner page loaded');
        }
    }
}

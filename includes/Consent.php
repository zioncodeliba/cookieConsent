<?php
if (!defined('ABSPATH')) { exit; }


class WP_CCM_Consent {
// Default categories
public static function categories() {
    // Get custom categories from options
    $custom_categories = get_option('wpccm_custom_categories', []);
    
    if (!empty($custom_categories) && is_array($custom_categories)) {
        $categories = [];
        foreach ($custom_categories as $category) {
            if (isset($category['key']) && isset($category['name'])) {
                $categories[$category['key']] = $category['name'];
            }
        }
        return $categories;
    }
    
    // Fallback to default categories
    return [
        'necessary' => wpccm_text('necessary'),
        'functional' => wpccm_text('functional'),
        'performance' => wpccm_text('performance'),
        'analytics' => wpccm_text('analytics'),
        'advertisement' => wpccm_text('advertisement'),
        'others' => wpccm_text('others'),
    ];
}

public static function get_categories_with_details() {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ck_categories';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'");
    
    if ($table_exists) {
        // Get categories from new database table
        $categories = $wpdb->get_results(
            "SELECT * FROM $categories_table WHERE is_active = 1 ORDER BY created_at ASC",
            ARRAY_A
        );
        
        if (!empty($categories)) {
            // Convert database format to expected format
            $formatted_categories = [];
            $necessary_category = null;
            $other_categories = [];
            
            foreach ($categories as $category) {
                $formatted_category = [
                    'key' => $category['category_key'],
                    'name' => $category['display_name'],
                    'description' => $category['description'] ?: 'תיאור לא זמין',
                    'required' => (bool) $category['is_essential'],
                    'enabled' => (bool) $category['is_active'],
                    'color' => $category['color'] ?: '#666666',
                    'icon' => $category['icon'] ?: '📦',
                    'sort_order' => (int) $category['sort_order']
                ];
                
                // Separate necessary category from others
                if ($category['category_key'] === 'necessary') {
                    $necessary_category = $formatted_category;
                } else {
                    $other_categories[] = $formatted_category;
                }
            }
            
            // Build final array: necessary first, then others by creation date (oldest to newest)
            if ($necessary_category) {
                $formatted_categories[] = $necessary_category;
            }
            
            // Add other categories (already sorted by created_at ASC from the query)
            foreach ($other_categories as $category) {
                $formatted_categories[] = $category;
            }
            
            return $formatted_categories;
        }
    }
    
    // Fallback: Try old wp_options method
    $custom_categories = get_option('wpccm_custom_categories', []);
    
    if (!empty($custom_categories) && is_array($custom_categories)) {
        return $custom_categories;
    }
    
    // Final fallback to default categories
    return [
        [
            'key' => 'necessary',
            'name' => wpccm_text('necessary'),
            'description' => 'Essential cookies required for basic site functionality.',
            'required' => true,
            'enabled' => true,
            'color' => '#d63384',
            'icon' => '🔒',
            'sort_order' => 1
        ],
        [
            'key' => 'functional', 
            'name' => wpccm_text('functional'),
            'description' => 'Cookies that enhance your experience by remembering your preferences.',
            'required' => false,
            'enabled' => true,
            'color' => '#0073aa',
            'icon' => '⚙️',
            'sort_order' => 2
        ],
        [
            'key' => 'performance',
            'name' => wpccm_text('performance'), 
            'description' => 'Cookies that help us optimize our website performance.',
            'required' => false,
            'enabled' => true,
            'color' => '#00a32a',
            'icon' => '📈',
            'sort_order' => 3
        ],
        [
            'key' => 'analytics',
            'name' => wpccm_text('analytics'),
            'description' => 'Cookies that help us understand how visitors interact with our website.',
            'required' => false,
            'enabled' => true,
            'color' => '#dba617',
            'icon' => '📊',
            'sort_order' => 4
        ],
        [
            'key' => 'advertisement',
            'name' => wpccm_text('advertisement'),
            'description' => 'Cookies used to deliver personalized advertisements.',
            'required' => false,
            'enabled' => true,
            'color' => '#8c8f94',
            'icon' => '📢',
            'sort_order' => 5
        ],
        [
            'key' => 'others',
            'name' => wpccm_text('others'),
            'description' => 'Other cookies that do not fit into the above categories.',
            'required' => false,
            'enabled' => true,
            'color' => '#666666',
            'icon' => '📦',
            'sort_order' => 6
        ]
    ];
}


public static function default_options() {
return [
'banner' => [
'title' => wpccm_text('we_use_cookies'),
'description' => wpccm_text('cookie_description'),
'policy_url' => '',
],
'map' => [
// handle => category key, e.g. 'google-analytics' => 'analytics'
],
'purge' => [
'cookies' => ['_ga', '_ga_XXXX', '_gid', '_fbp', '_hjSessionUser'],
],

];
}


public static function get_options() {
$opts = get_option('wpccm_options');
if (!is_array($opts)) { $opts = []; }
return wp_parse_args($opts, self::default_options());
}


// Current consent state from cookies (essential cookie)
public static function get_state() {
$state = [
'necessary' => true, // Always enabled
'functional' => (isset($_COOKIE['consent_functional']) && $_COOKIE['consent_functional'] === '1'),
'performance' => (isset($_COOKIE['consent_performance']) && $_COOKIE['consent_performance'] === '1'),
'analytics' => (isset($_COOKIE['consent_analytics']) && $_COOKIE['consent_analytics'] === '1'),
'advertisement' => (isset($_COOKIE['consent_advertisement']) && $_COOKIE['consent_advertisement'] === '1'),
'others' => (isset($_COOKIE['consent_others']) && $_COOKIE['consent_others'] === '1'),
];
return $state;
}


public static function handles_map() {
$opts = self::get_options();
$map = isset($opts['map']) && is_array($opts['map']) ? $opts['map'] : [];
// Normalize values to known categories
$cats = self::categories();
foreach ($map as $handle => $cat) {
if (!isset($cats[$cat])) {
unset($map[$handle]);
}
}
return $map;
}

/**
 * Check if plugin is activated (via license validation)
 */
public static function is_plugin_activated() {
    // Regular activation check
    $license_key = get_option('wpccm_license_key', '');
    
    if (empty($license_key)) {
        return false;
    }
    
    // Check if license is valid
    $dashboard = WP_CCM_Dashboard::get_instance();
    $result = $dashboard->test_connection_silent();
    return $result && isset($result['success']) && $result['success'];
}
}

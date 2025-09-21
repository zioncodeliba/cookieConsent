<?php
if (!defined('ABSPATH')) { exit; }


class WP_CCM_Consent {
private static $script_lookup = null;
// Default categories
public static function categories() {
    // Try loading categories from the dedicated database table first
    if (function_exists('wpccm_get_categories')) {
        $db_categories = wpccm_get_categories(true);

        if (!empty($db_categories) && is_array($db_categories)) {
            $categories = [];

            foreach ($db_categories as $category) {
                if (empty($category['category_key']) || empty($category['display_name'])) {
                    continue;
                }

                $categories[$category['category_key']] = $category['display_name'];
            }

            if (!empty($categories)) {
                return $categories;
            }
        }
    }

    // Fallback to custom categories stored in wp_options (legacy behaviour)
    $custom_categories = get_option('wpccm_custom_categories', []);

    if (!empty($custom_categories) && is_array($custom_categories)) {
        $categories = [];
        foreach ($custom_categories as $category) {
            if (isset($category['key']) && isset($category['name'])) {
                $categories[$category['key']] = $category['name'];
            }
        }
        if (!empty($categories)) {
            return $categories;
        }
    }

    // Final fallback to hard-coded default categories
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
    $state = [];

    // Prefer detailed categories from the database to determine required/optional flags
    $detailed_categories = self::get_categories_with_details();

    if (!empty($detailed_categories) && is_array($detailed_categories)) {
        foreach ($detailed_categories as $category) {
            if (empty($category['key'])) {
                continue;
            }

            $key = sanitize_key($category['key']);
            $is_required = !empty($category['required']) || $key === 'necessary';

            if ($is_required) {
                $state[$key] = true;
                continue;
            }

            $cookie_name = 'consent_' . $key;
            $state[$key] = isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === '1';
        }
    }

    // Ensure all known categories have a state (fallback to legacy list if needed)
    $categories = self::categories();

    foreach ($categories as $key => $label) {
        $key = sanitize_key($key);

        if (isset($state[$key])) {
            continue;
        }

        if ($key === 'necessary') {
            $state[$key] = true;
            continue;
        }

        $cookie_name = 'consent_' . $key;
        $state[$key] = isset($_COOKIE[$cookie_name]) && $_COOKIE[$cookie_name] === '1';
    }

    return $state;
}


public static function handles_map() {
    $categories = self::categories();
    self::ensure_script_lookup($categories);

    if (isset(self::$script_lookup['handles']) && is_array(self::$script_lookup['handles'])) {
        return self::$script_lookup['handles'];
    }

    return [];
}

public static function inline_hash_map() {
    $categories = self::categories();
    self::ensure_script_lookup($categories);

    if (isset(self::$script_lookup['hashes']) && is_array(self::$script_lookup['hashes'])) {
        return self::$script_lookup['hashes'];
    }

    return [];
}

public static function resolve_script_category($handle, $src) {
    $categories = self::categories();
    self::ensure_script_lookup($categories);

    $lookup = is_array(self::$script_lookup) ? self::$script_lookup : [];

    $has_db_data = !empty($lookup['has_db_data']);

    if ($has_db_data) {
        if (isset($lookup['handles'][$handle])) {
            return $lookup['handles'][$handle];
        }

        $matched = self::match_category_by_src($src, $lookup);
        if (!empty($matched)) {
            return $matched;
        }

        return '';
    }

    return isset($lookup['handles'][$handle]) ? $lookup['handles'][$handle] : '';
}

private static function ensure_script_lookup(array $categories) {
    if (self::$script_lookup !== null) {
        return;
    }

    self::$script_lookup = [
        'handles' => [],
        'urls' => [],
        'hashes' => [],
        'has_db_data' => false,
    ];

    if (function_exists('wpccm_get_scripts_from_db')) {
        $db_scripts = wpccm_get_scripts_from_db();

        if (!empty($db_scripts) && is_array($db_scripts)) {
            $lookup = self::build_script_lookup_from_db($db_scripts, $categories);
            if (!empty($lookup['has_db_data'])) {
                self::$script_lookup = $lookup;
                return;
            }
        }
    }

    // Legacy fallback – pull mappings from wp_options
    $opts = self::get_options();
    $map = isset($opts['map']) && is_array($opts['map']) ? $opts['map'] : [];

    foreach ($map as $handle => $category_key) {
        if (!isset($categories[$category_key])) {
            unset($map[$handle]);
        }
    }

    self::$script_lookup['handles'] = $map;
}

private static function build_script_lookup_from_db(array $scripts, array $categories) {
    $lookup = [
        'handles' => [],
        'urls' => [],
        'hashes' => [],
        'has_db_data' => false,
    ];

    foreach ($scripts as $script) {
        if (empty($script['category'])) {
            continue;
        }

        $category_key = sanitize_key((string) $script['category']);

        if (!isset($categories[$category_key])) {
            continue;
        }

        $lookup['has_db_data'] = true;

        if (!empty($script['script_handle'])) {
            $lookup['handles'][$script['script_handle']] = $category_key;
        }

        if (!empty($script['handle'])) {
            $lookup['handles'][$script['handle']] = $category_key;
        }

        if (!empty($script['script_url'])) {
            $normalized_url = self::normalize_script_url($script['script_url']);

            if ($normalized_url) {
                $lookup['urls'][$normalized_url] = $category_key;

                // Domain-level matches are intentionally avoided to prevent over-blocking
            }

            // Capture inline hashes embedded in the URL placeholder (inline_<hash>)
            if (strpos($script['script_url'], 'inline_') === 0) {
                $inline_hash = substr($script['script_url'], 7);

                if (!empty($inline_hash)) {
                    $lookup['hashes'][$inline_hash] = $category_key;
                }
            }
        }

        if (!empty($script['script_hash'])) {
            $lookup['hashes'][$script['script_hash']] = $category_key;
        }
    }

    if (!empty($lookup['urls']) && empty($lookup['handles'])) {
        global $wp_scripts;

        if ($wp_scripts && isset($wp_scripts->registered) && !empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                $src = isset($script->src) ? $script->src : '';

                if (empty($src)) {
                    continue;
                }

                $normalized_src = self::normalize_script_url($src);

                if (!$normalized_src) {
                    continue;
                }

                if (isset($lookup['urls'][$normalized_src])) {
                    $lookup['handles'][$handle] = $lookup['urls'][$normalized_src];
                }
            }
        }
    }

    return $lookup;
}

private static function match_category_by_src($src, array $lookup) {
    if (empty($src)) {
        return '';
    }

    $normalized_src = self::normalize_script_url($src);

    if (!$normalized_src) {
        return '';
    }

    if (isset($lookup['urls'][$normalized_src])) {
        return $lookup['urls'][$normalized_src];
    }

    return '';
}

private static function normalize_script_url($url) {
    if (empty($url)) {
        return '';
    }

    $url = trim($url);

    if (strpos($url, '//') === 0) {
        $url = (is_ssl() ? 'https:' : 'http:') . $url;
    } elseif (!preg_match('#^https?://#i', $url)) {
        $url = '/' . ltrim($url, '/');
        $url = home_url($url);
    }

    return $url;
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

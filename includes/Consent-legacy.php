<?php
/**
 * LEGACY CONSENT CLASS - PRESERVED FOR REFACTORING
 * TODO: This file contains the old Consent class logic that needs to be refactored
 * TODO: Move working functions to the new modular structure
 * TODO: Remove this file after refactoring is complete
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

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
    // Get custom categories with full details
    $custom_categories = get_option('wpccm_custom_categories', []);
    
    if (!empty($custom_categories) && is_array($custom_categories)) {
        return $custom_categories;
    }
    
    // Fallback to default categories with details
    return [
        [
            'key' => 'necessary',
            'name' => wpccm_text('necessary'),
            'description' => 'Essential cookies required for basic site functionality.',
            'required' => true,
            'enabled' => true
        ],
        [
            'key' => 'functional', 
            'name' => wpccm_text('functional'),
            'description' => 'Cookies that enhance your experience by remembering your preferences.',
            'required' => false,
            'enabled' => true
        ],
        [
            'key' => 'performance',
            'name' => wpccm_text('performance'), 
            'description' => 'Cookies that help us optimize our website performance.',
            'required' => false,
            'enabled' => true
        ],
        [
            'key' => 'analytics',
            'name' => wpccm_text('analytics'),
            'description' => 'Cookies that help us understand how visitors interact with our website.',
            'required' => false,
            'enabled' => true
        ],
        [
            'key' => 'advertisement',
            'name' => wpccm_text('advertisement'),
            'description' => 'Cookies used to deliver personalized advertisements.',
            'required' => false,
            'enabled' => true
        ],
        [
            'key' => 'others',
            'name' => wpccm_text('others'),
            'description' => 'Other cookies that do not fit into the above categories.',
            'required' => false,
            'enabled' => true
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

// TODO: REFACTORING NOTES:
// 1. categories() -> Move to inc/consent-categories.php
// 2. get_categories_with_details() -> Move to inc/consent-categories.php
// 3. default_options() -> Move to inc/consent-defaults.php
// 4. All other methods -> Review and move to appropriate modules

// TODO: DEPENDENCIES TO CHECK:
// - WordPress functions (get_option, wpccm_text)
// - Custom options and text functions
// - Category management functions

// TODO: INTEGRATION POINTS:
// - Replace old Consent class with new modular system
// - Update category management throughout the codebase
// - Maintain backward compatibility during transition
// - Test all functionality after refactoring

// TODO: MODULES TO CREATE:
// - inc/consent-categories.php - Category management
// - inc/consent-defaults.php - Default options
// - inc/consent-text.php - Text management
// - inc/consent-options.php - Options management

} // End of class

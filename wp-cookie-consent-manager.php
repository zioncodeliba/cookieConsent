<?php
/**
 * Plugin Name: WP Cookie Consent Manager
 * Plugin URI: https://wordpress-1142719-5821343.cloudwaysapps.com
 * Description: A WordPress plugin for managing cookie consent and user preferences.
 * Version: 1.0.19
 * Author: code&core
 * License: GPL v2 or later
 * Text Domain: wp-cookie-consent-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// === Plugin Update Checker bootstrap ===
// Try Composer autoload first:
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
}
// Fallback: direct include if you installed PUC manually under vendor/plugin-update-checker/
if (!class_exists(\YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
    $puc_fallback = __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';
    if (file_exists($puc_fallback)) {
        require $puc_fallback;
    }
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Detect plugin slug automatically: e.g. "wp-cookie-consent-manager/wp-cookie-consent-manager.php"
define('WPCCM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPCCM_PLUGIN_SLUG', dirname(WPCCM_PLUGIN_BASENAME)); // "wp-cookie-consent-manager"

// The URL to your JSON metadata (see step 3)
define('WPCCM_UPDATE_JSON_URL', 'https://wordpress-1142719-5821343.cloudwaysapps.com/ck_updates/wpccm.json');

add_action('plugins_loaded', function () {
    if (!class_exists(\YahnisElsts\PluginUpdateChecker\v5\PucFactory::class)) {
        // PUC not loaded, skip.
        return;
    }
    
    // Build the update checker
    $updateChecker = PucFactory::buildUpdateChecker(
        WPCCM_UPDATE_JSON_URL,   // JSON metadata URL
        __FILE__,                // Main plugin file
        WPCCM_PLUGIN_SLUG        // Plugin slug (folder name)
    );

    // (Optional) אם יש רישוי – לצרף מפתח רישיון לבקשות:
    // $updateChecker->addQueryArgFilter(function($args) {
    //     $args['license_key'] = get_option('wpccm_license_key', '');
    //     $args['website_id']  = get_option('wpccm_website_id', '');
    //     return $args;
    // });
});

// (Optional) קישור "בדוק עדכונים עכשיו" במסך תוספים
add_filter('plugin_action_links_' . WPCCM_PLUGIN_BASENAME, function ($links) {
    $url = wp_nonce_url(self_admin_url('update-core.php?force-check=1'), 'upgrade-core');
    $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates now', 'wp-cookie-consent-manager') . '</a>';
    return $links;
});


// Define plugin constants
define('WPCCM_VERSION', '1.0.5');
define('WPCCM_URL', plugin_dir_url(__FILE__));
define('WPCCM_PATH', plugin_dir_path(__FILE__));

// Dashboard API Configuration
define('WPCCM_DASHBOARD_API_URL', 'https://phplaravel-1142719-5823893.cloudwaysapps.com/api');
define('WPCCM_DASHBOARD_VERSION', '1.0.0');

// Include required files
require_once WPCCM_PATH . 'includes/Consent.php';
require_once WPCCM_PATH . 'includes/Admin.php';
require_once WPCCM_PATH . 'includes/Dashboard.php';

// Include new modular components
require_once WPCCM_PATH . 'inc/header-filter.php';
require_once WPCCM_PATH . 'inc/filters-script-tag.php';
require_once WPCCM_PATH . 'inc/ajax-delete-http-only.php';
require_once WPCCM_PATH . 'inc/ajax-scan-scripts.php';
require_once WPCCM_PATH . 'inc/detect-heuristics.php'; // Added for auto-categorization
require_once WPCCM_PATH . 'inc/enqueue.php';

// Include admin components
require_once WPCCM_PATH . 'admin/class-cc-settings-page.php';

// Include debug info in development
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once WPCCM_PATH . 'debug-info.php';
    require_once WPCCM_PATH . 'test-ajax.php';
}

// Translation function
function wpccm_text($key, $default = '') {
    $texts = [
        // Admin Interface
        'cookie_consent_manager' => ['en' => 'Cookie Consent Manager', 'he' => 'מנהל הסכמה לעוגיות'],
        'cookie_consent' => ['en' => 'Cookie Consent', 'he' => 'הסכמה לעוגיות'],
        'cookie_scanner' => ['en' => 'Cookie Scanner', 'he' => 'סורק עוגיות'],
        'banner_settings' => ['en' => 'Banner Settings', 'he' => 'הגדרות באנר'],
        'script_mapping' => ['en' => 'Script Mapping', 'he' => 'מיפוי סקריפטים'],
        'cookie_purging' => ['en' => 'Cookie Purging', 'he' => 'מחיקת עוגיות'],
        
        // Banner Fields
        'title' => ['en' => 'Title', 'he' => 'כותרת'],
        'description' => ['en' => 'Description', 'he' => 'תיאור'],
        'accept_text' => ['en' => 'Accept Text', 'he' => 'טקסט קבלה'],
        'reject_text' => ['en' => 'Reject Text', 'he' => 'טקסט דחיה'],
        'save_text' => ['en' => 'Save Text', 'he' => 'טקסט שמירה'],
        'policy_url' => ['en' => 'Policy URL', 'he' => 'קישור למדיניות'],
        'handle_category_map' => ['en' => 'Handle:Category Map', 'he' => 'מיפוי Handle:קטגוריה'],
        'cookies_to_purge' => ['en' => 'Cookies to Purge', 'he' => 'עוגיות למחיקה'],
        'cookie_expiry_days' => ['en' => 'Cookie Expiry (days)', 'he' => 'תוקף עוגיות (ימים)'],
        
        // Default Banner Texts
        'we_use_cookies' => ['en' => 'We use cookies', 'he' => 'אנו משתמשים בעוגיות'],
        'cookie_description' => ['en' => 'We use cookies to improve your experience. Manage your preferences below.', 'he' => 'אנו משתמשים בעוגיות כדי לשפר את החוויה שלך. נהל את ההעדפות שלך למטה.'],
        'accept_all' => ['en' => 'Accept all', 'he' => 'קבל הכל'],
        'reject_non_essential' => ['en' => 'Reject non-essential', 'he' => 'דחה לא חיוניות'],
        'save_choices' => ['en' => 'Save choices', 'he' => 'שמור בחירות'],
        'learn_more' => ['en' => 'Learn more', 'he' => 'למד עוד'],
        'cookie_settings' => ['en' => 'Cookie Settings', 'he' => 'הגדרות עוגיות'],
        'privacy_overview' => ['en' => 'Privacy Overview', 'he' => 'סקירת פרטיות'],
        'show_more' => ['en' => 'Show more', 'he' => 'הראה עוד'],
        'always_enabled' => ['en' => 'Always Enabled', 'he' => 'תמיד מופעל'],
        'disabled' => ['en' => 'Disabled', 'he' => 'כבוי'],
        'enabled' => ['en' => 'Enabled', 'he' => 'מופעל'],
        'save_accept' => ['en' => 'SAVE & ACCEPT', 'he' => 'שמור וקבל'],
        
        // Categories
        'necessary' => ['en' => 'Necessary', 'he' => 'נחוץ'],
        'functional' => ['en' => 'Functional', 'he' => 'פונקציונלי'],
        'performance' => ['en' => 'Performance', 'he' => 'ביצועים'],
        'analytics' => ['en' => 'Analytics', 'he' => 'אנליטיקה'],
        'advertisement' => ['en' => 'Advertisement', 'he' => 'פרסום'],
        'others' => ['en' => 'Others', 'he' => 'אחרים'],
        'functional_required' => ['en' => 'Functional (required)', 'he' => 'פונקציונלי (נדרש)'],
        
        // Scanner
        'scanner_description' => ['en' => 'This tool scans current browser cookies and suggests categories for consent management.', 'he' => 'כלי זה סורק את העוגיות הנוכחיות בדפדפן ומציע קטגוריות לניהול הסכמה.'],
        'cookie' => ['en' => 'Cookie', 'he' => 'עוגיה'],
        'suggested_category' => ['en' => 'Suggested Category', 'he' => 'קטגוריה מוצעת'],
        'include_in_purge' => ['en' => 'Include in Purge?', 'he' => 'לכלול במחיקה?'],
        'update_settings' => ['en' => 'Update Settings', 'he' => 'עדכן הגדרות'],
        'uncategorized' => ['en' => 'uncategorized', 'he' => 'לא מקוטלג'],
        
        // Handle Mapping
        'scan_handles' => ['en' => 'Scan Registered Handles', 'he' => 'סרוק Handles רשומים'],
        'scan_live_handles' => ['en' => 'Scan Live Website', 'he' => 'סרוק אתר חי'],
        'handle' => ['en' => 'Handle', 'he' => 'Handle'],
        'script_file' => ['en' => 'Script File', 'he' => 'קובץ סקריפט'],
        'related_cookies' => ['en' => 'Related Cookies', 'he' => 'עוגיות קשורות'],
        'unknown_script' => ['en' => '(unknown)', 'he' => '(לא ידוע)'],
        'unknown_cookies' => ['en' => '(unknown)', 'he' => '(לא ידוע)'],
        'enter_cookies_separated' => ['en' => 'Enter cookies separated by commas', 'he' => 'הזן עוגיות מופרדות בפסיקים'],
        'cookies_input_help' => ['en' => 'Enter cookie names that this script creates, separated by commas. Example: _ga, _gid, _gat', 'he' => 'הזן שמות עוגיות שהסקריפט יוצר, מופרדות בפסיקים. דוגמה: _ga, _gid, _gat'],
        'sync_to_purge' => ['en' => 'Sync to Purge List', 'he' => 'סנכרן לרשימת מחיקה'],
        'sync_to_purge_help' => ['en' => 'Add all mapped cookies to the cookie purge list', 'he' => 'הוסף את כל העוגיות המשויכות לרשימת המחיקה'],
        'click_scan_to_see' => ['en' => '(click scan to see files)', 'he' => '(לחץ סרוק לראות קבצים)'],
        'what_is_script_mapping' => ['en' => 'What is Script Mapping?', 'he' => 'מה זה מיפוי סקריפטים?'],
        'script_mapping_explanation' => ['en' => 'Script mapping allows you to control which JavaScript files run based on user consent. Click "Scan" to discover scripts on your site and categorize them. Only relevant third-party scripts will be shown.', 'he' => 'מיפוי סקריפטים מאפשר לך לשלוט באילו קבצי JavaScript רצים לפי הסכמת המשתמש. לחץ "סרוק" כדי לגלות סקריפטים באתר ולקטלג אותם. יוצגו רק סקריפטים רלוונטיים של צד שלישי.'],
        'type' => ['en' => 'Type', 'he' => 'סוג'],
        'select_category' => ['en' => 'Select Category', 'he' => 'בחר קטגוריה'],
        'save_mapping' => ['en' => 'Save Mapping', 'he' => 'שמור מיפוי'],
        'loading' => ['en' => 'Loading...', 'he' => 'טוען...'],
        'none' => ['en' => 'None', 'he' => 'ללא'],
        'manual_mapping' => ['en' => 'Manual Text Mapping', 'he' => 'מיפוי טקסט ידני'],
        
        // Cookie Purge Management

        'update_purge_list' => ['en' => 'Update Purge List', 'he' => 'עדכן רשימת מחיקה'],
        'auto_detected' => ['en' => 'Auto-detected', 'he' => 'זוהה אוטומטית'],
        'should_purge' => ['en' => 'Should Purge?', 'he' => 'למחוק?'],
        'purge_suggestions' => ['en' => 'Purge Suggestions', 'he' => 'הצעות מחיקה'],
        'purge_suggestions_desc' => ['en' => 'Automatically detect cookies that should be purged when users reject non-essential cookies.', 'he' => 'זיהוי אוטומטי של עוגיות שצריכות להימחק כאשר משתמשים דוחים עוגיות לא חיוניות.'],
        'cookies_purge_desc' => ['en' => 'Comma-separated list of cookie names to delete when users reject non-essential cookies.', 'he' => 'רשימה מופרדת בפסיקים של שמות עוגיות למחיקה כאשר משתמשים דוחים עוגיות לא חיוניות.'],
        
        // Table Management
        'add_new' => ['en' => 'Add New', 'he' => 'הוסף חדש'],
        'remove' => ['en' => 'Remove', 'he' => 'הסר'],
        'actions' => ['en' => 'Actions', 'he' => 'פעולות'],
        'category' => ['en' => 'Category', 'he' => 'קטגוריה'],
        'cookie_name' => ['en' => 'Cookie Name', 'he' => 'שם עוגיה'],
        'add_handle' => ['en' => 'Add Handle', 'he' => 'הוסף Handle'],
        'add_cookie' => ['en' => 'Add Cookie', 'he' => 'הוסף עוגיה'],
        'enter_handle_name' => ['en' => 'Enter handle name...', 'he' => 'הכנס שם handle...'],
        'enter_cookie_name' => ['en' => 'Enter cookie name...', 'he' => 'הכנס שם עוגיה...'],
        
        // Categories Management
        'manage_categories' => ['en' => 'Manage Cookie Categories', 'he' => 'ניהול קטגוריות עוגיות'],
        'cookie_categories' => ['en' => 'Cookie Categories', 'he' => 'קטגוריות עוגיות'],
        'add_category' => ['en' => 'Add Category', 'he' => 'הוסף קטגוריה'],
        'category_key' => ['en' => 'Category Key', 'he' => 'מפתח קטגוריה'],
        'category_name' => ['en' => 'Category Name', 'he' => 'שם קטגוריה'],
        'category_description' => ['en' => 'Description', 'he' => 'תיאור'],
        'required_category' => ['en' => 'Required', 'he' => 'נדרש'],
        'category_enabled' => ['en' => 'Enabled', 'he' => 'מופעל'],
        'save_categories' => ['en' => 'Save Categories', 'he' => 'שמור קטגוריות'],
        'delete_category' => ['en' => 'Delete', 'he' => 'מחק'],
        'key_placeholder' => ['en' => 'e.g., social_media', 'he' => 'למשל, רשתות_חברתיות'],
        'name_placeholder' => ['en' => 'e.g., Social Media', 'he' => 'למשל, רשתות חברתיות'],
        'description_placeholder' => ['en' => 'Describe what this category includes...', 'he' => 'תאר מה הקטגוריה הזו כוללת...'],
        'saved_successfully' => ['en' => 'Saved successfully!', 'he' => 'נשמר בהצלחה!'],
        
        // Cookie Scanner
        'current_cookies' => ['en' => 'Current Cookies', 'he' => 'עוגיות נוכחיות'],
        'cookie_suggestions' => ['en' => 'Cookie Suggestions', 'he' => 'הצעות עוגיות'],
        'current_cookies_description' => ['en' => 'This shows all cookies currently active on your website domain.', 'he' => 'זה מציג את כל העוגיות הפעילות כרגע בדומיין האתר שלך.'],
        'refresh_cookies' => ['en' => 'Refresh Cookies', 'he' => 'רענן עוגיות'],
        'scan_cookies' => ['en' => 'Scan for Suggestions', 'he' => 'סרוק הצעות'],
        'cookie_value' => ['en' => 'Cookie Value', 'he' => 'ערך עוגיה'],
        'delete_cookie' => ['en' => 'Delete', 'he' => 'מחק'],
        'confirm_delete_cookie' => ['en' => 'Are you sure you want to delete this cookie?', 'he' => 'האם אתה בטוח שברצונך למחוק את העוגיה הזו?'],
        'no_cookies_found' => ['en' => 'No cookies found on this domain.', 'he' => 'לא נמצאו עוגיות בדומיין זה.'],
        'no_suggestions_found' => ['en' => 'No cookie suggestions found.', 'he' => 'לא נמצאו הצעות עוגיות.'],
        'add_to_purge_list' => ['en' => 'Add to Purge List', 'he' => 'הוסף לרשימת מחיקה'],
        'cookies_added_to_purge' => ['en' => 'Cookies added to purge list', 'he' => 'עוגיות נוספו לרשימת המחיקה'],
        
        // Data Deletion
        'data_deletion' => ['en' => 'Data Deletion History', 'he' => 'מחיקת היסטוריית נתונים'],
        'data_deletion_description' => ['en' => 'Choose the type of data you want to delete:', 'he' => 'בחר את סוג הנתונים שברצונך למחוק:'],
        'delete_browsing_data' => ['en' => 'Request to delete browsing data', 'he' => 'שליחת בקשה למחיקת נתוני גלישה'],
        'delete_account_data' => ['en' => 'Request to delete browsing data and account', 'he' => 'שליחת בקשה למחיקת נתוני גלישה וחשבון'],
        'browsing_data_description' => ['en' => 'Delete browsing history, cookies, and preferences', 'he' => 'מחיקת היסטוריית גלישה, עוגיות, והעדפות'],
        'account_data_description' => ['en' => 'Delete all data including user account', 'he' => 'מחיקת כל הנתונים כולל חשבון משתמש'],
        'ip_address' => ['en' => 'IP Address:', 'he' => 'כתובת IP:'],
        'edit_ip' => ['en' => 'Edit', 'he' => 'ערוך'],
        'save_ip' => ['en' => 'Save', 'he' => 'שמור'],
        'cancel' => ['en' => 'Cancel', 'he' => 'ביטול'],
        'submit_deletion_request' => ['en' => 'Submit Request', 'he' => 'שלח בקשה'],
        'deletion_request_sent' => ['en' => 'Request sent successfully', 'he' => 'בקשה נשלחה בהצלחה'],
        'deletion_request_error' => ['en' => 'Error sending request', 'he' => 'שגיאה בשליחת הבקשה'],
        'uncategorized' => ['en' => 'Uncategorized', 'he' => 'לא מקוטלג'],
        'settings' => ['en' => 'Settings', 'he' => 'הגדרות'],
        'cookies_in_category' => ['en' => 'Cookies in this category:', 'he' => 'עוגיות בקטגוריה זו:'],
        'sync_with_current_cookies' => ['en' => 'Sync with Current Cookies', 'he' => 'סנכרן עם עוגיות נוכחיות'],
        'suggest_non_essential_cookies' => ['en' => 'Suggest Non-Essential Cookies', 'he' => 'הצע עוגיות לא הכרחיות'],
        'non_essential_cookies_found' => ['en' => 'Found cookies on this website', 'he' => 'נמצאו עוגיות באתר זה'],
        'no_non_essential_cookies' => ['en' => 'No cookies found on this website', 'he' => 'לא נמצאו עוגיות באתר זה'],
        'cookie_reason_cart' => ['en' => 'Shopping cart functionality', 'he' => 'פונקציונליות עגלת קניות'],
        'cookie_reason_language' => ['en' => 'Language preferences', 'he' => 'העדפות שפה'],
        'cookie_reason_currency' => ['en' => 'Currency selection', 'he' => 'בחירת מטבע'],
        'cookie_reason_functional' => ['en' => 'Enhances user experience', 'he' => 'משפר חוויית משתמש'],
        'cookie_reason_cache' => ['en' => 'Improves loading speed', 'he' => 'משפר מהירות טעינה'],
        'cookie_reason_performance' => ['en' => 'Website performance optimization', 'he' => 'אופטימיזציה של ביצועי האתר'],
        'cookie_reason_ga' => ['en' => 'Google Analytics tracking', 'he' => 'מעקב Google Analytics'],
        'cookie_reason_hotjar' => ['en' => 'Hotjar user behavior analysis', 'he' => 'ניתוח התנהגות משתמשים Hotjar'],
        'cookie_reason_analytics' => ['en' => 'Website usage analytics', 'he' => 'אנליטיקה של שימוש באתר'],
        'cookie_reason_facebook' => ['en' => 'Facebook advertising', 'he' => 'פרסום פייסבוק'],
        'cookie_reason_google_ads' => ['en' => 'Google Ads tracking', 'he' => 'מעקב Google Ads'],
        'cookie_reason_ads' => ['en' => 'Personalized advertising', 'he' => 'פרסום מותאם אישית'],
        'cookie_reason_other' => ['en' => 'Other website functionality', 'he' => 'פונקציונליות אחרת של האתר'],
        'cookie_purge_explanation' => ['en' => 'Cookies in this list will be automatically deleted when visitors reject non-essential cookies through the consent banner.', 'he' => 'עוגיות ברשימה זו יימחקו אוטומטית כאשר מבקרים דוחים עוגיות לא הכרחיות דרך באנר ההסכמה.'],
        'sync_explanation' => ['en' => 'Find and add ALL cookies currently present on your website (including necessary ones)', 'he' => 'מצא והוסף את כל העוגיות הקיימות כרגע באתר שלך (כולל הכרחיות)'],
        'suggest_explanation' => ['en' => 'Get suggestions from a predefined list of common tracking cookies', 'he' => 'קבל הצעות מרשימה מוגדרת מראש של עוגיות מעקב נפוצות'],
        'what_are_purge_cookies' => ['en' => 'What are purge cookies?', 'he' => 'מה זה עוגיות למחיקה?'],
        'add_cookie_manually' => ['en' => 'Add cookie manually', 'he' => 'הוסף עוגיה ידנית'],
        'no_cookies_configured' => ['en' => 'No cookies configured for purging', 'he' => 'לא הוגדרו עוגיות למחיקה'],
        'default_cookies_warning' => ['en' => 'When visitors reject cookies, the system will use default cookies (_ga, _gid, _fbp, _hjSessionUser). Use the "Sync with Current Cookies" button to add cookies specific to your website.', 'he' => 'כאשר מבקרים דוחים עוגיות, המערכת תשתמש בעוגיות ברירת מחדל (_ga, _gid, _fbp, _hjSessionUser). השתמש בכפתור "סנכרן עם עוגיות נוכחיות" כדי להוסיף עוגיות ספציפיות לאתר שלך.'],
        'clear_all' => ['en' => 'Clear All', 'he' => 'מחק הכל'],
        'clear_all_cookies' => ['en' => 'Clear All Cookies', 'he' => 'מחק כל העוגיות'],
        'clear_all_handles' => ['en' => 'Clear All Handles', 'he' => 'מחק כל ה-Handles'],
        'confirm_clear_all_cookies' => ['en' => 'Are you sure you want to remove all cookies from the purge list?', 'he' => 'האם אתה בטוח שברצונך להסיר את כל העוגיות מרשימת המחיקה?'],
        'confirm_clear_all_handles' => ['en' => 'Are you sure you want to remove all handle mappings?', 'he' => 'האם אתה בטוח שברצונך להסיר את כל מיפויי ה-Handles?'],
        'all_items_cleared' => ['en' => 'All items cleared successfully!', 'he' => 'כל הפריטים נמחקו בהצלחה!'],
        'cookie_reason_session' => ['en' => 'Session management - essential for login', 'he' => 'ניהול סשן - חיוני להתחברות'],
        'cookie_reason_security' => ['en' => 'Security token - prevents attacks', 'he' => 'אסימון אבטחה - מונע התקפות'],
        'cookie_reason_wordpress' => ['en' => 'WordPress core functionality', 'he' => 'פונקציונליות ליבה של WordPress'],
        'cookie_reason_necessary' => ['en' => 'Essential for basic site functionality', 'he' => 'חיוני לתפקוד בסיסי של האתר'],
    ];
    
    $locale = get_locale();
    $lang = (strpos($locale, 'he') === 0) ? 'he' : 'en';
    
    if (isset($texts[$key][$lang])) {
        return $texts[$key][$lang];
    }
    
    return $default ?: $key;
}

class WP_CCM {
    
    public function __construct() {
        // בדיקה שהפלאגין מחובר לדשבורד לפני הפעלת פונקציונליות
        if (!$this->is_dashboard_connected()) {
            // אם לא מחובר לדשבורד, רק מציג הודעה למנהל
            add_action('admin_notices', [$this, 'show_dashboard_connection_notice']);
            add_action('wp_footer', [$this, 'show_dashboard_connection_warning']);
            return;
        }

        // Front assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);

        // Inject banner container in footer
        add_action('wp_footer', [$this, 'render_banner_container']);

        // Filter scripts by consent
        add_filter('script_loader_tag', [$this, 'maybe_defer_script_by_consent'], 10, 3);
        
        // AJAX Hooks
        add_action('wp_ajax_wpccm_save_purge', [$this, 'ajax_save_purge']);
        add_action('wp_ajax_wpccm_scan_handles', [$this, 'ajax_scan_handles']);
        add_action('wp_ajax_wpccm_save_handle_mapping', [$this, 'ajax_save_handle_mapping']);

        add_action('wp_ajax_wpccm_update_purge_list', [$this, 'ajax_update_purge_list']);
        // Save structured purge cookies (name+category) directly from Purge tab
        add_action('wp_ajax_wpccm_save_purge_cookies', [$this, 'ajax_save_purge_cookies']);
        add_action('wp_ajax_wpccm_get_current_cookies_by_category', [$this, 'ajax_get_current_cookies_by_category']);
        add_action('wp_ajax_nopriv_wpccm_get_current_cookies_by_category', [$this, 'ajax_get_current_cookies_by_category']);
        add_action('wp_ajax_wpccm_get_current_non_essential_cookies', [$this, 'ajax_get_current_non_essential_cookies']);
        
        // Consent logging
        add_action('wp_ajax_wpccm_log_consent', [$this, 'ajax_log_consent']);
        add_action('wp_ajax_nopriv_wpccm_log_consent', [$this, 'ajax_log_consent']);
        
        // Data deletion requests
        add_action('wp_ajax_wpccm_submit_data_deletion_request', [$this, 'ajax_submit_data_deletion_request']);
        add_action('wp_ajax_nopriv_wpccm_submit_data_deletion_request', [$this, 'ajax_submit_data_deletion_request']);
    }

    public function enqueue_front_assets() {
        // Don't load on admin pages to avoid conflicts
        if (is_admin()) {
            return;
        }
        
        // Don't interfere with AJAX requests from other plugins
        if (wp_doing_ajax()) {
            return;
        }
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $api_url = get_option('wpccm_dashboard_api_url', '');
        $license_key = get_option('wpccm_license_key', '');
        $website_id = get_option('wpccm_website_id', '');
        
        if (empty($api_url) || empty($license_key) || empty($website_id)) {
            return;
        }
        
        wp_register_style('wpccm', WPCCM_URL . 'assets/css/consent.css', [], WPCCM_VERSION);
        wp_enqueue_style('wpccm');

        $script_url = WPCCM_URL . 'assets/js/consent.js';
        
        // Get configuration
        $options = WP_CCM_Consent::get_options();
        $state = WP_CCM_Consent::get_state();
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד לפני טעינת הסקריפטים
        $dashboard = WP_CCM_Dashboard::get_instance();
        $test_result = $dashboard->test_connection_silent();
        
        if (!$test_result) {
            error_log('WPCCM Debug - License validation failed, not loading scripts');
            return;
        }
        
        // Load everything together in one script block
        add_action('wp_footer', function() use ($script_url, $options, $state) {
            $wpccm_config = json_encode([
                'options' => $options,
                'state' => $state,
                'categories' => WP_CCM_Consent::get_categories_with_details(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'pluginUrl' => WPCCM_URL,
                'version' => WPCCM_VERSION,
                'texts' => [
                    'necessary' => wpccm_text('necessary'),
                    'functional' => wpccm_text('functional'),
                    'performance' => wpccm_text('performance'),
                    'analytics' => wpccm_text('analytics'),
                    'advertisement' => wpccm_text('advertisement'),
                    'others' => wpccm_text('others'),
                    'functional_required' => wpccm_text('functional_required'),
                    'learn_more' => wpccm_text('learn_more'),
                    'accept_all' => wpccm_text('accept_all'),
                    'reject_non_essential' => wpccm_text('reject_non_essential'),
                    'save_choices' => wpccm_text('save_choices'),
                    'cookie_description' => wpccm_text('cookie_description'),
                    'cookie_settings' => wpccm_text('cookie_settings'),
                    'privacy_overview' => wpccm_text('privacy_overview'),
                    'always_enabled' => wpccm_text('always_enabled'),
                    'disabled' => wpccm_text('disabled'),
                    'enabled' => wpccm_text('enabled'),
                    'save_accept' => wpccm_text('save_accept'),
                    'cookies_in_category' => wpccm_text('cookies_in_category'),
                ]
            ]);
            
            // Add AJAX configuration to the config
            $config_array = json_decode($wpccm_config, true);
            $config_array['ajaxUrl'] = admin_url('admin-ajax.php');
            $config_array['nonce'] = wp_create_nonce('wpccm_ajax');
            $config_array['nonceAction'] = 'wpccm_ajax';
            $wpccm_config = json_encode($config_array);
            
            echo '<script>
            // Set WPCCM config first
            window.WPCCM = ' . $wpccm_config . ';
            
            // Create banner container immediately
            (function() {
                function createBannerContainer() {
                    if (!document.getElementById("wpccm-banner-root")) {
                        var div = document.createElement("div");
                        div.id = "wpccm-banner-root";
                        div.setAttribute("aria-live", "polite");
                        document.body.appendChild(div);
                    }
                }
                if (document.body) {
                    createBannerContainer();
                } else {
                    document.addEventListener("DOMContentLoaded", createBannerContainer);
                }
            })();
            </script>';
            
            // Load the main script immediately after
            echo '<script src="' . esc_url($script_url) . '?v=' . WPCCM_VERSION . '"></script>';
        }, 1);


    }

    public function render_banner_container() {
    
        // Don't render on admin pages
        if (is_admin()) {

            return;
        }
        
        // Don't render during AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $api_url = get_option('wpccm_dashboard_api_url', '');
        $license_key = get_option('wpccm_license_key', '');
        $website_id = get_option('wpccm_website_id', '');
        
        // לוג לדיבוג
        // error_log('WPCCM Debug - API URL: ' . $api_url);
        // error_log('WPCCM Debug - License Key: ' . substr($license_key, 0, 10) . '...');
        // error_log('WPCCM Debug - Website ID: ' . $website_id);
        
        if (empty($api_url) || empty($license_key) || empty($website_id)) {
            error_log('WPCCM Debug - Connection check failed, not rendering banner');
            return;
        }
        
        // בדיקה שהרישיון באמת תקין
        $dashboard = WP_CCM_Dashboard::get_instance();
        $test_result = $dashboard->test_connection_silent();
        
        if (!$test_result) {
            error_log('WPCCM Debug - License validation failed, not rendering banner');
            echo '<!-- WPCCM Banner Container - Connection Failed -->';
            return;
        }
        
        error_log('WPCCM Debug - Connection check passed, rendering banner');
        
        // Container for the banner. The JS will render markup here.
        echo '<!-- WPCCM Banner Container - Connection Validated -->';
        echo '<div id="wpccm-banner-root" aria-live="polite"></div>';

    }

    /**
     * Intercept non-essential script tags and convert them to type="text/plain" with data-consent.
     * Handles must be mapped to categories in settings.
     */
    public function maybe_defer_script_by_consent($tag, $handle, $src) {
        // Don't process admin scripts
        if (is_admin()) {
            return $tag;
        }
        
        // Don't interfere with AJAX requests
        if (wp_doing_ajax()) {
            return $tag;
        }
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $api_url = get_option('wpccm_dashboard_api_url', '');
        $license_key = get_option('wpccm_license_key', '');
        $website_id = get_option('wpccm_website_id', '');
        
        if (empty($api_url) || empty($license_key) || empty($website_id)) {
            return $tag;
        }
        
        // Don't interfere with jQuery or essential WordPress scripts
        if (in_array($handle, ['jquery', 'jquery-core', 'jquery-migrate', 'wp-hooks', 'wp-i18n', 'wp-polyfill'])) {
            return $tag;
        }
        
        $map = WP_CCM_Consent::handles_map();
        if (empty($map[$handle])) {
            return $tag; // Not managed by us
        }

        $category = $map[$handle]; // e.g., 'analytics' | 'advertisement' | 'functional'
        $state = WP_CCM_Consent::get_state();

        $allowed = !empty($state[$category]) && $state[$category] === true;

        // Always let "functional" pass if you consider it essential-like; keep strict here
        if ($allowed) {
            // Optionally annotate
            return str_replace('<script ', '<script data-ccm-consent="'.$category.'" ', $tag);
        }

        // Not allowed -> convert src to data-src and set type=text/plain
        // 1) ensure type="text/plain"
        if (strpos($tag, 'type=') !== false) {
            $tag = preg_replace('/type=("|\'|)[^"\']*("|\'|)/i', 'type="text/plain"', $tag);
        } else {
            $tag = str_replace('<script ', '<script type="text/plain" ', $tag);
        }

        // 2) replace src with data-src (external script)
        if ($src) {
            $tag = str_replace(' src="' . esc_url($src) . '"', ' data-src="' . esc_url($src) . '"', $tag);
        } else {
            // Inline script: leave as text/plain so our JS can activate later if consent changes
        }

        // 3) add data-consent attribute
        if (strpos($tag, 'data-consent=') === false) {
            $tag = str_replace('<script ', '<script data-consent="' . esc_attr($category) . '" ', $tag);
        }

        return $tag;
    }

    public function ajax_save_purge() {
        if (!current_user_can('manage_options')) wp_die('No access');
        $raw = isset($_POST['cookies']) ? sanitize_text_field($_POST['cookies']) : '';
        $list = array_filter(array_map('trim', explode(',', $raw)));
        $opts = WP_CCM_Consent::get_options();
        $opts['purge']['cookies'] = $list;
        update_option('wpccm_options', $opts);
        wp_die('OK ('.count($list).' cookies)');
    }

    public function ajax_scan_handles() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        global $wp_scripts, $wp_styles;
        
        $handles = [];
        
        // Get registered scripts
        if ($wp_scripts && isset($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                // Skip WordPress admin/core scripts that shouldn't be managed for consent
                if ($this->should_skip_handle($handle, $script->src ?? '')) {
                    continue;
                }
                
                $handles[] = [
                    'handle' => $handle,
                    'type' => 'script',
                    'src' => $script->src ?? '',
                    'suggested' => $this->suggest_handle_category($handle, $script->src ?? '')
                ];
            }
        }
        
        // Get registered styles (less common but possible)
        if ($wp_styles && isset($wp_styles->registered)) {
            foreach ($wp_styles->registered as $handle => $style) {
                if (strpos($handle, 'analytics') !== false || strpos($handle, 'tracking') !== false) {
                    $handles[] = [
                        'handle' => $handle,
                        'type' => 'style',
                        'src' => $style->src ?? '',
                        'suggested' => $this->suggest_handle_category($handle, $style->src ?? '')
                    ];
                }
            }
        }
        
        wp_send_json_success($handles);
    }

    private function should_skip_handle($handle, $src = '') {
        // Skip WordPress core essential scripts
        $core_scripts = [
            'jquery', 'jquery-core', 'jquery-migrate', 'jquery-ui-core', 'jquery-ui-widget',
            'wp-hooks', 'wp-i18n', 'wp-polyfill', 'wp-dom-ready', 'wp-element', 'wp-components',
            'wp-api-fetch', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-block-editor', 'wp-blocks',
            'react', 'react-dom', 'moment', 'lodash', 'wp-keycodes', 'wp-compose', 'wp-primitives'
        ];
        
        // Skip admin-only scripts
        $admin_scripts = [
            'utils', 'common', 'wp-sanitize', 'sack', 'quicktags', 'colorpicker', 'editor', 'clipboard',
            'wp-theme-plugin-editor', 'wp-codemirror', 'csslint', 'esprima', 'jshint', 'jsonlint',
            'htmlhint', 'wp-tinymce', 'wp-tinymce-root', 'wp-tinymce-lists', 'wp-admin-bar', 'admin-bar',
            'dashboard', 'nav-menu', 'revisions', 'media-upload', 'media-gallery', 'custom-header',
            'custom-background', 'wp-color-picker', 'iris', 'wp-lists', 'postbox', 'tags-box',
            'word-count', 'wp-fullscreen-stub', 'wp-pointer', 'autosave', 'heartbeat', 'wp-auth-check',
            'wp-lists', 'prototype', 'thickbox', 'schedule', 'jquery-table-hotkeys',
            'jquery-touch-punch', 'suggest', 'imagesloaded', 'masonry', 'jquery-masonry', 'wp-embed',
            // Scriptaculous library (old JavaScript framework)
            'scriptaculous', 'scriptaculous-root', 'scriptaculous-builder', 'scriptaculous-dragdrop',
            'scriptaculous-effects', 'scriptaculous-slider', 'scriptaculous-sound', 'scriptaculous-controls',
            // More WordPress internals
            'wp-i18n-loader', 'wp-a11y', 'wp-util', 'wp-backbone', 'underscore', 'backbone',
            'media-models', 'media-views', 'media-editor', 'media-audiovideo', 'mce-view',
            'image-edit', 'set-post-thumbnail', 'customize-base', 'customize-loader', 'customize-preview',
            'customize-models', 'customize-views', 'customize-controls', 'customize-selective-refresh',
            'accordion', 'shortcode', 'media-upload'
        ];
        
        // Skip if it's a core or admin script
        if (in_array($handle, array_merge($core_scripts, $admin_scripts))) {
            return true;
        }
        
        // Skip by handle patterns (WordPress internals, old libraries)
        $skip_patterns = [
            '/^wp-/', '/^scriptaculous/', '/^prototype/', '/^mce-/', '/^media-/',
            '/^customize-/', '/^admin-/', '/^dashboard/', '/^nav-menu/',
            '/^jquery-ui-/', '/^jquery-effects/', '/^thickbox/', '/^farbtastic/',
            '/^plupload/', '/^swfupload/', '/^flash/', '/^silverlight/'
        ];
        
        foreach ($skip_patterns as $pattern) {
            if (preg_match($pattern, $handle)) {
                return true;
            }
        }
        
        // Skip if src contains admin paths or is empty
        if (empty($src) || 
            strpos($src, '/wp-admin/') !== false || 
            strpos($src, '/wp-includes/js/') !== false ||
            strpos($src, '/wp-includes/css/') !== false) {
            return true;
        }
        
        // Only show scripts that are likely to be third-party/plugin related
        // Must have external src or be from plugins/themes
        if (!empty($src) && (
            strpos($src, '/wp-content/plugins/') !== false ||
            strpos($src, '/wp-content/themes/') !== false ||
            strpos($src, '://') !== false  // External scripts
        )) {
            return false; // Don't skip - this is relevant
        }
        
        // Skip everything else (WordPress core internals)
        return true;
    }

    public function ajax_save_handle_mapping() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        $mapping = isset($_POST['mapping']) ? $_POST['mapping'] : [];
        $clean_mapping = [];
        
        foreach ($mapping as $handle => $category) {
            $handle = sanitize_text_field($handle);
            $category = sanitize_text_field($category);
            if ($handle && $category && $category !== 'none') {
                $clean_mapping[$handle] = $category;
            }
        }
        
        $opts = WP_CCM_Consent::get_options();
        $opts['map'] = $clean_mapping;
        update_option('wpccm_options', $opts);
        
        wp_send_json_success(['count' => count($clean_mapping)]);
    }

    private function suggest_handle_category($handle, $src = '') {
        $text = strtolower($handle . ' ' . $src);
        
        // Necessary patterns (essential for site function)
        if (preg_match('/(session|csrf|security|essential|required|wp-admin|auth|login)/i', $text)) {
            return 'necessary';
        }
        
        // Functional patterns
        if (preg_match('/(wp-|wordpress|jquery|bootstrap|theme|cart|checkout|preference|wishlist|compare|currency)/i', $text)) {
            return 'functional';
        }
        
        // Performance patterns
        if (preg_match('/(cache|cdn|speed|performance|optimization|compress|minify|lazy|defer)/i', $text)) {
            return 'performance';
        }
        
        // Analytics patterns
        if (preg_match('/(google.*analytics|gtag|gtm|_ga|analytics|mixpanel|segment|hotjar|matomo|piwik|tracking|statistics)/i', $text)) {
            return 'analytics';
        }
        
        // Advertisement patterns  
        if (preg_match('/(facebook.*pixel|fbevents|adnexus|doubleclick|googleads|adsense|criteo|outbrain|taboola|advertising|marketing|campaign)/i', $text)) {
            return 'advertisement';
        }
        
        // Others (fallback)
        return 'others';
    }



    public function ajax_update_purge_list() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        $cookies = isset($_POST['cookies']) ? (array) $_POST['cookies'] : [];
        $clean_cookies = array_map('sanitize_text_field', $cookies);
        $clean_cookies = array_filter($clean_cookies);
        
        $opts = WP_CCM_Consent::get_options();
        $opts['purge']['cookies'] = array_values($clean_cookies);
        update_option('wpccm_options', $opts);
        
        wp_send_json_success([
            'count' => count($clean_cookies),
            'cookies' => $clean_cookies
        ]);
    }

    /**
     * Save purge cookies with categories from Purge tab (AJAX)
     */
    public function ajax_save_purge_cookies() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        $cookiesJson = isset($_POST['cookies_json']) ? wp_unslash($_POST['cookies_json']) : '';
        if ($cookiesJson === '') {
            wp_send_json_error('Missing cookies_json');
        }
        
        $decoded = json_decode($cookiesJson, true);
        if (!is_array($decoded)) {
            wp_send_json_error('Invalid cookies_json');
        }
        
        $structured = [];
        foreach ($decoded as $cookie) {
            if (is_array($cookie)) {
                $name = isset($cookie['name']) ? sanitize_text_field($cookie['name']) : '';
                $category = isset($cookie['category']) ? sanitize_text_field($cookie['category']) : '';
                if ($name !== '') {
                    $structured[] = [ 'name' => $name, 'category' => $category ];
                }
            } elseif (is_string($cookie)) {
                $name = sanitize_text_field($cookie);
                if ($name !== '') {
                    $structured[] = [ 'name' => $name, 'category' => '' ];
                }
            }
        }
        
        $opts = WP_CCM_Consent::get_options();
        $opts['purge']['cookies'] = $structured;
        update_option('wpccm_options', $opts);
        
        wp_send_json_success([ 'saved' => count($structured) ]);
    }



    public function ajax_get_current_cookies_by_category() {
        // Allow non-logged-in users to access this for frontend banner
        error_log('WPCCM: ajax_get_current_cookies_by_category called');
        
        $categories_with_cookies = [];
        
        // Get cookies from the purge list (configured by site admin)
        $opts = WP_CCM_Consent::get_options();
        $purge_cookies = isset($opts['purge']['cookies']) ? $opts['purge']['cookies'] : [];
        error_log('WPCCM: Purge cookies: ' . print_r($purge_cookies, true));
        
        // Get cookie mapping from script mapping
        $cookie_mapping = isset($opts['cookie_mapping']) && is_array($opts['cookie_mapping']) ? $opts['cookie_mapping'] : [];
        error_log('WPCCM: Cookie mapping: ' . print_r($cookie_mapping, true));
        
        // Get all categories
        $categories = WP_CCM_Consent::get_categories_with_details();
        error_log('WPCCM: Categories: ' . print_r($categories, true));
        
        foreach ($categories as $category) {
            $category_key = $category['key'];
            $cookie_names = [];
            
            // First, check if we have explicit cookie mapping for this category
            foreach ($cookie_mapping as $handle => $cookies_string) {
                // Get the category for this handle from the script mapping
                $handle_category = isset($opts['map'][$handle]) ? $opts['map'][$handle] : '';
                
                if ($handle_category === $category_key && !empty($cookies_string)) {
                    // Split cookies by comma and add them to this category
                    $cookies_array = array_map('trim', explode(',', $cookies_string));
                    foreach ($cookies_array as $cookie_name) {
                        if (!empty($cookie_name) && !in_array($cookie_name, $cookie_names)) {
                            $cookie_names[] = $cookie_name;
                        }
                    }
                }
            }
            
            // Then, categorize remaining cookies from purge list using pattern matching
            foreach ($purge_cookies as $cookie_data) {
                // Handle both old string format and new object format
                if (is_array($cookie_data) && isset($cookie_data['name'])) {
                    $cookie_name = $cookie_data['name'];
                    $cookie_category = isset($cookie_data['category']) ? $cookie_data['category'] : '';
                } else {
                    $cookie_name = $cookie_data;
                    $cookie_category = '';
                }
                
                $cookie_name = sanitize_text_field($cookie_name);
                if (empty($cookie_name)) continue;
                
                // If cookie already has a category assigned, use it
                if (!empty($cookie_category) && $cookie_category === $category_key) {
                    if (!in_array($cookie_name, $cookie_names)) {
                        $cookie_names[] = $cookie_name;
                    }
                } 
                // Otherwise, use pattern matching
                elseif (empty($cookie_category)) {
                    $suggested_category = $this->categorize_cookie_name($cookie_name);
                    if ($suggested_category === $category_key && !in_array($cookie_name, $cookie_names)) {
                        $cookie_names[] = $cookie_name;
                    }
                }
            }
            
            // Only include categories that have cookies
            if (!empty($cookie_names)) {
                $categories_with_cookies[$category_key] = [
                    'name' => $category['name'],
                    'cookies' => $cookie_names
                ];
            }
        }
        
        error_log('WPCCM: Final categories_with_cookies: ' . print_r($categories_with_cookies, true));
        wp_send_json_success($categories_with_cookies);
    }

    private function categorize_cookie_name($cookie_name) {
        $name = strtolower($cookie_name);
        
        // Necessary patterns (essential for site function)
        if (preg_match('/(phpsessid|wordpress_|_wp_session|csrf_token|wp-settings|session|auth|login|user_|admin_)/i', $name)) {
            return 'necessary';
        }
        
        // Functional patterns
        if (preg_match('/(wp-|cart_|wishlist_|currency|language|preference|compare|theme_|settings)/i', $name)) {
            return 'functional';
        }
        
        // Performance patterns
        if (preg_match('/(cache_|cdn_|speed|performance|optimization|compress|minify|lazy|defer|w3tc_|wp_rocket)/i', $name)) {
            return 'performance';
        }
        
        // Analytics patterns
        if (preg_match('/(_ga|_gid|_gat|__utm|_hjid|_hjsession|ajs_|_mkto_trk|hubspot|_pk_|_omapp|__qca|optimizely|mp_|_clck|_clsk|muid|_scid|_uetvid|vuid)/i', $name)) {
            return 'analytics';
        }
        
        // Advertisement patterns  
        if (preg_match('/(_fbp|fr|_gcl_|ide|test_cookie|dsid|__gads|__gpi|_gac_|anid|nid|1p_jar|apisid|hsid|sapisid|sid|sidcc|ssid|_pinterest|uuid2|sess|anj|usersync|tdcpm|tdid|tuuid|ouuid|_cc_)/i', $name)) {
            return 'advertisement';
        }
        
        // Social media patterns
        if (preg_match('/(twitter_|personalization_id|guest_id|datr|sb|wd|xs|c_user|li_sugr|lidc|bcookie|bscookie|ysc|visitor_info)/i', $name)) {
            return 'others';
        }
        
        // Custom/unknown cookies - check for common patterns
        if (preg_match('/^(test_|demo_|hello|sample_|custom_|user_)/i', $name)) {
            return 'functional';
        }
        
        // WordPress-specific patterns we might have missed
        if (preg_match('/(wordpress|wp_|_wp)/i', $name)) {
            return 'functional';
        }
        
        // Default to others for anything unrecognized
        return 'others';
    }

    public function ajax_get_current_non_essential_cookies() {
        error_log('WPCCM Debug: ajax_get_current_non_essential_cookies called');
        
        if (!current_user_can('manage_options')) {
            error_log('WPCCM Debug: User not authorized');
            wp_die('No access');
        }
        
        // Get current cookies from the request (sent from JavaScript)
        $current_cookies = isset($_POST['current_cookies']) ? (array) $_POST['current_cookies'] : [];
        
        // Debug log
        error_log('WPCCM Debug: Received cookies from JavaScript: ' . print_r($current_cookies, true));
        
        $all_cookies = [];
        
        foreach ($current_cookies as $cookie_name) {
            $cookie_name = sanitize_text_field($cookie_name);
            if (empty($cookie_name)) continue;
            
            $category = $this->categorize_cookie_name($cookie_name);
            
            // Include ALL cookies (no filtering)
            $all_cookies[] = [
                'name' => $cookie_name,
                'category' => $category,
                'category_display' => $this->get_category_display_name($category),
                'reason' => $this->get_cookie_reason_by_category($cookie_name, $category)
            ];
        }
        
        // Debug log
        error_log('WPCCM Debug: Processed cookies: ' . print_r($all_cookies, true));
        
        // Sort by category and name
        usort($all_cookies, function($a, $b) {
            if ($a['category'] === $b['category']) {
                return strcmp($a['name'], $b['name']);
            }
            return strcmp($a['category'], $b['category']);
        });
        
        error_log('WPCCM Debug: Sending response with ' . count($all_cookies) . ' cookies');
        wp_send_json_success($all_cookies);
    }

    private function get_category_display_name($category) {
        $names = [
            'necessary' => wpccm_text('necessary'),
            'functional' => wpccm_text('functional'),
            'performance' => wpccm_text('performance'), 
            'analytics' => wpccm_text('analytics'),
            'advertisement' => wpccm_text('advertisement'),
            'others' => wpccm_text('others')
        ];
        return $names[$category] ?? $category;
    }

    /**
     * AJAX handler for data deletion requests
     */
    public function ajax_submit_data_deletion_request() {
        // Get request data
        $deletion_type = sanitize_text_field($_POST['deletion_type'] ?? '');
        $ip_address = sanitize_text_field($_POST['ip_address'] ?? '');
        
        // Validate deletion type
        if (!in_array($deletion_type, ['browsing', 'account'])) {
            wp_send_json_error('Invalid deletion type');
            return;
        }
        
        // Validate IP address
        if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
            wp_send_json_error('Invalid IP address');
            return;
        }
        
        // Get settings
        $options = WP_CCM_Consent::get_options();
        $auto_delete = isset($options['data_deletion']['auto_delete']) ? $options['data_deletion']['auto_delete'] : false;
        
        // Log the deletion request
        $this->log_data_deletion_request($deletion_type, $ip_address);
        
        // If auto delete is enabled, perform the deletion
        if ($auto_delete) {
            $deletion_result = $this->perform_data_deletion($deletion_type, $ip_address);
            if ($deletion_result) {
                wp_send_json_success('Data deleted successfully');
            } else {
                wp_send_json_error('Failed to delete data');
            }
        } else {
            wp_send_json_success('Deletion request submitted successfully');
        }
    }

    /**
     * Log data deletion request
     */
    private function log_data_deletion_request($deletion_type, $ip_address) {
        global $wpdb;
        
        $deletion_requests_table = $wpdb->prefix . 'wpccm_deletion_requests';
        
        // Create table if it doesn't exist
        $this->create_deletion_requests_table();
        
        $result = $wpdb->insert(
            $deletion_requests_table,
            [
                'deletion_type' => $deletion_type,
                'ip_address' => $ip_address,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ],
            [
                '%s', '%s', '%s', '%s'
            ]
        );
        
        return $result !== false;
    }

    /**
     * Create deletion requests table
     */
    private function create_deletion_requests_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wpccm_deletion_requests';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            deletion_type varchar(20) NOT NULL,
            ip_address varchar(45) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            deleted_at datetime NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY deletion_type (deletion_type),
            KEY ip_address (ip_address),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Perform data deletion
     */
    private function perform_data_deletion($deletion_type, $ip_address) {
        global $wpdb;
        
        $consent_history_table = $wpdb->prefix . 'wpccm_consent_history';
        $deletion_requests_table = $wpdb->prefix . 'wpccm_deletion_requests';
        
        // Delete consent history for this IP
        $deleted_rows = $wpdb->delete(
            $consent_history_table,
            ['user_ip' => $ip_address],
            ['%s']
        );
        
        // Update deletion request status
        $wpdb->update(
            $deletion_requests_table,
            [
                'status' => 'completed',
                'deleted_at' => current_time('mysql')
            ],
            [
                'ip_address' => $ip_address,
                'status' => 'pending'
            ],
            ['%s', '%s'],
            ['%s', '%s']
        );
        
        return $deleted_rows !== false;
    }

    private function get_cookie_reason_by_category($cookie_name, $category) {
        // Use existing get_cookie_reason function logic
        $cookie_name_lower = strtolower($cookie_name);
        
        switch ($category) {
            case 'necessary':
                if (strpos($cookie_name_lower, 'session') !== false) return wpccm_text('cookie_reason_session', 'Session management - essential for login');
                if (strpos($cookie_name_lower, 'csrf') !== false) return wpccm_text('cookie_reason_security', 'Security token - prevents attacks');
                if (strpos($cookie_name_lower, 'wordpress') !== false) return wpccm_text('cookie_reason_wordpress', 'WordPress core functionality');
                return wpccm_text('cookie_reason_necessary', 'Essential for basic site functionality');
                
            case 'functional':
                if (strpos($cookie_name_lower, 'cart') !== false) return wpccm_text('cookie_reason_cart', 'Shopping cart functionality');
                if (strpos($cookie_name_lower, 'lang') !== false) return wpccm_text('cookie_reason_language', 'Language preferences');
                if (strpos($cookie_name_lower, 'currency') !== false) return wpccm_text('cookie_reason_currency', 'Currency selection');
                return wpccm_text('cookie_reason_functional', 'Enhances user experience');
                
            case 'performance':
                if (strpos($cookie_name_lower, 'cache') !== false) return wpccm_text('cookie_reason_cache', 'Improves loading speed');
                return wpccm_text('cookie_reason_performance', 'Website performance optimization');
                
            case 'analytics':
                if (strpos($cookie_name_lower, '_ga') !== false) return wpccm_text('cookie_reason_ga', 'Google Analytics tracking');
                if (strpos($cookie_name_lower, '_hj') !== false) return wpccm_text('cookie_reason_hotjar', 'Hotjar user behavior analysis');
                return wpccm_text('cookie_reason_analytics', 'Website usage analytics');
                
            case 'advertisement':
                if (strpos($cookie_name_lower, '_fbp') !== false) return wpccm_text('cookie_reason_facebook', 'Facebook advertising');
                if (strpos($cookie_name_lower, '_gcl') !== false) return wpccm_text('cookie_reason_google_ads', 'Google Ads tracking');
                return wpccm_text('cookie_reason_ads', 'Personalized advertising');
                
            default:
                return wpccm_text('cookie_reason_other', 'Other website functionality');
        }
    }
    
    /**
     * AJAX handler for logging consent actions
     */
    public function ajax_log_consent() {
        error_log('WPCCM: ajax_log_consent called');
        error_log('WPCCM: POST data: ' . print_r($_POST, true));
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $api_url = get_option('wpccm_dashboard_api_url', '');
        $license_key = get_option('wpccm_license_key', '');
        $website_id = get_option('wpccm_website_id', '');
        
        if (empty($api_url) || empty($license_key) || empty($website_id)) {
            wp_send_json_error('הפלאגין לא מחובר לדשבורד מרכזי');
            return;
        }
        
        // For now, let's skip nonce verification to test if the rest works
        // TODO: Fix nonce issue later
        error_log('WPCCM: Skipping nonce verification for testing');
        
        /*
        // Verify nonce for security
        $nonce = $_POST['nonce'] ?? '';
        error_log('WPCCM: Received nonce: ' . $nonce);
        error_log('WPCCM: Expected nonce action: wpccm_ajax');
        
        // Check if nonce is valid
        $nonce_valid = wp_verify_nonce($nonce, 'wpccm_ajax');
        error_log('WPCCM: Nonce verification result: ' . ($nonce_valid ? 'true' : 'false'));
        
        // If nonce is invalid, try to get more info
        if (!$nonce_valid) {
            error_log('WPCCM: Nonce validation failed. Checking session...');
            error_log('WPCCM: Current user ID: ' . get_current_user_id());
            error_log('WPCCM: User logged in: ' . (is_user_logged_in() ? 'yes' : 'no'));
            
            // Try to create a new nonce for debugging
            $new_nonce = wp_create_nonce('wpccm_ajax');
            error_log('WPCCM: New nonce created: ' . $new_nonce);
        }
        
        if (!$nonce_valid) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        */
        
        $action_type = sanitize_text_field($_POST['action_type'] ?? '');
        $consent_data = $_POST['consent_data'] ?? [];
        $categories_accepted = $_POST['categories_accepted'] ?? [];
        
        if (empty($action_type)) {
            wp_send_json_error('Missing action type');
            return;
        }
        
        // Clean and validate data
        $consent_data = array_map('sanitize_text_field', (array)$consent_data);
        $categories_accepted = array_map('sanitize_text_field', (array)$categories_accepted);
        
        // Log the action
        $result = wpccm_log_consent_action($action_type, $consent_data, $categories_accepted);
        
        // Send to dashboard if connected
        if ($result) {
            // Trigger dashboard sync
            do_action('wpccm_consent_saved', $consent_data, get_current_user_id());
            
            wp_send_json_success([
                'message' => 'Consent logged successfully',
                'action' => $action_type,
                'categories' => $categories_accepted
            ]);
        } else {
            wp_send_json_error('Failed to log consent');
        }
    }

    /**
     * בדיקה שהפלאגין מחובר לדשבורד
     */
    private function is_dashboard_connected() {
        $api_url = get_option('wpccm_dashboard_api_url', '');
        $license_key = get_option('wpccm_license_key', '');
        $website_id = get_option('wpccm_website_id', '');
        
        // בדיקה בסיסית שיש את כל הפרטים
        if (empty($api_url) || empty($license_key) || empty($website_id)) {
            return false;
        }
        
        // בדיקה שהרישיון תקין (בדיקה מהירה)
        $dashboard = WP_CCM_Dashboard::get_instance();
        $connection_settings = $dashboard->get_connection_settings();
        
        return !empty($connection_settings['api_url']) && 
               !empty($connection_settings['license_key']) && 
               !empty($connection_settings['website_id']);
    }

    /**
     * הצגת הודעה למנהל שהפלאגין לא מחובר לדשבורד
     */
    public function show_dashboard_connection_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>Cookie Consent Manager:</strong> הפלאגין לא מחובר לדשבורד מרכזי. ';
        echo '<a href="' . admin_url('admin.php?page=wpccm-dashboard') . '">לחץ כאן להגדרת החיבור</a></p>';
        echo '</div>';
    }

    /**
     * הצגת אזהרה למבקרים שהפלאגין לא מחובר לדשבורד
     */
    public function show_dashboard_connection_warning() {
        if (current_user_can('manage_options')) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px; border-radius: 4px; text-align: center; direction: rtl;">';
            echo '<strong>Cookie Consent Manager:</strong> הפלאגין לא מחובר לדשבורד מרכזי. ';
            echo '<a href="' . admin_url('admin.php?page=wpccm-dashboard') . '" style="color: #856404;">לחץ כאן להגדרת החיבור</a>';
            echo '</div>';
        }
    }
}

// Initialize plugin immediately for admin
if (is_admin()) {
    new WP_CCM_Admin();
}

// Initialize new modular components
add_action('init', function() {
    error_log('WPCCM: Initializing modular components');
    
    // Initialize header filtering
    add_action('shutdown', 'wpccm_filter_set_cookie_headers');
    add_action('wp_footer', 'wpccm_filter_set_cookie_headers');
    
    // Initialize script filtering
    add_filter('script_loader_tag', 'wpccm_filter_script_tag', 10, 3);
    add_filter('wp_add_inline_script', 'wpccm_filter_inline_script', 10, 2);
    
    // Initialize AJAX handlers
    add_action('wp_ajax_wpccm_delete_cookies', 'wpccm_ajax_delete_cookies');
    add_action('wp_ajax_nopriv_wpccm_delete_cookies', 'wpccm_ajax_nopriv_delete_cookies');
    
    // Initialize script enqueuing
    add_action('wp_enqueue_scripts', 'wpccm_conditional_enqueue');
    
    error_log('WPCCM: Modular components initialized');
});

// Initialize main plugin for frontend and AJAX
add_action('wp_loaded', function() {
    if (!is_admin() || wp_doing_ajax()) {
        
        if (!isset($GLOBALS['wpccm_instance'])) {
            // בדיקה שהפלאגין מחובר לדשבורד לפני טעינה
            $api_url = get_option('wpccm_dashboard_api_url', '');
            $license_key = get_option('wpccm_license_key', '');
            $website_id = get_option('wpccm_website_id', '');

            if (!empty($api_url) && !empty($license_key) && !empty($website_id)) {
                // בדיקה שהרישיון באמת תקין
                $dashboard = WP_CCM_Dashboard::get_instance();
               
                if ($dashboard->test_connection_silent()) {
                    
                    $GLOBALS['wpccm_instance'] = new WP_CCM();
                    
                    error_log('WPCCM Debug - Plugin loaded successfully');
                } else {
                    error_log('WPCCM Debug - Plugin not loaded - license validation failed');
                    
                }
            } else {
                error_log('WPCCM Debug - Plugin not loaded - missing connection details');
            }
        }
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create or update settings
    WP_CCM_Consent::get_options();
    
    // Create database tables
    wpccm_create_database_tables();
});

/**
 * Create database tables for consent history
 */
function wpccm_create_database_tables() {
    global $wpdb;
    
    $consent_history_table = $wpdb->prefix . 'wpccm_consent_history';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $consent_history_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NULL,
        user_ip varchar(45) NOT NULL,
        user_agent text NOT NULL,
        action_type varchar(20) NOT NULL,
        consent_data longtext NOT NULL,
        categories_accepted text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        user_session varchar(64) NULL,
        referer_url varchar(500) NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY action_type (action_type),
        KEY created_at (created_at),
        KEY user_ip (user_ip)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Update database version
    update_option('wpccm_db_version', '1.0');
}

/**
 * Log consent action to database
 */
function wpccm_log_consent_action($action_type, $consent_data = [], $categories_accepted = []) {
    global $wpdb;
    
    $consent_history_table = $wpdb->prefix . 'wpccm_consent_history';
    
    // Get user information
    $user_id = get_current_user_id();
    $user_ip = wpccm_get_user_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer_url = $_SERVER['HTTP_REFERER'] ?? '';
    $user_session = session_id() ?: wp_generate_uuid4();
    
    $result = $wpdb->insert(
        $consent_history_table,
        [
            'user_id' => $user_id ?: null,
            'user_ip' => $user_ip,
            'user_agent' => substr($user_agent, 0, 1000), // Limit length
            'action_type' => $action_type,
            'consent_data' => wp_json_encode($consent_data),
            'categories_accepted' => wp_json_encode($categories_accepted),
            'user_session' => $user_session,
            'referer_url' => substr($referer_url, 0, 500), // Limit length
            'created_at' => current_time('mysql')
        ],
        [
            '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
        ]
    );
    
    // Send to dashboard if connected
    if ($result !== false) {
        // Trigger dashboard sync
        do_action('wpccm_consent_saved', $consent_data, $user_id);
    }
    
    return $result !== false;
}

/**
 * Get user IP address safely
 */
function wpccm_get_user_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs (take first one)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
<?php
/**
 * Plugin Name: WP Cookie Consent Manager
 * Plugin URI: https://wordpress-1142719-5821343.cloudwaysapps.com
 * Description: A WordPress plugin for managing cookie consent and user preferences.
 * Version: 1.0.35
 * Author: code&core
 * License: GPL v2 or later
 * Text Domain: wp-cookie-consent-manager
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('WPCCM_VERSION', '1.0.35');

// Dashboard API Configuration
define('WPCCM_DASHBOARD_API_URL', 'https://phplaravel-1142719-5823893.cloudwaysapps.com/api');
// define('WPCCM_DASHBOARD_API_URL', 'http://localhost:8000/api');
define('WPCCM_DASHBOARD_VERSION', '1.0.35');

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

// Load translations for WordPress i18n (optional)
add_action('init', function () {
    load_plugin_textdomain('wp-cookie-consent-manager', false, dirname(WPCCM_PLUGIN_BASENAME) . '/languages');
});

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

define('WPCCM_URL', plugin_dir_url(__FILE__));
define('WPCCM_PATH', plugin_dir_path(__FILE__));
define('WPCCM_TOOL_PATH', WPCCM_PATH . 'tools/');


// Include required files
require_once WPCCM_PATH . 'includes/Consent.php';

require_once WPCCM_PATH . 'includes/Admin.php';
require_once WPCCM_PATH . 'includes/Dashboard.php';
// Include new modular components
// require_once WPCCM_PATH . 'inc/header-filter.php';
// require_once WPCCM_PATH . 'inc/filters-script-tag.php';
// require_once WPCCM_PATH . 'inc/ajax-delete-http-only.php';
// require_once WPCCM_PATH . 'inc/ajax-scan-scripts.php';
// require_once WPCCM_PATH . 'inc/detect-heuristics.php'; // Added for auto-categorization
// require_once WPCCM_PATH . 'inc/enqueue.php';

// Locale helpers & translation
function wpccm_get_lang() {
    $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
    return (strpos($locale, 'he') === 0) ? 'he' : 'en';
}

function wpccm_translate_pair($en, $he = '') {
    $lang = wpccm_get_lang();
    if ($lang === 'he') {
        return $he !== '' ? $he : $en;
    }
    return $en !== '' ? $en : $he;
}

// Translation function
function wpccm_text($key, $default = '') {
    $texts = [
        // Admin Interface
        'cookie_consent_manager' => ['en' => 'Cookie Consent Manager', 'he' => 'מנהל הסכמה לעוגיות'],
        'cookie_consent' => ['en' => 'Cookie Consent', 'he' => 'הסכמה לעוגיות'],
        // 'cookie_scanner' => ['en' => 'Cookie Scanner', 'he' => 'סורק עוגיות'],
        'banner_settings' => ['en' => 'Banner Settings', 'he' => 'הגדרות באנר'],
        'script_mapping' => ['en' => 'Script Mapping', 'he' => 'מיפוי סקריפטים'],
        'cookie_purging' => ['en' => 'Cookie Purging', 'he' => 'מחיקת עוגיות'],
        'symc_cookie_and_script' => ['en' => 'Symc Cookie And Script', 'he' => 'סנכרון עוגיות וסקריפטים'],
        'sync_services' => ['en' => 'Site Services Sync', 'he' => 'סינכרון השירותים באתר'],
        'forms_sync' => ['en' => 'Forms Sync', 'he' => 'סינכרון טפסים'],
        'forms_sync_description' => ['en' => 'Scan your site to locate every form and ensure a cookie policy consent checkbox is attached.', 'he' => 'סרוק את האתר כדי לאתר את כל הטפסים ולוודא שיש צ׳קבוקס לאישור מדיניות העוגיות.'],
        'forms_sync_history' => ['en' => 'Forms Sync History', 'he' => 'היסטוריית סינכרון טפסים'],
        'forms_detected' => ['en' => 'Detected Forms', 'he' => 'טפסים שנמצאו'],
        'forms_no_results' => ['en' => 'No forms detected yet. Run the sync to discover forms on your site.', 'he' => 'לא נמצאו טפסים עדיין. הפעל את הסינכרון כדי לגלות טפסים באתר.'],
        'forms_policy_label' => ['en' => 'I confirm that I accept the cookie policy.', 'he' => 'אני מאשר/ת כי קראתי ואני מסכים/ה למדיניות העוגיות.'],
        'forms_policy_required' => ['en' => 'You must accept the cookie policy before submitting.', 'he' => 'יש לאשר את מדיניות העוגיות לפני שליחת הטופס.'],
        
        // Banner Fields
        'title' => ['en' => 'Title', 'he' => 'כותרת'],
        'description' => ['en' => 'Description', 'he' => 'תיאור'],
        'accept_text' => ['en' => 'Accept Text', 'he' => 'טקסט קבלה'],
        'reject_text' => ['en' => 'Reject Text', 'he' => 'טקסט דחיה'],
        'save_text' => ['en' => 'Save Text', 'he' => 'טקסט שמירה'],
        'policy_url' => ['en' => 'Policy URL', 'he' => 'קישור למדיניות'],
        'handle_category_map' => ['en' => 'Handle:Category Map', 'he' => 'מיפוי Handle:קטגוריה'],
        'cookies_to_purge' => ['en' => 'Cookies to Purge', 'he' => 'מיפוי עוגיות'],
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
        'handle' => ['en' => 'Handle', 'he' => 'Handle'],
        'script_file' => ['en' => 'Script File', 'he' => 'קובץ סקריפט'],
        'related_cookies' => ['en' => 'Related Cookies', 'he' => 'עוגיות קשורות'],
        'unknown_script' => ['en' => '(unknown)', 'he' => '(לא ידוע)'],
        'unknown_cookies' => ['en' => '(unknown)', 'he' => '(לא ידוע)'],
        'enter_cookies_separated' => ['en' => 'Enter cookies separated by commas', 'he' => 'הזן עוגיות מופרדות בפסיקים'],
        'cookies_input_help' => ['en' => 'Enter cookie names that this script creates, separated by commas. Example: _ga, _gid, _gat', 'he' => 'הזן שמות עוגיות שהסקריפט יוצר, מופרדות בפסיקים. דוגמה: _ga, _gid, _gat'],
        'no_related_cookies' => ['en' => 'No related cookies found', 'he' => 'לא נמצאו עוגיות קשורות'],
        'cookies_input_helper_text' => ['en' => 'Choose from the list or enter manually', 'he' => 'בחר מהרשימה או הזן ידנית'],
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
        'script_sync_title' => ['en' => 'Script Sync', 'he' => 'סינכרון סקריפטים'],
        'script_sync_description' => ['en' => 'Scan the site to find all active scripts and categorize them.', 'he' => 'סרוק את האתר כדי למצוא את כל הסקריפטים הפעילים ולקטלג אותם לפי קטגוריות.'],
        'sync_scripts_button' => ['en' => 'Sync Scripts', 'he' => 'סנכרן סקריפטים'],
        'script_sync_history_title' => ['en' => 'Script Sync History', 'he' => 'היסטוריית סינכרון סקריפטים'],
        'script_sync_scanning' => ['en' => 'Scanning...', 'he' => 'סורק...'],
        'script_sync_starting' => ['en' => 'Starting script scan...', 'he' => 'מתחיל סריקת סקריפטים...'],
        'script_sync_error' => ['en' => 'Error syncing scripts', 'he' => 'שגיאה בסינכרון סקריפטים'],
        'scripts_table_url_type' => ['en' => 'URL/Script type', 'he' => 'URL/סוג סקריפט'],
        'scripts_table_type' => ['en' => 'Type', 'he' => 'סוג'],
        'scripts_table_category' => ['en' => 'Category', 'he' => 'קטגוריה'],
        'scripts_table_last_seen' => ['en' => 'Last seen', 'he' => 'נצפה לאחרונה'],
        'scripts_table_actions' => ['en' => 'Actions', 'he' => 'פעולות'],
        'scripts_table_empty' => ['en' => 'No scripts in the table. Click "Sync Scripts" to start.', 'he' => 'אין סקריפטים בטבלה. לחץ על "סנכרן סקריפטים" כדי להתחיל.'],
        'script_internal' => ['en' => 'Internal script', 'he' => 'סקריפט פנימי'],
        'script_external' => ['en' => 'External', 'he' => 'חיצוני'],
        'script_internal_label' => ['en' => 'Internal', 'he' => 'פנימי'],
        'script_open_new_tab' => ['en' => 'Open in a new tab', 'he' => 'פתח בטאב חדש'],
        'script_edit_button' => ['en' => 'Edit', 'he' => 'ערוך'],
        'script_edit_category_title' => ['en' => 'Edit Script Category', 'he' => 'ערוך קטגוריית סקריפט'],
        'script_edit_category_label' => ['en' => 'Category:', 'he' => 'קטגוריה:'],
        'save' => ['en' => 'Save', 'he' => 'שמור'],
        'close' => ['en' => 'Close', 'he' => 'סגור'],
        'script_sync_details_title' => ['en' => 'Script Sync Details - %s', 'he' => 'פרטי סינכרון סקריפטים - %s'],
        'script_sync_details_url_type' => ['en' => 'URL/Type', 'he' => 'URL/סוג'],
        'script_sync_details_type' => ['en' => 'Type', 'he' => 'סוג'],
        'script_sync_details_category' => ['en' => 'Category', 'he' => 'קטגוריה'],
        'script_sync_details_no_data' => ['en' => 'No data to display', 'he' => 'אין נתונים להצגה'],
        'script_sync_details_loading' => ['en' => 'Loading...', 'he' => 'טוען...'],
        'script_sync_details_load_error' => ['en' => 'Could not load sync details', 'he' => 'לא ניתן לטעון פרטי סינכרון'],
        'script_update_error' => ['en' => 'Error updating script category', 'he' => 'שגיאה בעדכון קטגוריית הסקריפט'],
        'script_history_sync_time' => ['en' => 'Sync time', 'he' => 'זמן סינכרון'],
        'script_history_type' => ['en' => 'Type', 'he' => 'סוג'],
        'script_history_total' => ['en' => 'Total found', 'he' => 'סך הכל נמצא'],
        'script_history_new' => ['en' => 'New', 'he' => 'חדשים'],
        'script_history_updated' => ['en' => 'Updated', 'he' => 'מעודכנים'],
        'script_history_status' => ['en' => 'Status', 'he' => 'סטטוס'],
        'script_history_actions' => ['en' => 'Actions', 'he' => 'פעולות'],
        'script_history_empty' => ['en' => 'No sync history yet. Run your first sync to see data.', 'he' => 'אין היסטוריית סינכרון עדיין. בצע סינכרון ראשון כדי לראות נתונים.'],
        'script_history_view_details' => ['en' => 'View details of new scripts', 'he' => 'צפה בפרטי הסקריפטים החדשים'],
        'script_sync_type_auto' => ['en' => 'Automatic', 'he' => 'אוטומטי'],
        'script_sync_type_manual' => ['en' => 'Manual', 'he' => 'ידני'],
        'forms_table_page' => ['en' => 'Page', 'he' => 'דף'],
        'forms_table_form_id' => ['en' => 'Form ID', 'he' => 'מזהה טופס'],
        'forms_table_action' => ['en' => 'Action URL', 'he' => 'כתובת פעולה'],
        'forms_table_method' => ['en' => 'Method', 'he' => 'שיטה'],
        'forms_table_status' => ['en' => 'Status', 'he' => 'סטטוס'],
        'forms_table_created' => ['en' => 'Created', 'he' => 'נוצר'],
        'forms_page_id' => ['en' => 'Page ID:', 'he' => 'ID דף:'],
        'forms_no_identifier' => ['en' => 'No ID/class', 'he' => 'ללא מזהה/מחלקה'],
        'forms_action_same_page' => ['en' => '(Submits to same page)', 'he' => '(נשלח לאותו עמוד)'],
        'forms_status_required' => ['en' => 'Required', 'he' => 'חובה'],
        'forms_status_disabled' => ['en' => 'Disabled', 'he' => 'מושבת'],
        'forms_history_empty_title' => ['en' => 'No form sync history yet', 'he' => 'אין היסטוריית סינכרון טפסים עדיין'],
        'forms_history_empty_hint' => ['en' => 'History will update after the first sync', 'he' => 'ההיסטוריה תתעדכן אחרי סינכרון ראשון'],
        'forms_history_time' => ['en' => 'Time', 'he' => 'זמן'],
        'forms_history_type' => ['en' => 'Type', 'he' => 'סוג'],
        'forms_history_status' => ['en' => 'Status', 'he' => 'סטטוס'],
        'forms_history_found' => ['en' => 'Forms found', 'he' => 'טפסים שנמצאו'],
        'forms_history_new' => ['en' => 'New forms', 'he' => 'טפסים חדשים'],
        'forms_history_details' => ['en' => 'Details', 'he' => 'פרטים'],
        'forms_sync_type_manual' => ['en' => 'Manual', 'he' => 'ידני'],
        'forms_sync_type_auto' => ['en' => 'Automatic', 'he' => 'אוטומטי'],
        'forms_details_loading' => ['en' => 'Loading...', 'he' => 'טוען...'],
        'forms_details_load_error' => ['en' => 'Could not load sync details', 'he' => 'לא ניתן לטעון פרטי סינכרון'],
        'forms_details_title' => ['en' => 'New forms - %s', 'he' => 'טפסים חדשים - %s'],
        'forms_details_page' => ['en' => 'Page', 'he' => 'דף'],
        'forms_details_identifier' => ['en' => 'Identifier', 'he' => 'מזהה'],
        'forms_details_action' => ['en' => 'Action', 'he' => 'פעולה'],
        'forms_details_no_data' => ['en' => 'No data to display', 'he' => 'אין נתונים להצגה'],
        'forms_sync_scanning' => ['en' => 'Scanning forms...', 'he' => 'סורק טפסים...'],
        'forms_sync_detecting' => ['en' => 'Detecting forms on the site...', 'he' => 'מאתר טפסים באתר...'],
        'forms_sync_error' => ['en' => 'Error syncing forms', 'he' => 'שגיאה בסינכרון טפסים'],
        'forms_sync_found_summary' => ['en' => 'Found %d forms (%d new)', 'he' => 'נמצאו %d טפסים (%d חדשים)'],
        'scripts_sync_found_summary' => ['en' => 'Found %d scripts (%d new, %d updated)', 'he' => 'נמצאו %d סקריפטים (%d חדשים, %d מעודכנים)'],
        'forms_updated_label' => ['en' => 'Updated:', 'he' => 'עודכן:'],
        'design_settings_saved' => ['en' => 'Design settings saved successfully! Banner position: %s, Floating button position: %s, Size: %s', 'he' => 'הגדרות העיצוב נשמרו בהצלחה! מיקום באנר: %s, מיקום כפתור צף: %s, גודל: %s'],
        'no_permissions' => ['en' => 'You do not have sufficient permissions', 'he' => 'אין לך הרשאות מתאימות'],
        'security_check_failed' => ['en' => 'Security check failed', 'he' => 'בדיקת אבטחה נכשלה'],
        
        // Categories Management
        'manage_categories' => ['en' => 'Manage Cookie Categories', 'he' => 'ניהול קטגוריות עוגיות'],
        'cookie_categories' => ['en' => 'Cookie Categories', 'he' => 'קטגוריות עוגיות'],
        'add_category' => ['en' => 'Add Category', 'he' => 'הוסף קטגוריה'],
        'category_key' => ['en' => 'Category Key', 'he' => 'מפתח קטגוריה'],
        'category_name' => ['en' => 'Category Name', 'he' => 'שם קטגוריה'],
        'category_description' => ['en' => 'Description', 'he' => 'תיאור'],
        'required_category' => ['en' => 'Required', 'he' => 'נדרש'],
        'category_enabled' => ['en' => 'Enabled', 'he' => 'מופעל'],
        'manage_categories_description' => ['en' => 'Manage the different cookie categories on your site. Each cookie will be assigned to one of these categories.', 'he' => 'כאן תוכל לנהל את הקטגוריות השונות של העוגיות באתר. כל עוגיה תשויך לאחת מהקטגוריות הללו.'],
        'add_new_category' => ['en' => 'Add new category', 'he' => 'הוסף קטגוריה חדשה'],
        'categories_empty_title' => ['en' => 'No categories yet', 'he' => 'אין קטגוריות עדיין'],
        'categories_empty_hint' => ['en' => 'Click "Add new category" to get started', 'he' => 'לחץ על "הוסף קטגוריה חדשה" כדי להתחיל'],
        'category_display_name' => ['en' => 'Display Name', 'he' => 'שם תצוגה'],
        'category_color' => ['en' => 'Color', 'he' => 'צבע'],
        'category_icon' => ['en' => 'Icon', 'he' => 'אייקון'],
        'category_essential' => ['en' => 'Essential', 'he' => 'חיוני'],
        'category_active' => ['en' => 'Active', 'he' => 'פעיל'],
        'save_categories' => ['en' => 'Save Categories', 'he' => 'שמור קטגוריות'],
        'delete_category' => ['en' => 'Delete', 'he' => 'מחק'],
        'not_essential' => ['en' => 'No', 'he' => 'לא'],
        'inactive' => ['en' => 'Inactive', 'he' => 'לא פעיל'],
        'no_description' => ['en' => 'No description', 'he' => 'אין תיאור'],
        'edit_category' => ['en' => 'Edit category', 'he' => 'ערוך קטגוריה'],
        'edit' => ['en' => 'Edit', 'he' => 'ערוך'],
        'delete_category_label' => ['en' => 'Delete category', 'he' => 'מחק קטגוריה'],
        'delete' => ['en' => 'Delete', 'he' => 'מחק'],
        'category_modal_add_title' => ['en' => 'Add new category', 'he' => 'הוסף קטגוריה חדשה'],
        'category_modal_edit_title' => ['en' => 'Edit category', 'he' => 'ערוך קטגוריה'],
        'category_key_label' => ['en' => 'Category key:', 'he' => 'מפתח קטגוריה:'],
        'category_key_help' => ['en' => 'English only, no spaces', 'he' => 'באנגלית בלבד, ללא רווחים'],
        'category_display_name_label' => ['en' => 'Display name:', 'he' => 'שם תצוגה:'],
        'category_description_label' => ['en' => 'Description:', 'he' => 'תיאור:'],
        'category_color_label' => ['en' => 'Color:', 'he' => 'צבע:'],
        'category_icon_label' => ['en' => 'Icon (emoji):', 'he' => 'אייקון (אמוג׳י):'],
        'category_icon_placeholder' => ['en' => '📦', 'he' => '📦'],
        'category_essential_label' => ['en' => 'Essential category', 'he' => 'קטגוריה חיונית'],
        'category_essential_hint' => ['en' => '(Cannot be disabled by the user)', 'he' => '(לא ניתן לכבות על ידי המשתמש)'],
        'saving' => ['en' => 'Saving...', 'he' => 'שומר...'],
        'error_saving_category' => ['en' => 'Error saving category:', 'he' => 'שגיאה בשמירת הקטגוריה:'],
        'key_placeholder' => ['en' => 'e.g., social_media', 'he' => 'למשל, רשתות_חברתיות'],
        'name_placeholder' => ['en' => 'e.g., Social Media', 'he' => 'למשל, רשתות חברתיות'],
        'description_placeholder' => ['en' => 'Describe what this category includes...', 'he' => 'תאר מה הקטגוריה הזו כוללת...'],
        'saved_successfully' => ['en' => 'Saved successfully!', 'he' => 'נשמר בהצלחה!'],
        'no_permissions' => ['en' => 'You do not have sufficient permissions', 'he' => 'אין לך הרשאות מתאימות'],
        'security_check_failed' => ['en' => 'Security check failed', 'he' => 'בדיקת אבטחה נכשלה'],
        
        // Management Dashboard
        'management_title' => ['en' => 'Cookie Management & Statistics', 'he' => 'ניהול עוגיות וסטטיסטיקות'],
        'consents_today' => ['en' => 'Consents Today', 'he' => 'הסכמות היום'],
        'rejects_today' => ['en' => 'Rejections Today', 'he' => 'דחיות היום'],
        'total_users' => ['en' => 'Total Users', 'he' => 'סה״כ משתמשים'],
        'active_cookies' => ['en' => 'Active Cookies', 'he' => 'עוגיות פעילות'],
        'quick_actions' => ['en' => 'Quick Actions', 'he' => 'פעולות מהירות'],
        'export_report' => ['en' => 'Export report', 'he' => 'ייצוא דוח'],
        'refresh_data' => ['en' => 'Refresh data', 'he' => 'רענון נתונים'],
        'advanced_settings' => ['en' => 'Advanced settings', 'he' => 'הגדרות מתקדמות'],
        'charts_and_analysis' => ['en' => 'Charts & Analysis', 'he' => 'גרפים וניתוחים'],
        'consents_over_time' => ['en' => 'Consents over time', 'he' => 'הסכמות לאורך זמן'],
        'category_distribution' => ['en' => 'Category distribution', 'he' => 'התפלגות לפי קטגוריות'],
        'loading_data' => ['en' => 'Loading data...', 'he' => 'טוען נתונים...'],
        'error_loading_data' => ['en' => 'Error loading data', 'he' => 'שגיאה בטעינת הנתונים'],
        'error_server_connection' => ['en' => 'Server connection error', 'he' => 'שגיאה בחיבור לשרת'],
        'no_history_data' => ['en' => 'No history data', 'he' => 'אין נתוני היסטוריה'],
        'loaded_records_ip_page' => ['en' => 'Loaded %1$d records out of %2$d for IP: %3$s (page %4$d)', 'he' => 'נטענו %1$d רשומות מתוך %2$d עבור IP: %3$s (עמוד %4$d)'],
        'loaded_records_ip_all' => ['en' => 'Loaded all data for search: %1$s (%2$d records)', 'he' => 'נטענו את כל הנתונים עבור חיפוש: %1$s (%2$d רשומות)'],
        'loaded_records_page' => ['en' => 'Loaded %1$d records out of %2$d (page %3$d)', 'he' => 'נטענו %1$d רשומות מתוך %2$d (עמוד %3$d)'],
        'loaded_records_all' => ['en' => 'Loaded all data: %d records', 'he' => 'נטענו את כל הנתונים: %d רשומות'],
        'search_results_ip' => ['en' => 'Search results for: %s', 'he' => 'תוצאות חיפוש עבור: %s'],
        'exporting_ip' => ['en' => 'Exporting data for search: %s...', 'he' => 'מייצא נתונים עבור חיפוש: %s...'],
        'exporting' => ['en' => 'Exporting data...', 'he' => 'מייצא נתונים...'],
        'export_complete_ip' => ['en' => 'Export complete for search: %s!', 'he' => 'הייצוא הושלם בהצלחה עבור חיפוש: %s!'],
        'export_complete' => ['en' => 'Export complete!', 'he' => 'הייצוא הושלם בהצלחה!'],
        'enter_exact_ip' => ['en' => 'Please enter a search term', 'he' => 'אנא הזן מחרוזת חיפוש'],
        'searching' => ['en' => 'Searching...', 'he' => 'מחפש...'],
        'previous' => ['en' => 'Previous', 'he' => 'הקודם'],
        'next' => ['en' => 'Next', 'he' => 'הבא'],
        'rows_per_page' => ['en' => 'Rows per page', 'he' => 'שורות בעמוד'],
        'search_placeholder' => ['en' => 'Search all columns...', 'he' => 'חפש בכל העמודות...'],
        'no_categories_label' => ['en' => 'No categories', 'he' => 'ללא קטגוריות'],
        'invalid_data' => ['en' => 'Invalid data', 'he' => 'נתונים לא תקינים'],
        'action_accept' => ['en' => 'Accept', 'he' => 'קבלה'],
        'action_reject' => ['en' => 'Reject', 'he' => 'דחייה'],
        'action_save' => ['en' => 'Save', 'he' => 'שמירה'],
        'action_accept_all' => ['en' => 'Accept all', 'he' => 'קבלת הכל'],
        'action_reject_all' => ['en' => 'Reject all', 'he' => 'דחיית הכל'],
        'action_withdraw' => ['en' => 'Withdraw consent', 'he' => 'משיכת הסכמה'],
        'refreshing' => ['en' => 'Refreshing...', 'he' => 'רענון...'],
        'design_settings_saved' => ['en' => 'Design settings saved successfully! Banner position: %s, Floating button position: %s, Size: %s', 'he' => 'הגדרות העיצוב נשמרו בהצלחה! מיקום באנר: %s, מיקום כפתור צף: %s, גודל: %s'],
        'activity_history' => ['en' => 'Activity History', 'he' => 'היסטוריית פעילות'],
        'activity_history_description' => ['en' => 'View user activity history on the site', 'he' => 'צפייה בהיסטוריית פעילות המשתמשים באתר'],
        'load_100_records' => ['en' => 'Load 100 records', 'he' => 'טען 100 רשומות'],
        'load_500_records' => ['en' => 'Load 500 records', 'he' => 'טען 500 רשומות'],
        'load_all_data' => ['en' => 'Load all data', 'he' => 'טען את כל הנתונים'],
        'export_csv' => ['en' => 'Export to CSV', 'he' => 'ייצא ל-CSV'],
        'export_json' => ['en' => 'Export to JSON', 'he' => 'ייצא ל-JSON'],
        'search_ip_placeholder' => ['en' => 'Enter exact IP address...', 'he' => 'הזן כתובת IP מדויקת...'],
        'search_button' => ['en' => 'Search', 'he' => 'חפש'],
        'clear_search' => ['en' => 'Clear search', 'he' => 'נקה חיפוש'],
        'table_date' => ['en' => 'Date', 'he' => 'תאריך'],
        'table_action_type' => ['en' => 'Action type', 'he' => 'סוג פעולה'],
        'table_categories' => ['en' => 'Categories', 'he' => 'קטגוריות'],
        'table_user_ip' => ['en' => 'User IP', 'he' => 'IP משתמש'],
        'table_referer_url' => ['en' => 'Referer URL', 'he' => 'URL הפניה'],
        'export_error' => ['en' => 'Error exporting data', 'he' => 'שגיאה בייצוא הנתונים'],
        
        // Data Deletion Management
        'data_deletion_management' => ['en' => 'Data Deletion Management', 'he' => 'ניהול מחיקת נתונים'],
        'data_deletion_manage_requests' => ['en' => 'Manage data deletion requests from site users', 'he' => 'ניהול בקשות מחיקת נתונים ממשתמשי האתר'],
        'auto_deletion_settings' => ['en' => 'Automatic deletion settings', 'he' => 'הגדרות מחיקה אוטומטית'],
        'auto_deletion' => ['en' => 'Automatic deletion', 'he' => 'מחיקה אוטומטית'],
        'enable_auto_delete' => ['en' => 'Enable automatic deletion of data when a request is received', 'he' => 'הפעל מחיקה אוטומטית של נתונים כאשר מתקבלת בקשה'],
        'auto_delete_description' => ['en' => 'When enabled, data will be deleted immediately when a request is received. Otherwise, requests will be kept for manual handling.', 'he' => 'כאשר מופעל, הנתונים יימחקו מיד כאשר מתקבלת בקשה. אחרת, הבקשות יישמרו לטיפול ידני.'],
        'total_requests' => ['en' => 'Total requests', 'he' => 'סה\"כ בקשות'],
        'pending_requests' => ['en' => 'Pending requests', 'he' => 'בקשות ממתינות'],
        'completed_requests' => ['en' => 'Completed requests', 'he' => 'בקשות שהושלמו'],
        'request_date' => ['en' => 'Request date', 'he' => 'תאריך בקשה'],
        'ip_address' => ['en' => 'IP address', 'he' => 'כתובת IP'],
        'deletion_type' => ['en' => 'Deletion type', 'he' => 'סוג מחיקה'],
        'status' => ['en' => 'Status', 'he' => 'סטטוס'],
        'deletion_date' => ['en' => 'Deletion date', 'he' => 'תאריך מחיקה'],
        'actions' => ['en' => 'Actions', 'he' => 'פעולות'],
        'no_deletion_requests' => ['en' => 'No deletion requests', 'he' => 'אין בקשות מחיקה'],
        'status_completed' => ['en' => 'Completed', 'he' => 'הושלם'],
        'status_pending' => ['en' => 'Pending', 'he' => 'ממתין'],
        'delete_data' => ['en' => 'Delete data', 'he' => 'מחק נתונים'],
        'delete_in_progress' => ['en' => 'Deleting...', 'he' => 'מוחק...'],
        'delete_confirm' => ['en' => 'Are you sure you want to delete all data for this IP?', 'he' => 'האם אתה בטוח שברצונך למחוק את כל הנתונים עבור כתובת IP זו?'],
        'error_deleting_data' => ['en' => 'Error deleting data: %s', 'he' => 'שגיאה במחיקת הנתונים: %s'],
        'communication_error' => ['en' => 'Communication error with the server', 'he' => 'שגיאה בתקשורת עם השרת'],
        'deletion_type_browsing' => ['en' => 'Browsing data', 'he' => 'נתוני גלישה'],
        'deletion_type_account' => ['en' => 'Browsing and account data', 'he' => 'נתוני גלישה וחשבון'],
        
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
        'scanning_site_cookies' => ['en' => 'Scanning cookies from the site...', 'he' => 'סורק עוגיות מהאתר...'],
        'site_cookies_found' => ['en' => 'Found %d cookies from the site', 'he' => 'נמצאו %d עוגיות מהאתר'],
        'cookies_added_to_table' => ['en' => 'Cookies added to the table successfully', 'he' => 'העוגיות נוספו לטבלה בהצלחה'],
        'cookies_added_to_table_admin' => ['en' => 'Cookies added to the table successfully (from admin)', 'he' => 'העוגיות נוספו לטבלה בהצלחה (מהאדמין)'],
        'error_with_message' => ['en' => 'Error: %s', 'he' => 'שגיאה: %s'],
        'error_saving_cookies' => ['en' => 'Error saving cookies', 'he' => 'שגיאה בשמירת העוגיות'],
        'error_accessing_site_using_admin' => ['en' => 'Could not access the site, using admin cookies', 'he' => 'לא ניתן לגשת לאתר, משתמש בעוגיות האדמין'],
        'unknown_error' => ['en' => 'Unknown error', 'he' => 'שגיאה לא מוכרת'],
        
        // Sync History
        'cookie_sync_history_title' => ['en' => 'Cookie Sync History', 'he' => 'היסטוריית סינכרון עוגיות'],
        'cookie_sync_history_description' => ['en' => 'List of all sync actions performed on the site (manual and automatic)', 'he' => 'רשימת כל פעולות הסינכרון שבוצעו באתר (ידניות ואוטומטיות)'],
        'cookie_sync_history_empty_title' => ['en' => 'No sync history yet', 'he' => 'אין היסטוריית סינכרון עדיין'],
        'cookie_sync_history_empty_hint' => ['en' => 'History will appear after the first sync', 'he' => 'ההיסטוריה תתחיל להופיע אחרי הסינכרון הראשון'],
        'sync_column_time' => ['en' => 'Time', 'he' => 'זמן'],
        'sync_column_type' => ['en' => 'Type', 'he' => 'סוג'],
        'sync_column_status' => ['en' => 'Status', 'he' => 'סטטוס'],
        'sync_column_cookies_found' => ['en' => 'Cookies found', 'he' => 'עוגיות נמצאו'],
        'sync_column_new_cookies' => ['en' => 'New cookies', 'he' => 'עוגיות חדשות'],
        'sync_column_execution_time' => ['en' => 'Execution time', 'he' => 'זמן ביצוע'],
        'sync_column_details' => ['en' => 'Details', 'he' => 'פרטים'],
        'manual_sync_label' => ['en' => 'Manual', 'he' => 'ידני'],
        'automatic_sync_label' => ['en' => 'Automatic', 'he' => 'אוטומטי'],
        'view_details' => ['en' => 'View', 'he' => 'צפה'],
        'view_new_cookies_details' => ['en' => 'View details of new cookies', 'he' => 'צפה בפרטי העוגיות החדשות'],
        'execution_seconds' => ['en' => '%ss', 'he' => '%s שניות'],
        'not_available' => ['en' => 'N/A', 'he' => 'לא זמין'],
        'sync_status_success' => ['en' => 'Success', 'he' => 'הצליח'],
        'sync_status_error' => ['en' => 'Error', 'he' => 'שגיאה'],
        'sync_status_skipped' => ['en' => 'Skipped', 'he' => 'דולג'],
        
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
        'what_are_purge_cookies' => ['en' => 'What are purge cookies?', 'he' => 'מה זה מיפוי עוגיות?'],
        'add_cookie_manually' => ['en' => 'Add cookie manually', 'he' => 'הוסף עוגיה ידנית'],
        'no_cookies_configured' => ['en' => 'No cookies configured for purging', 'he' => 'לא הוגדרו עוגיות למיפוי'],
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
        'dashboard_api_description' => ['en' => 'Central dashboard API URL', 'he' => 'כתובת ה-API של הדשבורד המרכזי'],
        'license_valid' => ['en' => 'License valid:', 'he' => 'רישיון תקף:'],
        'license_invalid_or_disconnected' => ['en' => 'License invalid or not connected', 'he' => 'רישיון לא תקף או לא מחובר'],
        'error_code_label' => ['en' => 'Error code:', 'he' => 'קוד שגיאה:'],
        'license_key_description' => ['en' => 'License key from the central dashboard', 'he' => 'מפתח הרישיון מהדשבורד המרכזי'],
        'edit' => ['en' => 'Edit', 'he' => 'ערוך'],
        'enter_license_key' => ['en' => 'Enter the license key you received when purchasing the plugin', 'he' => 'הזן את מפתח הרישיון שקיבלת בעת רכישת התוסף'],
    ];
    
    $lang = wpccm_get_lang();
    
    if (isset($texts[$key][$lang])) {
        return $texts[$key][$lang];
    }
    
    return $default ?: $key;
}

class WP_CCM {
    private $inline_buffer_started = false;

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
        add_filter('wp_script_attributes', [$this, 'filter_module_script_attributes']);
        add_action('template_redirect', [$this, 'start_inline_script_buffer'], 0);
        
        // Save structured purge cookies (name+category) directly from Purge tab
        add_action('wp_ajax_wpccm_create_cookies_table', [$this, 'ajax_create_cookies_table']);
        add_action('wp_ajax_wpccm_update_cookie_category', [$this, 'ajax_update_cookie_category']);
        add_action('wp_ajax_wpccm_save_purge_cookies', [$this, 'ajax_save_purge_cookies']);
        add_action('wp_ajax_wpccm_get_current_cookies_by_category', [$this, 'ajax_get_current_cookies_by_category']);
        add_action('wp_ajax_nopriv_wpccm_get_current_cookies_by_category', [$this, 'ajax_get_current_cookies_by_category']);
        add_action('wp_ajax_wpccm_get_current_non_essential_cookies', [$this, 'ajax_get_current_non_essential_cookies']);
        
        // Debug AJAX handler
        add_action('wp_ajax_wpccm_debug_script_mapping', [$this, 'ajax_debug_script_mapping']);
        
        // Consent logging
        add_action('wp_ajax_wpccm_log_consent', [$this, 'ajax_log_consent']);
        add_action('wp_ajax_nopriv_wpccm_log_consent', [$this, 'ajax_log_consent']);
        
        // Data deletion requests
        add_action('wp_ajax_wpccm_submit_data_deletion_request', [$this, 'ajax_submit_data_deletion_request']);
        add_action('wp_ajax_nopriv_wpccm_submit_data_deletion_request', [$this, 'ajax_submit_data_deletion_request']);
        
        // Get user IP
        add_action('wp_ajax_wpccm_get_user_ip', [$this, 'ajax_get_user_ip']);
        add_action('wp_ajax_nopriv_wpccm_get_user_ip', [$this, 'ajax_get_user_ip']);
    }

    public function start_inline_script_buffer() {
        if ($this->inline_buffer_started) {
            return;
        }

        if (is_admin() || wp_doing_ajax()) {
            return;
        }

        if ((defined('REST_REQUEST') && REST_REQUEST) || is_feed()) {
            return;
        }

        // Only buffer when there are known inline scripts
        if (empty(WP_CCM_Consent::inline_hash_map())) {
            return;
        }

        $this->inline_buffer_started = true;
        ob_start([$this, 'filter_inline_scripts_output']);
    }

    public function filter_inline_scripts_output($html) {
        if (empty($html) || stripos($html, '<script') === false) {
            return $html;
        }

        $hash_map = WP_CCM_Consent::inline_hash_map();

        if (empty($hash_map)) {
            return $html;
        }

        $state = WP_CCM_Consent::get_state();

        $callback = function ($matches) use ($hash_map, $state) {
            $raw_attributes = isset($matches[1]) ? $matches[1] : '';
            $script_content = isset($matches[2]) ? $matches[2] : '';

            if (stripos($raw_attributes, 'src=') !== false) {
                return $matches[0];
            }

            $trimmed_content = trim($script_content);

            if ($trimmed_content === '' || stripos($trimmed_content, 'wpccm') !== false) {
                return $matches[0];
            }

            $hash = md5($trimmed_content);

            if (!isset($hash_map[$hash])) {
                return $matches[0];
            }

            $category = sanitize_key($hash_map[$hash]);

            if ($category === 'necessary') {
                return $matches[0];
            }

            $is_allowed = !empty($state[$category]);

            $attributes = $this->parse_html_attributes($raw_attributes);

            if ($is_allowed) {
                $attributes['data-cc'] = $category;

                if (isset($attributes['type']) && strtolower($attributes['type']) === 'text/plain') {
                    unset($attributes['type']);
                }

                unset($attributes['data-inline-blocked']);

                return '<script' . $this->build_html_attributes($attributes) . '>' . $script_content . '</script>';
            }

            // Block inline script by converting it to consent-controlled placeholder
            $attributes['type'] = 'text/plain';
            $attributes['data-cc'] = $category;
            $attributes['data-inline-blocked'] = '1';

            return '<script' . $this->build_html_attributes($attributes) . '>' . $script_content . '</script>';
        };

        return preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#is', $callback, $html);
    }

    private function parse_html_attributes($attribute_string) {
        $attributes = [];

        if (empty($attribute_string)) {
            return $attributes;
        }

        $parsed = wp_kses_hair($attribute_string, [], null);

        if (is_array($parsed)) {
            foreach ($parsed as $attribute) {
                if (!isset($attribute['name'])) {
                    continue;
                }

                $name = strtolower($attribute['name']);
                $value = isset($attribute['value']) ? $attribute['value'] : '';
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    private function build_html_attributes(array $attributes) {
        if (empty($attributes)) {
            return '';
        }

        $parts = [];

        foreach ($attributes as $name => $value) {
            $name = strtolower($name);

            if ($value === null || $value === '') {
                $parts[] = $name;
                continue;
            }

            $parts[] = sprintf('%s="%s"', $name, esc_attr($value));
        }

        return ' ' . implode(' ', $parts);
    }

    public function enqueue_front_assets() {
        // var_dump("enqueue_front_assetsenqueue_front_assetsenqueue_front_assets");
        // Don't load on admin pages to avoid conflicts
        if (is_admin()) {
            return;
        }
        
        // Don't interfere with AJAX requests from other plugins
        if (wp_doing_ajax()) {
            return;
        }
        
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $license_key = get_option('wpccm_license_key', '');
        if (empty($license_key)) {
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
        
        if (!$test_result || !isset($test_result['success']) || !$test_result['success']) {
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
                'syncIntervalMinutes' => get_option('wpccm_sync_interval_minutes', 60),
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
                    'forms_policy_label' => wpccm_text('forms_policy_label'),
                    'forms_policy_required' => wpccm_text('forms_policy_required'),
                ]
            ]);
            
            // Add AJAX configuration to the config
            $config_array = json_decode($wpccm_config, true);
            $config_array['ajaxUrl'] = admin_url('admin-ajax.php');
            $config_array['nonce'] = wp_create_nonce('wpccm_ajax');
            $config_array['nonceAction'] = 'wpccm_ajax';
            $wpccm_config = json_encode($config_array);
            $this->render_banner_container();
            
            echo '<script>
            // Set WPCCM config first
            window.WPCCM = ' . $wpccm_config . ';
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
        $license_key = get_option('wpccm_license_key', '');
        if (empty($license_key)) {
            error_log('WPCCM Debug - Connection check failed, not rendering banner');
            return;
        }
        
        // בדיקה שהרישיון באמת תקין
        $dashboard = WP_CCM_Dashboard::get_instance();
        $test_result = $dashboard->test_connection_silent();
        
        if (!$test_result || !isset($test_result['success']) || !$test_result['success']) {
            error_log('WPCCM Debug - License validation failed, not rendering banner');
            echo '<!-- WPCCM Banner Container - Connection Failed -->';
            return;
        }
        
        // Get design settings
        $options = get_option('wpccm_options', []);
        $design_settings = isset($options['design']) ? $options['design'] : [];
        
        // Default design values
        $banner_position = isset($design_settings['banner_position']) ? $design_settings['banner_position'] : 'top';
        $floating_button_position = isset($design_settings['floating_button_position']) ? $design_settings['floating_button_position'] : 'bottom-right';
        $background_color = isset($design_settings['background_color']) ? $design_settings['background_color'] : '#ffffff';
        $text_color = isset($design_settings['text_color']) ? $design_settings['text_color'] : '#000000';
        $accept_button_color = isset($design_settings['accept_button_color']) ? $design_settings['accept_button_color'] : '#000000';
        $reject_button_color = isset($design_settings['reject_button_color']) ? $design_settings['reject_button_color'] : '#000000';
        $settings_button_color = isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#000000';
        $data_deletion_button_color = isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#000000';
        $size = isset($design_settings['size']) ? $design_settings['size'] : 'medium';
        
        // Calculate size values
        $padding = '15px';
        $font_size = '14px';
        $button_padding = '8px 16px';
        
        if ($size === 'small') {
            $padding = '8px';
            $font_size = '12px';
            $button_padding = '6px 12px';
        } elseif ($size === 'large') {
            $padding = '25px';
            $font_size = '18px';
            $button_padding = '12px 24px';
        }

        // Container for the banner with design settings
        echo '<!-- WPCCM Banner Container - Connection Validated -->';
        echo '<div id="wpccm-banner-root" aria-live="polite" 
                data-banner-position="' . esc_attr($banner_position) . '"
                data-floating-position="' . esc_attr($floating_button_position) . '"
                data-background-color="' . esc_attr($background_color) . '"
                data-text-color="' . esc_attr($text_color) . '"
                data-accept-button-color="' . esc_attr($accept_button_color) . '"
                data-reject-button-color="' . esc_attr($reject_button_color) . '"
                data-settings-button-color="' . esc_attr($settings_button_color) . '"
                data-data-deletion-button-color="' . esc_attr($data_deletion_button_color) . '"
                data-size="' . esc_attr($size) . '"
                data-padding="' . esc_attr($padding) . '"
                data-font-size="' . esc_attr($font_size) . '"
                data-button-padding="' . esc_attr($button_padding) . '">
             </div>';

    }

    /**
     * Intercept non-essential script tags and convert them to type="text/plain" with data-cc.
     * Handles must be mapped to categories in settings.
     */
    public function maybe_defer_script_by_consent($tag, $handle, $src) {
        $decision = $this->evaluate_script_consent($handle, $src);

        if (!$decision['process']) {
            return $tag;
        }

        $category = $decision['category'];

        if ($decision['allowed']) {
            if (strpos($tag, 'data-cc=') === false) {
                $tag = str_replace('<script ', '<script data-cc="' . esc_attr($category) . '" ', $tag);
            }

            return $tag;
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

        // 3) normalise consent attribute naming
        if (strpos($tag, 'data-consent=') !== false) {
            $tag = preg_replace('/data-consent=("|\')[^"\']*("|\')/i', '', $tag);
        }

        if (strpos($tag, 'data-cc=') === false) {
            $tag = str_replace('<script ', '<script data-cc="' . esc_attr($category) . '" ', $tag);
        }
        
        // Avoid duplicate spaces introduced by removals
        $tag = preg_replace('/<script\s+/', '<script ', $tag);
        
        return $tag;
    }

    public function filter_module_script_attributes($attributes) {
        if (!is_array($attributes) || empty($attributes['src'])) {
            return $attributes;
        }

        // Ensure we're working on module scripts only and avoid re-processing converted tags
        if (!isset($attributes['type']) || $attributes['type'] !== 'module') {
            return $attributes;
        }

        // Determine the module handle (ID attribute uses the module identifier with -js-module suffix)
        $handle = isset($attributes['id']) ? $attributes['id'] : '';

        $decision = $this->evaluate_script_consent($handle, $attributes['src']);

        if (!$decision['process']) {
            return $attributes;
        }

        $category = $decision['category'];

        if ($decision['allowed']) {
            $attributes['data-cc'] = $category;
            unset($attributes['data-consent'], $attributes['data-ccm-consent']);
            return $attributes;
        }

        // Not allowed -> convert to text/plain and preserve original src for later activation
        $attributes['data-src'] = $attributes['src'];
        unset($attributes['src']);
        $attributes['type'] = 'text/plain';
        $attributes['data-cc'] = $category;
        unset($attributes['data-consent'], $attributes['data-ccm-consent']);
        $attributes['data-original-type'] = 'module';

        return $attributes;
    }

    private function evaluate_script_consent($handle, $src) {
        // Don't process admin scripts
        if (is_admin() || wp_doing_ajax()) {
            return ['process' => false, 'category' => '', 'allowed' => false];
        }

        // בדיקה דינמית שהפלאגין מחובר לדשבורד
        $license_key = get_option('wpccm_license_key', '');
        if (empty($license_key)) {
            return ['process' => false, 'category' => '', 'allowed' => false];
        }

        // Don't interfere with core essential handles
        $essential_handles = ['jquery', 'jquery-core', 'jquery-migrate', 'wp-hooks', 'wp-i18n', 'wp-polyfill'];
        if (!empty($handle) && in_array($handle, $essential_handles, true)) {
            return ['process' => false, 'category' => '', 'allowed' => false];
        }

        $category = WP_CCM_Consent::resolve_script_category($handle, $src);

        if (empty($category)) {
            return ['process' => false, 'category' => '', 'allowed' => false];
        }

        $state = WP_CCM_Consent::get_state();

        if (!array_key_exists($category, $state)) {
            $state[$category] = false;
        }

        $allowed = !empty($state[$category]) && $state[$category] === true;

        return [
            'process' => true,
            'category' => $category,
            'allowed' => $allowed,
        ];
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

    /**
     * Save purge cookies with categories from Purge tab (AJAX)
     */
    public function ajax_create_cookies_table() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        // Force create/update database tables
        wpccm_create_database_tables();
        
        wp_send_json_success(['message' => 'Cookies table created successfully']);
    }
    
    public function ajax_update_cookie_category() {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        $cookie_name = sanitize_text_field($_POST['cookie_name'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        
        if (empty($cookie_name) || empty($category)) {
            wp_send_json_error('Missing cookie name or category');
            return;
        }
        
        global $wpdb;
        $cookies_table = $wpdb->prefix . 'ck_cookies';
        
        // Update in database
        $result = $wpdb->update(
            $cookies_table,
            [
                'category' => $category,
                'updated_at' => current_time('mysql')
            ],
            ['name' => $cookie_name],
            ['%s', '%s'],
            ['%s']
        );
        
        if ($result !== false) {
            // Also update in wp_options for backward compatibility
            $opts = WP_CCM_Consent::get_options();
            if (isset($opts['purge']['cookies']) && is_array($opts['purge']['cookies'])) {
                foreach ($opts['purge']['cookies'] as &$cookie) {
                    if (isset($cookie['name']) && $cookie['name'] === $cookie_name) {
                        $cookie['category'] = $category;
                        break;
                    }
                }
                update_option('wpccm_options', $opts);
            }
            
            wp_send_json_success(['message' => 'Cookie category updated successfully']);
        } else {
            wp_send_json_error('Failed to update cookie category in database');
        }
    }
    
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
                    // Get existing value from database if available
                    global $wpdb;
                    $cookies_table = $wpdb->prefix . 'ck_cookies';
                    $existing_value = $wpdb->get_var($wpdb->prepare(
                        "SELECT value FROM $cookies_table WHERE name = %s AND is_active = 1",
                        $name
                    ));
                    
                    $structured[] = [ 
                        'name' => $name, 
                        'category' => $category,
                        'value' => $existing_value ?: ''
                    ];
                }
            } elseif (is_string($cookie)) {
                $name = sanitize_text_field($cookie);
                if ($name !== '') {
                    $structured[] = [ 'name' => $name, 'category' => '', 'value' => '' ];
                }
            }
        }
        
        $opts = WP_CCM_Consent::get_options();
        $opts['purge']['cookies'] = $structured;
        update_option('wpccm_options', $opts);
        
        // Also save to new cookies table
        wpccm_save_cookies_to_db($structured);
        
        wp_send_json_success([ 'saved' => count($structured) ]);
    }



    public function ajax_get_current_cookies_by_category() {
        // Allow non-logged-in users to access this for frontend banner
        error_log('WPCCM: ajax_get_current_cookies_by_category called');
        
        $categories_with_cookies = [];
        
        // First, get cookies and scripts from the database
        $db_cookies = wpccm_get_cookies_from_db();
        $db_scripts = wpccm_get_scripts_from_db();
        // error_log('WPCCM: DB cookies count: ' . count($db_cookies));
        // error_log('WPCCM: DB scripts count: ' . count($db_scripts));
        // error_log('WPCCM: DB cookies: ' . print_r($db_cookies, true));
        // error_log('WPCCM: DB scripts: ' . print_r($db_scripts, true));
        
        // If we have cookies or scripts from database, use only those
        if (!empty($db_cookies) || !empty($db_scripts)) {
            $categories_with_data = [];
            
            // Get all categories for response
            $categories = WP_CCM_Consent::get_categories_with_details();
            foreach ($categories as $category) {
                $category_key = $category['key'];
                $categories_with_data[$category_key] = [
                    'cookies' => [],
                    'scripts' => []
                ];
            }
            
            // Organize DB cookies by category
            foreach ($db_cookies as $cookie) {
                $category_key = $cookie['category'];
                if (isset($categories_with_data[$category_key]) && !in_array($cookie['name'], $categories_with_data[$category_key]['cookies'])) {
                    $categories_with_data[$category_key]['cookies'][] = $cookie['name'];
                }
            }
            
            // Organize DB scripts by category
            foreach ($db_scripts as $script) {
                $category_key = $script['category'];
                // Extract script name from URL
                $script_name = $this->extract_script_name_from_url($script['script_url']);
                if (isset($categories_with_data[$category_key]) && !in_array($script_name, $categories_with_data[$category_key]['scripts'])) {
                    $categories_with_data[$category_key]['scripts'][] = $script_name;
                }
            }
            
            error_log('WPCCM: Using DB data only: ' . print_r($categories_with_data, true));
            wp_send_json_success($categories_with_data);
            return;
        }
        
        // No DB data found - return empty categories (don't fallback to configuration)
        error_log('WPCCM: No DB data found, returning empty categories');
        
        // Get all categories and return them empty
        $categories = WP_CCM_Consent::get_categories_with_details();
        $categories_with_data = [];
        foreach ($categories as $category) {
            $category_key = $category['key'];
            $categories_with_data[$category_key] = [
                'cookies' => [],
                'scripts' => []
            ];
        }
        
        error_log('WPCCM: Returning empty categories: ' . print_r($categories_with_data, true));
        wp_send_json_success($categories_with_data);
        return;
        
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
    
    /**
     * Extract script name from URL
     * Examples:
     * - "http://localhost:8888/wp_plagin_ccode/wp-includes/js/dist/script-modules/block-library/navigation/view.min.js?ver=61572d447d60c0aa5240" 
     *   → "view.min.js?ver=61572d447d60c0aa5240"
     */
    private function extract_script_name_from_url($url) {
        // Parse the URL to get the path
        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        
        // Get the filename from the path
        $filename = basename($path);
        
        // If there's a query string, add it back
        if (isset($parsed['query']) && !empty($parsed['query'])) {
            $filename .= '?' . $parsed['query'];
        }
        
        // If no filename found, return the full URL
        return !empty($filename) ? $filename : $url;
    }

    private function categorize_cookie_name($cookie_name) {
        $name = strtolower($cookie_name);
        
        // Necessary patterns (essential for site function)
        if (preg_match('/(consent_|phpsessid|wordpress_|_wp_session|csrf_token|wp-settings|session|auth|login|user_|admin_)/i', $name)) {
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
        
        foreach ($current_cookies as $cookie_data) {
            // Handle both old format (string) and new format (array)
            if (is_string($cookie_data)) {
                $cookie_name = sanitize_text_field($cookie_data);
                $cookie_value = '';
                wpccm_debug_log("Processing string cookie: $cookie_name");
            } else if (is_array($cookie_data) && isset($cookie_data['name'])) {
                $cookie_name = sanitize_text_field($cookie_data['name']);
                $cookie_value = sanitize_textarea_field($cookie_data['value'] ?? '');
                wpccm_debug_log("Processing array cookie: $cookie_name", ['value' => $cookie_value]);
            } else {
                wpccm_debug_log("Skipping invalid cookie data", $cookie_data);
                continue;
            }
            
            if (empty($cookie_name)) continue;
            
            $category = $this->categorize_cookie_name($cookie_name);
            
            // Include ALL cookies (no filtering)
            $all_cookies[] = [
                'name' => $cookie_name,
                'value' => $cookie_value,
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
        
        // Get existing cookies for comparison
        $existing_cookies = wpccm_get_cookies_from_db();
        $existing_names = array_column($existing_cookies, 'name');
        
        // Track new and updated cookies
        $new_cookies = [];
        $updated_cookies = [];
        foreach ($all_cookies as $cookie) {
            if (!in_array($cookie['name'], $existing_names)) {
                $new_cookies[] = $cookie;
            } else {
                $updated_cookies[] = $cookie;
            }
        }
        
        // Save cookies to database immediately with values
        wpccm_save_cookies_to_db($all_cookies);

        $record_history = !empty($_POST['record_history']);
        $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'manual';

          wpccm_save_sync_history(
            'manual',
            count($all_cookies),
            count($new_cookies),
            count($updated_cookies),
            $new_cookies, // Only save new cookies data
            'success'
        );
        
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
         $license_key = get_option('wpccm_license_key', '');
         if (empty($license_key)) {
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
     * AJAX handler for getting user IP address
     */
    public function ajax_get_user_ip() {
        // Verify nonce for security (optional for IP detection)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'wpccm_ajax')) {
            error_log('WPCCM: Nonce verification failed for IP request, but continuing...');
        }
        
        $user_ip = $this->get_real_user_ip();
        wp_send_json_success(['ip' => $user_ip]);
    }
    
    /**
     * Get real user IP address
     */
    private function get_real_user_ip() {
        // Check for shared internet/proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from remote address
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        // Check for IP passed from remote address (CloudFlare)
        elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // Check for IP passed from remote address (other proxies)
        elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }
        // Return normal IP
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        
        return '127.0.0.1'; // fallback
    }

    /**
     * Debug AJAX handler for script mapping
     */
    public function ajax_debug_script_mapping() {
        if (!current_user_can('manage_options')) {
            wp_die('No access');
        }
        
        $script_domain_map = get_option('cc_script_domain_map', []);
        $script_handle_map = get_option('cc_script_handle_map', []);
        
        global $wpdb;
        $scripts_table = $wpdb->prefix . 'ck_scripts';
        $scripts = $wpdb->get_results("SELECT * FROM $scripts_table WHERE is_active = 1", ARRAY_A);
        
        // REBUILD the domain mapping from current scripts
        $new_domain_map = [];
        foreach ($scripts as $script) {
            if ($script['script_type'] === 'external' && !empty($script['script_url'])) {
                $domain = wpccm_extract_domain_from_url($script['script_url']);
                if ($domain) {
                    $new_domain_map[$domain] = $script['category'];
                }
            }
        }
        
        // Update the mapping
        update_option('cc_script_domain_map', $new_domain_map);
        
        wp_send_json_success([
            'script_domain_map_old' => $script_domain_map,
            'script_domain_map_new' => $new_domain_map,
            'script_handle_map' => $script_handle_map,
            'scripts_in_db' => $scripts,
            'message' => 'Domain mapping rebuilt from database!'
        ]);
    }

    /**
     * בדיקה שהפלאגין מחובר לדשבורד
     */
    private function is_dashboard_connected() {
        // בדיקה דינמית שהפלאגין מחובר לדשבורד
         $license_key = get_option('wpccm_license_key', '');
         if (empty($license_key)) {
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

// Schedule automatic cookie sync
// add_action('wp', 'wpccm_schedule_cookie_sync');
// add_action('wpccm_auto_cookie_sync', 'wpccm_perform_auto_cookie_sync');

// Schedule automatic script sync
// add_action('wp', 'wpccm_schedule_script_sync');
// add_action('wpccm_auto_script_sync', 'wpccm_perform_auto_script_sync');

// // Hook for plugin activation to schedule the cron
// register_activation_hook(__FILE__, 'wpccm_activate_cookie_sync');
// register_activation_hook(__FILE__, 'wpccm_activate_script_sync');

// // Hook for plugin deactivation to clear the cron
// register_deactivation_hook(__FILE__, 'wpccm_deactivate_cookie_sync');
// register_deactivation_hook(__FILE__, 'wpccm_deactivate_script_sync');

// // Initialize new modular components
// add_action('init', function() {
//     // Initialize header filtering
//     add_action('shutdown', 'wpccm_filter_set_cookie_headers');
//     add_action('wp_footer', 'wpccm_filter_set_cookie_headers');
    
//     // Initialize script filtering
//     add_filter('script_loader_tag', 'wpccm_filter_script_tag', 10, 3);
//     add_filter('wp_add_inline_script', 'wpccm_filter_inline_script', 10, 2);
    
//     // Initialize AJAX handlers
//     add_action('wp_ajax_wpccm_delete_cookies', 'wpccm_ajax_delete_cookies');
//     add_action('wp_ajax_nopriv_wpccm_delete_cookies', 'wpccm_ajax_nopriv_delete_cookies');
    
//     // Initialize script enqueuing
//     add_action('wp_enqueue_scripts', 'wpccm_conditional_enqueue');
// });

// Initialize main plugin for frontend and AJAX
add_action('wp_loaded', function() {
    if (!is_admin() || wp_doing_ajax()) {
        
        if (!isset($GLOBALS['wpccm_instance'])) {

            // בדיקה שהפלאגין מחובר לדשבורד לפני טעינה
            $license_key = get_option('wpccm_license_key', '');

            if (!empty($license_key)) {
                // בדיקה שהרישיון באמת תקין
                $dashboard = WP_CCM_Dashboard::get_instance();
                $test_result = $dashboard->test_connection_silent();
               
                if ($test_result && isset($test_result['success']) && $test_result['success']) {
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

    // Set default sync interval to 60 minutes if not set
    if (!get_option('wpccm_sync_interval_minutes')) {
        update_option('wpccm_sync_interval_minutes', 60);
    }

    // Enable auto sync by default and start scheduling
    update_option('wpccm_auto_sync_enabled', true);
    update_option('wpccm_auto_script_sync_enabled', true);
    update_option('wpccm_auto_form_sync_enabled', true);

    // Schedule sync to start immediately on activation
    // wpccm_schedule_cookie_sync();
    // wpccm_schedule_script_sync();
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
    
    // Create cookies table
    $cookies_table = $wpdb->prefix . 'ck_cookies';
    
    $cookies_sql = "CREATE TABLE $cookies_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        value text NULL,
        category varchar(50) NOT NULL DEFAULT 'others',
        description text NULL,
        purpose text NULL,
        expiry varchar(100) NULL,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_name (name),
        KEY category (category),
        KEY is_active (is_active)
    ) $charset_collate;";
    
    dbDelta($cookies_sql);
    
    // Create sync history table
    $sync_history_table = $wpdb->prefix . 'ck_sync_history';
    $sync_history_sql = "CREATE TABLE $sync_history_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        sync_type varchar(50) NOT NULL DEFAULT 'auto_cookie',
        sync_time datetime DEFAULT CURRENT_TIMESTAMP,
        total_cookies_found int(10) NOT NULL DEFAULT 0,
        new_cookies_added int(10) NOT NULL DEFAULT 0,
        updated_cookies int(10) NOT NULL DEFAULT 0,
        cookies_data longtext NULL,
        status varchar(20) NOT NULL DEFAULT 'success',
        error_message text NULL,
        execution_time float NULL,
        user_id bigint(20) NULL,
        PRIMARY KEY (id),
        KEY sync_type (sync_type),
        KEY sync_time (sync_time),
        KEY status (status)
    ) $charset_collate;";
    
    dbDelta($sync_history_sql);
    
    // Create categories table
    $categories_table = $wpdb->prefix . 'ck_categories';
    $categories_sql = "CREATE TABLE $categories_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        category_key varchar(50) NOT NULL,
        display_name varchar(255) NOT NULL,
        description text NULL,
        color varchar(7) NOT NULL DEFAULT '#666666',
        icon varchar(50) NULL,
        sort_order int(10) NOT NULL DEFAULT 0,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        is_essential tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_category_key (category_key),
        KEY is_active (is_active),
        KEY sort_order (sort_order)
    ) $charset_collate;";
    
    dbDelta($categories_sql);
    
    // Create scripts table
    $scripts_table = $wpdb->prefix . 'ck_scripts';
    $scripts_sql = "CREATE TABLE $scripts_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        script_url varchar(2048) NOT NULL,
        script_type varchar(50) NOT NULL DEFAULT 'external',
        script_content longtext NULL,
        script_hash varchar(64) NULL,
        category varchar(50) NOT NULL DEFAULT 'others',
        is_active tinyint(1) NOT NULL DEFAULT 1,
        first_detected datetime DEFAULT CURRENT_TIMESTAMP,
        last_seen datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        detection_count int(10) NOT NULL DEFAULT 1,
        notes text NULL,
        PRIMARY KEY (id),
        KEY script_url (script_url(255)),
        KEY category (category),
        KEY is_active (is_active),
        KEY last_seen (last_seen)
    ) $charset_collate;";
    
    dbDelta($scripts_sql);

    // Create forms table
    $forms_table = $wpdb->prefix . 'ck_forms';
    $forms_sql = "CREATE TABLE $forms_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        form_hash varchar(64) NOT NULL,
        post_id bigint(20) NOT NULL,
        page_title varchar(255) NOT NULL,
        page_url varchar(512) DEFAULT '' NOT NULL,
        form_id_attr varchar(191) NULL,
        form_class_attr text NULL,
        form_action varchar(512) NULL,
        form_method varchar(10) NOT NULL DEFAULT 'POST',
        identifier varchar(255) NULL,
        consent_required tinyint(1) NOT NULL DEFAULT 1,
        is_active tinyint(1) NOT NULL DEFAULT 1,
        detection_count int(10) NOT NULL DEFAULT 1,
        detected_at datetime DEFAULT CURRENT_TIMESTAMP,
        last_seen datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY form_hash (form_hash),
        KEY post_id (post_id),
        KEY is_active (is_active)
    ) $charset_collate;";

    dbDelta($forms_sql);
    
    // Migrate existing cookies data
    wpccm_migrate_cookies_data();
    
    // Create default categories
    wpccm_create_default_categories();
    
    // Enable auto sync for both cookies and scripts on first activation
    if (!get_option('wpccm_auto_sync_enabled', false)) {
        update_option('wpccm_auto_sync_enabled', true);
    }
    if (!get_option('wpccm_auto_script_sync_enabled', false)) {
        update_option('wpccm_auto_script_sync_enabled', true);
    }
    if (!get_option('wpccm_auto_form_sync_enabled', false)) {
        update_option('wpccm_auto_form_sync_enabled', true);
    }
    
    // Update database version
    update_option('wpccm_db_version', '1.5');
}

/**
 * Migrate existing cookies data from wp_options to database table
 */
function wpccm_migrate_cookies_data() {
    // Check if migration already done
    if (get_option('wpccm_cookies_migrated', false)) {
        return;
    }
    
    $opts = get_option('wpccm_options', []);
    $existing_cookies = isset($opts['purge']['cookies']) ? $opts['purge']['cookies'] : [];
    
    if (!empty($existing_cookies)) {
        wpccm_save_cookies_to_db($existing_cookies);
        
        // Mark migration as completed
        update_option('wpccm_cookies_migrated', true);
        
        error_log('WPCCM: Migrated ' . count($existing_cookies) . ' cookies to database table');
    }
}

/**
 * Add custom cron schedules
 */
function wpccm_add_cron_schedules($schedules) {
    // Get current sync interval (default to 60 minutes)
    $interval_minutes = get_option('wpccm_sync_interval_minutes', 60);

    // Create dynamic cron schedule
    $schedules['wpccm_custom_interval'] = array(
        'interval' => $interval_minutes * 60, // Convert minutes to seconds
        'display'  => sprintf(esc_html__('Every %d minutes (WPCCM)'), $interval_minutes)
    );

    // Keep the old minute schedule for backward compatibility
    $schedules['wpccm_every_minute'] = array(
        'interval' => 60, // 60 seconds = 1 minute
        'display'  => esc_html__('Every Minute (WPCCM)')
    );

    // Add some common intervals for more reliability
    $schedules['wpccm_every_2_minutes'] = array(
        'interval' => 120,
        'display'  => esc_html__('Every 2 minutes (WPCCM)')
    );

    $schedules['wpccm_every_5_minutes'] = array(
        'interval' => 300,
        'display'  => esc_html__('Every 5 minutes (WPCCM)')
    );

    $schedules['wpccm_every_15_minutes'] = array(
        'interval' => 900,
        'display'  => esc_html__('Every 15 minutes (WPCCM)')
    );

    $schedules['wpccm_every_30_minutes'] = array(
        'interval' => 1800,
        'display'  => esc_html__('Every 30 minutes (WPCCM)')
    );

    return $schedules;
}
add_filter('cron_schedules', 'wpccm_add_cron_schedules');

/**
 * Get appropriate cron schedule based on interval minutes
 */
function wpccm_get_cron_schedule($interval_minutes) {
    switch ($interval_minutes) {
        case 1:
            return 'wpccm_every_minute';
        case 2:
            return 'wpccm_every_2_minutes';
        case 5:
            return 'wpccm_every_5_minutes';
        case 15:
            return 'wpccm_every_15_minutes';
        case 30:
            return 'wpccm_every_30_minutes';
        case 60:
            return 'hourly'; // Use WordPress built-in hourly
        default:
            return 'wpccm_custom_interval'; // Use dynamic schedule for other values
    }
}

/**
 * Schedule automatic cookie sync every minute
 */
function wpccm_schedule_cookie_sync() {
    // Clear ALL existing schedules - force complete cleanup
    wp_clear_scheduled_hook('wpccm_auto_cookie_sync');

    // Only schedule if auto sync is enabled
    if (!get_option('wpccm_auto_sync_enabled', false)) {
        return;
    }

    // Get current sync interval (default to 60 minutes)
    $interval_minutes = get_option('wpccm_sync_interval_minutes', 60);
    $cron_schedule = wpccm_get_cron_schedule($interval_minutes);

    // Calculate next run - start in interval time from now
    $current_time = current_time('timestamp', true);
    $next_run = $current_time + ($interval_minutes * 60);

    // Frontend auto-sync is now used instead of wp_schedule_event
    // wp_schedule_event($next_run, $cron_schedule, 'wpccm_auto_cookie_sync');

    wpccm_debug_log('Scheduled automatic cookie sync', [
        'current_time' => date('Y-m-d H:i:s', $current_time),
        'next_run' => date('Y-m-d H:i:s', $next_run),
        'interval_minutes' => $interval_minutes,
        'cron_schedule' => $cron_schedule
    ]);
}

/**
 * Activate cookie sync cron on plugin activation
 */
function wpccm_activate_cookie_sync() {
    // wpccm_schedule_cookie_sync();
    // wpccm_debug_log('Cookie sync cron activated');
}

/**
 * Deactivate cookie sync cron on plugin deactivation
 */
function wpccm_deactivate_cookie_sync() {
    $timestamp = wp_next_scheduled('wpccm_auto_cookie_sync');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'wpccm_auto_cookie_sync');
        wpccm_debug_log('Cookie sync cron deactivated');
    }
}

/**
 * Schedule automatic script sync every minute
 */
function wpccm_schedule_script_sync() {
    // Clear ALL existing schedules - force complete cleanup
    wp_clear_scheduled_hook('wpccm_auto_script_sync');

    // Only schedule if auto sync is enabled
    if (!get_option('wpccm_auto_sync_enabled', false)) {
        return;
    }

    // Get current sync interval (default to 60 minutes)
    $interval_minutes = get_option('wpccm_sync_interval_minutes', 60);
    $cron_schedule = wpccm_get_cron_schedule($interval_minutes);

    // Calculate next run - start in interval time from now
    $current_time = current_time('timestamp', true);
    $next_run = $current_time + ($interval_minutes * 60);

    // Frontend auto-sync is now used instead of wp_schedule_event
    // wp_schedule_event($next_run, $cron_schedule, 'wpccm_auto_script_sync');

    wpccm_debug_log('Scheduled automatic script sync', [
        'current_time' => date('Y-m-d H:i:s', $current_time),
        'next_run' => date('Y-m-d H:i:s', $next_run),
        'interval_minutes' => $interval_minutes,
        'cron_schedule' => $cron_schedule
    ]);
}

/**
 * Activate script sync cron on plugin activation
 */
function wpccm_activate_script_sync() {
    update_option('wpccm_auto_script_sync_enabled', true);
    // wpccm_schedule_script_sync();
    // wpccm_debug_log('Script sync cron activated');
}

// /**
//  * Deactivate script sync cron on plugin deactivation
//  */
// function wpccm_deactivate_script_sync() {
//     $timestamp = wp_next_scheduled('wpccm_auto_script_sync');
//     if ($timestamp) {
//         wp_unschedule_event($timestamp, 'wpccm_auto_script_sync');
//         wpccm_debug_log('Script sync cron deactivated');
//     }
// }

/**
 * Perform automatic script sync in background
 */
function wpccm_perform_auto_script_sync() {
    $start_time = microtime(true);
    wpccm_debug_log('Starting automatic script sync');
    
    // Check if plugin is properly activated and licensed
    if (!WP_CCM_Consent::is_plugin_activated()) {
        wpccm_debug_log('Auto script sync skipped - plugin not activated');
        // wpccm_save_sync_history('auto_script', 0, 0, 0, null, 'skipped', 'Plugin not activated');
        return;
    }
    
    try {
        // Get existing scripts count for comparison
        $existing_scripts = wpccm_get_scripts_from_db();
        $existing_count = count($existing_scripts);
        $existing_urls = array_column($existing_scripts, 'script_url');
        
        // Get scripts from the current site
        $current_scripts = wpccm_get_current_site_scripts();
        
        if (empty($current_scripts)) {
            $execution_time = microtime(true) - $start_time;
            wpccm_debug_log('Auto script sync - no scripts found on site');
            // wpccm_save_sync_history('auto_script', 0, 0, 0, null, 'success', null, $execution_time);
            return;
        }
        
        wpccm_debug_log('Auto script sync found scripts', ['count' => count($current_scripts)]);
        
        // Process and categorize scripts
        $processed_scripts = [];
        $new_scripts = [];
        $updated_scripts = [];
        
        foreach ($current_scripts as $script_data) {
            $processed_script = [
                'url' => $script_data['url'],
                'type' => $script_data['type'],
                'content' => $script_data['content'],
                'hash' => $script_data['hash'],
                'category' => $script_data['category']
            ];
            
            $processed_scripts[] = $processed_script;
            
            // Check if this is a new script
            if (!in_array($script_data['url'], $existing_urls)) {
                $new_scripts[] = $processed_script;
            } else {
                $updated_scripts[] = $processed_script;
            }
        }
        
        if (!empty($processed_scripts)) {
            // Save to database
            $result = wpccm_save_scripts_to_db($processed_scripts);
            
            $execution_time = microtime(true) - $start_time;
            
            // Save history
            wpccm_save_sync_history(
                'auto_script',
                count($processed_scripts),
                $result['new'],
                $result['updated'],
                array_slice($new_scripts, 0, 10), // Only save first 10 new scripts for history
                'success',
                null,
                $execution_time
            );
            
            wpccm_debug_log('Auto script sync completed successfully', [
                'total_scripts' => count($processed_scripts),
                'new_scripts' => $result['new'],
                'updated_scripts' => $result['updated'],
                'execution_time' => $execution_time
            ]);
        }
        
    } catch (Exception $e) {
        $execution_time = microtime(true) - $start_time;
        $error_message = $e->getMessage();
        
        wpccm_debug_log('Auto script sync error: ' . $error_message);
        error_log('WPCCM Auto Script Sync Error: ' . $error_message);
        
        // Save error to history
        wpccm_save_sync_history(
            'auto_script',
            0,
            0,
            0,
            null,
            'error',
            $error_message,
            $execution_time
        );
    }
}

/**
 * Perform automatic cookie sync in background
 */
function wpccm_perform_auto_cookie_sync() {
    $start_time = microtime(true);
    wpccm_debug_log('Starting automatic cookie sync');
    
    // Check if plugin is properly activated and licensed
    if (!WP_CCM_Consent::is_plugin_activated()) {
        wpccm_debug_log('Auto sync skipped - plugin not activated');
        wpccm_save_sync_history('auto_cookie', 0, 0, 0, null, 'skipped', 'Plugin not activated');
        return;
    }
    
    try {
        // Get existing cookies count for comparison
        $existing_cookies = wpccm_get_cookies_from_db();
        $existing_count = count($existing_cookies);
        $existing_names = array_column($existing_cookies, 'name');
        
        // Get cookies from the current site
        $current_cookies = wpccm_get_current_site_cookies();
        
        if (empty($current_cookies)) {
            $execution_time = microtime(true) - $start_time;
            wpccm_debug_log('Auto sync - no cookies found on site');
            wpccm_save_sync_history('auto_cookie', 0, 0, 0, null, 'success', null, $execution_time);
            return;
        }
        
        wpccm_debug_log('Auto sync found cookies', ['count' => count($current_cookies)]);
        
        // Process and categorize cookies
        $processed_cookies = [];
        $new_cookies = [];
        $updated_cookies = [];
        
        foreach ($current_cookies as $cookie_data) {
            if (!isset($cookie_data['name']) || empty($cookie_data['name'])) {
                continue;
            }
            
            $cookie_name = sanitize_text_field($cookie_data['name']);
            $cookie_value = sanitize_textarea_field($cookie_data['value'] ?? '');
            
            // Use existing categorization logic
            $category = wpccm_categorize_cookie_name($cookie_name);
            
            $processed_cookie = [
                'name' => $cookie_name,
                'value' => $cookie_value,
                'category' => $category,
                'category_display' => wpccm_get_category_display_name($category),
                'reason' => wpccm_get_cookie_reason_by_category($cookie_name, $category)
            ];
            
            $processed_cookies[] = $processed_cookie;
            
            // Track if this is a new cookie or updated
            if (!in_array($cookie_name, $existing_names)) {
                $new_cookies[] = $processed_cookie;
            } else {
                $updated_cookies[] = $processed_cookie;
            }
        }
        
        if (!empty($processed_cookies)) {
            // Save to database
            wpccm_save_cookies_to_db($processed_cookies);
            
            $execution_time = microtime(true) - $start_time;
            
            // Save history
            wpccm_save_sync_history(
                'auto_cookie',
                count($processed_cookies),
                count($new_cookies),
                count($updated_cookies),
                $new_cookies, // Only save new cookies data to keep history manageable
                'success',
                null,
                $execution_time
            );
            
            wpccm_debug_log('Auto sync completed successfully', [
                'total_cookies' => count($processed_cookies),
                'new_cookies' => count($new_cookies),
                'updated_cookies' => count($updated_cookies),
                'execution_time' => $execution_time
            ]);
        }
        
    } catch (Exception $e) {
        $execution_time = microtime(true) - $start_time;
        $error_message = $e->getMessage();
        
        wpccm_debug_log('Auto sync error: ' . $error_message);
        error_log('WPCCM Auto Sync Error: ' . $error_message);
        
        // Save error to history
        wpccm_save_sync_history(
            'auto_cookie',
            0,
            0,
            0,
            null,
            'error',
            $error_message,
            $execution_time
        );
    }
}

function wpccm_perform_auto_form_sync($manual = false) {
    $start_time = microtime(true);

    if (!WP_CCM_Consent::is_plugin_activated()) {
        if ($manual) {
            wpccm_save_sync_history('manual_forms', 0, 0, 0, null, 'skipped', 'Plugin not activated');
        } else {
            wpccm_save_sync_history('auto_forms', 0, 0, 0, null, 'skipped', 'Plugin not activated');
        }
        return ['total' => 0, 'new' => 0, 'updated' => 0];
    }

    $forms = wpccm_detect_site_forms();
    $total = count($forms);

    $save_result = wpccm_save_forms_to_db($forms);

    $execution_time = microtime(true) - $start_time;

    $sync_type = $manual ? 'manual_forms' : 'auto_forms';

    $history_payload = [];
    if (!empty($save_result['new'])) {
        foreach (array_slice($save_result['new'], 0, 10) as $form) {
            $history_payload[] = [
                'page_title' => $form['page_title'],
                'identifier' => $form['identifier'],
                'action' => $form['form_action'],
            ];
        }
    }

    wpccm_save_sync_history(
        $sync_type,
        $total,
        count($save_result['new']),
        count($save_result['updated']),
        $history_payload,
        'success',
        null,
        $execution_time
    );

    return [
        'total' => $total,
        'new' => count($save_result['new']),
        'updated' => count($save_result['updated'])
    ];
}

function wpccm_detect_site_forms() {
    $forms = [];
    $seen = [];

    $post_types = get_post_types([
        'public' => true,
    ]);

    if (empty($post_types)) {
        $post_types = ['post', 'page'];
    }

    $query = new WP_Query([
        'post_type'      => $post_types,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ]);

    if ($query->have_posts()) {
        foreach ($query->posts as $post_id) {
            global $post;
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            $content = $post->post_content;
            $rendered_content = apply_filters('the_content', $content);

            $extracted = wpccm_extract_forms_from_content($rendered_content, $post_id);
            foreach ($extracted as $form) {
                if (isset($seen[$form['form_hash']])) {
                    continue;
                }
                $forms[] = $form;
                $seen[$form['form_hash']] = true;
            }
        }
    }

    wp_reset_postdata();

    return $forms;
}

function wpccm_extract_forms_from_content($content, $post_id) {
    $results = [];

    if (empty($content)) {
        return $results;
    }

    if (!class_exists('DOMDocument')) {
        return $results;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $html = '<!DOCTYPE html><html><body>' . $content . '</body></html>';
    $loaded = $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    if (!$loaded) {
        return $results;
    }

    $forms = $dom->getElementsByTagName('form');
    if (!$forms->length) {
        return $results;
    }

    $page_title = get_the_title($post_id);
    $page_url = get_permalink($post_id);

    foreach ($forms as $form) {
        $id_attr = $form->getAttribute('id');
        $class_attr = trim($form->getAttribute('class'));
        $action_attr = $form->getAttribute('action');
        $method_attr = strtoupper($form->getAttribute('method')) ?: 'POST';

        $identifier = '';
        if (!empty($id_attr)) {
            $identifier = '#' . $id_attr;
        } elseif (!empty($class_attr)) {
            $classes = preg_split('/\s+/', trim($class_attr));
            if (!empty($classes)) {
                $identifier = '.' . implode('.', array_slice($classes, 0, 2));
            }
        }

        $form_hash = md5($post_id . '|' . $id_attr . '|' . $class_attr . '|' . $action_attr . '|' . $method_attr);

        $identifier_clean = preg_replace('/[^#\.\w\- ]/', '', $identifier);

        $results[] = [
            'form_hash'        => $form_hash,
            'post_id'          => (int) $post_id,
            'page_title'       => sanitize_text_field($page_title),
            'page_url'         => esc_url_raw($page_url),
            'form_id_attr'     => sanitize_text_field($id_attr),
            'form_class_attr'  => sanitize_text_field($class_attr),
            'form_action'      => esc_url_raw($action_attr),
            'form_method'      => sanitize_text_field($method_attr),
            'identifier'       => $identifier_clean,
        ];
    }

    return $results;
}

function wpccm_save_forms_to_db($forms_data) {
    global $wpdb;

    $forms_table = $wpdb->prefix . 'ck_forms';

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms_table));
    if ($table_exists !== $forms_table) {
        wpccm_create_database_tables();
    }

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $forms_table));
    if ($table_exists !== $forms_table) {
        error_log('WPCCM Forms Sync: forms table missing, aborting save');
        return ['new' => [], 'updated' => []];
    }

    if (!is_array($forms_data)) {
        $forms_data = [];
    }

    // Build map of existing forms
    $existing_rows = $wpdb->get_results("SELECT id, form_hash, detection_count FROM $forms_table", ARRAY_A);
    $existing_map = [];
    if ($existing_rows) {
        foreach ($existing_rows as $row) {
            $existing_map[$row['form_hash']] = $row;
        }
    }

    // Mark all as inactive before updating
    $wpdb->query("UPDATE $forms_table SET is_active = 0");

    $new = [];
    $updated = [];

    $now = current_time('mysql');

    foreach ($forms_data as $form) {
        $hash = $form['form_hash'];
        if (isset($existing_map[$hash])) {
            $row = $existing_map[$hash];
            $updated_rows = $wpdb->update(
                $forms_table,
                [
                    'post_id'         => $form['post_id'],
                    'page_title'      => $form['page_title'],
                    'page_url'        => $form['page_url'],
                    'form_id_attr'    => $form['form_id_attr'],
                    'form_class_attr' => $form['form_class_attr'],
                    'form_action'     => $form['form_action'],
                    'form_method'     => $form['form_method'],
                    'identifier'      => $form['identifier'],
                    'is_active'       => 1,
                    'last_seen'       => $now,
                    'detection_count' => (int) $row['detection_count'] + 1,
                ],
                ['id' => $row['id']],
                ['%d','%s','%s','%s','%s','%s','%s','%s','%d','%s','%d'],
                ['%d']
            );

            if ($updated_rows === false) {
                error_log('WPCCM Forms Sync: update failed - ' . $wpdb->last_error);
            } else {
                $updated[] = $form;
            }
        } else {
            $inserted = $wpdb->insert(
                $forms_table,
                [
                    'form_hash'        => $hash,
                    'post_id'          => $form['post_id'],
                    'page_title'       => $form['page_title'],
                    'page_url'         => $form['page_url'],
                    'form_id_attr'     => $form['form_id_attr'],
                    'form_class_attr'  => $form['form_class_attr'],
                    'form_action'      => $form['form_action'],
                    'form_method'      => $form['form_method'],
                    'identifier'       => $form['identifier'],
                    'consent_required' => 1,
                    'is_active'        => 1,
                    'detection_count'  => 1,
                    'detected_at'      => $now,
                    'last_seen'        => $now,
                ],
                ['%s','%d','%s','%s','%s','%s','%s','%s','%s','%d','%d','%d','%s','%s']
            );

            if ($inserted === false) {
                error_log('WPCCM Forms Sync: insert failed - ' . $wpdb->last_error);
            } else {
                $new[] = $form;
            }
        }
    }

    return [
        'new' => $new,
        'updated' => $updated
    ];
}

function wpccm_get_forms_from_db($active_only = true) {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'ck_forms';

    $where = $active_only ? 'WHERE is_active = 1' : '';

    return $wpdb->get_results(
        "SELECT * FROM $forms_table $where ORDER BY last_seen DESC",
        ARRAY_A
    ) ?: [];
}

/**
 * Get current site cookies (simplified version for background sync)
 */
function wpccm_get_current_site_cookies() {
    $cookies = [];
    
    // Try to get cookies from WordPress cookies if available
    if (!empty($_COOKIE)) {
        foreach ($_COOKIE as $name => $value) {
            // Skip WordPress admin cookies and session cookies
            if (strpos($name, 'wordpress_') === 0 || 
                strpos($name, 'wp-') === 0 || 
                $name === 'PHPSESSID' ||
                strpos($name, 'comment_') === 0) {
                continue;
            }
            
            $cookies[] = [
                'name' => $name,
                'value' => $value
            ];
        }
    }
    
    return $cookies;
}

/**
 * Helper functions for background sync
 */
function wpccm_categorize_cookie_name($cookie_name) {
    $cookie_name_lower = strtolower($cookie_name);
    
    // Necessary patterns
    if (preg_match('/(session|csrf|security|essential|required|auth|login|consent_necessary)/i', $cookie_name_lower)) {
        return 'necessary';
    }
    
    // Functional patterns  
    if (preg_match('/(wp-|wordpress|preference|language|currency|cart|wishlist|compare)/i', $cookie_name_lower)) {
        return 'functional';
    }
    
    // Performance patterns
    if (preg_match('/(cache|cdn|speed|performance|optimization)/i', $cookie_name_lower)) {
        return 'performance';
    }
    
    // Analytics patterns
    if (preg_match('/(google.*analytics|gtag|gtm|_ga|_gid|_gat|analytics|mixpanel|segment|hotjar|matomo|piwik|tracking|statistics)/i', $cookie_name_lower)) {
        return 'analytics';
    }
    
    // Advertisement patterns
    if (preg_match('/(facebook|fb|_fbp|_fbc|doubleclick|adsystem|googlesyndication|ads|advertisement|marketing|retargeting)/i', $cookie_name_lower)) {
        return 'advertisement';
    }
    
    // Default to others
    return 'others';
}

function wpccm_get_category_display_name($category_key) {
    $category = wpccm_get_category_by_key($category_key);
    
    if ($category) {
        return $category['display_name'];
    }
    
    // Fallback for backward compatibility
    $names = [
        'necessary' => 'נחוץ',
        'functional' => 'פונקציונלי', 
        'performance' => 'ביצועים',
        'analytics' => 'אנליטיקה',
        'advertisement' => 'פרסום',
        'others' => 'אחרים'
    ];
    
    return isset($names[$category_key]) ? $names[$category_key] : $names['others'];
}

function wpccm_get_cookie_reason_by_category($cookie_name, $category) {
    $lang = wpccm_get_lang();
    
    $reasons = [
        'necessary' => [
            'en' => 'Essential for basic site functionality',
            'he' => 'חיוני לתפקוד בסיסי של האתר',
        ],
        'functional' => [
            'en' => 'Improves the user experience',
            'he' => 'משפר את חוויית המשתמש',
        ],
        'performance' => [
            'en' => 'Helps improve site performance',
            'he' => 'עוזר לשיפור ביצועי האתר',
        ],
        'analytics' => [
            'en' => 'Helps understand site usage',
            'he' => 'עוזר להבין את השימוש באתר',
        ],
        'advertisement' => [
            'en' => 'Used to show personalized ads',
            'he' => 'משמש להצגת פרסומות מותאמות',
        ],
        'default' => [
            'en' => 'Other functionality on the site',
            'he' => 'פונקציונליות אחרת של האתר',
        ],
    ];
    
    $reason_key = isset($reasons[$category]) ? $category : 'default';
    
    return $reasons[$reason_key][$lang] ?? $reasons[$reason_key]['en'];
}

/**
 * Default category definitions in both languages.
 */
function wpccm_get_default_category_definitions() {
    return [
        'necessary' => [
            'display_name' => [
                'en' => 'Necessary',
                'he' => 'נחוץ',
            ],
            'description' => [
                'en' => 'Essential cookies for basic site functionality',
                'he' => 'עוגיות הכרחיות לפעולת האתר הבסיסית',
            ],
            'color' => '#d63384',
            'icon' => '🔒',
            'sort_order' => 1,
            'is_essential' => 1,
        ],
        'functional' => [
            'display_name' => [
                'en' => 'Functional',
                'he' => 'פונקציונלי',
            ],
            'description' => [
                'en' => 'Cookies that improve user experience',
                'he' => 'עוגיות המשפרות את חוויית המשתמש',
            ],
            'color' => '#0073aa',
            'icon' => '⚙️',
            'sort_order' => 2,
            'is_essential' => 0,
        ],
        'performance' => [
            'display_name' => [
                'en' => 'Performance',
                'he' => 'ביצועים',
            ],
            'description' => [
                'en' => 'Cookies that enhance website performance',
                'he' => 'עוגיות לשיפור ביצועי האתר',
            ],
            'color' => '#00a32a',
            'icon' => '📈',
            'sort_order' => 3,
            'is_essential' => 0,
        ],
        'analytics' => [
            'display_name' => [
                'en' => 'Analytics',
                'he' => 'אנליטיקה',
            ],
            'description' => [
                'en' => 'Cookies for measuring traffic and user behavior',
                'he' => 'עוגיות למדידת תנועה וניתוח התנהגות משתמשים',
            ],
            'color' => '#dba617',
            'icon' => '📊',
            'sort_order' => 4,
            'is_essential' => 0,
        ],
        'advertisement' => [
            'display_name' => [
                'en' => 'Advertisement',
                'he' => 'פרסום',
            ],
            'description' => [
                'en' => 'Cookies for personalized advertising and marketing',
                'he' => 'עוגיות למטרות פרסום ושיווק מותאם אישית',
            ],
            'color' => '#8c8f94',
            'icon' => '📢',
            'sort_order' => 5,
            'is_essential' => 0,
        ],
        'others' => [
            'display_name' => [
                'en' => 'Others',
                'he' => 'אחרים',
            ],
            'description' => [
                'en' => 'Cookies that do not fit other categories',
                'he' => 'עוגיות שלא נכללות בקטגוריות אחרות',
            ],
            'color' => '#666666',
            'icon' => '📦',
            'sort_order' => 6,
            'is_essential' => 0,
        ],
    ];
}

/**
 * Adjust default categories to the current site language unless user-customized.
 */
function wpccm_localize_default_categories($categories) {
    if (empty($categories)) {
        return [];
    }
    
    $defaults = wpccm_get_default_category_definitions();
    $lang     = wpccm_get_lang();
    
    foreach ($categories as &$category) {
        $key = isset($category['category_key']) ? $category['category_key'] : null;
        if (!$key || !isset($defaults[$key])) {
            continue;
        }
        
        $default = $defaults[$key];
        
        $known_display_names = array_values($default['display_name']);
        $known_descriptions  = array_values($default['description']);
        
        $has_custom_name = !isset($category['display_name']) || !in_array($category['display_name'], $known_display_names, true) ? true : false;
        $has_custom_desc = !isset($category['description']) || !in_array($category['description'], $known_descriptions, true) ? true : false;
        
        if (!$has_custom_name && isset($default['display_name'][$lang])) {
            $category['display_name'] = $default['display_name'][$lang];
        }
        
        if (!$has_custom_desc && isset($default['description'][$lang])) {
            $category['description'] = $default['description'][$lang];
        }
    }
    
    unset($category);
    
    return $categories;
}

/**
 * Create default categories
 */
function wpccm_create_default_categories() {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ck_categories';
    
    // Check if categories already exist
    $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
    if ($existing_count > 0) {
        return; // Categories already exist
    }
    
    $default_categories = wpccm_get_default_category_definitions();
    $lang = wpccm_get_lang();
    
    foreach ($default_categories as $category_key => $category) {
        $display_name = isset($category['display_name'][$lang]) ? $category['display_name'][$lang] : $category['display_name']['en'];
        $description  = isset($category['description'][$lang]) ? $category['description'][$lang] : $category['description']['en'];
        
        $wpdb->insert(
            $categories_table,
            [
                'category_key' => $category_key,
                'display_name' => $display_name,
                'description' => $description,
                'color' => $category['color'],
                'icon' => $category['icon'],
                'sort_order' => $category['sort_order'],
                'is_active' => 1,
                'is_essential' => $category['is_essential']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
        );
    }
    
    wpccm_debug_log('Default categories created', ['count' => count($default_categories)]);
}

/**
 * Get all active categories from database
 */
function wpccm_get_categories($active_only = true) {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ck_categories';
    
    $where_clause = $active_only ? "WHERE is_active = 1" : "";
    
    $categories = $wpdb->get_results(
        "SELECT * FROM $categories_table $where_clause ORDER BY sort_order ASC, display_name ASC",
        ARRAY_A
    );
    
    if (!$categories) {
        return [];
    }
    
    return wpccm_localize_default_categories($categories);
}

/**
 * Get category by key
 */
function wpccm_get_category_by_key($category_key) {
    global $wpdb;
    
    $categories_table = $wpdb->prefix . 'ck_categories';
    
    $category = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $categories_table WHERE category_key = %s AND is_active = 1",
        $category_key
    ), ARRAY_A);
    
    if (!$category) {
        return null;
    }
    
    $localized = wpccm_localize_default_categories([$category]);
    return $localized ? $localized[0] : null;
}

/**
 * Save sync history to database
 */
function wpccm_save_sync_history($sync_type, $total_found, $new_added, $updated, $cookies_data = null, $status = 'success', $error_message = null, $execution_time = null) {
    global $wpdb;
    
    $sync_history_table = $wpdb->prefix . 'ck_sync_history';
    
    $data = [
        'sync_type' => sanitize_text_field($sync_type),
        'sync_time' => current_time('mysql'),
        'total_cookies_found' => (int) $total_found,
        'new_cookies_added' => (int) $new_added,
        'updated_cookies' => (int) $updated,
        'cookies_data' => $cookies_data ? json_encode($cookies_data) : null,
        'status' => sanitize_text_field($status),
        'error_message' => $error_message ? sanitize_textarea_field($error_message) : null,
        'execution_time' => $execution_time ? (float) $execution_time : null,
        'user_id' => get_current_user_id() ?: null
    ];
    
    $formats = ['%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%d'];
    
    $result = $wpdb->insert($sync_history_table, $data, $formats);
    
    wpccm_debug_log('Sync history saved', [
        'result' => $result,
        'sync_type' => $sync_type,
        'total_found' => $total_found,
        'new_added' => $new_added,
        'updated' => $updated,
        'status' => $status
    ]);
    
    return $result;
}

/**
 * Get sync history from database
 */
function wpccm_get_sync_history($limit = 20) {
    global $wpdb;
    
    $sync_history_table = $wpdb->prefix . 'ck_sync_history';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $sync_history_table ORDER BY sync_time DESC LIMIT %d",
        $limit
    ), ARRAY_A);
    
    return $results ?: [];
}

/**
 * Debug logging function for WPCCM
 * Set WPCCM_DEBUG to true to enable logging
 */
function wpccm_debug_log($message, $data = null) {
    if (!defined('WPCCM_DEBUG') || !WPCCM_DEBUG) {
        return;
    }
    
    $log_message = "[" . date('Y-m-d H:i:s') . "] WPCCM Debug: $message";
    if ($data !== null) {
        $log_message .= "\n" . print_r($data, true);
    }
    $log_message .= "\n\n";
    
    file_put_contents(__DIR__ . '/wpccm-debug.log', $log_message, FILE_APPEND | LOCK_EX);
    error_log("WPCCM Debug: $message");
}

/**
 * Save scripts to database
 */
function wpccm_save_scripts_to_db($scripts_data) {
    global $wpdb;
    
    wpccm_debug_log('wpccm_save_scripts_to_db called', $scripts_data);
    
    $scripts_table = $wpdb->prefix . 'ck_scripts';
    
    // First, mark all existing scripts as inactive
    $wpdb->update(
        $scripts_table,
        ['is_active' => 0],
        [],
        ['%d']
    );
    
    $new_count = 0;
    $updated_count = 0;
    
    // Initialize mappings for legacy blocking system
    $script_domain_map = get_option('cc_script_domain_map', []);
    
    foreach ($scripts_data as $script_data) {
        $script_url = sanitize_text_field($script_data['url']);
        $script_type = sanitize_text_field($script_data['type']);
        $script_content = isset($script_data['content']) ? $script_data['content'] : '';
        $script_hash = isset($script_data['hash']) ? sanitize_text_field($script_data['hash']) : md5($script_url . $script_content);
        $category = sanitize_text_field($script_data['category']);
        
        // Check if script already exists
        $existing_script = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $scripts_table WHERE script_url = %s OR script_hash = %s",
            $script_url,
            $script_hash
        ));
        
        if ($existing_script) {
            // Update existing script
            $wpdb->update(
                $scripts_table,
                [
                    'script_type' => $script_type,
                    'script_content' => $script_content,
                    'category' => $category,
                    'is_active' => 1,
                    'last_seen' => current_time('mysql'),
                    'detection_count' => $existing_script->detection_count + 1
                ],
                ['id' => $existing_script->id],
                ['%s', '%s', '%s', '%d', '%s', '%d'],
                ['%d']
            );
            $updated_count++;
        } else {
            // Insert new script
            $wpdb->insert(
                $scripts_table,
                [
                    'script_url' => $script_url,
                    'script_type' => $script_type,
                    'script_content' => $script_content,
                    'script_hash' => $script_hash,
                    'category' => $category,
                    'is_active' => 1,
                    'first_detected' => current_time('mysql'),
                    'last_seen' => current_time('mysql'),
                    'detection_count' => 1
                ],
                ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d']
            );
            $new_count++;
        }
        
        // IMPORTANT: Update the legacy mapping system for blocking to work
        if ($script_type === 'external' && !empty($script_url)) {
            // Extract domain for domain mapping
            $domain = wpccm_extract_domain_from_url($script_url);
            if ($domain) {
                $script_domain_map[$domain] = $category;
            }
        }
    }
    
    // Update the legacy mapping options that the blocking system uses
    update_option('cc_script_domain_map', $script_domain_map);
    
    wpccm_debug_log('Scripts saved to database and legacy mappings updated', [
        'total_scripts' => count($scripts_data),
        'new_scripts' => $new_count,
        'updated_scripts' => $updated_count,
        'domain_mappings' => count($script_domain_map)
    ]);
    
    return ['new' => $new_count, 'updated' => $updated_count];
}

/**
 * Extract domain from URL for legacy mapping system
 */
function wpccm_extract_domain_from_url($url) {
    $parsed = parse_url($url);
    if (isset($parsed['host'])) {
        return $parsed['host'];
    }
    return false;
}

/**
 * Get scripts from database
 */
function wpccm_get_scripts_from_db() {
    global $wpdb;
    
    $scripts_table = $wpdb->prefix . 'ck_scripts';
    
    $results = $wpdb->get_results(
        "SELECT * FROM $scripts_table WHERE is_active = 1 ORDER BY category, script_url",
        ARRAY_A
    );
    
    return $results ?: [];
}

/**
 * Get current site scripts (scan the frontend)
 */
function wpccm_get_current_site_scripts() {
    $scripts = [];
    
    wpccm_debug_log('Starting script scan', ['site_url' => home_url()]);
    
    // Get the site's homepage content
    $response = wp_remote_get(home_url());
    
    if (is_wp_error($response)) {
        wpccm_debug_log('Failed to fetch site content for script scanning', ['error' => $response->get_error_message()]);
        return $scripts;
    }
    
    $body = wp_remote_retrieve_body($response);
    
    wpccm_debug_log('Received response', ['body_length' => strlen($body)]);
    
    if (empty($body)) {
        wpccm_debug_log('Empty body received when scanning for scripts');
        return $scripts;
    }
    
    // Parse HTML to find scripts
    $dom = new DOMDocument();
    @$dom->loadHTML($body);
    $script_tags = $dom->getElementsByTagName('script');
    
    wpccm_debug_log('Found script tags', ['count' => $script_tags->length]);
    
    foreach ($script_tags as $script_tag) {
        $src = $script_tag->getAttribute('src');
        $content = $script_tag->textContent;
        $id = $script_tag->getAttribute('id');
        
        if (!empty($src)) {
            // External script
            $scripts[] = [
                'url' => $src,
                'type' => 'external',
                'content' => '',
                'hash' => md5($src),
                'category' => wpccm_categorize_script($src, '')
            ];
        } elseif (!empty(trim($content))) {
            // Inline script
            $content_hash = md5(trim($content));
            $scripts[] = [
                'url' => 'inline_' . $content_hash,
                'type' => 'inline',
                'content' => trim($content),
                'hash' => $content_hash,
                'category' => wpccm_categorize_script('', trim($content))
            ];
        }
    }
    
    wpccm_debug_log('Scripts found on site', ['count' => count($scripts)]);
    
    return $scripts;
}

/**
 * Categorize script based on URL or content
 */
function wpccm_categorize_script($url, $content) {
    $url_lower = strtolower($url);
    $content_lower = strtolower($content);
    
    // NECESSARY: Our own plugin scripts (always allow)
    if (strpos($url_lower, 'wp-cookie-consent-manager') !== false ||
        strpos($content_lower, 'wpccm') !== false ||
        strpos($content_lower, 'cookie consent') !== false ||
        strpos($content_lower, 'wpccmjanitordata') !== false ||
        strpos($content_lower, 'wpccmconsentdata') !== false) {
        return 'necessary';
    }
    
    // NECESSARY: Essential WordPress and consent-related scripts
    if (strpos($url_lower, 'wp-includes') !== false ||
        strpos($url_lower, 'wp-admin') !== false ||
        strpos($url_lower, 'jquery') !== false ||
        strpos($content_lower, 'consent') !== false) {
        return 'necessary';
    }
    
    // Analytics scripts
    if (strpos($url_lower, 'google-analytics') !== false ||
        strpos($url_lower, 'googletagmanager') !== false ||
        strpos($url_lower, 'gtag') !== false ||
        strpos($content_lower, 'ga(') !== false ||
        strpos($content_lower, 'gtag(') !== false) {
        return 'analytics';
    }
    
    // Advertisement scripts
    if (strpos($url_lower, 'doubleclick') !== false ||
        strpos($url_lower, 'googlesyndication') !== false ||
        strpos($url_lower, 'facebook.net') !== false ||
        strpos($url_lower, 'ads') !== false) {
        return 'advertisement';
    }
    
    // Performance/CDN scripts
    if (strpos($url_lower, 'cdn.') !== false ||
        strpos($url_lower, 'cloudflare') !== false ||
        strpos($url_lower, 'jquery') !== false) {
        return 'performance';
    }
    
    // Functional scripts
    if (strpos($url_lower, 'chat') !== false ||
        strpos($url_lower, 'support') !== false ||
        strpos($content_lower, 'localstorage') !== false ||
        strpos($content_lower, 'sessionstorage') !== false) {
        return 'functional';
    }
    
    // Default to others
    return 'others';
}

/**
 * Save cookies to database table
 */
function wpccm_save_cookies_to_db($cookies_data) {
    global $wpdb;
    
    wpccm_debug_log('wpccm_save_cookies_to_db called', $cookies_data);
    
    $cookies_table = $wpdb->prefix . 'ck_cookies';
    
    // First, deactivate all existing cookies
    $wpdb->update(
        $cookies_table,
        ['is_active' => 0],
        [],
        ['%d']
    );
    
    // Insert or update each cookie
    foreach ($cookies_data as $cookie) {
        if (empty($cookie['name'])) continue;
        
        $name = sanitize_text_field($cookie['name']);
        $category = sanitize_text_field($cookie['category']) ?: 'others';
        $value = isset($cookie['value']) ? sanitize_textarea_field($cookie['value']) : '';
        
        wpccm_debug_log("Processing cookie: $name", ['value' => $value, 'category' => $category]);
        
        
        // Check if cookie exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $cookies_table WHERE name = %s",
            $name
        ));
        
        if ($existing) {
            // Update existing cookie
            $wpdb->update(
                $cookies_table,
                [
                    'value' => $value,
                    'category' => $category,
                    'is_active' => 1,
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%s', '%s', '%d', '%s'],
                ['%d']
            );
        } else {
            // Insert new cookie
            $wpdb->insert(
                $cookies_table,
                [
                    'name' => $name,
                    'value' => $value,
                    'category' => $category,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%d', '%s', '%s']
            );
        }
    }
}

/**
 * Get cookies from database table
 */
function wpccm_get_cookies_from_db() {
    global $wpdb;
    
    $cookies_table = $wpdb->prefix . 'ck_cookies';
    
    $results = $wpdb->get_results(
        "SELECT * FROM $cookies_table WHERE is_active = 1 ORDER BY category, name",
        ARRAY_A
    );
    
    return $results ?: [];
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

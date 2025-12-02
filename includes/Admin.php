<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/Admin/Assets.php';
require_once __DIR__ . '/Admin/Debug.php';
require_once __DIR__ . '/Admin/Ajax/Consent.php';
require_once __DIR__ . '/Admin/Pages/Deletion.php';
require_once __DIR__ . '/Admin/Pages/History.php';

class WP_CCM_Admin {
    private $deletion_page;
    private $history_page;
    
    public function __construct() {
        $this->deletion_page = new WP_CCM_Admin_Page_Deletion();
        $this->history_page  = new WP_CCM_Admin_Page_History();

        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        (new WP_CCM_Admin_Assets())->register();
        (new WP_CCM_Admin_Debug())->register();
        (new WP_CCM_Admin_Ajax_Consent())->register();
        
        // AJAX handlers for management page
        add_action('wp_ajax_wpccm_delete_data_manually', [$this, 'ajax_delete_data_manually']);
        
        // AJAX handlers for advanced scanner (moved from deleted CC_Detect_Page class)
        add_action('wp_ajax_cc_detect_save_map', [$this, 'ajax_cc_detect_save_map']);
        add_action('wp_ajax_cc_detect_store', [$this, 'ajax_cc_detect_store']);
        add_action('wp_ajax_cc_detect_delete_mapping', [$this, 'ajax_cc_detect_delete_mapping']);
        add_action('wp_ajax_cc_detect_get_registered_scripts', [$this, 'ajax_cc_detect_get_registered_scripts']);


        
        // AJAX handler for saving general settings
        add_action('wp_ajax_wpccm_save_general_settings', [$this, 'ajax_save_general_settings']);
        
        // AJAX handler for saving design settings
        add_action('wp_ajax_wpccm_save_design_settings', [$this, 'ajax_save_design_settings']);
        
        // AJAX handler for getting frontend cookies
        add_action('wp_ajax_wpccm_get_frontend_cookies', [$this, 'ajax_get_frontend_cookies']);
        add_action('wp_ajax_nopriv_wpccm_get_frontend_cookies', [$this, 'ajax_get_frontend_cookies']);
        
        // Auto sync management
        add_action('wp_ajax_wpccm_toggle_auto_sync', [$this, 'ajax_toggle_auto_sync']);
        add_action('wp_ajax_wpccm_get_auto_sync_status', [$this, 'ajax_get_auto_sync_status']);
        add_action('wp_ajax_wpccm_run_manual_auto_sync', [$this, 'ajax_run_manual_auto_sync']);
        add_action('wp_ajax_wpccm_get_sync_details', [$this, 'ajax_get_sync_details']);
        add_action('wp_ajax_wpccm_force_reschedule', [$this, 'ajax_force_reschedule']);
        add_action('wp_ajax_wpccm_change_sync_interval', [$this, 'ajax_change_sync_interval']);

        // Frontend auto sync (no admin permissions required)
        add_action('wp_ajax_wpccm_frontend_auto_sync', [$this, 'ajax_frontend_auto_sync']);
        add_action('wp_ajax_nopriv_wpccm_frontend_auto_sync', [$this, 'ajax_frontend_auto_sync']);
        
        // Category management
        add_action('wp_ajax_wpccm_get_category', [$this, 'ajax_get_category']);
        add_action('wp_ajax_wpccm_save_category', [$this, 'ajax_save_category']);
        add_action('wp_ajax_wpccm_delete_category', [$this, 'ajax_delete_category']);
        // add_action('wp_ajax_wpccm_check_categories_table', [$this, 'ajax_check_categories_table']);
        
        // Script sync management
        add_action('wp_ajax_wpccm_sync_scripts', [$this, 'ajax_sync_scripts']);
        add_action('wp_ajax_wpccm_update_script_category', [$this, 'ajax_update_script_category']);
        add_action('wp_ajax_wpccm_sync_forms', [$this, 'ajax_sync_forms']);
        
        add_action('admin_notices', [$this, 'show_activation_notice']);
    }
    
    public function add_menu() {
        // Main menu page - Cookie Consent Manager
        $menu_title = 'Cookie Consent';
        
        // Add red dot if plugin is not activated
        if (!$this->is_plugin_activated()) {
            $menu_title .= ' <span style="color: #dc3232; font-size: 16px;">●</span>';
        }
        
        add_menu_page(
            wpccm_text('cookie_consent_manager'),
            $menu_title,
            'manage_options',
            'wpccm',
            [$this, 'render_page'],
            'dashicons-shield-alt',
            30
        );
        
        // Settings submenu
        add_submenu_page(
            'wpccm',
            wpccm_text('cookie_consent_manager'),
            wpccm_text('settings'),
            'manage_options',
            'wpccm',
            [$this, 'render_page']
        );

        // Settings submenu
        add_submenu_page(
            'wpccm',
            wpccm_text('sync_services'),
            wpccm_text('sync_services'),
            'manage_options',
            'wpccm-sync',
            [$this, 'render_page_sync']
        );
        
        // NEW: Management & Statistics submenu
        add_submenu_page(
            'wpccm',
            wpccm_translate_pair('Cookie Management & Statistics', 'ניהול עוגיות וסטטיסטיקות'),
            wpccm_translate_pair('Management & Statistics', 'ניהול וסטטיסטיקות'),
            'manage_options',
            'wpccm-management',
            [$this, 'render_management_page']
        );
        
        // NEW: Data Deletion Management submenu
        add_submenu_page(
            'wpccm',
            wpccm_translate_pair('Data Deletion Management', 'ניהול מחיקת נתונים'),
            wpccm_translate_pair('Deletion Management', 'ניהול מחיקה'),
            'manage_options',
            'wpccm-deletion',
            [$this->deletion_page, 'render']
        );
        
        // NEW: Activity History submenu
        add_submenu_page(
            'wpccm',
            wpccm_translate_pair('Activity History', 'היסטוריית פעילות'),
            wpccm_translate_pair('Activity History', 'היסטוריית פעילות'),
            'manage_options',
            'wpccm-history',
            [$this->history_page, 'render']
        );
        
    }
    
    // public function enqueue_admin_assets($hook) {
    //     // Only load on our plugin pages
    //     $plugin_pages = [
    //         'settings_page_wpccm', 
    //         'settings_page_wpccm-scanner', 
    //         'settings_page_wpccm-management', 
    //         'settings_page_wpccm-deletion', 
    //         'settings_page_wpccm-history',
    //         'cookie-consent_page_wpccm-advanced-scanner'
    //     ];
        
    //     if (!in_array($hook, $plugin_pages)) {
    //         return;
    //     }
        
    //     // Enqueue jQuery first
    //     wp_enqueue_script('jquery');
        
    //     // Enqueue CSS for admin tables
    //     wp_enqueue_style('wpccm-admin', WPCCM_URL . 'assets/css/consent.css', [], WPCCM_VERSION);

    //     // Enqueue Chart.js for statistics
    //     wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        
    //     // Localize script to provide ajaxurl and nonce
    //     wp_localize_script('jquery', 'wpccm_ajax', [
    //         'ajaxurl' => admin_url('admin-ajax.php'),
    //         'nonce' => wp_create_nonce('wpccm_admin_nonce')
    //     ]);
        
    //     // Debug: Log the current hook
    //     error_log('WPCCM: Current hook: ' . $hook);
        
    //     // For advanced scanner page, log debug info
    //     if ($hook === 'cookie-consent_page_wpccm-advanced-scanner') {
    //         error_log('WPCCM: Advanced scanner page loaded');
    //     }
    // }
    
    public function settings_init() {
        register_setting('wpccm_group', 'wpccm_options', [$this, 'sanitize_options']);
        
        // Dashboard Connection Settings (separate options)
        register_setting('wpccm_dashboard_group', 'wpccm_dashboard_api_url');
        register_setting('wpccm_dashboard_group', 'wpccm_license_key');
        // register_setting('wpccm_dashboard_group', 'wpccm_website_id');
        
        
        // Advanced Scanner Settings (for script mappings)
        register_setting('wpccm_advanced_scanner_group', 'wpccm_script_handle_map');
        register_setting('wpccm_advanced_scanner_group', 'wpccm_script_handle_map_categories');
        
        // Add a custom handler for the advanced scanner form processing
        add_action('admin_init', [$this, 'handle_advanced_scanner_save']);
        
        // Tab 1: Activation & Dashboard Connection
        add_settings_section('wpccm_dashboard_connection', wpccm_translate_pair('Activation', 'אקטיבציה'), [$this, 'dashboard_connection_section_callback'], 'wpccm_general');
        // add_settings_field('dashboard_api_url', 'כתובת API של הדשבורד', [$this, 'field_dashboard_api_url'], 'wpccm_general', 'wpccm_dashboard_connection');
        add_settings_field('dashboard_license_key', wpccm_translate_pair('License Key', 'מפתח רישיון'), [$this, 'field_dashboard_license_key'], 'wpccm_general', 'wpccm_dashboard_connection');
        // add_settings_field('dashboard_test_connection', 'בדיקת חיבור', [$this, 'field_dashboard_test_connection'], 'wpccm_general', 'wpccm_dashboard_connection');
        
        // Tab 1: General Settings
        add_settings_section('wpccm_general', wpccm_translate_pair('General Settings', 'הגדרות כלליות'), null, 'wpccm_general');
        add_settings_field('banner_title', wpccm_text('title'), [$this, 'field_text'], 'wpccm_general', 'wpccm_general', ['key' => 'banner.title']);
        add_settings_field('banner_description', wpccm_text('description'), [$this, 'field_textarea'], 'wpccm_general', 'wpccm_general', ['key' => 'banner.description']);
        add_settings_field('banner_policy_url', wpccm_text('policy_url'), [$this, 'field_text'], 'wpccm_general', 'wpccm_general', ['key' => 'banner.policy_url']);
        
        // // Tab 2: Cookie Purging
        // add_settings_section('wpccm_purge', '', null, 'wpccm_purge');
        // add_settings_field('purge_cookies', '', [$this, 'field_cookies'], 'wpccm_purge', 'wpccm_purge');

        // // Tab 3: Script Mapping
        // add_settings_section('wpccm_mapping', wpccm_text('script_mapping'), null, 'wpccm_mapping');
        // add_settings_field('script_map', wpccm_text('handle_category_map'), [$this, 'field_map'], 'wpccm_mapping', 'wpccm_mapping');
    }
    
    public function sanitize_options($input) {
        error_log('WPCCM Debug: sanitize_options called with input: ' . print_r($input, true));
        
        if (!is_array($input)) return [];
        
        $out = wp_parse_args($input, WP_CCM_Consent::default_options());
        
        // Sanitize banner fields
        $out['banner']['title'] = sanitize_text_field($out['banner']['title']);
        $out['banner']['description'] = sanitize_textarea_field($out['banner']['description']);
        $out['banner']['policy_url'] = esc_url_raw($out['banner']['policy_url']);
        
        // Handle mapping input (now JSON)
        if (isset($out['map']) && is_string($out['map'])) {
            $decoded = json_decode($out['map'], true);
            if (is_array($decoded)) {
                $map = [];
                foreach ($decoded as $handle => $category) {
                    $handle = sanitize_text_field($handle);
                    $category = sanitize_text_field($category);
                    if ($handle && $category) {
                        $map[$handle] = $category;
                    }
                }
                $out['map'] = $map;
            } else {
                $out['map'] = [];
            }
        } elseif (!is_array($out['map'])) {
            $out['map'] = [];
        }

        // Cookie mapping input (JSON)
        if (isset($out['cookie_mapping']) && is_string($out['cookie_mapping'])) {
            $decoded = json_decode($out['cookie_mapping'], true);
            if (is_array($decoded)) {
                $cookie_map = [];
                foreach ($decoded as $handle => $cookies) {
                    $handle = sanitize_text_field($handle);
                    $cookies = sanitize_text_field($cookies);
                    if ($handle && $cookies) {
                        $cookie_map[$handle] = $cookies;
                    }
                }
                $out['cookie_mapping'] = $cookie_map;
            } else {
                $out['cookie_mapping'] = [];
            }
        } elseif (!is_array($out['cookie_mapping'])) {
            $out['cookie_mapping'] = [];
        }

        // Script sources mapping input (JSON)
        if (isset($out['script_sources']) && is_string($out['script_sources'])) {
            $decoded = json_decode($out['script_sources'], true);
            if (is_array($decoded)) {
                $sources_map = [];
                foreach ($decoded as $handle => $src) {
                    $handle = sanitize_text_field($handle);
                    $src = esc_url_raw($src);
                    if ($handle && $src) {
                        $sources_map[$handle] = $src;
                    }
                }
                $out['script_sources'] = $sources_map;
            } else {
                $out['script_sources'] = [];
            }
        } elseif (!is_array($out['script_sources'])) {
            $out['script_sources'] = [];
        }

        // purge cookies (now JSON with categories support)
        if (isset($out['purge']['cookies']) && is_string($out['purge']['cookies'])) {
            $decoded = json_decode($out['purge']['cookies'], true);
            if (is_array($decoded)) {
                $cookies = [];
                foreach ($decoded as $cookie_data) {
                    if (is_string($cookie_data)) {
                        // Old format - just cookie name
                        $cookie_name = sanitize_text_field($cookie_data);
                        if ($cookie_name) {
                            $cookies[] = [
                                'name' => $cookie_name,
                                'category' => ''
                            ];
                        }
                    } elseif (is_array($cookie_data) && isset($cookie_data['name'])) {
                        // New format - cookie with category
                        $cookie_name = sanitize_text_field($cookie_data['name']);
                        $category = isset($cookie_data['category']) ? sanitize_text_field($cookie_data['category']) : '';
                        if ($cookie_name) {
                            $cookies[] = [
                                'name' => $cookie_name,
                                'category' => $category
                            ];
                        }
                    }
                }
                $out['purge']['cookies'] = $cookies;
            } else {
                $out['purge']['cookies'] = [];
            }
        } elseif (!is_array($out['purge']['cookies'])) {
            $out['purge']['cookies'] = [];
        }

        // Design settings
        if (isset($out['design']) && is_array($out['design'])) {
            $design = $out['design'];
            
            // Sanitize banner position
            $design['banner_position'] = isset($design['banner_position']) ? sanitize_text_field($design['banner_position']) : 'top';
            if (!in_array($design['banner_position'], ['top', 'bottom'])) {
                $design['banner_position'] = 'top';
            }
            
            // Sanitize floating button position
            $design['floating_button_position'] = isset($design['floating_button_position']) ? sanitize_text_field($design['floating_button_position']) : 'bottom-right';
            if (!in_array($design['floating_button_position'], ['top-right', 'top-left', 'bottom-right', 'bottom-left'])) {
                $design['floating_button_position'] = 'bottom-right';
            }
            
            // Sanitize colors
            $design['background_color'] = isset($design['background_color']) ? sanitize_text_field($design['background_color']) : '#ffffff';
            $design['text_color'] = isset($design['text_color']) ? sanitize_text_field($design['text_color']) : '#000000';
            // Only allow black or white for text color
            if (!in_array($design['text_color'], ['#000000', '#ffffff'])) {
                $design['text_color'] = '#000000';
            }
            $design['accept_button_color'] = isset($design['accept_button_color']) ? sanitize_text_field($design['accept_button_color']) : '#0073aa';
            $design['reject_button_color'] = isset($design['reject_button_color']) ? sanitize_text_field($design['reject_button_color']) : '#6c757d';
            $design['settings_button_color'] = isset($design['settings_button_color']) ? sanitize_text_field($design['settings_button_color']) : '#28a745';
            
            // Sanitize size
            $design['size'] = isset($design['size']) ? sanitize_text_field($design['size']) : 'medium';
            if (!in_array($design['size'], ['small', 'medium', 'large'])) {
                $design['size'] = 'medium';
            }
            
            $out['design'] = $design;
        } else {
            // Set default design settings if not present
            $out['design'] = [
                'banner_position' => 'top',
                'floating_button_position' => 'bottom-right',
                'background_color' => '#ffffff',
                'text_color' => '#000000',
                'accept_button_color' => '#0073aa',
                'reject_button_color' => '#6c757d',
                'settings_button_color' => '#28a745',
                'size' => 'medium'
            ];
        }

        error_log('WPCCM Debug: sanitize_options returning: ' . print_r($out, true));
        return $out;
    }

    /**
     * Handle advanced scanner form save
     */
    public function handle_advanced_scanner_save() {
        // Check if this is a post request with our form data
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        
        // Check if it's our form by looking for the option_page
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'wpccm_advanced_scanner_group') {
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_advanced_scanner_group-options')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Process script handle mappings
        if (isset($_POST['wpccm_script_handle_map']) && isset($_POST['wpccm_script_handle_map_categories'])) {
            $script_mappings = array();
            $script_keys = $_POST['wpccm_script_handle_map'];
            $script_categories = $_POST['wpccm_script_handle_map_categories'];
            
            foreach ($script_keys as $old_key => $new_key) {
                $new_key = sanitize_text_field($new_key);
                $category = isset($script_categories[$old_key]) ? sanitize_text_field($script_categories[$old_key]) : '';
                
                if (!empty($new_key) && !empty($category)) {
                    $script_mappings[$new_key] = $category;
                }
            }
            
            // Update the options using the correct option names
            update_option('cc_script_handle_map', $script_mappings);
            
            // Keep domain mappings as is
            $domain_mappings = get_option('cc_script_domain_map', array());
            update_option('cc_script_domain_map', $domain_mappings);
            
            // Add success message
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>הגדרות סורק מתקדם נשמרו בהצלחה!</p></div>';
            });
            
            // Redirect to prevent resubmission
            $redirect_url = add_query_arg(['settings-updated' => 'true'], $_POST['_wp_http_referer']);
            wp_redirect($redirect_url);
            exit;
        }
    }

    // --- fields renderers ---
    private function opt($path, $default = '') {
        $opts = WP_CCM_Consent::get_options();
        // dot path
        $segments = explode('.', $path);
        $val = $opts;
        foreach ($segments as $seg) {
            if (isset($val[$seg])) { $val = $val[$seg]; } else { return $default; }
        }
        return $val;
    }

    public function field_text($args) {
        $key = $args['key'];
        $val = esc_attr($this->opt($key));
        echo '<input type="text" class="regular-text" name="wpccm_options['.esc_attr(str_replace('.', '][', $key)).']" value="'.$val.'" />';
    }

    public function field_textarea($args) {
        $key = $args['key'];
        $val = esc_textarea($this->opt($key));
        echo '<textarea rows="4" class="large-text" name="wpccm_options['.esc_attr(str_replace('.', '][', $key)).']">'.$val.'</textarea>';
    }

    public function field_number($args) {
        $key = $args['key'];
        $val = esc_attr($this->opt($key, 180));
        echo '<input type="number" min="1" name="wpccm_options['.esc_attr(str_replace('.', '][', $key)).']" value="'.$val.'" />';
    }



    private function get_related_cookies_for_handle($handle) {
        // Map common handles to their typical cookies
        $handle_cookies = [
            'google-analytics' => '_ga, _gid, _gat',
            'gtag' => '_ga, _gid, _gat_*',
            'facebook-pixel' => '_fbp, fr',
            'woocommerce' => 'woocommerce_*',
            'mailpoet' => 'mailpoet_*',
            'contact-form-7' => 'cf7_*',
            'hotjar' => '_hjid, _hjSessionUser',
            'mixpanel' => 'mp_*',
            'segment' => 'ajs_*',
            'hubspot' => '__hstc, hubspotutk',
            'linkedin' => 'li_sugr, lidc',
            'pinterest' => '_pinterest_*',
            'twitter' => 'personalization_id',
            'youtube' => 'VISITOR_INFO1_LIVE',
            'criteo' => 'cto_*',
            'doubleclick' => 'IDE, test_cookie',
            'adsense' => '__gads, __gpi'
        ];

        if (isset($handle_cookies[$handle])) {
            return $handle_cookies[$handle];
        }

        // Try to guess based on handle patterns
        if (strpos($handle, 'analytics') !== false || strpos($handle, 'ga') !== false) {
            return '_ga, _gid';
        }
        if (strpos($handle, 'facebook') !== false || strpos($handle, 'fb') !== false) {
            return '_fbp, fr';
        }
        if (strpos($handle, 'woo') !== false || strpos($handle, 'commerce') !== false) {
            return 'woocommerce_*';
        }
        if (strpos($handle, 'pixel') !== false) {
            return 'tracking cookies';
        }

        return wpccm_text('unknown_cookies');
    }

    private function get_saved_cookies_for_handle($handle) {
        $opts = WP_CCM_Consent::get_options();
        $cookie_mapping = isset($opts['cookie_mapping']) && is_array($opts['cookie_mapping']) ? $opts['cookie_mapping'] : [];
        return isset($cookie_mapping[$handle]) ? $cookie_mapping[$handle] : '';
    }

    private function get_saved_src_for_handle($handle) {
        $opts = WP_CCM_Consent::get_options();
        $script_sources = isset($opts['script_sources']) && is_array($opts['script_sources']) ? $opts['script_sources'] : [];
        return isset($script_sources[$handle]) ? $script_sources[$handle] : '';
    }

    private function render_category_select($selected = '') {
        $categories = [
            '' => wpccm_text('none'),
            'necessary' => wpccm_text('necessary'),
            'functional' => wpccm_text('functional'),
            'performance' => wpccm_text('performance'),
            'analytics' => wpccm_text('analytics'),
            'advertisement' => wpccm_text('advertisement'),
            'others' => wpccm_text('others')
        ];
        
        $html = '<select class="category-select">';
        foreach ($categories as $value => $label) {
            $sel = ($value === $selected) ? ' selected' : '';
            $html .= '<option value="'.esc_attr($value).'"'.$sel.'>'.esc_html($label).'</option>';
        }
        $html .= '</select>';
        return $html;
    }



    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php echo wpccm_text('sync_services'); ?></h1>
            
            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper wpccm-tabs">
                <a href="#general" class="nav-tab nav-tab-active" data-tab="general"><?php echo esc_html(wpccm_translate_pair('General Settings', 'הגדרות כלליות')); ?></a>
                <a href="#design" class="nav-tab" data-tab="design"><?php echo esc_html(wpccm_translate_pair('Design Settings', 'הגדרות עיצוב')); ?></a>
                <a href="#categoriess" class="nav-tab" data-tab="categoriess"><?php echo esc_html(wpccm_translate_pair('Categories', 'קטגוריות')); ?></a>
            </nav>
            
            <form method="post" action="options.php">

                <?php settings_fields('wpccm_group'); ?>
                <?php settings_fields('wpccm_dashboard_group'); ?>

                <!-- Tab Content -->
                <div id="general" class="wpccm-tab-content active">
                    
                    <?php do_settings_sections('wpccm_general'); ?>
                    
                    <!-- Auto sync controls -->
                    <div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #00a32a;">
                        <h3 style="margin: 0 0 10px 0; color: #1d2327;">⏰ <?php echo esc_html(wpccm_translate_pair('Auto-sync cookies & scripts', 'סינכרון אוטומטי של עוגיות וסקריפטים')); ?></h3>
                        <p style="margin: 0 0 15px 0; color: #50575e;"><?php echo esc_html(wpccm_translate_pair('The system scans and updates cookies and scripts in the background at the frequency you choose, keeping your lists up to date.', 'המערכת סורקת ומעדכנת עוגיות וסקריפטים באופן אוטומטי ברקע לפי התדירות שתבחר, כך שתמיד תהיה לך רשימה מעודכנת של כל הרכיבים באתר.')); ?></p>
                        
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <label for="wpccm-sync-interval" style="font-weight: 500; color: #1d2327;">⏱️ <?php echo esc_html(wpccm_translate_pair('Sync frequency:', 'תדירות סינכרון:')); ?></label>
                            <?php
                            $current_interval = (int) get_option('wpccm_sync_interval_minutes', 60);
                            $interval_options = [
                                1 => wpccm_translate_pair('Every minute', 'כל דקה'),
                                2 => wpccm_translate_pair('Every 2 minutes', 'כל 2 דקות'),
                                3 => wpccm_translate_pair('Every 3 minutes', 'כל 3 דקות'),
                                5 => wpccm_translate_pair('Every 5 minutes', 'כל 5 דקות'),
                                10 => wpccm_translate_pair('Every 10 minutes', 'כל 10 דקות'),
                                15 => wpccm_translate_pair('Every 15 minutes', 'כל 15 דקות'),
                                20 => wpccm_translate_pair('Every 20 minutes', 'כל 20 דקות'),
                                30 => wpccm_translate_pair('Every 30 minutes', 'כל 30 דקות'),
                                45 => wpccm_translate_pair('Every 45 minutes', 'כל 45 דקות'),
                                60 => wpccm_translate_pair('Every 60 minutes (1 hour)', 'כל 60 דקות (שעה)')
                            ];
                            ?>
                            <select id="wpccm-sync-interval" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 3px;">
                                <?php foreach ($interval_options as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current_interval, $value); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span id="wpccm-auto-sync-status" style="color: #50575e; font-size: 13px; font-weight: 500;"></span>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 10px; background: #fff; border-radius: 3px; font-size: 12px; color: #666;">
                            <strong>💡 <?php echo esc_html(wpccm_translate_pair('How it works:', 'איך זה עובד:')); ?></strong>
                            <ul style="margin: 5px 0 0 20px; padding: 0;">
                                <li><?php echo esc_html(wpccm_translate_pair('Sync runs automatically at the chosen frequency', 'הסינכרון רץ אוטומטית לפי התדירות שנבחרת')); ?></li>
                                <li><?php echo esc_html(wpccm_translate_pair('The system scans your site and finds new cookies & scripts', 'המערכת סורקת את האתר ומוצאת עוגיות וסקריפטים חדשים')); ?></li>
                                <li><?php echo wp_kses_post('<strong>' . esc_html(wpccm_translate_pair('New cookies', 'עוגיות חדשות')) . '</strong> ' . esc_html(wpccm_translate_pair('are added to the cookies mapping table automatically', 'נוספות אוטומטית לטבלה במיפוי העוגיות'))); ?></li>
                                <li><?php echo wp_kses_post('<strong>' . esc_html(wpccm_translate_pair('New scripts', 'סקריפטים חדשים')) . '</strong> ' . esc_html(wpccm_translate_pair('are added to the scripts sync table automatically', 'נוספים אוטומטית לטבלה בסינכרון הסקריפטים'))); ?></li>
                                </ul>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <button type="button" class="button-primary" id="save-general-settings"><?php echo esc_html(wpccm_translate_pair('Save general settings', 'שמור הגדרות כלליות')); ?></button>
                        <span id="general-settings-result" style="margin-left: 10px;"></span>
                    </p>
                </div>

                <!-- Tab Content -->
                <div id="design" class="wpccm-tab-content">
                    
                    <?php 
                    try {
                        $this->render_design_tab(); 
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading design settings:', 'שגיאה בטעינת הגדרות עיצוב:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading design settings:', 'שגיאה בטעינת הגדרות עיצוב:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                    ?>
                    <p class="submit">
                        <button type="button" class="button-primary" id="save-design-settings"><?php echo esc_html(wpccm_translate_pair('Save design settings', 'שמור הגדרות עיצוב')); ?></button>
                        <button type="button" class="button" id="reset-design-settings" style="margin-right: 10px;"><?php echo esc_html(wpccm_translate_pair('Default settings', 'הגדרות ברירת מחדל')); ?></button>
                        <span id="design-settings-result" style="margin-left: 10px;"></span>
                    </p>
                </div>
                
                <div id="categoriess" class="wpccm-tab-content">
                    
                    <?php 
                    
                    try {
                        
                        $this->render_categories_tab(); 
                        
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading categories:', 'שגיאה בטעינת קטגוריות:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading categories:', 'שגיאה בטעינת קטגוריות:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                    ?>
                    <p class="submit">
                        <input type="submit" name="save_categories_settings" class="button-primary" value="<?php echo esc_attr(wpccm_translate_pair('Save category settings', 'שמור הגדרות קטגוריות')); ?>" />
                    </p>
                </div>
                
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            //console.log('WPCCM: General settings page loaded');
            
            // // Update design preview from general settings on page load
            // setTimeout(function() {
            //     if (typeof updatePreviewFromGeneralSettings === 'function') {
            //         updatePreviewFromGeneralSettings();
            //     }
            // }, 500);

            $('.wpccm-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).data('tab');
                //console.log('WPCCM Debug: Tab clicked:', targetTab);
                
                // Update active tab
                $('.wpccm-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.wpccm-tab-content').removeClass('active');
                $('#' + targetTab).addClass('active');
                
                // Show corresponding submit button
                $('[id$="-submit"]').removeClass('active');
                $('#' + targetTab + '-submit').addClass('active');
                
                // // Update design preview if switching to design tab
                // if (targetTab === 'design') {
                //     // Wait a bit for the tab content to be visible
                //     setTimeout(function() {
                //         if (typeof updatePreviewFromGeneralSettings === 'function') {
                //             updatePreviewFromGeneralSettings();
                //         }
                //     }, 100);
                // }
            });
            
            // Initialize first tab as active on page load
            var firstTab = $('.wpccm-tabs .nav-tab').first();
            //console.log('WPCCM Debug: First tab element:', firstTab.length ? 'found' : 'not found');
            if (firstTab.length) {
                var firstTabId = firstTab.data('tab');
                //console.log('WPCCM Debug: Initializing first tab:', firstTabId);
                
                // Set first tab as active
                firstTab.addClass('nav-tab-active');
                //console.log('WPCCM Debug: Added nav-tab-active to first tab');
                
                // Show first tab content
                $('.wpccm-tab-content').removeClass('active');
                $('#' + firstTabId).addClass('active');
                //console.log('WPCCM Debug: Made tab content active:', '#' + firstTabId);
                
                // Show first tab submit button
                $('[id$="-submit"]').removeClass('active');
                $('#' + firstTabId + '-submit').addClass('active');
                //console.log('WPCCM Debug: Made submit button active:', '#' + firstTabId + '-submit');
                
            } else {
                //console.log('WPCCM Debug: No tabs found!');
            }
            
            // Check if save button exists
            if ($('#save-general-settings').length) {
                //console.log('WPCCM: Save general settings button found');
            } else {
                //console.log('WPCCM: Save general settings button not found');
            }
            
            // Auto sync functionality
            loadAutoSyncStatus();

            const autoSyncStrings = {
                rescheduling: '<?php echo esc_js(wpccm_translate_pair('Rescheduling sync to', 'מתזמן מחדש סינכרון ל-')); ?>',
                minutes: '<?php echo esc_js(wpccm_translate_pair('minutes', 'דקות')); ?>',
                errorPrefix: '<?php echo esc_js(wpccm_translate_pair('Error', 'שגיאה')); ?>',
                unknownError: '<?php echo esc_js(wpccm_translate_pair('Unknown error', 'שגיאה לא ידועה')); ?>',
                changeIntervalError: '<?php echo esc_js(wpccm_translate_pair('Error changing sync frequency', 'שגיאה בשינוי תדירות הסינכרון')); ?>',
                statusActive: '<?php echo esc_js(wpccm_translate_pair('🟢 Active - running on the front-end', '🟢 פעיל - רץ בפרונט')); ?>',
                statusNextRun: '<?php echo esc_js(wpccm_translate_pair('Next run:', 'הרצה הבאה:')); ?>',
                statusDescription: '<?php echo esc_js(wpccm_translate_pair('Auto-sync runs in visitors browsers every', 'הסינכרון רץ אוטומטית בדפדפן של המבקרים כל')); ?>'
            };
            const autoSyncLocale = '<?php echo wpccm_get_lang() === 'he' ? 'he-IL' : 'en-US'; ?>';
            const wpccmGeneralStrings = {
                missingActivation: '<?php echo esc_js('✗ ' . wpccm_translate_pair('Please fill all activation fields (API URL, license key, site ID)', 'אנא מלא את כל שדות האקטיבציה (כתובת API, מפתח רישיון, מזהה אתר)')); ?>',
                missingBanner: '<?php echo esc_js('✗ ' . wpccm_translate_pair('Please fill the banner title and description', 'אנא מלא את כותרת הבאנר ותיאור הבאנר')); ?>',
                savingButton: '<?php echo esc_js(wpccm_translate_pair('Saving...', 'שומר...')); ?>',
                savingSettings: '<?php echo esc_js(wpccm_translate_pair('Saving settings...', 'שומר הגדרות...')); ?>',
                errorSaving: '<?php echo esc_js('✗ ' . wpccm_translate_pair('Error saving settings', 'שגיאה בשמירת ההגדרות')); ?>',
                confirmResetDesign: '<?php echo esc_js(wpccm_translate_pair('Are you sure you want to reset all design settings to defaults?', 'האם אתה בטוח שברצונך לאפס את כל הגדרות העיצוב לברירת המחדל?')); ?>',
                defaultsRestored: '<?php echo esc_js('✓ ' . wpccm_translate_pair('Default settings restored', 'הוחזרו הגדרות ברירת המחדל')); ?>',
                saveGeneralLabel: '<?php echo esc_js(wpccm_translate_pair('Save general settings', 'שמור הגדרות כלליות')); ?>',
                savingDesign: '<?php echo esc_js(wpccm_translate_pair('Saving design settings...', 'שומר הגדרות עיצוב...')); ?>',
                errorSavingDesign: '<?php echo esc_js('✗ ' . wpccm_translate_pair('Error saving design settings', 'שגיאה בשמירת הגדרות העיצוב')); ?>',
                saveDesignLabel: '<?php echo esc_js(wpccm_translate_pair('Save design settings', 'שמור הגדרות עיצוב')); ?>'
            };
            
            // Sync interval dropdown change handler
            $('#wpccm-sync-interval').on('change', function() {
                const minutes = parseInt($(this).val());
                const dropdown = $(this);
                const originalSelection = dropdown.val();

                dropdown.prop('disabled', true);
                showAutoSyncMessage(autoSyncStrings.rescheduling + ' ' + minutes + ' ' + autoSyncStrings.minutes + '...', 'info');

                $.post(ajaxurl, {
                    action: 'wpccm_change_sync_interval',
                    minutes: minutes,
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                    }).done(function(response) {
                        if (response.success) {
                            showAutoSyncMessage(response.data.message, 'success');
                            // Refresh status to show new timing
                            setTimeout(function() {
                            loadAutoSyncStatus();
                        }, 1000);
                        } else {
                            // Revert selection on error
                            dropdown.val(originalSelection);
                            showAutoSyncMessage(autoSyncStrings.errorPrefix + ': ' + (response.data || autoSyncStrings.unknownError), 'error');
                        }
                    }).fail(function() {
                        // Revert selection on error
                        dropdown.val(originalSelection);
                        showAutoSyncMessage(autoSyncStrings.changeIntervalError, 'error');
                    }).always(function() {
                        dropdown.prop('disabled', false);
                    });
            });

            function loadAutoSyncStatus() {
                $.post(ajaxurl, {
                    action: 'wpccm_get_auto_sync_status'
                }).done(function(response) {
                    if (response.success) {
                        console.log('WPCCM Debug: Response data', response.data);
                        updateAutoSyncUI(response.data);
                    }
                });
            }
            
            function updateAutoSyncUI(data) {
                const status = $('#wpccm-auto-sync-status');
                const dropdown = $('#wpccm-sync-interval');

                // Update dropdown to show current interval
                if (data.interval_minutes) {
                    dropdown.val(data.interval_minutes);
                }

                // Show frontend auto-sync status with next run calculation
                let statusText = autoSyncStrings.statusActive;

                // Calculate next run time (approximately)
                const now = new Date();
                const nextRun = new Date(now.getTime() + (data.interval_minutes * 60 * 1000));
                const nextRunFormatted = nextRun.toLocaleString(autoSyncLocale, {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });

                statusText += ' - ' + autoSyncStrings.statusNextRun + ' ' + nextRunFormatted;
                statusText += '<br><small style="color: #666;">' + autoSyncStrings.statusDescription + ' ' + data.interval_minutes + ' ' + autoSyncStrings.minutes + '</small>';

                status.html(statusText);

                // Frontend auto-sync is now used - no countdown needed

            }
            
            function showAutoSyncMessage(message, type) {
                const messageDiv = $('<div class="notice notice-' + type + ' is-dismissible" style="margin: 10px 0;"><p>' + message + '</p></div>');
                $('#wpccm-auto-sync-status').parent().append(messageDiv);

                // Auto remove after 5 seconds
                setTimeout(function() {
                    messageDiv.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }

            // Frontend auto-sync is now used - no countdown timer needed
            
            // Save general settings via AJAX
            $('#save-general-settings').on('click', function() {
                //console.log('WPCCM: Save general settings button clicked');
                var $button = $(this);
                var $result = $('#general-settings-result');
                
                // Check if result element exists
                if (!$result.length) {
                    //console.log('WPCCM: Result element not found');
                    return;
                }
                
                // Collect form data
                
                var licenseKey = $('input[name="wpccm_license_key"]').val();
                console.log('WPCCM: License key:', licenseKey);
                
                var bannerTitle = $('input[name="wpccm_options[banner][title]"]').val();
                var bannerDescription = $('textarea[name="wpccm_options[banner][description]"]').val();
                var bannerPolicyUrl = $('input[name="wpccm_options[banner][policy_url]"]').val();
                
                //console.log('WPCCM: Form data collected:', {
                //     dashboardApiUrl: dashboardApiUrl,
                //     licenseKey: licenseKey ? licenseKey.substring(0, 8) + '...' : 'empty',
                //     websiteId: websiteId,
                //     masterCode: masterCode ? masterCode.substring(0, 3) + '...' : 'empty',
                //     bannerTitle: bannerTitle,
                //     bannerDescription: bannerDescription ? bannerDescription.substring(0, 50) + '...' : 'empty',
                //     bannerPolicyUrl: bannerPolicyUrl
                // });
                
                // Validate required fields
                if (!licenseKey) {
                    //console.log('WPCCM: Validation failed - missing activation fields');
                    $result.html('<span class="error">' + wpccmGeneralStrings.missingActivation + '</span>');
                    return;
                }
                
                if (!bannerTitle || !bannerDescription) {
                    //console.log('WPCCM: Validation failed - missing banner fields');
                    $result.html('<span class="error">' + wpccmGeneralStrings.missingBanner + '</span>');
                    return;
                }
                
                //console.log('WPCCM: Validation passed, proceeding with save');
                
                // Disable button and show loading
                $button.prop('disabled', true).text(wpccmGeneralStrings.savingButton);
                $result.html('<span class="loading">' + wpccmGeneralStrings.savingSettings + '</span>');
                
                // Prepare form data
                var formData = {
                    action: 'wpccm_save_general_settings',
                    nonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>',
                    license_key: licenseKey,
                    banner_title: bannerTitle,
                    banner_description: bannerDescription,
                    banner_policy_url: bannerPolicyUrl
                };
                
                //console.log('WPCCM: Form data prepared for AJAX:', formData);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        //console.log('WPCCM: AJAX response received:', response);
                        if (response.success) {
                            $result.html('<span class="success">✓ ' + response.data.message + '</span>');
                            // Reload page if plugin activation status changed
                            if (response.data.activated !== undefined) {
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            }
                            // // Update design preview if we're on design tab
                            // if ($('#design').hasClass('active') && typeof updatePreviewFromGeneralSettings === 'function') {
                            //     updatePreviewFromGeneralSettings();
                            // }
                            // Show saved data in console for debugging
                            if (response.data.saved_data) {
                                //console.log('WPCCM: Saved general settings:', response.data.saved_data);
                            }
                        } else {
                            $result.html('<span class="error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        //console.log('WPCCM: AJAX error:', {xhr: xhr, status: status, error: error});
                        $result.html('<span class="error">' + wpccmGeneralStrings.errorSaving + '</span>');
                    },
                    complete: function() {
                        // Re-enable button
                        $button.prop('disabled', false).text(wpccmGeneralStrings.saveGeneralLabel);
                    }
                });
            });
            
            // Reset design settings to defaults
            $('#reset-design-settings').on('click', function() {
                if (confirm(wpccmGeneralStrings.confirmResetDesign)) {
                    // Reset all form fields to default values
                    $('#banner_position_top').prop('checked', true);
                    $('#floating_button_position_bottom_right').prop('checked', true);
                    $('#background_color').val('#ffffff');
                    $('#text_color').val('#000000');
                    $('#accept_button_color').val('#0073aa');
                    $('#reject_button_color').val('#6c757d');
                    $('#settings_button_color').val('#28a745');
                    $('#data_deletion_button_color').val('#dc3545');
                    $('#size').val('medium');
                    
                    // Update preview immediately (will be called after function is defined)
                    setTimeout(function() {
                        if (typeof updatePreviewDefault === 'function') {
                    updatePreviewDefault();
                        }
                    }, 100);
                    
                    // Update visual state of position buttons
                    $(".wpccm-position-button").css({
                        "border-color": "#ddd",
                        "background": "#f9f9f9"
                    });
                    $(".wpccm-position-option div:last-child").css("color", "#666");
                    
                    // Highlight top position button (default) - now it's the second button
                    $("#banner_position_top").closest(".wpccm-position-option").find(".wpccm-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#banner_position_top").closest(".wpccm-position-option").find("div:last-child").css("color", "#0073aa");
                    
                    // Update visual state of floating position buttons
                    $(".wpccm-floating-position-button").css({
                        "border-color": "#ddd",
                        "background": "#f9f9f9"
                    });
                    $(".wpccm-floating-position-option div:last-child").css("color", "#666");
                    
                    // Highlight bottom-right position button (default)
                    $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
                    
                    // // Update preview from general settings as well
                    // if (typeof updatePreviewFromGeneralSettings === 'function') {
                    //     updatePreviewFromGeneralSettings();
                    // }
                    
                    // Show success message
                    $('#design-settings-result').html('<span class="success">' + wpccmGeneralStrings.defaultsRestored + '</span>');
                    
                    // Clear message after 3 seconds
                    setTimeout(function() {
                        $('#design-settings-result').html('');
                    }, 3000);
                }
            });
            
            // Save design settings via AJAX
            $('#save-design-settings').on('click', function() {
                var $button = $(this);
                var $result = $('#design-settings-result');
                
                // Collect form data
                var bannerPosition = $("input[name='wpccm_options[design][banner_position]']:checked").val();
                var floatingButtonPosition = $("input[name=\'wpccm_options[design][floating_button_position]\']:checked").val();
                var backgroundColor = $('#background_color').val();
                var textColor = $('#text_color').val();
                var acceptButtonColor = $('#accept_button_color').val();
                var rejectButtonColor = $('#reject_button_color').val();
                var settingsButtonColor = $('#settings_button_color').val();
                var size = $('#size').val();
                
                // Disable button and show loading
                $button.prop('disabled', true).text(wpccmGeneralStrings.savingButton);
                $result.html('<span class="loading">' + wpccmGeneralStrings.savingDesign + '</span>');
                
                // Prepare form data
                var formData = {
                    action: 'wpccm_save_design_settings',
                    nonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>',
                    banner_position: bannerPosition,
                    floating_button_position: floatingButtonPosition,
                    background_color: backgroundColor,
                    text_color: textColor,
                    accept_button_color: acceptButtonColor,
                    reject_button_color: rejectButtonColor,
                    settings_button_color: settingsButtonColor,
                    size: size
                };
                

                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $result.html('<span class="success">✓ ' + response.data.message + '</span>');
                            // // Update preview from general settings after saving design settings
                            // if (typeof updatePreviewFromGeneralSettings === 'function') {
                            //     updatePreviewFromGeneralSettings();
                            // }
                        } else {
                            $result.html('<span class="error">✗ ' + response.data + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<span class="error">' + wpccmGeneralStrings.errorSavingDesign + '</span>');
                    },
                    complete: function() {
                        // Re-enable button
                        $button.prop('disabled', false).text(wpccmGeneralStrings.saveDesignLabel);
                    }
                });
            });

            function updatePreviewDefault() {
                // console.log("WPCCM: updatePreview555555");
                // Get text color from the only color field that exists
                var textColor = $("#text_color").val() || '#000000';
                
                // Set colors based on theme (since individual color fields don't exist)
                var bgColor, acceptButtonColor, rejectButtonColor, settingsButtonColor, dataDeletionButtonColor;
                
                if (textColor === '#ffffff') {
                    // Dark theme - use predefined colors
                    bgColor = '#242424F2';
                    acceptButtonColor = '#ffffff';
                    rejectButtonColor = '#ffffff';
                    settingsButtonColor = '#ffffff';
                    dataDeletionButtonColor = '#ffffff';
                } else {
                    // Light theme - use predefined colors
                    bgColor = '#ffffff';
                    acceptButtonColor = '#0073aa';
                    rejectButtonColor = '#6c757d';
                    settingsButtonColor = '#28a745';
                    dataDeletionButtonColor = '#dc3545';
                }
                var bannerPosition = $("input[name=\'wpccm_options[design][banner_position]\']:checked").val();
                var floatingButtonPosition = $("input[name=\'wpccm_options[design][floating_button_position]\']:checked").val();
                var size = $("#size").val();
                
                // Update border radius based on banner position
                var bannerRadius = (bannerPosition === 'top') ? '0 0 20px 20px' : '20px 20px 0 0';
                var themeClass = (textColor === '#ffffff') ? 'dark-theme' : 'light-theme';
                
                // Update banner classes
                $("#wpccm-banner-preview").removeClass('dark-theme light-theme banner_top banner_bottom')
                                         .addClass(themeClass + ' banner_' + bannerPosition);
                
                // Apply theme-specific styling
                if (themeClass === 'dark-theme') {
                    // Dark theme styling
                    $("#wpccm-banner-preview").css({
                        "background-color": "#242424F2",
                        "color": "#fff",
                        "border-radius": bannerRadius
                    });
                    
                    // Dark theme - all buttons white
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion, #wpccm-banner-preview .wpccm-btn-reject, #wpccm-banner-preview .wpccm-btn-accept").css({
                        "color": "#fff",
                        "border-color": "#fff"
                    });
                    
                    // Dark theme - all icons white
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion svg path, #wpccm-banner-preview .wpccm-top-actions button svg path").attr("stroke", "#fff");
                    $("#wpccm-banner-preview .wpccm-btn-settings").css("border-color", "#fff");
                    $("#wpccm-banner-preview .wpccm-btn-settings svg path").attr("fill", "#fff");
                    
                    // Dark theme - logo purple
                    $("#wpccm-banner-preview .wpccm-top-text svg path").attr("fill", "#C79ADB");
                    
                    // Dark theme - text white
                    $("#wpccm-banner-preview .wpccm-top-text, #wpccm-banner-preview .wpccm-top-text a").css("color", "#fff");
                    
                } else {
                    // Light theme styling
                    $("#wpccm-banner-preview").css({
                        "background-color": bgColor,
                        "color": textColor,
                        "border-radius": bannerRadius
                    });
                    
                    // Light theme - original colors
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion").css({
                        "color": dataDeletionButtonColor,
                        "border-color": dataDeletionButtonColor
                    });
                    
                    $("#wpccm-banner-preview .wpccm-btn-reject").css({
                        "color": textColor,
                        "border-color": textColor
                    });
                    
                    $("#wpccm-banner-preview .wpccm-btn-accept").css({
                        "color": acceptButtonColor,
                        "border-color": acceptButtonColor
                    });
                    
                    $("#wpccm-banner-preview .wpccm-btn-settings").css("border-color", settingsButtonColor);
                    
                    // Light theme - original icon colors
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion svg path").attr("stroke", dataDeletionButtonColor);
                    $("#wpccm-banner-preview .wpccm-btn-settings svg path").attr("fill", settingsButtonColor);
                    
                    // Light theme - original logo color
                    $("#wpccm-banner-preview .wpccm-top-text svg path").attr("fill", "#33294D");
                    
                    // Light theme - original text color
                    $("#wpccm-banner-preview .wpccm-top-text, #wpccm-banner-preview .wpccm-top-text a").css("color", textColor);
                }
                
                // Update text color for title and description specifically
                $("#wpccm-banner-preview h4, #wpccm-banner-preview p").css("color", (themeClass === 'dark-theme') ? "#fff" : textColor);
                
                // Update size with actual visual changes
                var padding, fontSize, buttonPadding, logoSize, settingsBtnSize, iconSize;
                if (size === "small") {
                    padding = "8px";
                    fontSize = "12px";
                    buttonPadding = "6px 12px";
                    logoSize = "35px";
                    settingsBtnSize = "32px";
                    iconSize = "16px";
                } else if (size === "large") {
                    padding = "25px";
                    fontSize = "18px";
                    buttonPadding = "12px 24px";
                    logoSize = "55px";
                    settingsBtnSize = "48px";
                    iconSize = "24px";
                } else {
                    // medium
                    padding = "15px";
                    fontSize = "14px";
                    buttonPadding = "8px 16px";
                    logoSize = "45px";
                    settingsBtnSize = "40px";
                    iconSize = "20px";
                }
                
                // Update dynamic sizes
                $("#wpccm-banner-preview .wpccm-top-text svg").css({
                    "width": logoSize,
                    "height": logoSize
                });
                
                $("#wpccm-banner-preview .wpccm-btn-settings").css({
                    "width": settingsBtnSize,
                    "height": settingsBtnSize
                });
                
                $("#wpccm-banner-preview .wpccm-btn-data-deletion svg, #wpccm-banner-preview .wpccm-btn-settings svg").css({
                    "width": iconSize,
                    "height": iconSize
                });
                
                $("#wpccm-banner-preview").css({
                    "padding": padding
                });
                
                $("#wpccm-banner-preview h4").css({
                    "font-size": fontSize
                });
                
                $("#wpccm-banner-preview p").css({
                    "font-size": fontSize
                });
                
                $("#wpccm-banner-preview button:not(.wpccm-btn-settings)").css({
                    "padding": buttonPadding,
                    "font-size": fontSize
                });
                
                // Update banner position indicator
                $("#wpccm-banner-preview").attr("data-position", bannerPosition);
                
                // Update floating button position indicator
                $("#wpccm-banner-preview").attr("data-floating-position", floatingButtonPosition);
                
                // Update info text
                var positionText = bannerPosition === "top" ? "' . $js_position_top . '" : "' . $js_position_bottom . '";
                var floatingTextMap = {
                    "bottom-left": "' . $js_float_bl . '",
                    "bottom-right": "' . $js_float_br . '",
                    "top-right": "' . $js_float_tr . '",
                    "top-left": "' . $js_float_tl . '"
                };
                var sizeTextMap = {
                    "small": "' . $js_size_small . '",
                    "medium": "' . $js_size_medium . '",
                    "large": "' . $js_size_large . '"
                };
                $("#preview-position").text(positionText);
                $("#preview-floating-position").text(floatingTextMap[floatingButtonPosition] || floatingButtonPosition);
                $("#preview-size").text(sizeTextMap[size] || size);
                
                console.log("WPCCM: Preview updated - BG:", bgColor, "Text:", textColor, "Accept:", acceptButtonColor, "Reject:", rejectButtonColor, "Settings:", settingsButtonColor, "Size:", size, "Position:", bannerPosition);
                
                // Apply theme styling AFTER all other updates to ensure it's not overridden
                var themeClassFinal = (textColor === '#ffffff' || textColor === 'white' || textColor === '#fff' || textColor === '#FFFFFF') ? 'dark-theme' : 'light-theme';
                if (themeClassFinal === 'dark-theme') {
                    // Force dark theme styling with !important
                    $("#wpccm-banner-preview")[0].style.setProperty("background-color", "#242424F2", "important");
                    $("#wpccm-banner-preview")[0].style.setProperty("color", "#fff", "important");
                    
                    // Force dark theme button colors with !important
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion, #wpccm-banner-preview .wpccm-btn-reject, #wpccm-banner-preview .wpccm-btn-accept").each(function() {
                        this.style.setProperty("color", "#fff", "important");
                        this.style.setProperty("border-color", "#fff", "important");
                        this.style.setProperty("background-color", "transparent", "important");
                    });
                    
                    // Force dark theme settings button with !important
                    $("#wpccm-banner-preview .wpccm-btn-settings")[0].style.setProperty("border-color", "#fff", "important");
                    $("#wpccm-banner-preview .wpccm-btn-settings")[0].style.setProperty("background-color", "transparent", "important");
                } else {
                    // Force light theme styling - reset dark theme overrides with !important
                    $("#wpccm-banner-preview")[0].style.setProperty("background-color", bgColor, "important");
                    $("#wpccm-banner-preview")[0].style.setProperty("color", textColor, "important");
                    
                    // Force light theme button colors with !important
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion")[0].style.setProperty("color", dataDeletionButtonColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion")[0].style.setProperty("border-color", dataDeletionButtonColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-data-deletion")[0].style.setProperty("background-color", "transparent", "important");
                    
                    $("#wpccm-banner-preview .wpccm-btn-reject")[0].style.setProperty("color", textColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-reject")[0].style.setProperty("border-color", textColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-reject")[0].style.setProperty("background-color", "transparent", "important");
                    
                    $("#wpccm-banner-preview .wpccm-btn-accept")[0].style.setProperty("color", acceptButtonColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-accept")[0].style.setProperty("border-color", acceptButtonColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-accept")[0].style.setProperty("background-color", "transparent", "important");
                    
                    $("#wpccm-banner-preview .wpccm-btn-settings")[0].style.setProperty("border-color", settingsButtonColor, "important");
                    $("#wpccm-banner-preview .wpccm-btn-settings")[0].style.setProperty("background-color", "transparent", "important");
                }
            }
            
            // Additional event listeners for real-time updates (after function is defined)
            $("#text_color, #size").on("change input propertychange paste keyup mouseup", updatePreviewDefault);
            
            // Event listeners for banner position changes
            $("input[name='wpccm_options[design][banner_position]'], input[name='wpccm_options[design][floating_button_position]']").on("change", updatePreviewDefault);
            
        });
        </script>
        
        <style>
        .wpccm-tabs {
            margin-bottom: 20px;
        }
        
        .wpccm-tab-content {
            display: none;
        }
        
        .wpccm-tab-content.active {
            display: block;
        }
        
        .wpccm-tab-content h2 {
            margin-top: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        /* Submit buttons - only show active one */
        [id$="-submit"] {
            display: none;
        }
        
        [id$="-submit"].active {
            display: block;
        }
        
        /* General settings save button styles */
        #save-general-settings {
            margin-right: 10px;
        }
        
        #general-settings-result {
            font-weight: 500;
            vertical-align: middle;
        }
        
        #general-settings-result span {
            padding: 5px 10px;
            border-radius: 3px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        /* Design settings save button styles */
        #save-design-settings {
            margin-right: 10px;
        }
        
        #reset-design-settings {
            background-color: #f8f9fa;
            border-color: #6c757d;
            color: #6c757d;
        }
        
        #reset-design-settings:hover {
            background-color: #e9ecef;
            border-color: #5a6268;
            color: #5a6268;
        }
        
        #design-settings-result {
            font-weight: 500;
            vertical-align: middle;
        }
        
        #design-settings-result span {
            padding: 5px 10px;
            border-radius: 3px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        </style>
        <?php
    }

    public function render_page_sync() {
        ?>
        <div class="wrap">
            <h1><?php echo wpccm_text('sync_services'); ?></h1>
            
            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper wpccm-tabs">
                <a href="#cookies" class="nav-tab nav-tab-active" data-tab="cookies"><?php echo esc_html(wpccm_translate_pair('Cookies', 'עוגיות')); ?></a>
                <a href="#script-sync" class="nav-tab" data-tab="script-sync"><?php echo esc_html(wpccm_translate_pair('Scripts', 'סקריפטים')); ?></a>
                <a href="#forms-sync" class="nav-tab" data-tab="forms-sync"><?php echo wpccm_text('forms_sync'); ?></a>
            </nav>
            
            <form method="post" action="options.php">
                
                <div id="cookies" class="wpccm-tab-content active">
                    <?php 
                    try {
                        $this->render_purge_tab(); 
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading cookies:', 'שגיאה בטעינת עוגיות:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading cookies:', 'שגיאה בטעינת עוגיות:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                    ?>
                    <!-- Auto-save enabled - no manual save button needed -->
                </div>
                <!-- Script Sync Tab Content -->
                <div id="script-sync" class="wpccm-tab-content">
                    <?php 
                    try {
                        $this->render_script_sync_tab(); 
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading scripts sync:', 'שגיאה בטעינת סינכרון סקריפטים:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>' . esc_html(wpccm_translate_pair('Error loading scripts sync:', 'שגיאה בטעינת סינכרון סקריפטים:')) . ' ' . esc_html($e->getMessage()) . '</p></div>';
                    }
                    ?>
                </div>

                <div id="forms-sync" class="wpccm-tab-content">
                    <?php 
                    try {
                        $this->render_forms_sync_tab();
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת סינכרון טפסים: ' . $e->getMessage() . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת סינכרון טפסים: ' . $e->getMessage() . '</p></div>';
                    }
                    ?>
                </div>
                
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {

            $('.wpccm-tabs .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).data('tab');
                
                // Update active tab
                $('.wpccm-tabs .nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show target content
                $('.wpccm-tab-content').removeClass('active');
                $('#' + targetTab).addClass('active');
                
                // Show corresponding submit button
                $('[id$="-submit"]').removeClass('active');
                $('#' + targetTab + '-submit').addClass('active');
                
            });
            
            // Initialize first tab as active on page load
            var firstTab = $('.wpccm-tabs .nav-tab').first();
            //console.log('WPCCM Debug: First tab element:', firstTab.length ? 'found' : 'not found');
            if (firstTab.length) {
                var firstTabId = firstTab.data('tab');
                //console.log('WPCCM Debug: Initializing first tab:', firstTabId);
                
                // Set first tab as active
                firstTab.addClass('nav-tab-active');
                //console.log('WPCCM Debug: Added nav-tab-active to first tab');
                
                // Show first tab content
                $('.wpccm-tab-content').removeClass('active');
                $('#' + firstTabId).addClass('active');
                //console.log('WPCCM Debug: Made tab content active:', '#' + firstTabId);
                
                // Show first tab submit button
                $('[id$="-submit"]').removeClass('active');
                $('#' + firstTabId + '-submit').addClass('active');
                //console.log('WPCCM Debug: Made submit button active:', '#' + firstTabId + '-submit');
                
            }
            
        });
        </script>
        
        <style>
        .wpccm-tabs {
            margin-bottom: 20px;
        }
        
        .wpccm-tab-content {
            display: none;
        }
        
        .wpccm-tab-content.active {
            display: block;
        }
        
        .wpccm-tab-content h2 {
            margin-top: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }
        
        /* Submit buttons - only show active one */
        [id$="-submit"] {
            display: none;
        }
        
        [id$="-submit"].active {
            display: block;
        }
        
        /* General settings save button styles */
        #save-general-settings {
            margin-right: 10px;
        }
        
        #general-settings-result {
            font-weight: 500;
            vertical-align: middle;
        }
        
        #general-settings-result span {
            padding: 5px 10px;
            border-radius: 3px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        
        /* Design settings save button styles */
        #save-design-settings {
            margin-right: 10px;
        }
        
        #reset-design-settings {
            background-color: #f8f9fa;
            border-color: #6c757d;
            color: #6c757d;
        }
        
        #reset-design-settings:hover {
            background-color: #e9ecef;
            border-color: #5a6268;
            color: #5a6268;
        }
        
        #design-settings-result {
            font-weight: 500;
            vertical-align: middle;
        }
        
        #design-settings-result span {
            padding: 5px 10px;
            border-radius: 3px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        </style>
        <?php
    }
    

    
    private function render_category_row($category, $index) {

        $key = isset($category['key']) ? esc_attr($category['key']) : '';
        $name = isset($category['name']) ? esc_attr($category['name']) : '';
        $description = isset($category['description']) ? esc_attr($category['description']) : '';
        $required = isset($category['required']) ? $category['required'] : false;
        $enabled = isset($category['enabled']) ? $category['enabled'] : true;
        
        echo '<tr class="category-row">';
        
        // Key
        echo '<td>';
        echo '<input type="text" name="categories['.$index.'][key]" value="'.$key.'" class="category-key-input" placeholder="'.wpccm_text('key_placeholder').'" />';
        echo '</td>';
        
        // Name
        echo '<td>';
        echo '<input type="text" name="categories['.$index.'][name]" value="'.$name.'" class="category-name-input" placeholder="'.wpccm_text('name_placeholder').'" />';
        echo '</td>';
        
        // Description
        echo '<td>';
        echo '<textarea name="categories['.$index.'][description]" class="category-description-input" placeholder="'.wpccm_text('description_placeholder').'">'.$description.'</textarea>';
        echo '</td>';
        
        // Required
        echo '<td style="text-align: center;">';
        echo '<input type="checkbox" name="categories['.$index.'][required]" value="1" '.checked($required, true, false).' />';
        echo '</td>';
        
        // Enabled
        echo '<td style="text-align: center;">';
        echo '<input type="checkbox" name="categories['.$index.'][enabled]" value="1" '.checked($enabled, true, false).' />';
        echo '</td>';
        
        // Actions
        echo '<td>';
        echo '<button type="button" class="button remove-category">'.wpccm_text('delete_category').'</button>';
        echo '</td>';
        
        echo '</tr>';
    }
    

    private function render_design_tab() {
        $opts = WP_CCM_Consent::get_options();
        $design_settings = isset($opts['design']) ? $opts['design'] : [];
        
        // Get banner content from general settings
        $banner_title = isset($opts['banner']['title']) ? $opts['banner']['title'] : wpccm_text('we_use_cookies');
        $banner_description = isset($opts['banner']['description']) ? $opts['banner']['description'] : wpccm_text('cookie_description');
        $banner_policy_url = isset($opts['banner']['policy_url']) ? $opts['banner']['policy_url'] : '';
        
        // Default values
        $banner_position = isset($design_settings['banner_position']) ? $design_settings['banner_position'] : 'top';
        $floating_button_position = isset($design_settings['floating_button_position']) ? $design_settings['floating_button_position'] : 'bottom-right';
        $background_color = isset($design_settings['background_color']) ? $design_settings['background_color'] : '#ffffff';
        $text_color = isset($design_settings['text_color']) ? $design_settings['text_color'] : '#000000';
        $button_color = isset($design_settings['button_color']) ? $design_settings['button_color'] : '#0073aa';
        $size = isset($design_settings['size']) ? $design_settings['size'] : 'medium';
        
        echo '<div id="wpccm-design-settings">';
        
        // Main explanation
        echo '<div class="wpccm-explanation-box" style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">🎨 ' . esc_html(wpccm_translate_pair('Consent banner design settings', 'הגדרות עיצוב באנר הסכמה')) . '</h4>';
        echo '<p style="margin: 0; color: #555;">' . esc_html(wpccm_translate_pair('Customize the look and placement of the cookie consent banner', 'התאם את המראה והמיקום של באנר הסכמה לעוגיות')) . '</p>';
        echo '</div>';
        
        // Design settings form
        echo '<table class="form-table">';
        
        // Banner Position
        echo '<tr>';
        echo '<th scope="row"><label for="banner_position">' . esc_html(wpccm_translate_pair('Banner position', 'מיקום הבאנר')) . '</label></th>';
        echo '<td>';
        echo '<div style="display: flex; gap: 20px; align-items: center;">';
        
        // Bottom position button
        echo '<div class="wpccm-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][banner_position]" id="banner_position_bottom" value="bottom" ' . checked($banner_position, 'bottom', false) . ' style="display: none;" />';
        echo '<label for="banner_position_bottom" class="wpccm-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($banner_position === 'bottom' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($banner_position === 'bottom' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; top: 0; left: 0; right: 0; height: 50px; background: ' . esc_attr($background_color) . '; border-bottom: 1px solid #dee2e6;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 10px; width: 25px; height: 8px; background: #dc3545; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 47px; width: 25px; height: 8px; background: #6c757d; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; top: 60px; left: 85px; width: 25px; height: 8px; background: #28a745; border-radius: 4px;"></div>';
        echo '</label>';
        echo '<div style="margin-bottom: 8px; font-weight: 500; color: ' . ($banner_position === 'bottom' ? '#0073aa' : '#666') . ';">' . esc_html(wpccm_translate_pair('Bottom of page', 'בתחתית הדף')) . '</div>';
        echo '</div>';
        
        // Top position button
        echo '<div class="wpccm-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][banner_position]" id="banner_position_top" value="top" ' . checked($banner_position, 'top', false) . ' style="display: none;" />';
        echo '<label for="banner_position_top" class="wpccm-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($banner_position === 'top' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($banner_position === 'top' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; bottom: 0; left: 0; right: 0; height: 50px; background: ' . esc_attr($background_color) . '; border-top: 1px solid #dee2e6;"></div>';
        echo '<div style="position: absolute; bottom: 60px; left: 10px; width: 25px; height: 8px; background: #dc3545; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; bottom: 60px; left: 47px; width: 25px; height: 8px; background: #6c757d; border-radius: 4px;"></div>';
        echo '<div style="position: absolute; bottom: 60px; left: 85px; width: 25px; height: 8px; background: #28a745; border-radius: 4px;"></div>';
        echo '</label>';
        echo '<div style="margin-bottom: 8px; font-weight: 500; color: ' . ($banner_position === 'top' ? '#0073aa' : '#666') . ';">' . esc_html(wpccm_translate_pair('Top of page', 'בראש הדף')) . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '<p class="description">' . esc_html(wpccm_translate_pair('Click a square to choose the banner position', 'לחץ על הריבוע כדי לבחור את מיקום הבאנר')) . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Floating Button Position
        echo '<tr>';
        echo '<th scope="row"><label for="floating_button_position">' . esc_html(wpccm_translate_pair('Floating button position', 'מיקום כפתור צף')) . '</label></th>';
        echo '<td>';
        echo '<div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">';
        
        // Bottom-left position button
        echo '<div class="wpccm-floating-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][floating_button_position]" id="floating_button_position_bottom_left" value="bottom-left" ' . checked($floating_button_position, 'bottom-left', false) . ' style="display: none;" />';
        echo '<label for="floating_button_position_bottom_left" class="wpccm-floating-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($floating_button_position === 'bottom-left' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($floating_button_position === 'bottom-left' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; bottom: 15px; left: 15px; width: 12px; height: 12px; background: #28a745; border-radius: 50%;"></div>';
        echo '</label>';
        echo '<div style="margin-top: 8px; font-weight: 500; color: ' . ($floating_button_position === 'bottom-left' ? '#0073aa' : '#666') . '; font-size: 12px;">' . esc_html(wpccm_translate_pair('Bottom left', 'שמאל למטה')) . '</div>';
        echo '</div>';

        // Bottom-right position button
        echo '<div class="wpccm-floating-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][floating_button_position]" id="floating_button_position_bottom_right" value="bottom-right" ' . checked($floating_button_position, 'bottom-right', false) . ' style="display: none;" />';
        echo '<label for="floating_button_position_bottom_right" class="wpccm-floating-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($floating_button_position === 'bottom-right' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($floating_button_position === 'bottom-right' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; bottom: 15px; right: 15px; width: 12px; height: 12px; background: #28a745; border-radius: 50%;"></div>';
        echo '</label>';
        echo '<div style="margin-top: 8px; font-weight: 500; color: ' . ($floating_button_position === 'bottom-right' ? '#0073aa' : '#666') . '; font-size: 12px;">' . esc_html(wpccm_translate_pair('Bottom right', 'ימין למטה')) . '</div>';
        echo '</div>';
        
        // Top-right position button
        echo '<div class="wpccm-floating-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][floating_button_position]" id="floating_button_position_top_right" value="top-right" ' . checked($floating_button_position, 'top-right', false) . ' style="display: none;" />';
        echo '<label for="floating_button_position_top_right" class="wpccm-floating-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($floating_button_position === 'top-right' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($floating_button_position === 'top-right' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; top: 15px; right: 15px; width: 12px; height: 12px; background: #28a745; border-radius: 50%;"></div>';
        echo '</label>';
        echo '<div style="margin-top: 8px; font-weight: 500; color: ' . ($floating_button_position === 'top-right' ? '#0073aa' : '#666') . '; font-size: 12px;">' . esc_html(wpccm_translate_pair('Top right', 'ימין למעלה')) . '</div>';
        echo '</div>';
        
        // Top-left position button
        echo '<div class="wpccm-floating-position-option" style="text-align: center;">';
        echo '<input type="radio" name="wpccm_options[design][floating_button_position]" id="floating_button_position_top_left" value="top-left" ' . checked($floating_button_position, 'top-left', false) . ' style="display: none;" />';
        echo '<label for="floating_button_position_top_left" class="wpccm-floating-position-button" style="display: block; width: 120px; height: 80px; border: 3px solid ' . ($floating_button_position === 'top-left' ? '#0073aa' : '#ddd') . '; border-radius: 8px; cursor: pointer; background: ' . ($floating_button_position === 'top-left' ? '#e7f3ff' : '#f9f9f9') . '; transition: all 0.3s ease; position: relative; overflow: hidden;">';
        echo '<div style="position: absolute; top: 15px; left: 15px; width: 12px; height: 12px; background: #28a745; border-radius: 50%;"></div>';
        echo '</label>';
        echo '<div style="margin-top: 8px; font-weight: 500; color: ' . ($floating_button_position === 'top-left' ? '#0073aa' : '#666') . '; font-size: 12px;">' . esc_html(wpccm_translate_pair('Top left', 'שמאל למעלה')) . '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '<p class="description">' . esc_html(wpccm_translate_pair('Click a square to choose the floating button position', 'לחץ על הריבוע כדי לבחור את מיקום כפתור הצף')) . '</p>';
        echo '</td>';
        echo '</tr>';
        
        // Text Color (Black or White only)
        echo '<tr>';
        echo '<th scope="row"><label for="text_color">' . esc_html(wpccm_translate_pair('Theme', 'חבילת עיצוב')) . '</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][text_color]" id="text_color">';
        echo '<option value="#000000" ' . selected($text_color, '#000000', false) . '>' . esc_html(wpccm_translate_pair('Light theme', 'עיצוב לבן')) . '</option>';
        echo '<option value="#ffffff" ' . selected($text_color, '#ffffff', false) . '>' . esc_html(wpccm_translate_pair('Dark theme', 'עיצוב כהה')) . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html(wpccm_translate_pair('Choose a theme', 'בחר חבילת עיצוב')) . '</p>';
        echo '</td>';
        echo '</tr>';
        
        
        // Size
        echo '<tr>';
        echo '<th scope="row"><label for="size">' . esc_html(wpccm_translate_pair('Size', 'גודל')) . '</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][size]" id="size">';
        echo '<option value="small" ' . selected($size, 'small', false) . '>' . esc_html(wpccm_translate_pair('Small', 'קטן')) . '</option>';
        echo '<option value="medium" ' . selected($size, 'medium', false) . '>' . esc_html(wpccm_translate_pair('Medium', 'בינוני')) . '</option>';
        echo '<option value="large" ' . selected($size, 'large', false) . '>' . esc_html(wpccm_translate_pair('Large', 'גדול')) . '</option>';
        echo '</select>';
        echo '<p class="description">' . esc_html(wpccm_translate_pair('Choose banner size', 'בחר גודל לבאנר')) . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Preview section
        echo '<div class="wpccm-preview-section" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 4px;">';
        echo '<h3>' . esc_html(wpccm_translate_pair('Preview', 'תצוגה מקדימה')) . '</h3>';
        // Calculate initial size values
        $initial_padding = '15px';
        $initial_font_size = '14px';
        $initial_button_padding = '8px 16px';
        $fill_color = isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745';
        
        if ($size === 'small') {
            $initial_padding = '8px';
            $initial_font_size = '12px';
            $initial_button_padding = '6px 12px';
        } elseif ($size === 'large') {
            $initial_padding = '25px';
            $initial_font_size = '18px';
            $initial_button_padding = '12px 24px';
        }
        
        // Calculate dynamic sizes
        $logo_size = $size === 'small' ? '35px' : ($size === 'large' ? '55px' : '45px');
        $settings_btn_size = $size === 'small' ? '32px' : ($size === 'large' ? '48px' : '40px');
        $icon_size = $size === 'small' ? '16px' : ($size === 'large' ? '24px' : '20px');
        
        // Banner radius based on position
        $banner_radius = $banner_position === 'top' ? '0 0 20px 20px' : '20px 20px 0 0';
        $theme_class = $text_color === '#ffffff' ? 'dark-theme' : 'light-theme';
        
        echo '<div id="wpccm-banner-preview" class="wpccm-top-banner ' . $theme_class . ' banner_' . $banner_position . '" style="padding: 20px 62px; border: 2px solid #ddd; border-radius: ' . $banner_radius . '; margin: 10px 0; background: ' . esc_attr($background_color) . '; color: ' . esc_attr($text_color) . '; transition: all 0.3s ease; box-shadow: 0 2px 14px rgba(0,0,0,0.2); max-width: calc(100% - 110px);" data-position="' . esc_attr($banner_position) . '" data-floating-position="' . esc_attr($floating_button_position) . '">';
        echo '<div class="wpccm-top-content" style="max-width: 100%; margin: 0 auto; padding: 0; display: flex; flex-direction: row-reverse; align-items: center; justify-content: space-between; gap: 20px;">';
        
        // Left actions (buttons)
        echo '<div class="wpccm-left-actions" style="display: flex; align-items: center;">';
        echo '<div class="wpccm-top-actions" style="display: flex; gap: 10px; flex-wrap: wrap;">';
        
        // Data deletion button with dynamic icon
        echo '<button class="wpccm-btn-data-deletion" style="background-color: transparent; color: ' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '; border: 1px solid ' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '; padding: ' . $initial_button_padding . '; cursor: pointer; font-size: ' . $initial_font_size . '; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 5px; border-radius: 30px;">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" style="width: ' . $icon_size . '; height: ' . $icon_size . ';" viewBox="0 0 20 20" fill="none">';
        echo '<path d="M1 1L9 9.5M12.554 9.085C15.034 10.037 17.017 9.874 19 9.088C18.5 15.531 15.496 18.008 11.491 19C11.491 19 8.474 16.866 8.039 11.807C7.992 11.259 7.969 10.986 8.082 10.677C8.196 10.368 8.42 10.147 8.867 9.704C9.603 8.976 9.97 8.612 10.407 8.52C10.844 8.43 11.414 8.648 12.554 9.085Z" stroke="' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '" stroke-linecap="round" stroke-linejoin="round"/>';
        echo '<path d="M17.5 14.446C17.5 14.446 15 14.93 12.5 13" stroke="' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '" stroke-linecap="round" stroke-linejoin="round"/>';
        echo '<path d="M13.5 5.25C13.5 5.58152 13.6317 5.89946 13.8661 6.13388C14.1005 6.3683 14.4185 6.5 14.75 6.5C15.0815 6.5 15.3995 6.3683 15.6339 6.13388C15.8683 5.89946 16 5.58152 16 5.25C16 4.91848 15.8683 4.60054 15.6339 4.36612C15.3995 4.1317 15.0815 4 14.75 4C14.4185 4 14.1005 4.1317 13.8661 4.36612C13.6317 4.60054 13.5 4.91848 13.5 5.25Z" stroke="' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '"/>';
        echo '<path d="M11 2V2.1" stroke="' . esc_attr(isset($design_settings['data_deletion_button_color']) ? $design_settings['data_deletion_button_color'] : '#dc3545') . '" stroke-linecap="round" stroke-linejoin="round"/>';
        echo '</svg> ' . esc_html(wpccm_translate_pair('Clear history', 'ניקוי היסטוריה')) . '</button>';
        
        // Reject button
        echo '<button class="wpccm-btn-reject" style="background-color: transparent; color: ' . esc_attr($text_color) . '; border: 1px solid ' . esc_attr($text_color) . '; padding: ' . $initial_button_padding . '; font-size: ' . $initial_font_size . '; border-radius: 30px;">' . esc_html(wpccm_text('reject_non_essential')) . '</button>';
        
        // Accept button
        echo '<button class="wpccm-btn-accept" style="background-color: transparent; color: ' . esc_attr(isset($design_settings['accept_button_color']) ? $design_settings['accept_button_color'] : '#0073aa') . '; border: 1px solid ' . esc_attr(isset($design_settings['accept_button_color']) ? $design_settings['accept_button_color'] : '#0073aa') . '; padding: ' . $initial_button_padding . '; font-size: ' . $initial_font_size . '; border-radius: 30px;">' . esc_html(wpccm_text('accept_all')) . '</button>';
        
        echo '</div>';
        
        // Settings button with dynamic size
        echo '<button class="wpccm-btn-settings" style="border-color: ' . esc_attr(isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745') . '; width: ' . $settings_btn_size . '; height: ' . $settings_btn_size . '; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid; margin-right: 30px; background-color: transparent;">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" style="width: ' . $icon_size . '; height: ' . $icon_size . ';" viewBox="0 0 20 20" fill="none">';
        echo '<path d="M8.54149 7.47418C8.20711 7.6643 7.91364 7.91868 7.67796 8.22268C7.44229 8.52667 7.26908 8.87429 7.1683 9.2455C7.06752 9.61671 7.04116 10.0042 7.09074 10.3856C7.14032 10.7671 7.26485 11.1349 7.45718 11.4681C7.64951 11.8012 7.90583 12.093 8.21139 12.3266C8.51694 12.5603 8.86569 12.7312 9.23757 12.8295C9.60944 12.9278 9.99709 12.9516 10.3782 12.8995C10.7593 12.8474 11.1263 12.7204 11.4582 12.5259C12.1226 12.1363 12.606 11.4998 12.8029 10.7552C12.9997 10.0106 12.8941 9.21833 12.509 8.55133C12.1239 7.88432 11.4906 7.39672 10.7473 7.19492C10.004 6.99312 9.21104 7.09351 8.54149 7.47418ZM8.19566 11.0417C8.05671 10.8047 7.96601 10.5425 7.92879 10.2703C7.89157 9.99806 7.90856 9.72117 7.97879 9.45555C8.04901 9.18992 8.17109 8.94081 8.33798 8.72256C8.50487 8.50431 8.71329 8.32122 8.95123 8.18384C9.18917 8.04647 9.45193 7.95751 9.72439 7.92209C9.99685 7.88668 10.2736 7.90551 10.5388 7.9775C10.8039 8.04948 11.0522 8.17321 11.2694 8.34154C11.4865 8.50988 11.6682 8.71951 11.804 8.95835C12.0759 9.4366 12.1476 10.003 12.0035 10.5339C11.8593 11.0648 11.511 11.5172 11.0346 11.7923C10.5582 12.0673 9.99228 12.1428 9.46041 12.0022C8.92855 11.8616 8.47389 11.5163 8.19566 11.0417Z" fill="' . esc_attr(isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745') . '"/>';
        echo '<path d="M8.8834 2.08331C8.67444 2.08343 8.47314 2.16204 8.31941 2.30358C8.16569 2.44511 8.07074 2.63924 8.0534 2.84748L7.96423 3.91331C7.14681 4.18703 6.39292 4.62264 5.74756 5.19415L4.77923 4.73748C4.59014 4.64848 4.37451 4.63378 4.17509 4.69629C3.97567 4.7588 3.80701 4.89396 3.70256 5.07498L2.5859 7.00831C2.48127 7.1894 2.44856 7.4032 2.49426 7.60728C2.53995 7.81136 2.66071 7.9908 2.83256 8.10998L3.7109 8.71998C3.53895 9.56465 3.53895 10.4353 3.7109 11.28L2.83256 11.89C2.66071 12.0092 2.53995 12.1886 2.49426 12.3927C2.44856 12.5968 2.48127 12.8106 2.5859 12.9916L3.70256 14.925C3.80701 15.106 3.97567 15.2412 4.17509 15.3037C4.37451 15.3662 4.59014 15.3515 4.77923 15.2625L5.7484 14.8058C6.3935 15.3772 7.1471 15.8128 7.96423 16.0866L8.0534 17.1525C8.07074 17.3607 8.16569 17.5548 8.31941 17.6964C8.47314 17.8379 8.67444 17.9165 8.8834 17.9166H11.1167C11.3257 17.9165 11.527 17.8379 11.6807 17.6964C11.8344 17.5548 11.9294 17.3607 11.9467 17.1525L12.0359 16.0866C12.8533 15.8129 13.6072 15.3773 14.2526 14.8058L15.2209 15.2625C15.41 15.3515 15.6256 15.3662 15.825 15.3037C16.0245 15.2412 16.1931 15.106 16.2976 14.925L17.4142 12.9916C17.5189 12.8106 17.5516 12.5968 17.5059 12.3927C17.4602 12.1886 17.3394 12.0092 17.1676 11.89L16.2892 11.28C16.4612 10.4353 16.4612 9.56465 16.2892 8.71998L17.1676 8.10998C17.3394 7.9908 17.4602 7.81136 17.5059 7.60728C17.5516 7.4032 17.5189 7.1894 17.4142 7.00831L16.2976 5.07498C16.1931 4.89396 16.0245 4.7588 15.825 4.69629C15.6256 4.63378 15.41 4.64848 15.2209 4.73748L14.2517 5.19415C13.6066 4.62274 12.853 4.18713 12.0359 3.91331L11.9467 2.84748C11.9294 2.63924 11.8344 2.44511 11.6807 2.30358C11.527 2.16204 11.3257 2.08343 11.1167 2.08331H8.8834ZM8.8834 2.91665H11.1167L11.2526 4.54998L11.5301 4.62915C12.4152 4.88169 13.2242 5.34918 13.8851 5.98998L14.0926 6.18998L15.5759 5.49165L16.6926 7.42498L15.3467 8.35998L15.4167 8.63915C15.6392 9.53273 15.6392 10.4672 15.4167 11.3608L15.3467 11.64L16.6926 12.575L15.5759 14.5083L14.0926 13.8091L13.8851 14.0091C13.2243 14.6503 12.4153 15.118 11.5301 15.3708L11.2526 15.45L11.1167 17.0833H8.8834L8.74756 15.45L8.47006 15.3708C7.58489 15.1183 6.77588 14.6508 6.11506 14.01L5.90756 13.81L4.42423 14.5083L3.30756 12.575L4.6534 11.64L4.5834 11.3608C4.35889 10.4675 4.35889 9.53248 4.5834 8.63915L4.6534 8.35998L3.3084 7.42498L4.42506 5.49165L5.9084 6.19081L6.1159 5.99081C6.77663 5.34971 7.58564 4.88193 8.4709 4.62915L8.7484 4.54998L8.8834 2.91665Z" fill="' . esc_attr(isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745') . '"/>';
        echo '</svg></button>';
        echo '</div>';
        
        // Text content with logo - using proper font size and styling like the real banner
        echo '<span class="wpccm-top-text" style="flex: 1; font-size: 19px; font-weight: 400; color: ' . esc_attr($text_color) . '; line-height: 1.5; display: flex; align-items: center; flex-wrap: wrap;">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" style="width: ' . $logo_size . '; height: ' . $logo_size . '; flex-shrink: 0; margin-left: 27px;" viewBox="0 0 71 71" fill="none">';
        echo '<g clip-path="url(#clip0_123_23)">';
        echo '<path d="M21.627 47.9957C24.6078 47.9957 27.0242 45.1557 27.0242 41.6523C27.0242 38.149 24.6078 35.309 21.627 35.309C18.6462 35.309 16.2297 38.149 16.2297 41.6523C16.2297 45.1557 18.6462 47.9957 21.627 47.9957Z" fill="#33294D"/>';
        echo '<path d="M50.2095 47.9957C53.1903 47.9957 55.6067 45.1557 55.6067 41.6523C55.6067 38.149 53.1903 35.309 50.2095 35.309C47.2287 35.309 44.8123 38.149 44.8123 41.6523C44.8123 45.1557 47.2287 47.9957 50.2095 47.9957Z" fill="#33294D"/>';
        echo '<path d="M39.8331 45.4354C38.8069 44.4801 37.4005 43.9451 35.9182 43.9451C34.4359 43.9451 33.0296 44.4801 32.0033 45.4354C31.0531 46.3143 30.521 47.4607 30.521 48.6453C30.521 51.2438 32.9535 53.3455 35.9182 53.3455C38.8829 53.3455 41.3154 51.2438 41.3154 48.6453C41.3154 46.0468 40.7833 46.2761 39.8331 45.4354ZM35.9182 45.015C37.1345 45.015 38.2367 45.4736 38.9969 46.1614C38.2747 46.8875 37.1725 47.3843 35.9182 47.3843C34.6639 47.3843 33.5237 46.8875 32.8395 46.1614C33.5997 45.4354 34.7019 45.015 35.9182 45.015ZM35.9182 52.3902C34.5119 52.3902 33.2576 51.8552 32.3834 51.0145C32.8775 50.5177 34.1698 49.486 35.9182 50.5559C37.6286 49.486 38.9589 50.4795 39.453 51.0145C38.5788 51.8552 37.3245 52.3902 35.9182 52.3902Z" fill="#33294D" stroke="#33294D" stroke-miterlimit="10"/>';
        echo '<path d="M22.9572 30.303C23.2233 31.6404 21.931 32.9779 20.0686 33.3218C18.2441 33.6657 16.5338 32.8633 16.3057 31.564C16.0396 30.2266 17.3319 28.8891 19.1944 28.5452C21.0188 28.2013 22.7292 29.0037 22.9572 30.303Z" fill="#33294D"/>';
        echo '<path d="M48.917 30.303C48.651 31.6404 49.9433 32.9779 51.8057 33.3218C53.6301 33.6657 55.3405 32.8633 55.5685 31.564C55.8346 30.2266 54.5423 28.8891 52.6799 28.5452C50.8555 28.2013 49.1451 29.0037 48.917 30.303Z" fill="#33294D"/>';
        echo '<path d="M35.5001 1.64317C37.7046 1.64317 39.8331 1.83424 41.9235 2.25458C42.8357 4.70022 45.3443 8.82724 52.0718 9.43865C52.0718 9.43865 54.2383 14.4446 61.1939 14.4446C68.1495 14.4446 61.65 14.4446 61.878 14.4446C66.4391 20.2148 69.1757 27.5517 69.1757 35.5C69.1757 43.4483 69.1757 37.2578 69.0617 38.1367C69.4798 38.8245 69.4417 39.7417 68.8716 40.3913L68.7956 40.4677C66.4011 56.8229 52.4139 69.3568 35.5001 69.3568C18.5863 69.3568 4.40909 56.6701 2.16658 40.2002C1.67247 39.6652 1.52044 38.8628 1.8245 38.1749L1.93853 37.9074C1.86251 37.105 1.86251 36.3025 1.86251 35.5C1.8245 16.8138 16.9139 1.64317 35.5001 1.64317ZM35.5001 5.12227e-06C16.0397 5.12227e-06 0.190135 15.9349 0.190135 35.5C0.190135 55.0651 0.190135 36.9139 0.266152 37.6017C-0.151942 38.6717 -0.0379162 39.8945 0.608229 40.8498C1.86251 49.1039 5.96744 56.6701 12.2389 62.1728C18.6623 67.8665 26.9482 71 35.5381 71C44.128 71 52.2999 67.9047 58.7233 62.2874C64.9567 56.8229 69.0997 49.3332 70.43 41.1555C71.0761 40.162 71.2281 38.9392 70.8101 37.831C70.8481 37.0667 70.8861 36.3025 70.8861 35.5C70.8861 27.3988 68.2255 19.7562 63.2083 13.4128L62.6762 12.7632H61.84C61.65 12.8014 61.4219 12.8014 61.2319 12.8014C55.4926 12.8014 53.7062 8.94188 53.6302 8.78903L53.2501 7.87191L52.2619 7.79548C46.7126 7.29871 44.4701 4.16524 43.5199 1.68138L43.1778 0.802481L42.2656 0.611415C40.0611 0.191071 37.8186 -0.038208 35.5381 -0.038208L35.5001 5.12227e-06Z" fill="#33294D"/>';
        echo '</g>';
        echo '<defs><clipPath id="clip0_123_23"><rect width="71" height="71" fill="white"/></clipPath></defs>';
        echo '</svg>';
        echo '<span style="max-width: 797px;">' . esc_html($banner_description);
        if (!empty($banner_policy_url)) {
            echo ' <a href="' . esc_url($banner_policy_url) . '" target="_blank" style="font-size: 19px; font-weight: 400; color: ' . esc_attr($text_color) . '; margin-right: 5px; text-decoration: underline;">' . esc_html(wpccm_text('learn_more')) . '</a>';
        }
        echo '</span>';
        echo '</span>';

        echo '</div>';
        echo '</div>';
        echo '<div style="margin-top: 10px; font-size: 12px; color: #666;">';
        echo '<strong>' . esc_html(wpccm_translate_pair('Banner position:', 'מיקום באנר:')) . '</strong> <span id="preview-position">' . ($banner_position === 'top' ? esc_html(wpccm_translate_pair('Top of page', 'בראש הדף')) : esc_html(wpccm_translate_pair('Bottom of page', 'בתחתית הדף'))) . '</span> | ';
        echo '<strong>' . esc_html(wpccm_translate_pair('Floating button:', 'מיקום כפתור צף:')) . '</strong> <span id="preview-floating-position">' . esc_html($floating_button_position) . '</span> | ';
        echo '<strong>' . esc_html(wpccm_translate_pair('Size:', 'גודל:')) . '</strong> <span id="preview-size">' . esc_html($size) . '</span>';
        echo '</div>';
        echo '<p class="description">' . esc_html(wpccm_translate_pair('The preview updates live as you change the settings', 'התצוגה המקדימה מתעדכנת בזמן אמת כשאתה משנה את ההגדרות')) . '</p>';
        echo '<p class="description" style="margin-top: 10px; font-style: italic; color: #666;">💡 <strong>' . esc_html(wpccm_translate_pair('Tip:', 'טיפ:')) . '</strong> ' . esc_html(wpccm_translate_pair('The preview title and description come from the general settings. Update them in the “General Settings” tab.', 'הכותרת והתיאור בתצוגה המקדימה מגיעים מההגדרות הכלליות. שנה אותם בטאב \"הגדרות כלליות\" כדי לראות את השינויים כאן.')) . '</p>';
        echo '</div>';
        
        echo '</div>'; // Close main container
        
        // JavaScript for live preview
        $js_position_top = esc_js(wpccm_translate_pair('Top of page', 'בראש הדף'));
        $js_position_bottom = esc_js(wpccm_translate_pair('Bottom of page', 'בתחתית הדף'));
        $js_float_bl = esc_js(wpccm_translate_pair('Bottom left', 'שמאל למטה'));
        $js_float_br = esc_js(wpccm_translate_pair('Bottom right', 'ימין למטה'));
        $js_float_tr = esc_js(wpccm_translate_pair('Top right', 'ימין למעלה'));
        $js_float_tl = esc_js(wpccm_translate_pair('Top left', 'שמאל למעלה'));
        $js_size_small = esc_js(wpccm_translate_pair('Small', 'קטן'));
        $js_size_medium = esc_js(wpccm_translate_pair('Medium', 'בינוני'));
        $js_size_large = esc_js(wpccm_translate_pair('Large', 'גדול'));
        $js_cookie_category_saved = esc_js(wpccm_translate_pair('Cookie category updated successfully', 'קטגוריית העוגיה עודכנה ונשמרה בהצלחה'));
        $js_error_saving_category = esc_js(wpccm_translate_pair('Error saving category:', 'שגיאה בשמירת הקטגוריה:'));
        $js_server_error = esc_js(wpccm_translate_pair('Server connection error', 'שגיאה בחיבור לשרת'));
        $js_unknown_error = esc_js(wpccm_translate_pair('Unknown error', 'שגיאה לא ידועה'));

        echo '<script>
        jQuery(document).ready(function($) {
            // console.log("WPCCM: jQuery(document).ready555555");
            function updatePreview() {
                // console.log("WPCCM: updatePreview555555");
                var bgColor = $("#background_color").val();
                var textColor = $("#text_color").val();
                var acceptButtonColor = $("#accept_button_color").val();
                var rejectButtonColor = $("#reject_button_color").val();
                var settingsButtonColor = $("#settings_button_color").val();
                var bannerPosition = $("input[name=\'wpccm_options[design][banner_position]\']:checked").val();
                var floatingButtonPosition = $("#floating_button_position").val();
                var size = $("#size").val();
                
                // Update colors
                $("#wpccm-banner-preview").css({
                    "background-color": bgColor,
                    "color": textColor
                });
                
                // Update button colors
                //$("#wpccm-banner-preview .wpccm-btn-accept").css("background-color", acceptButtonColor); // Accept button
                $("#wpccm-banner-preview .wpccm-btn-reject").css("color", textColor).css("border-color", textColor); // Reject button
                //$("#wpccm-banner-preview .wpccm-btn-settings").css("background-color", settingsButtonColor); // Settings button
                
                // Update data deletion button color
                var dataDeletionButtonColor = $("#data_deletion_button_color").val();
                $("#wpccm-banner-preview .wpccm-btn-data-deletion").css("color", dataDeletionButtonColor).css("border-color", dataDeletionButtonColor);
                
                // Update size with actual visual changes
                var padding, fontSize, buttonPadding;
                if (size === "small") {
                    padding = "8px";
                    fontSize = "12px";
                    buttonPadding = "6px 12px";
                } else if (size === "large") {
                    padding = "25px";
                    fontSize = "18px";
                    buttonPadding = "12px 24px";
                } else {
                    // medium
                    padding = "15px";
                    fontSize = "14px";
                    buttonPadding = "8px 16px";
                }
                
                $("#wpccm-banner-preview").css({
                    "padding": padding
                });
                
                $("#wpccm-banner-preview h4").css({
                    "font-size": fontSize
                });
                
                $("#wpccm-banner-preview p").css({
                    "font-size": fontSize
                });
                
                // $("#wpccm-banner-preview button").css({
                //     "padding": buttonPadding,
                //     "font-size": fontSize
                // });
                
                // Update banner position indicator
                $("#wpccm-banner-preview").attr("data-position", bannerPosition);
                
                // Update floating button position indicator
                $("#wpccm-banner-preview").attr("data-floating-position", floatingButtonPosition);
                
                // Update info text
                var positionText = bannerPosition === "top" ? "' . $js_position_top . '" : "' . $js_position_bottom . '";
                var floatingTextMap = {
                    "bottom-left": "' . $js_float_bl . '",
                    "bottom-right": "' . $js_float_br . '",
                    "top-right": "' . $js_float_tr . '",
                    "top-left": "' . $js_float_tl . '"
                };
                var sizeTextMap = {
                    "small": "' . $js_size_small . '",
                    "medium": "' . $js_size_medium . '",
                    "large": "' . $js_size_large . '"
                };
                $("#preview-position").text(positionText);
                $("#preview-floating-position").text(floatingTextMap[floatingButtonPosition] || floatingButtonPosition);
                $("#preview-size").text(sizeTextMap[size] || size);
                
                console.log("WPCCM: Preview updated - BG:", bgColor, "Text:", textColor, "Accept:", acceptButtonColor, "Reject:", rejectButtonColor, "Settings:", settingsButtonColor, "Size:", size, "Position:", bannerPosition);
            }
            
            // Update preview on any change
            $("#background_color, #text_color, #accept_button_color, #reject_button_color, #settings_button_color, #data_deletion_button_color, #floating_button_position, #size").on("change input", updatePreview);
            
            // Handle banner position radio buttons
            $("input[name=\'wpccm_options[design][banner_position]\']").on("change", function() {
                updatePreview();
                // Update visual state of position buttons
                $(".wpccm-position-button").css({
                    "border-color": "#ddd",
                    "background": "#f9f9f9"
                });
                $(".wpccm-position-option div:last-child").css("color", "#666");
                
                var selectedPosition = $(this).val();
                if (selectedPosition === "top") {
                    $("#banner_position_top").closest(".wpccm-position-option").find(".wpccm-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#banner_position_top").closest(".wpccm-position-option").find("div:last-child").css("color", "#0073aa");
                } else if (selectedPosition === "bottom") {
                    $("#banner_position_bottom").closest(".wpccm-position-option").find(".wpccm-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#banner_position_bottom").closest(".wpccm-position-option").find("div:last-child").css("color", "#0073aa");
                }
            });
            
            // Handle floating button position radio buttons
            $("input[name=\'wpccm_options[design][floating_button_position]\']").on("change", function() {
                updatePreview();
                // Update visual state of floating position buttons
                $(".wpccm-floating-position-button").css({
                    "border-color": "#ddd",
                    "background": "#f9f9f9"
                });
                $(".wpccm-floating-position-option div:last-child").css("color", "#666");
                
                var selectedFloatingPosition = $(this).val();
                if (selectedFloatingPosition === "top-right") {
                    $("#floating_button_position_top_right").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#floating_button_position_top_right").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
                } else if (selectedFloatingPosition === "top-left") {
                    $("#floating_button_position_top_left").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#floating_button_position_top_left").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
                } else if (selectedFloatingPosition === "bottom-right") {
                    $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
                } else if (selectedFloatingPosition === "bottom-left") {
                    $("#floating_button_position_bottom_left").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                        "border-color": "#0073aa",
                        "background": "#e7f3ff"
                    });
                    $("#floating_button_position_bottom_left").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
                }
            });
            
            // // Update preview when general settings change (if we\'re on design tab)
            // function updatePreviewFromGeneralSettings() {
            //     // Get values from general settings fields
            //     var generalTitle = $("input[name=\'wpccm_options[banner][title]\']").val();
            //     var generalDescription = $("textarea[name=\'wpccm_options[banner][description]\']").val();
                
            //     // Update preview content if we have values
            //     if (generalTitle) {
            //         $("#wpccm-banner-preview h4").text(generalTitle);
            //     }
            //     if (generalDescription) {
            //         $("#wpccm-banner-preview p").text(generalDescription);
            //     }
            // }
            
            // // Listen for changes in general settings
            // $("input[name=\'wpccm_options[banner][title]\'], textarea[name=\'wpccm_options[banner][description]\']").on("input", function() {
            //     updatePreviewFromGeneralSettings();
            // });
            
            // Initial preview
            updatePreview();
            
            // Update visual state of position buttons on page load
            var initialBannerPosition = $("input[name=\'wpccm_options[design][banner_position]\']:checked").val();
            if (initialBannerPosition === "top") {
                $("#banner_position_top").closest(".wpccm-position-option").find(".wpccm-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#banner_position_top").closest(".wpccm-position-option").find("div:last-child").css("color", "#0073aa");
            } else if (initialBannerPosition === "bottom") {
                $("#banner_position_bottom").closest(".wpccm-position-option").find(".wpccm-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#banner_position_bottom").closest(".wpccm-position-option").find("div:last-child").css("color", "#0073aa");
            }
            
            // Update visual state of floating position buttons on page load
            var initialFloatingPosition = $("input[name=\'wpccm_options[design][floating_button_position]\']:checked").val();
            if (initialFloatingPosition === "top-right") {
                $("#floating_button_position_top_right").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#floating_button_position_top_right").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
            } else if (initialFloatingPosition === "top-left") {
                $("#floating_button_position_top_left").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#floating_button_position_top_left").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
            } else if (initialFloatingPosition === "bottom-right") {
                $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#floating_button_position_bottom_right").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
            } else if (initialFloatingPosition === "bottom-left") {
                $("#floating_button_position_bottom_left").closest(".wpccm-floating-position-option").find(".wpccm-floating-position-button").css({
                    "border-color": "#0073aa",
                    "background": "#e7f3ff"
                });
                $("#floating_button_position_bottom_left").closest(".wpccm-floating-position-option").find("div:last-child").css("color", "#0073aa");
            }
            
            // updatePreviewFromGeneralSettings();
        });
        </script>';
    }

    private function render_purge_tab() {
        
        echo '<script>
        window.WPCCM_TABLE = {
            ajaxUrl: "' . admin_url('admin-ajax.php') . '",
            nonce: "' . wp_create_nonce('wpccm_cookie_scanner') . '",
            texts: {
                sync_with_current_cookies: "' . wpccm_text('sync_with_current_cookies') . '",
                add_cookie: "' . wpccm_text('add_cookie') . '",
                cookie_name: "' . wpccm_text('cookie_name') . '",
                actions: "' . wpccm_text('actions') . '",
                remove: "' . wpccm_text('remove') . '",
                enter_cookie_name: "' . wpccm_text('enter_cookie_name') . '",
                loading: "' . wpccm_text('loading') . '",
                non_essential_cookies_found: "' . wpccm_text('non_essential_cookies_found') . '",
                no_non_essential_cookies: "' . wpccm_text('no_non_essential_cookies') . '",
                clear_all_cookies: "' . wpccm_text('clear_all_cookies') . '",
                confirm_clear_all_cookies: "' . wpccm_text('confirm_clear_all_cookies') . '",
                all_items_cleared: "' . wpccm_text('all_items_cleared') . '",
                none: "' . wpccm_text('none') . '",
                necessary: "' . wpccm_text('necessary') . '",
                functional: "' . wpccm_text('functional') . '",
                performance: "' . wpccm_text('performance') . '",
                analytics: "' . wpccm_text('analytics') . '",
                advertisement: "' . wpccm_text('advertisement') . '",
                others: "' . wpccm_text('others') . '",
                no_related_cookies: "' . wpccm_text('no_related_cookies') . '",
                cookies_input_helper_text: "' . wpccm_text('cookies_input_helper_text') . '",
                scanning_site_cookies: "' . wpccm_text('scanning_site_cookies') . '",
                site_cookies_found: "' . wpccm_text('site_cookies_found') . '",
                cookies_added_to_table: "' . wpccm_text('cookies_added_to_table') . '",
                cookies_added_to_table_admin: "' . wpccm_text('cookies_added_to_table_admin') . '",
                error_with_message: "' . wpccm_text('error_with_message') . '",
                error_saving_cookies: "' . wpccm_text('error_saving_cookies') . '",
                error_accessing_site_using_admin: "' . wpccm_text('error_accessing_site_using_admin') . '",
                unknown_error: "' . wpccm_text('unknown_error') . '"
            }
        };
        //console.log("WPCCM Debug: WPCCM_TABLE initialized BEFORE script load:", window.WPCCM_TABLE);
        </script>';
        
        // THEN load the script
        echo '<script src="' . WPCCM_URL . 'assets/js/table-manager.js"></script>';
        
        // THEN initialize
        echo '<script>
        jQuery(document).ready(function($) {
            //console.log("WPCCM Debug: jQuery ready in render_purge_tab");
            //console.log("WPCCM Debug: WPCCM_TABLE available:", typeof window.WPCCM_TABLE !== "undefined");
            
            // Initialize table manager
            if (typeof initTableManager === "function") {
                //console.log("WPCCM Debug: initTableManager found, initializing");
                initTableManager();
            } else {
                //console.log("WPCCM Debug: initTableManager not found");
            }
        });
        </script>';
        
        // Add CSS styles for the table
        $this->add_cookie_table_styles();
        
        // Get cookies from new database table only
        $cookies = wpccm_get_cookies_from_db();
        
        echo '<div id="wpccm-cookie-purge-table">';
        
        // Main explanation
        echo '<div class="wpccm-explanation-box" style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">ℹ️ '.wpccm_text('what_are_purge_cookies', 'What are purge cookies?').'</h4>';
        echo '<p style="margin: 0; color: #555;">'.wpccm_text('cookie_purge_explanation').'</p>';
        echo '</div>';
        
        // Buttons with tooltips
        echo '<div style="position: relative; margin-bottom: 15px;">';
        echo '<button type="button" class="button button-primary" id="wpccm-sync-current-cookies-btn" title="'.esc_attr(wpccm_text('sync_explanation')).'">🔄 ' . esc_html(wpccm_translate_pair('Sync cookies', 'סנכרן עוגיות')) . '</button>';
        echo '<span id="wpccm-sync-result"></span>';
        echo '<button type="button" class="button" id="wpccm-sync-categories-btn" style="margin-left: 10px; background: #00a32a; color: white; display: none;" title="' . esc_attr(wpccm_translate_pair('Sync categories from mapping table', 'סנכרן קטגוריות מטבלת המיפוי')) . '">' . esc_html(wpccm_translate_pair('Sync categories', 'סנכרן קטגוריות')) . '</button>';
        echo '<button type="button" class="button" id="wpccm-add-cookie" style="margin-left: 10px; display: none;" title="'.wpccm_text('add_cookie_manually', 'Add cookie manually').'">'.wpccm_text('add_cookie').'</button>';
        echo '<button type="button" class="button button-secondary" id="wpccm-clear-all-cookies" style="margin-left: 10px; color: #d63384; display: none;" title="'.esc_attr(wpccm_text('confirm_clear_all_cookies')).'">'.wpccm_text('clear_all_cookies').'</button>';
        echo '</div>'; // Close buttons div
        
        
        echo '<div id="wpccm-cookie-suggestions-inline" style="margin-top: 15px;"></div>';
        
        echo '<div class="wpccm-table-container" style="margin-top: 15px; max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        echo '<table class="widefat fixed striped" id="wpccm-cookies-table" style="margin: 0; border: none;">';
        echo '<thead><tr>';
        echo '<th>'.wpccm_text('cookie_name').'</th>';
        echo '<th>'.wpccm_text('cookie_value', 'Value').'</th>';
        echo '<th>'.wpccm_text('category').'</th>';
        echo '<th style="width: 80px;">'.wpccm_text('actions').'</th>';
        echo '</tr></thead><tbody>';
        
        // If no cookies, show message inside table
        if (empty($cookies)) {
            echo '<tr>';
            echo '<td colspan="4" style="text-align: center; padding: 40px 20px; background: #f9f9f9;">';
            echo '<div style="font-size: 48px; margin-bottom: 15px;">🍪</div>';
            echo '<h3 style="margin: 0 0 10px 0; color: #0073aa;">' . esc_html(wpccm_text('no_cookies_found', wpccm_translate_pair('No cookies recorded yet', 'אין עוגיות רשומות במערכת'))) . '</h3>';
            echo '<p style="margin: 0 0 15px 0; color: #555;">' . esc_html(wpccm_translate_pair('The scan runs in the background and results will appear soon.', 'הסריקה עובדת ברקע והתוצאות יופיעו בקרוב')) . '</p>';
            echo '<p style="margin: 0; color: #0073aa; font-weight: 600;">💡 ' . esc_html(wpccm_translate_pair('Click “Sync cookies” to scan cookies from the site', 'לחץ על \"🔄 סנכרן עוגיות\" כדי לסרוק עוגיות מהאתר')) . '</p>';
            echo '</td>';
            echo '</tr>';
        }
        
        foreach ($cookies as $cookie_data) {
            // Support both old and new format
            if (is_string($cookie_data)) {
                $cookie_name = $cookie_data;
                $category = '';
            } else {
                $cookie_name = isset($cookie_data['name']) ? $cookie_data['name'] : '';
                $category = isset($cookie_data['category']) ? $cookie_data['category'] : '';
            }
            
            // Get cookie value from database
            $cookie_value = isset($cookie_data['value']) ? $cookie_data['value'] : '';
            // Truncate long values for display
            if (strlen($cookie_value) > 50) {
                $cookie_value = substr($cookie_value, 0, 50) . '...';
            }
            
            // Get category display name
            $category_display = $this->get_category_display_name($category);
            
            echo '<tr>';
            echo '<td><strong>'.esc_html($cookie_name).'</strong>';
            // Hidden inputs to maintain data for JavaScript functions
            echo '<input type="hidden" class="cookie-input" value="'.esc_attr($cookie_name).'" />';
            echo '</td>';
            echo '<td><code>'.esc_html($cookie_value ?: 'N/A').'</code></td>';
            // Get category data for styling
            $category_data = wpccm_get_category_by_key($category);
            $color = $category_data ? $category_data['color'] : '#666666';
            $icon = $category_data && !empty($category_data['icon']) ? $category_data['icon'] . ' ' : '';
            
            echo '<td><span class="category-badge category-'.esc_attr($category).'" style="background: '.$color.'; color: white; padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">'.$icon.esc_html($category_display).'</span>';
            // Hidden select to maintain data for JavaScript functions
            echo '<select class="category-select" style="display: none;">';
            echo '<option value="'.esc_attr($category).'" selected>'.esc_html($category_display).'</option>';
            echo '</select>';
            echo '</td>';
            echo '<td>';
            echo '<button type="button" class="button button-small edit-category-btn" data-cookie="'.esc_attr($cookie_name).'" data-category="'.esc_attr($category).'" title="ערוך קטגוריה">';
            echo '<span class="dashicons dashicons-edit" style="font-size: 14px; line-height: 1;"></span>';
            echo '</button>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>'; // Close table container
        echo '</div>'; // Close main container
        
        // Hidden input to store the actual data
        $encoded_cookies = json_encode($cookies);
        echo '<input type="hidden" name="wpccm_options[purge][cookies]" id="wpccm-cookies-data" value="'.esc_attr($encoded_cookies).'" />';
        
        // Add category edit modal
        $this->add_category_edit_modal();
        
        // Add sync history table
        $this->render_sync_history_table();
    }

    /**
     * Get category display name in Hebrew
     */
    private function get_category_display_name($category_key) {
        $category = wpccm_get_category_by_key($category_key);
        
        if ($category) {
            return $category['display_name'];
        }
        
        // Fallback for backward compatibility
        $names = [
            'necessary' => wpccm_text('necessary'),
            'functional' => wpccm_text('functional'),
            'performance' => wpccm_text('performance'),
            'analytics' => wpccm_text('analytics'),
            'advertisement' => wpccm_text('advertisement'),
            'others' => wpccm_text('others')
        ];
        
        return isset($names[$category_key]) ? $names[$category_key] : ucfirst($category_key);
    }

    /**
     * Add CSS for cookie table styling
     */
    private function add_cookie_table_styles() {
        echo '<style>
        .category-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .category-necessary { background: #d4edda; color: #155724; }
        .category-functional { background: #cce7ff; color: #004085; }
        .category-performance { background: #fff3cd; color: #856404; }
        .category-analytics { background: #e2e3e5; color: #383d41; }
        .category-advertisement { background: #f8d7da; color: #721c24; }
        .category-others { background: #e7f1ff; color: #0c5460; }
        
        #wpccm-cookies-table td {
            vertical-align: middle;
        }
        #wpccm-cookies-table code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            color: #6c757d;
        }
        .edit-category-btn {
            padding: 4px 6px !important;
            min-height: auto !important;
            height: auto !important;
        }
        .edit-category-btn .dashicons {
            width: 14px;
            height: 14px;
        }
        </style>';
    }

    /**
     * Add category edit modal
     */
    private function add_category_edit_modal() {
        $edit_title = wpccm_translate_pair('Edit category', 'ערוך קטגוריה');
        $cookie_label = wpccm_translate_pair('Cookie name:', 'שם העוגיה:');
        $category_label = wpccm_translate_pair('Category:', 'קטגוריה:');
        $save_label = wpccm_translate_pair('Save', 'שמור');
        $cancel_label = wpccm_translate_pair('Cancel', 'ביטול');

        echo '
        <!-- Category Edit Modal -->
        <div id="category-edit-modal" style="display: none;">
            <div class="category-edit-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
                <div class="category-edit-dialog" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 400px;">
                    <h3 style="margin-top: 0;">' . esc_html($edit_title) . '</h3>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">' . esc_html($cookie_label) . '</label>
                        <span id="edit-cookie-name" style="font-family: monospace; background: #f8f9fa; padding: 4px 8px; border-radius: 4px;"></span>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label for="edit-category-select" style="display: block; margin-bottom: 5px; font-weight: 600;">' . esc_html($category_label) . '</label>
                        <select id="edit-category-select" style="width: 100%; padding: 8px;">';
        
        $categories = wpccm_get_categories();
        foreach ($categories as $category) {
            $icon = !empty($category['icon']) ? $category['icon'] . ' ' : '';
            echo '<option value="' . esc_attr($category['category_key']) . '">' . $icon . esc_html($category['display_name']) . '</option>';
        }
        
        echo '</select>
                    </div>
                    <div style="text-align: left;">
                        <button type="button" id="save-category-btn" class="button button-primary" style="margin-left: 10px;">' . esc_html($save_label) . '</button>
                        <button type="button" id="cancel-category-btn" class="button">' . esc_html($cancel_label) . '</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Open modal when edit button clicked
            $(document).on("click", ".edit-category-btn", function() {
                var cookieName = $(this).data("cookie");
                var currentCategory = $(this).data("category");
                
                $("#edit-cookie-name").text(cookieName);
                $("#edit-category-select").val(currentCategory);
                $("#category-edit-modal").data("cookie-name", cookieName).show();
            });

            // Close modal when cancel clicked
            $("#cancel-category-btn, .category-edit-overlay").on("click", function(e) {
                if (e.target === this) {
                    $("#category-edit-modal").hide();
                }
            });

            // Save category changes (old modal)
            $("#save-category-btn").on("click", function() {
            console.log("Save category button clicked");
                var cookieName = $("#category-edit-modal").data("cookie-name");
                var newCategory = $("#edit-category-select").val();
                var newCategoryDisplay = $("#edit-category-select option:selected").text();
                
                // Find the row and update it
                $(".edit-category-btn[data-cookie=\'" + cookieName + "\']").each(function() {
                    var $row = $(this).closest("tr");
                    var $badge = $row.find(".category-badge");
                    var $hiddenSelect = $row.find(".category-select");
                    
                    // Update visual badge
                    $badge.removeClass(function(index, className) {
                        return (className.match(/category-\\S+/g) || []).join(" ");
                    });
                    $badge.addClass("category-" + newCategory);
                    $badge.text(newCategoryDisplay);
                    
                    // Update hidden select
                    $hiddenSelect.empty().append("<option value=\'" + newCategory + "\' selected>" + newCategoryDisplay + "</option>");
                    
                    // Update button data
                    $(this).data("category", newCategory);
                });
                
                // Update the data and trigger save
                if (typeof updateCookieData === "function") {
                    updateCookieData();
                }
                
                // Save to database via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "wpccm_update_cookie_category",
                        cookie_name: cookieName,
                        category: newCategory,
                        _wpnonce: "' . wp_create_nonce('wpccm_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            $("<div class=\"notice notice-success is-dismissible\" style=\"margin: 10px 0;\"><p>' . $js_cookie_category_saved . '</p></div>")
                                .prependTo("#wpccm-cookie-purge-table")
                                .delay(3000)
                                .fadeOut();
                        } else {
                            $("<div class=\"notice notice-error is-dismissible\" style=\"margin: 10px 0;\"><p>' . $js_error_saving_category . ' " + (response.data || "' . $js_unknown_error . '") + "</p></div>")
                                .prependTo("#wpccm-cookie-purge-table")
                                .delay(5000)
                                .fadeOut();
                        }
                    },
                    error: function() {
                        $("<div class=\"notice notice-error is-dismissible\" style=\"margin: 10px 0;\"><p>' . $js_server_error . '</p></div>")
                            .prependTo("#wpccm-cookie-purge-table")
                            .delay(5000)
                            .fadeOut();
                    }
                });
                
                $("#category-edit-modal").hide();
            });
        });
        </script>';
    }

    private function render_categories_tab() {
        // Get categories from new database table
        $categories = wpccm_get_categories(false); // Get all categories including inactive
        
        echo '<div id="wpccm-categories-manager">';
        echo '<div style="background: #f0f0f1; padding: 15px; border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #00a32a;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #1d2327;">🏷️ ' . esc_html(wpccm_text('manage_categories')) . '</h3>';
        echo '<p style="margin: 0; color: #50575e;">' . esc_html(wpccm_text('manage_categories_description')) . '</p>';
        echo '</div>';
        
        echo '<div style="margin-bottom: 15px;">';
        echo '<button type="button" class="button button-primary" id="wpccm-add-category">➕ ' . esc_html(wpccm_text('add_new_category')) . '</button>';
        //echo '<button type="button" class="button button-secondary" id="wpccm-check-table" style="margin-right: 10px;">🔍 ' . esc_html(wpccm_translate_pair('Check table', 'בדוק טבלה')) . '</button>';
        echo '</div>';
        
        // Add debug button JavaScript
        // echo '<script>
        // jQuery(document).ready(function($) {
        //     $("#wpccm-check-table").on("click", function() {
        //         $.post(ajaxurl, {
        //             action: "wpccm_check_categories_table",
        //             _wpnonce: "' . wp_create_nonce('wpccm_admin_nonce') . '"
        //         }).done(function(response) {
        //             console.log("Table check response:", response);
        //             alert(response.data || response.message || "בדיקה הושלמה - בדוק קונסול");
        //         });
        //     });
        // });
        // </script>';
        
        if (empty($categories)) {
            echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 4px; text-align: center; color: #666;">';
            echo '<div style="font-size: 36px; margin-bottom: 10px;">📂</div>';
            echo '<p style="margin: 0; font-size: 14px;">' . esc_html(wpccm_text('categories_empty_title')) . '</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 12px;">' . esc_html(wpccm_text('categories_empty_hint')) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="wpccm-table-container" style="border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; overflow: hidden;">';
            echo '<table class="widefat" id="wpccm-categories-table" style="margin: 0; border: none;">';
            echo '<thead>';
            echo '<tr style="background: #f6f7f7;">';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_key')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_display_name')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_description')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_color')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_icon')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_essential')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('category_active')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('actions')) . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($categories as $category) {
                $this->render_new_category_row($category);
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>'; // Close main container
        
        // Add category edit modal
        $this->add_category_management_modal();
        
        // Add JavaScript for category management
        $this->enqueue_new_categories_js();
    }
    
    
    private function enqueue_scanner_js() {
        ?>
        <style>
        .tab-content {
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-top: none;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .nav-tab-wrapper {
            margin-bottom: 0;
        }
        .cookie-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .cookie-table th,
        .cookie-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .cookie-table th {
            background-color: #f9f9f9;
            font-weight: 600;
        }
        .cookie-table tr:hover {
            background-color: #f5f5f5;
        }
        .cookie-name {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            color: #0073aa;
        }
        .cookie-value {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }
        .cookie-category {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            color: white;
        }
        .category-necessary { background: #28a745; }
        .category-functional { background: #17a2b8; }
        .category-performance { background: #ffc107; color: #212529; }
        .category-analytics { background: #6f42c1; }
        .category-advertisement { background: #dc3545; }
        .category-others { background: #6c757d; }
        .category-uncategorized { background: #e9ecef; color: #495057; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').hide();
                $(this).addClass('nav-tab-active');
                
                if ($(this).attr('href') === '#current-cookies') {
                    $('#current-cookies-content').show();
                } else {
                    $('#cookie-suggestions-content').show();
                }
            });
            
            // Refresh current cookies
            $('#refresh-cookies').on('click', function() {
                loadCurrentCookies();
            });
            
            // Scan for suggestions
            $('#scan-suggest-cookies').on('click', function() {
                loadCookieSuggestions();
            });
            
            // Load current cookies on page load
            loadCurrentCookies();
            
            function loadCurrentCookies() {
                $('#current-cookies-list').html('<p><?php echo wpccm_text('loading'); ?>...</p>');
                
                // Get cookies from current domain
                var cookies = document.cookie.split(';').filter(function(cookie) {
                    return cookie.trim().length > 0;
                }).map(function(cookie) {
                    var parts = cookie.trim().split('=');
                    var name = parts[0];
                    var value = parts.slice(1).join('=');
                    return {
                        name: name,
                        value: value,
                        category: categorizeCookie(name)
                    };
                });
                
                if (cookies.length === 0) {
                    $('#current-cookies-list').html('<p><?php echo wpccm_text('no_cookies_found'); ?></p>');
                    return;
                }
                
                renderCurrentCookiesTable(cookies);
            }
            
            function renderCurrentCookiesTable(cookies) {
                var html = '<table class="cookie-table">';
                html += '<thead><tr>';
                html += '<th><?php echo wpccm_text('cookie_name'); ?></th>';
                html += '<th><?php echo wpccm_text('cookie_value'); ?></th>';
                html += '<th><?php echo wpccm_text('category'); ?></th>';
                html += '<th><?php echo wpccm_text('actions'); ?></th>';
                html += '</tr></thead><tbody>';
                
                cookies.forEach(function(cookie) {
                    html += '<tr>';
                    html += '<td><span class="cookie-name">' + escapeHtml(cookie.name) + '</span></td>';
                    html += '<td><span class="cookie-value" title="' + escapeHtml(cookie.value) + '">' + escapeHtml(cookie.value.substring(0, 50)) + (cookie.value.length > 50 ? '...' : '') + '</span></td>';
                    html += '<td><span class="cookie-category category-' + cookie.category + '">' + getCategoryDisplayName(cookie.category) + '</span></td>';
                    html += '<td><button type="button" class="button button-small delete-cookie" data-cookie="' + escapeHtml(cookie.name) + '"><?php echo wpccm_text('delete_cookie'); ?></button></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                $('#current-cookies-list').html(html);
                
                // Bind delete buttons
                $('.delete-cookie').on('click', function() {
                    var cookieName = $(this).data('cookie');
                    if (confirm('<?php echo wpccm_text('confirm_delete_cookie'); ?>')) {
                        deleteCookie(cookieName);
                        loadCurrentCookies(); // Refresh list
                    }
                });
            }
            
            function loadCookieSuggestions() {
                $('#cookie-suggestions-list').html('<p><?php echo wpccm_text('loading'); ?>...</p>');
                
                $.post(ajaxurl, {
                    action: 'wpccm_suggest_purge_cookies',
                    nonce: '<?php echo wp_create_nonce("wpccm_cookie_scanner"); ?>'
                }, function(response) {
                    if (response.success) {
                        renderSuggestionsTable(response.data.suggestions || []);
                    } else {
                        $('#cookie-suggestions-list').html('<p>Error loading suggestions</p>');
                    }
                });
            }
            
            function renderSuggestionsTable(suggestions) {
                if (suggestions.length === 0) {
                    $('#cookie-suggestions-list').html('<p><?php echo wpccm_text('no_suggestions_found'); ?></p>');
                    return;
                }
                
                var html = '<table class="cookie-table">';
                html += '<thead><tr>';
                html += '<th><?php echo wpccm_text('cookie'); ?></th>';
                html += '<th><?php echo wpccm_text('suggested_category'); ?></th>';
                html += '<th><?php echo wpccm_text('reason'); ?></th>';
                html += '<th><?php echo wpccm_text('include_in_purge'); ?></th>';
                html += '</tr></thead><tbody>';
                
                suggestions.forEach(function(suggestion) {
                    html += '<tr>';
                    html += '<td><span class="cookie-name">' + escapeHtml(suggestion.name) + '</span></td>';
                    html += '<td><span class="cookie-category category-' + suggestion.category + '">' + escapeHtml(suggestion.category) + '</span></td>';
                    html += '<td>' + escapeHtml(suggestion.reason) + '</td>';
                    html += '<td><input type="checkbox" class="suggestion-checkbox" data-cookie="' + escapeHtml(suggestion.name) + '" checked></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                html += '<p><button type="button" class="button button-primary" id="add-suggestions"><?php echo wpccm_text('add_to_purge_list'); ?></button></p>';
                
                $('#cookie-suggestions-list').html(html);
                
                // Bind add suggestions button
                $('#add-suggestions').on('click', function() {
                    var selectedCookies = [];
                    $('.suggestion-checkbox:checked').each(function() {
                        selectedCookies.push($(this).data('cookie'));
                    });
                    
                    if (selectedCookies.length > 0) {
                        addToPurgeList(selectedCookies);
                    }
                });
            }
            
            function categorizeCookie(cookieName) {
                var name = cookieName.toLowerCase();
                
                // Necessary patterns (essential for site function)
                if (/(phpsessid|wordpress_|_wp_session|csrf_token|wp-settings|session|auth|login|user_|admin_)/i.test(name)) {
                    return 'necessary';
                }
                
                // Functional patterns
                if (/(wp-|cart_|wishlist_|currency|language|preference|compare|theme_|settings)/i.test(name)) {
                    return 'functional';
                }
                
                // Performance patterns
                if (/(cache_|cdn_|speed|performance|optimization|compress|minify|lazy|defer|w3tc_|wp_rocket)/i.test(name)) {
                    return 'performance';
                }
                
                // Analytics patterns
                if (/(_ga|_gid|_gat|__utm|_hjid|_hjsession|ajs_|_mkto_trk|hubspot|_pk_|_omapp|__qca|optimizely|mp_|_clck|_clsk|muid|_scid|_uetvid|vuid)/i.test(name)) {
                    return 'analytics';
                }
                
                // Advertisement patterns  
                if (/(_fbp|fr|_gcl_|ide|test_cookie|dsid|__gads|__gpi|_gac_|anid|nid|1p_jar|apisid|hsid|sapisid|sid|sidcc|ssid|_pinterest|uuid2|sess|anj|usersync|tdcpm|tdid|tuuid|ouuid|_cc_)/i.test(name)) {
                    return 'advertisement';
                }
                
                // Social media patterns
                if (/(twitter_|personalization_id|guest_id|datr|sb|wd|xs|c_user|li_sugr|lidc|bcookie|bscookie|ysc|visitor_info)/i.test(name)) {
                    return 'others';
                }
                
                // Custom/unknown cookies - check for common patterns
                if (/^(test_|demo_|hello|sample_|custom_|user_)/i.test(name)) {
                    return 'functional';
                }
                
                // WordPress-specific patterns we might have missed
                if (/(wordpress|wp_|_wp)/i.test(name)) {
                    return 'functional';
                }
                
                // Default to others for anything unrecognized
                return 'others';
            }
            
            function getCategoryDisplayName(category) {
                var names = {
                    'necessary': '<?php echo wpccm_text('necessary'); ?>',
                    'functional': '<?php echo wpccm_text('functional'); ?>',
                    'performance': '<?php echo wpccm_text('performance'); ?>',
                    'analytics': '<?php echo wpccm_text('analytics'); ?>',
                    'advertisement': '<?php echo wpccm_text('advertisement'); ?>',
                    'others': '<?php echo wpccm_text('others'); ?>',
                    'uncategorized': '<?php echo wpccm_text('uncategorized'); ?>'
                };
                return names[category] || category;
            }
            
            function deleteCookie(name) {
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=' + window.location.hostname + ';';
                document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; domain=.' + window.location.hostname + ';';
            }
            
            function addToPurgeList(cookies) {
                alert('<?php echo wpccm_text('cookies_added_to_purge'); ?>: ' + cookies.join(', '));
                // Here you could make an AJAX call to actually add them to the purge list
            }
            
            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render the new Management & Statistics page
     */
    public function render_management_page() {
        ?>
        <div class="wrap">
            <h1><span class="dashicons dashicons-chart-bar"></span> <?php echo esc_html(wpccm_text('management_title')); ?></h1>
            
            <!-- Dashboard Overview -->
            <div class="wpccm-dashboard-grid">
                <!-- Statistics Cards -->
                <div class="wpccm-stats-cards">
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3><?php echo esc_html(wpccm_text('consents_today')); ?></h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-dismiss"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3><?php echo esc_html(wpccm_text('rejects_today')); ?></h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3><?php echo esc_html(wpccm_text('total_users')); ?></h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-admin-tools"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3><?php echo esc_html(wpccm_text('active_cookies')); ?></h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="wpccm-quick-actions">
                    <h3><?php echo esc_html(wpccm_text('quick_actions')); ?></h3>
                    <div class="wpccm-action-buttons">
                        <button type="button" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php echo esc_html(wpccm_text('export_report')); ?>
                        </button>
                        <button type="button" class="button" onclick="refreshAllData()">
                            <span class="dashicons dashicons-update"></span>
                            <?php echo esc_html(wpccm_text('refresh_data')); ?>
                        </button>
                        <button type="button" class="button" onclick="goToAdvancedSettings()">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php echo esc_html(wpccm_text('advanced_settings')); ?>
                        </button>

                    </div>
                </div>
            </div>
            

            
            <!-- Charts Area -->
            <div class="wpccm-charts-section">
                <h2><?php echo esc_html(wpccm_text('charts_and_analysis')); ?></h2>
                <div class="wpccm-charts-grid">
                    <div class="wpccm-chart-container">
                        <h3><?php echo esc_html(wpccm_text('consents_over_time')); ?></h3>
                        <canvas id="consentTimeChart"></canvas>
                    </div>
                    <div class="wpccm-chart-container">
                        <h3><?php echo esc_html(wpccm_text('category_distribution')); ?></h3>
                        <canvas id="consentCategoryChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .wpccm-dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .wpccm-stats-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .wpccm-stat-card {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .wpccm-stat-icon {
            margin-left: 15px;
            font-size: 32px;
            color: #0073aa;
        }
        
        .wpccm-stat-content h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #666;
        }
        
        .wpccm-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #0073aa;
        }
        
        .wpccm-quick-actions {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .wpccm-quick-actions h3 {
            margin-top: 0;
        }
        
        .wpccm-action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .wpccm-action-buttons .button {
            justify-content: flex-start;
            text-align: right;
        }
        
        .wpccm-action-buttons .dashicons {
            margin-left: 8px;
        }
        
        .wpccm-recent-activity,
        .wpccm-charts-section {
            background: white;
            border: 1px solid #ccd0d4;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .wpccm-charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        
                 .wpccm-chart-container {
             border: 1px solid #ddd;
             border-radius: 4px;
             padding: 15px;
             text-align: center;
             min-height: 200px;
         }
         
         .wpccm-activity-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 15px;
         }
         
         .wpccm-activity-table th,
         .wpccm-activity-table td {
             padding: 12px;
             text-align: right;
             border-bottom: 1px solid #ddd;
         }
         
         .wpccm-activity-table th {
             background-color: #f9f9f9;
             font-weight: 600;
         }
         
         .wpccm-activity-table tr:hover {
             background-color: #f5f5f5;
         }
         
         .action-badge {
             display: inline-block;
             padding: 4px 8px;
             border-radius: 12px;
             font-size: 12px;
             font-weight: 500;
             color: white;
             text-transform: uppercase;
         }
         
         .action-accept,
         .action-accept_all {
             background: #28a745;
         }
         
         .action-reject,
         .action-reject_all {
             background: #dc3545;
         }
         
         .action-save {
             background: #17a2b8;
         }
         
         .wpccm-pagination button {
             margin: 0 2px;
         }
         
         .wpccm-controls {
             display: flex;
             align-items: center;
             flex-wrap: wrap;
             gap: 10px;
             margin-bottom: 15px;
         }
         
         .wpccm-controls .button {
             margin: 0;
         }
         
         .wpccm-loading-info {
             font-style: italic;
             color: #666;
             margin-right: 10px;
         }
         
         .wpccm-search-controls {
             display: flex;
             align-items: center;
             flex-wrap: wrap;
             gap: 10px;
             margin-bottom: 15px;
             padding: 15px;
             background-color: #f9f9f9;
             border-radius: 4px;
             border: 1px solid #ddd;
         }
         
         .wpccm-search-controls input[type="text"] {
             padding: 6px 12px;
             border: 1px solid #ddd;
             border-radius: 4px;
             font-size: 14px;
         }
         
         .wpccm-search-controls input[type="text"]:focus {
             outline: none;
             border-color: #0073aa;
             box-shadow: 0 0 0 1px #0073aa;
         }
         
         .wpccm-search-info {
             font-style: italic;
             color: #0073aa;
             font-weight: 500;
         }
         
         @keyframes spin {
             from { transform: rotate(0deg); }
             to { transform: rotate(360deg); }
         }
         
         .wpccm-refresh-button:disabled {
             opacity: 0.7;
             cursor: not-allowed;
         }
        </style>
        
        <script>

        const wpccmDashboardTexts = {
            loading_data: <?php echo json_encode(wpccm_text('loading_data')); ?>,
            error_loading_data: <?php echo json_encode(wpccm_text('error_loading_data')); ?>,
            error_server_connection: <?php echo json_encode(wpccm_text('error_server_connection')); ?>,
            no_history_data: <?php echo json_encode(wpccm_text('no_history_data')); ?>,
            loaded_records_ip_page: <?php echo json_encode(wpccm_text('loaded_records_ip_page')); ?>,
            loaded_records_ip_all: <?php echo json_encode(wpccm_text('loaded_records_ip_all')); ?>,
            loaded_records_page: <?php echo json_encode(wpccm_text('loaded_records_page')); ?>,
            loaded_records_all: <?php echo json_encode(wpccm_text('loaded_records_all')); ?>,
            search_results_ip: <?php echo json_encode(wpccm_text('search_results_ip')); ?>,
            exporting_ip: <?php echo json_encode(wpccm_text('exporting_ip')); ?>,
            exporting: <?php echo json_encode(wpccm_text('exporting')); ?>,
            export_complete_ip: <?php echo json_encode(wpccm_text('export_complete_ip')); ?>,
            export_complete: <?php echo json_encode(wpccm_text('export_complete')); ?>,
            enter_exact_ip: <?php echo json_encode(wpccm_text('enter_exact_ip')); ?>,
            searching: <?php echo json_encode(wpccm_text('searching')); ?>,
            previous: <?php echo json_encode(wpccm_text('previous')); ?>,
            next: <?php echo json_encode(wpccm_text('next')); ?>,
            no_categories_label: <?php echo json_encode(wpccm_text('no_categories_label')); ?>,
            invalid_data: <?php echo json_encode(wpccm_text('invalid_data')); ?>,
            action_accept: <?php echo json_encode(wpccm_text('action_accept')); ?>,
            action_reject: <?php echo json_encode(wpccm_text('action_reject')); ?>,
            action_save: <?php echo json_encode(wpccm_text('action_save')); ?>,
            action_accept_all: <?php echo json_encode(wpccm_text('action_accept_all')); ?>,
            action_reject_all: <?php echo json_encode(wpccm_text('action_reject_all')); ?>,
            refreshing: <?php echo json_encode(wpccm_text('refreshing')); ?>,
        };
        const wpccmDateLocale = <?php echo json_encode(wpccm_get_lang() === 'he' ? 'he-IL' : 'en-US'); ?>;

        // Load statistics on page load
        jQuery(document).ready(function($) {
            loadStatistics();
            loadConsentHistory(1, 100, ''); // Load 100 records by default
        });

        function loadStatistics() {
            jQuery.post(ajaxurl, {
                action: 'wpccm_get_consent_stats',
                nonce: '<?php echo wp_create_nonce('wpccm_stats'); ?>'
            }, function(response) {
                if (response.success) {
                    var stats = response.data;
                    updateStatCard('today_accepts', stats.today_accepts || 0);
                    updateStatCard('today_rejects', stats.today_rejects || 0);
                    updateStatCard('total_users', stats.total_users || 0);
                    updateStatCard('active_cookies', stats.active_cookies || 0);
                }
            });
        }
        
                 function updateStatCard(type, value) {
             var cards = document.querySelectorAll('.wpccm-stat-card');
             var index = 0;
             switch(type) {
                 case 'today_accepts': index = 0; break;
                 case 'today_rejects': index = 1; break;
                 case 'total_users': index = 2; break;
                 case 'active_cookies': index = 3; break;
             }
             if (cards[index]) {
                 cards[index].querySelector('.wpccm-stat-number').textContent = value;
             }
         }
         
         function loadConsentHistory(page = 1, perPage = 100, searchIP = '') {
             var tbody = jQuery('.wpccm-activity-table tbody');
             tbody.html('<tr><td colspan="5">' + wpccmDashboardTexts.loading_data + '</td></tr>');
             
             jQuery.post(ajaxurl, {
                 action: 'wpccm_get_consent_history',
                 nonce: '<?php echo wp_create_nonce('wpccm_history'); ?>',
                 page: page,
                 per_page: perPage,
                 search_ip: searchIP
             }, function(response) {
                 if (response.success) {
                     renderConsentHistory(response.data);
                 } else {
                     tbody.html('<tr><td colspan="5">' + wpccmDashboardTexts.error_loading_data + '</td></tr>');
                 }
             }).fail(function() {
                 tbody.html('<tr><td colspan="5">' + wpccmDashboardTexts.error_server_connection + '</td></tr>');
             });
         }
         
         function renderConsentHistory(data) {
             var tbody = jQuery('.wpccm-activity-table tbody');
             tbody.empty();
             
             // Update loading info
             var loadingInfo = jQuery('.wpccm-loading-info');
             var searchInfo = jQuery('.wpccm-search-info');
             var currentSearchIP = getCurrentSearchIP();
             
             if (currentSearchIP !== '') {
                 if (data.per_page > 0) {
                     loadingInfo.text(wpccmDashboardTexts.loaded_records_ip_page
                         .replace('%1$d', data.data.length)
                         .replace('%2$d', data.total)
                         .replace('%3$s', currentSearchIP)
                         .replace('%4$d', data.current_page));
                 } else {
                     loadingInfo.text(wpccmDashboardTexts.loaded_records_ip_all
                         .replace('%1$s', currentSearchIP)
                         .replace('%2$d', data.data.length));
                 }
                 searchInfo.text(wpccmDashboardTexts.search_results_ip.replace('%s', currentSearchIP));
             } else {
                 if (data.per_page > 0) {
                     loadingInfo.text(wpccmDashboardTexts.loaded_records_page
                         .replace('%1$d', data.data.length)
                         .replace('%2$d', data.total)
                         .replace('%3$d', data.current_page));
                 } else {
                     loadingInfo.text(wpccmDashboardTexts.loaded_records_all.replace('%d', data.data.length));
                 }
                 searchInfo.text('');
             }
             
             if (!data.data || data.data.length === 0) {
                 tbody.html('<tr><td colspan="5">' + wpccmDashboardTexts.no_history_data + '</td></tr>');
                 loadingInfo.text('');
                 return;
             }
             
             data.data.forEach(function(record) {
                 var date = new Date(record.created_at).toLocaleString(wpccmDateLocale);
                var actionText = getActionText(record.action_type);
                 var categories = '';
                 
                 try {
                     var categoriesData = JSON.parse(record.categories_accepted || '[]');
                     if (Array.isArray(categoriesData) && categoriesData.length > 0) {
                         categories = categoriesData.join(', ');
                     } else {
                         categories = wpccmDashboardTexts.no_categories_label;
                     }
                 } catch (e) {
                     categories = wpccmDashboardTexts.invalid_data;
                 }
                 
                 var row = '<tr>' +
                     '<td>' + escapeHtml(date) + '</td>' +
                     '<td><span class="action-badge action-' + record.action_type + '">' + actionText + '</span></td>' +
                     '<td>' + escapeHtml(categories) + '</td>' +
                     '<td>' + escapeHtml(record.user_ip || '-') + '</td>' +
                     '<td>' + escapeHtml(record.referer_url || '-') + '</td>' +
                 '</tr>';
                 
                 tbody.append(row);
             });
             
             // Add pagination if needed (only when not loading all data)
             if (data.per_page > 0 && data.total > data.per_page) {
                 addPagination(data);
             } else {
                 // Remove existing pagination if loading all data
                 jQuery('.wpccm-pagination').remove();
             }
         }
         
         function getActionText(actionType) {
             var actions = {
                 'accept': wpccmDashboardTexts.action_accept,
                 'reject': wpccmDashboardTexts.action_reject, 
                 'save': wpccmDashboardTexts.action_save,
                 'accept_all': wpccmDashboardTexts.action_accept_all,
                 'reject_all': wpccmDashboardTexts.action_reject_all
             };
             return actions[actionType] || actionType;
         }
         
         function addPagination(data) {
             var totalPages = Math.ceil(data.total / data.per_page);
             var currentPage = data.current_page;
             
             if (totalPages <= 1) return;
             
             var paginationHtml = '<div class="wpccm-pagination" style="margin-top: 15px; text-align: center;">';
             
             // Previous button
             if (currentPage > 1) {
                 paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage - 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">« ' + wpccmDashboardTexts.previous + '</button> ';
             }
             
             // Page numbers (show max 5 pages around current)
             var startPage = Math.max(1, currentPage - 2);
             var endPage = Math.min(totalPages, currentPage + 2);
             
             for (var i = startPage; i <= endPage; i++) {
                 var buttonClass = (i === currentPage) ? 'button button-primary' : 'button';
                 paginationHtml += '<button class="' + buttonClass + '" onclick="loadConsentHistory(' + i + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">' + i + '</button> ';
             }
             
             // Next button
             if (currentPage < totalPages) {
                 paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage + 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">' + wpccmDashboardTexts.next + ' »</button>';
             }
             
             paginationHtml += '</div>';
             
             jQuery('.wpccm-recent-activity').append(paginationHtml);
         }
         
         function escapeHtml(text) {
             var div = document.createElement('div');
             div.textContent = text;
             return div.innerHTML;
         }
         
         function exportData(format) {
             var loadingInfo = jQuery('.wpccm-loading-info');
             var currentSearchIP = getCurrentSearchIP();
             
             if (currentSearchIP !== '') {
                 loadingInfo.text(wpccmDashboardTexts.exporting_ip.replace('%s', currentSearchIP));
             } else {
                 loadingInfo.text(wpccmDashboardTexts.exporting);
             }
             
             // Create a form to submit the export request
             var form = document.createElement('form');
             form.method = 'POST';
             form.action = ajaxurl;
             form.target = '_blank';
             
             var actionInput = document.createElement('input');
             actionInput.type = 'hidden';
             actionInput.name = 'action';
             actionInput.value = 'wpccm_export_consent_history';
             
             var nonceInput = document.createElement('input');
             nonceInput.type = 'hidden';
             nonceInput.name = 'nonce';
             nonceInput.value = '<?php echo wp_create_nonce('wpccm_export'); ?>';
             
             var formatInput = document.createElement('input');
             formatInput.type = 'hidden';
             formatInput.name = 'format';
             formatInput.value = format;
             
             var searchInput = document.createElement('input');
             searchInput.type = 'hidden';
             searchInput.name = 'search_ip';
             searchInput.value = currentSearchIP;
             
             form.appendChild(actionInput);
             form.appendChild(nonceInput);
             form.appendChild(formatInput);
             form.appendChild(searchInput);
             
             document.body.appendChild(form);
             form.submit();
             document.body.removeChild(form);
             
             if (currentSearchIP !== '') {
                 loadingInfo.text(wpccmDashboardTexts.export_complete_ip.replace('%s', currentSearchIP));
             } else {
                 loadingInfo.text(wpccmDashboardTexts.export_complete);
             }
             
             setTimeout(function() {
                 loadingInfo.text('');
             }, 3000);
         }
         
         function searchByIP() {
             var searchIP = jQuery('#search-ip').val().trim();
             var searchInfo = jQuery('.wpccm-search-info');
             
             if (searchIP === '') {
                 searchInfo.text(wpccmDashboardTexts.enter_exact_ip);
                 return;
             }
             
             searchInfo.text(wpccmDashboardTexts.searching);
             loadConsentHistory(1, 100, searchIP);
         }
         
         function clearSearch() {
             jQuery('#search-ip').val('');
             jQuery('.wpccm-search-info').text('');
             loadConsentHistory(1, 100, '');
         }
         
         function getCurrentSearchIP() {
             return jQuery('#search-ip').val().trim();
         }
         
         // Add Enter key support for search
         jQuery(document).ready(function($) {
             jQuery('#search-ip').on('keypress', function(e) {
                 if (e.which === 13) { // Enter key
                     searchByIP();
                 }
             });
         });
         
         function refreshAllData() {
             // Show loading state
             var refreshButton = jQuery('button[onclick="refreshAllData()"]');
             var originalText = refreshButton.html();
             refreshButton.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> ' + wpccmDashboardTexts.refreshing);
             refreshButton.prop('disabled', true);
             
             // Refresh statistics
             loadStatistics();
             
             // Refresh consent history (with current search if any)
             var currentSearchIP = getCurrentSearchIP();
             loadConsentHistory(1, 100, currentSearchIP);
             
             // Reset button after 2 seconds
             setTimeout(function() {
                 refreshButton.html(originalText);
                 refreshButton.prop('disabled', false);
             }, 2000);
         }
         
         function goToAdvancedSettings() {
             // Redirect to WPCCM plugin settings page
             window.location.href = '<?php echo admin_url('admin.php?page=wpccm'); ?>';
         }
        </script>
        <?php
    }
    
    /**
     * AJAX handler to get consent statistics
     */
    public function ajax_get_consent_stats() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpccm_consent_history';
        
        // Get today's stats
        $today = current_time('Y-m-d');
        
        $stats = [
            'today_accepts' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE action_type = 'accept' AND DATE(created_at) = %s", 
                $today
            )),
            'today_rejects' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE action_type = 'reject' AND DATE(created_at) = %s", 
                $today
            )),
            'total_users' => $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_ip) FROM $table"
            ),
            'active_cookies' => $this->get_active_cookies_count()
        ];
        
        wp_send_json_success($stats);
    }
    
         /**
      * AJAX handler to get consent history
      */
     public function ajax_get_consent_history() {
         // Check permissions and nonce
         if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_history')) {
             wp_send_json_error('Unauthorized');
             return;
         }
         
         global $wpdb;
         $table = $wpdb->prefix . 'wpccm_consent_history';
         
         // Check if table exists
         $table_exists = $wpdb->get_var($wpdb->prepare(
             "SHOW TABLES LIKE %s", 
             $table
         ));
         
         if ($table_exists !== $table) {
             wp_send_json_success([
                 'data' => [],
                 'total' => 0,
                 'per_page' => 20,
                 'current_page' => 1,
                 'message' => 'No consent history table found. Click "Create DB Tables" to create it.'
             ]);
             return;
         }
         
         $page = intval($_POST['page'] ?? 1);
         $per_page = intval($_POST['per_page'] ?? 100); // Allow custom per_page, default to 100
         $search_ip = sanitize_text_field($_POST['search_ip'] ?? '');
         
         // Build WHERE clause for IP search
         $where_clause = '';
         $where_params = [];
         
         if (!empty($search_ip)) {
             $where_clause = 'WHERE user_ip = %s';
             $where_params[] = $search_ip;
         }
         
         // If per_page is 0 or negative, get all records
         if ($per_page <= 0) {
             if (!empty($where_clause)) {
                 $results = $wpdb->get_results($wpdb->prepare(
                     "SELECT * FROM $table $where_clause ORDER BY created_at DESC",
                     $where_params
                 ));
             } else {
                 $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
             }
             $total = count($results);
         } else {
             $offset = ($page - 1) * $per_page;
             
             if (!empty($where_clause)) {
                 $results = $wpdb->get_results($wpdb->prepare(
                     "SELECT * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d",
                     array_merge($where_params, [$per_page, $offset])
                 ));
                 
                 // Get total count for search results
                 $total = $wpdb->get_var($wpdb->prepare(
                     "SELECT COUNT(*) FROM $table $where_clause",
                     $where_params
                 ));
             } else {
                 $results = $wpdb->get_results($wpdb->prepare(
                     "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
                     $per_page, $offset
                 ));
                 $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
             }
         }
         
         wp_send_json_success([
             'data' => $results ?: [],
             'total' => intval($total),
             'per_page' => $per_page,
             'current_page' => $page
         ]);
     }
    
    /**
     * Get count of active cookies (configured in plugin)
     */
    private function get_active_cookies_count() {
        $options = get_option('wpccm_options', []);
        $cookies = $options['purge']['cookies'] ?? [];
        return count($cookies);
    }
    
    /**
     * Create database tables if they don't exist
     */
    public function create_tables_if_needed() {
        global $wpdb;
        
        $consent_history_table = $wpdb->prefix . 'wpccm_consent_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", 
            $consent_history_table
        ));
        
        if ($table_exists !== $consent_history_table) {
            wpccm_create_database_tables();
            return "Table created successfully!";
        }
        
        return "Table already exists.";
    }
    

    
    /**
     * AJAX handler to export consent history data
     */
    public function ajax_export_consent_history() {
        // Check permissions and nonce
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_export')) {
            wp_send_json_error('Unauthorized');
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpccm_consent_history';
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s", 
            $table
        ));
        
        if ($table_exists !== $table) {
            wp_send_json_error('No consent history table found');
            return;
        }
        
                 $format = $_POST['format'] ?? 'csv';
         $search_ip = sanitize_text_field($_POST['search_ip'] ?? '');
         
         // Build WHERE clause for IP search
         $where_clause = '';
         $where_params = [];
         
         if (!empty($search_ip)) {
             $where_clause = 'WHERE user_ip = %s';
             $where_params[] = $search_ip;
         }
         
         if (!empty($where_clause)) {
             $results = $wpdb->get_results($wpdb->prepare(
                 "SELECT * FROM $table $where_clause ORDER BY created_at DESC",
                 $where_params
             ));
         } else {
             $results = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC");
         }
        
        if ($format === 'csv') {
            $this->export_to_csv($results);
        } elseif ($format === 'json') {
            $this->export_to_json($results);
        } else {
            wp_send_json_error('Invalid export format');
        }
    }
    
    /**
     * Export data to CSV format
     */
    private function export_to_csv($data) {
        $filename = 'consent-history-' . date('Y-m-d-H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for Hebrew text
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, ['תאריך', 'סוג פעולה', 'קטגוריות', 'IP משתמש', 'User Agent', 'URL הפניה']);
        
        foreach ($data as $row) {
            $categories = '';
            try {
                $categoriesData = json_decode($row->categories_accepted ?? '[]', true);
                if (is_array($categoriesData)) {
                    $categories = implode(', ', $categoriesData);
                }
            } catch (Exception $e) {
                $categories = 'נתונים לא תקינים';
            }
            
            fputcsv($output, [
                $row->created_at,
                $row->action_type,
                $categories,
                $row->user_ip,
                $row->user_agent,
                $row->referer_url
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export data to JSON format
     */
    private function export_to_json($data) {
        $filename = 'consent-history-' . date('Y-m-d-H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Get deletion type text
     */
    private function get_deletion_type_text($type) {
        $types = [
            'browsing' => 'נתוני גלישה',
            'account' => 'נתוני גלישה וחשבון'
        ];
        return $types[$type] ?? $type;
    }

    /**
     * AJAX handler for manual data deletion
     */
    public function ajax_delete_data_manually() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_delete_data')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Get request data
        $ip_address = sanitize_text_field($_POST['ip_address'] ?? '');
        $request_id = intval($_POST['request_id'] ?? 0);
        
        // Validate IP address
        if (empty($ip_address) || !filter_var($ip_address, FILTER_VALIDATE_IP)) {
            wp_send_json_error('Invalid IP address');
            return;
        }
        
        // Validate request ID
        if ($request_id <= 0) {
            wp_send_json_error('Invalid request ID');
            return;
        }
        
        global $wpdb;
        $consent_history_table = $wpdb->prefix . 'wpccm_consent_history';
        $deletion_requests_table = $wpdb->prefix . 'wpccm_deletion_requests';
        
        // Delete consent history for this IP
        $deleted_rows = $wpdb->delete(
            $consent_history_table,
            ['user_ip' => $ip_address],
            ['%s']
        );
        
        if ($deleted_rows === false) {
            wp_send_json_error('Failed to delete consent history');
            return;
        }
        
        // Update deletion request status
        $updated = $wpdb->update(
            $deletion_requests_table,
            [
                'status' => 'completed',
                'deleted_at' => current_time('mysql')
            ],
            [
                'id' => $request_id,
                'status' => 'pending'
            ],
            ['%s', '%s'],
            ['%d', '%s']
        );
        
        if ($updated === false) {
            wp_send_json_error('Failed to update request status');
            return;
        }
        
        wp_send_json_success([
            'message' => 'Data deleted successfully',
            'deleted_rows' => $deleted_rows
        ]);
    }

    /**
     * Dashboard Connection Section Callback
     */
    public function dashboard_connection_section_callback() {
        echo '<p>' . wpccm_text('enter_license_key') . '</p>';
    }

    /**
     * Dashboard API URL Field
     */
    public function field_dashboard_api_url() {
        $value = WPCCM_DASHBOARD_API_URL;
        echo '<input type="url" name="wpccm_dashboard_api_url" value="' . esc_attr($value) . '" class="large-text" disabled />';
        echo '<p class="description">' . esc_html(wpccm_text('dashboard_api_description')) . '</p>';
    }

    /**
     * Dashboard License Key Field
     */
    public function field_dashboard_license_key() {
        $value = get_option('wpccm_license_key', '');
        
        // בדיקת סטטוס הרישיון דרך Dashboard class
        $license_status = null;
        $error_message = '';
        
        if (!empty($value)) {
            if (class_exists('WP_CCM_Dashboard')) {
                $dashboard = WP_CCM_Dashboard::get_instance();
                $license_status = $dashboard->test_connection_silent();
            }
        }
        
        // אם הרישיון תקף
        if ($license_status && $license_status['success']) {
            // הצגת רישיון תקף עם אפשרות עריכה
            echo '<div class="license-field-container">';
            echo '<div class="license-status valid">';
            echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span>';
            echo '<strong>' . esc_html(wpccm_text('license_valid')) . '</strong> ' . esc_html(substr($value, 0, 8) . '...');
            echo '<button type="button" class="button button-small" id="edit-license-key" style="margin-right: 10px;">' . esc_html(wpccm_text('edit')) . '</button>';
            echo '</div>';
            echo '<div class="license-input-container" style="display: none;">';
            echo '<input type="text" name="wpccm_license_key" value="' . esc_attr($value) . '" class="large-text" placeholder="' . esc_attr(wpccm_text('enter_license_key')) . '" />';
            echo '<button type="button" class="button button-small" id="cancel-edit-license" style="margin-right: 5px;">' . esc_html(wpccm_text('cancel')) . '</button>';
            echo '</div>';
        echo '</div>';
        
            // JavaScript לטיפול בעריכה
        echo '<script>
        jQuery(document).ready(function($) {
                $("#edit-license-key").click(function() {
                    $(".license-status").hide();
                    $(".license-input-container").show();
                });
                
                $("#cancel-edit-license").click(function() {
                    $(".license-input-container").hide();
                    $(".license-status").show();
                    // איפוס הערך המקורי
                    $("input[name=\'wpccm_license_key\']").val("' . esc_js($value) . '");
                });
            });
            </script>';
                        } else {
            // הצגת שדה רגיל כאשר אין רישיון תקף
            echo '<div class="license-field-container">';
            
            if (!empty($value)) {
                // הצגת הודעת השגיאה הספציפית מהשרת
                $error_message = wpccm_text('license_invalid_or_disconnected');
                if ($license_status && isset($license_status['error'])) {
                    $error_message = $license_status['error'];
                }
                
                echo '<div class="license-status invalid" style="margin-bottom: 10px;">';
                echo '<span class="dashicons dashicons-warning" style="color: orange;"></span>';
                echo '<strong>' . esc_html($error_message) . '</strong>';
                
                // הצגת קוד שגיאה אם קיים
                if ($license_status && isset($license_status['code']) && $license_status['code'] !== 200) {
                    echo '<br><small style="color: #666;">' . esc_html(wpccm_text('error_code_label')) . ' ' . esc_html($license_status['code']) . '</small>';
                }
                echo '</div>';
            }
            
            echo '<input type="text" name="wpccm_license_key" value="' . esc_attr($value) . '" class="large-text" placeholder="' . esc_attr(wpccm_text('enter_license_key')) . '" />';
            echo '<p class="description">' . esc_html(wpccm_text('license_key_description')) . '</p>';
            echo '</div>';
        }
    }



    /**
     * Dashboard Test Connection Field
     */
    public function field_dashboard_test_connection() {
        echo '<button type="button" class="button" id="test-connection-general">בדוק חיבור</button>';
        echo '<div id="connection-result-general"></div>';
        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-connection-general").on("click", function() {
                var $result = $("#connection-result-general");
                $result.html("<p>בודק חיבור...</p>");
                
                // קח את הנתונים מהשדות הנוכחיים (לא מהדאטאבייס)
                var apiUrl = $("input[name=\'wpccm_dashboard_api_url\']").val();
                var licenseKey = $("input[name=\'wpccm_license_key\']").val();
                
                if (!licenseKey) {
                    $result.html("<p style=\"color: red;\">אנא מלא את כל השדות לפני בדיקת החיבור</p>");
                    return;
                }
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "wpccm_test_connection_custom",
                        nonce: "' . wp_create_nonce('wpccm_admin_nonce') . '",
                        api_url: apiUrl,
                        license_key: licenseKey,
                        website_id: 1
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html("<p style=\"color: green;\">" + response.data + "</p>");
                        } else {
                            $result.html("<p style=\"color: red;\">" + response.data + "</p>");
                        }
                    },
                    error: function() {
                        $result.html("<p style=\"color: red;\">שגיאה בחיבור</p>");
                    }
                });
            });
        });
        </script>';
    }

    /**
     * Check if plugin is activated
     */
    private function is_plugin_activated() {
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

    /**
     * Show activation notice at the top of all plugin pages
     */
    public function show_activation_notice() {
        // Only show on our plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wpccm') === false) {
            return;
        }
        
        // Check if plugin is activated
        if (!$this->is_plugin_activated()) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>Cookie Consent Manager:</strong> הפלאגין לא מופעל! ';
            echo '<a href="' . admin_url('admin.php?page=wpccm') . '">לחץ כאן להפעלת הפלאגין</a></p>';
            echo '</div>';
        }
    }

    /**
     * AJAX handler for saving script mappings from advanced scanner
     */
    public function ajax_cc_detect_save_map() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => wpccm_text('no_permissions')]);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => wpccm_text('security_check_failed')]);
        }
        
        // Get selected items
        $selected_items_json = isset($_POST['selected_items']) ? $_POST['selected_items'] : '';
        
        // Remove slashes that WordPress might have added
        $selected_items_json = stripslashes($selected_items_json);
        
        $selected_items = json_decode($selected_items_json, true);
        
        if (!is_array($selected_items) || empty($selected_items)) {
            wp_send_json_error(['message' => 'לא נבחרו פריטים לשמירה']);
        }
        
        // Process items and save to options
        // CLEAR existing mappings first (for save all functionality)
        $script_mappings = array();
        $domain_mappings = array();
        
        foreach ($selected_items as $item) {
            if (!isset($item['category']) || empty($item['category']) || $item['category'] === 'unassigned') {
                continue;
            }
            
            // Save handle mapping if exists
            if (!empty($item['handle']) && $item['handle'] !== 'N/A') {
                $handle = sanitize_text_field($item['handle']);
                $category = sanitize_text_field($item['category']);
                $script_mappings[$handle] = $category;
            }
            
            // Save domain mapping if exists
            if (!empty($item['domain']) && $item['domain'] !== 'Local') {
                $domain = sanitize_text_field($item['domain']);
                $category = sanitize_text_field($item['category']);
                $domain_mappings[$domain] = $category;
            }
        }
        
        // Update options
        update_option('cc_script_handle_map', $script_mappings);
        update_option('cc_script_domain_map', $domain_mappings);
        
        wp_send_json_success([
            'message' => 'המיפויים נשמרו בהצלחה',
            'script_count' => count($script_mappings),
            'domain_count' => count($domain_mappings)
        ]);
    }
    
    /**
     * AJAX handler for storing scanned items
     */
    public function ajax_cc_detect_store() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        // For now, just return success (implement actual storage if needed)
        wp_send_json_success(['message' => 'נתונים נשמרו בהצלחה']);
    }
    
    /**
     * AJAX handler for deleting mappings
     */
    public function ajax_cc_detect_delete_mapping() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        
        if (empty($key)) {
            wp_send_json_error(['message' => 'מפתח לא תקין']);
        }
        
        // Remove from both script and domain mappings
        $script_mappings = get_option('cc_script_handle_map', array());
        $domain_mappings = get_option('cc_script_domain_map', array());
        
        unset($script_mappings[$key]);
        unset($domain_mappings[$key]);
        
        update_option('cc_script_handle_map', $script_mappings);
        update_option('cc_script_domain_map', $domain_mappings);
        
        wp_send_json_success(['message' => 'המיפוי נמחק בהצלחה']);
    }
    
    /**
     * AJAX handler for getting WordPress registered scripts
     */
    public function ajax_cc_detect_get_registered_scripts() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        global $wp_scripts;
        $detected_scripts = [];
        
        if (!empty($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                // Skip WordPress core scripts that are usually necessary
                if (strpos($handle, 'wp-') === 0 && strpos($script->src, 'wp-includes') !== false) {
                    continue;
                }
                
                $src = $script->src;
                if (empty($src)) continue;
                
                // Make relative URLs absolute
                if (strpos($src, 'http') !== 0) {
                    $src = site_url($src);
                }
                
                try {
                    $url = parse_url($src);
                    $domain = isset($url['host']) ? $url['host'] : 'Local';
                    
                    // Suggest category based on handle and source
                    $suggested_category = $this->suggest_script_category($handle, $src);
                    
                    $detected_scripts[] = [
                        'key' => $handle,
                        'handle' => $handle,
                        'domain' => $domain,
                        'suggested' => $suggested_category,
                        'category' => $suggested_category,
                        'src' => $src
                    ];
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        wp_send_json_success([
            'scripts' => $detected_scripts,
            'count' => count($detected_scripts)
        ]);
    }
    
    /**
     * Suggest category for a script based on handle and source
     */
    private function suggest_script_category($handle, $src) {
        $handle_lower = strtolower($handle);
        $src_lower = strtolower($src);
        
        // Analytics patterns
        if (strpos($handle_lower, 'analytics') !== false || 
            strpos($handle_lower, 'gtag') !== false || 
            strpos($handle_lower, 'gtm') !== false ||
            strpos($src_lower, 'google-analytics') !== false ||
            strpos($src_lower, 'googletagmanager') !== false) {
            return 'analytics';
        }
        
        // Marketing/Social patterns
        if (strpos($handle_lower, 'facebook') !== false || 
            strpos($handle_lower, 'pixel') !== false ||
            strpos($handle_lower, 'ads') !== false ||
            strpos($src_lower, 'facebook') !== false) {
            return 'marketing';
        }
        
        // Performance patterns
        if (strpos($handle_lower, 'cache') !== false || 
            strpos($handle_lower, 'optimize') !== false ||
            strpos($src_lower, 'cdn') !== false) {
            return 'performance';
        }
        
        // Functional patterns (jQuery, Bootstrap, etc.)
        if (strpos($handle_lower, 'jquery') !== false || 
            strpos($handle_lower, 'bootstrap') !== false ||
            strpos($handle_lower, 'foundation') !== false) {
            return 'functional';
        }
        
        // Plugin scripts are usually functional
        if (strpos($src_lower, 'wp-content/plugins') !== false) {
            return 'functional';
        }
        
        // Theme scripts are usually functional
        if (strpos($src_lower, 'wp-content/themes') !== false) {
            return 'functional';
        }
        
        // Default
        return 'others';
    }
    
    /**
     * AJAX handler for saving general settings
     */
    public function ajax_save_general_settings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        // Get and sanitize dashboard settings
        $dashboard_api_url = isset($_POST['dashboard_api_url']) ? sanitize_text_field($_POST['dashboard_api_url']) : '';
        $license_key = isset($_POST['license_key']) ? sanitize_text_field($_POST['license_key']) : '';
        $website_id = isset($_POST['website_id']) ? sanitize_text_field($_POST['website_id']) : 1;
        
        // Get and sanitize banner settings
        $banner_title = isset($_POST['banner_title']) ? sanitize_text_field($_POST['banner_title']) : '';
        $banner_description = isset($_POST['banner_description']) ? sanitize_textarea_field($_POST['banner_description']) : '';
        $banner_policy_url = isset($_POST['banner_policy_url']) ? esc_url_raw($_POST['banner_policy_url']) : '';
        
        // Validate required fields
        if (empty($license_key)) {
            wp_send_json_error('אנא מלא את כל שדות האקטיבציה (כתובת API, מפתח רישיון, מזהה אתר)');
        }
        
        if (empty($banner_title) || empty($banner_description)) {
            wp_send_json_error('אנא מלא את כותרת הבאנר ותיאור הבאנר');
        }
        
        // Save dashboard settings (only if not skipping dashboard)
        // if (!$skip_dashboard_validation) {
        // var_dump("WPCCM: License key:", $license_key);
        update_option('wpccm_license_key', $license_key);
        WP_CCM_Dashboard::get_instance()->clear_cached_license_status();
        // }
        
        // Save banner settings
        $current_options = get_option('wpccm_options', []);
        $current_options['banner']['title'] = $banner_title;
        $current_options['banner']['description'] = $banner_description;
        $current_options['banner']['policy_url'] = $banner_policy_url;
        update_option('wpccm_options', $current_options);
        
        // Prepare success message based on whether dashboard is skipped
        if ($skip_dashboard_validation) {
            $message = 'ההגדרות הכלליות נשמרו בהצלחה! (דשבורד דולג באמצעות קוד מאסטר) כותרת באנר: ' . $banner_title . ', תיאור: ' . substr($banner_description, 0, 50) . '...';
        } else {
            $message = 'ההגדרות הכלליות נשמרו בהצלחה! כתובת API: ' . $dashboard_api_url . ', מפתח רישיון: ' . substr($license_key, 0, 8) . '..., מזהה אתר: ' . $website_id . ', כותרת באנר: ' . $banner_title . ', תיאור: ' . substr($banner_description, 0, 50) . '...';
        }
        
        wp_send_json_success([
            'message' => $message,
            'activated' => $this->is_plugin_activated(),
            'saved_data' => [
                'license_key' => substr($license_key, 0, 8) . '...',
                'banner_title' => $banner_title,
                'banner_description' => substr($banner_description, 0, 50) . '...',
                'banner_policy_url' => $banner_policy_url
            ]
        ]);
    }
    
    /**
     * AJAX handler for getting frontend cookies
     */
    public function ajax_get_frontend_cookies() {
        
        // Check if plugin is activated
        if (!WP_CCM_Consent::is_plugin_activated()) {
            wp_send_json_error('Plugin not activated');
            return;
        }
        // var_dump($_POST);
        // Verify nonce
        // if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
        //     wp_send_json_error('Security check failed');
        //     return;
        // }
        
        // Get cookies from the current request
        $cookies = [];
        
        // Get cookies from $_COOKIE superglobal
        if (!empty($_COOKIE)) {
            wpccm_debug_log('ajax_get_frontend_cookies found cookies', ['count' => count($_COOKIE), 'cookies' => $_COOKIE]);
            // echo '<pre>';
            // print_r($_COOKIE);
            // echo '</pre>';
            // var_dump($_COOKIE);
            // var_dump($_SERVER);
            foreach ($_COOKIE as $name => $value) {
                // Skip WordPress admin cookies
                // if (strpos($name, 'wordpress_') === 0 || 
                //     strpos($name, 'wp-') === 0 || 
                //     $name === 'PHPSESSID' ||
                //     strpos($name, 'comment_') === 0) {
                //     continue;
                // }
                $cookies[] = [
                    'name' => $name,
                    'value' => $value
                ];
            }
        }
        
        // Also try to get cookies from HTTP headers
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookie_header = $_SERVER['HTTP_COOKIE'];
            $cookie_pairs = explode(';', $cookie_header);
            
            foreach ($cookie_pairs as $pair) {
                $pair = trim($pair);
                if (empty($pair)) continue;
                
                $name_value = explode('=', $pair, 2);
                if (count($name_value) === 2) {
                    $name = trim($name_value[0]);
                    
                    // // Skip WordPress admin cookies
                    // if (strpos($name, 'wordpress_') === 0 || 
                    //     strpos($name, 'wp-') === 0 || 
                    //     $name === 'PHPSESSID' ||
                    //     strpos($name, 'comment_') === 0) {
                    //     continue;
                    // }
                    
                    // Check if this cookie name already exists
                    $exists = false;
                    foreach ($cookies as $existing_cookie) {
                        if (is_array($existing_cookie) && $existing_cookie['name'] === $name) {
                            $exists = true;
                            break;
                        }
                    }
                    
                    if (!$exists) {
                        // Try to get the value from $_COOKIE if available
                        $value = isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
                        $cookies[] = [
                            'name' => $name,
                            'value' => $value
                        ];
                    }
                }
            }
        }
        
        // Check if this is a GET request (for iframe)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Return JavaScript that sends cookies via postMessage
            $cookies_json = json_encode($cookies);
            echo "<script>
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'wpccm_cookies_response',
                    success: true,
                    cookies: $cookies_json,
                    count: " . count($cookies) . ",
                    source: 'frontend'
                }, '*');
            }
            </script>";
            exit;
        }
        
        wp_send_json_success([
            'cookies' => $cookies,
            'count' => count($cookies),
            'source' => 'frontend'
        ]);
    }
    
    /**
     * AJAX handler for toggling auto sync (both cookies and scripts)
     */
    public function ajax_toggle_auto_sync() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $enable = isset($_POST['enable']) ? (bool) $_POST['enable'] : false;

        if ($enable) {
            // Enable sync and run immediately
            update_option('wpccm_auto_sync_enabled', true);
            update_option('wpccm_auto_script_sync_enabled', true);
            update_option('wpccm_auto_form_sync_enabled', true);

            // Clear existing schedules and create new ones
            wp_clear_scheduled_hook('wpccm_auto_cookie_sync');
            wp_clear_scheduled_hook('wpccm_auto_script_sync');

            // wpccm_schedule_cookie_sync();
            // wpccm_schedule_script_sync();

            // Run sync immediately
            wpccm_perform_auto_cookie_sync();
            wpccm_perform_auto_script_sync();
            wpccm_perform_auto_form_sync(true);

            $interval_minutes = get_option('wpccm_sync_interval_minutes', 60);
            $interval_text = $interval_minutes == 1 ? 'דקה' : $interval_minutes . ' דקות';
            $message = 'סינכרון אוטומטי הופעל - יתבצע כל ' . $interval_text;
        } else {
            // Disable sync
            wp_clear_scheduled_hook('wpccm_auto_cookie_sync');
            wp_clear_scheduled_hook('wpccm_auto_script_sync');

            update_option('wpccm_auto_sync_enabled', false);
            update_option('wpccm_auto_script_sync_enabled', false);
            update_option('wpccm_auto_form_sync_enabled', false);
            $message = 'סינכרון אוטומטי בוטל';
        }

        $next_cookie_run = $enable ? wp_next_scheduled('wpccm_auto_cookie_sync') : null;
        $next_script_run = $enable ? wp_next_scheduled('wpccm_auto_script_sync') : null;
        $next_run = null;
        if ($next_cookie_run && $next_script_run) {
            $next_run = min($next_cookie_run, $next_script_run);
        } elseif ($next_cookie_run) {
            $next_run = $next_cookie_run;
        } elseif ($next_script_run) {
            $next_run = $next_script_run;
        }

        wp_send_json_success([
            'enabled' => $enable,
            'message' => $message,
            'sync_method' => 'frontend',
            'interval_minutes' => get_option('wpccm_sync_interval_minutes', 60),
            'forms_enabled' => get_option('wpccm_auto_form_sync_enabled', false)
        ]);
    }
    
    /**
     * AJAX handler for getting auto sync status (both cookies and scripts)
     */
    public function ajax_get_auto_sync_status() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }

        $cookies_enabled = get_option('wpccm_auto_sync_enabled', false);
        $scripts_enabled = get_option('wpccm_auto_script_sync_enabled', false);
        $forms_enabled   = get_option('wpccm_auto_form_sync_enabled', false);
        $enabled = $cookies_enabled && $scripts_enabled && $forms_enabled;

        // Get current sync interval (default to 60 minutes)
        $interval_minutes = get_option('wpccm_sync_interval_minutes', 60);
        
        $next_cookie_run = wp_next_scheduled('wpccm_auto_cookie_sync');
        $next_script_run = wp_next_scheduled('wpccm_auto_script_sync');
        $current_time = current_time('timestamp', true);
        
        // Use the earliest next run time for display
        $next_run = null;
        if ($next_cookie_run && $next_script_run) {
            $next_run = min($next_cookie_run, $next_script_run);
        } elseif ($next_cookie_run) {
            $next_run = $next_cookie_run;
        } elseif ($next_script_run) {
            $next_run = $next_script_run;
        }
        
        wp_send_json_success([
            'enabled' => $enabled,
            'cookies_enabled' => $cookies_enabled,
            'scripts_enabled' => $scripts_enabled,
            'forms_enabled' => $forms_enabled,
            'interval_minutes' => $interval_minutes,
            'sync_method' => 'frontend'
        ]);
    }
    
    /**
     * AJAX handler for running manual auto sync (for testing both cookies and scripts)
     */
    public function ajax_run_manual_auto_sync() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            $results = [];
            
            // Run cookie sync
            if (get_option('wpccm_auto_sync_enabled', false)) {
                wpccm_perform_auto_cookie_sync();
                $results[] = 'עוגיות';
            }
            
            // Run script sync
            if (get_option('wpccm_auto_script_sync_enabled', false)) {
                wpccm_perform_auto_script_sync();
                $results[] = 'סקריפטים';
            }

            if (get_option('wpccm_auto_form_sync_enabled', false)) {
                wpccm_perform_auto_form_sync(true);
                $results[] = 'טפסים';
            }
            
            if (empty($results)) {
                wp_send_json_success([
                    'message' => 'אין סינכרון אוטומטי מופעל - הפעל תחילה את הסינכרון',
                    'run_time' => current_time('Y-m-d H:i:s')
                ]);
            } else {
                wp_send_json_success([
                    'message' => 'סינכרון הופעל ידנית עבור: ' . implode(' ו', $results) . ' - בדוק את הטבלאות לתוצאות',
                    'run_time' => current_time('Y-m-d H:i:s'),
                    'synced_items' => $results
                ]);
            }
            
        } catch (Exception $e) {
            wp_send_json_error('שגיאה בהרצת סינכרון ידני: ' . $e->getMessage());
        }
    }
    
    /**
     * Render script sync tab
     */
    private function render_script_sync_tab() {
        ?>
        <div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #00a32a;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">🔍 <?php echo esc_html(wpccm_text('script_sync_title')); ?></h3>
            <p style="margin: 0 0 15px 0; color: #50575e;"><?php echo esc_html(wpccm_text('script_sync_description')); ?></p>
            
            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <button type="button" class="button button-primary" id="wpccm-sync-scripts-btn">🔄 <?php echo esc_html(wpccm_text('sync_scripts_button')); ?></button>
                <span id="wpccm-scripts-sync-status" style="color: #50575e; font-size: 13px; font-weight: 500;"></span>
            </div>
        </div>

        <!-- Scripts Table -->
        <div id="wpccm-scripts-table-container">
            <?php $this->render_scripts_table(); ?>
        </div>

        <!-- Scripts Sync History -->
        <div style="margin-top: 30px;">
            <h3>📊 <?php echo esc_html(wpccm_text('script_sync_history_title')); ?></h3>
            <?php $this->render_scripts_sync_history_table(); ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Sync scripts
            $('#wpccm-sync-scripts-btn').on('click', function() {
                const button = $(this);
                const originalText = button.text();
                const status = $('#wpccm-scripts-sync-status');
                
                button.text('⏳ ' + <?php echo json_encode(wpccm_text('script_sync_scanning')); ?>).prop('disabled', true);
                status.text(<?php echo json_encode(wpccm_text('script_sync_starting')); ?>);
                
                $.post(ajaxurl, {
                    action: 'wpccm_sync_scripts',
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success) {
                        status.html('✅ ' + response.data.message);
                        // Refresh the scripts table
                        $('#wpccm-scripts-table-container').load(location.href + ' #wpccm-scripts-table-container > *');
                        // Refresh history table
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        const errTpl = <?php echo json_encode(wpccm_text('error_with_message')); ?> || 'Error: %s';
                        const fallback = <?php echo json_encode(wpccm_text('unknown_error')); ?> || 'Unknown error';
                        const msg = errTpl.replace('%s', response.data || fallback);
                        status.html('❌ ' + msg);
                    }
                }).fail(function() {
                    status.html('❌ ' + <?php echo json_encode(wpccm_text('script_sync_error')); ?>);
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render forms sync tab
     */
    private function render_forms_sync_tab() {
        ?>
        <div style="background: #f0f0f1; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #0073aa;">
            <h3 style="margin: 0 0 10px 0; color: #1d2327;">📝 <?php echo wpccm_text('forms_sync'); ?></h3>
            <p style="margin: 0 0 15px 0; color: #50575e;"><?php echo wpccm_text('forms_sync_description'); ?></p>

            <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                <button type="button" class="button button-primary" id="wpccm-sync-forms-btn">🔄 <?php echo wpccm_text('forms_sync'); ?></button>
                <span id="wpccm-forms-sync-status" style="color: #50575e; font-size: 13px; font-weight: 500;"></span>
            </div>
        </div>

        <div id="wpccm-forms-table-container">
            <?php $this->render_forms_table(); ?>
        </div>

        <div style="margin-top: 30px;">
            <h3>📊 <?php echo wpccm_text('forms_sync_history'); ?></h3>
            <?php $this->render_forms_sync_history_table(); ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#wpccm-sync-forms-btn').on('click', function() {
                const button = $(this);
                const originalText = button.text();
                const status = $('#wpccm-forms-sync-status');

                button.text('⏳ ' + <?php echo json_encode(wpccm_text('forms_sync_scanning')); ?>).prop('disabled', true);
                status.text(<?php echo json_encode(wpccm_text('forms_sync_detecting')); ?>);

                $.post(ajaxurl, {
                    action: 'wpccm_sync_forms',
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success) {
                        const msg = response.data && response.data.message ? response.data.message : <?php echo json_encode(wpccm_text('forms_sync')); ?>;
                        status.html('✅ ' + msg);
                        $('#wpccm-forms-table-container').load(location.href + ' #wpccm-forms-table-container > *');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        const errTpl = <?php echo json_encode(wpccm_text('error_with_message')); ?> || 'Error: %s';
                        const unknownErr = <?php echo json_encode(wpccm_text('unknown_error')); ?> || 'Unknown error';
                        status.html('❌ ' + errTpl.replace('%s', response.data || unknownErr));
                    }
                }).fail(function() {
                    status.html('❌ ' + <?php echo json_encode(wpccm_text('forms_sync_error')); ?>);
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render scripts table
     */
    private function render_scripts_table() {
        $scripts = wpccm_get_scripts_from_db();
        $categories = wpccm_get_categories();
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 40%;"><?php echo esc_html(wpccm_text('scripts_table_url_type')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('scripts_table_type')); ?></th>
                    <th style="width: 20%;"><?php echo esc_html(wpccm_text('scripts_table_category')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('scripts_table_last_seen')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('scripts_table_actions')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scripts)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #666;">
                            🔍 <?php echo esc_html(wpccm_text('scripts_table_empty')); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scripts as $script): ?>
                        <tr data-script-id="<?php echo $script['id']; ?>">
                            <td>
                                <div style="font-weight: 500;">
                                    <?php if ($script['script_type'] === 'external'): ?>
                                        <a href="<?php echo esc_url($script['script_url']); ?>" target="_blank" title="<?php echo esc_attr(wpccm_text('script_open_new_tab')); ?>">
                                            <?php echo esc_html($script['script_url']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #666;"><?php echo esc_html(wpccm_text('script_internal')); ?></span>
                                        <div style="font-size: 11px; color: #999; margin-top: 2px;">
                                            <?php echo substr(esc_html($script['script_content']), 0, 100) . '...'; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="script-type-badge script-type-<?php echo $script['script_type']; ?>">
                                    <?php echo $script['script_type'] === 'external' ? '🔗 ' . esc_html(wpccm_text('script_external')) : '📝 ' . esc_html(wpccm_text('script_internal_label')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="category-badge category-<?php echo $script['category']; ?>">
                                    <?php echo $this->get_category_display_name($script['category']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y H:i', strtotime($script['last_seen'])); ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small edit-script-category" 
                                        data-script-id="<?php echo $script['id']; ?>"
                                        data-current-category="<?php echo $script['category']; ?>"
                                        title="<?php echo esc_attr(wpccm_text('script_edit_button')); ?>">
                                    ✏️ <?php echo esc_html(wpccm_text('script_edit_button')); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Edit Category Modal -->
        <div id="edit-script-category-modal" style="display: none;">
            <div class="modal-content">
                <h3><?php echo esc_html(wpccm_text('script_edit_category_title')); ?></h3>
                <form id="edit-script-category-form">
                    <input type="hidden" id="edit-script-id" name="script_id" value="">
                    <label for="edit-script-category"><?php echo esc_html(wpccm_text('script_edit_category_label')); ?></label>
                    <select id="edit-script-category" name="category" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo esc_attr($category['category_key']); ?>">
                                <?php echo esc_html($category['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="modal-buttons">
                        <button type="button" id="save-script-category" class="button button-primary"><?php echo esc_html(wpccm_text('save')); ?></button>
                        <button type="button" id="cancel-script-edit" class="button"><?php echo esc_html(wpccm_text('cancel')); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <style>
        .script-type-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 500;
        }
        .script-type-external {
            background: #e3f2fd;
            color: #1976d2;
        }
        .script-type-inline {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        #edit-script-category-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
        }
        #edit-script-category-modal .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 4px;
            min-width: 400px;
        }
        .modal-buttons {
            margin-top: 15px;
            text-align: right;
        }
        .modal-buttons button {
            margin-left: 10px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Edit script category
            $(document).on('click', '.edit-script-category', function() {
                const scriptId = $(this).data('script-id');
                const currentCategory = $(this).data('current-category');
                
                $('#edit-script-id').val(scriptId);
                $('#edit-script-category').val(currentCategory);
                $('#edit-script-category-modal').show();
            });

            // Cancel edit
            $('#cancel-script-edit').on('click', function() {
                $('#edit-script-category-modal').hide();
            });

            // Save script category
            $('#save-script-category').on('click', function() {
                const scriptId = $('#edit-script-id').val();
                const category = $('#edit-script-category').val();
                
                $.post(ajaxurl, {
                    action: 'wpccm_update_script_category',
                    script_id: scriptId,
                    category: category,
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success) {
                        $('#edit-script-category-modal').hide();
                        // Refresh the table
                        $('#wpccm-scripts-table-container').load(location.href + ' #wpccm-scripts-table-container > *');
                    } else {
                        const errTpl = <?php echo json_encode(wpccm_text('error_with_message')); ?> || 'Error: %s';
                        const unknownErr = <?php echo json_encode(wpccm_text('unknown_error')); ?> || 'Unknown error';
                        alert(errTpl.replace('%s', response.data || unknownErr));
                    }
                }).fail(function() {
                    alert(<?php echo json_encode(wpccm_text('script_update_error')); ?>);
                });
            });

            // View script sync details
            $(document).on('click', '.view-script-sync-details', function() {
                const syncId = $(this).data('sync-id');
                const button = $(this);
                const originalText = button.text();
                
                button.text('⏳ ' + <?php echo json_encode(wpccm_text('script_sync_details_loading')); ?>).prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wpccm_get_sync_details',
                    sync_id: syncId,
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success && response.data.cookies_data) {
                        showScriptSyncDetailsModal(response.data.cookies_data, response.data.sync_time);
                    } else {
                        alert(<?php echo json_encode(wpccm_text('script_sync_details_load_error')); ?>);
                    }
                }).fail(function() {
                    alert(<?php echo json_encode(wpccm_text('script_sync_details_load_error')); ?>);
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });

            function showScriptSyncDetailsModal(scriptsData, syncTime) {
                let modalHtml = '<div id="script-sync-details-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">';
                modalHtml += '<div style="background: white; padding: 20px; border-radius: 8px; max-width: 800px; max-height: 80%; overflow-y: auto; margin: 20px;">';
                const detailsTitleTpl = <?php echo json_encode(wpccm_text('script_sync_details_title')); ?> || 'Script Sync Details - %s';
                modalHtml += '<h3 style="margin: 0 0 15px 0; color: #1d2327;">📊 ' + detailsTitleTpl.replace('%s', syncTime) + '</h3>';
                modalHtml += '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                modalHtml += '<table style="width: 100%; border-collapse: collapse;">';
                modalHtml += '<thead style="background: #f9f9f9; position: sticky; top: 0;"><tr>';
                modalHtml += '<th style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo esc_html(wpccm_text('script_sync_details_url_type')); ?></th>';
                modalHtml += '<th style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo esc_html(wpccm_text('script_sync_details_type')); ?></th>';
                modalHtml += '<th style="padding: 8px; border-bottom: 1px solid #ddd; text-align: right;"><?php echo esc_html(wpccm_text('script_sync_details_category')); ?></th>';
                modalHtml += '</tr></thead><tbody>';
                
                if (Array.isArray(scriptsData)) {
                    scriptsData.forEach(function(script) {
                        modalHtml += '<tr>';
                        modalHtml += '<td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px;">';
                        if (script.type === 'external') {
                            modalHtml += '<a href="' + script.url + '" target="_blank" style="color: #0073aa;">' + script.url + '</a>';
                        } else {
                            modalHtml += '<span style="color: #666;"><?php echo esc_html(wpccm_text('script_internal')); ?></span>';
                            if (script.content) {
                                modalHtml += '<div style="font-size: 10px; color: #999; margin-top: 2px;">' + script.content.substring(0, 100) + '...</div>';
                            }
                        }
                        modalHtml += '</td>';
                        modalHtml += '<td style="padding: 8px; border-bottom: 1px solid #eee;">';
                        modalHtml += script.type === 'external' ? '🔗 ' + <?php echo json_encode(wpccm_text('script_external')); ?> : '📝 ' + <?php echo json_encode(wpccm_text('script_internal_label')); ?>;
                        modalHtml += '</td>';
                        modalHtml += '<td style="padding: 8px; border-bottom: 1px solid #eee;">' + getScriptCategoryBadge(script.category) + '</td>';
                        modalHtml += '</tr>';
                    });
                } else {
                    modalHtml += '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #666;"><?php echo esc_html(wpccm_text('script_sync_details_no_data')); ?></td></tr>';
                }
                
                modalHtml += '</tbody></table>';
                modalHtml += '</div>';
                modalHtml += '<div style="margin-top: 15px; text-align: center;">';
                modalHtml += '<button type="button" class="button button-primary" onclick="closeScriptSyncDetailsModal()"><?php echo esc_html(wpccm_text('close')); ?></button>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                
                $('body').append(modalHtml);
            }

            function getScriptCategoryBadge(category) {
                const categories = <?php 
                $categories = wpccm_get_categories();
                $categories_js = [];
                foreach ($categories as $cat) {
                    $categories_js[$cat['category_key']] = [
                        'name' => $cat['display_name'],
                        'color' => $cat['color'],
                        'icon' => $cat['icon'] ?? ''
                    ];
                }
                echo json_encode($categories_js);
                ?>;
                
                const cat = categories[category] || categories['others'] || { name: '<?php echo esc_html(wpccm_text('others')); ?>', color: '#666', icon: '' };
                const icon = cat.icon ? cat.icon + ' ' : '';
                return '<span style="background: ' + cat.color + '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">' + icon + cat.name + '</span>';
            }

            // Close script sync modal function (global)
            window.closeScriptSyncDetailsModal = function() {
                $('#script-sync-details-modal').remove();
            };

            // Close modal on background click
            $(document).on('click', '#script-sync-details-modal', function(e) {
                if (e.target === this) {
                    closeScriptSyncDetailsModal();
                }
            });
        });
        </script>
        <?php
    }

    private function render_forms_table() {
        $forms = wpccm_get_forms_from_db();

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php echo esc_html(wpccm_text('forms_table_page')); ?></th>
                    <th style="width: 20%;"><?php echo esc_html(wpccm_text('forms_table_form_id')); ?></th>
                    <th style="width: 25%;"><?php echo esc_html(wpccm_text('forms_table_action')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('forms_table_method')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('forms_table_status')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('forms_table_created')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forms)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #666;">
                            <?php echo wpccm_text('forms_no_results'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($forms as $form): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;">
                                    <a href="<?php echo esc_url($form['page_url']); ?>" target="_blank"><?php echo esc_html($form['page_title']); ?></a>
                                </div>
                                <div style="font-size: 11px; color: #666;"><?php echo esc_html(wpccm_text('forms_page_id')); ?> <?php echo (int) $form['post_id']; ?></div>
                            </td>
                            <td>
                                <?php if (!empty($form['form_id_attr'])): ?>
                                    <div>#<?php echo esc_html($form['form_id_attr']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($form['form_class_attr'])): ?>
                                    <div class="description">.<?php echo esc_html(str_replace(' ', '.', trim($form['form_class_attr']))); ?></div>
                                <?php endif; ?>
                                <?php if (empty($form['form_id_attr']) && empty($form['form_class_attr'])): ?>
                                    <span style="color:#666; font-size:11px;"><?php echo esc_html(wpccm_text('forms_no_identifier')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($form['form_action'])): ?>
                                    <code style="font-size: 11px; word-break: break-all;">
                                        <?php echo esc_html($form['form_action']); ?>
                                    </code>
                                <?php else: ?>
                                    <span style="color:#666; font-size: 11px;"><?php echo esc_html(wpccm_text('forms_action_same_page')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-transform: uppercase; text-align:center;">
                                <?php echo esc_html($form['form_method'] ?: 'POST'); ?>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($form['consent_required']): ?>
                                    <span style="color:#00a32a; font-weight:600;"><?php echo esc_html(wpccm_text('forms_status_required')); ?></span>
                                <?php else: ?>
                                    <span style="color:#666;"><?php echo esc_html(wpccm_text('forms_status_disabled')); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px; color:#555;">
                                <?php
                                    $detected_at = !empty($form['detected_at']) ? date_i18n('d/m/Y', strtotime($form['detected_at'])) : '-';
                                    $last_seen = !empty($form['last_seen']) ? date_i18n('d/m/Y', strtotime($form['last_seen'])) : '-';
                                ?>
                                <?php echo esc_html($detected_at); ?><br>
                                <span style="color:#888;"><?php echo esc_html(wpccm_text('forms_updated_label')); ?> <?php echo esc_html($last_seen); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_forms_sync_history_table() {
        $history = wpccm_get_sync_history(25);

        $history = array_values(array_filter($history, function ($entry) {
            return isset($entry['sync_type']) && in_array($entry['sync_type'], ['auto_forms', 'manual_forms'], true);
        }));

        $history = array_slice($history, 0, 10);

        if (empty($history)) {
            echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 4px; text-align: center; color: #666;">';
            echo '<div style="font-size: 36px; margin-bottom: 10px;">🗂️</div>';
            echo '<p style="margin: 0; font-size: 14px;">' . esc_html(wpccm_text('forms_history_empty_title')) . '</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 12px;">' . esc_html(wpccm_text('forms_history_empty_hint')) . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width: 20%;">' . esc_html(wpccm_text('forms_history_time')) . '</th>';
        echo '<th style="width: 20%;">' . esc_html(wpccm_text('forms_history_type')) . '</th>';
        echo '<th style="width: 15%;">' . esc_html(wpccm_text('forms_history_status')) . '</th>';
        echo '<th style="width: 15%;">' . esc_html(wpccm_text('forms_history_found')) . '</th>';
        echo '<th style="width: 15%;">' . esc_html(wpccm_text('forms_history_new')) . '</th>';
        echo '<th style="width: 15%;">' . esc_html(wpccm_text('forms_history_details')) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($history as $entry) {
            $sync_time = date('d/m/Y H:i', strtotime($entry['sync_time']));
            $sync_label = $entry['sync_type'] === 'manual_forms'
                ? '👤 ' . wpccm_text('forms_sync_type_manual')
                : '⏰ ' . wpccm_text('forms_sync_type_auto');
            $status = $this->get_sync_status_display($entry['status']);

            echo '<tr>';
            echo '<td style="padding:10px;">' . esc_html($sync_time) . '</td>';
            echo '<td style="padding:10px;">' . esc_html($sync_label) . '</td>';
            echo '<td style="padding:10px;">' . $status . '</td>';
            echo '<td style="padding:10px; text-align:center;">' . (int) $entry['total_cookies_found'] . '</td>';
            echo '<td style="padding:10px; text-align:center;">';
            if ($entry['new_cookies_added'] > 0) {
                echo '<strong style="color:#00a32a;">+' . (int) $entry['new_cookies_added'] . '</strong>';
            } else {
                echo '<span style="color:#666;">0</span>';
            }
            echo '</td>';
            echo '<td style="padding:10px;">';
            if (!empty($entry['cookies_data'])) {
                echo '<button type="button" class="button button-small view-form-sync-details" data-sync-id="' . (int) $entry['id'] . '" title="' . esc_attr(wpccm_text('forms_history_details')) . '">👁️ ' . esc_html(wpccm_text('view_details')) . '</button>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        ?>
        <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.view-form-sync-details', function() {
                const syncId = $(this).data('sync-id');
                const button = $(this);
                const originalText = button.text();

                button.text('⏳ ' + <?php echo json_encode(wpccm_text('forms_details_loading')); ?>).prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'wpccm_get_sync_details',
                    sync_id: syncId,
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success && Array.isArray(response.data.cookies_data)) {
                        showFormSyncDetailsModal(response.data.cookies_data, response.data.sync_time);
                    } else {
                        alert(<?php echo json_encode(wpccm_text('forms_details_load_error')); ?>);
                    }
                }).fail(function() {
                    alert(<?php echo json_encode(wpccm_text('forms_details_load_error')); ?>);
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });

            function showFormSyncDetailsModal(formsData, syncTime) {
                let modalHtml = '<div id="form-sync-details-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; display: flex; align-items: center; justify-content: center;">';
                modalHtml += '<div style="background: white; padding: 20px; border-radius: 8px; max-width: 700px; max-height: 80%; overflow-y: auto; margin: 20px;">';
                const titleTpl = <?php echo json_encode(wpccm_text('forms_details_title')); ?> || 'New forms - %s';
                modalHtml += '<h3 style="margin: 0 0 15px 0; color: #1d2327;">📝 ' + titleTpl.replace('%s', syncTime) + '</h3>';
                modalHtml += '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                modalHtml += '<table style="width:100%; border-collapse: collapse;">';
                modalHtml += '<thead style="background:#f9f9f9;"><tr>';
                modalHtml += '<th style="padding:8px; border-bottom:1px solid #ddd; text-align:right;"><?php echo esc_html(wpccm_text('forms_details_page')); ?></th>';
                modalHtml += '<th style="padding:8px; border-bottom:1px solid #ddd; text-align:right;"><?php echo esc_html(wpccm_text('forms_details_identifier')); ?></th>';
                modalHtml += '<th style="padding:8px; border-bottom:1px solid #ddd; text-align:right;"><?php echo esc_html(wpccm_text('forms_details_action')); ?></th>';
                modalHtml += '</tr></thead><tbody>';

                if (Array.isArray(formsData) && formsData.length) {
                    formsData.forEach(function(item) {
                        modalHtml += '<tr>';
                        modalHtml += '<td style="padding:8px; border-bottom:1px solid #eee;">' + (item.page_title || '-') + '</td>';
                        modalHtml += '<td style="padding:8px; border-bottom:1px solid #eee;">' + (item.identifier || '-') + '</td>';
                        modalHtml += '<td style="padding:8px; border-bottom:1px solid #eee; font-size:11px; word-break:break-all;">' + (item.action || <?php echo json_encode(wpccm_text('forms_action_same_page')); ?>) + '</td>';
                        modalHtml += '</tr>';
                    });
                } else {
                    modalHtml += '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #666;"><?php echo esc_html(wpccm_text('forms_details_no_data')); ?></td></tr>';
                }

                modalHtml += '</tbody></table>';
                modalHtml += '</div>';
                modalHtml += '<div style="margin-top: 15px; text-align: center;">';
                modalHtml += '<button type="button" class="button button-primary" onclick="closeFormSyncDetailsModal()"><?php echo esc_html(wpccm_text('close')); ?></button>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                modalHtml += '</div>';

                jQuery('body').append(modalHtml);
            }

            window.closeFormSyncDetailsModal = function() {
                jQuery('#form-sync-details-modal').remove();
            }
        });
        </script>
        <?php
    }

    /**
     * Render scripts sync history table
     */
    private function render_scripts_sync_history_table() {
        $history = wpccm_get_sync_history(10);
        
        // Filter only script sync history (we'll add this later)
        $script_history = array_filter($history, function($entry) {
            return strpos($entry['sync_type'], 'script') !== false;
        });
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;"><?php echo esc_html(wpccm_text('script_history_sync_time')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('script_history_type')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('script_history_total')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('script_history_new')); ?></th>
                    <th style="width: 15%;"><?php echo esc_html(wpccm_text('script_history_updated')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('script_history_status')); ?></th>
                    <th style="width: 10%;"><?php echo esc_html(wpccm_text('script_history_actions')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($script_history)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #666;">
                            📊 <?php echo esc_html(wpccm_text('script_history_empty')); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($script_history as $entry): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($entry['sync_time'])); ?></td>
                            <td><?php echo $entry['sync_type'] === 'auto_script' ? '🤖 ' . esc_html(wpccm_text('script_sync_type_auto')) : '👤 ' . esc_html(wpccm_text('script_sync_type_manual')); ?></td>
                            <td><?php echo $entry['total_cookies_found']; ?></td>
                            <td><?php echo $entry['new_cookies_added']; ?></td>
                            <td><?php echo $entry['updated_cookies']; ?></td>
                            <td><?php echo $this->get_sync_status_display($entry['status']); ?></td>
                            <td>
                                <?php if (!empty($entry['cookies_data'])): ?>
                                    <?php 
                                    $data = json_decode($entry['cookies_data'], true);
                                    $count = is_array($data) ? count($data) : 0;
                                    ?>
                                    <button type="button" class="button button-small view-script-sync-details" 
                                            data-sync-id="<?php echo $entry['id']; ?>"
                                            title="<?php echo esc_attr(wpccm_text('script_history_view_details')); ?>">
                                        👁️ <?php echo esc_html(wpccm_text('view_details')); ?> (<?php echo $count; ?>)
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * AJAX handler for syncing scripts
     */
    public function ajax_sync_scripts() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            $start_time = microtime(true);
            
            // Get scripts from site
            $scripts = wpccm_get_current_site_scripts();
            
            if (empty($scripts)) {
                wp_send_json_success([
                    'message' => 'לא נמצאו סקריפטים באתר',
                    'total_scripts' => 0,
                    'new_scripts' => 0,
                    'updated_scripts' => 0
                ]);
                return;
            }
            
            // Save to database
            $result = wpccm_save_scripts_to_db($scripts);
            
            $execution_time = microtime(true) - $start_time;
            
            // Save to sync history (we'll modify the existing function to handle scripts)
            wpccm_save_sync_history(
                'manual_script',
                count($scripts),
                $result['new'],
                $result['updated'],
                array_slice($scripts, 0, 10), // Only save first 10 for history
                'success',
                null,
                $execution_time
            );
            
            wp_send_json_success([
                'message' => sprintf(
                    wpccm_text('scripts_sync_found_summary'),
                    count($scripts),
                    $result['new'],
                    $result['updated']
                ),
                'total_scripts' => count($scripts),
                'new_scripts' => $result['new'],
                'updated_scripts' => $result['updated']
            ]);
            
        } catch (Exception $e) {
            wpccm_debug_log('Script sync error: ' . $e->getMessage());
            wp_send_json_error('שגיאה בסינכרון: ' . $e->getMessage());
        }
    }

    public function ajax_sync_forms() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        try {
            $result = wpccm_perform_auto_form_sync(true);

            wp_send_json_success([
                'message' => sprintf(
                    wpccm_text('forms_sync_found_summary'),
                    $result['total'],
                    $result['new']
                )
            ]);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * AJAX handler for updating script category
     */
    public function ajax_update_script_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $script_id = intval($_POST['script_id']);
        $category = sanitize_text_field($_POST['category']);
        
        global $wpdb;
        $scripts_table = $wpdb->prefix . 'ck_scripts';
        
        // Get script details first
        $script = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $scripts_table WHERE id = %d",
            $script_id
        ));
        
        if (!$script) {
            wp_send_json_error('סקריפט לא נמצא');
            return;
        }
        
        // Update in new system
        $result = $wpdb->update(
            $scripts_table,
            ['category' => $category],
            ['id' => $script_id],
            ['%s'],
            ['%d']
        );
        
        if ($result !== false) {
            // IMPORTANT: Also update legacy mapping system for blocking to work
            if ($script->script_type === 'external' && !empty($script->script_url)) {
                $parsed = parse_url($script->script_url);
                $domain = isset($parsed['host']) ? $parsed['host'] : false;
                if ($domain) {
                    $script_domain_map = get_option('cc_script_domain_map', []);
                    $script_domain_map[$domain] = $category;
                    update_option('cc_script_domain_map', $script_domain_map);
                    
                    wpccm_debug_log('Updated script category and legacy mapping', [
                        'script_id' => $script_id,
                        'domain' => $domain,
                        'category' => $category
                    ]);
                }
            }
            
            wp_send_json_success('קטגוריית הסקריפט עודכנה בהצלחה');
        } else {
            wp_send_json_error('שגיאה בעדכון קטגוריית הסקריפט');
        }
    }

    /**
     * AJAX handler for getting sync details
     */
    public function ajax_get_sync_details() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $sync_id = (int) $_POST['sync_id'];
        if (!$sync_id) {
            wp_send_json_error('Invalid sync ID');
            return;
        }
        
        global $wpdb;
        $sync_history_table = $wpdb->prefix . 'ck_sync_history';
        
        $sync_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $sync_history_table WHERE id = %d",
            $sync_id
        ), ARRAY_A);
        
        if (!$sync_entry) {
            wp_send_json_error('Sync entry not found');
            return;
        }
        
        $cookies_data = null;
        if (!empty($sync_entry['cookies_data'])) {
            $cookies_data = json_decode($sync_entry['cookies_data'], true);
        }
        
        wp_send_json_success([
            'cookies_data' => $cookies_data,
            'sync_time' => date('d/m/Y H:i:s', strtotime($sync_entry['sync_time'])),
            'sync_type' => $sync_entry['sync_type'],
            'status' => $sync_entry['status']
        ]);
    }
    
    /**
     * Render sync history table
     */
    private function render_sync_history_table() {
        // Get sync history (include extra rows so cookie-only filter still shows results)
        $history = wpccm_get_sync_history(25);

        // Keep only cookie-related entries (auto_cookie/newer and legacy auto/manual)
        $history = array_values(array_filter($history, function ($entry) {
            if (!isset($entry['sync_type'])) {
                return false;
            }

            return in_array($entry['sync_type'], ['manual', 'auto_cookie', 'auto'], true);
        }));

        // Limit to the 10 most recent cookie sync rows
        $history = array_slice($history, 0, 10);
        
        echo '<div style="margin-top: 30px;">';
        echo '<h3 style="margin: 0 0 15px 0; color: #1d2327;">📊 ' . esc_html(wpccm_text('cookie_sync_history_title')) . '</h3>';
        echo '<p style="margin: 0 0 15px 0; color: #50575e;">' . esc_html(wpccm_text('cookie_sync_history_description')) . '</p>';
        
        if (empty($history)) {
            echo '<div style="background: #f9f9f9; padding: 20px; border-radius: 4px; text-align: center; color: #666;">';
            echo '<div style="font-size: 36px; margin-bottom: 10px;">📋</div>';
            echo '<p style="margin: 0; font-size: 14px;">' . esc_html(wpccm_text('cookie_sync_history_empty_title')) . '</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 12px;">' . esc_html(wpccm_text('cookie_sync_history_empty_hint')) . '</p>';
            echo '</div>';
        } else {
            echo '<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; overflow: hidden;">';
            echo '<table class="widefat" style="margin: 0; border: none;">';
            echo '<thead>';
            echo '<tr style="background: #f6f7f7;">';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_time')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_type')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_status')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_cookies_found')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_new_cookies')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_execution_time')) . '</th>';
            echo '<th style="padding: 12px; font-weight: 600;">' . esc_html(wpccm_text('sync_column_details')) . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($history as $entry) {
                $sync_time = date('d/m/Y H:i', strtotime($entry['sync_time']));
                $sync_label = '👤 ' . wpccm_text('manual_sync_label');

                if ($entry['sync_type'] === 'auto_cookie' || $entry['sync_type'] === 'auto') {
                    $sync_label = '⏰ ' . wpccm_text('automatic_sync_label');
                }

                $status = $this->get_sync_status_display($entry['status']);
                $execution_time = $entry['execution_time']
                    ? sprintf(wpccm_text('execution_seconds'), number_format($entry['execution_time'], 3))
                    : wpccm_text('not_available');
                
                echo '<tr>';
                echo '<td style="padding: 10px;">' . esc_html($sync_time) . '</td>';
                echo '<td style="padding: 10px;">' . esc_html($sync_label) . '</td>';
                echo '<td style="padding: 10px;">' . $status . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . (int) $entry['total_cookies_found'] . '</td>';
                echo '<td style="padding: 10px; text-align: center;">';
                if ($entry['new_cookies_added'] > 0) {
                    echo '<strong style="color: #00a32a;">+' . (int) $entry['new_cookies_added'] . '</strong>';
                } else {
                    echo '<span style="color: #666;">0</span>';
                }
                echo '</td>';
                echo '<td style="padding: 10px; text-align: center; font-family: monospace; font-size: 12px;">' . esc_html($execution_time) . '</td>';
                echo '<td style="padding: 10px;">';
                
                if (!empty($entry['cookies_data'])) {
                    $cookies_data = json_decode($entry['cookies_data'], true);
                    if (is_array($cookies_data) && !empty($cookies_data)) {
                        echo '<button type="button" class="button button-small view-sync-details" data-sync-id="' . $entry['id'] . '" title="' . esc_attr(wpccm_text('view_new_cookies_details')) . '">';
                        echo '👁️ ' . esc_html(wpccm_text('view_details')) . ' (' . count($cookies_data) . ')';
                        echo '</button>';
                    }
                }
                
                if (!empty($entry['error_message'])) {
                    echo '<div style="margin-top: 5px; font-size: 11px; color: #d63384; background: #ffeaea; padding: 3px 6px; border-radius: 2px;">';
                    echo esc_html($entry['error_message']);
                    echo '</div>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // Add JavaScript for viewing details
        $this->add_sync_history_javascript();
    }
    
    /**
     * Get sync status display
     */
    private function get_sync_status_display($status) {
        switch ($status) {
            case 'success':
                return '<span style="color: #00a32a; font-weight: 600;">✅ ' . esc_html(wpccm_text('sync_status_success')) . '</span>';
            case 'error':
                return '<span style="color: #d63384; font-weight: 600;">❌ ' . esc_html(wpccm_text('sync_status_error')) . '</span>';
            case 'skipped':
                return '<span style="color: #dba617; font-weight: 600;">⏭️ ' . esc_html(wpccm_text('sync_status_skipped')) . '</span>';
            default:
                return '<span style="color: #666;">' . esc_html($status) . '</span>';
        }
    }
    
    /**
     * Add sync history JavaScript
     */
    private function add_sync_history_javascript() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // View sync details
            $('.view-sync-details').on('click', function() {
                const syncId = $(this).data('sync-id');
                const button = $(this);
                const originalText = button.text();
                
                button.text('⏳ טוען...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wpccm_get_sync_details',
                    sync_id: syncId,
                    _wpnonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>'
                }).done(function(response) {
                    if (response.success && response.data.cookies_data) {
                        showSyncDetailsModal(response.data.cookies_data, response.data.sync_time);
                    } else {
                        alert('לא ניתן לטעון פרטי סינכרון');
                    }
                }).fail(function() {
                    alert('שגיאה בטעינת פרטי סינכרון');
                }).always(function() {
                    button.text(originalText).prop('disabled', false);
                });
            });
            
            function showSyncDetailsModal(cookiesData, syncTime) {
                let modalHtml = '<div id="sync-details-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">';
                modalHtml += '<div style="background: white; border-radius: 8px; padding: 20px; max-width: 600px; max-height: 80vh; overflow-y: auto; position: relative;">';
                modalHtml += '<h3 style="margin: 0 0 15px 0;">🍪 עוגיות שנוספו בסינכרון</h3>';
                modalHtml += '<p style="margin: 0 0 15px 0; color: #666; font-size: 13px;">זמן סינכרון: ' + syncTime + '</p>';
                modalHtml += '<div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                modalHtml += '<table class="widefat" style="margin: 0;">';
                modalHtml += '<thead><tr><th>שם עוגיה</th><th>קטגוריה</th><th>ערך</th></tr></thead>';
                modalHtml += '<tbody>';
                
                cookiesData.forEach(function(cookie) {
                    const categoryBadge = getCategoryBadge(cookie.category);
                    const truncatedValue = cookie.value && cookie.value.length > 30 ? cookie.value.substring(0, 30) + '...' : (cookie.value || 'N/A');
                    
                    modalHtml += '<tr>';
                    modalHtml += '<td><strong>' + cookie.name + '</strong></td>';
                    modalHtml += '<td>' + categoryBadge + '</td>';
                    modalHtml += '<td><code style="font-size: 11px;">' + truncatedValue + '</code></td>';
                    modalHtml += '</tr>';
                });
                
                modalHtml += '</tbody></table>';
                modalHtml += '</div>';
                modalHtml += '<div style="margin-top: 15px; text-align: center;">';
                modalHtml += '<button type="button" class="button button-primary" onclick="closeSyncDetailsModal()">סגור</button>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                modalHtml += '</div>';
                
                $('body').append(modalHtml);
            }
            
            function getCategoryBadge(category) {
                // Get categories from PHP (will be loaded dynamically in future version)
                const categories = <?php 
                $categories = wpccm_get_categories();
                $categories_js = [];
                foreach ($categories as $cat) {
                    $categories_js[$cat['category_key']] = [
                        'name' => $cat['display_name'],
                        'color' => $cat['color'],
                        'icon' => $cat['icon'] ?? ''
                    ];
                }
                echo json_encode($categories_js);
                ?>;
                
                const cat = categories[category] || categories['others'] || { name: 'אחרים', color: '#666', icon: '' };
                const icon = cat.icon ? cat.icon + ' ' : '';
                return '<span style="background: ' + cat.color + '; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 600;">' + icon + cat.name + '</span>';
            }
            
            // Close modal function (global)
            window.closeSyncDetailsModal = function() {
                $('#sync-details-modal').remove();
            };
            
            // Close modal on background click
            $(document).on('click', '#sync-details-modal', function(e) {
                if (e.target === this) {
                    closeSyncDetailsModal();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for getting category details
     */
    public function ajax_get_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $category_id = (int) $_POST['category_id'];
        if (!$category_id) {
            wp_send_json_error('Invalid category ID');
            return;
        }
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ck_categories';
        
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $categories_table WHERE id = %d",
            $category_id
        ), ARRAY_A);
        
        if (!$category) {
            wp_send_json_error('Category not found');
            return;
        }
        
        wp_send_json_success($category);
    }
    
    /**
     * AJAX handler for saving category
     */
    public function ajax_save_category() {
        error_log('WPCCM: ajax_save_category called with data: ' . print_r($_POST, true));
        
        if (!current_user_can('manage_options')) {
            error_log('WPCCM: No access - user cannot manage options');
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            error_log('WPCCM: Security check failed - invalid nonce');
            wp_send_json_error('Security check failed');
            return;
        }
        
        $category_id = (int) $_POST['category_id'];
        $category_key = sanitize_key($_POST['category_key']);
        $display_name = sanitize_text_field($_POST['display_name']);
        $description = sanitize_textarea_field($_POST['description']);
        $color = sanitize_hex_color($_POST['color']);
        $icon = sanitize_text_field($_POST['icon']);
        $is_essential = (int) $_POST['is_essential'];
        
        if (empty($category_key) || empty($display_name)) {
            wp_send_json_error('מפתח הקטגוריה ושם התצוגה הם שדות חובה');
            return;
        }
        
        if (!$color) {
            $color = '#666666';
        }
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ck_categories';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'");
        if (!$table_exists) {
            error_log('WPCCM: Categories table does not exist: ' . $categories_table);
            wp_send_json_error('טבלת הקטגוריות לא קיימת. אנא כבה והפעל את הפלאגין.');
            return;
        }
        
        $data = [
            'category_key' => $category_key,
            'display_name' => $display_name,
            'description' => $description,
            'color' => $color,
            'icon' => $icon,
            'is_essential' => $is_essential,
            'is_active' => 1
        ];
        
        $formats = ['%s', '%s', '%s', '%s', '%s', '%d', '%d'];
        
        error_log('WPCCM: Attempting to save category with data: ' . print_r($data, true));
        
        if ($category_id) {
            // Update existing category
            error_log('WPCCM: Updating existing category ID: ' . $category_id);
            $result = $wpdb->update($categories_table, $data, ['id' => $category_id], $formats, ['%d']);
        } else {
            // Check if category key already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $categories_table WHERE category_key = %s",
                $category_key
            ));
            
            error_log('WPCCM: Checking existing categories for key "' . $category_key . '": ' . $existing);
            
            if ($existing > 0) {
                error_log('WPCCM: Category key already exists');
                wp_send_json_error('מפתח הקטגוריה כבר קיים');
                return;
            }
            
            // Insert new category
            error_log('WPCCM: Inserting new category');
            $result = $wpdb->insert($categories_table, $data, $formats);
        }
        
        error_log('WPCCM: Database operation result: ' . print_r($result, true));
        error_log('WPCCM: Last database error: ' . $wpdb->last_error);
        
        if ($result === false) {
            error_log('WPCCM: Database operation failed');
            wp_send_json_error('שגיאה בשמירת הקטגוריה: ' . $wpdb->last_error);
            return;
        }
        
        error_log('WPCCM: Category saved successfully');
        wp_send_json_success('הקטגוריה נשמרה בהצלחה');
    }
    
    /**
     * AJAX handler for deleting category
     */
    public function ajax_delete_category() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }
        
        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $category_id = (int) $_POST['category_id'];
        if (!$category_id) {
            wp_send_json_error('Invalid category ID');
            return;
        }
        
        global $wpdb;
        $categories_table = $wpdb->prefix . 'ck_categories';
        
        // Check if category is essential
        $category = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $categories_table WHERE id = %d",
            $category_id
        ), ARRAY_A);
        
        if (!$category) {
            wp_send_json_error('Category not found');
            return;
        }
        
        if ($category['is_essential']) {
            wp_send_json_error('לא ניתן למחוק קטגוריה חיונית');
            return;
        }
        
        // Check if category is used by cookies
        $cookies_table = $wpdb->prefix . 'ck_cookies';
        $cookies_using_category = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $cookies_table WHERE category = %s AND is_active = 1",
            $category['category_key']
        ));
        
        if ($cookies_using_category > 0) {
            wp_send_json_error('לא ניתן למחוק קטגוריה שבשימוש על ידי עוגיות פעילות');
            return;
        }
        
        // Delete the category
        $result = $wpdb->delete($categories_table, ['id' => $category_id], ['%d']);
        
        if ($result === false) {
            wp_send_json_error('שגיאה במחיקת הקטגוריה');
            return;
        }
        
        wp_send_json_success('הקטגוריה נמחקה בהצלחה');
    }
    
    /**
     * AJAX handler for checking categories table
     */
    // public function ajax_check_categories_table() {
    //     if (!current_user_can('manage_options')) {
    //         wp_send_json_error('No access');
    //         return;
    //     }
        
    //     global $wpdb;
    //     $categories_table = $wpdb->prefix . 'ck_categories';
        
    //     // Check if table exists
    //     $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'");
        
    //     if (!$table_exists) {
    //         wp_send_json_error('טבלת הקטגוריות לא קיימת! אנא כבה והפעל את הפלאגין.');
    //         return;
    //     }
        
    //     // Get table structure
    //     $table_structure = $wpdb->get_results("DESCRIBE $categories_table");
        
    //     // Get categories count
    //     $categories_count = $wpdb->get_var("SELECT COUNT(*) FROM $categories_table");
        
    //     $message = "✅ טבלת הקטגוריות קיימת!\n";
    //     $message .= "📊 מספר קטגוריות: $categories_count\n";
    //     $message .= "🏗️ מבנה הטבלה: " . count($table_structure) . " עמודות\n";
    //     $message .= "📋 עמודות: " . implode(', ', array_column($table_structure, 'Field'));
        
    //     wp_send_json_success($message);
    // }
    
    /**
     * Render a single category row in the new categories table
     */
    private function render_new_category_row($category) {
        $essential_badge = $category['is_essential']
            ? '<span style="background: #d63384; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' . esc_html(wpccm_text('category_essential')) . '</span>'
            : '<span style="color: #666;">' . esc_html(wpccm_text('not_essential')) . '</span>';
        $active_badge = $category['is_active']
            ? '<span style="background: #00a32a; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' . esc_html(wpccm_text('category_active')) . '</span>'
            : '<span style="background: #666; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' . esc_html(wpccm_text('inactive')) . '</span>';
        $icon = !empty($category['icon']) ? $category['icon'] : '📦';
        
        echo '<tr>';
        echo '<td style="padding: 10px;"><code>' . esc_html($category['category_key']) . '</code></td>';
        echo '<td style="padding: 10px;"><strong>' . esc_html($category['display_name']) . '</strong></td>';
        echo '<td style="padding: 10px;">' . esc_html($category['description'] ?: wpccm_text('no_description')) . '</td>';
        echo '<td style="padding: 10px; text-align: center;">';
        echo '<div style="width: 20px; height: 20px; background: ' . esc_attr($category['color']) . '; border-radius: 3px; margin: 0 auto; border: 1px solid #ddd;"></div>';
        echo '</td>';
        echo '<td style="padding: 10px; text-align: center; font-size: 18px;">' . $icon . '</td>';
        echo '<td style="padding: 10px; text-align: center;">' . $essential_badge . '</td>';
        echo '<td style="padding: 10px; text-align: center;">' . $active_badge . '</td>';
        echo '<td style="padding: 10px;">';
        echo '<button type="button" class="button button-small edit-category-btn" data-category-id="' . $category['id'] . '" title="' . esc_attr(wpccm_text('edit_category')) . '">✏️ ' . esc_html(wpccm_text('edit')) . '</button> ';
        if (!$category['is_essential']) {
            echo '<button type="button" class="button button-small delete-category-btn" data-category-id="' . $category['id'] . '" title="' . esc_attr(wpccm_text('delete_category_label')) . '" style="color: #d63384;">🗑️ ' . esc_html(wpccm_text('delete')) . '</button>';
        }
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Add category management modal
     */
    private function add_category_management_modal() {
        ?>
        <!-- Category Management Modal -->
        <div id="category-management-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); min-width: 500px; max-height: 80vh; overflow-y: auto;">
                <h3 id="modal-title" style="margin-top: 0;"><?php echo esc_html(wpccm_text('category_modal_add_title')); ?></h3>
                
                <form id="category-form">
                    <input type="hidden" id="category-id" value="">
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html(wpccm_text('category_key_label')); ?></label>
                        <input type="text" id="category-key" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="<?php echo esc_attr(wpccm_text('key_placeholder')); ?>" required>
                        <small style="color: #666;"><?php echo esc_html(wpccm_text('category_key_help')); ?></small>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html(wpccm_text('category_display_name_label')); ?></label>
                        <input type="text" id="category-name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="<?php echo esc_attr(wpccm_text('name_placeholder')); ?>" required>
                    </div>
                    
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html(wpccm_text('category_description_label')); ?></label>
                        <textarea id="category-description" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; height: 80px;" placeholder="<?php echo esc_attr(wpccm_text('description_placeholder')); ?>"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html(wpccm_text('category_color_label')); ?></label>
                            <input type="color" id="category-color" style="width: 100%; padding: 4px; border: 1px solid #ddd; border-radius: 4px; height: 40px;" value="#666666">
                        </div>
                        <div style="flex: 1;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600;"><?php echo esc_html(wpccm_text('category_icon_label')); ?></label>
                            <input type="text" id="category-icon" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="<?php echo esc_attr(wpccm_text('category_icon_placeholder')); ?>" maxlength="2">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="category-essential"> 
                            <strong><?php echo esc_html(wpccm_text('category_essential_label')); ?></strong>
                            <small style="color: #666;"><?php echo esc_html(wpccm_text('category_essential_hint')); ?></small>
                        </label>
                    </div>
                    
                    <div style="text-align: left;">
                        <button type="button" id="save-category-btn" class="button button-primary" style="margin-left: 10px;"><?php echo esc_html(wpccm_text('save')); ?></button>
                        <button type="button" id="cancel-category-modal" class="button"><?php echo esc_html(wpccm_text('cancel')); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Open modal when edit button clicked
            $(document).on("click", ".edit-category-btn", function() {
                var cookieName = $(this).data("cookie");
                var currentCategory = $(this).data("category");
                
                $("#edit-cookie-name").text(cookieName);
                $("#edit-category-select").val(currentCategory);
                $("#category-edit-modal").data("cookie-name", cookieName).show();
            });

            // Close modal when cancel clicked
            $("#cancel-category-btn, .category-edit-overlay").on("click", function(e) {
                if (e.target === this) {
                    $("#category-edit-modal").hide();
                }
            });

            // Save category
            $("#save-category-btn").on("click", function() {
                console.log("Save category button clicked");
                
                // Define ajaxurl and nonce for this function
                const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                const nonce = '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>';
                
                // Get form data
                const formData = {
                    action: 'wpccm_save_category',
                    category_id: $('#category-id').val(),
                    category_key: $('#category-key').val(),
                    display_name: $('#category-name').val(),
                    description: $('#category-description').val(),
                    color: $('#category-color').val(),
                    icon: $('#category-icon').val(),
                    is_essential: $('#category-essential').prop('checked') ? 1 : 0,
                    _wpnonce: nonce
                };
                
                console.log('Form data prepared:', formData);
                
                // Show loading state
                const submitBtn = $(this);
                const originalText = submitBtn.text();
                submitBtn.text('🔄 ' + <?php echo json_encode(wpccm_text('saving')); ?>).prop('disabled', true);
                
                // Send AJAX request
                $.post(ajaxurl, formData).done(function(response) {
                    console.log('Save category response:', response);
                    if (response.success) {
                        $('#category-management-modal').hide();
                        location.reload(); // Refresh to show changes
                    } else {
                        const errPrefix = <?php echo json_encode(wpccm_text('error_saving_category')); ?> || 'Error saving category:';
                        const unknownErr = <?php echo json_encode(wpccm_text('unknown_error')); ?> || 'Unknown error';
                        alert(errPrefix + ' ' + (response.data || unknownErr));
                        submitBtn.text(originalText).prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    const errPrefix = <?php echo json_encode(wpccm_text('error_saving_category')); ?> || 'Error saving category:';
                    alert(errPrefix + ' ' + error);
                    submitBtn.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add JavaScript for new categories management
     */
    private function enqueue_new_categories_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            
            // Check if wpccm_ajax is defined, fallback to manual setup
            let ajaxurl, nonce;
            if (typeof wpccm_ajax !== 'undefined') {
                ajaxurl = wpccm_ajax.ajaxurl;
                nonce = wpccm_ajax.nonce;
                console.log('Using wpccm_ajax:', wpccm_ajax);
            } else {
                console.warn('wpccm_ajax not defined, using fallback');
                ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
                nonce = '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>';
            }
            console.log('Using ajaxurl:', ajaxurl);
            
            // Add new category
            $('#wpccm-add-category').on('click', function() {
                
                $('#modal-title').text(<?php echo json_encode(wpccm_text('category_modal_add_title')); ?>);
                
                // Reset form fields manually instead of using reset()
                $('#category-id').val('');
                $('#category-key').val('');
                $('#category-name').val('');
                $('#category-description').val('');
                $('#category-color').val('#666666');
                $('#category-icon').val('');
                $('#category-essential').prop('checked', false);
                
                console.log('About to show modal');
                $('#category-management-modal').show();
                console.log('Modal show() called');
            });
            
            // Edit category
            $(document).on('click', '.edit-category-btn', function() {
                const categoryId = $(this).data('category-id');
                
                $('#modal-title').text('ערוך קטגוריה');
                $('#category-id').val(categoryId);
                
                // Load category data via AJAX
                $.post(ajaxurl, {
                    action: 'wpccm_get_category',
                    category_id: categoryId,
                    _wpnonce: nonce
                }).done(function(response) {
                    if (response.success) {
                        const cat = response.data;
                        $('#category-key').val(cat.category_key);
                        $('#category-name').val(cat.display_name);
                        $('#category-description').val(cat.description || '');
                        $('#category-color').val(cat.color);
                        $('#category-icon').val(cat.icon || '');
                        $('#category-essential').prop('checked', cat.is_essential == 1);
                        $('#category-management-modal').show();
                    } else {
                        alert('שגיאה בטעינת נתוני הקטגוריה');
                    }
                });
            });
            
            // Delete category
            $(document).on('click', '.delete-category-btn', function() {
                if (!confirm('האם אתה בטוח שברצונך למחוק קטגוריה זו?')) {
                    return;
                }
                
                const categoryId = $(this).data('category-id');
                const button = $(this);
                const originalText = button.text();
                
                button.text('🔄 מוחק...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'wpccm_delete_category',
                    category_id: categoryId,
                    _wpnonce: nonce
                }).done(function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('שגיאה במחיקת הקטגוריה: ' + (response.data || 'שגיאה לא ידועה'));
                        button.text(originalText).prop('disabled', false);
                    }
                }).fail(function() {
                    alert('שגיאה במחיקת הקטגוריה');
                    button.text(originalText).prop('disabled', false);
                });
            });
            
            // Save category
            $('#category-form').on('submit', function(e) {
                e.preventDefault();
                
                console.log('Form submitted, preparing data...');
                
                const formData = {
                    action: 'wpccm_save_category',
                    category_id: $('#category-id').val(),
                    category_key: $('#category-key').val(),
                    display_name: $('#category-name').val(),
                    description: $('#category-description').val(),
                    color: $('#category-color').val(),
                    icon: $('#category-icon').val(),
                    is_essential: $('#category-essential').prop('checked') ? 1 : 0,
                    _wpnonce: nonce
                };
                
                console.log('Form data prepared:', formData);
                
                // Show loading state
                const submitBtn = $('#category-form button[type="submit"]');
                const originalText = submitBtn.text();
                submitBtn.text('🔄 שומר...').prop('disabled', true);
                
                console.log('Sending AJAX request to:', ajaxurl);
                console.log('Request data:', formData);
                
                $.post(ajaxurl, formData).done(function(response) {
                    console.log('Save category response:', response);
                    if (response.success) {
                        $('#category-management-modal').hide();
                        // location.reload(); // Refresh to show changes
                    } else {
                        alert('שגיאה בשמירת הקטגוריה: ' + (response.data || 'שגיאה לא ידועה'));
                        submitBtn.text(originalText).prop('disabled', false);
                    }
                }).fail(function(xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    alert('שגיאה בשמירת הקטגוריה: ' + error);
                    submitBtn.text(originalText).prop('disabled', false);
                });
            });
            
            // Cancel modal
            $('#cancel-category-modal').on('click', function() {
                $('#category-management-modal').hide();
            });
            
            // Close modal when clicking on background
            $('#category-management-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#category-management-modal').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    
    /**
     * AJAX handler for saving design settings
     */
    public function ajax_save_design_settings() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        // Get and sanitize design settings
        $banner_position = isset($_POST['banner_position']) ? sanitize_text_field($_POST['banner_position']) : 'top';
        $floating_button_position = isset($_POST['floating_button_position']) ? sanitize_text_field($_POST['floating_button_position']) : 'bottom-right';
        $background_color = isset($_POST['background_color']) ? sanitize_text_field($_POST['background_color']) : '#ffffff';
        $text_color = isset($_POST['text_color']) ? sanitize_text_field($_POST['text_color']) : '#000000';
        $accept_button_color = isset($_POST['accept_button_color']) ? sanitize_text_field($_POST['accept_button_color']) : '#0073aa';
        $reject_button_color = isset($_POST['reject_button_color']) ? sanitize_text_field($_POST['reject_button_color']) : '#6c757d';
        $settings_button_color = isset($_POST['settings_button_color']) ? sanitize_text_field($_POST['settings_button_color']) : '#28a745';
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : 'medium';
        
        // Validate values
        if (!in_array($banner_position, ['top', 'bottom'])) {
            $banner_position = 'top';
        }
        
        if (!in_array($floating_button_position, ['top-right', 'top-left', 'bottom-right', 'bottom-left'])) {
            $floating_button_position = 'bottom-right';
        }
        
        // Validate text color (only black or white)
        if (!in_array($text_color, ['#000000', '#ffffff'])) {
            $text_color = '#000000';
        }
        
        if (!in_array($size, ['small', 'medium', 'large'])) {
            $size = 'medium';
        }
        
        // Save design settings
        $current_options = get_option('wpccm_options', []);
        $current_options['design'] = [
            'banner_position' => $banner_position,
            'floating_button_position' => $floating_button_position,
            'background_color' => $background_color,
            'text_color' => $text_color,
            'accept_button_color' => $accept_button_color,
            'reject_button_color' => $reject_button_color,
            'settings_button_color' => $settings_button_color,
            'size' => $size
        ];
        
        // Debug logging
        error_log('WPCCM Debug: About to save options: ' . print_r($current_options, true));
        
        update_option('wpccm_options', $current_options);
        
        wp_send_json_success([
            'message' => sprintf(
                wpccm_text('design_settings_saved'),
                $banner_position,
                $floating_button_position,
                $size
            ),
            'saved_data' => [
                'banner_position' => $banner_position,
                'floating_button_position' => $floating_button_position,
                'background_color' => $background_color,
                'text_color' => $text_color,
                'accept_button_color' => $accept_button_color,
                'reject_button_color' => $reject_button_color,
                'settings_button_color' => $settings_button_color,
                'size' => $size
            ]
        ]);
    }

    /**
     * AJAX handler for forcing complete reschedule (clear all and reschedule immediately)
     */
    public function ajax_force_reschedule() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Clear ALL existing cron jobs completely
        wp_clear_scheduled_hook('wpccm_auto_cookie_sync');
        wp_clear_scheduled_hook('wpccm_auto_script_sync');

        // Force enable both sync types
        update_option('wpccm_auto_sync_enabled', true);
        update_option('wpccm_auto_script_sync_enabled', true);
        update_option('wpccm_auto_form_sync_enabled', true);

        // Schedule new ones starting in 30 seconds (almost immediately)
        $current_time = current_time('timestamp', true);
        $next_run = $current_time + 30; // Start in 30 seconds

        // Frontend auto-sync is now used instead of wp_schedule_event
        // wp_schedule_event($next_run, 'wpccm_every_minute', 'wpccm_auto_cookie_sync');
        // wp_schedule_event($next_run, 'wpccm_every_minute', 'wpccm_auto_script_sync');

        wpccm_debug_log('Force rescheduled sync to start immediately', [
            'current_time' => date('Y-m-d H:i:s', $current_time),
            'next_run' => date('Y-m-d H:i:s', $next_run)
        ]);

        wp_send_json_success([
            'message' => 'הסינכרון רץ בפרונט כל ' . get_option('wpccm_sync_interval_minutes', 60) . ' דקות - אין צורך בתזמון מחדש',
            'sync_method' => 'frontend'
        ]);
    }

    /**
     * AJAX handler for changing sync interval
     */
    public function ajax_change_sync_interval() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No access');
            return;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        $minutes = intval($_POST['minutes']);
        if ($minutes < 1 || $minutes > 60) {
            wp_send_json_error('Invalid interval - must be between 1-60 minutes');
            return;
        }

        // Save the new interval
        update_option('wpccm_sync_interval_minutes', $minutes);

        // Always enable sync
        update_option('wpccm_auto_sync_enabled', true);
        update_option('wpccm_auto_script_sync_enabled', true);

        // Clear existing cron jobs
        wp_clear_scheduled_hook('wpccm_auto_cookie_sync');
        wp_clear_scheduled_hook('wpccm_auto_script_sync');

        // Schedule new ones with the new interval
        // wpccm_schedule_cookie_sync();
        // wpccm_schedule_script_sync();

        $interval_text = $minutes == 1 ? 'דקה' : $minutes . ' דקות';

        wpccm_debug_log('Sync interval changed', [
            'minutes' => $minutes,
            'interval_text' => $interval_text
        ]);

        $next_cookie_run = wp_next_scheduled('wpccm_auto_cookie_sync');
        $next_script_run = wp_next_scheduled('wpccm_auto_script_sync');
        $next_run = $next_cookie_run && $next_script_run ? min($next_cookie_run, $next_script_run) : ($next_cookie_run ?: $next_script_run);

        wp_send_json_success([
            'message' => 'תדירות הסינכרון שונתה ל' . $interval_text . ' - הסינכרון הבא יתבצע בקרוב',
            'next_run_timestamp' => $next_run,
            'next_run_formatted' => $next_run ? wp_date('Y-m-d H:i:s', $next_run) : null,
            'interval_minutes' => $minutes
        ]);
    }

    /**
     * AJAX handler for frontend auto sync (no admin permissions required)
     */
    public function ajax_frontend_auto_sync() {
        // Check if plugin is activated
        if (!WP_CCM_Consent::is_plugin_activated()) {
            wp_send_json_error('Plugin not activated');
            return;
        }

        // Check if auto sync is enabled
        if (!get_option('wpccm_auto_sync_enabled', false) && !get_option('wpccm_auto_script_sync_enabled', false) && !get_option('wpccm_auto_form_sync_enabled', false)) {
            wp_send_json_error('Auto sync disabled');
            return;
        }

        try {
            $results = [];

            // Run cookie sync if enabled
            if (get_option('wpccm_auto_sync_enabled', false)) {
                wpccm_perform_auto_cookie_sync();
                $results[] = 'cookies';
            }

            // Run script sync if enabled
            if (get_option('wpccm_auto_script_sync_enabled', false)) {
                wpccm_perform_auto_script_sync();
                $results[] = 'scripts';
            }

            if (get_option('wpccm_auto_form_sync_enabled', false)) {
                wpccm_perform_auto_form_sync();
                $results[] = 'forms';
            }

            wp_send_json_success([
                'synced' => $results,
                'timestamp' => current_time('Y-m-d H:i:s')
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Render sync page for cookies and scripts
     */
}

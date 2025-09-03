<?php
if (!defined('ABSPATH')) { exit; }

class WP_CCM_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers for management page
        add_action('wp_ajax_wpccm_get_consent_stats', [$this, 'ajax_get_consent_stats']);
        add_action('wp_ajax_wpccm_get_consent_history', [$this, 'ajax_get_consent_history']);
        add_action('wp_ajax_wpccm_export_consent_history', [$this, 'ajax_export_consent_history']);
        add_action('wp_ajax_wpccm_delete_data_manually', [$this, 'ajax_delete_data_manually']);
        
        // AJAX handlers for advanced scanner (moved from deleted CC_Detect_Page class)
        add_action('wp_ajax_cc_detect_save_map', [$this, 'ajax_cc_detect_save_map']);
        add_action('wp_ajax_cc_detect_store', [$this, 'ajax_cc_detect_store']);
        add_action('wp_ajax_cc_detect_delete_mapping', [$this, 'ajax_cc_detect_delete_mapping']);
        add_action('wp_ajax_cc_detect_get_registered_scripts', [$this, 'ajax_cc_detect_get_registered_scripts']);

        add_action('wp_ajax_wpccm_get_debug_log', [$this, 'ajax_get_debug_log']);
        
        // AJAX handlers for master code
        add_action('wp_ajax_wpccm_save_master_code', [$this, 'ajax_save_master_code']);
        add_action('wp_ajax_wpccm_remove_master_code', [$this, 'ajax_remove_master_code']);
        
        // AJAX handler for saving general settings
        add_action('wp_ajax_wpccm_save_general_settings', [$this, 'ajax_save_general_settings']);
        
        // AJAX handler for saving design settings
        add_action('wp_ajax_wpccm_save_design_settings', [$this, 'ajax_save_design_settings']);
        
        // AJAX handler for getting frontend cookies
        add_action('wp_ajax_wpccm_get_frontend_cookies', [$this, 'ajax_get_frontend_cookies']);
        add_action('wp_ajax_nopriv_wpccm_get_frontend_cookies', [$this, 'ajax_get_frontend_cookies']);
        
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
        
        // NEW: Management & Statistics submenu
        add_submenu_page(
            'wpccm',
            'ניהול עוגיות וסטטיסטיקות',
            'ניהול וסטטיסטיקות',
            'manage_options',
            'wpccm-management',
            [$this, 'render_management_page']
        );
        
        // NEW: Data Deletion Management submenu
        add_submenu_page(
            'wpccm',
            'ניהול מחיקת נתונים',
            'ניהול מחיקה',
            'manage_options',
            'wpccm-deletion',
            [$this, 'render_deletion_page']
        );
        
        // NEW: Activity History submenu
        add_submenu_page(
            'wpccm',
            'היסטוריית פעילות',
            'היסטוריית פעילות',
            'manage_options',
            'wpccm-history',
            [$this, 'render_history_page']
        );
        

        
        // Dashboard Integration is now part of General Settings
        

        
        // Cookie scanner/viewer submenu
        add_submenu_page(
            'options-general.php',
            wpccm_text('cookie_scanner'),
            wpccm_text('cookie_scanner'),
            'manage_options',
            'wpccm-scanner',
            [$this, 'render_scanner_page']
        );
    }
    
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        $plugin_pages = [
            'settings_page_wpccm', 
            'settings_page_wpccm-scanner', 
            'settings_page_wpccm-management', 
            'settings_page_wpccm-deletion', 
            'settings_page_wpccm-history',
            'cookie-consent_page_wpccm-advanced-scanner'
        ];
        
        if (!in_array($hook, $plugin_pages)) {
            return;
        }
        
        // Enqueue jQuery first
        wp_enqueue_script('jquery');
        
        // Enqueue CSS for admin tables
        wp_enqueue_style('wpccm-admin', WPCCM_URL . 'assets/css/consent.css', [], WPCCM_VERSION);

        // Enqueue Chart.js for statistics
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        
        // Debug: Log the current hook
        error_log('WPCCM: Current hook: ' . $hook);
        
        // For advanced scanner page, log debug info
        if ($hook === 'cookie-consent_page_wpccm-advanced-scanner') {
            error_log('WPCCM: Advanced scanner page loaded');
        }
    }
    
    public function settings_init() {
        register_setting('wpccm_group', 'wpccm_options', [$this, 'sanitize_options']);
        
        // Dashboard Connection Settings (separate options)
        register_setting('wpccm_dashboard_group', 'wpccm_dashboard_api_url');
        register_setting('wpccm_dashboard_group', 'wpccm_license_key');
        // register_setting('wpccm_dashboard_group', 'wpccm_website_id');
        
        // Master Code Settings
        register_setting('wpccm_dashboard_group', 'wpccm_master_code');
        register_setting('wpccm_dashboard_group', 'wpccm_stored_master_code');
        
        // Advanced Scanner Settings (for script mappings)
        register_setting('wpccm_advanced_scanner_group', 'wpccm_script_handle_map');
        register_setting('wpccm_advanced_scanner_group', 'wpccm_script_handle_map_categories');
        
        // Add a custom handler for the advanced scanner form processing
        add_action('admin_init', [$this, 'handle_advanced_scanner_save']);
        
        // Tab 1: Activation & Dashboard Connection
        add_settings_section('wpccm_dashboard_connection', 'אקטיבציה', [$this, 'dashboard_connection_section_callback'], 'wpccm_general');
        add_settings_field('dashboard_api_url', 'כתובת API של הדשבורד', [$this, 'field_dashboard_api_url'], 'wpccm_general', 'wpccm_dashboard_connection');
        add_settings_field('dashboard_license_key', 'מפתח רישיון', [$this, 'field_dashboard_license_key'], 'wpccm_general', 'wpccm_dashboard_connection');
        add_settings_field('dashboard_test_connection', 'בדיקת חיבור', [$this, 'field_dashboard_test_connection'], 'wpccm_general', 'wpccm_dashboard_connection');
        add_settings_field('dashboard_master_code', 'קוד מאסטר (אופציונלי)', [$this, 'field_dashboard_master_code'], 'wpccm_general', 'wpccm_dashboard_connection');
        
        // Tab 1: General Settings
        add_settings_section('wpccm_general', 'הגדרות כלליות', null, 'wpccm_general');
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
            <h1><?php echo wpccm_text('cookie_consent_manager'); ?></h1>
            
            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper wpccm-tabs">
                <a href="#general" class="nav-tab nav-tab-active" data-tab="general">הגדרות כלליות</a>
                <a href="#design" class="nav-tab" data-tab="design">הגדרות עיצוב</a>
                <a href="#purge" class="nav-tab" data-tab="purge">מיפוי עוגיות</a>
                <a href="#mapping" class="nav-tab" data-tab="mapping">מיפוי סקריפטים</a>
                <a href="#categoriess" class="nav-tab" data-tab="categoriess">קטגוריות</a>
            </nav>
            
            <form method="post" action="options.php">
                <?php settings_fields('wpccm_group'); ?>
                <?php settings_fields('wpccm_dashboard_group'); ?>
                
                <!-- Tab Content -->
                <div id="general" class="wpccm-tab-content active">
                    
                    <?php do_settings_sections('wpccm_general'); ?>
                    <p class="submit">
                        <button type="button" class="button-primary" id="save-general-settings">שמור הגדרות כלליות</button>
                        <span id="general-settings-result" style="margin-left: 10px;"></span>
                    </p>
                </div>

                <!-- Tab Content -->
                <div id="design" class="wpccm-tab-content">
                    
                    <?php 
                    try {
                        $this->render_design_tab(); 
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת הגדרות עיצוב: ' . $e->getMessage() . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת הגדרות עיצוב: ' . $e->getMessage() . '</p></div>';
                    }
                    ?>
                    <p class="submit">
                        <button type="button" class="button-primary" id="save-design-settings">שמור הגדרות עיצוב</button>
                        <button type="button" class="button" id="reset-design-settings" style="margin-right: 10px;">הגדרות ברירת מחדל</button>
                        <span id="design-settings-result" style="margin-left: 10px;"></span>
                    </p>
                </div>
                
                <div id="purge" class="wpccm-tab-content">
                    <?php 
                    try {
                        $this->render_purge_tab(); 
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת מחיקת עוגיות: ' . $e->getMessage() . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת מחיקת עוגיות: ' . $e->getMessage() . '</p></div>';
                    }
                    ?>
                    <p class="submit">
                        <input type="submit" name="save_purge_settings" class="button-primary" value="שמור הגדרות מחיקת עוגיות" />
                    </p>
                </div>
                
                <div id="mapping" class="wpccm-tab-content">
                    <!-- <h2>מיפוי סקריפטים</h2> -->
                    <?php $this->render_mapping_tab(); ?>
                </div>

                </div>
                
                <div id="categoriess" class="wpccm-tab-content">
                    <h2>קטגוריות עוגיות</h2>
                    <p>תוכן טאב קטגוריות עוגיות</p>
                    <?php 
                    
                    try {
                        
                        $this->render_categories_tab(); 
                        
                    } catch (Exception $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת קטגוריות: ' . $e->getMessage() . '</p></div>';
                    } catch (Error $e) {
                        echo '<div class="notice notice-error"><p>שגיאה בטעינת קטגוריות: ' . $e->getMessage() . '</p></div>';
                    }
                    ?>
                    <p class="submit">
                        <input type="submit" name="save_categories_settings" class="button-primary" value="שמור הגדרות קטגוריות" />
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
                
                var masterCode = $('input[name="wpccm_master_code"]').val();
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
                
                // Check if master code is provided and valid
                var skipDashboard = masterCode === '56588486';
                
                // Validate required fields (skip dashboard validation if master code is valid)
                if (!skipDashboard && (!licenseKey)) {
                    //console.log('WPCCM: Validation failed - missing activation fields');
                    $result.html('<span class="error">✗ אנא מלא את כל שדות האקטיבציה (כתובת API, מפתח רישיון, מזהה אתר)</span>');
                    return;
                }
                
                if (!bannerTitle || !bannerDescription) {
                    //console.log('WPCCM: Validation failed - missing banner fields');
                    $result.html('<span class="error">✗ אנא מלא את כותרת הבאנר ותיאור הבאנר</span>');
                    return;
                }
                
                //console.log('WPCCM: Validation passed, proceeding with save');
                
                // Disable button and show loading
                $button.prop('disabled', true).text('שומר...');
                $result.html('<span class="loading">שומר הגדרות...</span>');
                
                // Prepare form data
                var formData = {
                    action: 'wpccm_save_general_settings',
                    nonce: '<?php echo wp_create_nonce('wpccm_admin_nonce'); ?>',
                    license_key: licenseKey,
                    master_code: masterCode,
                    skip_dashboard: skipDashboard,
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
                        $result.html('<span class="error">✗ שגיאה בשמירת ההגדרות</span>');
                    },
                    complete: function() {
                        // Re-enable button
                        $button.prop('disabled', false).text('שמור הגדרות כלליות');
                    }
                });
            });
            
            // Reset design settings to defaults
            $('#reset-design-settings').on('click', function() {
                if (confirm('האם אתה בטוח שברצונך לאפס את כל הגדרות העיצוב לברירת המחדל?')) {
                    // Reset all form fields to default values
                    $('#banner_position').val('top');
                    $('#floating_button_position').val('bottom-right');
                    $('#background_color').val('#ffffff');
                    $('#text_color').val('#000000');
                    $('#accept_button_color').val('#0073aa');
                    $('#reject_button_color').val('#6c757d');
                    $('#settings_button_color').val('#28a745');
                    $('#size').val('medium');
                    
                    // Update preview immediately
                    updatePreviewDefault();
                    
                    // // Update preview from general settings as well
                    // if (typeof updatePreviewFromGeneralSettings === 'function') {
                    //     updatePreviewFromGeneralSettings();
                    // }
                    
                    // Show success message
                    $('#design-settings-result').html('<span class="success">✓ הוחזרו הגדרות ברירת המחדל</span>');
                    
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
                var bannerPosition = $('#banner_position').val();
                var floatingButtonPosition = $('#floating_button_position').val();
                var backgroundColor = $('#background_color').val();
                var textColor = $('#text_color').val();
                var acceptButtonColor = $('#accept_button_color').val();
                var rejectButtonColor = $('#reject_button_color').val();
                var settingsButtonColor = $('#settings_button_color').val();
                var size = $('#size').val();
                
                // Disable button and show loading
                $button.prop('disabled', true).text('שומר...');
                $result.html('<span class="loading">שומר הגדרות עיצוב...</span>');
                
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
                        $result.html('<span class="error">✗ שגיאה בשמירת הגדרות העיצוב</span>');
                    },
                    complete: function() {
                        // Re-enable button
                        $button.prop('disabled', false).text('שמור הגדרות עיצוב');
                    }
                });
            });

            function updatePreviewDefault() {
                // console.log("WPCCM: updatePreview555555");
                var bgColor = $("#background_color").val();
                var textColor = $("#text_color").val();
                var acceptButtonColor = $("#accept_button_color").val();
                var rejectButtonColor = $("#reject_button_color").val();
                var settingsButtonColor = $("#settings_button_color").val();
                var bannerPosition = $("#banner_position").val();
                var floatingButtonPosition = $("#floating_button_position").val();
                var size = $("#size").val();
                
                // Update colors
                $("#wpccm-banner-preview").css({
                    "background-color": bgColor,
                    "color": textColor
                });
                
                // Update button colors
                $("#wpccm-banner-preview button:first").css("background-color", acceptButtonColor); // Accept button
                $("#wpccm-banner-preview button:nth-child(2)").css("color", textColor).css("border-color", textColor); // Reject button
                $("#wpccm-banner-preview button:last").css("background-color", settingsButtonColor); // Settings button
                
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
                
                $("#wpccm-banner-preview button").css({
                    "padding": buttonPadding,
                    "font-size": fontSize
                });
                
                // Update banner position indicator
                $("#wpccm-banner-preview").attr("data-position", bannerPosition);
                
                // Update floating button position indicator
                $("#wpccm-banner-preview").attr("data-floating-position", floatingButtonPosition);
                
                // Update info text
                var positionText = bannerPosition === "top" ? "בראש הדף" : "בתחתית הדף";
                $("#preview-position").text(positionText);
                $("#preview-floating-position").text(floatingButtonPosition);
                $("#preview-size").text(size);
                
                console.log("WPCCM: Preview updated - BG:", bgColor, "Text:", textColor, "Accept:", acceptButtonColor, "Reject:", rejectButtonColor, "Settings:", settingsButtonColor, "Size:", size, "Position:", bannerPosition);
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

    /**
     * Render advanced scanner section
     */
    private function render_advanced_scanner_section() {
        // Get current mappings
        $script_mappings = get_option('cc_script_handle_map', array()); 
        $domain_mappings = get_option('cc_script_domain_map', array());
        $all_script_mappings = array_merge($script_mappings, $domain_mappings);
        


        $categories = array(
            'necessary' => 'הכרחיות',
            'functional' => 'פונקציונליות',
            'performance' => 'ביצועים',
            'analytics' => 'אנליטיקס',
            'marketing' => 'שיווק',
            'others' => 'אחרות'
        );

        // error_log('WPCCM Debug: About to include advanced-scanner-page.php');
        include WPCCM_PATH . 'admin/views/advanced-scanner-page.php';
        // error_log('WPCCM Debug: advanced-scanner-page.php included successfully');
    }
    
    /**
     * Render categories management tab content
     */
    private function render_mapping_tab() {
        // Include the advanced scanner content
        $this->render_advanced_scanner_section();
        
        echo '<hr style="margin: 40px 0;">';
        
        // Load handle-mapper script directly
        echo '<script src="' . WPCCM_URL . 'assets/js/handle-mapper.js"></script>';
        echo '<script>
        jQuery(document).ready(function($) {
            // Set up WPCCM_MAPPER data
            window.WPCCM_MAPPER = {
                ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                nonce: "' . wp_create_nonce('wpccm_handle_mapping') . '",
                texts: {
                    loading: "' . wpccm_text('loading') . '"
                }
            };
            //console.log("WPCCM Debug: WPCCM_MAPPER initialized:", window.WPCCM_MAPPER);
        });
        </script>';
        
        $opts = WP_CCM_Consent::get_options();
        $map = isset($opts['map']) && is_array($opts['map']) ? $opts['map'] : [];
        
        // echo '<div id="wpccm-handle-mapping-table">';
        
        // // Explanation box
        // echo '<div class="wpccm-explanation-box" style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">';
        // echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">ℹ️ מיפוי סקריפטים קלאסי</h4>';
        // echo '<p style="margin: 0; color: #555;">השיטה הקלאסית למיפוי סקריפטים - רלוונטית לסקריפטים שנרשמו ב-WordPress.</p>';
        // echo '</div>';
        
        // echo '<button type="button" class="button button-primary" id="wpccm-scan-live-handles">'.wpccm_text('scan_live_handles').'</button>';
        // echo '<button type="button" class="button button-primary" id="wpccm-scan-handles" style="margin-left: 10px;">'.wpccm_text('scan_handles').'</button>';
        // echo '<button type="button" class="button" id="wpccm-add-handle" style="margin-left: 10px;">'.wpccm_text('add_handle').'</button>';
        // echo '<button type="button" class="button" id="wpccm-sync-to-purge" style="margin-left: 10px; background: #00a32a; color: white;" title="'.esc_attr(wpccm_text('sync_to_purge_help')).'">'.wpccm_text('sync_to_purge').'</button>';
        // echo '<button type="button" class="button button-secondary" id="wpccm-clear-all-handles" style="margin-left: 10px; color: #d63384;" title="'.esc_attr(wpccm_text('confirm_clear_all_handles')).'">'.wpccm_text('clear_all_handles').'</button>';
        
        // echo '<div class="wpccm-table-container" style="margin-top: 15px; max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        // echo '<table class="widefat fixed striped" id="wpccm-handles-table" style="margin: 0; border: none;">';
        // echo '<thead><tr>';
        // echo '<th>'.wpccm_text('handle').'</th>';
        // echo '<th>'.wpccm_text('script_file').'</th>';
        // echo '<th>'.wpccm_text('related_cookies').'</th>';
        // echo '<th>'.wpccm_text('category').'</th>';
        // echo '<th style="width: 100px;">'.wpccm_text('actions').'</th>';
        // echo '</tr></thead><tbody>';
        
        // foreach ($map as $handle => $category) {
        //     $suggested_cookies = $this->get_related_cookies_for_handle($handle);
        //     $saved_cookies = $this->get_saved_cookies_for_handle($handle);
        //     $cookies_value = !empty($saved_cookies) ? $saved_cookies : $suggested_cookies;
            
        //     $saved_src = $this->get_saved_src_for_handle($handle);
        //     $src_display = !empty($saved_src) ? 
        //         '<span class="script-src-display" style="color: #666; font-size: 12px;" title="'.esc_attr($saved_src).'">'.esc_html(basename(parse_url($saved_src, PHP_URL_PATH))).'</span>' :
        //         '<span class="script-src-display" style="color: #999; font-size: 12px; font-style: italic;">לא נמצאו עוגיות קשורות</span>';
            
        //     echo '<tr>';
        //     echo '<td><input type="text" class="handle-input" value="'.esc_attr($handle).'" /></td>';
        //     echo '<td>'.$src_display.'</td>';
        //     echo '<td>';
        //     echo '<div class="cookies-input-container">';
        //     echo '<select class="cookies-dropdown" multiple style="width: 100%; font-size: 12px; min-height: 60px;">';
            
        //     // Get cookies from purge list
        //     $purge_cookies = isset($opts['purge']['cookies']) ? $opts['purge']['cookies'] : [];
        //     $selected_cookies = !empty($saved_cookies) ? array_map('trim', explode(',', $saved_cookies)) : [];
            
        //     foreach ($purge_cookies as $purge_cookie) {
        //         // Handle both old string format and new object format
        //         if (is_array($purge_cookie) && isset($purge_cookie['name'])) {
        //             $cookie_name = trim($purge_cookie['name']);
        //         } else {
        //             $cookie_name = trim($purge_cookie);
        //         }
                
        //         if (!empty($cookie_name)) {
        //             $selected = in_array($cookie_name, $selected_cookies) ? ' selected' : '';
        //             echo '<option value="'.esc_attr($cookie_name).'"'.$selected.'>'.esc_html($cookie_name).'</option>';
        //         }
        //     }
        //     echo '</select>';
        //     echo '<input type="text" class="cookies-input-manual" value="'.esc_attr($cookies_value).'" placeholder="'.esc_attr(wpccm_text('enter_cookies_separated')).'" style="width: 100%; font-size: 12px; margin-top: 5px;" title="'.esc_attr(wpccm_text('cookies_input_help')).'" />';
        //     echo '<div class="cookies-input-help" style="font-size: 11px; color: #666; margin-top: 3px;">';
        //     echo 'בחר מהרשימה או הזן ידנית';
        //     echo '</div>';
        //     echo '</div>';
        //     echo '</td>';
        //     echo '<td>'.$this->render_category_select($category).'</td>';
        //     echo '<td><button type="button" class="button remove-handle">'.wpccm_text('remove').'</button></td>';
        //     echo '</tr>';
        // }
        
        // echo '</tbody></table>';
        // echo '</div>'; // Close table container
        // echo '</div>'; // Close main container
        
        // // Hidden input to store the actual data
        // $encoded_map = json_encode($map);
        // echo '<input type="hidden" name="wpccm_options[map]" id="wpccm-map-data" value="'.esc_attr($encoded_map).'" />';
        
        // // Hidden fields for cookies and script sources
        // $cookie_mapping = isset($opts['cookie_mapping']) && is_array($opts['cookie_mapping']) ? $opts['cookie_mapping'] : [];
        // $script_sources = isset($opts['script_sources']) && is_array($opts['script_sources']) ? $opts['script_sources'] : [];
        
        // echo '<input type="hidden" name="wpccm_options[cookie_mapping]" id="wpccm-cookie-mapping-data" value="'.esc_attr(json_encode($cookie_mapping)).'" />';
        // echo '<input type="hidden" name="wpccm_options[script_sources]" id="wpccm-script-sources-data" value="'.esc_attr(json_encode($script_sources)).'" />';
    }

    private function render_design_tab() {
        $opts = WP_CCM_Consent::get_options();
        $design_settings = isset($opts['design']) ? $opts['design'] : [];
        
        // Get banner content from general settings
        $banner_title = isset($opts['banner']['title']) ? $opts['banner']['title'] : 'באנר הסכמה לעוגיות';
        $banner_description = isset($opts['banner']['description']) ? $opts['banner']['description'] : 'אנו משתמשים בעוגיות כדי לשפר את החוויה שלך באתר. המשך הגלישה מהווה הסכמה לשימוש בעוגיות.';
        
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
        echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">🎨 הגדרות עיצוב באנר הסכמה</h4>';
        echo '<p style="margin: 0; color: #555;">התאם את המראה והמיקום של באנר הסכמה לעוגיות</p>';
        echo '</div>';
        
        // Design settings form
        echo '<table class="form-table">';
        
        // Banner Position
        echo '<tr>';
        echo '<th scope="row"><label for="banner_position">מיקום הבאנר</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][banner_position]" id="banner_position">';
        echo '<option value="top" ' . selected($banner_position, 'top', false) . '>בראש הדף</option>';
        echo '<option value="bottom" ' . selected($banner_position, 'bottom', false) . '>בתחתית הדף</option>';
        echo '</select>';
        echo '<p class="description">בחר איפה הבאנר יופיע בדף</p>';
        echo '</td>';
        echo '</tr>';
        
        // Floating Button Position
        echo '<tr>';
        echo '<th scope="row"><label for="floating_button_position">מיקום כפתור צף</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][floating_button_position]" id="floating_button_position">';
        echo '<option value="top-right" ' . selected($floating_button_position, 'top-right', false) . '>ימין למעלה</option>';
        echo '<option value="top-left" ' . selected($floating_button_position, 'top-left', false) . '>שמאל למעלה</option>';
        echo '<option value="bottom-right" ' . selected($floating_button_position, 'bottom-right', false) . '>ימין למטה</option>';
        echo '<option value="bottom-left" ' . selected($floating_button_position, 'bottom-left', false) . '>שמאל למטה</option>';
        echo '</select>';
        echo '<p class="description">בחר מיקום כפתור הצף לפתיחת הגדרות עוגיות</p>';
        echo '</td>';
        echo '</tr>';
        
        // Background Color
        echo '<tr>';
        echo '<th scope="row"><label for="background_color">צבע רקע</label></th>';
        echo '<td>';
        echo '<input type="color" name="wpccm_options[design][background_color]" id="background_color" value="' . esc_attr($background_color) . '" />';
        echo '<p class="description">בחר צבע רקע לבאנר</p>';
        echo '</td>';
        echo '</tr>';
        
        // Text Color (Black or White only)
        echo '<tr>';
        echo '<th scope="row"><label for="text_color">צבע טקסט</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][text_color]" id="text_color">';
        echo '<option value="#000000" ' . selected($text_color, '#000000', false) . '>שחור</option>';
        echo '<option value="#ffffff" ' . selected($text_color, '#ffffff', false) . '>לבן</option>';
        echo '</select>';
        echo '<p class="description">בחר צבע טקסט לבאנר (שחור או לבן בלבד)</p>';
        echo '</td>';
        echo '</tr>';
        
        // Button Colors (Horizontal Layout)
        echo '<tr>';
        echo '<th scope="row">צבעי כפתורים</th>';
        echo '<td>';
        echo '<div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">';
        
        // Accept Button Color
        echo '<div style="display: flex; flex-direction: column; align-items: center; min-width: 120px;">';
        echo '<label for="accept_button_color" style="margin-bottom: 5px; font-weight: 500; text-align: center;">קבל הכל</label>';
        echo '<input type="color" name="wpccm_options[design][accept_button_color]" id="accept_button_color" value="' . esc_attr(isset($design_settings['accept_button_color']) ? $design_settings['accept_button_color'] : '#0073aa') . '" style="width: 60px; height: 40px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer;" />';
        echo '<small style="margin-top: 3px; color: #666; text-align: center;">כחול</small>';
        echo '</div>';
        
        // Reject Button Color
        echo '<div style="display: flex; flex-direction: column; align-items: center; min-width: 120px;">';
        echo '<label for="reject_button_color" style="margin-bottom: 5px; font-weight: 500; text-align: center;">דחה</label>';
        echo '<input type="color" name="wpccm_options[design][reject_button_color]" id="reject_button_color" value="' . esc_attr(isset($design_settings['reject_button_color']) ? $design_settings['reject_button_color'] : '#6c757d') . '" style="width: 60px; height: 40px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer;" />';
        echo '<small style="margin-top: 3px; color: #666; text-align: center;">אפור</small>';
        echo '</div>';
        
        // Settings Button Color
        echo '<div style="display: flex; flex-direction: column; align-items: center; min-width: 120px;">';
        echo '<label for="settings_button_color" style="margin-bottom: 5px; font-weight: 500; text-align: center;">הגדרת עוגיות</label>';
        echo '<input type="color" name="wpccm_options[design][settings_button_color]" id="settings_button_color" value="' . esc_attr(isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745') . '" style="width: 60px; height: 40px; border: 2px solid #ddd; border-radius: 4px; cursor: pointer;" />';
        echo '<small style="margin-top: 3px; color: #666; text-align: center;">ירוק</small>';
        echo '</div>';
        
        echo '</div>';
        echo '<p class="description">בחר צבע לכל כפתור בנפרד</p>';
        echo '</td>';
        echo '</tr>';
        
        // Size
        echo '<tr>';
        echo '<th scope="row"><label for="size">גודל</label></th>';
        echo '<td>';
        echo '<select name="wpccm_options[design][size]" id="size">';
        echo '<option value="small" ' . selected($size, 'small', false) . '>קטן</option>';
        echo '<option value="medium" ' . selected($size, 'medium', false) . '>בינוני</option>';
        echo '<option value="large" ' . selected($size, 'large', false) . '>גדול</option>';
        echo '</select>';
        echo '<p class="description">בחר גודל לבאנר</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        // Preview section
        echo '<div class="wpccm-preview-section" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 4px;">';
        echo '<h3>תצוגה מקדימה</h3>';
        // Calculate initial size values
        $initial_padding = '15px';
        $initial_font_size = '14px';
        $initial_button_padding = '8px 16px';
        
        if ($size === 'small') {
            $initial_padding = '8px';
            $initial_font_size = '12px';
            $initial_button_padding = '6px 12px';
        } elseif ($size === 'large') {
            $initial_padding = '25px';
            $initial_font_size = '18px';
            $initial_button_padding = '12px 24px';
        }
        
        echo '<div id="wpccm-banner-preview" style="padding: ' . $initial_padding . '; border: 2px solid #ddd; border-radius: 4px; margin: 10px 0; background: ' . esc_attr($background_color) . '; color: ' . esc_attr($text_color) . '; transition: all 0.3s ease;" data-position="' . esc_attr($banner_position) . '" data-floating-position="' . esc_attr($floating_button_position) . '">';
        echo '<h4 style="margin: 0 0 10px 0; font-size: ' . $initial_font_size . ';">' . esc_html($banner_title) . '</h4>';
        echo '<p style="margin: 0 0 15px 0; font-size: ' . $initial_font_size . '; line-height: 1.4;">' . esc_html($banner_description) . '</p>';
        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
        echo '<button style="background: ' . esc_attr(isset($design_settings['accept_button_color']) ? $design_settings['accept_button_color'] : '#0073aa') . '; color: white; border: none; padding: ' . $initial_button_padding . '; border-radius: 4px; cursor: pointer; font-size: ' . $initial_font_size . '; transition: all 0.3s ease;">קבל הכל</button>';
        echo '<button style="background: transparent; color: ' . esc_attr($text_color) . '; border: 1px solid ' . esc_attr($text_color) . '; padding: ' . $initial_button_padding . '; border-radius: 4px; cursor: pointer; font-size: ' . $initial_font_size . '; transition: all 0.3s ease;">דחה</button>';
        echo '<button style="background: ' . esc_attr(isset($design_settings['settings_button_color']) ? $design_settings['settings_button_color'] : '#28a745') . '; color: white; border: none; padding: ' . $initial_button_padding . '; border-radius: 4px; cursor: pointer; font-size: ' . $initial_font_size . '; transition: all 0.3s ease;">הגדרת עוגיות</button>';
        echo '</div>';
        echo '</div>';
        echo '<div style="margin-top: 10px; font-size: 12px; color: #666;">';
        echo '<strong>מיקום באנר:</strong> <span id="preview-position">' . ($banner_position === 'top' ? 'בראש הדף' : 'בתחתית הדף') . '</span> | ';
        echo '<strong>מיקום כפתור צף:</strong> <span id="preview-floating-position">' . $floating_button_position . '</span> | ';
        echo '<strong>גודל:</strong> <span id="preview-size">' . $size . '</span>';
        echo '</div>';
        echo '<p class="description">התצוגה המקדימה מתעדכנת בזמן אמת כשאתה משנה את ההגדרות</p>';
        echo '<p class="description" style="margin-top: 10px; font-style: italic; color: #666;">💡 <strong>טיפ:</strong> הכותרת והתיאור בתצוגה המקדימה מגיעים מההגדרות הכלליות. שנה אותם בטאב "הגדרות כלליות" כדי לראות את השינויים כאן.</p>';
        echo '</div>';
        
        echo '</div>'; // Close main container
        
        // JavaScript for live preview
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
                var bannerPosition = $("#banner_position").val();
                var floatingButtonPosition = $("#floating_button_position").val();
                var size = $("#size").val();
                
                // Update colors
                $("#wpccm-banner-preview").css({
                    "background-color": bgColor,
                    "color": textColor
                });
                
                // Update button colors
                $("#wpccm-banner-preview button:first").css("background-color", acceptButtonColor); // Accept button
                $("#wpccm-banner-preview button:nth-child(2)").css("color", textColor).css("border-color", textColor); // Reject button
                $("#wpccm-banner-preview button:last").css("background-color", settingsButtonColor); // Settings button
                
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
                
                $("#wpccm-banner-preview button").css({
                    "padding": buttonPadding,
                    "font-size": fontSize
                });
                
                // Update banner position indicator
                $("#wpccm-banner-preview").attr("data-position", bannerPosition);
                
                // Update floating button position indicator
                $("#wpccm-banner-preview").attr("data-floating-position", floatingButtonPosition);
                
                // Update info text
                var positionText = bannerPosition === "top" ? "בראש הדף" : "בתחתית הדף";
                $("#preview-position").text(positionText);
                $("#preview-floating-position").text(floatingButtonPosition);
                $("#preview-size").text(size);
                
                console.log("WPCCM: Preview updated - BG:", bgColor, "Text:", textColor, "Accept:", acceptButtonColor, "Reject:", rejectButtonColor, "Settings:", settingsButtonColor, "Size:", size, "Position:", bannerPosition);
            }
            
            // Update preview on any change
            $("#background_color, #text_color, #accept_button_color, #reject_button_color, #settings_button_color, #banner_position, #floating_button_position, #size").on("change input", updatePreview);
            
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
                others: "' . wpccm_text('others') . '"
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
        
        $opts = WP_CCM_Consent::get_options();
        $cookies = isset($opts['purge']['cookies']) ? $opts['purge']['cookies'] : [];
        
        echo '<div id="wpccm-cookie-purge-table">';
        
        // Warning if no cookies configured
        if (empty($cookies)) {
            echo '<div class="wpccm-warning-box" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin-bottom: 15px;">';
            echo '<h4 style="margin: 0 0 8px 0; color: #856404;">⚠️ '.wpccm_text('no_cookies_configured', 'No cookies configured for purging').'</h4>';
            echo '<p style="margin: 0; color: #856404;">'.wpccm_text('default_cookies_warning', 'When visitors reject cookies, the system will use default cookies (_ga, _gid, _fbp, _hjSessionUser). Use the "Sync with Current Cookies" button to add cookies specific to your website.').'</p>';
            echo '</div>';
        }
        
        // Main explanation
        echo '<div class="wpccm-explanation-box" style="background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; padding: 15px; margin-bottom: 15px;">';
        echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">ℹ️ '.wpccm_text('what_are_purge_cookies', 'What are purge cookies?').'</h4>';
        echo '<p style="margin: 0; color: #555;">'.wpccm_text('cookie_purge_explanation').'</p>';
        echo '</div>';
        
        // Buttons with tooltips
        echo '<div style="position: relative;">';
        echo '<button type="button" class="button button-primary" id="wpccm-sync-current-cookies-btn" title="'.esc_attr(wpccm_text('sync_explanation')).'">'.wpccm_text('sync_with_current_cookies').'</button>';
        echo '<span id="wpccm-sync-result"></span>';
        echo '<button type="button" class="button" id="wpccm-sync-categories-btn" style="margin-left: 10px; background: #00a32a; color: white;" title="סנכרן קטגוריות מטבלת המיפוי">סנכרן קטגוריות</button>';
        echo '<button type="button" class="button" id="wpccm-add-cookie" style="margin-left: 10px;" title="'.wpccm_text('add_cookie_manually', 'Add cookie manually').'">'.wpccm_text('add_cookie').'</button>';
        echo '<button type="button" class="button button-secondary" id="wpccm-clear-all-cookies" style="margin-left: 10px; color: #d63384;" title="'.esc_attr(wpccm_text('confirm_clear_all_cookies')).'">'.wpccm_text('clear_all_cookies').'</button>';
        echo '</div>';
        
        echo '<div id="wpccm-cookie-suggestions-inline" style="margin-top: 15px;"></div>';
        
        echo '<div class="wpccm-table-container" style="margin-top: 15px; max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        echo '<table class="widefat fixed striped" id="wpccm-cookies-table" style="margin: 0; border: none;">';
        echo '<thead><tr>';
        echo '<th>'.wpccm_text('cookie_name').'</th>';
        echo '<th>'.wpccm_text('category').'</th>';
        echo '<th style="width: 100px;">'.wpccm_text('actions').'</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($cookies as $cookie_data) {
            // Support both old and new format
            if (is_string($cookie_data)) {
                $cookie_name = $cookie_data;
                $category = '';
            } else {
                $cookie_name = isset($cookie_data['name']) ? $cookie_data['name'] : '';
                $category = isset($cookie_data['category']) ? $cookie_data['category'] : '';
            }
            
            echo '<tr>';
            echo '<td><input type="text" class="cookie-input" value="'.esc_attr($cookie_name).'" /></td>';
            echo '<td>'.$this->render_category_select($category).'</td>';
            echo '<td><button type="button" class="button remove-cookie">'.wpccm_text('remove').'</button></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>'; // Close table container
        echo '</div>'; // Close main container
        
        // Hidden input to store the actual data
        $encoded_cookies = json_encode($cookies);
        echo '<input type="hidden" name="wpccm_options[purge][cookies]" id="wpccm-cookies-data" value="'.esc_attr($encoded_cookies).'" />';
    }

    private function render_categories_tab() {
        
        // Handle form submission for categories
        if (isset($_POST['save_categories']) && wp_verify_nonce($_POST['wpccm_categories_nonce'], 'wpccm_save_categories')) {
            $this->save_categories();
            echo '<div class="notice notice-success is-dismissible"><p>'.wpccm_text('save_categories').' - '.wpccm_text('saved_successfully', 'Saved successfully!').'</p></div>';
        }
        try {
            $categories = WP_CCM_Consent::get_categories_with_details();
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>שגיאה בטעינת קטגוריות: ' . $e->getMessage() . '</p></div>';
            $categories = [];
        }
        
        echo '<div id="wpccm-categories-manager">';
        echo '<button type="button" class="button" id="wpccm-add-category">'.wpccm_text('add_category').'</button>';
        
        echo '<div class="wpccm-table-container" style="margin-top: 15px; max-height: 500px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        echo '<table class="widefat fixed striped" id="wpccm-categories-table" style="margin: 0; border: none;">';
        echo '<thead><tr>';
        echo '<th style="width: 150px;">'.wpccm_text('category_key').'</th>';
        echo '<th style="width: 150px;">'.wpccm_text('category_name').'</th>';
        echo '<th>'.wpccm_text('category_description').'</th>';
        echo '<th style="width: 80px;">'.wpccm_text('required_category').'</th>';
        echo '<th style="width: 80px;">'.wpccm_text('category_enabled').'</th>';
        echo '<th style="width: 100px;">'.wpccm_text('actions').'</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($categories as $index => $category) {
            $this->render_category_row($category, $index);
        }
        
        echo '</tbody></table>';
        echo '</div>'; // Close table container
        echo '</div>'; // Close main container
        
        // echo '<p class="submit">';
        // echo '<input type="submit" name="save_categories" class="button-primary" value="'.wpccm_text('save_categories').'" />';
        // echo '</p>';
        
        // Add nonce field for categories
        wp_nonce_field('wpccm_save_categories', 'wpccm_categories_nonce');
        
        // Add JavaScript for dynamic table management
        // Temporarily disabled to debug
        $this->enqueue_categories_js();
    }
    
    private function save_categories() {
        if (!isset($_POST['categories']) || !is_array($_POST['categories'])) {
            return;
        }
        
        $categories = [];
        foreach ($_POST['categories'] as $category_data) {
            if (empty($category_data['key']) || empty($category_data['name'])) {
                continue; // Skip empty categories
            }
            
            $categories[] = [
                'key' => sanitize_key($category_data['key']),
                'name' => sanitize_text_field($category_data['name']),
                'description' => sanitize_textarea_field($category_data['description']),
                'required' => isset($category_data['required']) && $category_data['required'] === '1',
                'enabled' => isset($category_data['enabled']) && $category_data['enabled'] === '1'
            ];
        }
        
        update_option('wpccm_custom_categories', $categories);
    }
    
    private function enqueue_categories_js() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var categoryIndex = $('.category-row').length;
            
            $('#wpccm-add-category').on('click', function() {
                var newRow = $('<tr class="category-row">' +
                    '<td><input type="text" name="categories[' + categoryIndex + '][key]" class="category-key-input" placeholder="<?php echo wpccm_text('key_placeholder'); ?>" /></td>' +
                    '<td><input type="text" name="categories[' + categoryIndex + '][name]" class="category-name-input" placeholder="<?php echo wpccm_text('name_placeholder'); ?>" /></td>' +
                    '<td><textarea name="categories[' + categoryIndex + '][description]" class="category-description-input" placeholder="<?php echo wpccm_text('description_placeholder'); ?>"></textarea></td>' +
                    '<td style="text-align: center;"><input type="checkbox" name="categories[' + categoryIndex + '][required]" value="1" /></td>' +
                    '<td style="text-align: center;"><input type="checkbox" name="categories[' + categoryIndex + '][enabled]" value="1" checked /></td>' +
                    '<td><button type="button" class="button remove-category"><?php echo wpccm_text('delete_category'); ?></button></td>' +
                    '</tr>');
                
                $('#wpccm-categories-table tbody').append(newRow);
                categoryIndex++;
                
                // Scroll to new row
                $('.wpccm-table-container').scrollTop($('.wpccm-table-container')[0].scrollHeight);
            });
            
            $(document).on('click', '.remove-category', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }
    
    public function render_scanner_page() {
        echo '<div class="wrap">';
        echo '<h1>'.wpccm_text('cookie_scanner').'</h1>';
        
        // Tabs
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#current-cookies" class="nav-tab nav-tab-active" id="tab-current">'.wpccm_text('current_cookies').'</a>';
        echo '<a href="#cookie-suggestions" class="nav-tab" id="tab-suggestions">'.wpccm_text('cookie_suggestions').'</a>';
        echo '</h2>';
        
        // Current Cookies Tab
        echo '<div id="current-cookies-content" class="tab-content">';
        echo '<p>'.wpccm_text('current_cookies_description').'</p>';
        echo '<button type="button" class="button button-primary" id="refresh-cookies">'.wpccm_text('refresh_cookies').'</button>';
        echo '<div id="current-cookies-list" style="margin-top: 20px;"></div>';
        echo '</div>';
        
        // Cookie Suggestions Tab
        echo '<div id="cookie-suggestions-content" class="tab-content" style="display: none;">';
        echo '<p>'.wpccm_text('scanner_description').'</p>';
        echo '<button type="button" class="button button-primary" id="scan-suggest-cookies">'.wpccm_text('scan_cookies').'</button>';
        echo '<div id="cookie-suggestions-list" style="margin-top: 20px;"></div>';
        echo '</div>';
        
        echo '</div>';
        
        // Add JavaScript for the scanner
        $this->enqueue_scanner_js();
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
            <h1><span class="dashicons dashicons-chart-bar"></span> ניהול עוגיות וסטטיסטיקות</h1>
            
            <!-- Dashboard Overview -->
            <div class="wpccm-dashboard-grid">
                <!-- Statistics Cards -->
                <div class="wpccm-stats-cards">
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3>הסכמות היום</h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-dismiss"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3>דחיות היום</h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-groups"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3>סה״כ משתמשים</h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                    
                    <div class="wpccm-stat-card">
                        <div class="wpccm-stat-icon">
                            <span class="dashicons dashicons-admin-tools"></span>
                        </div>
                        <div class="wpccm-stat-content">
                            <h3>עוגיות פעילות</h3>
                            <span class="wpccm-stat-number">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="wpccm-quick-actions">
                    <h3>פעולות מהירות</h3>
                    <div class="wpccm-action-buttons">
                        <button type="button" class="button button-primary">
                            <span class="dashicons dashicons-download"></span>
                            ייצוא דוח
                        </button>
                        <button type="button" class="button" onclick="refreshAllData()">
                            <span class="dashicons dashicons-update"></span>
                            רענון נתונים
                        </button>
                        <button type="button" class="button" onclick="goToAdvancedSettings()">
                            <span class="dashicons dashicons-admin-settings"></span>
                            הגדרות מתקדמות
                        </button>

                    </div>
                </div>
            </div>
            

            
            <!-- Charts Area -->
            <div class="wpccm-charts-section">
                <h2>גרפים וניתוחים</h2>
                <div class="wpccm-charts-grid">
                    <div class="wpccm-chart-container">
                        <h3>הסכמות לאורך זמן</h3>
                        <canvas id="consentTimeChart"></canvas>
                    </div>
                    <div class="wpccm-chart-container">
                        <h3>התפלגות לפי קטגוריות</h3>
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
             tbody.html('<tr><td colspan="5">טוען נתונים...</td></tr>');
             
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
                     tbody.html('<tr><td colspan="5">שגיאה בטעינת הנתונים</td></tr>');
                 }
             }).fail(function() {
                 tbody.html('<tr><td colspan="5">שגיאה בחיבור לשרת</td></tr>');
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
                     loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total + ' עבור IP מדויק: ' + currentSearchIP + ' (עמוד ' + data.current_page + ')');
                 } else {
                     loadingInfo.text('נטענו את כל הנתונים עבור IP מדויק: ' + currentSearchIP + ': ' + data.data.length + ' רשומות');
                 }
                 searchInfo.text('תוצאות חיפוש מדויק עבור IP: ' + currentSearchIP);
             } else {
                 if (data.per_page > 0) {
                     loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total + ' (עמוד ' + data.current_page + ')');
                 } else {
                     loadingInfo.text('נטענו את כל הנתונים: ' + data.data.length + ' רשומות');
                 }
                 searchInfo.text('');
             }
             
             if (!data.data || data.data.length === 0) {
                 tbody.html('<tr><td colspan="5">אין נתוני היסטוריה</td></tr>');
                 loadingInfo.text('');
                 return;
             }
             
             data.data.forEach(function(record) {
                 var date = new Date(record.created_at).toLocaleString('he-IL');
                 var actionText = getActionText(record.action_type);
                 var categories = '';
                 
                 try {
                     var categoriesData = JSON.parse(record.categories_accepted || '[]');
                     if (Array.isArray(categoriesData) && categoriesData.length > 0) {
                         categories = categoriesData.join(', ');
                     } else {
                         categories = 'ללא קטגוריות';
                     }
                 } catch (e) {
                     categories = 'נתונים לא תקינים';
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
                 'accept': 'קבלה',
                 'reject': 'דחייה', 
                 'save': 'שמירה',
                 'accept_all': 'קבלת הכל',
                 'reject_all': 'דחיית הכל'
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
                 paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage - 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">« הקודם</button> ';
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
                 paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage + 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">הבא »</button>';
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
                 loadingInfo.text('מייצא נתונים עבור IP מדויק: ' + currentSearchIP + '...');
             } else {
                 loadingInfo.text('מייצא נתונים...');
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
                 loadingInfo.text('הייצוא הושלם בהצלחה עבור IP מדויק: ' + currentSearchIP + '!');
             } else {
                 loadingInfo.text('הייצוא הושלם בהצלחה!');
             }
             
             setTimeout(function() {
                 loadingInfo.text('');
             }, 3000);
         }
         
         function searchByIP() {
             var searchIP = jQuery('#search-ip').val().trim();
             var searchInfo = jQuery('.wpccm-search-info');
             
             if (searchIP === '') {
                 searchInfo.text('אנא הזן כתובת IP מדויקת לחיפוש');
                 return;
             }
             
             searchInfo.text('מחפש...');
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
             refreshButton.html('<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> רענון...');
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
     * Render deletion requests tab
     */
    private function render_deletion_requests_tab() {
        // Get deletion requests
        global $wpdb;
        $deletion_requests_table = $wpdb->prefix . 'wpccm_deletion_requests';
        
        // Create table if it doesn't exist
        $this->create_deletion_requests_table();
        
        // Get all deletion requests
        $requests = $wpdb->get_results("
            SELECT * FROM $deletion_requests_table 
            ORDER BY created_at DESC
        ");
        
        // Get settings
        $opts = WP_CCM_Consent::get_options();
        $auto_delete = isset($opts['data_deletion']['auto_delete']) ? $opts['data_deletion']['auto_delete'] : false;
        
        echo '<div id="wpccm-deletion-requests-table">';
        
        // Settings section
        echo '<div class="wpccm-settings-section" style="margin-bottom: 30px;">';
        echo '<h3>הגדרות מחיקה אוטומטית</h3>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">מחיקה אוטומטית</th>';
        echo '<td>';
        echo '<label>';
        echo '<input type="checkbox" name="wpccm_options[data_deletion][auto_delete]" value="1" ' . ($auto_delete ? 'checked' : '') . ' />';
        echo ' הפעל מחיקה אוטומטית של נתונים כאשר מתקבלת בקשה';
        echo '</label>';
        echo '<p class="description">כאשר מופעל, הנתונים יימחקו מיד כאשר מתקבלת בקשה. אחרת, הבקשות יישמרו לטיפול ידני.</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        echo '</div>';
        
        // Statistics
        $total_requests = count($requests);
        $pending_requests = count(array_filter($requests, function($r) { return $r->status === 'pending'; }));
        $completed_requests = count(array_filter($requests, function($r) { return $r->status === 'completed'; }));
        
        echo '<div class="wpccm-stats-section" style="margin-bottom: 20px;">';
        echo '<div class="wpccm-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        echo '<div class="wpccm-stat-box" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; text-align: center;">';
        echo '<div class="wpccm-stat-number" style="font-size: 24px; font-weight: bold; color: #007cba;">' . $total_requests . '</div>';
        echo '<div class="wpccm-stat-label" style="color: #6c757d; font-size: 14px;">סה"כ בקשות</div>';
        echo '</div>';
        echo '<div class="wpccm-stat-box" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; text-align: center;">';
        echo '<div class="wpccm-stat-number" style="font-size: 24px; font-weight: bold; color: #856404;">' . $pending_requests . '</div>';
        echo '<div class="wpccm-stat-label" style="color: #856404; font-size: 14px;">בקשות ממתינות</div>';
        echo '</div>';
        echo '<div class="wpccm-stat-box" style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 15px; text-align: center;">';
        echo '<div class="wpccm-stat-number" style="font-size: 24px; font-weight: bold; color: #0c5460;">' . $completed_requests . '</div>';
        echo '<div class="wpccm-stat-label" style="color: #0c5460; font-size: 14px;">בקשות שהושלמו</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Table
        echo '<div class="wpccm-table-container" style="margin-top: 15px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        echo '<table class="widefat fixed striped" id="wpccm-deletion-requests-table" style="margin: 0; border: none;">';
        echo '<thead><tr>';
        echo '<th>תאריך בקשה</th>';
        echo '<th>כתובת IP</th>';
        echo '<th>סוג מחיקה</th>';
        echo '<th>סטטוס</th>';
        echo '<th>תאריך מחיקה</th>';
        echo '<th style="width: 120px;">פעולות</th>';
        echo '</tr></thead><tbody>';
        
        if (empty($requests)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6c757d;">אין בקשות מחיקה</td></tr>';
        } else {
            foreach ($requests as $request) {
                $status_class = $request->status === 'completed' ? 'status-completed' : 'status-pending';
                $status_text = $request->status === 'completed' ? 'הושלם' : 'ממתין';
                
                echo '<tr>';
                echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($request->created_at))) . '</td>';
                echo '<td>' . esc_html($request->ip_address) . '</td>';
                echo '<td>' . esc_html($this->get_deletion_type_text($request->deletion_type)) . '</td>';
                echo '<td><span class="wpccm-status ' . $status_class . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>' . ($request->deleted_at ? esc_html(date('d/m/Y H:i', strtotime($request->deleted_at))) : '-') . '</td>';
                echo '<td>';
                
                if ($request->status === 'pending') {
                    echo '<button type="button" class="button button-primary wpccm-delete-data-btn" data-ip="' . esc_attr($request->ip_address) . '" data-id="' . esc_attr($request->id) . '">מחק נתונים</button>';
                } else {
                    echo '<span style="color: #6c757d;">הושלם</span>';
                }
                
                echo '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
        
        // Add JavaScript for delete functionality
        echo '<script>
        jQuery(document).ready(function($) {
            $(".wpccm-delete-data-btn").on("click", function() {
                if (confirm("האם אתה בטוח שברצונך למחוק את כל הנתונים עבור כתובת IP זו?")) {
                    var ip = $(this).data("ip");
                    var id = $(this).data("id");
                    var btn = $(this);
                    
                    btn.prop("disabled", true).text("מוחק...");
                    
                    $.post(ajaxurl, {
                        action: "wpccm_delete_data_manually",
                        ip_address: ip,
                        request_id: id,
                        nonce: "' . wp_create_nonce('wpccm_delete_data') . '"
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert("שגיאה במחיקת הנתונים: " + (response.data || "שגיאה לא ידועה"));
                            btn.prop("disabled", false).text("מחק נתונים");
                        }
                    }).fail(function() {
                        alert("שגיאה בתקשורת עם השרת");
                        btn.prop("disabled", false).text("מחק נתונים");
                    });
                }
            });
        });
        </script>';
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
     * Render deletion management page
     */
    public function render_deletion_page() {
        ?>
        <div class="wrap">
            <h1>ניהול מחיקת נתונים</h1>
            <p class="description">ניהול בקשות מחיקת נתונים ממשתמשי האתר</p>
            
            <?php $this->render_deletion_requests_tab(); ?>
        </div>
        <?php
    }

    /**
     * Render activity history page
     */
    public function render_history_page() {
        ?>
        <div class="wrap">
            <h1>היסטוריית פעילות</h1>
            <p class="description">צפייה בהיסטוריית פעילות המשתמשים באתר</p>
            
            <?php $this->render_consent_history_tab(); ?>
        </div>
        <?php
    }
    


    /**
     * Render consent history tab
     */
    private function render_consent_history_tab() {
        ?>
        <!-- Controls for data loading -->
        <div class="wpccm-controls" style="margin-bottom: 15px;">
            <button class="button" onclick="loadConsentHistory(1, 100, getCurrentSearchIP())">טען 100 רשומות</button>
            <button class="button" onclick="loadConsentHistory(1, 500, getCurrentSearchIP())">טען 500 רשומות</button>
            <button class="button button-primary" onclick="loadConsentHistory(1, 0, getCurrentSearchIP())">טען את כל הנתונים</button>
            <button class="button" onclick="exportData('csv')" style="margin-left: 10px;">ייצא ל-CSV</button>
            <button class="button" onclick="exportData('json')">ייצא ל-JSON</button>
            <span class="wpccm-loading-info" style="margin-left: 10px; color: #666;"></span>
        </div>
        
        <!-- Search controls -->
        <div class="wpccm-search-controls" style="margin-bottom: 15px;">
            <input type="text" id="search-ip" placeholder="הזן כתובת IP מדויקת..." style="width: 200px; margin-left: 10px;">
            <button class="button" onclick="searchByIP()">חפש</button>
            <button class="button" onclick="clearSearch()">נקה חיפוש</button>
            <span class="wpccm-search-info" style="margin-right: 10px; color: #666;"></span>
        </div>
        
        <table class="wpccm-activity-table">
            <thead>
                <tr>
                    <th>תאריך</th>
                    <th>סוג פעולה</th>
                    <th>קטגוריות</th>
                    <th>IP משתמש</th>
                    <th>URL הפניה</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be populated here -->
            </tbody>
        </table>
        
        <style>
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
        </style>
        
        <script>
        // Load consent history on page load
        jQuery(document).ready(function($) {
            loadConsentHistory(1, 100, ''); // Load 100 records by default
        });
        
        function loadConsentHistory(page = 1, perPage = 100, searchIP = '') {
            var tbody = jQuery('.wpccm-activity-table tbody');
            tbody.html('<tr><td colspan="5">טוען נתונים...</td></tr>');
            
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
                    tbody.html('<tr><td colspan="5">שגיאה בטעינת הנתונים</td></tr>');
                }
            }).fail(function() {
                tbody.html('<tr><td colspan="5">שגיאה בחיבור לשרת</td></tr>');
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
                    loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total + ' עבור IP מדויק: ' + currentSearchIP + ' (עמוד ' + data.current_page + ')');
                } else {
                    loadingInfo.text('נטענו את כל הנתונים עבור IP מדויק: ' + currentSearchIP + ': ' + data.data.length + ' רשומות');
                }
                searchInfo.text('תוצאות חיפוש מדויק עבור IP: ' + currentSearchIP);
            } else {
                if (data.per_page > 0) {
                    loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total + ' (עמוד ' + data.current_page + ')');
                } else {
                    loadingInfo.text('נטענו את כל הנתונים: ' + data.data.length + ' רשומות');
                }
                searchInfo.text('');
            }
            
            if (!data.data || data.data.length === 0) {
                tbody.html('<tr><td colspan="5">אין נתוני היסטוריה</td></tr>');
                loadingInfo.text('');
                return;
            }
            
            data.data.forEach(function(record) {
                var date = new Date(record.created_at).toLocaleString('he-IL');
                var actionText = getActionText(record.action_type);
                var categories = '';
                
                try {
                    var categoriesData = JSON.parse(record.categories_accepted || '[]');
                    if (Array.isArray(categoriesData) && categoriesData.length > 0) {
                        categories = categoriesData.join(', ');
                    } else {
                        categories = 'ללא קטגוריות';
                    }
                } catch (e) {
                    categories = 'נתונים לא תקינים';
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
                'accept': 'קבלה',
                'reject': 'דחייה', 
                'save': 'שמירה',
                'accept_all': 'קבלת הכל',
                'reject_all': 'דחיית הכל'
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
                paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage - 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">« הקודם</button> ';
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
                paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage + 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">הבא »</button>';
            }
            
            paginationHtml += '</div>';
            
            jQuery('.wpccm-activity-table').after(paginationHtml);
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
                loadingInfo.text('מייצא נתונים עבור IP מדויק: ' + currentSearchIP + '...');
            } else {
                loadingInfo.text('מייצא נתונים...');
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
                loadingInfo.text('הייצוא הושלם בהצלחה עבור IP מדויק: ' + currentSearchIP + '!');
            } else {
                loadingInfo.text('הייצוא הושלם בהצלחה!');
            }
            
            setTimeout(function() {
                loadingInfo.text('');
            }, 3000);
        }
        
        function searchByIP() {
            var searchIP = jQuery('#search-ip').val().trim();
            var searchInfo = jQuery('.wpccm-search-info');
            
            if (searchIP === '') {
                searchInfo.text('אנא הזן כתובת IP מדויקת לחיפוש');
                return;
            }
            
            searchInfo.text('מחפש...');
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
        </script>
        <?php
    }


    /**
     * Dashboard Connection Section Callback
     */
    public function dashboard_connection_section_callback() {
        echo '<p>הפעל את הפלאגין על ידי הגדרת החיבור לדשבורד המרכזי. הפלאגין לא יעבוד עד שתתחבר לדשבורד.</p>';
    }

    /**
     * Dashboard API URL Field
     */
    public function field_dashboard_api_url() {
        $value = WPCCM_DASHBOARD_API_URL;
        echo '<input type="url" name="wpccm_dashboard_api_url" value="' . esc_attr($value) . '" class="large-text" disabled />';
        echo '<p class="description">כתובת ה-API של הדשבורד המרכזי</p>';
    }

    /**
     * Dashboard License Key Field
     */
    public function field_dashboard_license_key() {
        $value = get_option('wpccm_license_key', '');
        echo '<input type="text" name="wpccm_license_key" value="' . esc_attr($value) . '" class="large-text" placeholder="הכנס את מפתח הרישיון" />';
        echo '<p class="description">מפתח הרישיון מהדשבורד המרכזי</p>';
    }


    /**
     * Dashboard Master Code Field
     */
    public function field_dashboard_master_code() {
        $master_code = get_option('wpccm_master_code', '');
        $stored_master_code = get_option('wpccm_stored_master_code', '');
        $is_activated = !empty($master_code) && !empty($stored_master_code) && $master_code === $stored_master_code;
        
        echo '<div class="master-code-container">';
        echo '<input type="text" id="master_code_input" name="wpccm_master_code" value="' . esc_attr($master_code) . '" class="regular-text" placeholder="הכנס קוד מאסטר" />';
        echo '<button type="button" class="button" id="save-master-code">שמור קוד מאסטר</button>';
        echo '<button type="button" class="button button-secondary" id="remove-master-code">הסר קוד מאסטר</button>';
        echo '</div>';
        
        if ($is_activated) {
            echo '<p class="description" style="color: green;"><strong>✓ הפלאגין מופעל באמצעות קוד מאסטר</strong></p>';
        } else {
            echo '<p class="description">קוד מאסטר מאפשר הפעלת הפלאגין ללא צורך בדשבורד</p>';
        }
        
        echo '<div id="master-code-result"></div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            $("#save-master-code").on("click", function() {
                var masterCode = $("#master_code_input").val();
                var $result = $("#master-code-result");
                
                if (!masterCode) {
                    $result.html("<p style=\"color: red;\">אנא הכנס קוד מאסטר</p>");
                    return;
                }
                
                $result.html("<p>שומר קוד מאסטר...</p>");
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "wpccm_save_master_code",
                        nonce: "' . wp_create_nonce('wpccm_admin_nonce') . '",
                        master_code: masterCode
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html("<p style=\"color: green;\">" + response.data.message + "</p>");
                            if (response.data.activated) {
                                location.reload();
                            }
                        } else {
                            $result.html("<p style=\"color: red;\">" + response.data + "</p>");
                        }
                    },
                    error: function() {
                        $result.html("<p style=\"color: red;\">שגיאה בשמירת קוד המאסטר</p>");
                    }
                });
            });
            
            $("#remove-master-code").on("click", function() {
                var $result = $("#master-code-result");
                $result.html("<p>מסיר קוד מאסטר...</p>");
                
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "wpccm_remove_master_code",
                        nonce: "' . wp_create_nonce('wpccm_admin_nonce') . '"
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html("<p style=\"color: green;\">" + response.data.message + "</p>");
                            $("#master_code_input").val("");
                            location.reload();
                        } else {
                            $result.html("<p style=\"color: red;\">" + response.data + "</p>");
                        }
                    },
                    error: function() {
                        $result.html("<p style=\"color: red;\">שגיאה בהסרת קוד המאסטר</p>");
                    }
                });
            });
        });
        </script>';
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
        // Check for master code first
        $master_code = get_option('wpccm_master_code', '');
        $stored_master_code = get_option('wpccm_stored_master_code', '');
        
        // If master code is set and matches stored code, activate plugin
        if (!empty($master_code) && !empty($stored_master_code) && $master_code === $stored_master_code) {
            return true;
        }
        
        // Regular activation check
        $license_key = get_option('wpccm_license_key', '');
        
        if (empty($license_key)) {
            return false;
        }
        
        // Check if license is valid
        $dashboard = WP_CCM_Dashboard::get_instance();
        return $dashboard->test_connection_silent();
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
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
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
     * AJAX handler for getting debug log
     */
    public function ajax_get_debug_log() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        // Get the error log content
        $log_content = '';
        
        // Try to read WordPress debug log
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
            // Read only WPCCM related entries from the last 1000 lines
            $lines = file($debug_log_path);
            $wpccm_lines = [];
            
            // Get last 1000 lines and filter for WPCCM entries
            $last_lines = array_slice($lines, -1000);
            foreach ($last_lines as $line) {
                if (strpos($line, 'WPCCM:') !== false) {
                    $wpccm_lines[] = $line;
                }
            }
            
            if (!empty($wpccm_lines)) {
                $log_content = implode('', array_slice($wpccm_lines, -50)); // Last 50 WPCCM entries
            } else {
                $log_content = "No WPCCM debug entries found in the last 1000 log lines.\n";
                $log_content .= "Debug log path: " . $debug_log_path . "\n";
                $log_content .= "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'enabled' : 'disabled') . "\n";
                $log_content .= "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'enabled' : 'disabled') . "\n";
            }
        } else {
            $log_content = "Debug log file not found or not readable.\n";
            $log_content .= "Expected path: " . $debug_log_path . "\n";
            $log_content .= "File exists: " . (file_exists($debug_log_path) ? 'yes' : 'no') . "\n";
            $log_content .= "File readable: " . (is_readable($debug_log_path) ? 'yes' : 'no') . "\n";
            $log_content .= "WP_DEBUG: " . (defined('WP_DEBUG') && WP_DEBUG ? 'enabled' : 'disabled') . "\n";
            $log_content .= "WP_DEBUG_LOG: " . (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ? 'enabled' : 'disabled') . "\n";
        }
        
        wp_send_json_success([
            'log' => $log_content,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * AJAX handler for saving master code
     */
    public function ajax_save_master_code() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        $master_code = isset($_POST['master_code']) ? sanitize_text_field($_POST['master_code']) : '';
        
        if (empty($master_code)) {
            wp_send_json_error(['message' => 'קוד המאסטר לא יכול להיות ריק']);
        }
        
        // Save the master code
        update_option('wpccm_master_code', $master_code);
        
        // Also save it as stored code for comparison
        update_option('wpccm_stored_master_code', $master_code);
        
        wp_send_json_success([
            'message' => 'קוד המאסטר נשמר בהצלחה',
            'activated' => $this->is_plugin_activated()
        ]);
    }
    
    /**
     * AJAX handler for removing master code
     */
    public function ajax_remove_master_code() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'אין לך הרשאות מתאימות']);
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wpccm_admin_nonce')) {
            wp_send_json_error(['message' => 'בדיקת אבטחה נכשלה']);
        }
        
        // Remove the master code
        delete_option('wpccm_master_code');
        delete_option('wpccm_stored_master_code');
        
        wp_send_json_success([
            'message' => 'קוד המאסטר הוסר בהצלחה',
            'activated' => $this->is_plugin_activated()
        ]);
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
        $master_code = isset($_POST['master_code']) ? sanitize_text_field($_POST['master_code']) : '';
        $skip_dashboard = isset($_POST['skip_dashboard']) ? (bool)$_POST['skip_dashboard'] : false;
        
        // Get and sanitize banner settings
        $banner_title = isset($_POST['banner_title']) ? sanitize_text_field($_POST['banner_title']) : '';
        $banner_description = isset($_POST['banner_description']) ? sanitize_textarea_field($_POST['banner_description']) : '';
        $banner_policy_url = isset($_POST['banner_policy_url']) ? esc_url_raw($_POST['banner_policy_url']) : '';
        
        // Check if master code is valid
        $skip_dashboard_validation = ($master_code === '56588486');
        
        // Validate required fields (skip dashboard validation if master code is valid)
        if (!$skip_dashboard_validation && (empty($dashboard_api_url) || empty($license_key) || empty($website_id))) {
            wp_send_json_error('אנא מלא את כל שדות האקטיבציה (כתובת API, מפתח רישיון, מזהה אתר)');
        }
        
        if (empty($banner_title) || empty($banner_description)) {
            wp_send_json_error('אנא מלא את כותרת הבאנר ותיאור הבאנר');
        }
        
        // Save dashboard settings (only if not skipping dashboard)
        // if (!$skip_dashboard_validation) {
        // var_dump("WPCCM: License key:", $license_key);
        update_option('wpccm_license_key', $license_key);
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
                'license_key' => $skip_dashboard_validation ? 'skipped' : substr($license_key, 0, 8) . '...',
                'master_code_used' => $skip_dashboard_validation,
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
            foreach ($_COOKIE as $name => $value) {
                // Skip WordPress admin cookies
                // if (strpos($name, 'wordpress_') === 0 || 
                //     strpos($name, 'wp-') === 0 || 
                //     $name === 'PHPSESSID' ||
                //     strpos($name, 'comment_') === 0) {
                //     continue;
                // }
                $cookies[] = $name;
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
                    
                    if (!in_array($name, $cookies)) {
                        $cookies[] = $name;
                    }
                }
            }
        }
        
        // Remove duplicates and sort
        $cookies = array_unique($cookies);
        sort($cookies);
        
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
            'message' => 'הגדרות העיצוב נשמרו בהצלחה! מיקום באנר: ' . $banner_position . ', מיקום כפתור צף: ' . $floating_button_position . ', גודל: ' . $size,
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
}
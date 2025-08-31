<?php
/**
 * CC Settings Page Class
 * Manages the admin settings page for cookie and script mappings
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * CC Settings Page Class
 */
class CC_Settings_Page {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'options-general.php',
            'Cookie Consent Mappings',
            'Cookie Mappings',
            'manage_options',
            'cc-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        // Register settings
        register_setting('cc_settings_group', 'cc_cookie_name_map');
        register_setting('cc_settings_group', 'cc_script_handle_map');
        
        // Add settings sections
        add_settings_section(
            'cc_cookie_mapping_section',
            'Cookie Name Mappings',
            array($this, 'render_cookie_section_description'),
            'cc-settings'
        );
        
        add_settings_section(
            'cc_script_mapping_section',
            'Script Handle Mappings',
            array($this, 'render_script_section_description'),
            'cc-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'cc_cookie_name_map',
            'Cookie Mappings',
            array($this, 'render_cookie_mapping_field'),
            'cc-settings',
            'cc_cookie_mapping_section'
        );
        
        add_settings_field(
            'cc_script_handle_map',
            'Script Mappings',
            array($this, 'render_script_mapping_field'),
            'cc-settings',
            'cc_script_mapping_section'
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_cc-settings') {
            return;
        }
        
        wp_enqueue_script(
            'wpccm-admin-settings',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin-settings.js',
            array('jquery', 'jquery-ui-sortable'),
            '1.0.0',
            true
        );
        
        wp_enqueue_style(
            'wpccm-admin-settings',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin-settings.css',
            array(),
            '1.0.0'
        );
        
        // Localize script
        wp_localize_script('wpccm-admin-settings', 'wpccmAdminData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpccm_admin_nonce'),
            'strings' => array(
                'confirmDelete' => 'Are you sure you want to delete this mapping?',
                'saving' => 'Saving...',
                'saved' => 'Settings saved successfully!',
                'error' => 'Error saving settings.'
            )
        ));
    }
    
    /**
     * Render cookie section description
     */
    public function render_cookie_section_description() {
        echo '<p>Map cookie names (or regex patterns) to consent categories. Cookies will be filtered based on user consent preferences.</p>';
    }
    
    /**
     * Render script section description
     */
    public function render_script_section_description() {
        echo '<p>Map script handles or domains to consent categories. Scripts will be loaded only when the corresponding category is allowed.</p>';
    }
    
    /**
     * Render cookie mapping field
     */
    public function render_cookie_mapping_field() {
        $cookie_map = get_option('cc_cookie_name_map', array());
        $this->render_mapping_table('cookie', $cookie_map);
    }
    
    /**
     * Render script mapping field
     */
    public function render_script_mapping_field() {
        $script_map = get_option('cc_script_handle_map', array());
        $this->render_mapping_table('script', $script_map);
    }
    
    /**
     * Render mapping table
     */
    private function render_mapping_table($type, $mappings) {
        $categories = array(
            'necessary' => 'Necessary',
            'functional' => 'Functional',
            'performance' => 'Performance',
            'analytics' => 'Analytics',
            'advertisement' => 'Advertisement',
            'others' => 'Others'
        );
        
        ?>
        <div class="wpccm-mapping-table" data-type="<?php echo esc_attr($type); ?>">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 40%;"><?php echo $type === 'cookie' ? 'Cookie Name/Regex' : 'Script Handle/Domain'; ?></th>
                        <th style="width: 30%;">Category</th>
                        <th style="width: 20%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="wpccm-<?php echo esc_attr($type); ?>-mappings">
                    <?php if (!empty($mappings)): ?>
                        <?php foreach ($mappings as $key => $category): ?>
                            <tr>
                                <td>
                                    <input type="text" 
                                           name="cc_<?php echo esc_attr($type); ?>_map[<?php echo esc_attr($key); ?>][key]" 
                                           value="<?php echo esc_attr($key); ?>" 
                                           class="regular-text" />
                                </td>
                                <td>
                                    <select name="cc_<?php echo esc_attr($type); ?>_map[<?php echo esc_attr($key); ?>][category]">
                                        <?php foreach ($categories as $cat_key => $cat_name): ?>
                                            <option value="<?php echo esc_attr($cat_key); ?>" 
                                                    <?php selected($category, $cat_key); ?>>
                                                <?php echo esc_html($cat_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <button type="button" class="button button-small wpccm-remove-mapping">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="wpccm-add-mapping">
                <button type="button" class="button button-secondary wpccm-add-mapping-row">
                    Add New <?php echo ucfirst($type); ?> Mapping
                </button>
            </div>
        </div>
        
        <script type="text/template" id="wpccm-<?php echo esc_attr($type); ?>-template">
            <tr>
                <td>
                    <input type="text" 
                           name="cc_<?php echo esc_attr($type); ?>_map[{{key}}][key]" 
                           value="" 
                           class="regular-text" 
                           placeholder="<?php echo $type === 'cookie' ? 'cookie_name or /regex/' : 'script_handle or domain.com'; ?>" />
                </td>
                <td>
                    <select name="cc_<?php echo esc_attr($type); ?>_map[{{key}}][category]">
                        <?php foreach ($categories as $cat_key => $cat_name): ?>
                            <option value="<?php echo esc_attr($cat_key); ?>">
                                <?php echo esc_html($cat_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-small wpccm-remove-mapping">
                        Remove
                    </button>
                </td>
            </tr>
        </script>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        ?>
        <div class="wrap">
            <h1>Cookie Consent Mappings</h1>
            
            <form method="post" action="options.php" id="wpccm-settings-form">
                <?php
                settings_fields('cc_settings_group');
                do_settings_sections('cc-settings');
                submit_button('Save Mappings');
                ?>
            </form>
            
            <div class="wpccm-settings-info">
                <h3>How to use:</h3>
                <ul>
                    <li><strong>Cookie Mappings:</strong> Enter cookie names or regex patterns (e.g., <code>/_ga.*/</code>) and assign them to consent categories.</li>
                    <li><strong>Script Mappings:</strong> Enter script handles (e.g., <code>google-analytics</code>) or domains and assign them to consent categories.</li>
                    <li><strong>Categories:</strong> Choose the appropriate consent category for each mapping.</li>
                </ul>
                
                <h3>Examples:</h3>
                <ul>
                    <li><strong>Cookie:</strong> <code>_ga</code> → <code>Analytics</code></li>
                    <li><strong>Cookie:</strong> <code>/_fbp.*/</code> → <code>Advertisement</code></li>
                    <li><strong>Script:</strong> <code>google-analytics</code> → <code>Analytics</code></li>
                    <li><strong>Script:</strong> <code>facebook-pixel</code> → <code>Advertisement</code></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save mappings
     */
    public function save_mappings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        
        // Save cookie mappings
        if (isset($_POST['cc_cookie_name_map'])) {
            $cookie_map = array();
            foreach ($_POST['cc_cookie_name_map'] as $mapping) {
                if (!empty($mapping['key']) && !empty($mapping['category'])) {
                    $cookie_map[sanitize_text_field($mapping['key'])] = sanitize_text_field($mapping['category']);
                }
            }
            update_option('cc_cookie_name_map', $cookie_map);
        }
        
        // Save script mappings
        if (isset($_POST['cc_script_handle_map'])) {
            $script_map = array();
            foreach ($_POST['cc_script_handle_map'] as $mapping) {
                if (!empty($mapping['key']) && !empty($mapping['category'])) {
                    $script_map[sanitize_text_field($mapping['key'])] = sanitize_text_field($mapping['category']);
                }
            }
            update_option('cc_script_handle_map', $script_map);
        }
        
        wp_redirect(admin_url('options-general.php?page=cc-settings&updated=true'));
        exit;
    }
}

// Initialize the settings page
new CC_Settings_Page();

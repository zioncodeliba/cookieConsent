<?php
/**
 * CC Settings Page View
 * Renders the admin settings page for cookie and script mappings
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the main settings page
 */
function wpccm_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Get current mappings
    $cookie_mappings = get_option('cc_cookie_name_map', array());
    $script_mappings = get_option('cc_script_handle_map', array());
    
    // Get consent categories
    $categories = array(
        'necessary' => 'Necessary',
        'functional' => 'Functional',
        'performance' => 'Performance',
        'analytics' => 'Analytics',
        'advertisement' => 'Advertisement',
        'others' => 'Others'
    );
    
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Cookie Consent Mappings</h1>
        
        <hr class="wp-header-end">
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings updated successfully!</p>
            </div>
        <?php endif; ?>
        
        <div class="wpccm-settings-container">
            <form method="post" action="options.php" id="wpccm-settings-form">
                <?php settings_fields('cc_settings_group'); ?>
                
                <!-- Cookie Mappings Section -->
                <div class="wpccm-settings-section">
                    <h2>Cookie Name Mappings</h2>
                    <p class="description">
                        Map cookie names (or regex patterns) to consent categories. 
                        Cookies will be filtered based on user consent preferences.
                    </p>
                    
                    <div class="wpccm-scan-controls">
                        <button type="button" class="button button-secondary wpccm-scan-cookies">
                            🔍 Scan Current Cookies
                        </button>
                        <button type="button" class="button button-secondary wpccm-scan-scripts">
                            🔍 Scan Registered Scripts
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px;"></span>
                    </div>
                    
                    <div class="wpccm-mapping-table" data-type="cookie">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Cookie Name/Regex</th>
                                    <th style="width: 30%;">Category</th>
                                    <th style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="wpccm-cookie-mappings">
                                <?php if (!empty($cookie_mappings)): ?>
                                    <?php foreach ($cookie_mappings as $key => $category): ?>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                       name="cc_cookie_name_map[<?php echo esc_attr($key); ?>][key]" 
                                                       value="<?php echo esc_attr($key); ?>" 
                                                       class="regular-text" 
                                                       placeholder="cookie_name or /regex/" />
                                            </td>
                                            <td>
                                                <select name="cc_cookie_name_map[<?php echo esc_attr($key); ?>][category]">
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
                            <button type="button" class="button button-secondary wpccm-add-mapping-row" data-type="cookie">
                                Add New Cookie Mapping
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Script Mappings Section -->
                <div class="wpccm-settings-section">
                    <h2>Script Handle Mappings</h2>
                    <p class="description">
                        Map script handles or domains to consent categories. 
                        Scripts will be loaded only when the corresponding category is allowed.
                    </p>
                    
                    <div class="wpccm-scan-controls">
                        <button type="button" class="button button-secondary wpccm-scan-scripts">
                            🔍 Scan Registered Scripts
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px;"></span>
                    </div>
                    
                    <div class="wpccm-mapping-table" data-type="script">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Script Handle/Domain</th>
                                    <th style="width: 30%;">Category</th>
                                    <th style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="wpccm-script-mappings">
                                <?php if (!empty($script_mappings)): ?>
                                    <?php foreach ($script_mappings as $key => $category): ?>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                       name="cc_script_handle_map[<?php echo esc_attr($key); ?>][key]" 
                                                       value="<?php echo esc_attr($key); ?>" 
                                                       class="regular-text" 
                                                       placeholder="script_handle or domain.com" />
                                            </td>
                                            <td>
                                                <select name="cc_script_handle_map[<?php echo esc_attr($key); ?>][category]">
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
                            <button type="button" class="button button-secondary wpccm-add-mapping-row" data-type="script">
                                Add New Script Mapping
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php submit_button('Save All Mappings', 'primary', 'submit', false); ?>
            </form>
        </div>
        
        <!-- Help Section -->
        <div class="wpccm-help-section">
            <h3>How to use Cookie Consent Mappings</h3>
            
            <div class="wpccm-help-content">
                <div class="wpccm-help-column">
                    <h4>Cookie Mappings</h4>
                    <ul>
                        <li><strong>Exact names:</strong> <code>_ga</code>, <code>_fbp</code>, <code>analytics_id</code></li>
                        <li><strong>Regex patterns:</strong> <code>/_ga.*/</code>, <code>/_fbp.*/</code>, <code>/analytics_.*/</code></li>
                        <li><strong>Wildcards:</strong> <code>google_*</code>, <code>facebook_*</code></li>
                    </ul>
                    
                    <h4>Script Mappings</h4>
                    <ul>
                        <li><strong>Script handles:</strong> <code>google-analytics</code>, <code>facebook-pixel</code></li>
                        <li><strong>Domains:</strong> <code>googletagmanager.com</code>, <code>facebook.net</code></li>
                        <li><strong>Plugin names:</strong> <code>woocommerce</code>, <code>yoast-seo</code></li>
                    </ul>
                </div>
                
                <div class="wpccm-help-column">
                    <h4>Consent Categories</h4>
                    <ul>
                        <li><strong>Necessary:</strong> Essential cookies for site functionality</li>
                        <li><strong>Functional:</strong> Cookies that enhance user experience</li>
                        <li><strong>Performance:</strong> Cookies for site optimization</li>
                        <li><strong>Analytics:</strong> Cookies for visitor tracking</li>
                        <li><strong>Advertisement:</strong> Cookies for targeted ads</li>
                        <li><strong>Others:</strong> Miscellaneous cookies</li>
                    </ul>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="wpccm-save-section">
                <button type="submit" class="button button-primary wpccm-save-mappings">
                    Save Mappings
                </button>
            </div>
            
            <div class="wpccm-examples">
                <h4>Common Examples</h4>
                <table class="wp-list-table widefat">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Name/Pattern</th>
                            <th>Category</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Cookie</td>
                            <td><code>_ga</code></td>
                            <td>Analytics</td>
                            <td>Google Analytics tracking</td>
                        </tr>
                        <tr>
                            <td>Cookie</td>
                            <td><code>/_fbp.*/</code></td>
                            <td>Advertisement</td>
                            <td>Facebook Pixel cookies</td>
                        </tr>
                        <tr>
                            <td>Script</td>
                            <td><code>google-analytics</code></td>
                            <td>Analytics</td>
                            <td>Google Analytics script</td>
                        </tr>
                        <tr>
                            <td>Script</td>
                            <td><code>facebook-pixel</code></td>
                            <td>Advertisement</td>
                            <td>Facebook Pixel script</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Templates for JavaScript -->
        <script type="text/template" id="wpccm-cookie-template">
            <tr>
                <td>
                    <input type="text" 
                           name="cc_cookie_name_map[{{key}}][key]" 
                           value="" 
                           class="regular-text" 
                           placeholder="cookie_name or /regex/" />
                </td>
                <td>
                    <select name="cc_cookie_name_map[{{key}}][category]">
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
        
        <script type="text/template" id="wpccm-script-template">
            <tr>
                <td>
                    <input type="text" 
                           name="cc_script_handle_map[{{key}}][key]" 
                           value="" 
                           class="regular-text" 
                           placeholder="script_handle or domain.com" />
                </td>
                <td>
                    <select name="cc_script_handle_map[{{key}}][category]">
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
    </div>
    <?php
}

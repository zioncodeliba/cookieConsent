<?php
/**
 * CC Automatic Detection Page View
 * Renders the admin page for automatic script/iframe detection
 * 
 * @package WP_Cookie_Consent_Manager
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the automatic detection page
 */
function wpccm_render_detect_page($detected_items) {
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Cookie Consent Manager</h1>
        <p class="description">Automatically detect scripts on your website and manage consent mappings for GDPR compliance.</p>
        
        <hr class="wp-header-end">
        
        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p>Settings saved successfully!</p>
            </div>
        <?php endif; ?>
        
        <div class="wpccm-detect-container">
            <!-- Debug Info -->
            <div class="wpccm-debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
                <strong>Debug Info:</strong><br>
                <span id="wpccm-debug-status">Loading...</span><br>
                <button type="button" onclick="//console.log('wpccmDetectData:', window.wpccmDetectData)">Check Data</button>
                <button type="button" onclick="//console.log('jQuery loaded:', typeof jQuery)">Check jQuery</button>
                <button type="button" onclick="//console.log('Current page hook:', 'settings_page_cc-detect')">Check Hook</button>
                <button type="button" onclick="//console.log('Current URL:', window.location.href)">Check URL</button>
                <button type="button" onclick="//console.log('Page title:', document.title)">Check Title</button>
                <button type="button" onclick="//console.log('Scripts loaded:', document.querySelectorAll('script[src*=&quot;detect-page&quot;]').length)">Check Scripts</button>
                <button type="button" onclick="testAjaxSave()">Test AJAX Save</button>
            </div>
            
            <style>
            .wpccm-mappings-section {
                margin-top: 30px;
                margin-bottom: 30px;
            }
            
            .wpccm-mapping-type {
                font-weight: bold;
                padding: 2px 8px;
                border-radius: 3px;
                color: white;
                font-size: 11px;
            }
            
            .wpccm-type-handle {
                background-color: #0073aa;
            }
            
            .wpccm-type-domain {
                background-color: #826eb4;
            }
            
            .wpccm-category-badge {
                padding: 2px 8px;
                border-radius: 3px;
                color: white;
                font-size: 11px;
                font-weight: bold;
            }
            
            .wpccm-category-necessary {
                background-color: #dc3232;
            }
            
            .wpccm-category-functional {
                background-color: #00a32a;
            }
            
            .wpccm-category-analytics {
                background-color: #0073aa;
            }
            
            .wpccm-category-performance {
                background-color: #826eb4;
            }
            
            .wpccm-category-marketing {
                background-color: #ff6900;
            }
            
            .wpccm-category-others {
                background-color: #646970;
            }
            
            .wpccm-source-badge {
                padding: 2px 8px;
                border-radius: 3px;
                background-color: #f0f0f1;
                color: #2c3338;
                font-size: 11px;
                border: 1px solid #c3c4c7;
            }
            
            .wpccm-delete-mapping {
                color: #dc3232 !important;
                border-color: #dc3232 !important;
            }
            
            .wpccm-delete-mapping:hover {
                background-color: #dc3232 !important;
                color: white !important;
            }
            </style>
            
            <script>
            function testAjaxSave() {
                jQuery.ajax({
                    url: wpccmDetectData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'cc_detect_save_map',
                        nonce: wpccmDetectData.nonce,
                        selected_items: JSON.stringify([{
                            key: 'test|script:test.js',
                            category: 'analytics',
                            handle: 'test-script',
                            domain: 'test.com'
                        }])
                    },
                    success: function(response) {
                        alert('Test AJAX Success: ' + JSON.stringify(response, null, 2));
                        //console.log('Test AJAX Success:', response);
                    },
                    error: function(xhr, status, error) {
                        alert('Test AJAX Error: ' + error + '\nStatus: ' + status + '\nResponse: ' + xhr.responseText);
                        console.error('Test AJAX Error:', xhr, status, error);
                    }
                });
            }
            </script>
            
            <!-- Scan Controls -->
            <div class="wpccm-scan-section">
                <h2>Scan Target Page</h2>
                <p class="description">
                    Enter a URL path on your site to automatically detect scripts and iframes.
                    The scanner will analyze the page and suggest consent categories.
                </p>
                
                <div class="wpccm-scan-controls">
                    <input type="text" 
                           id="wpccm-scan-url" 
                           class="regular-text" 
                           placeholder="/blog/ or /" 
                           value="/" />
                    <button type="button" class="button button-primary" id="wpccm-scan-button">
                        🔍 Scan Page
                    </button>
                    <span class="spinner" style="float: none; margin-left: 10px;"></span>
                </div>
                
                <!-- Hidden iframe for scanning -->
                <div id="wpccm-scan-iframe-container" style="display: none;">
                    <iframe id="wpccm-scan-iframe" 
                            style="width: 100%; height: 300px; border: 1px solid #ddd;"
                            sandbox="allow-scripts allow-same-origin">
                    </iframe>
                </div>
            </div>
            
            <!-- Results Table -->
            <div class="wpccm-results-section">
                <h2>Detected Scripts & Iframes</h2>
                <p class="description">
                    Review detected items and assign consent categories. 
                    Selected items will be saved to your mappings.
                </p>
                
                <div class="wpccm-results-controls">
                    <button type="button" class="button button-secondary" id="wpccm-select-all">
                        Select All
                    </button>
                    <button type="button" class="button button-secondary" id="wpccm-deselect-all">
                        Deselect All
                    </button>
                    <button type="button" class="button button-primary" id="wpccm-save-selected">
                        Save Selected
                    </button>
                </div>
                
                <div class="wpccm-results-table">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 5%;">
                                    <input type="checkbox" id="wpccm-select-all-checkbox" />
                                </th>
                                <th style="width: 10%;">Type</th>
                                <th style="width: 15%;">Handle</th>
                                <th style="width: 20%;">Source URL</th>
                                <th style="width: 12%;">Domain</th>
                                <th style="width: 12%;">Current CC</th>
                                <th style="width: 13%;">Suggested</th>
                                <th style="width: 13%;">Category</th>
                            </tr>
                        </thead>
                        <tbody id="wpccm-results-tbody">
                            <?php if (!empty($detected_items)): ?>
                                <?php foreach ($detected_items as $key => $item): ?>
                                    <tr data-key="<?php echo esc_attr($key); ?>">
                                        <td>
                                            <input type="checkbox" 
                                                   class="wpccm-item-checkbox" 
                                                   value="<?php echo esc_attr($key); ?>" />
                                        </td>
                                        <td>
                                            <span class="wpccm-item-type wpccm-type-<?php echo esc_attr($item['type']); ?>">
                                                <?php echo esc_html(ucfirst($item['type'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['handle'])): ?>
                                                <code><?php echo esc_html($item['handle']); ?></code>
                                            <?php else: ?>
                                                <em>—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['src'])): ?>
                                                <a href="<?php echo esc_url($item['src']); ?>" 
                                                   target="_blank" 
                                                   title="<?php echo esc_attr($item['src']); ?>">
                                                    <?php echo esc_html(wpccm_truncate_url($item['src'])); ?>
                                                </a>
                                            <?php else: ?>
                                                <em>—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['domain'])): ?>
                                                <code><?php echo esc_html($item['domain']); ?></code>
                                            <?php else: ?>
                                                <em>—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['cc'])): ?>
                                                <span class="wpccm-cc-badge wpccm-cc-<?php echo esc_attr($item['cc']); ?>">
                                                    <?php echo esc_html(ucfirst($item['cc'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <em>—</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['suggested'])): ?>
                                                <span class="wpccm-suggested-badge wpccm-suggested-<?php echo esc_attr($item['suggested']); ?>">
                                                    <?php echo esc_html(ucfirst($item['suggested'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <em>Unassigned</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="wpccm-category-select">
                                                <option value="unassigned" <?php echo ($item['category'] === 'unassigned') ? 'selected' : ''; ?>>Unassigned</option>
                                                <option value="necessary" <?php echo ($item['category'] === 'necessary') ? 'selected' : ''; ?>>Necessary</option>
                                                <option value="functional" <?php echo ($item['category'] === 'functional') ? 'selected' : ''; ?>>Functional</option>
                                                <option value="analytics" <?php echo ($item['category'] === 'analytics') ? 'selected' : ''; ?>>Analytics</option>
                                                <option value="performance" <?php echo ($item['category'] === 'performance') ? 'selected' : ''; ?>>Performance</option>
                                                <option value="marketing" <?php echo ($item['category'] === 'marketing') ? 'selected' : ''; ?>>Marketing</option>
                                                <option value="others" <?php echo ($item['category'] === 'others') ? 'selected' : ''; ?>>Others</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="wpccm-no-results">
                                    <td colspan="7" style="text-align: center; padding: 40px;">
                                        <p>No items detected yet. Use the scanner above to detect scripts and iframes.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Existing Mappings Section -->
            <div class="wpccm-mappings-section">
                <h2>Script Handle Mappings</h2>
                <p class="description">
                    Below are all the currently saved script handle mappings. You can edit or delete mappings as needed.
                </p>
                
                <form method="post" action="options.php" id="wpccm-mappings-form">
                    <?php settings_fields('wpccm_settings_group'); ?>
                    
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
                                <?php 
                                $script_mappings = get_option('cc_script_handle_map', array()); 
                                $domain_mappings = get_option('cc_script_domain_map', array());
                                $all_script_mappings = array_merge($script_mappings, $domain_mappings);
                                $categories = array(
                                    'necessary' => 'Necessary',
                                    'functional' => 'Functional',
                                    'performance' => 'Performance',
                                    'analytics' => 'Analytics',
                                    'marketing' => 'Marketing',
                                    'others' => 'Others'
                                );
                                ?>
                                <?php if (!empty($all_script_mappings)): ?>
                                    <?php foreach ($all_script_mappings as $key => $category): ?>
                                        <tr>
                                            <td>
                                                <input type="text" 
                                                       name="cc_script_handle_map[<?php echo esc_attr($key); ?>]" 
                                                       value="<?php echo esc_attr($key); ?>" 
                                                       class="regular-text wpccm-mapping-key" 
                                                       placeholder="script_handle or domain.com" 
                                                       data-original-key="<?php echo esc_attr($key); ?>" />
                                            </td>
                                            <td>
                                                <select name="cc_script_handle_map_categories[<?php echo esc_attr($key); ?>]" class="wpccm-mapping-category">
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
                                <?php else: ?>
                                    <tr id="wpccm-no-mappings">
                                        <td colspan="3" style="text-align: center; padding: 40px;">
                                            <p>No mappings found. Use the scanner above to detect and save script mappings.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <div class="wpccm-add-mapping" style="margin-top: 10px;">
                            <button type="button" class="button button-secondary wpccm-add-mapping-row" data-type="script">
                                Add New Script Mapping
                            </button>
                            <button type="submit" class="button button-primary" style="margin-left: 10px;">
                                Save All Mappings
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Template for new rows -->
            <script type="text/template" id="wpccm-script-template">
                <tr>
                    <td>
                        <input type="text" 
                               name="cc_script_handle_map[{{key}}]" 
                               value="" 
                               class="regular-text wpccm-mapping-key" 
                               placeholder="script_handle or domain.com" 
                               data-original-key="" />
                    </td>
                    <td>
                        <select name="cc_script_handle_map_categories[{{key}}]" class="wpccm-mapping-category">
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
            
            <!-- Help Section -->
            <div class="wpccm-help-section">
                <h3>How it works</h3>
                <ul>
                    <li><strong>Scan:</strong> Enter a URL path and click Scan. A hidden iframe will load the page.</li>
                    <li><strong>Detection:</strong> The scanner analyzes all &lt;script&gt; and &lt;iframe&gt; tags on the page.</li>
                    <li><strong>Review:</strong> Review detected items and assign consent categories.</li>
                    <li><strong>Save:</strong> Selected items are saved to your script and domain mappings.</li>
                    <li><strong>Manage:</strong> View and delete existing mappings in the table below.</li>
                </ul>
                
                <h3>Category Guidelines</h3>
                <ul>
                    <li><strong>Necessary:</strong> Essential for site functionality (WordPress core, security)</li>
                    <li><strong>Functional:</strong> Enhances user experience (forms, chat, preferences)</li>
                    <li><strong>Analytics:</strong> Performance and usage tracking (Google Analytics, stats)</li>
                    <li><strong>Marketing:</strong> Advertising and conversion tracking (Facebook Pixel, ads)</li>
                    <li><strong>Performance:</strong> Speed and optimization (CDN, caching, lazy loading)</li>
                    <li><strong>Others:</strong> Miscellaneous third-party services</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Helper function to truncate long URLs
 */
function wpccm_truncate_url($url, $length = 50) {
    if (strlen($url) <= $length) {
        return $url;
    }
    
    $parts = parse_url($url);
    $truncated = '';
    
    if (isset($parts['scheme'])) {
        $truncated .= $parts['scheme'] . '://';
    }
    
    if (isset($parts['host'])) {
        $truncated .= $parts['host'];
    }
    
    if (isset($parts['path'])) {
        $path = $parts['path'];
        if (strlen($path) > 20) {
            $path = '...' . substr($path, -17);
        }
        $truncated .= $path;
    }
    
    if (strlen($truncated) > $length) {
        $truncated = substr($truncated, 0, $length - 3) . '...';
    }
    
    return $truncated;
}

// Render the page
wpccm_render_detect_page($detected_items);

// Manual script inclusion for debugging
?>
<script type="text/javascript">
    //console.log('=== MANUAL SCRIPT INCLUSION ===');
    //console.log('Adding script manually...');
    
    // Create script element
    const script = document.createElement('script');
    script.src = '<?php echo plugin_dir_url(dirname(dirname(__FILE__))); ?>assets/js/admin/detect-page.js';
    script.type = 'text/javascript';
    
    // Add load event
    script.onload = function() {
        //console.log('Manual script loaded successfully!');
    };
    
    // Add error event
    script.onerror = function() {
        console.error('Manual script failed to load!');
    };
    
    // Append to head
    document.head.appendChild(script);
    //console.log('Script element added to head');
</script>
<?php

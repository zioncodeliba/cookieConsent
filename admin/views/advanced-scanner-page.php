<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wpccm-advanced-scanner-section">
    <!-- <h2>סורק סקריפטים מתקדם</h2>
    <p class="description">סרוק ונהל סקריפטים באתר שלך עם טכנולוגיה מתקדמת להתאמה עם הגדרות ה-GDPR.</p> -->
    
    <?php if (isset($_GET['settings-updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>הגדרות נשמרו בהצלחה!</p>
        </div>
    <?php endif; ?>
    
    <div class="wpccm-advanced-scanner-container">
        <!-- Scanner Section -->
        <div class="wpccm-scanner-section">
            <h2>סריקת סקריפטים אוטומטית מתקדמת</h2>
            <p class="description">
                הסורק המתקדם שלנו טוען את הדפים באמצעות iframe ומזהה באופן אוטומטי את כל הסקריפטים שנטענים בפועל.
                זה מאפשר זיהוי מדויק יותר מאשר סריקה סטטית של הקוד.
            </p>
            
            <div class="wpccm-scanner-controls">
                <button id="wpccm-start-advanced-scan" class="button button-primary">
                    <span class="dashicons dashicons-search"></span>
                    התחל סריקה מתקדמת
                </button>
                <button id="wpccm-clear-results" class="button button-secondary" style="display: none;">
                    <span class="dashicons dashicons-dismiss"></span>
                    נקה תוצאות
                </button>
                <span id="wpccm-scan-status" class="wpccm-status-text"></span>
            </div>
            
            <!-- Scanner iframe container -->
            <div id="wpccm-scanner-container" style="display: none;">
                <h3>תצוגה מקדימה וסריקה</h3>
                <div class="wpccm-iframe-wrapper">
                    <iframe id="wpccm-scanner-iframe" src="" style="width: 100%; height: 600px; border: 2px solid #0073aa; border-radius: 4px; background: #fff;"></iframe>
                </div>
                <div class="wpccm-scan-info">
                    <p><strong>הוראות:</strong> ה-iframe מציג את האתר שלך. נווט בין דפים כדי לגלות סקריפטים נוספים. הסורק יזהה אוטומטי את כל הסקריפטים שנטענים.</p>
                </div>
            </div>
            
            <!-- Detected Items Section -->
            <div id="wpccm-detected-items" style="display: none;">
                <h3>סקריפטים שזוהו על ידי הסורק המתקדם</h3>
                <div class="wpccm-items-controls">
                    <label class="wpccm-select-all-label">
                        <input type="checkbox" id="wpccm-select-all"> 
                        <strong>בחר הכל</strong>
                    </label>
                    <button id="wpccm-save-selected" class="button button-primary" disabled>
                        <span class="dashicons dashicons-saved"></span>
                        שמור נבחרים למיפוי
                    </button>
                    <span id="wpccm-selection-count" class="wpccm-count-display">0 נבחרים</span>
                </div>
                
                <div class="wpccm-items-table-wrapper">
                    <table class="wp-list-table widefat fixed striped wpccm-detected-table">
                        <thead>
                            <tr>
                                <th style="width: 5%;" class="check-column">בחר</th>
                                <th style="width: 30%;">Handle/Script</th>
                                <th style="width: 25%;">Domain</th>
                                <th style="width: 20%;">קטגוריה מוצעת</th>
                                <th style="width: 20%;">קטגוריה סופית</th>
                            </tr>
                        </thead>
                        <tbody id="wpccm-items-tbody">
                            <!-- Dynamic content will be added here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <hr style="margin: 40px 0;">
        
        <!-- Existing Mappings Section -->
        <div class="wpccm-mappings-section">
            <h2>מיפויי סקריפטים שמורים</h2>
            <p class="description">
                נהל את כל המיפויים השמורים. ניתן לערוך או למחוק מיפויים לפי הצורך. מיפויים אלה ישפיעו על איך סקריפטים נטענים באתר שלך.
            </p>
            
            <form method="post" action="#" id="wpccm-advanced-mappings-form">
                
                <div class="wpccm-mapping-table" data-type="script">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 40%;">Handle/Domain של הסקריפט</th>
                                <th style="width: 30%;">קטגוריה</th>
                                <th style="width: 20%;">פעולות</th>
                            </tr>
                        </thead>
                        <tbody id="wpccm-script-mappings">
                            <?php if (!empty($all_script_mappings)): ?>
                                <?php foreach ($all_script_mappings as $key => $category): ?>
                                    <tr>
                                        <td>
                                            <input type="text" 
                                                   name="wpccm_script_handle_map[<?php echo esc_attr($key); ?>]" 
                                                   value="<?php echo esc_attr($key); ?>" 
                                                   class="regular-text wpccm-mapping-key" 
                                                   placeholder="script_handle או domain.com" 
                                                   data-original-key="<?php echo esc_attr($key); ?>" />
                                        </td>
                                        <td>
                                            <select name="wpccm_script_handle_map_categories[<?php echo esc_attr($key); ?>]" class="wpccm-mapping-category">
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
                                                <span class="dashicons dashicons-trash"></span>
                                                הסר
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr id="wpccm-no-mappings">
                                    <td colspan="3" style="text-align: center; padding: 40px;">
                                        <div class="wpccm-empty-state">
                                            <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc;"></span>
                                            <p><strong>לא נמצאו מיפויים</strong></p>
                                            <p>השתמש בסורק המתקדם למעלה כדי לגלות ולשמור מיפויי סקריפטים.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="wpccm-add-mapping" style="margin-top: 15px;">
                        <button type="button" class="button button-secondary wpccm-add-mapping-row" data-type="script">
                            <span class="dashicons dashicons-plus-alt"></span>
                            הוסף מיפוי חדש
                        </button>
                        <button type="button" class="button button-primary" id="wpccm-save-new-method" style="margin-right: 10px;">
                            <span class="dashicons dashicons-saved"></span>
                            שמור את כל המיפויים
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
                           name="wpccm_script_handle_map[{{key}}]" 
                           value="" 
                           class="regular-text wpccm-mapping-key" 
                           placeholder="script_handle או domain.com" 
                           data-original-key="" />
                </td>
                <td>
                    <select name="wpccm_script_handle_map_categories[{{key}}]" class="wpccm-mapping-category">
                        <?php foreach ($categories as $cat_key => $cat_name): ?>
                            <option value="<?php echo esc_attr($cat_key); ?>">
                                <?php echo esc_html($cat_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-small wpccm-remove-mapping">
                        <span class="dashicons dashicons-trash"></span>
                        הסר
                    </button>
                </td>
            </tr>
        </script>
        
        <!-- Help Section -->
        <div class="wpccm-help-section" style="margin-top: 40px;">
            <div class="wpccm-help-card">
                <h3><span class="dashicons dashicons-info"></span> איך הסורק המתקדם עובד?</h3>
                <div class="wpccm-help-content">
                    <div class="wpccm-help-steps">
                        <div class="wpccm-help-step">
                            <div class="wpccm-step-number">1</div>
                            <div class="wpccm-step-content">
                                <strong>סריקה דינמית:</strong> הסורק טוען את האתר שלך ב-iframe ומזהה באמת את הסקריפטים שנטענים
                            </div>
                        </div>
                        <div class="wpccm-help-step">
                            <div class="wpccm-step-number">2</div>
                            <div class="wpccm-step-content">
                                <strong>זיהוי חכם:</strong> מערכת AI מציעה קטגוריות מתאימות בהתאם לתפקיד הסקריפט (אנליטיקס, שיווק, וכו')
                            </div>
                        </div>
                        <div class="wpccm-help-step">
                            <div class="wpccm-step-number">3</div>
                            <div class="wpccm-step-content">
                                <strong>ניהול מתקדם:</strong> שמור, ערוך ומחק מיפויים בממשק נוח עם יכולות עריכה מלאות
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="wpccm-help-card">
                <h4><span class="dashicons dashicons-shield-alt"></span> מה קורה אחרי השמירה?</h4>
                <p>המיפויים שנשמרו ישפיעו על איך הסקריפטים נטענים באתר:</p>
                <ul class="wpccm-help-list">
                    <li><strong>קטגוריות לא הכרחיות</strong> - הסקריפטים יעוכבו עד שהמשתמש יסכים</li>
                    <li><strong>הסכמת המשתמש</strong> - תשפיע על אילו סקריפטים יופעלו באופן אוטומטי</li>
                    <li><strong>עמידה בתקנות</strong> - מבטיח GDPR compliance מלא</li>
                    <li><strong>ביצועים משופרים</strong> - סקריפטים לא נחוצים לא יטענו בכלל</li>
                </ul>
            </div>
        </div>
    </div>

<style>
.wpccm-advanced-scanner-container {
    max-width: 1200px;
}

.wpccm-scanner-controls {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 4px solid #0073aa;
}

.wpccm-scanner-controls .button {
    margin-left: 10px;
}

.wpccm-status-text {
    margin-right: 15px;
    font-weight: bold;
    color: #0073aa;
}

.wpccm-iframe-wrapper {
    margin: 20px 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border-radius: 6px;
    overflow: hidden;
}

.wpccm-scan-info {
    background: #e7f3ff;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #b3d9ff;
    margin-top: 15px;
}

.wpccm-items-controls {
    margin: 20px 0;
    padding: 15px;
    background: #f0f6fc;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.wpccm-select-all-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
}

.wpccm-count-display {
    color: #666;
    font-style: italic;
}

.wpccm-items-table-wrapper {
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    background: #fff;
}

.wpccm-detected-table {
    margin: 0;
}

.wpccm-detected-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.wpccm-category-select, .wpccm-mapping-category {
    width: 100%;
}

.wpccm-mapping-key {
    width: 100%;
}

.wpccm-empty-state {
    text-align: center;
    color: #666;
}

.wpccm-empty-state p {
    margin: 10px 0;
}

.wpccm-help-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.wpccm-help-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.wpccm-help-card h3, .wpccm-help-card h4 {
    margin-top: 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.wpccm-help-steps {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.wpccm-help-step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.wpccm-step-number {
    background: #0073aa;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    flex-shrink: 0;
    margin-top: 2px;
}

.wpccm-step-content {
    flex: 1;
}

.wpccm-help-list {
    margin: 15px 0;
    padding-right: 20px;
}

.wpccm-help-list li {
    margin: 8px 0;
    line-height: 1.5;
}

/* RTL specific styles */
.wpccm-advanced-scanner-container {
    direction: rtl;
}

.wpccm-items-controls button {
    margin-right: 10px;
}

.wpccm-add-mapping button {
    margin-left: 10px;
}

/* Responsive design */
@media (max-width: 768px) {
    .wpccm-help-section {
        grid-template-columns: 1fr;
    }
    
    .wpccm-items-controls {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
    }
}

.button .dashicons {
    margin-top: 3px;
    margin-left: 5px;
}
</style>

<script>
// Initialize on page load
jQuery(document).ready(function($) {
    // Configuration
    const CONFIG = {
        selectors: {
            startScanButton: '#wpccm-start-advanced-scan',
            clearResultsButton: '#wpccm-clear-results',
            scanStatus: '#wpccm-scan-status',
            scannerContainer: '#wpccm-scanner-container',
            scannerIframe: '#wpccm-scanner-iframe',
            detectedItems: '#wpccm-detected-items',
            itemsTable: '#wpccm-items-tbody',
            selectAllCheckbox: '#wpccm-select-all',
            saveSelectedButton: '#wpccm-save-selected',
            selectionCount: '#wpccm-selection-count'
        },
        actions: {
            store: 'cc_detect_store',
            saveMap: 'cc_detect_save_map',
            deleteMapping: 'cc_detect_delete_mapping',
            getRegisteredScripts: 'cc_detect_get_registered_scripts',
            saveAllMappings: 'cc_detect_save_all_mappings'
        }
    };

    // Create AJAX data directly in PHP - using existing nonce action
    window.wpccmDetectData = {
        ajax_url: '<?php echo admin_url("admin-ajax.php"); ?>',
        nonce: '<?php echo wp_create_nonce("wpccm_admin_nonce"); ?>',
        site_url: '<?php echo home_url(); ?>'
    };
    
    // Initialize
    init();

    function init() {
        bindEvents();

    }

    function bindEvents() {
        // Scan controls
        $(CONFIG.selectors.startScanButton).on('click', startAdvancedScan);
        $(CONFIG.selectors.clearResultsButton).on('click', clearResults);
        $(CONFIG.selectors.saveSelectedButton).on('click', saveSelectedItems);
        
        // Table controls
        $(document).on('change', CONFIG.selectors.selectAllCheckbox, handleSelectAll);
        $(document).on('change', '.wpccm-item-checkbox', handleItemCheckboxChange);
        $(document).on('change', '.wpccm-category-select', handleCategoryChange);
        
        // Mapping table controls
        $(document).on('click', '.wpccm-add-mapping-row', handleAddMappingRow);
        $(document).on('click', '.wpccm-remove-mapping', handleRemoveMappingRow);
        
        // Handle save all mappings button click
        $(document).on('click', '#wpccm-save-new-method', function(e) {
            e.preventDefault();
            saveAllMappingsNewMethod();
        });
        
        // Listen for tab changes to check form existence and setup additional handlers if needed
        $(document).on('click', '.nav-tab[data-tab="mapping"]', function() {
            setTimeout(function() {
                
                // Ensure event handlers are attached even if tab is loaded dynamically
                const $form = $('#wpccm-advanced-mappings-form');
                const $submitBtn = $form.find('button[type="submit"]');
                
                if ($form.length && $submitBtn.length) {
                    
                    // Remove any existing handlers and add a fresh one (to prevent duplicates)
                    $submitBtn.off('click.wpccm-save').on('click.wpccm-save', function(e) {
                        e.preventDefault();
                        handleMappingsFormSubmit(e);
                    });
                }
            }, 500);
        });
        
        
    }
    


    function startAdvancedScan() {
        const $button = $(CONFIG.selectors.startScanButton);
        const $status = $(CONFIG.selectors.scanStatus);
        
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> סורק...');
        $status.text('מתחיל סריקה מתקדמת...');
        
        // Show scanner iframe
        const $container = $(CONFIG.selectors.scannerContainer);
        const $iframe = $(CONFIG.selectors.scannerIframe);
        
        // Set iframe source to site homepage
        $iframe.attr('src', wpccmDetectData.site_url || '/');
        $container.show();
        
        // Show clear button
        $(CONFIG.selectors.clearResultsButton).show();
        
        // Listen for iframe load and start detecting scripts
        $iframe.on('load', function() {
            setTimeout(function() {
                // Try to scan the iframe for real scripts
                scanIframeForScripts($iframe[0]);
            }, 1000);
        });
    }

    function scanIframeForScripts(iframe) {
        const $button = $(CONFIG.selectors.startScanButton);
        const $status = $(CONFIG.selectors.scanStatus);
        
        try {
            $status.text('סורק סקריפטים באתר...');
            
            // Try to access iframe content
            let iframeDoc;
            try {
                iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
            } catch (e) {
                /// If we can't access iframe content due to CORS, use the registered scripts from WordPress
                scanRegisteredScripts();
                return;
            }
            
            const detectedScripts = [];
            const seenScripts = new Set();
            
            // Scan script tags in iframe
            const scriptTags = iframeDoc.querySelectorAll('script[src]');
            
            scriptTags.forEach(function(script) {
                const src = script.src;
                if (!src || seenScripts.has(src)) return;
                
                seenScripts.add(src);
                
                try {
                    const url = new URL(src);
                    const domain = url.hostname;
                    const filename = url.pathname.split('/').pop() || 'unknown';
                    
                    // Try to extract handle from script attributes or src
                    let handle = script.id || script.getAttribute('data-handle') || filename.replace(/\.(js|min\.js)$/, '');
                    
                    // Suggest category based on domain and content
                    const suggestedCategory = suggestCategoryFromScript(src, domain, handle);
                    
                    detectedScripts.push({
                        key: handle + '-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5),
                        handle: handle,
                        domain: domain,
                        suggested: suggestedCategory,
                        category: suggestedCategory,
                        src: src
                    });
                } catch (e) {
                    console.log('Error processing script:', src, e);
                }
            });
            
            // Also scan for inline scripts that might load external resources
            const inlineScripts = iframeDoc.querySelectorAll('script:not([src])');
            inlineScripts.forEach(function(script) {
                const content = script.textContent || script.innerHTML;
                
                // Look for common patterns
                const patterns = [
                    /(?:google-analytics|gtag|ga\()/i,
                    /facebook.*pixel|fbq\(/i,
                    /googletagmanager/i,
                    /analytics|tracking|gtm/i
                ];
                
                patterns.forEach(function(pattern, index) {
                    if (pattern.test(content)) {
                        const services = ['Google Analytics', 'Facebook Pixel', 'Google Tag Manager', 'Generic Analytics'];
                        const categories = ['analytics', 'marketing', 'analytics', 'analytics'];
                        
                        detectedScripts.push({
                            key: 'inline-' + services[index].toLowerCase().replace(/\s+/g, '-') + '-' + Date.now(),
                            handle: services[index] + ' (Inline)',
                            domain: 'Inline Script',
                            suggested: categories[index],
                            category: categories[index],
                            src: 'Inline script detected'
                        });
                    }
                });
            });
            
            $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> התחל סריקה מתקדמת');
            
            if (detectedScripts.length > 0) {
                $status.text(`הסריקה הושלמה - נמצאו ${detectedScripts.length} סקריפטים`);
                displayDetectedItems(detectedScripts);
            } else {
                $status.text('הסריקה הושלמה - לא נמצאו סקריפטים חיצוניים');
                // Show WordPress registered scripts as fallback
                scanRegisteredScripts();
            }
            
        } catch (error) {
            console.error('Error scanning iframe:', error);
            $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> התחל סריקה מתקדמת');
            $status.text('שגיאה בסריקה - משתמש בשיטה אלטרנטיבית');
            
            // Fallback to registered scripts
            scanRegisteredScripts();
        }
    }

    function suggestCategoryFromScript(src, domain, handle) {
        const url = src.toLowerCase();
        const domainLower = domain.toLowerCase();
        const handleLower = handle.toLowerCase();
        
        // Analytics patterns
        if (domainLower.includes('google') && (url.includes('analytics') || url.includes('gtag') || url.includes('gtm'))) {
            return 'analytics';
        }
        
        // Marketing/Social patterns
        if (domainLower.includes('facebook') || domainLower.includes('twitter') || domainLower.includes('linkedin')) {
            return 'marketing';
        }
        
        if (url.includes('pixel') || url.includes('tracking') || url.includes('ads')) {
            return 'marketing';
        }
        
        // Performance patterns
        if (url.includes('cdn') || url.includes('cache') || url.includes('optimize')) {
            return 'performance';
        }
        
        // Functional patterns
        if (handleLower.includes('jquery') || handleLower.includes('bootstrap') || url.includes('wp-includes')) {
            return 'functional';
        }
        
        // Default
        return 'others';
    }

    function scanRegisteredScripts() {
        const $status = $(CONFIG.selectors.scanStatus);
        $status.text('סורק סקריפטים רשומים ב-WordPress...');
        
        // Use AJAX to get WordPress registered scripts
        $.ajax({
            url: wpccmDetectData.ajax_url,
            type: 'POST',
            data: {
                action: CONFIG.actions.getRegisteredScripts,
                nonce: wpccmDetectData.nonce
            },
            success: function(response) {
                if (response.success && response.data.scripts) {
                    $status.text(`נמצאו ${response.data.scripts.length} סקריפטים רשומים`);
                    displayDetectedItems(response.data.scripts);
                } else {
                    $status.text('לא נמצאו סקריפטים רשומים');
                    // Show mock data as last resort
                    showEnhancedDetectedItems();
                }
            },
            error: function() {
                $status.text('שגיאה בטעינת סקריפטים רשומים - משתמש בנתוני דמו');
                showEnhancedDetectedItems();
            }
        });
    }

    function showEnhancedDetectedItems() {
        // Enhanced mock data for demonstration
        const mockItems = [
            {
                key: 'google-analytics-gtag',
                handle: 'google-analytics',
                domain: 'googletagmanager.com',
                suggested: 'analytics',
                category: 'analytics',
                src: 'https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID'
            },
            {
                key: 'facebook-pixel',
                handle: 'facebook-pixel',
                domain: 'facebook.com',
                suggested: 'marketing',
                category: 'marketing',
                src: 'https://connect.facebook.net/en_US/fbevents.js'
            },
            {
                key: 'jquery-core',
                handle: 'jquery',
                domain: '',
                suggested: 'necessary',
                category: 'necessary',
                src: '/wp-includes/js/jquery/jquery.min.js'
            },
            {
                key: 'youtube-embed',
                handle: '',
                domain: 'youtube.com',
                suggested: 'marketing',
                category: 'unassigned',
                src: 'https://www.youtube.com/iframe_api'
            }
        ];
        
        displayDetectedItems(mockItems);
    }

    function displayDetectedItems(items) {
        const $container = $(CONFIG.selectors.detectedItems);
        const $tbody = $(CONFIG.selectors.itemsTable);
        
        $tbody.empty();
        
        items.forEach(function(item) {
            const row = createEnhancedItemRow(item);
            $tbody.append(row);
        });
        
        $container.show();
        updateSaveButtonState();
        updateSelectionCount();
    }

    function createEnhancedItemRow(item) {
        const categories = {
            'necessary': 'הכרחיות',
            'functional': 'פונקציונליות', 
            'performance': 'ביצועים',
            'analytics': 'אנליטיקס',
            'marketing': 'שיווק',
            'others': 'אחרות',
            'unassigned': 'לא מוקצה'
        };
        
        let categoryOptions = '';
        Object.keys(categories).forEach(function(key) {
            const selected = item.category === key ? 'selected' : '';
            categoryOptions += '<option value="' + key + '" ' + selected + '>' + categories[key] + '</option>';
        });
        
        const suggestedBadge = item.suggested ? 
            '<span class="wpccm-suggested-badge wpccm-suggested-' + item.suggested + '">' + categories[item.suggested] + '</span>' : 
            '<span class="wpccm-suggested-badge wpccm-suggested-none">לא מוצע</span>';
        
        return `
            <tr data-key="${item.key}" class="wpccm-detected-row">
                <td class="check-column">
                    <input type="checkbox" class="wpccm-item-checkbox" value="${item.key}" ${item.category !== 'unassigned' ? 'checked' : ''}>
                </td>
                <td>
                    <div class="wpccm-script-info">
                        <code class="wpccm-handle">${item.handle || 'N/A'}</code>
                        ${item.src ? '<div class="wpccm-src-preview" title="' + item.src + '">' + item.src.substring(0, 60) + (item.src.length > 60 ? '...' : '') + '</div>' : ''}
                    </div>
                </td>
                <td><code class="wpccm-domain">${item.domain || 'Local'}</code></td>
                <td>${suggestedBadge}</td>
                <td>
                    <select class="wpccm-category-select" data-key="${item.key}">
                        ${categoryOptions}
                    </select>
                </td>
            </tr>
        `;
    }

    function clearResults() {
        $(CONFIG.selectors.scannerContainer).hide();
        $(CONFIG.selectors.detectedItems).hide();
        $(CONFIG.selectors.clearResultsButton).hide();
        $(CONFIG.selectors.scanStatus).text('');
    }

    function handleSelectAll() {
        const isChecked = $(this).prop('checked');
        $('.wpccm-item-checkbox').prop('checked', isChecked);
        updateSaveButtonState();
        updateSelectionCount();
    }

    function handleItemCheckboxChange() {
        updateSelectAllState();
        updateSaveButtonState();
        updateSelectionCount();
    }

    function handleCategoryChange() {
        const $select = $(this);
        const $row = $select.closest('tr');
        const $checkbox = $row.find('.wpccm-item-checkbox');
        
        // Auto-check if category is not unassigned
        if ($select.val() !== 'unassigned') {
            $checkbox.prop('checked', true);
        } else {
            $checkbox.prop('checked', false);
        }
        
        updateSelectAllState();
        updateSaveButtonState();
        updateSelectionCount();
    }

    function updateSelectAllState() {
        const $checkboxes = $('.wpccm-item-checkbox');
        const checkedCount = $checkboxes.filter(':checked').length;
        const totalCount = $checkboxes.length;
        
        $(CONFIG.selectors.selectAllCheckbox).prop('indeterminate', checkedCount > 0 && checkedCount < totalCount);
        $(CONFIG.selectors.selectAllCheckbox).prop('checked', checkedCount === totalCount);
    }

    function updateSaveButtonState() {
        const checkedCount = $('.wpccm-item-checkbox:checked').length;
        $(CONFIG.selectors.saveSelectedButton).prop('disabled', checkedCount === 0);
    }

    function updateSelectionCount() {
        const checkedCount = $('.wpccm-item-checkbox:checked').length;
        $(CONFIG.selectors.selectionCount).text(checkedCount + ' נבחרים');
    }

    function saveSelectedItems() {
        const selectedItems = [];
        $('.wpccm-item-checkbox:checked').each(function() {
            const $checkbox = $(this);
            const $row = $checkbox.closest('tr');
            const key = $checkbox.val();
            const category = $row.find('.wpccm-category-select').val();
            const handle = $row.find('.wpccm-handle').text();
            const domain = $row.find('.wpccm-domain').text();
            
            selectedItems.push({
                key: key,
                handle: handle !== 'N/A' ? handle : '',
                domain: domain !== 'Local' ? domain : '',
                category: category
            });
        });
        
        if (selectedItems.length === 0) {
            alert('אנא בחר לפחות פריט אחד לשמירה');
            return;
        }
        
        // Show saving state
        const $button = $(CONFIG.selectors.saveSelectedButton);
        const originalText = $button.html();
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> שומר...');
        

        
        // Save mappings via AJAX
        $.ajax({
            url: wpccmDetectData.ajax_url,
            type: 'POST',
            data: {
                action: CONFIG.actions.saveMap,
                nonce: wpccmDetectData.nonce,
                selected_items: JSON.stringify(selectedItems)
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>המיפויים נשמרו בהצלחה! נשמרו ' + selectedItems.length + ' פריטים.</p></div>')
                        .insertAfter('.wp-heading-inline')
                        .delay(5000)
                        .fadeOut();
                    
                    // Add to mappings table
                    addItemsToMappingsTable(selectedItems);
                    
                    // Clear selection
                    $('.wpccm-item-checkbox').prop('checked', false);
                    updateSaveButtonState();
                    updateSelectionCount();
                    updateSelectAllState();
                } else {
                    alert('שגיאה בשמירה: ' + (response.data ? response.data.message : 'שגיאה לא ידועה'));
                }
            },
            error: function() {
                alert('שגיאה בתקשורת עם השרת');
            },
            complete: function() {
                $button.prop('disabled', false).html(originalText);
            }
        });
    }

    function addItemsToMappingsTable(selectedItems) {
        const $mappingsTbody = $('#wpccm-script-mappings');
        
        // Remove "no mappings" row if it exists
        $('#wpccm-no-mappings').remove();
        
        selectedItems.forEach(function(item) {
            if (item.category && item.category !== 'unassigned') {
                // Add handle mapping row if handle exists
                if (item.handle) {
                    const handleRow = createMappingRow('handle', item.handle, item.category);
                    $mappingsTbody.append(handleRow);
                }
                
                // Add domain mapping row if domain exists  
                if (item.domain) {
                    const domainRow = createMappingRow('domain', item.domain, item.category);
                    $mappingsTbody.append(domainRow);
                }
            }
        });
    }

    function createMappingRow(type, identifier, category) {
        const categories = {
            'necessary': 'הכרחיות',
            'functional': 'פונקציונליות',
            'performance': 'ביצועים', 
            'analytics': 'אנליטיקס',
            'marketing': 'שיווק',
            'others': 'אחרות'
        };
        
        let categoryOptions = '';
        Object.keys(categories).forEach(function(key) {
            const selected = key === category ? 'selected' : '';
            categoryOptions += '<option value="' + key + '" ' + selected + '>' + categories[key] + '</option>';
        });
        
        return `
            <tr>
                <td>
                    <input type="text" 
                           name="wpccm_script_handle_map[${identifier}]" 
                           value="${identifier}" 
                           class="regular-text wpccm-mapping-key" 
                           placeholder="script_handle או domain.com" 
                           data-original-key="${identifier}" />
                </td>
                <td>
                    <select name="wpccm_script_handle_map_categories[${identifier}]" class="wpccm-mapping-category">
                        ${categoryOptions}
                    </select>
                </td>
                <td>
                    <button type="button" class="button button-small wpccm-remove-mapping">
                        <span class="dashicons dashicons-trash"></span>
                        הסר
                    </button>
                </td>
            </tr>
        `;
    }

    function handleAddMappingRow() {
        const $tbody = $('#wpccm-script-mappings');
        const template = $('#wpccm-script-template').html();
        const timestamp = Date.now();
        
        // Remove "no mappings" row if it exists
        $('#wpccm-no-mappings').remove();
        
        // Replace template placeholders
        const newRow = template.replace(/\{\{key\}\}/g, 'new_' + timestamp);
        
        // Add new row with animation
        const $newRow = $(newRow).hide();
        $tbody.append($newRow);
        $newRow.fadeIn(300);
        
        // Focus on the new input field
        $tbody.find('tr:last input[type="text"]').focus();
    }

    function handleRemoveMappingRow() {
        const $button = $(this);
        const $row = $button.closest('tr');
        const $tbody = $row.closest('tbody');
        
        // Remove the row with animation
        $row.fadeOut(300, function() {
            $(this).remove();
            
            // Check if table is empty
            if ($tbody.find('tr').length === 0) {
                $tbody.html(`
                    <tr id="wpccm-no-mappings">
                        <td colspan="3" style="text-align: center; padding: 40px;">
                            <div class="wpccm-empty-state">
                                <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc;"></span>
                                <p><strong>לא נמצאו מיפויים</strong></p>
                                <p>השתמש בסורק המתקדם למעלה כדי לגלות ולשמור מיפויי סקריפטים.</p>
                            </div>
                        </td>
                    </tr>
                `);
            }
        });
    }


    function saveAllMappingsNewMethod() {
       
        // Debug: check table state
        const $tbody = $('#wpccm-script-mappings');
        const $allRows = $tbody.find('tr');
        
        // Collect all mappings from the form and convert to same format as saveSelectedItems
        const selectedItems = [];
        
        $('#wpccm-script-mappings tr').each(function() {
            const $row = $(this);
            const $keyInput = $row.find('.wpccm-mapping-key');
            const $categorySelect = $row.find('.wpccm-mapping-category');
            
            if ($keyInput.length && $categorySelect.length) {
                const key = $keyInput.val().trim();
                const category = $categorySelect.val();
                
                // Only add if both key and category have actual values
                if (key && key.length > 0 && category && category.length > 0) {
                    selectedItems.push({
                        key: key,
                        handle: key, // Use the key as handle (could be script handle)
                        domain: key.includes('.') ? key : '', // If contains dot, treat as domain
                        category: category
                    });
                } else {
                    console.log('Skipping empty row:', { key, category });
                }
            } else {
                console.log('Row missing input elements');
            }
        });
        
        if (selectedItems.length === 0) {
            alert('לא נמצאו מיפויים לשמירה. אנא הוסף מיפויים לפני השמירה.');
            return;
        }
        
        // Show saving state
        const $newButton = $('#wpccm-save-new-method');
        const originalText = $newButton.html();
        $newButton.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="animation: spin 1s linear infinite;"></span> שומר...');
        
        // Use the SAME AJAX action as the working save button!
        $.ajax({
            url: wpccmDetectData.ajax_url,
            type: 'POST',
            data: {
                action: CONFIG.actions.saveMap,  // Use the working action!
                nonce: wpccmDetectData.nonce,
                selected_items: JSON.stringify(selectedItems)
            },
            success: function(response) {
                
                if (response.success) {
                    // Reload the page to refresh the table
                    location.reload();
                } else {
                    alert('שגיאה בשמירה: ' + (response.data ? response.data.message : 'שגיאה לא ידועה'));
                }
            },
            error: function(xhr, status, error) {
                console.log('NEW METHOD - Error:', { xhr, status, error });
                alert('שגיאה בתקשורת עם השרת (שיטה חדשה): ' + error);
            },
            complete: function() {
                $newButton.prop('disabled', false).html(originalText);
            }
        });
    }

    function updateMappingsTable(mappings) {
        const $tbody = $('#wpccm-script-mappings');
        $tbody.empty();
        
        const categories = {
            'necessary': 'הכרחיות',
            'functional': 'פונקציונליות',
            'performance': 'ביצועים',
            'analytics': 'אנליטיקס',
            'marketing': 'שיווק',
            'others': 'אחרות'
        };
        
        if (Object.keys(mappings).length === 0) {
            // Show empty state
            $tbody.html(`
                <tr id="wpccm-no-mappings">
                    <td colspan="3" style="text-align: center; padding: 40px;">
                        <div class="wpccm-empty-state">
                            <span class="dashicons dashicons-search" style="font-size: 48px; color: #ccc;"></span>
                            <p><strong>לא נמצאו מיפויים</strong></p>
                            <p>השתמש בסורק המתקדם למעלה כדי לגלות ולשמור מיפויי סקריפטים.</p>
                        </div>
                    </td>
                </tr>
            `);
        } else {
            // Add rows for each mapping
            Object.keys(mappings).forEach(function(key) {
                const category = mappings[key];
                
                let categoryOptions = '';
                Object.keys(categories).forEach(function(catKey) {
                    const selected = catKey === category ? 'selected' : '';
                    categoryOptions += '<option value="' + catKey + '" ' + selected + '>' + categories[catKey] + '</option>';
                });
                
                const row = `
                    <tr>
                        <td>
                            <input type="text" 
                                   name="wpccm_script_handle_map[${key}]" 
                                   value="${key}" 
                                   class="regular-text wpccm-mapping-key" 
                                   placeholder="script_handle או domain.com" 
                                   data-original-key="${key}" />
                        </td>
                        <td>
                            <select name="wpccm_script_handle_map_categories[${key}]" class="wpccm-mapping-category">
                                ${categoryOptions}
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-small wpccm-remove-mapping">
                                <span class="dashicons dashicons-trash"></span>
                                הסר
                            </button>
                        </td>
                    </tr>
                `;
                
                $tbody.append(row);
            });
        }
    }
    
    // Add CSS for suggested badges and enhanced styling
    $('<style>').text(`
        .wpccm-suggested-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .wpccm-suggested-analytics { background: #e3f2fd; color: #1976d2; }
        .wpccm-suggested-marketing { background: #fff3e0; color: #f57c00; }
        .wpccm-suggested-necessary { background: #e8f5e8; color: #388e3c; }
        .wpccm-suggested-functional { background: #f3e5f5; color: #7b1fa2; }
        .wpccm-suggested-performance { background: #fce4ec; color: #c2185b; }
        .wpccm-suggested-others { background: #f5f5f5; color: #616161; }
        .wpccm-suggested-none { background: #ffebee; color: #d32f2f; }
        
        .wpccm-script-info .wpccm-handle {
            display: block;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .wpccm-src-preview {
            font-size: 11px;
            color: #666;
            font-family: monospace;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .wpccm-detected-row:hover {
            background-color: #f8f9fa;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `).appendTo('head');
});
</script>

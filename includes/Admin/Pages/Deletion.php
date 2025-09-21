<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Page_Deletion {
    public function render() {
        ?>
        <div class="wrap">
            <h1>ניהול מחיקת נתונים</h1>
            <p class="description">ניהול בקשות מחיקת נתונים ממשתמשי האתר</p>
            
            <?php $this->render_deletion_requests_tab(); ?>
        </div>
        <?php
    }

    private function render_deletion_requests_tab_old() {
        global $wpdb;

        $deletion_requests_table = $wpdb->prefix . 'wpccm_deletion_requests';
        $this->create_deletion_requests_table();

        $requests = $wpdb->get_results(
            "SELECT * FROM $deletion_requests_table ORDER BY created_at DESC"
        );

        $opts = WP_CCM_Consent::get_options();
        $auto_delete = isset($opts['data_deletion']['auto_delete']) ? $opts['data_deletion']['auto_delete'] : false;

        echo '<div id="wpccm-deletion-requests-table">';

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

        $total_requests = count($requests);
        $pending_requests = count(array_filter($requests, static function ($r) {
            return $r->status === 'pending';
        }));
        $completed_requests = count(array_filter($requests, static function ($r) {
            return $r->status === 'completed';
        }));

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
                echo '<td><span class="wpccm-status ' . esc_attr($status_class) . '">' . esc_html($status_text) . '</span></td>';
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

        ?>
        <script>
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
                        nonce: "<?php echo wp_create_nonce('wpccm_delete_data'); ?>"
                    }, function(response) {
                        if (response && response.success) {
                            btn.closest('tr').fadeOut(300, function() { $(this).remove(); });
                        } else {
                            alert(response.data || "שגיאה במחיקת הנתונים");
                            btn.prop("disabled", false).text("מחק נתונים");
                        }
                    }).fail(function() {
                        alert("שגיאה בחיבור לשרת");
                        btn.prop("disabled", false).text("מחק נתונים");
                    });
                }
            });
        });
        </script>
        <?php
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

    private function get_deletion_type_text($type) {
        $types = [
            'browsing' => 'נתוני גלישה',
            'account' => 'נתוני גלישה וחשבון',
        ];

        return $types[$type] ?? $type;
    }

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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}

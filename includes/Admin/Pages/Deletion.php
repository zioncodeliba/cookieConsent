<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Page_Deletion {
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(wpccm_text('data_deletion_management')); ?></h1>
            <p class="description"><?php echo esc_html(wpccm_text('data_deletion_manage_requests')); ?></p>
            
            <?php $this->render_deletion_requests_tab(); ?>
        </div>
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
        echo '<h3>' . esc_html(wpccm_text('auto_deletion_settings')) . '</h3>';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . esc_html(wpccm_text('auto_deletion')) . '</th>';
        echo '<td>';
        echo '<label>';
        echo '<input type="checkbox" name="wpccm_options[data_deletion][auto_delete]" value="1" ' . ($auto_delete ? 'checked' : '') . ' />';
        echo ' ' . esc_html(wpccm_text('enable_auto_delete'));
        echo '</label>';
        echo '<p class="description">' . esc_html(wpccm_text('auto_delete_description')) . '</p>';
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
        echo '<div class="wpccm-stat-label" style="color: #6c757d; font-size: 14px;">' . esc_html(wpccm_text('total_requests')) . '</div>';
        echo '</div>';
        echo '<div class="wpccm-stat-box" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; text-align: center;">';
        echo '<div class="wpccm-stat-number" style="font-size: 24px; font-weight: bold; color: #856404;">' . $pending_requests . '</div>';
        echo '<div class="wpccm-stat-label" style="color: #856404; font-size: 14px;">' . esc_html(wpccm_text('pending_requests')) . '</div>';
        echo '</div>';
        echo '<div class="wpccm-stat-box" style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 15px; text-align: center;">';
        echo '<div class="wpccm-stat-number" style="font-size: 24px; font-weight: bold; color: #0c5460;">' . $completed_requests . '</div>';
        echo '<div class="wpccm-stat-label" style="color: #0c5460; font-size: 14px;">' . esc_html(wpccm_text('completed_requests')) . '</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Table
        echo '<div class="wpccm-table-container" style="margin-top: 15px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff;">';
        echo '<table class="widefat fixed striped" id="wpccm-deletion-requests-table" style="margin: 0; border: none;">';
        echo '<thead><tr>';
        echo '<th>' . esc_html(wpccm_text('request_date')) . '</th>';
        echo '<th>' . esc_html(wpccm_text('ip_address')) . '</th>';
        echo '<th>' . esc_html(wpccm_text('deletion_type')) . '</th>';
        echo '<th>' . esc_html(wpccm_text('status')) . '</th>';
        echo '<th>' . esc_html(wpccm_text('deletion_date')) . '</th>';
        echo '<th style="width: 120px;">' . esc_html(wpccm_text('actions')) . '</th>';
        echo '</tr></thead><tbody>';
        
        if (empty($requests)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6c757d;">' . esc_html(wpccm_text('no_deletion_requests')) . '</td></tr>';
        } else {
            foreach ($requests as $request) {
                $status_class = $request->status === 'completed' ? 'status-completed' : 'status-pending';
                $status_text = $request->status === 'completed' ? wpccm_text('status_completed') : wpccm_text('status_pending');
                
                echo '<tr>';
                echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($request->created_at))) . '</td>';
                echo '<td>' . esc_html($request->ip_address) . '</td>';
                echo '<td>' . esc_html($this->get_deletion_type_text($request->deletion_type)) . '</td>';
                echo '<td><span class="wpccm-status ' . $status_class . '">' . esc_html($status_text) . '</span></td>';
                echo '<td>' . ($request->deleted_at ? esc_html(date('d/m/Y H:i', strtotime($request->deleted_at))) : '-') . '</td>';
                echo '<td>';
                
                if ($request->status === 'pending') {
                    echo '<button type="button" class="button button-primary wpccm-delete-data-btn" data-ip="' . esc_attr($request->ip_address) . '" data-id="' . esc_attr($request->id) . '">' . esc_html(wpccm_text('delete_data')) . '</button>';
                } else {
                    echo '<span style="color: #6c757d;">' . esc_html(wpccm_text('status_completed')) . '</span>';
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
            const deletionTexts = {
                confirm_delete: ' . json_encode(wpccm_text('delete_confirm')) . ',
                deleting: ' . json_encode(wpccm_text('delete_in_progress')) . ',
                delete_data: ' . json_encode(wpccm_text('delete_data')) . ',
                error_deleting: ' . json_encode(wpccm_text('error_deleting_data')) . ',
                communication_error: ' . json_encode(wpccm_text('communication_error')) . ',
                unknown_error: ' . json_encode(wpccm_text('unknown_error')) . '
            };

            $(".wpccm-delete-data-btn").on("click", function() {
                if (confirm(deletionTexts.confirm_delete)) {
                    var ip = $(this).data("ip");
                    var id = $(this).data("id");
                    var btn = $(this);
                    
                    btn.prop("disabled", true).text(deletionTexts.deleting);
                    
                    $.post(ajaxurl, {
                        action: "wpccm_delete_data_manually",
                        ip_address: ip,
                        request_id: id,
                        nonce: "' . wp_create_nonce('wpccm_delete_data') . '"
                    }, function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            const err = deletionTexts.error_deleting.replace("%s", (response.data || deletionTexts.unknown_error));
                            alert(err);
                            btn.prop("disabled", false).text(deletionTexts.delete_data);
                        }
                    }).fail(function() {
                        alert(deletionTexts.communication_error);
                        btn.prop("disabled", false).text(deletionTexts.delete_data);
                    });
                }
            });
        });
        </script>';
    }

    private function get_deletion_type_text($type) {
        $types = [
            'browsing' => wpccm_text('deletion_type_browsing'),
            'account' => wpccm_text('deletion_type_account'),
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

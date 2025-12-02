<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Page_History {
    public function render() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(wpccm_text('activity_history')); ?></h1>
            <p class="description"><?php echo esc_html(wpccm_text('activity_history_description')); ?></p>
            
            <?php $this->render_history_content(); ?>
        </div>
        <?php
    }

    private function render_history_content_old() {
        ?>
        <div class="wpccm-history-container" style="margin-top: 20px;">
            <div class="wpccm-controls" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                <button class="button button-primary" onclick="loadConsentHistory(1, 100);"><?php echo esc_html(wpccm_text('load_100_records')); ?></button>
                <button class="button" onclick="exportData('csv');"><?php echo esc_html(wpccm_text('export_csv')); ?></button>
                <button class="button" onclick="exportData('json');"><?php echo esc_html(wpccm_text('export_json')); ?></button>
                <button class="button" onclick="searchByIP();"><?php echo esc_html(wpccm_text('search_button')); ?></button>
                <button class="button" onclick="clearSearch();"><?php echo esc_html(wpccm_text('clear_search')); ?></button>
                <span class="wpccm-loading-info"></span>
            </div>

            <div class="wpccm-search-controls" style="display: none;">
                <label>IP: <input type="text" id="wpccm-search-ip" placeholder="<?php echo esc_attr(wpccm_text('search_ip_placeholder')); ?>" /></label>
                <button class="button button-primary" onclick="performSearch();"><?php echo esc_html(wpccm_text('search_button')); ?></button>
                <button class="button" onclick="clearSearch();"><?php echo esc_html(wpccm_text('clear_search')); ?></button>
                <span class="wpccm-search-info"></span>
            </div>

            <table class="widefat fixed striped wpccm-activity-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php echo esc_html(wpccm_text('table_date')); ?></th>
                        <th><?php echo esc_html(wpccm_text('table_action_type')); ?></th>
                        <th><?php echo esc_html(wpccm_text('table_categories')); ?></th>
                        <th><?php echo esc_html(wpccm_text('table_user_ip')); ?></th>
                        <th><?php echo esc_html(wpccm_text('table_referer_url')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5"><?php echo esc_html(wpccm_text('loading_data')); ?></td></tr>
                </tbody>
            </table>
        </div>

        <style>
        .wpccm-activity-table td {
            vertical-align: middle;
        }

        .wpccm-activity-table .action-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
        }

        .wpccm-activity-table .action-accept { background: #d4edda; color: #155724; }
        .wpccm-activity-table .action-reject { background: #f8d7da; color: #721c24; }
        .wpccm-activity-table .action-withdraw { background: #fff3cd; color: #856404; }

        .wpccm-loading-info {
            font-style: italic;
            color: #666;
        }

        .wpccm-search-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }

        .wpccm-search-controls input[type="text"] {
            padding: 5px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        </style>

        <script>
        const wpccmHistoryTexts = {
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
            action_withdraw: <?php echo json_encode(wpccm_text('action_withdraw')); ?>,
            export_error: <?php echo json_encode(wpccm_text('export_error')); ?>,
        };
        const wpccmHistoryLocale = <?php echo json_encode(wpccm_get_lang() === 'he' ? 'he-IL' : 'en-US'); ?>;
        jQuery(document).ready(function($) {
            loadConsentHistory(1, 100, '');
        });

        function loadConsentHistory(page = 1, perPage = 100, searchIP = '') {
            const tbody = jQuery('.wpccm-activity-table tbody');
            tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.loading_data + '</td></tr>');

            jQuery.post(ajaxurl, {
                action: 'wpccm_get_consent_history',
                nonce: '<?php echo wp_create_nonce('wpccm_history'); ?>',
                page: page,
                per_page: perPage,
                search_ip: searchIP
            }).done(function(response) {
                if (response.success) {
                    renderConsentHistory(response.data);
                } else {
                    tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.error_loading_data + '</td></tr>');
                }
            }).fail(function() {
                tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.error_server_connection + '</td></tr>');
            });
        }

        function renderConsentHistory(data) {
            const tbody = jQuery('.wpccm-activity-table tbody');
            tbody.empty();

            const loadingInfo = jQuery('.wpccm-loading-info');
            const searchInfo = jQuery('.wpccm-search-info');
            const currentSearchIP = getCurrentSearchIP();

            if (currentSearchIP !== '') {
                loadingInfo.text(wpccmHistoryTexts.loaded_records_ip_page
                    .replace('%1$d', data.data.length)
                    .replace('%2$d', data.total)
                    .replace('%3$s', currentSearchIP)
                    .replace('%4$d', 1));
                searchInfo.text(wpccmHistoryTexts.search_results_ip.replace('%s', currentSearchIP));
            } else {
                loadingInfo.text(wpccmHistoryTexts.loaded_records_page
                    .replace('%1$d', data.data.length)
                    .replace('%2$d', data.total)
                    .replace('%3$d', 1));
                searchInfo.text('');
            }

            if (!data.data || data.data.length === 0) {
                tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.no_history_data + '</td></tr>');
                return;
            }

            data.data.forEach(function(record) {
                const date = new Date(record.created_at).toLocaleString(wpccmHistoryLocale);
                const actionText = getActionText(record.action_type);

                let categories = '';
                try {
                    const categoriesData = JSON.parse(record.categories_accepted || '[]');
                    categories = Array.isArray(categoriesData) && categoriesData.length
                        ? categoriesData.join(', ')
                        : wpccmHistoryTexts.no_categories_label;
                } catch (e) {
                    categories = wpccmHistoryTexts.invalid_data;
                }

                const row = '<tr>' +
                    '<td>' + escapeHtml(date) + '</td>' +
                    '<td><span class="action-badge action-' + record.action_type + '">' + actionText + '</span></td>' +
                    '<td>' + escapeHtml(categories) + '</td>' +
                    '<td>' + escapeHtml(record.user_ip || '-') + '</td>' +
                    '<td>' + escapeHtml(record.referer_url || '-') + '</td>' +
                '</tr>';

                tbody.append(row);
            });
        }

        function getActionText(action) {
            switch (action) {
                case 'accept': return wpccmHistoryTexts.action_accept;
                case 'reject': return wpccmHistoryTexts.action_reject;
                case 'withdraw': return wpccmHistoryTexts.action_withdraw || wpccmHistoryTexts.action_reject;
                default: return action;
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function exportData(format) {
            const searchIP = getCurrentSearchIP();

            jQuery.post(ajaxurl, {
                action: 'wpccm_export_consent_history',
                nonce: '<?php echo wp_create_nonce('wpccm_export'); ?>',
                format: format,
                search_ip: searchIP
            }).done(function(response) {
                if (response.success) {
                    const blob = new Blob([format === 'json' ? JSON.stringify(response.data, null, 2) : response.data], {
                        type: format === 'json' ? 'application/json' : 'text/csv;charset=utf-8;'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'consent-history.' + format;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                } else {
                    alert(response.data || wpccmHistoryTexts.export_error);
                }
            }).fail(function() {
                alert(wpccmHistoryTexts.error_server_connection);
            });
        }

        function searchByIP() {
            jQuery('.wpccm-search-controls').show();
        }

        function performSearch() {
            const ip = jQuery('#wpccm-search-ip').val().trim();
            if (!ip) {
                alert(wpccmHistoryTexts.enter_exact_ip);
                return;
            }
            loadConsentHistory(1, 100, ip);
            setCurrentSearchIP(ip);
        }

        function clearSearch() {
            jQuery('#wpccm-search-ip').val('');
            jQuery('.wpccm-search-controls').hide();
            setCurrentSearchIP('');
            loadConsentHistory(1, 100, '');
        }

        function getCurrentSearchIP() {
            return window.sessionStorage.getItem('wpccm_search_ip') || '';
        }

        function setCurrentSearchIP(ip) {
            window.sessionStorage.setItem('wpccm_search_ip', ip);
        }
        </script>
        <?php
    }

    /**
     * Render consent history tab
     */
    private function render_history_content() {
        ?>
        <!-- Controls for data loading -->
        <div class="wpccm-controls" style="margin-bottom: 15px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <div class="wpccm-control-group" style="display: flex; align-items: center; gap: 6px;">
                <label for="rows-per-page" style="margin: 0;"><?php echo esc_html(wpccm_text('rows_per_page')); ?></label>
                <select id="rows-per-page" style="min-width: 110px;">
                    <option value="50">50</option>
                    <option value="100" selected>100</option>
                    <option value="500">500</option>
                    <option value="0"><?php echo esc_html(wpccm_text('load_all_data')); ?></option>
                </select>
            </div>
            <div class="wpccm-control-group" style="flex: 1; min-width: 200px;">
                <input type="text" id="search-ip" placeholder="<?php echo esc_attr(wpccm_text('search_placeholder')); ?>" style="width: 100%; max-width: 320px;">
            </div>
            <div class="wpccm-control-group" style="display: flex; align-items: center; gap: 8px;">
                <button class="button" onclick="exportData('csv')"><?php echo esc_html(wpccm_text('export_csv')); ?></button>
                <button class="button" onclick="exportData('json')"><?php echo esc_html(wpccm_text('export_json')); ?></button>
            </div>
            <span class="wpccm-loading-info" style="color: #666;"></span>
            <span class="wpccm-search-info" style="color: #666;"></span>
        </div>
        
        <table class="wpccm-activity-table">
            <thead>
                <tr>
                    <th><?php echo esc_html(wpccm_text('table_date')); ?></th>
                    <th><?php echo esc_html(wpccm_text('table_action_type')); ?></th>
                    <th><?php echo esc_html(wpccm_text('table_categories')); ?></th>
                    <th><?php echo esc_html(wpccm_text('table_user_ip')); ?></th>
                    <th><?php echo esc_html(wpccm_text('table_referer_url')); ?></th>
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
        const wpccmHistoryTexts = {
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
            action_withdraw: <?php echo json_encode(wpccm_text('action_withdraw')); ?>,
            export_error: <?php echo json_encode(wpccm_text('export_error')); ?>,
        };
        const wpccmHistoryLocale = <?php echo json_encode(wpccm_get_lang() === 'he' ? 'he-IL' : 'en-US'); ?>;
        
        let wpccmCurrentPerPage = 100;
        let wpccmSearchDebounce;

        jQuery(document).ready(function($) {
            wpccmCurrentPerPage = parseInt($('#rows-per-page').val(), 10) || 100;

            $('#rows-per-page').on('change', function() {
                wpccmCurrentPerPage = parseInt($(this).val(), 10) || 100;
                loadConsentHistory(1, wpccmCurrentPerPage, getCurrentSearchTerm());
            });

            $('#search-ip').on('input', function() {
                const term = getCurrentSearchTerm();
                clearTimeout(wpccmSearchDebounce);
                wpccmSearchDebounce = setTimeout(function() {
                    loadConsentHistory(1, wpccmCurrentPerPage, term);
                }, 300);
            });

            loadConsentHistory(1, wpccmCurrentPerPage, '');
        });

        function loadConsentHistory(page = 1, perPage = 100, searchTerm = '') {
            var tbody = jQuery('.wpccm-activity-table tbody');
            tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.loading_data + '</td></tr>');
            
            jQuery.post(ajaxurl, {
                action: 'wpccm_get_consent_history',
                nonce: '<?php echo wp_create_nonce('wpccm_history'); ?>',
                page: page,
                per_page: perPage,
                search_term: searchTerm
            }, function(response) {
                if (response.success) {
                    renderConsentHistory(response.data);
                } else {
                    tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.error_loading_data + '</td></tr>');
                }
            }).fail(function() {
                tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.error_server_connection + '</td></tr>');
            });
        }
        
        function renderConsentHistory(data) {
            var tbody = jQuery('.wpccm-activity-table tbody');
            tbody.empty();
            
            // Update loading info
            var loadingInfo = jQuery('.wpccm-loading-info');
            var searchInfo = jQuery('.wpccm-search-info');
            var currentSearchIP = getCurrentSearchTerm();
            
            if (currentSearchIP !== '') {
                if (data.per_page > 0) {
                    loadingInfo.text(wpccmHistoryTexts.loaded_records_ip_page
                        .replace('%1$d', data.data.length)
                        .replace('%2$d', data.total)
                        .replace('%3$s', currentSearchIP)
                        .replace('%4$d', data.current_page));
                } else {
                    loadingInfo.text(wpccmHistoryTexts.loaded_records_ip_all
                        .replace('%1$s', currentSearchIP)
                        .replace('%2$d', data.data.length));
                }
                searchInfo.text(wpccmHistoryTexts.search_results_ip.replace('%s', currentSearchIP));
            } else {
                if (data.per_page > 0) {
                    loadingInfo.text(wpccmHistoryTexts.loaded_records_page
                        .replace('%1$d', data.data.length)
                        .replace('%2$d', data.total)
                        .replace('%3$d', data.current_page));
                } else {
                    loadingInfo.text(wpccmHistoryTexts.loaded_records_all.replace('%d', data.data.length));
                }
                searchInfo.text('');
            }
            
            if (!data.data || data.data.length === 0) {
                tbody.html('<tr><td colspan="5">' + wpccmHistoryTexts.no_history_data + '</td></tr>');
                loadingInfo.text('');
                return;
            }
            
            data.data.forEach(function(record) {
                var date = new Date(record.created_at).toLocaleString(wpccmHistoryLocale);
                var actionText = getActionText(record.action_type);
                var categories = '';
                
                try {
                    var categoriesData = JSON.parse(record.categories_accepted || '[]');
                    if (Array.isArray(categoriesData) && categoriesData.length > 0) {
                        categories = categoriesData.join(', ');
                    } else {
                        categories = wpccmHistoryTexts.no_categories_label;
                    }
                } catch (e) {
                    categories = wpccmHistoryTexts.invalid_data;
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
                'accept': wpccmHistoryTexts.action_accept,
                'reject': wpccmHistoryTexts.action_reject, 
                'save': wpccmHistoryTexts.action_save,
                'accept_all': wpccmHistoryTexts.action_accept_all,
                'reject_all': wpccmHistoryTexts.action_reject_all,
                'withdraw': wpccmHistoryTexts.action_withdraw || wpccmHistoryTexts.action_reject
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
                paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage - 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">« ' + wpccmHistoryTexts.previous + '</button> ';
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
                paginationHtml += '<button class="button" onclick="loadConsentHistory(' + (currentPage + 1) + ', ' + data.per_page + ', \'' + getCurrentSearchIP() + '\')">' + wpccmHistoryTexts.next + ' »</button>';
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
                loadingInfo.text(wpccmHistoryTexts.exporting_ip.replace('%s', currentSearchIP));
            } else {
                loadingInfo.text(wpccmHistoryTexts.exporting);
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
                loadingInfo.text(wpccmHistoryTexts.export_complete_ip.replace('%s', currentSearchIP));
            } else {
                loadingInfo.text(wpccmHistoryTexts.export_complete);
            }
            
            setTimeout(function() {
                loadingInfo.text('');
            }, 3000);
        }
        
        function searchByIP() {
            var searchIP = jQuery('#search-ip').val().trim();
            var searchInfo = jQuery('.wpccm-search-info');
            
            if (searchIP === '') {
                searchInfo.text(wpccmHistoryTexts.enter_exact_ip);
                return;
            }
            
            searchInfo.text(wpccmHistoryTexts.searching);
            loadConsentHistory(1, wpccmCurrentPerPage, searchIP);
        }
        
        function clearSearch() {
            jQuery('#search-ip').val('');
            jQuery('.wpccm-search-info').text('');
            loadConsentHistory(1, wpccmCurrentPerPage, '');
        }

        function getCurrentSearchTerm() {
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
}

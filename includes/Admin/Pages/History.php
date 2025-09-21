<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Page_History {
    public function render() {
        ?>
        <div class="wrap">
            <h1>היסטוריית פעילות</h1>
            <p class="description">צפייה בהיסטוריית פעילות המשתמשים באתר</p>
            
            <?php $this->render_history_content(); ?>
        </div>
        <?php
    }

    private function render_history_content_old() {
        ?>
        <div class="wpccm-history-container" style="margin-top: 20px;">
            <div class="wpccm-controls" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                <button class="button button-primary" onclick="loadConsentHistory(1, 100);">טעינת נתונים</button>
                <button class="button" onclick="exportData('csv');">ייצוא CSV</button>
                <button class="button" onclick="exportData('json');">ייצוא JSON</button>
                <button class="button" onclick="searchByIP();">חיפוש לפי IP</button>
                <button class="button" onclick="clearSearch();">ניקוי חיפוש</button>
                <span class="wpccm-loading-info"></span>
            </div>

            <div class="wpccm-search-controls" style="display: none;">
                <label>IP לחיפוש: <input type="text" id="wpccm-search-ip" placeholder="הכנס כתובת IP" /></label>
                <button class="button button-primary" onclick="performSearch();">חפש</button>
                <button class="button" onclick="clearSearch();">נקה</button>
                <span class="wpccm-search-info"></span>
            </div>

            <table class="widefat fixed striped wpccm-activity-table" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th>תאריך</th>
                        <th>פעולה</th>
                        <th>קטגוריות מאושרות</th>
                        <th>IP</th>
                        <th>מקור</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5">בחר פעולה מהכפתורים למעלה כדי לטעון נתונים.</td></tr>
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
        jQuery(document).ready(function($) {
            loadConsentHistory(1, 100, '');
        });

        function loadConsentHistory(page = 1, perPage = 100, searchIP = '') {
            const tbody = jQuery('.wpccm-activity-table tbody');
            tbody.html('<tr><td colspan="5">טוען נתונים...</td></tr>');

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
                    tbody.html('<tr><td colspan="5">שגיאה בטעינת הנתונים</td></tr>');
                }
            }).fail(function() {
                tbody.html('<tr><td colspan="5">שגיאה בחיבור לשרת</td></tr>');
            });
        }

        function renderConsentHistory(data) {
            const tbody = jQuery('.wpccm-activity-table tbody');
            tbody.empty();

            const loadingInfo = jQuery('.wpccm-loading-info');
            const searchInfo = jQuery('.wpccm-search-info');
            const currentSearchIP = getCurrentSearchIP();

            if (currentSearchIP !== '') {
                loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total + ' עבור IP: ' + currentSearchIP);
                searchInfo.text('חיפוש לפי IP: ' + currentSearchIP);
            } else {
                loadingInfo.text('נטענו ' + data.data.length + ' רשומות מתוך ' + data.total);
                searchInfo.text('');
            }

            if (!data.data || data.data.length === 0) {
                tbody.html('<tr><td colspan="5">אין נתוני היסטוריה</td></tr>');
                return;
            }

            data.data.forEach(function(record) {
                const date = new Date(record.created_at).toLocaleString('he-IL');
                const actionText = getActionText(record.action_type);

                let categories = '';
                try {
                    const categoriesData = JSON.parse(record.categories_accepted || '[]');
                    categories = Array.isArray(categoriesData) && categoriesData.length
                        ? categoriesData.join(', ')
                        : 'ללא קטגוריות';
                } catch (e) {
                    categories = 'נתונים לא תקינים';
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
                case 'accept': return 'קיבל הכל';
                case 'reject': return 'דחה הכל';
                case 'withdraw': return 'משיכת הסכמה';
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
                    alert(response.data || 'שגיאה בייצוא הנתונים');
                }
            }).fail(function() {
                alert('שגיאה בחיבור לשרת');
            });
        }

        function searchByIP() {
            jQuery('.wpccm-search-controls').show();
        }

        function performSearch() {
            const ip = jQuery('#wpccm-search-ip').val().trim();
            if (!ip) {
                alert('אנא הזן כתובת IP לחיפוש');
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
}

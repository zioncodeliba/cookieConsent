<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_CCM_Admin_Ajax_Consent {
    public function register() {
        add_action('wp_ajax_wpccm_get_consent_stats', [$this, 'get_consent_stats']);
        add_action('wp_ajax_wpccm_get_consent_history', [$this, 'get_consent_history']);
        add_action('wp_ajax_wpccm_export_consent_history', [$this, 'export_consent_history']);
    }

    public function get_consent_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpccm_consent_history';
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
            'total_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_ip) FROM $table"),
            'active_cookies' => $this->get_active_cookies_count(),
        ];

        wp_send_json_success($stats);
    }

    public function get_consent_history() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_history')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpccm_consent_history';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($table_exists !== $table) {
            wp_send_json_success([
                'data' => [],
                'total' => 0,
                'per_page' => 20,
                'current_page' => 1,
                'message' => 'No consent history table found. Click "Create DB Tables" to create it.',
            ]);
            return;
        }

        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = intval($_POST['per_page'] ?? 100);
        $search_term = sanitize_text_field($_POST['search_term'] ?? ($_POST['search_ip'] ?? ''));

        $where_clause = '';
        $where_params = [];
        if ($search_term !== '') {
            $like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_clause = "WHERE user_ip LIKE %s OR action_type LIKE %s OR referer_url LIKE %s OR categories_accepted LIKE %s";
            $where_params = [$like, $like, $like, $like];
        }

        if ($per_page <= 0) {
            $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC";
            $results = $where_params
                ? $wpdb->get_results($wpdb->prepare($query, $where_params))
                : $wpdb->get_results($query);
            $total = count($results);
            $paged_results = $results;
        } else {
            $offset = ($page - 1) * $per_page;
            $query = "SELECT SQL_CALC_FOUND_ROWS * FROM $table $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params = $where_params;
        $params[] = $per_page;
        $params[] = $offset;

        $paged_results = $wpdb->get_results($wpdb->prepare($query, $params));
            $total = intval($wpdb->get_var('SELECT FOUND_ROWS()'));
        }

        wp_send_json_success([
            'data' => $paged_results ?: [],
            'total' => intval($total),
            'per_page' => $per_page > 0 ? $per_page : $total,
            'current_page' => $page,
        ]);
    }

    public function export_consent_history() {
        if (!current_user_can('manage_options') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wpccm_export')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'wpccm_consent_history';

        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($table_exists !== $table) {
            wp_send_json_error('No consent history table found');
            return;
        }

        $search_term = sanitize_text_field($_POST['search_term'] ?? ($_POST['search_ip'] ?? ''));
        $where_clause = '';
        $where_params = [];
        if ($search_term !== '') {
            $like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_clause = "WHERE user_ip LIKE %s OR action_type LIKE %s OR referer_url LIKE %s OR categories_accepted LIKE %s";
            $where_params = [$like, $like, $like, $like];
        }

        $query = "SELECT * FROM $table $where_clause ORDER BY created_at DESC";
        $all_records = $where_params
            ? $wpdb->get_results($wpdb->prepare($query, $where_params), ARRAY_A)
            : $wpdb->get_results($query, ARRAY_A);
        $format = sanitize_text_field($_POST['format'] ?? 'csv');

        if ($format === 'json') {
            $this->export_to_json($all_records);
        } else {
            $this->export_to_csv($all_records);
        }
    }

    private function get_active_cookies_count() {
        $options = get_option('wpccm_options', []);
        $cookies = $options['purge']['cookies'] ?? [];
        return count($cookies);
    }

    private function export_to_csv($data) {
        $filename = 'consent-history-' . date('Y-m-d-H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($output, ['תאריך', 'סוג פעולה', 'קטגוריות', 'IP משתמש', 'User Agent', 'URL הפניה']);

        foreach ($data as $row) {
            $categories = '';
            try {
                $categoriesData = json_decode($row['categories_accepted'] ?? '[]', true);
                if (is_array($categoriesData)) {
                    $categories = implode(', ', $categoriesData);
                }
            } catch (Exception $e) {
                $categories = 'נתונים לא תקינים';
            }

            fputcsv($output, [
                $row['created_at'] ?? '',
                $row['action_type'] ?? '',
                $categories,
                $row['user_ip'] ?? '',
                $row['user_agent'] ?? '',
                $row['referer_url'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    private function export_to_json($data) {
        $filename = 'consent-history-' . date('Y-m-d-H-i-s') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

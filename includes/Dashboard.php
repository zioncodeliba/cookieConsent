<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Dashboard Integration Class
 * מחבר את הפלאגין לדשבורד המרכזי
 */
class WP_CCM_Dashboard {
    
    private static $instance = null;
    private $api_url;
    private $license_key;
    private $website_id;
    private $license_status_cache = null;
    private $license_status_cache_key = 'wpccm_license_status';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {    
        $this->api_url =  WPCCM_DASHBOARD_API_URL;
        $this->license_key = get_option('wpccm_license_key', '');
        $this->website_id = 1;
        
        // הוספת hooks
        add_action('wp_ajax_wpccm_sync_with_dashboard', array($this, 'sync_with_dashboard'));
        add_action('wp_ajax_wpccm_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_wpccm_test_connection_custom', array($this, 'test_connection_custom'));
        add_action('wp_ajax_wpccm_sync_consent_data', array($this, 'sync_consent_data'));
        add_action('wp_ajax_wpccm_get_dashboard_stats', array($this, 'get_dashboard_stats'));
        
        // הוספת hook לשמירת העדפות
        add_action('wpccm_consent_saved', array($this, 'send_consent_to_dashboard'), 10, 2);
    }
    
    /**
     * בדיקת חיבור לדשבורד
     */
    public function test_connection() {
        check_ajax_referer('wpccm_admin_nonce', 'nonce');
        
        if (empty($this->license_key)) {
            wp_send_json_error('חסרים פרטי רישיון');
        }
        
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->api_url . '/plugin/validate-license', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'license_key' => $this->license_key,
                'domain' => $domain
            )),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('שגיאה בחיבור: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['valid']) && $data['valid']) {
            $message = isset($data['message']) ? $data['message'] : 'חיבור מוצלח לדשבורד';
            wp_send_json_success($message);
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'שגיאה בחיבור - קוד: ' . $code;
            wp_send_json_error($error_message);
        }
    }

    /**
     * בדיקת חיבור שקטה (ללא AJAX response)
     */
    public function test_connection_silent() {
        if (empty($this->license_key)) {
            $result = array(
                'success' => false,
                'error' => 'מפתח רישיון חסר'
            );

            $this->set_cached_license_status($result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }

        $cached = $this->get_cached_license_status();
        if (null !== $cached) {
            return $cached;
        }
        
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($this->api_url . '/plugin/validate-license', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'license_key' => $this->license_key,
                'domain' => $domain
            )),
            'timeout' => 3
        ));
        // echo '<pre>';
        // print_r($response);
        // echo '</pre>';
        // // var_dump($response);
        // die();
        
        if (is_wp_error($response)) {
            $result = array(
                'success' => false,
                'error' => 'שגיאת תקשורת: ' . $response->get_error_message(),
                'code' => 0
            );

            $this->set_cached_license_status($result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['valid']) && $data['valid']) {
            // בדיקה נוספת של סטטוס האתר
            if (isset($data['website']) && isset($data['website']['status'])) {
                $website_status = $data['website']['status'];
                if ($website_status !== 'active') {
                    $error_message = 'האתר מוגדר כלא פעיל בדשבורד המרכזי';
                    
                    // הודעות ספציפיות לפי סטטוס
                    switch ($website_status) {
                        case 'inactive':
                            $error_message = 'האתר מוגדר כלא פעיל בדשבורד המרכזי - יש להפעיל את האתר';
                            break;
                        case 'suspended':
                            $error_message = 'האתר הושעה בדשבורד המרכזי - צור קשר עם התמיכה';
                            break;
                        case 'pending':
                            $error_message = 'האתר ממתין לאישור בדשבורד המרכזי';
                            break;
                        default:
                            $error_message = 'סטטוס האתר בדשבורד: ' . $website_status;
                    }
                    
                    $result = array(
                        'success' => false,
                        'error' => $error_message,
                        'code' => $code,
                        'website_status' => $website_status
                    );

                    $this->set_cached_license_status($result, 10 * MINUTE_IN_SECONDS);
                    return $result;
                }
            }
            
            $result = array(
                'success' => true,
                'message' => 'רישיון תקף'
            );

            $this->set_cached_license_status($result, 30 * MINUTE_IN_SECONDS);
            return $result;
        }
        
        // טיפול בהודעות שגיאה ספציפיות מהשרת
        $error_message = 'רישיון לא תקף';
        if (isset($data['message'])) {
            $error_message = $data['message'];
        } elseif (isset($data['error'])) {
            $error_message = $data['error'];
        } elseif ($code !== 200) {
            $error_message = 'שגיאת שרת: קוד ' . $code;
        }
        
        $result = array(
            'success' => false,
            'error' => $error_message,
            'code' => $code
        );

        $this->set_cached_license_status($result, 10 * MINUTE_IN_SECONDS);
        return $result;
    }

    /**
     * Cache helper for license validation to avoid blocking every request.
     */
    private function get_cached_license_status() {
        if (is_array($this->license_status_cache)) {
            return $this->license_status_cache;
        }

        $cached = get_transient($this->license_status_cache_key);
        if (is_array($cached) && isset($cached['license_key'], $cached['result']) && $cached['license_key'] === $this->license_key) {
            $this->license_status_cache = $cached['result'];
            return $this->license_status_cache;
        }

        return null;
    }

    private function set_cached_license_status(array $result, $ttl) {
        $this->license_status_cache = $result;
        set_transient($this->license_status_cache_key, array(
            'license_key' => $this->license_key,
            'result' => $result
        ), $ttl);
    }

    public function clear_cached_license_status() {
        $this->license_status_cache = null;
        delete_transient($this->license_status_cache_key);
    }

    /**
     * בדיקת חיבור עם נתונים מותאמים אישית (מהשדות הנוכחיים)
     */
    public function test_connection_custom() {
        
        check_ajax_referer('wpccm_admin_nonce', 'nonce');
        
        $api_url = sanitize_text_field($_POST['api_url'] ?? '');
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        
        if (empty($api_url) || empty($license_key)) {
            wp_send_json_error('חסרים פרטי רישיון');
        }
        
        $domain = parse_url(get_site_url(), PHP_URL_HOST);
        
        $response = wp_remote_post($api_url . '/plugin/validate-license', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'license_key' => $license_key,
                'domain' => $domain
            )),
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('שגיאה בחיבור: ' . $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($code === 200 && isset($data['valid']) && $data['valid']) {
            $message = isset($data['message']) ? $data['message'] : 'חיבור מוצלח לדשבורד';
            wp_send_json_success($message);
        } else {
            $error_message = isset($data['message']) ? $data['message'] : 'שגיאה בחיבור - קוד: ' . $code;
            wp_send_json_error($error_message);
        }
    }
    
    /**
     * סנכרון הגדרות עם הדשבורד
     */
    public function sync_with_dashboard() {
        check_ajax_referer('wpccm_admin_nonce', 'nonce');
        
        if (empty($this->website_id) || empty($this->license_key)) {
            wp_send_json_error('חסרים פרטי רישיון');
        }
        
        // קבלת הגדרות מהדשבורד
        $response = wp_remote_get($this->api_url . '/plugin/websites/' . $this->website_id . '/cookies?license_key=' . urlencode($this->license_key), array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('שגיאה בחיבור לדשבורד');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['cookies'])) {
            // עדכון הגדרות מקומיות
            $this->update_local_settings($data['cookies']);
            wp_send_json_success('הגדרות סונכרנו בהצלחה');
        } else {
            wp_send_json_error('שגיאה בקבלת הגדרות');
        }
    }
    
    /**
     * שליחת נתוני הסכמה לדשבורד
     */
    public function send_consent_to_dashboard($consent_data, $user_id = 0) {
        if (empty($this->website_id) || empty($this->license_key)) {
            return false;
        }
        
        $data = array(
            'website_id' => $this->website_id,
            'license_key' => $this->license_key,
            'consent_data' => $consent_data,
            'user_id' => $user_id,
            'domain' => get_site_url(),
            'timestamp' => current_time('mysql'),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id() ?: uniqid()
        );
        
        $response = wp_remote_post($this->api_url . '/plugin/consent', array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($data),
            'timeout' => 30
        ));
        
        return !is_wp_error($response);
    }
    
    /**
     * סנכרון נתוני הסכמה
     */
    public function sync_consent_data() {
        check_ajax_referer('wpccm_admin_nonce', 'nonce');
        
        if (empty($this->website_id) || empty($this->license_key)) {
            wp_send_json_error('חסרים פרטי רישיון');
        }
        
        // קבלת נתוני הסכמה מהדשבורד
        $response = wp_remote_get($this->api_url . '/websites/' . $this->website_id . '/connections', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->license_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('שגיאה בחיבור לדשבורד');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['connections'])) {
            wp_send_json_success($data['connections']);
        } else {
            wp_send_json_error('שגיאה בקבלת נתונים');
        }
    }
    
    /**
     * קבלת סטטיסטיקות מהדשבורד
     */
    public function get_dashboard_stats() {
        check_ajax_referer('wpccm_admin_nonce', 'nonce');
        
        if (empty($this->website_id) || empty($this->license_key)) {
            wp_send_json_error('חסרים פרטי רישיון');
        }
        
        $response = wp_remote_get($this->api_url . '/plugin/websites/' . $this->website_id . '/stats?license_key=' . urlencode($this->license_key), array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('שגיאה בחיבור לדשבורד');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['data'])) {
            wp_send_json_success($data['data']);
        } else {
            wp_send_json_error('שגיאה בקבלת סטטיסטיקות');
        }
    }
    
    /**
     * עדכון הגדרות מקומיות
     */
    private function update_local_settings($dashboard_cookies) {
        $current_options = WP_CCM_Consent::get_options();
        
        // עדכון רשימת עוגיות למחיקה
        $purge_cookies = array();
        foreach ($dashboard_cookies as $cookie) {
            if (isset($cookie['category']) && $cookie['category'] !== 'necessary') {
                $purge_cookies[] = $cookie['name'];
            }
        }
        
        $current_options['purge']['cookies'] = array_merge(
            $current_options['purge']['cookies'],
            $purge_cookies
        );
        
        update_option('wpccm_options', $current_options);
    }
    
    /**
     * קבלת כתובת IP של המשתמש
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * קבלת הגדרות חיבור
     */
    public function get_connection_settings() {
        return array(
            'api_url' => $this->api_url,
            'license_key' => $this->license_key,
            'website_id' => $this->website_id,
            'is_connected' => !empty($this->website_id) && !empty($this->license_key)
        );
    }
}

// אתחול המחלקה
WP_CCM_Dashboard::get_instance();

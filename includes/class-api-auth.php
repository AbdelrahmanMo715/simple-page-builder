<?php
class SPB_Api_Auth {
    
    private static $instance = null;
    private $current_api_key = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Hook into REST API authentication
        add_filter('determine_current_user', array($this, 'authenticate_api_request'), 20);
        add_filter('rest_authentication_errors', array($this, 'check_authentication_error'), 20);
    }
    
    /**
     * Main authentication method for REST API
     */
    public function authenticate_api_request($user_id) {
        // Skip if already authenticated or not our endpoint
        if ($user_id || !$this->is_our_endpoint()) {
            return $user_id;
        }
        
        // Get API key from headers
        $api_key = $this->get_api_key_from_request();
        
        if (!$api_key) {
            return $user_id;
        }
        
        // Validate the API key
        $api_key_data = $this->validate_api_key($api_key);
        
        if (!$api_key_data) {
            return $user_id;
        }
        
        // Check if API is globally enabled
        $settings = get_option('spb_settings', array());
        if (isset($settings['api_enabled']) && !$settings['api_enabled']) {
            return $user_id;
        }
        
        // Check rate limiting
        if (!$this->check_rate_limit($api_key_data->id)) {
            return $user_id;
        }
        
        // Store current API key data for later use
        $this->current_api_key = $api_key_data;
        
        // Log successful authentication
        $this->log_auth_success($api_key_data->id);
        
        // Return a fake user ID (we'll use 0 for API requests)
        return 0;
    }
    
    /**
     * Get API key from request headers
     */
    private function get_api_key_from_request() {
        $headers = getallheaders();
        
        // Check various header names
        $possible_headers = array(
            'X-API-Key',
            'X-API-KEY',
            'X-Api-Key',
            'Authorization'
        );
        
        foreach ($possible_headers as $header) {
            if (isset($headers[$header])) {
                $value = $headers[$header];
                
                // Handle Authorization: Bearer format
                if ($header === 'Authorization' && stripos($value, 'Bearer ') === 0) {
                    return trim(substr($value, 7));
                }
                
                return trim($value);
            }
        }
        
        // Also check $_GET for testing
        if (isset($_GET['api_key']) && defined('WP_DEBUG') && WP_DEBUG) {
            return sanitize_text_field($_GET['api_key']);
        }
        
        return false;
    }
    
    /**
     * Validate the API key
     */
    private function validate_api_key($api_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Get all active API keys
        $api_keys = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE status = 'active'"
        );
        
        foreach ($api_keys as $key_data) {
            // Verify the API key using WordPress password verification
            if (wp_check_password($api_key, $key_data->api_key_hash)) {
                // Check if key has expired
                if ($key_data->expires_at && strtotime($key_data->expires_at) < time()) {
                    $this->log_auth_failure($key_data->id, 'Key expired');
                    return false;
                }
                
                // Check permissions (for now, just check if it has create_pages permission)
                $permissions = maybe_unserialize($key_data->permissions);
                if (!is_array($permissions) || !in_array('create_pages', $permissions)) {
                    $this->log_auth_failure($key_data->id, 'Insufficient permissions');
                    return false;
                }
                
                return $key_data;
            }
        }
        
        // Log failed attempt (no matching key)
        $this->log_auth_failure(0, 'Invalid API key');
        return false;
    }
    
    /**
     * Check rate limiting for the API key
     */
    private function check_rate_limit($api_key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Get rate limit settings
        $key_data = $wpdb->get_row($wpdb->prepare(
            "SELECT rate_limit_hourly, last_used FROM $table_name WHERE id = %d",
            $api_key_id
        ));
        
        if (!$key_data) {
            return false;
        }
        
        $rate_limit = $key_data->rate_limit_hourly;
        $settings = get_option('spb_settings', array());
        $global_rate_limit = isset($settings['rate_limit']) ? (int)$settings['rate_limit'] : 100;
        
        // Use the lower of key-specific or global limit
        $effective_limit = min($rate_limit, $global_rate_limit);
        
        // Count requests in the last hour
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        $hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $request_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs 
             WHERE api_key_id = %d AND created_at > %s",
            $api_key_id,
            $hour_ago
        ));
        
        if ($request_count >= $effective_limit) {
            $this->log_auth_failure($api_key_id, 'Rate limit exceeded');
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if this is our plugin's endpoint
     */
    private function is_our_endpoint() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if it's our API endpoint
        if (strpos($request_uri, '/wp-json/pagebuilder/v1/') !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Handle authentication errors
     */
    public function check_authentication_error($result) {
        // If another authentication method succeeded, don't override
        if (!empty($result) || is_wp_error($result)) {
            return $result;
        }
        
        // If this is our endpoint but not authenticated, return error
        if ($this->is_our_endpoint() && !$this->current_api_key) {
            $api_key = $this->get_api_key_from_request();
            
            if (!$api_key) {
                return new WP_Error(
                    'missing_api_key',
                    __('API key is required', 'simple-page-builder'),
                    array('status' => 401)
                );
            }
            
            // Check if we have any auth failure logs for this request
            global $wpdb;
            $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
            
            $last_failure = $wpdb->get_var($wpdb->prepare(
                "SELECT response_body FROM $table_logs 
                 WHERE endpoint = 'auth' AND status_code = 401 
                 ORDER BY created_at DESC LIMIT 1"
            ));
            
            if ($last_failure) {
                $error_data = json_decode($last_failure, true);
                if ($error_data && isset($error_data['message'])) {
                    return new WP_Error(
                        'authentication_failed',
                        $error_data['message'],
                        array('status' => 401)
                    );
                }
            }
            
            return new WP_Error(
                'authentication_failed',
                __('Invalid API key or insufficient permissions', 'simple-page-builder'),
                array('status' => 401)
            );
        }
        
        return $result;
    }
    
    /**
     * Log successful authentication
     */
    private function log_auth_success($api_key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Update last used timestamp and increment request count
        $wpdb->update(
            $table_name,
            array(
                'last_used' => current_time('mysql'),
                'request_count' => $wpdb->get_var($wpdb->prepare(
                    "SELECT request_count FROM $table_name WHERE id = %d",
                    $api_key_id
                )) + 1
            ),
            array('id' => $api_key_id),
            array('%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Log authentication failure
     */
    private function log_auth_failure($api_key_id, $reason) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        
        $wpdb->insert(
            $table_name,
            array(
                'api_key_id' => $api_key_id,
                'endpoint' => 'auth',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                'status_code' => 401,
                'response_body' => json_encode(array(
                    'success' => false,
                    'message' => 'Authentication failed: ' . $reason
                )),
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get current authenticated API key data
     */
    public function get_current_api_key() {
        return $this->current_api_key;
    }
    
    /**
     * Generate a new API key (public/secret pair)
     */
    public static function generate_api_key_pair() {
        $public_key = wp_generate_password(64, false, false);
        $secret_key = wp_generate_password(64, false, false);
        
        return array(
            'public_key' => $public_key,
            'secret_key' => $secret_key,
            'public_key_hash' => wp_hash_password($public_key),
            'secret_key_hash' => wp_hash_password($secret_key)
        );
    }
    
    /**
     * Verify API key signature (for webhooks or advanced auth)
     */
    public static function verify_signature($data, $signature, $secret_key) {
        $expected_signature = hash_hmac('sha256', $data, $secret_key);
        return hash_equals($expected_signature, $signature);
    }
}
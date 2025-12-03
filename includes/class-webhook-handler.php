<?php
class SPB_Webhook_Handler {
    
    private static $instance = null;
    private $settings = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = get_option('spb_settings', array());
        
        // Hook into page creation event
        add_action('spb_trigger_webhook', array($this, 'trigger_webhook'), 10, 3);
        
        // Schedule retry for failed webhooks
        add_action('spb_retry_webhook', array($this, 'process_retry'), 10, 1);
        
        // Cleanup old webhook logs
        add_action('spb_daily_cleanup', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Trigger webhook notification
     */
    public function trigger_webhook($request_id, $api_key, $created_pages) {
        // Check if webhooks are enabled
        if (empty($this->settings['webhook_enabled']) || empty($this->settings['webhook_url'])) {
            return;
        }
        
        // Prepare payload
        $payload = $this->prepare_payload($request_id, $api_key, $created_pages);
        
        // Generate signature if secret exists
        $signature = '';
        if (!empty($this->settings['webhook_secret'])) {
            $signature = hash_hmac('sha256', json_encode($payload), $this->settings['webhook_secret']);
        }
        
        // Send webhook
        $this->send_webhook($request_id, $payload, $signature);
    }
    
    /**
     * Prepare webhook payload
     */
    private function prepare_payload($request_id, $api_key, $created_pages) {
        global $wpdb;
        
        // Get API key name
        $key_name = 'Unknown';
        if ($api_key && isset($api_key->key_name)) {
            $key_name = $api_key->key_name;
        } elseif ($api_key && is_object($api_key) && isset($api_key->id)) {
            $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
            $key_data = $wpdb->get_row($wpdb->prepare(
                "SELECT key_name FROM $table_name WHERE id = %d",
                $api_key->id
            ));
            if ($key_data) {
                $key_name = $key_data->key_name;
            }
        }
        
        // Prepare pages array
        $pages_data = array();
        foreach ($created_pages as $page) {
            if (isset($page['id'])) {
                $pages_data[] = array(
                    'id' => intval($page['id']),
                    'title' => isset($page['title']) ? $page['title'] : get_the_title($page['id']),
                    'url' => isset($page['url']) ? $page['url'] : get_permalink($page['id']),
                    'edit_url' => isset($page['edit_url']) ? $page['edit_url'] : get_edit_post_link($page['id'], 'url'),
                    'status' => isset($page['status']) ? $page['status'] : get_post_status($page['id'])
                );
            }
        }
        
        return array(
            'event' => 'pages_created',
            'timestamp' => current_time('c'),
            'request_id' => $request_id,
            'api_key_name' => $key_name,
            'api_key_id' => $api_key ? $api_key->id : null,
            'total_pages' => count($pages_data),
            'pages' => $pages_data,
            'site_url' => home_url(),
            'site_name' => get_bloginfo('name')
        );
    }
    
    /**
     * Send webhook request
     */
    private function send_webhook($request_id, $payload, $signature, $retry_count = 0) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        
        // Prepare headers
        $headers = array(
            'Content-Type' => 'application/json',
            'User-Agent' => 'SimplePageBuilder/' . SPB_VERSION
        );
        
        if (!empty($signature)) {
            $headers['X-Webhook-Signature'] = $signature;
        }
        
        // Add request ID header
        $headers['X-Request-ID'] = $request_id;
        
        // Send request
        $response = wp_remote_post($this->settings['webhook_url'], array(
            'method' => 'POST',
            'timeout' => 10,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => $headers,
            'body' => json_encode($payload),
            'data_format' => 'body'
        ));
        
        // Log the attempt
        $log_data = array(
            'request_id' => $request_id,
            'webhook_url' => $this->settings['webhook_url'],
            'payload' => json_encode($payload),
            'signature' => $signature,
            'retry_count' => $retry_count,
            'created_at' => current_time('mysql')
        );
        
        if (is_wp_error($response)) {
            // Request failed
            $log_data['error_message'] = $response->get_error_message();
            $log_data['status_code'] = 0;
            
            $this->log_webhook_attempt($log_data);
            
            // Schedule retry if under max retries
            if ($retry_count < 2) {
                $this->schedule_retry($request_id, $payload, $signature, $retry_count + 1);
            }
            
            return false;
        }
        
        // Request succeeded (at least at network level)
        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $log_data['status_code'] = $status_code;
        $log_data['response_body'] = $response_body;
        $log_data['delivered_at'] = current_time('mysql');
        
        $this->log_webhook_attempt($log_data);
        
        // Check if successful (2xx status)
        if ($status_code >= 200 && $status_code < 300) {
            return true;
        }
        
        // Non-2xx status, schedule retry
        $log_data['error_message'] = sprintf(__('HTTP %d received', 'simple-page-builder'), $status_code);
        $this->log_webhook_attempt($log_data);
        
        if ($retry_count < 2) {
            $this->schedule_retry($request_id, $payload, $signature, $retry_count + 1);
        }
        
        return false;
    }
    
    /**
     * Schedule a retry for failed webhook
     */
    private function schedule_retry($request_id, $payload, $signature, $retry_count) {
        // Calculate delay (exponential backoff: 5, 30, 90 seconds)
        $delays = array(5, 30, 90);
        $delay = isset($delays[$retry_count]) ? $delays[$retry_count] : 90;
        
        // Schedule single event
        wp_schedule_single_event(time() + $delay, 'spb_retry_webhook', array(
            array(
                'request_id' => $request_id,
                'payload' => $payload,
                'signature' => $signature,
                'retry_count' => $retry_count
            )
        ));
    }
    
    /**
     * Process retry for failed webhook
     */
    public function process_retry($data) {
        if (!is_array($data) || empty($data['request_id']) || empty($data['payload'])) {
            return;
        }
        
        $request_id = $data['request_id'];
        $payload = $data['payload'];
        $signature = isset($data['signature']) ? $data['signature'] : '';
        $retry_count = isset($data['retry_count']) ? $data['retry_count'] : 0;
        
        // Send the webhook again
        $this->send_webhook($request_id, $payload, $signature, $retry_count);
    }
    
    /**
     * Log webhook attempt
     */
    private function log_webhook_attempt($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        
        $wpdb->insert($table_name, $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get webhook delivery statistics
     */
    public function get_delivery_stats($period = '30days') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        
        // Calculate date range
        $date_conditions = array();
        
        switch ($period) {
            case 'today':
                $date_conditions['start'] = date('Y-m-d 00:00:00');
                break;
            case 'yesterday':
                $date_conditions['start'] = date('Y-m-d 00:00:00', strtotime('-1 day'));
                $date_conditions['end'] = date('Y-m-d 00:00:00');
                break;
            case '7days':
                $date_conditions['start'] = date('Y-m-d 00:00:00', strtotime('-7 days'));
                break;
            case '30days':
                $date_conditions['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
                break;
            default:
                $date_conditions['start'] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        }
        
        // Build WHERE clause
        $where = array();
        $values = array();
        
        if (isset($date_conditions['start'])) {
            $where[] = 'created_at >= %s';
            $values[] = $date_conditions['start'];
        }
        
        if (isset($date_conditions['end'])) {
            $where[] = 'created_at < %s';
            $values[] = $date_conditions['end'];
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Get statistics
        $query = "SELECT 
            COUNT(*) as total_attempts,
            SUM(CASE WHEN status_code >= 200 AND status_code < 300 THEN 1 ELSE 0 END) as successful,
            SUM(CASE WHEN status_code = 0 OR status_code >= 400 THEN 1 ELSE 0 END) as failed,
            AVG(CASE WHEN status_code > 0 THEN status_code ELSE NULL END) as avg_status_code,
            MAX(retry_count) as max_retries
            FROM $table_name
            $where_clause";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $stats = $wpdb->get_row($query);
        
        // Calculate success rate
        $success_rate = 0;
        if ($stats->total_attempts > 0) {
            $success_rate = round(($stats->successful / $stats->total_attempts) * 100, 1);
        }
        
        return array(
            'total_attempts' => intval($stats->total_attempts),
            'successful' => intval($stats->successful),
            'failed' => intval($stats->failed),
            'success_rate' => $success_rate,
            'avg_status_code' => round(floatval($stats->avg_status_code), 0),
            'max_retries' => intval($stats->max_retries)
        );
    }
    
    /**
     * Get recent webhook logs
     */
    public function get_recent_logs($limit = 20) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ));
        
        // Format logs
        foreach ($logs as &$log) {
            $log->status_class = $this->get_status_class($log->status_code);
            $log->status_text = $this->get_status_text($log->status_code, $log->error_message);
            $log->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at));
            
            if ($log->delivered_at) {
                $log->delivered_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->delivered_at));
            }
            
            // Truncate long payloads for display
            if (strlen($log->payload) > 200) {
                $log->payload_preview = substr($log->payload, 0, 200) . '...';
            } else {
                $log->payload_preview = $log->payload;
            }
        }
        
        return $logs;
    }
    
    /**
     * Get status class for CSS
     */
    private function get_status_class($status_code) {
        if ($status_code >= 200 && $status_code < 300) {
            return 'success';
        } elseif ($status_code >= 400 && $status_code < 500) {
            return 'warning';
        } elseif ($status_code >= 500) {
            return 'danger';
        } else {
            return 'error';
        }
    }
    
    /**
     * Get status text
     */
    private function get_status_text($status_code, $error_message = '') {
        if ($status_code === 0) {
            return $error_message ?: __('Network error', 'simple-page-builder');
        } elseif ($status_code >= 200 && $status_code < 300) {
            return sprintf(__('Success (%d)', 'simple-page-builder'), $status_code);
        } else {
            return sprintf(__('Failed (%d)', 'simple-page-builder'), $status_code);
        }
    }
    
    /**
     * Cleanup old webhook logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $settings = get_option('spb_settings', array());
        $retention_days = isset($settings['log_retention']) ? intval($settings['log_retention']) : 90;
        
        if ($retention_days <= 0) {
            return; // Keep forever
        }
        
        $table_name = $wpdb->prefix . SPB_TABLE_WEBHOOK_LOGS;
        $date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $date
        ));
    }
    
    /**
     * Verify webhook signature (for external use)
     */
    public static function verify_signature($payload, $signature, $secret) {
        if (empty($secret)) {
            return true; // No secret configured, accept all
        }
        
        $expected_signature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Get webhook secret
     */
    public function get_webhook_secret() {
        return isset($this->settings['webhook_secret']) ? $this->settings['webhook_secret'] : '';
    }
    
    /**
     * Test webhook delivery
     */
    public function test_webhook() {
        if (empty($this->settings['webhook_url'])) {
            return new WP_Error('no_url', __('Webhook URL is not set', 'simple-page-builder'));
        }
        
        $request_id = 'test_' . wp_generate_password(8, false);
        $api_key = (object) array(
            'id' => 0,
            'key_name' => 'Test'
        );
        
        $created_pages = array(
            array(
                'id' => 999,
                'title' => 'Test Page 1',
                'url' => home_url('/test-page-1')
            ),
            array(
                'id' => 1000,
                'title' => 'Test Page 2',
                'url' => home_url('/test-page-2')
            )
        );
        
        return $this->trigger_webhook($request_id, $api_key, $created_pages);
    }
}
<?php
class SPB_Api_Keys_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Handle admin actions via AJAX or direct POST
        // add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('wp_ajax_spb_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_spb_revoke_api_key', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_spb_delete_api_key', array($this, 'ajax_delete_api_key'));
        add_action('wp_ajax_spb_regenerate_secret', array($this, 'ajax_regenerate_secret'));
        add_action('spb_daily_cleanup', array($this, 'cleanup_expired_keys'));
    }
    
    /**
     * Generate a new API key
     */
    public function generate_api_key($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Validate data
        if (empty($data['key_name'])) {
            return new WP_Error('missing_name', __('Key name is required', 'simple-page-builder'));
        }
        
        // Generate key pair
        $key_pair = SPB_Api_Auth::generate_api_key_pair();
        
        // Prepare expiration date
        $expires_at = null;
        if (!empty($data['expiration_days']) && is_numeric($data['expiration_days'])) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . intval($data['expiration_days']) . ' days'));
        } elseif (!empty($data['expires_at']) && strtotime($data['expires_at'])) {
            $expires_at = date('Y-m-d H:i:s', strtotime($data['expires_at']));
        }
        
        // Prepare permissions
        $permissions = array('create_pages');
        if (!empty($data['permissions']) && is_array($data['permissions'])) {
            $permissions = array_merge($permissions, $data['permissions']);
        }
        
        // Insert into database
        $result = $wpdb->insert(
            $table_name,
            array(
                'key_name' => sanitize_text_field($data['key_name']),
                'api_key_hash' => $key_pair['public_key_hash'],
                'secret_key_hash' => $key_pair['secret_key_hash'],
                'status' => 'active',
                'permissions' => serialize($permissions),
                'expires_at' => $expires_at,
                'user_id' => get_current_user_id(),
                'rate_limit_hourly' => !empty($data['rate_limit']) ? intval($data['rate_limit']) : 100,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to create API key', 'simple-page-builder'));
        }
        
        $key_id = $wpdb->insert_id;
        
        // Log the generation
        $this->log_key_action($key_id, 'generated', get_current_user_id());
        
        return array(
            'id' => $key_id,
            'key_name' => $data['key_name'],
            'api_key' => $key_pair['public_key'],
            'secret_key' => $key_pair['secret_key'],
            'expires_at' => $expires_at,
            'permissions' => $permissions
        );
    }
    
    /**
     * Revoke an API key
     */
    public function revoke_api_key($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Check if key exists
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $key_id
        ));
        
        if (!$key) {
            return new WP_Error('not_found', __('API key not found', 'simple-page-builder'));
        }
        
        // Update status to revoked
        $result = $wpdb->update(
            $table_name,
            array('status' => 'revoked'),
            array('id' => $key_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to revoke API key', 'simple-page-builder'));
        }
        
        // Log the revocation
        $this->log_key_action($key_id, 'revoked', get_current_user_id());
        
        return true;
    }
    
    /**
     * Delete an API key
     */
    public function delete_api_key($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Check if key exists
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $key_id
        ));
        
        if (!$key) {
            return new WP_Error('not_found', __('API key not found', 'simple-page-builder'));
        }
        
        // Delete the key
        $result = $wpdb->delete(
            $table_name,
            array('id' => $key_id),
            array('%d')
        );
        
        if (!$result) {
            return new WP_Error('db_error', __('Failed to delete API key', 'simple-page-builder'));
        }
        
        // Log the deletion
        $this->log_key_action($key_id, 'deleted', get_current_user_id());
        
        return true;
    }
    
    /**
     * Regenerate secret key
     */
    public function regenerate_secret_key($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Check if key exists and is active
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND status = 'active'",
            $key_id
        ));
        
        if (!$key) {
            return new WP_Error('not_found', __('Active API key not found', 'simple-page-builder'));
        }
        
        // Generate new secret key
        $new_secret = wp_generate_password(64, false, false);
        $new_secret_hash = wp_hash_password($new_secret);
        
        // Update database
        $result = $wpdb->update(
            $table_name,
            array('secret_key_hash' => $new_secret_hash),
            array('id' => $key_id),
            array('%s'),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to regenerate secret key', 'simple-page-builder'));
        }
        
        // Log the regeneration
        $this->log_key_action($key_id, 'secret_regenerated', get_current_user_id());
        
        return array(
            'secret_key' => $new_secret,
            'key_name' => $key->key_name
        );
    }
    
    /**
     * Get all API keys with optional filters
     */
    public function get_api_keys($filters = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        $where = array('1=1');
        $values = array();
        
        // Apply filters
        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $values[] = intval($filters['user_id']);
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(key_name LIKE %s OR id = %d)';
            $values[] = '%' . $wpdb->esc_like($filters['search']) . '%';
            $values[] = intval($filters['search']);
        }
        
        // Build query
        $where_clause = implode(' AND ', $where);
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY created_at DESC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $keys = $wpdb->get_results($query);
        
        // Format the results
        foreach ($keys as &$key) {
            $key = $this->format_key_data($key);
        }
        
        return $keys;
    }
    
    /**
     * Get single API key by ID
     */
    public function get_api_key($key_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        $key = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $key_id
        ));
        
        if (!$key) {
            return null;
        }
        
        return $this->format_key_data($key);
    }
    
    /**
     * Format key data for display
     */
    private function format_key_data($key) {
        // Never show the actual hash
        $key->api_key_preview = substr($key->api_key_hash, 0, 8) . '***';
        
        // Format dates
        $key->created_at_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key->created_at));
        
        if ($key->last_used) {
            $key->last_used_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($key->last_used));
            $key->last_used_human = human_time_diff(strtotime($key->last_used)) . ' ' . __('ago', 'simple-page-builder');
        } else {
            $key->last_used_formatted = __('Never', 'simple-page-builder');
            $key->last_used_human = __('Never', 'simple-page-builder');
        }
        
        if ($key->expires_at) {
            $key->expires_at_formatted = date_i18n(get_option('date_format'), strtotime($key->expires_at));
            $key->expires_in = human_time_diff(time(), strtotime($key->expires_at));
            
            // Check if expired
            if (strtotime($key->expires_at) < time()) {
                $key->is_expired = true;
            }
        } else {
            $key->expires_at_formatted = __('Never', 'simple-page-builder');
            $key->expires_in = __('Never', 'simple-page-builder');
        }
        
        // Unserialize permissions
        $key->permissions_array = maybe_unserialize($key->permissions);
        if (!is_array($key->permissions_array)) {
            $key->permissions_array = array('create_pages');
        }
        
        // Get user info
        if ($key->user_id) {
            $user = get_user_by('id', $key->user_id);
            $key->user_name = $user ? $user->display_name : __('Unknown', 'simple-page-builder');
            $key->user_email = $user ? $user->user_email : '';
        }
        
        return $key;
    }
    
    /**
     * Get key statistics
     */
    public function get_key_statistics($key_id) {
        global $wpdb;
        
        $table_logs = $wpdb->prefix . SPB_TABLE_ACTIVITY_LOGS;
        
        $stats = array(
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_pages_created' => 0,
            'requests_today' => 0,
            'requests_this_week' => 0,
            'requests_this_month' => 0,
            'avg_response_time' => 0
        );
        
        // Total requests
        $stats['total_requests'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d",
            $key_id
        ));
        
        // Successful requests (2xx status codes)
        $stats['successful_requests'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d AND status_code BETWEEN 200 AND 299",
            $key_id
        ));
        
        // Failed requests (4xx, 5xx)
        $stats['failed_requests'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d AND status_code >= 400",
            $key_id
        ));
        
        // Total pages created
        $stats['total_pages_created'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pages_created) FROM $table_logs WHERE api_key_id = %d",
            $key_id
        )) ?: 0;
        
        // Today's requests
        $today = date('Y-m-d 00:00:00');
        $stats['requests_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d AND created_at >= %s",
            $key_id,
            $today
        ));
        
        // This week's requests
        $week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $stats['requests_this_week'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d AND created_at >= %s",
            $key_id,
            $week_start
        ));
        
        // This month's requests
        $month_start = date('Y-m-01 00:00:00');
        $stats['requests_this_month'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_logs WHERE api_key_id = %d AND created_at >= %s",
            $key_id,
            $month_start
        ));
        
        // Average response time
        $stats['avg_response_time'] = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(response_time) FROM $table_logs WHERE api_key_id = %d AND response_time > 0",
            $key_id
        )) ?: 0;
        
        return $stats;
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        // Check if we're on our admin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'simple-page-builder') {
            return;
        }
        
        // Check nonce and capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle form submissions
        if (isset($_POST['spb_action']) && isset($_POST['_wpnonce'])) {
            $action = sanitize_text_field($_POST['spb_action']);
            $nonce = $_POST['_wpnonce'];
            
            if (!wp_verify_nonce($nonce, 'spb_admin_action')) {
                wp_die(__('Security check failed', 'simple-page-builder'));
            }
            
            switch ($action) {
                case 'generate_key':
                    $this->handle_generate_key();
                    break;
                    
                case 'revoke_key':
                    $this->handle_revoke_key();
                    break;
                    
                case 'delete_key':
                    $this->handle_delete_key();
                    break;
            }
        }
    }
    
/**
 * Handle key generation via form
 */
private function handle_generate_key() {
    // Get form data
    $data = array(
        'key_name' => sanitize_text_field($_POST['key_name'] ?? ''),
        'expiration_days' => isset($_POST['expiration_days']) ? intval($_POST['expiration_days']) : 0,
        'rate_limit' => isset($_POST['rate_limit']) ? intval($_POST['rate_limit']) : 100
    );
    
    // Validate required fields
    if (empty($data['key_name'])) {
        // Store error in transient
        set_transient('spb_admin_error_' . get_current_user_id(), __('Key name is required', 'simple-page-builder'), 30);
        
        // Redirect back with error
        wp_safe_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'api-keys'
        ), admin_url('tools.php')));
        exit;
    }
    
    // Generate the key
    $result = $this->generate_api_key($data);
    
    if (is_wp_error($result)) {
        // Store error in transient
        set_transient('spb_admin_error_' . get_current_user_id(), $result->get_error_message(), 30);
        
        // Redirect back with error
        wp_safe_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'api-keys'
        ), admin_url('tools.php')));
        exit;
    }
    
    // Store the generated key in a transient to display on next page load
    set_transient('spb_generated_key_' . get_current_user_id(), $result, 300); // 5 minutes
    
    // Redirect back to admin page with success flag
    wp_safe_redirect(add_query_arg(array(
        'page' => 'simple-page-builder',
        'tab' => 'api-keys',
        'generated' => '1'
    ), admin_url('tools.php')));
    exit;
}
    
    /**
     * Handle key revocation
     */
    private function handle_revoke_key() {
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_die(__('Invalid key ID', 'simple-page-builder'));
        }
        
        $result = $this->revoke_api_key($key_id);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Redirect back
        wp_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'api-keys',
            'revoked' => '1'
        ), admin_url('tools.php')));
        exit;
    }
    
    /**
     * Handle key deletion
     */
    private function handle_delete_key() {
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_die(__('Invalid key ID', 'simple-page-builder'));
        }
        
        $result = $this->delete_api_key($key_id);
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        // Redirect back
        wp_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'api-keys',
            'deleted' => '1'
        ), admin_url('tools.php')));
        exit;
    }
    
    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-page-builder'));
        }
        
        $data = array(
            'key_name' => sanitize_text_field($_POST['key_name'] ?? ''),
            'expiration_days' => isset($_POST['expiration_days']) ? intval($_POST['expiration_days']) : 0,
            'rate_limit' => isset($_POST['rate_limit']) ? intval($_POST['rate_limit']) : 100
        );
        
        $result = $this->generate_api_key($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-page-builder'));
        }
        
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_send_json_error(array('message' => __('Invalid key ID', 'simple-page-builder')));
        }
        
        $result = $this->revoke_api_key($key_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('API key revoked successfully', 'simple-page-builder')));
    }
    
    /**
     * AJAX: Delete API key
     */
    public function ajax_delete_api_key() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-page-builder'));
        }
        
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_send_json_error(array('message' => __('Invalid key ID', 'simple-page-builder')));
        }
        
        $result = $this->delete_api_key($key_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success(array('message' => __('API key deleted successfully', 'simple-page-builder')));
    }
    
    /**
     * AJAX: Regenerate secret key
     */
    public function ajax_regenerate_secret() {
        check_ajax_referer('spb_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'simple-page-builder'));
        }
        
        $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
        
        if (!$key_id) {
            wp_send_json_error(array('message' => __('Invalid key ID', 'simple-page-builder')));
        }
        
        $result = $this->regenerate_secret_key($key_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Cleanup expired keys daily
     */
    public function cleanup_expired_keys() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
        
        // Auto-revoke expired keys
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name SET status = 'revoked' 
                 WHERE status = 'active' 
                 AND expires_at IS NOT NULL 
                 AND expires_at < %s",
                current_time('mysql')
            )
        );
        
        // Delete keys revoked more than 30 days ago
        $delete_date = date('Y-m-d H:i:s', strtotime('-30 days'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name 
                 WHERE status = 'revoked' 
                 AND last_used < %s",
                $delete_date
            )
        );
    }
    
    /**
     * Log key actions
     */
    private function log_key_action($key_id, $action, $user_id) {
        // We could log this to a separate table or WordPress activity log
        do_action('spb_key_action_logged', $key_id, $action, $user_id);
    }
}
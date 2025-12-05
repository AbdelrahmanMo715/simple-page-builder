<?php
/**
 * Plugin Name: Simple Page Builder
 * Plugin URI: https://example.com/simple-page-builder
 * Description: Create bulk pages via secure REST API with webhook notifications
 * Version: 1.0.0
 * Author: Abdelrahman Mohamed
 * License: GPL v2 or later
 * Text Domain: simple-page-builder
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SPB_VERSION', '1.0.0');
define('SPB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SPB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPB_TABLE_API_KEYS', 'spb_api_keys');
define('SPB_TABLE_ACTIVITY_LOGS', 'spb_activity_logs');
define('SPB_TABLE_WEBHOOK_LOGS', 'spb_webhook_logs');

// Autoloader function
spl_autoload_register(function ($class) {
    $prefix = 'SPB_';
    $base_dir = SPB_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('_', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// =====================================================================
// DIRECT AJAX REGISTRATION - WORKS EVEN IF OTHER CLASSES FAIL
// =====================================================================
add_action('wp_ajax_spb_revoke_api_key', 'spb_direct_revoke_api_key');
add_action('wp_ajax_spb_delete_api_key', 'spb_direct_delete_api_key');
add_action('wp_ajax_spb_test_direct', 'spb_direct_test');
add_action('wp_ajax_spb_generate_api_key', 'spb_direct_generate_api_key');
add_action('wp_ajax_spb_regenerate_secret', 'spb_direct_regenerate_secret');

function spb_direct_test() {
    error_log('Direct test AJAX called');
    wp_send_json_success('Direct AJAX is working!');
}

function spb_direct_generate_api_key() {
    // Handle generate via AJAX if needed
    wp_send_json_error('Generate via AJAX not implemented');
}
// In simple-page-builder.php, replace spb_direct_regenerate_secret() with:
function spb_direct_regenerate_secret() {
    error_log('Direct regenerate secret AJAX called');
    
    // Basic validation
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spb_admin_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if (!$key_id) {
        wp_send_json_error('Invalid key ID');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS;
    
    // First, get the existing key details
    $existing_key = $wpdb->get_row($wpdb->prepare(
        "SELECT key_name, expires_at, rate_limit_hourly, permissions, user_id 
         FROM $table_name WHERE id = %d",
        $key_id
    ));
    
    if (!$existing_key) {
        wp_send_json_error('Key not found');
    }
    
    // Use your SPB_Api_Auth class to generate a new key pair
    require_once SPB_PLUGIN_DIR . 'includes/class-api-auth.php';
    
    // Generate new API key pair
    $key_pair = SPB_Api_Auth::generate_api_key_pair();
    
    // Create new encryption key instance to encrypt the new API key
    $keys_manager = SPB_Api_Keys_Manager::get_instance();
    
    // Use reflection to access private method, or better: add a public method
    $encrypted_key = $keys_manager->encrypt_api_key($key_pair['public_key']);
    
    // Update ALL key data in database
    $result = $wpdb->update(
        $table_name,
        array(
            'api_key_hash' => $key_pair['public_key_hash'],
            'api_key_encrypted' => $encrypted_key,
            'secret_key_hash' => $key_pair['secret_key_hash'],
            'last_used' => NULL, // Reset last used
            'request_count' => 0, // Reset request count
            'updated_at' => current_time('mysql')
        ),
        array('id' => $key_id),
        array('%s', '%s', '%s', '%s', '%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'API Key and Secret regenerated successfully',
            'api_key' => $key_pair['public_key'],
            'secret_key' => $key_pair['secret_key'],
            'key_id' => $key_id,
            'key_name' => $existing_key->key_name
        ));
    } else {
        wp_send_json_error('Failed to regenerate keys: ' . $wpdb->last_error);
    }
}

function spb_direct_revoke_api_key() {
    error_log('Direct revoke AJAX called');
    
    // Basic validation
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spb_admin_nonce')) {
        wp_die('0'); // Return 0 for security failure
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('0'); // Return 0 for permission failure
    }
    
    $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if (!$key_id) {
        wp_die('0'); // Return 0 for invalid input
    }
    
    // Simple database update
    global $wpdb;
    $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS; // FIXED: Use constant
    
    $result = $wpdb->update(
        $table_name,
        array('status' => 'revoked'),
        array('id' => $key_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success(array(
            'message' => 'API key revoked successfully',
            'key_id' => $key_id,
            'redirect_url' => add_query_arg(array(
                'page' => 'simple-page-builder',
                'tab' => 'api-keys',
                'revoked' => '1'
            ), admin_url('tools.php'))
        ));
    } else {
        wp_die('0'); // Return 0 for database error
    }
}

function spb_direct_delete_api_key() {
    error_log('Direct delete AJAX called');
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spb_admin_nonce')) {
        wp_die('0');
    }
    
    if (!current_user_can('manage_options')) {
        wp_die('0');
    }
    
    $key_id = isset($_POST['key_id']) ? intval($_POST['key_id']) : 0;
    
    if (!$key_id) {
        wp_die('0');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . SPB_TABLE_API_KEYS; // FIXED: Use constant
    
    $result = $wpdb->delete(
        $table_name,
        array('id' => $key_id),
        array('%d')
    );
    
    if ($result) {
        wp_send_json_success(array(
            'message' => 'API key deleted successfully',
            'key_id' => $key_id,
            'redirect_url' => add_query_arg(array(
                'page' => 'simple-page-builder',
                'tab' => 'api-keys',
                'deleted' => '1'
            ), admin_url('tools.php'))
        ));
    } else {
        wp_die('0');
    }
}
// =====================================================================

// Main plugin class
class SimplePageBuilder {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Initialize on plugins_loaded
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function activate() {
        require_once SPB_PLUGIN_DIR . 'includes/class-database.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-database-updater.php'; 
        SPB_Database::create_tables();
        SPB_Database_Updater::run_on_activation(); 
        
        // Set default options
        add_option('spb_settings', array(
            'api_enabled' => true,
            'rate_limit' => 100,
            'default_expiration' => 30,
            'webhook_secret' => wp_generate_password(64, false),
            'webhook_url' => '',
            'webhook_enabled' => false
        ));
        
        // Schedule cleanup cron job
        if (!wp_next_scheduled('spb_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'spb_daily_cleanup');
        }
    }
    
    public function deactivate() {
        // Clear cron jobs
        wp_clear_scheduled_hook('spb_daily_cleanup');
    }
    
    public function init() {
        // Load required files
        $this->load_dependencies();
        
        // Initialize components
        $this->init_components();
    }
    
    private function load_dependencies() {
        // Core functionality
        require_once SPB_PLUGIN_DIR . 'includes/class-api-auth.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-api-endpoint.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-webhook-handler.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-api-keys-manager.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once SPB_PLUGIN_DIR . 'includes/class-simple-form-handler.php';
        
        // Admin functionality
        if (is_admin()) {
            require_once SPB_PLUGIN_DIR . 'admin/class-admin-menu.php';
            require_once SPB_PLUGIN_DIR . 'admin/class-api-keys-ui.php';
            require_once SPB_PLUGIN_DIR . 'admin/class-activity-log-ui.php';
            require_once SPB_PLUGIN_DIR . 'admin/class-pages-list-ui.php';
            require_once SPB_PLUGIN_DIR . 'admin/class-settings-ui.php';
            require_once SPB_PLUGIN_DIR . 'admin/class-documentation-ui.php';
        }
        
        // API
        require_once SPB_PLUGIN_DIR . 'api/class-rest-api.php';
    }
    
    private function init_components() {
        // Initialize API authentication
        SPB_Api_Auth::get_instance();
        
        // Initialize REST API
        SPB_Rest_API::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            SPB_Admin_Menu::get_instance();
        }
    }
}

// Initialize plugin
SimplePageBuilder::get_instance();
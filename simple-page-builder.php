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
        SPB_Database::create_tables();
        
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
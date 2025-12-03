<?php
class SPB_Admin_Menu {
    
    private static $instance = null;
    private $page_hook = '';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Handle form submissions
        add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'tools.php', // Parent menu (Tools)
            __('Simple Page Builder', 'simple-page-builder'),
            __('Page Builder', 'simple-page-builder'),
            'manage_options', // Capability required
            'simple-page-builder',
            array($this, 'render_admin_page'),
            10
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'spb-admin-style',
            SPB_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            SPB_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'spb-admin-script',
            SPB_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery', 'clipboard'),
            SPB_VERSION,
            true
        );
        
        // Localize script for AJAX
        wp_localize_script('spb-admin-script', 'spb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_admin_nonce'),
            'copy_text' => __('Copied!', 'simple-page-builder'),
            'error_text' => __('An error occurred', 'simple-page-builder')
        ));
        
        // Enqueue WordPress components
        wp_enqueue_script('clipboard');
        wp_enqueue_style('wp-components');
    }
    
    public function handle_form_submissions() {
        // We'll handle API key generation, settings save, etc. here
        // This will be expanded in specific UI classes
    }
    
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'api-keys';
        $tabs = array(
            'api-keys' => __('API Keys', 'simple-page-builder'),
            'activity-log' => __('Activity Log', 'simple-page-builder'),
            'created-pages' => __('Created Pages', 'simple-page-builder'),
            'settings' => __('Settings', 'simple-page-builder'),
            'documentation' => __('Documentation', 'simple-page-builder')
        );
        
        ?>
        <div class="wrap spb-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab => $name): ?>
                    <a href="?page=simple-page-builder&tab=<?php echo esc_attr($tab); ?>"
                       class="nav-tab <?php echo $current_tab === $tab ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <div class="spb-admin-content">
                <?php
                // Load the appropriate template
                $template_file = SPB_PLUGIN_DIR . "templates/{$current_tab}.php";
                
                if (file_exists($template_file)) {
                    include $template_file;
                } else {
                    include SPB_PLUGIN_DIR . 'templates/api-keys.php';
                }
                ?>
            </div>
        </div>
        <?php
    }
}
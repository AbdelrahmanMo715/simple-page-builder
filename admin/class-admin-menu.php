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
        
        // REMOVE THIS LINE - it's causing the error
        // add_action('admin_init', array($this, 'handle_form_submissions'));
    }
    
    public function add_admin_menu() {
        $this->page_hook = add_submenu_page(
            'tools.php',
            __('Simple Page Builder', 'simple-page-builder'),
            __('Page Builder', 'simple-page-builder'),
            'manage_options',
            'simple-page-builder',
            array($this, 'render_admin_page'),
            10
        );
    }
    
    public function enqueue_admin_assets($hook) {
        if ($hook !== $this->page_hook) {
            return;
        }
        
        wp_enqueue_style(
            'spb-admin-style',
            SPB_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            SPB_VERSION
        );
        
        wp_enqueue_script(
            'spb-admin-script',
            SPB_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery', 'clipboard'),
            SPB_VERSION,
            true
        );
        
        wp_localize_script('spb-admin-script', 'spb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('spb_admin_nonce'),
            'copy_text' => __('Copied!', 'simple-page-builder'),
            'error_text' => __('An error occurred', 'simple-page-builder')
        ));
        
        wp_enqueue_script('clipboard');
        wp_enqueue_style('wp-components');
    }
    
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'api-keys';
        
        ?>
        <div class="wrap spb-admin-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=simple-page-builder&tab=api-keys"
                   class="nav-tab <?php echo $current_tab === 'api-keys' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('API Keys', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=activity-log"
                   class="nav-tab <?php echo $current_tab === 'activity-log' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Activity Log', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=created-pages"
                   class="nav-tab <?php echo $current_tab === 'created-pages' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Created Pages', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=settings"
                   class="nav-tab <?php echo $current_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'simple-page-builder'); ?>
                </a>
                <a href="?page=simple-page-builder&tab=documentation"
                   class="nav-tab <?php echo $current_tab === 'documentation' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Documentation', 'simple-page-builder'); ?>
                </a>
            </nav>
            
            <div class="spb-admin-content">
                <?php
                switch ($current_tab) {
                    case 'api-keys':
                        $ui = SPB_Api_Keys_UI::get_instance();
                        $ui->render_page();
                        break;
                        
                    case 'activity-log':
                        $ui = SPB_Activity_Log_UI::get_instance();
                        $ui->render_page();
                        break;
                        
                    case 'created-pages':
                        $ui = SPB_Pages_List_UI::get_instance();
                        $ui->render_page();
                        break;
                        
                    case 'settings':
                        $ui = SPB_Settings_UI::get_instance();
                        $ui->render_page();
                        break;
                        
                    case 'documentation':
                        $ui = SPB_Documentation_UI::get_instance();
                        $ui->render_page();
                        break;
                        
                    default:
                        $ui = SPB_Api_Keys_UI::get_instance();
                        $ui->render_page();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    // REMOVE THIS ENTIRE METHOD - it's empty and causing issues
    // public function handle_form_submissions() {
       // Empty method - remove it
    // }
}
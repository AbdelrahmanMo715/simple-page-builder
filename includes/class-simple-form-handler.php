<?php
class SPB_Simple_Form_Handler {
    
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'handle_form'));
    }
    
    public static function handle_form() {
        // Only process if on our plugin page
        if (!isset($_GET['page']) || $_GET['page'] !== 'simple-page-builder') {
            return;
        }
        
        // Only process if form was submitted
        if (!isset($_POST['spb_action']) || $_POST['spb_action'] !== 'generate_key') {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'spb_admin_action')) {
            wp_die(__('Security check failed.', 'simple-page-builder'));
        }
        
        // Process the form
        $keys_manager = SPB_Api_Keys_Manager::get_instance();
        $data = array(
            'key_name' => sanitize_text_field($_POST['key_name'] ?? ''),
            'expiration_days' => isset($_POST['expiration_days']) ? intval($_POST['expiration_days']) : 0,
            'rate_limit' => isset($_POST['rate_limit']) ? intval($_POST['rate_limit']) : 100
        );
        
        $result = $keys_manager->generate_api_key($data);
        
        if (is_wp_error($result)) {
            set_transient('spb_admin_error_' . get_current_user_id(), $result->get_error_message(), 30);
        } else {
            set_transient('spb_generated_key_' . get_current_user_id(), $result, 300);
        }
        
        // Redirect back
        wp_safe_redirect(add_query_arg(array(
            'page' => 'simple-page-builder',
            'tab' => 'api-keys',
            'generated' => is_wp_error($result) ? '0' : '1'
        ), admin_url('tools.php')));
        exit;
    }
}

// Initialize
SPB_Simple_Form_Handler::init();